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
