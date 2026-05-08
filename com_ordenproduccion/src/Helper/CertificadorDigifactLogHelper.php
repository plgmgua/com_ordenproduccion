<?php

/**
 * Persists Digifact HTTP calls (certify, auth, shared NIT/CUI) for audit and reuse.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.50
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class CertificadorDigifactLogHelper
{
    public const TABLE = '#__ordenproduccion_certificador_digifact_log';

    /**
     * Whether the log table exists (after migration 3.118.50).
     */
    public static function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $tn  = str_replace('#__', $db->getPrefix(), self::TABLE);
            $cache = \in_array($tn, $db->getTableList(), true);
        } catch (\Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /**
     * Redact Password in a JSON auth request body for storage.
     */
    public static function redactAuthPasswordInJson(string $json): string
    {
        $d = json_decode($json, true);
        if (!\is_array($d)) {
            return $json;
        }
        if (\array_key_exists('Password', $d)) {
            $d['Password'] = '***REDACTED***';
        }
        if (\array_key_exists('password', $d)) {
            $d['password'] = '***REDACTED***';
        }
        $out = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $out !== false ? $out : $json;
    }

    /**
     * Redact bearer-style secrets in JSON auth responses (Token) for storage.
     */
    public static function redactAuthTokensInJson(string $raw): string
    {
        $d = json_decode($raw, true);
        if (!\is_array($d)) {
            return $raw;
        }
        foreach (['Token', 'token', 'access_token', 'AccessToken', 'refresh_token', 'RefreshToken'] as $k) {
            if (!isset($d[$k])) {
                continue;
            }
            if (\is_string($d[$k]) && $d[$k] !== '') {
                $d[$k] = '***REDACTED***';
            }
        }
        $out = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $out !== false ? $out : $raw;
    }

    /**
     * Serialize optional query params for GET logging (no secrets expected).
     *
     * @param  array<string, mixed>  $queryParams
     */
    public static function formatSharedRequestBodyForLog(array $queryParams): string
    {
        $out = json_encode($queryParams, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $out !== false ? $out : '';
    }

    /**
     * @param  array<string, mixed>  $row  environment, operation, request_method, request_url, request_headers_json?,
     *                                     request_body?, response_http_code, response_body?, client_error?, duration_ms?,
     *                                     invoice_id?, quotation_id?, created_by? (optional Joomla user id for this row)
     */
    public static function record(array $row): void
    {
        if (!self::tableExists()) {
            return;
        }
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            if (\array_key_exists('created_by', $row)) {
                $uid = max(0, (int) $row['created_by']);
            } else {
                $user = Factory::getUser();
                $uid  = $user->guest ? 0 : (int) $user->id;
            }

            $inv = isset($row['invoice_id']) ? (int) $row['invoice_id'] : 0;
            $quo = isset($row['quotation_id']) ? (int) $row['quotation_id'] : 0;

            $values = [
                $db->quote(Factory::getDate()->toSql()),
                (string) (int) $uid,
                $db->quote(substr((string) ($row['environment'] ?? 'test'), 0, 8)),
                $db->quote(substr((string) ($row['operation'] ?? 'unknown'), 0, 48)),
                $db->quote(substr((string) ($row['request_method'] ?? 'GET'), 0, 16)),
                $db->quote((string) ($row['request_url'] ?? '')),
                $db->quote((string) ($row['request_headers_json'] ?? '')),
                $db->quote((string) ($row['request_body'] ?? '')),
                (string) (int) ($row['response_http_code'] ?? 0),
                $db->quote((string) ($row['response_body'] ?? '')),
                $db->quote((string) ($row['client_error'] ?? '')),
                (string) (int) ($row['duration_ms'] ?? 0),
                $inv > 0 ? (string) $inv : 'NULL',
                $quo > 0 ? (string) $quo : 'NULL',
            ];

            $query = $db->getQuery(true)
                ->insert($db->quoteName(self::TABLE))
                ->columns([
                    $db->quoteName('created'),
                    $db->quoteName('created_by'),
                    $db->quoteName('environment'),
                    $db->quoteName('operation'),
                    $db->quoteName('request_method'),
                    $db->quoteName('request_url'),
                    $db->quoteName('request_headers_json'),
                    $db->quoteName('request_body'),
                    $db->quoteName('response_http_code'),
                    $db->quoteName('response_body'),
                    $db->quoteName('client_error'),
                    $db->quoteName('duration_ms'),
                    $db->quoteName('invoice_id'),
                    $db->quoteName('quotation_id'),
                ])
                ->values(implode(',', $values));
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Avoid breaking FEL flow if logging fails
        }
    }
}
