<?php

/**
 * Queue for outbound Telegram messages (processed by cron URL or flushed after tests).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.108.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Persists messages in `#__ordenproduccion_telegram_queue` for async delivery.
 */
class TelegramQueueHelper
{
    /**
     * Queue table has mismatch anchor columns (3.109.23+).
     *
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  bool
     *
     * @since   3.109.23
     */
    public static function queueSupportsMismatchAnchorMeta($db): bool
    {
        static $cache;

        if ($cache !== null) {
            return $cache;
        }

        $cache = false;
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_telegram_queue', false);
            $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
            $cache = isset($cols['mismatch_anchor_payment_proof_id'], $cols['mismatch_anchor_joomla_user_id']);
        } catch (\Throwable $e) {
        }

        return $cache;
    }

    /**
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  bool
     */
    public static function telegramQueueTableExists($db): bool
    {
        static $ok;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $name   = $prefix . 'ordenproduccion_telegram_queue';
            $ok     = \in_array($name, $tables, true);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Whether the sent-log table exists (3.109.0+).
     *
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  bool
     */
    public static function telegramSentLogTableExists($db): bool
    {
        static $ok;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $name   = $prefix . 'ordenproduccion_telegram_sent_log';
            $ok     = \in_array($name, $tables, true);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Max rows returned for queue / sent lists in UI when no limit is passed (legacy / scripts).
     *
     * @since  3.109.1
     */
    public const DISPLAY_LIST_LIMIT = 200;

    /**
     * Rows per page on the Grimpsa bot Queue tab.
     *
     * @since  3.109.13
     */
    public const QUEUE_PAGE_SIZE = 10;

    /**
     * Pending queue rows (FIFO) for display.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   int|null                            $limit  Max rows (default DISPLAY_LIST_LIMIT, max 500)
     * @param   int                                 $start  Offset (for pagination)
     *
     * @return  array<int, object>
     *
     * @since   3.109.1
     */
    public static function getPendingQueueItemsForDisplay(DatabaseInterface $db, ?int $limit = null, int $start = 0): array
    {
        $limit = $limit ?? self::DISPLAY_LIST_LIMIT;
        $limit = max(1, min(500, $limit));
        $start = max(0, $start);

        if (!self::telegramQueueTableExists($db)) {
            return [];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_telegram_queue'))
                    ->order($db->quoteName('id') . ' ASC')
                    ->setLimit($limit, $start)
            );

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Sent log rows (newest first) for display.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db     Database
     * @param   int|null                            $limit  Max rows
     * @param   int                                 $start  Offset (for pagination)
     *
     * @return  array<int, object>
     *
     * @since   3.109.1
     */
    public static function getSentLogItemsForDisplay(DatabaseInterface $db, ?int $limit = null, int $start = 0): array
    {
        $limit = $limit ?? self::DISPLAY_LIST_LIMIT;
        $limit = max(1, min(500, $limit));
        $start = max(0, $start);

        if (!self::telegramSentLogTableExists($db)) {
            return [];
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_telegram_sent_log'))
                    ->order($db->quoteName('sent_at') . ' DESC')
                    ->setLimit($limit, $start)
            );

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  int
     *
     * @since   3.109.1
     */
    public static function countPendingQueue(DatabaseInterface $db): int
    {
        if (!self::telegramQueueTableExists($db)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_telegram_queue'))
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  int
     *
     * @since   3.109.1
     */
    public static function countSentLog(DatabaseInterface $db): int
    {
        if (!self::telegramSentLogTableExists($db)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_telegram_sent_log'))
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Record a successfully sent queue row, then caller deletes the queue row.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db   Database
     * @param   object $row  Queue row (id, chat_id, body, created)
     *
     * @return  void
     */
    public static function logSentFromQueueRow($db, object $row): void
    {
        if (!self::telegramSentLogTableExists($db)) {
            return;
        }

        $id     = (int) ($row->id ?? 0);
        $chatId = trim((string) ($row->chat_id ?? ''));
        $body   = (string) ($row->body ?? '');
        if ($chatId === '' || $body === '') {
            return;
        }

        $queuedCreated = (string) ($row->created ?? '');
        if ($queuedCreated === '') {
            $queuedCreated = Factory::getDate()->toSql();
        }

        try {
            $now = Factory::getDate()->toSql();
            $o   = (object) [
                'chat_id'         => $chatId,
                'body'            => $body,
                'queued_created'  => $queuedCreated,
                'sent_at'         => $now,
                'source_queue_id' => $id > 0 ? $id : 0,
            ];
            $db->insertObject('#__ordenproduccion_telegram_sent_log', $o);
        } catch (\Throwable $e) {
            try {
                \Joomla\CMS\Log\Log::add(
                    'Telegram sent log insert failed: ' . $e->getMessage(),
                    \Joomla\CMS\Log\Log::WARNING,
                    'com_ordenproduccion'
                );
            } catch (\Throwable $e2) {
            }
        }
    }

    /**
     * Enqueue a message. If the queue table is missing (pre-migration), sends synchronously using component token.
     *
     * @param   string  $chatId  Telegram chat id
     * @param   string  $body    UTF-8 text
     *
     * @return  bool  True if queued or sent
     */
    public static function enqueue(string $chatId, string $body): bool
    {
        $chatId = trim($chatId);
        $body   = (string) $body;
        if ($chatId === '' || $body === '') {
            return false;
        }
        if (TelegramNotificationHelper::normalizeTelegramChatId($chatId) === null) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return false;
        }

        if (!self::telegramQueueTableExists($db)) {
            return self::sendSyncFallback($chatId, $body);
        }

        try {
            $now = Factory::getDate()->toSql();
            $o   = (object) [
                'chat_id'    => $chatId,
                'body'       => $body,
                'attempts'   => 0,
                'created'    => $now,
                'last_try'   => null,
                'last_error' => null,
            ];
            $db->insertObject('#__ordenproduccion_telegram_queue', $o);

            return true;
        } catch (\Throwable $e) {
            return self::sendSyncFallback($chatId, $body);
        }
    }

    /**
     * Enqueue a mismatch-ticket anchor DM; cron registers Telegram message_id after send (3.109.23+).
     *
     * @param   string  $chatId          Telegram chat id
     * @param   string  $body            Message body
     * @param   int     $paymentProofId  Payment proof PK
     * @param   int     $joomlaUserId    Order-owner Joomla user (recipient)
     *
     * @return  bool  True if queued
     *
     * @since   3.109.23
     */
    public static function enqueueMismatchAnchor(string $chatId, string $body, int $paymentProofId, int $joomlaUserId): bool
    {
        $chatId         = trim($chatId);
        $body           = (string) $body;
        $paymentProofId = (int) $paymentProofId;
        $joomlaUserId   = (int) $joomlaUserId;
        if ($chatId === '' || $body === '' || $paymentProofId < 1 || $joomlaUserId < 1) {
            return false;
        }
        if (TelegramNotificationHelper::normalizeTelegramChatId($chatId) === null) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return false;
        }

        if (!self::telegramQueueTableExists($db)) {
            return false;
        }

        try {
            $now = Factory::getDate()->toSql();
            $o   = (object) [
                'chat_id'    => $chatId,
                'body'       => $body,
                'attempts'   => 0,
                'created'    => $now,
                'last_try'   => null,
                'last_error' => null,
            ];
            if (self::queueSupportsMismatchAnchorMeta($db)) {
                $o->mismatch_anchor_payment_proof_id = $paymentProofId;
                $o->mismatch_anchor_joomla_user_id   = $joomlaUserId;
            }
            $db->insertObject('#__ordenproduccion_telegram_queue', $o);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param   string  $chatId  Chat id
     * @param   string  $body    Body
     *
     * @return  bool
     */
    protected static function sendSyncFallback(string $chatId, string $body): bool
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            return false;
        }
        $res = TelegramApiHelper::sendMessage($token, $chatId, $body);

        return !empty($res['ok']);
    }

    /**
     * Send pending rows (FIFO). Deletes on success; increments attempts on failure (drops after 5 failures).
     *
     * @param   int  $limit  Max rows per run
     *
     * @return  int  Number of messages sent successfully
     */
    public static function processBatch(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!self::telegramQueueTableExists($db)) {
            return 0;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            return 0;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_telegram_queue'))
                ->order($db->quoteName('id') . ' ASC')
                ->setLimit($limit)
        );

        try {
            $rows = $db->loadObjectList();
        } catch (\Throwable $e) {
            return 0;
        }

        if (!$rows) {
            return 0;
        }

        $sent = 0;
        $now  = Factory::getDate()->toSql();

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id < 1) {
                continue;
            }

            $chatId = trim((string) ($row->chat_id ?? ''));
            $body   = (string) ($row->body ?? '');
            if ($chatId === '' || $body === '') {
                $db->setQuery('DELETE FROM ' . $db->quoteName('#__ordenproduccion_telegram_queue') . ' WHERE ' . $db->quoteName('id') . ' = ' . $id);
                $db->execute();

                continue;
            }

            $res = TelegramApiHelper::sendMessage($token, $chatId, $body);

            if (!empty($res['ok'])) {
                $anchorProof = (int) ($row->mismatch_anchor_payment_proof_id ?? 0);
                $anchorUser  = (int) ($row->mismatch_anchor_joomla_user_id ?? 0);
                if ($anchorProof > 0 && $anchorUser > 0 && isset($res['message_id']) && TelegramMismatchAnchorHelper::tableExists($db)) {
                    TelegramMismatchAnchorHelper::insertAnchor(
                        $chatId,
                        (int) $res['message_id'],
                        $anchorProof,
                        $anchorUser
                    );
                }
                self::logSentFromQueueRow($db, $row);
                $db->setQuery('DELETE FROM ' . $db->quoteName('#__ordenproduccion_telegram_queue') . ' WHERE ' . $db->quoteName('id') . ' = ' . $id);
                $db->execute();
                $sent++;
            } else {
                $attempts = (int) ($row->attempts ?? 0) + 1;
                $err      = \substr((string) ($res['description'] ?? $res['error'] ?? ''), 0, 1000);

                if ($attempts >= 5) {
                    $db->setQuery('DELETE FROM ' . $db->quoteName('#__ordenproduccion_telegram_queue') . ' WHERE ' . $db->quoteName('id') . ' = ' . $id);
                    $db->execute();
                } else {
                    $db->setQuery(
                        'UPDATE ' . $db->quoteName('#__ordenproduccion_telegram_queue')
                        . ' SET ' . $db->quoteName('attempts') . ' = ' . $attempts
                        . ', ' . $db->quoteName('last_try') . ' = ' . $db->quote($now)
                        . ', ' . $db->quoteName('last_error') . ' = ' . $db->quote($err)
                        . ' WHERE ' . $db->quoteName('id') . ' = ' . $id
                    );
                    $db->execute();
                }
            }
        }

        return $sent;
    }
}
