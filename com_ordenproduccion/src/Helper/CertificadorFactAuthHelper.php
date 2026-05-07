<?php

/**
 * FEL certifier (Digifact-style) authentication POST — request JWT Token.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.7
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * HTTP JSON auth against certificador URL de autenticación.
 */
class CertificadorFactAuthHelper
{
    /**
     * POST {"Username":"GT.{nit}.{usuario}","Password":"..."} to auth URL.
     *
     * @return  array{ok: bool, http_code: int, token: string, expira_en: string, otorgado_a: string, error: string, raw_excerpt: string}
     */
    public static function fetchAuthToken(string $authUrl, string $nit, string $usuario, string $password, int $timeoutSec = 30): array
    {
        $authUrl = trim($authUrl);
        $nit     = trim($nit);
        $usuario = trim($usuario);
        $password = (string) $password;

        $empty = [
            'ok'           => false,
            'http_code'    => 0,
            'token'        => '',
            'expira_en'    => '',
            'otorgado_a'   => '',
            'error'        => '',
            'raw_excerpt'  => '',
        ];

        if ($authUrl === '' || !filter_var($authUrl, FILTER_VALIDATE_URL)) {
            $empty['error'] = 'invalid_url';

            return $empty;
        }
        if ($nit === '' || $usuario === '') {
            $empty['error'] = 'missing_user_fields';

            return $empty;
        }
        if ($password === '') {
            $empty['error'] = 'missing_password';

            return $empty;
        }

        $username = 'GT.' . $nit . '.' . $usuario;
        $payload  = [
            'Username' => $username,
            'Password' => $password,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false) {
            $empty['error'] = 'json_encode_failed';

            return $empty;
        }

        $httpCode   = 0;
        $rawBody    = '';
        $curlErr    = '';

        if (\function_exists('curl_init')) {
            $ch = curl_init($authUrl);
            curl_setopt_array($ch, [
                \CURLOPT_POST           => true,
                \CURLOPT_POSTFIELDS    => $body,
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_FOLLOWLOCATION => true,
                \CURLOPT_CONNECTTIMEOUT => 15,
                \CURLOPT_TIMEOUT        => max(5, min(120, $timeoutSec)),
                \CURLOPT_SSL_VERIFYPEER => true,
                \CURLOPT_SSL_VERIFYHOST => 2,
                \CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $rawBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $curlErr = (string) curl_error($ch);
            curl_close($ch);

            if ($rawBody === false) {
                return [
                    'ok' => false,
                    'http_code' => $httpCode,
                    'token' => '',
                    'expira_en' => '',
                    'otorgado_a' => '',
                    'error' => $curlErr !== '' ? $curlErr : 'curl_exec_failed',
                    'raw_excerpt' => '',
                ];
            }
        } else {
            return [
                'ok'          => false,
                'http_code'   => 0,
                'token'       => '',
                'expira_en'   => '',
                'otorgado_a'  => '',
                'error'       => 'curl_required',
                'raw_excerpt' => '',
            ];
        }

        $rawBody = (string) $rawBody;
        $excerpt = self::excerptBody($rawBody);
        $decoded = json_decode($rawBody, true);

        if (!\is_array($decoded)) {
            return [
                'ok'          => false,
                'http_code'   => $httpCode,
                'token'       => '',
                'expira_en'   => '',
                'otorgado_a'  => '',
                'error'       => 'invalid_json',
                'raw_excerpt' => $excerpt,
            ];
        }

        $token = isset($decoded['Token']) ? trim((string) $decoded['Token']) : '';
        if ($token !== '' && ($httpCode === 200 || $httpCode === 201)) {
            return [
                'ok'          => true,
                'http_code'   => $httpCode,
                'token'       => $token,
                'expira_en'   => isset($decoded['expira_en']) ? trim((string) $decoded['expira_en']) : '',
                'otorgado_a'  => isset($decoded['otorgado_a']) ? trim((string) $decoded['otorgado_a']) : '',
                'error'       => '',
                'raw_excerpt' => '',
            ];
        }

        $msg = '';
        if (isset($decoded['message'])) {
            $msg = is_string($decoded['message']) ? $decoded['message'] : json_encode($decoded['message']);
        } elseif (isset($decoded['Message'])) {
            $msg = is_string($decoded['Message']) ? $decoded['Message'] : json_encode($decoded['Message']);
        } elseif (isset($decoded['error'])) {
            $msg = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
        }

        return [
            'ok'          => false,
            'http_code'   => $httpCode,
            'token'       => '',
            'expira_en'   => '',
            'otorgado_a'  => '',
            'error'       => $msg !== '' ? $msg : ('http_' . $httpCode),
            'raw_excerpt' => $excerpt,
        ];
    }

    protected static function excerptBody(string $raw, int $max = 400): string
    {
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? '';
        if (\function_exists('mb_substr')) {
            return mb_substr($raw, 0, $max, 'UTF-8');
        }

        return substr($raw, 0, $max);
    }

    /**
     * Parse API "expira_en" (e.g. "6/5/2026 12:22:08 PM") to Unix timestamp (UTC).
     *
     * @return  int|null  null if unparseable
     *
     * @since   3.118.8
     */
    public static function parseExpiraEnToUnixUtc(string $expiraEn): ?int
    {
        $expiraEn = trim($expiraEn);
        if ($expiraEn === '') {
            return null;
        }

        $tryZones = ['America/Guatemala', 'UTC'];
        $appOffset = null;
        try {
            $appOffset = Factory::getApplication()->get('offset');
        } catch (\Throwable $e) {
            $appOffset = null;
        }
        if ($appOffset !== null && $appOffset !== '') {
            $tryZones[] = (string) $appOffset;
        }
        $tryZones = array_values(array_unique($tryZones));

        $formats = [
            'n/j/Y g:i:s A',
            'n/j/Y g:i A',
            'j/n/Y g:i:s A',
            'j/n/Y g:i A',
            'Y-m-d H:i:s',
            \DateTimeInterface::ATOM,
        ];

        foreach ($tryZones as $zoneStr) {
            try {
                $tz = new \DateTimeZone($zoneStr);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($formats as $fmt) {
                $dt = \DateTimeImmutable::createFromFormat($fmt, $expiraEn, $tz);
                if ($dt instanceof \DateTimeImmutable) {
                    return $dt->setTimezone(new \DateTimeZone('UTC'))->getTimestamp();
                }
            }
        }

        $ts = strtotime($expiraEn);
        if ($ts !== false) {
            return $ts;
        }

        return null;
    }
}
