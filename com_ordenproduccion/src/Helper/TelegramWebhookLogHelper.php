<?php

/**
 * Inbound Telegram Bot API webhook request log (`#__ordenproduccion_telegram_webhook_log`).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.109.41
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Persists one row per webhook HTTP attempt (never throws to callers).
 */
class TelegramWebhookLogHelper
{
    /**
     * Rows per page on the Grimpsabot «Webhook log» tab.
     */
    public const LOG_PAGE_SIZE = 25;

    /**
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  bool
     */
    public static function tableExists(DatabaseInterface $db): bool
    {
        static $ok;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $name   = $prefix . 'ordenproduccion_telegram_webhook_log';
            $ok     = \in_array($name, $tables, true);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Insert a log row. Safe to call on every webhook exit; failures are swallowed.
     *
     * @param   array<string,mixed>  $row  Keys: http_method, ip, user_agent, body_length, secret_header_present,
     *                                     secret_valid, http_status, outcome, update_id?, chat_id?, text_preview?
     *
     * @return  void
     */
    public static function record(array $row): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return;
        }

        if (!self::tableExists($db)) {
            return;
        }

        $ip = self::truncate((string) ($row['ip'] ?? ''), 64);
        $ua = self::truncate((string) ($row['user_agent'] ?? ''), 512);

        $outcome = self::truncate((string) ($row['outcome'] ?? ''), 80);
        if ($outcome === '') {
            $outcome = 'unknown';
        }

        $o = (object) [
            'created'               => Factory::getDate()->toSql(),
            'ip'                    => $ip,
            'user_agent'            => $ua,
            'http_method'           => self::truncate((string) ($row['http_method'] ?? ''), 8),
            'body_length'           => (int) ($row['body_length'] ?? 0),
            'secret_header_present' => !empty($row['secret_header_present']) ? 1 : 0,
            'secret_valid'          => !empty($row['secret_valid']) ? 1 : 0,
            'http_status'           => (int) ($row['http_status'] ?? 0),
            'outcome'               => $outcome,
        ];

        if (isset($row['update_id'])) {
            $uid = (int) $row['update_id'];
            if ($uid > 0) {
                $o->update_id = $uid;
            }
        }

        if (!empty($row['chat_id'])) {
            $cid = trim((string) $row['chat_id']);
            $o->chat_id = self::truncate($cid, 32);
        }

        if (!empty($row['text_preview'])) {
            $o->text_preview = self::truncate(self::sanitizePreview((string) $row['text_preview']), 512);
        }

        try {
            $db->insertObject('#__ordenproduccion_telegram_webhook_log', $o);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @return  int
     */
    public static function countRows(DatabaseInterface $db): int
    {
        if (!self::tableExists($db)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_telegram_webhook_log'))
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return  array<int, object>
     */
    public static function getRowsForDisplay(DatabaseInterface $db, int $limit, int $start): array
    {
        if (!self::tableExists($db) || $limit < 1) {
            return [];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_telegram_webhook_log'))
                    ->order($db->quoteName('id') . ' DESC'),
                $start,
                $limit
            );

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Client IP (first X-Forwarded-For hop when present).
     *
     * @return  string
     */
    public static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $xff   = trim((string) ($parts[0] ?? ''));
            if ($xff !== '') {
                $ip = $xff;
            }
        }

        return self::truncate($ip, 64);
    }

    /**
     * @return  string
     */
    public static function clientUserAgent(): string
    {
        return self::truncate(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 512);
    }

    /**
     * Short plain-text preview for logging (no HTML).
     */
    public static function sanitizePreview(string $text): string
    {
        $text = trim(strip_tags($text));
        $text = \preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return trim((string) \preg_replace('/\s+/u', ' ', $text));
    }

    private static function truncate(string $s, int $max): string
    {
        if ($max < 1) {
            return '';
        }
        if (\function_exists('mb_strlen') && \mb_strlen($s) > $max) {
            return \mb_substr($s, 0, $max - 1) . '…';
        }
        if (\strlen($s) > $max) {
            return \substr($s, 0, $max - 1) . '…';
        }

        return $s;
    }
}
