<?php

/**
 * Order-owner Telegram notifications (invoice created / envío issued).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.105.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Dispatches Telegram alerts when enabled in component params and user has chat_id.
 */
class TelegramNotificationHelper
{
    public const EVENT_INVOICE = 'invoice';

    public const EVENT_ENVIO   = 'envio';

    /**
     * After a new invoice row is stored: notify linked order owner(s).
     *
     * @param   int  $invoiceId  Primary key of #__ordenproduccion_invoices
     *
     * @return  void
     */
    public static function notifyInvoiceCreated(int $invoiceId): void
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1) {
            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return;
        }
        if ((int) $params->get('telegram_notify_invoice', 0) !== 1) {
            return;
        }

        $token = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            return;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_invoices'))
                    ->where($db->quoteName('id') . ' = ' . $invoiceId)
                    ->where($db->quoteName('state') . ' = 1')
            );
            $invoice = $db->loadObject();
        } catch (\Throwable $e) {
            return;
        }

        if (!$invoice) {
            return;
        }

        $recipientIds = self::collectRecipientUserIdsForInvoice($invoice);
        if ($recipientIds === []) {
            return;
        }

        $num   = trim((string) ($invoice->invoice_number ?? ''));
        $amt   = isset($invoice->invoice_amount) ? number_format((float) $invoice->invoice_amount, 2, '.', '') : '';
        $title = 'Factura: ' . ($num !== '' ? $num : '#' . $invoiceId);
        $lines = [
            $title,
            'Monto: ' . $amt,
            'Origen: sistema (nueva factura o importación)',
        ];

        foreach ($recipientIds as $uid) {
            self::sendToUserId($token, (int) $uid, implode("\n", $lines));
        }
    }

    /**
     * After envío (shipping slip) is issued for an order.
     *
     * @param   int     $ordenId         Work order id
     * @param   string  $tipoEnvio       e.g. completo / parcial
     * @param   string  $tipoMensajeria  e.g. propio
     *
     * @return  void
     */
    public static function notifyEnvioIssued(int $ordenId, string $tipoEnvio = '', string $tipoMensajeria = ''): void
    {
        $ordenId = (int) $ordenId;
        if ($ordenId < 1) {
            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return;
        }
        if ((int) $params->get('telegram_notify_envio', 0) !== 1) {
            return;
        }

        $token = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            return;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('id') . ' = ' . $ordenId)
                    ->where($db->quoteName('state') . ' = 1')
            );
            $orden = $db->loadObject();
        } catch (\Throwable $e) {
            return;
        }

        if (!$orden) {
            return;
        }

        $uid = self::resolveOwnerUserIdFromOrden($orden);
        if ($uid === null) {
            return;
        }

        $ot = trim((string) ($orden->orden_de_trabajo ?? ''));
        $lines = [
            'Envío emitido (orden ' . ($ot !== '' ? $ot : '#' . $ordenId) . ')',
            'Tipo: ' . ($tipoEnvio !== '' ? $tipoEnvio : '—'),
            'Mensajería: ' . ($tipoMensajeria !== '' ? $tipoMensajeria : '—'),
        ];

        self::sendToUserId($token, $uid, implode("\n", $lines));
    }

    /**
     * @param   object  $invoice  Row from #__ordenproduccion_invoices
     *
     * @return  int[]  Distinct user ids
     */
    public static function collectRecipientUserIdsForInvoice(object $invoice): array
    {
        $ids    = [];
        $invId  = (int) ($invoice->id ?? 0);
        $seen   = [];

        try {
            $orderIds = AccessHelper::getOrderIdsLinkedToInvoice($invId);
        } catch (\Throwable $e) {
            $orderIds = [];
        }

        if ($orderIds !== []) {
            try {
                $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                foreach ($orderIds as $oid) {
                    $oid = (int) $oid;
                    if ($oid < 1) {
                        continue;
                    }
                    $db->setQuery(
                        $db->getQuery(true)
                            ->select('*')
                            ->from($db->quoteName('#__ordenproduccion_ordenes'))
                            ->where($db->quoteName('id') . ' = ' . $oid)
                            ->where($db->quoteName('state') . ' = 1')
                    );
                    $orden = $db->loadObject();
                    if (!$orden) {
                        continue;
                    }
                    $uid = self::resolveOwnerUserIdFromOrden($orden);
                    if ($uid !== null && empty($seen[$uid])) {
                        $seen[$uid] = true;
                        $ids[] = $uid;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($ids === []) {
            $uid = self::resolveOwnerUserIdFromInvoiceRow($invoice);
            if ($uid !== null) {
                $ids[] = $uid;
            }
        }

        return $ids;
    }

    /**
     * Owner: sales_agent Joomla user name match, else created_by.
     */
    public static function resolveOwnerUserIdFromOrden(object $orden): ?int
    {
        $sales = trim((string) ($orden->sales_agent ?? ''));
        if ($sales !== '') {
            $uid = self::findUserIdByExactName($sales);
            if ($uid !== null) {
                return $uid;
            }
        }

        $cb = (int) ($orden->created_by ?? 0);

        return $cb > 0 ? $cb : null;
    }

    /**
     * When no orden link: match invoice.sales_agent to a user name.
     */
    public static function resolveOwnerUserIdFromInvoiceRow(object $invoice): ?int
    {
        $sales = trim((string) ($invoice->sales_agent ?? ''));
        if ($sales === '') {
            return null;
        }

        return self::findUserIdByExactName($sales);
    }

    public static function findUserIdByExactName(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote($name))
                    ->where($db->quoteName('block') . ' = 0')
            );
            $id = (int) $db->loadResult();

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve Telegram chat_id for a Joomla user.
     *
     * Order: (1) component table `#__ordenproduccion_telegram_users`;
     * (2) Users → custom fields `telegram_chat_id` or `telegram-chat-id` (com_fields).
     *
     * @return  string|null  chat_id or null
     */
    public static function getChatIdForUser(int $userId): ?string
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            if (self::telegramUsersTableExists($db)) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('chat_id'))
                        ->from($db->quoteName('#__ordenproduccion_telegram_users'))
                        ->where($db->quoteName('user_id') . ' = ' . $userId)
                );
                $cid = trim((string) $db->loadResult());
                $ok  = self::normalizeTelegramChatId($cid);
                if ($ok !== null) {
                    return $ok;
                }
            }
        } catch (\Throwable $e) {
        }

        return self::getTelegramChatIdFromUserCustomFields($userId);
    }

    /**
     * Valid numeric Telegram chat id (private / group), trimmed.
     *
     * @param   string  $raw  Raw value
     *
     * @return  string|null  Normalized digits or null
     */
    public static function normalizeTelegramChatId(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^-?\d{1,32}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * Read chat_id from Joomla user custom fields (Users → Fields).
     * Tries field **name** `telegram_chat_id` then `telegram-chat-id`.
     *
     * @param   int  $userId  Joomla user id
     *
     * @return  string|null
     */
    public static function getTelegramChatIdFromUserCustomFields(int $userId): ?string
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return null;
        }

        try {
            $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
            if ($user->guest || (int) $user->id !== $userId) {
                return null;
            }

            foreach (['telegram_chat_id', 'telegram-chat-id'] as $fieldName) {
                $raw = CotizacionPdfHelper::getUserCustomField($user, $fieldName);
                $ok  = self::normalizeTelegramChatId((string) $raw);
                if ($ok !== null) {
                    return $ok;
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * @param   \Joomla\Database\DatabaseInterface  $db  Database
     *
     * @return  bool
     */
    public static function telegramUsersTableExists($db): bool
    {
        static $ok;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $name   = $prefix . 'ordenproduccion_telegram_users';
            $ok     = \in_array($name, $tables, true);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * @param   string  $token  Bot token
     * @param   int     $userId Joomla user id
     * @param   string  $text   Message
     *
     * @return  void
     */
    protected static function sendToUserId(string $token, int $userId, string $text): void
    {
        $chatId = self::getChatIdForUser($userId);
        if ($chatId === null) {
            return;
        }

        TelegramApiHelper::sendMessage($token, $chatId, $text);
    }
}
