<?php

/**
 * Maps outbound Telegram "anchor" DMs to payment mismatch tickets (reply-to-comment flow).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.109.22
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Registry: (chat_id, message_id) → payment_proof_id for Telegram reply handling.
 */
class TelegramMismatchAnchorHelper
{
    /**
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  bool
     */
    public static function tableExists($db): bool
    {
        static $ok;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $name   = $prefix . 'ordenproduccion_telegram_mismatch_anchor';
            $ok     = \in_array($name, $tables, true);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Store anchor after a successful sendMessage (private chat).
     *
     * @param   string  $chatId          Telegram chat id
     * @param   int     $messageId       Telegram message_id from API
     * @param   int     $paymentProofId  Payment proof PK
     * @param   int     $joomlaUserId    Recipient Joomla user id
     *
     * @return  bool
     */
    public static function insertAnchor(string $chatId, int $messageId, int $paymentProofId, int $joomlaUserId): bool
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $messageId < 1 || $paymentProofId < 1 || $joomlaUserId < 1) {
            return false;
        }

        $norm = TelegramNotificationHelper::normalizeTelegramChatId($chatId);
        if ($norm === null) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return false;
        }

        if (!self::tableExists($db)) {
            return false;
        }

        try {
            $now = Factory::getDate()->toSql();
            $o   = (object) [
                'chat_id'           => $norm,
                'message_id'        => $messageId,
                'payment_proof_id'  => $paymentProofId,
                'joomla_user_id'    => $joomlaUserId,
                'created'           => $now,
            ];
            $db->insertObject('#__ordenproduccion_telegram_mismatch_anchor', $o, 'id');
        } catch (\Throwable $e) {
            try {
                \Joomla\CMS\Log\Log::add(
                    'Telegram mismatch anchor insert failed: ' . $e->getMessage(),
                    \Joomla\CMS\Log\Log::WARNING,
                    'com_ordenproduccion'
                );
            } catch (\Throwable $e2) {
            }

            return false;
        }

        return true;
    }

    /**
     * Resolve payment proof from reply_to message in the same chat.
     *
     * @param   string  $chatId           Inbound message chat.id
     * @param   int     $replyToMessageId reply_to_message.message_id
     *
     * @return  int  payment_proof_id or 0
     */
    public static function findPaymentProofIdByReply(string $chatId, int $replyToMessageId): int
    {
        $chatId = trim($chatId);
        if ($chatId === '' || $replyToMessageId < 1) {
            return 0;
        }

        $norm = TelegramNotificationHelper::normalizeTelegramChatId($chatId);
        if ($norm === null) {
            return 0;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!self::tableExists($db)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('payment_proof_id'))
                    ->from($db->quoteName('#__ordenproduccion_telegram_mismatch_anchor'))
                    ->where($db->quoteName('chat_id') . ' = ' . $db->quote($norm))
                    ->where($db->quoteName('message_id') . ' = ' . $replyToMessageId)
            );
            $pid = (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }

        return $pid > 0 ? $pid : 0;
    }

    /**
     * When the anchor registry row is missing (e.g. queue sent before 3.109.23 columns, or cron lag),
     * resolve proof id from the bot DM body the user replied to — same label as notifyMismatchTicketAnchors
     * ("PA-000222" → 222).
     *
     * @param   array<string,mixed>|null  $replyTo  Telegram reply_to_message object
     *
     * @return  int  payment_proof_id or 0
     *
     * @since   3.109.42
     */
    public static function parsePaymentProofIdFromReplyMessage(?array $replyTo): int
    {
        if (!\is_array($replyTo)) {
            return 0;
        }

        $blob = '';
        foreach (['text', 'caption'] as $k) {
            if (!empty($replyTo[$k])) {
                $blob .= (string) $replyTo[$k] . "\n";
            }
        }
        $blob = trim($blob);
        if ($blob === '') {
            return 0;
        }

        if (\preg_match('/\bPA-(\d{1,10})\b/i', $blob, $m)) {
            $id = (int) $m[1];

            return $id > 0 ? $id : 0;
        }

        return 0;
    }
}
