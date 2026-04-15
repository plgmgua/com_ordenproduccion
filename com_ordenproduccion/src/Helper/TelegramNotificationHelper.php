<?php

/**
 * Order-owner Telegram notifications (invoice, envío, payment proofs).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.105.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Dispatches Telegram alerts when enabled in component params and user has chat_id.
 */
class TelegramNotificationHelper
{
    public const EVENT_INVOICE = 'invoice';

    public const EVENT_ENVIO   = 'envio';

    public const EVENT_PAYMENT_PROOF_ENTERED = 'proof_entered';

    public const EVENT_PAYMENT_PROOF_VERIFIED = 'proof_verified';

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

        // DMs only to Joomla users resolved as owners of linked órdenes (not importer / not invoice-only sales_agent).
        $recipientIds = self::collectRecipientUserIdsForInvoice($invoice);

        // FEL/XML imports often have no orden link — still queue channel if enabled.
        if ($recipientIds === [] && !self::shouldBroadcastToChannel($params, self::EVENT_INVOICE)) {
            return;
        }

        $dmTemplate      = self::getInvoiceMessageTemplate($params);
        $channelTemplate = self::getInvoiceChannelMessageTemplate($params);
        $users           = Factory::getContainer()->get(UserFactoryInterface::class);

        $firstChannelVars = null;
        $recipientNames   = [];

        foreach ($recipientIds as $uid) {
            $uid = (int) $uid;
            if ($uid < 1) {
                continue;
            }
            try {
                $recipient = $users->loadUserById($uid);
            } catch (\Throwable $e) {
                continue;
            }
            if ($recipient->guest) {
                continue;
            }

            $vars = self::buildInvoiceTemplateVars($invoice, $recipient, $invoiceId);
            $text = self::replaceTemplatePlaceholders($dmTemplate, $vars);
            if ($firstChannelVars === null) {
                $firstChannelVars = $vars;
            }
            $recipientNames[] = trim((string) $recipient->name);
            self::sendToUserId($token, $uid, $text);
        }

        $channelVars = $firstChannelVars;
        if ($channelVars === null) {
            $channelVars = self::buildInvoiceTemplateVarsChannelFallback($invoice, $invoiceId);
        }

        if ($channelVars !== null && self::shouldBroadcastToChannel($params, self::EVENT_INVOICE)) {
            $channelBody = self::replaceTemplatePlaceholders($channelTemplate, $channelVars);
            $uniqueNames = \array_unique(\array_filter($recipientNames, static fn ($n) => $n !== ''));
            if (\count($uniqueNames) > 1) {
                self::ensureTelegramLanguageLoaded();
                $channelBody .= "\n\n" . Text::sprintf(
                    'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_RECIPIENTS_LINE',
                    \implode(', ', $uniqueNames)
                );
            }
            self::sendToAdministracionBroadcastChannel($params, $channelBody, self::EVENT_INVOICE);
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

        $uid         = self::resolveOwnerUserIdFromOrden($orden);
        $recipient   = null;
        if ($uid !== null && $uid > 0) {
            try {
                $u = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($uid);
                if (!$u->guest && (int) $u->id === $uid) {
                    $recipient = $u;
                }
            } catch (\Throwable $e) {
            }
        }

        $dmTemplate      = self::getEnvioMessageTemplate($params);
        $channelTemplate = self::getEnvioChannelMessageTemplate($params);
        $vars     = self::buildEnvioTemplateVars($orden, $recipient, $ordenId, $tipoEnvio, $tipoMensajeria);
        $text     = self::replaceTemplatePlaceholders($dmTemplate, $vars);

        // DM only to the orden owner when they have linked Telegram (not to order creator if owner differs).
        if ($uid !== null && $uid > 0 && self::getChatIdForUser($uid) !== null) {
            self::sendToUserId($token, $uid, $text);
        }

        $channelText = self::replaceTemplatePlaceholders($channelTemplate, $vars);
        self::sendToAdministracionBroadcastChannel($params, $channelText, self::EVENT_ENVIO);
    }

    /**
     * After a new payment proof is saved (ingresado).
     *
     * @param   int  $proofId  Primary key of #__ordenproduccion_payment_proofs
     *
     * @return  void
     */
    public static function notifyPaymentProofEntered(int $proofId): void
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return;
        }
        if ((int) $params->get('telegram_notify_payment_proof_entered', 0) !== 1) {
            return;
        }

        $token = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            return;
        }

        $proof = self::loadPaymentProofRow($proofId);
        if (!$proof) {
            return;
        }

        $recipientIds = self::collectRecipientUserIdsForPaymentProof($proofId);
        if ($recipientIds === []) {
            return;
        }

        $dmTemplate      = self::getPaymentProofEnteredMessageTemplate($params);
        $channelTemplate = self::getPaymentProofEnteredChannelMessageTemplate($params);
        $users           = Factory::getContainer()->get(UserFactoryInterface::class);

        $firstChannelVars = null;
        $recipientNames    = [];

        foreach ($recipientIds as $uid) {
            $uid = (int) $uid;
            if ($uid < 1) {
                continue;
            }
            try {
                $recipient = $users->loadUserById($uid);
            } catch (\Throwable $e) {
                continue;
            }
            if ($recipient->guest) {
                continue;
            }

            $vars = self::buildPaymentProofTemplateVars($proof, $recipient, $proofId, null, false);
            $text = self::replaceTemplatePlaceholders($dmTemplate, $vars);
            if ($firstChannelVars === null) {
                $firstChannelVars = $vars;
            }
            $recipientNames[] = trim((string) $recipient->name);
            self::sendToUserId($token, $uid, $text);
        }

        if ($firstChannelVars !== null) {
            $channelBody = self::replaceTemplatePlaceholders($channelTemplate, $firstChannelVars);
            $uniqueNames = \array_unique(\array_filter($recipientNames, static fn ($n) => $n !== ''));
            if (\count($uniqueNames) > 1) {
                self::ensureTelegramLanguageLoaded();
                $channelBody .= "\n\n" . Text::sprintf(
                    'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_RECIPIENTS_LINE',
                    \implode(', ', $uniqueNames)
                );
            }
            self::sendToAdministracionBroadcastChannel($params, $channelBody, self::EVENT_PAYMENT_PROOF_ENTERED);
        }
    }

    /**
     * After a payment proof is marked Verificado (directly or via approval workflow).
     *
     * @param   int      $proofId           Payment proof PK
     * @param   int      $verifiedByUserId  User who verified (0 if unknown)
     *
     * @return  void
     */
    public static function notifyPaymentProofVerified(int $proofId, int $verifiedByUserId = 0): void
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return;
        }
        if ((int) $params->get('telegram_notify_payment_proof_verified', 0) !== 1) {
            return;
        }

        $token = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            return;
        }

        $proof = self::loadPaymentProofRow($proofId);
        if (!$proof) {
            return;
        }

        $recipientIds = self::collectRecipientUserIdsForPaymentProof($proofId);
        if ($recipientIds === []) {
            return;
        }

        $dmTemplate      = self::getPaymentProofVerifiedMessageTemplate($params);
        $channelTemplate = self::getPaymentProofVerifiedChannelMessageTemplate($params);
        $users           = Factory::getContainer()->get(UserFactoryInterface::class);

        $firstChannelVars = null;
        $recipientNames    = [];

        foreach ($recipientIds as $uid) {
            $uid = (int) $uid;
            if ($uid < 1) {
                continue;
            }
            try {
                $recipient = $users->loadUserById($uid);
            } catch (\Throwable $e) {
                continue;
            }
            if ($recipient->guest) {
                continue;
            }

            $vars = self::buildPaymentProofTemplateVars($proof, $recipient, $proofId, $verifiedByUserId, true);
            $text = self::replaceTemplatePlaceholders($dmTemplate, $vars);
            if ($firstChannelVars === null) {
                $firstChannelVars = $vars;
            }
            $recipientNames[] = trim((string) $recipient->name);
            self::sendToUserId($token, $uid, $text);
        }

        if ($firstChannelVars !== null) {
            $channelBody = self::replaceTemplatePlaceholders($channelTemplate, $firstChannelVars);
            $uniqueNames = \array_unique(\array_filter($recipientNames, static fn ($n) => $n !== ''));
            if (\count($uniqueNames) > 1) {
                self::ensureTelegramLanguageLoaded();
                $channelBody .= "\n\n" . Text::sprintf(
                    'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_RECIPIENTS_LINE',
                    \implode(', ', $uniqueNames)
                );
            }
            self::sendToAdministracionBroadcastChannel($params, $channelBody, self::EVENT_PAYMENT_PROOF_VERIFIED);
        }
    }

    /**
     * Whether the Administración channel should receive this event type (checkboxes + chat id).
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     * @param   string                    $event   Event constant (invoice, envio, proof_entered, proof_verified)
     *
     * @return  bool
     */
    public static function shouldBroadcastToChannel($params, string $event): bool
    {
        if (!$params instanceof Registry) {
            return false;
        }

        $chatId = trim((string) $params->get('telegram_broadcast_chat_id', ''));
        if ($chatId === '' || self::normalizeTelegramChatId($chatId) === null) {
            return false;
        }

        $arr    = $params->toArray();
        $legacy = !empty($arr['telegram_broadcast_enabled']) && (int) $arr['telegram_broadcast_enabled'] === 1;

        if ($event === self::EVENT_INVOICE) {
            if (\array_key_exists('telegram_broadcast_invoice', $arr)) {
                return (int) ($arr['telegram_broadcast_invoice'] ?? 0) === 1;
            }

            return $legacy;
        }

        if ($event === self::EVENT_ENVIO) {
            if (\array_key_exists('telegram_broadcast_envio', $arr)) {
                return (int) ($arr['telegram_broadcast_envio'] ?? 0) === 1;
            }

            return $legacy;
        }

        if ($event === self::EVENT_PAYMENT_PROOF_ENTERED) {
            return \array_key_exists('telegram_broadcast_payment_proof_entered', $arr)
                && (int) ($arr['telegram_broadcast_payment_proof_entered'] ?? 0) === 1;
        }

        if ($event === self::EVENT_PAYMENT_PROOF_VERIFIED) {
            return \array_key_exists('telegram_broadcast_payment_proof_verified', $arr)
                && (int) ($arr['telegram_broadcast_payment_proof_verified'] ?? 0) === 1;
        }

        return false;
    }

    /**
     * Post the same alert to the Administración Telegram channel (optional component param).
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     * @param   string                    $body    Message body (same as DM, without channel prefix)
     * @param   string                    $event   Event constant (see class constants)
     *
     * @return  void
     */
    public static function sendToAdministracionBroadcastChannel($params, string $body, string $event): void
    {
        if (!$params instanceof Registry) {
            return;
        }
        if ($body === '') {
            return;
        }
        if (!self::shouldBroadcastToChannel($params, $event)) {
            return;
        }

        $chatId = trim((string) $params->get('telegram_broadcast_chat_id', ''));
        if ($chatId === '' || self::normalizeTelegramChatId($chatId) === null) {
            return;
        }

        self::ensureTelegramLanguageLoaded();

        $pfxKey = match ($event) {
            self::EVENT_ENVIO => 'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_PREFIX_ENVIO',
            self::EVENT_PAYMENT_PROOF_ENTERED => 'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_PREFIX_PAYMENT_PROOF_ENTERED',
            self::EVENT_PAYMENT_PROOF_VERIFIED => 'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_PREFIX_PAYMENT_PROOF_VERIFIED',
            default => 'COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_PREFIX_INVOICE',
        };
        $full = Text::_($pfxKey) . $body;

        TelegramQueueHelper::enqueue($chatId, $full);
    }

    /**
     * Replace {placeholder} in template. Unknown keys are left unchanged.
     *
     * @param   string               $template  Message with {key} tokens
     * @param   array<string,string>  $vars     key => value (no braces in keys)
     *
     * @return  string
     */
    public static function replaceTemplatePlaceholders(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            $out = str_replace('{' . $key . '}', (string) $value, $out);
        }

        return $out;
    }

    /**
     * Load site + admin com_ordenproduccion language (needed for controller tasks that skip the view).
     *
     * @return  void
     */
    public static function ensureTelegramLanguageLoaded(): void
    {
        try {
            $app  = Factory::getApplication();
            $lang = $app->getLanguage();
            $tag  = $lang->getTag();
            $lang->load('com_ordenproduccion', JPATH_SITE, $tag, true);
            $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion', $tag, true);
            $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', $tag, true);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Invoice message body from component params, or language default.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getInvoiceMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_message_invoice', ''));
        }

        return $t !== '' ? $t : Text::_('COM_ORDENPRODUCCION_TELEGRAM_TPL_INVOICE_DEFAULT');
    }

    /**
     * Invoice message body for Administración channel; falls back to DM template.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getInvoiceChannelMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_broadcast_message_invoice', ''));
        }
        if ($t !== '') {
            return $t;
        }

        return self::getInvoiceMessageTemplate($params);
    }

    /**
     * Envío message body from component params, or language default.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getEnvioMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_message_envio', ''));
        }

        return $t !== '' ? $t : Text::_('COM_ORDENPRODUCCION_TELEGRAM_TPL_ENVIO_DEFAULT');
    }

    /**
     * Envío message body for Administración channel; falls back to DM template.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getEnvioChannelMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_broadcast_message_envio', ''));
        }
        if ($t !== '') {
            return $t;
        }

        return self::getEnvioMessageTemplate($params);
    }

    /**
     * Payment proof entered — DM body from params or language default.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getPaymentProofEnteredMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_message_payment_proof_entered', ''));
        }

        return $t !== '' ? $t : Text::_('COM_ORDENPRODUCCION_TELEGRAM_TPL_PAYMENT_PROOF_ENTERED_DEFAULT');
    }

    /**
     * Payment proof entered — channel body; falls back to DM template.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getPaymentProofEnteredChannelMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_broadcast_message_payment_proof_entered', ''));
        }
        if ($t !== '') {
            return $t;
        }

        return self::getPaymentProofEnteredMessageTemplate($params);
    }

    /**
     * Payment proof verified — DM body from params or language default.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getPaymentProofVerifiedMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_message_payment_proof_verified', ''));
        }

        return $t !== '' ? $t : Text::_('COM_ORDENPRODUCCION_TELEGRAM_TPL_PAYMENT_PROOF_VERIFIED_DEFAULT');
    }

    /**
     * Payment proof verified — channel body; falls back to DM template.
     *
     * @param   \Joomla\Registry\Registry  $params  Component params
     *
     * @return  string
     */
    public static function getPaymentProofVerifiedChannelMessageTemplate($params): string
    {
        self::ensureTelegramLanguageLoaded();

        $t = '';
        if ($params instanceof Registry) {
            $t = trim((string) $params->get('telegram_broadcast_message_payment_proof_verified', ''));
        }
        if ($t !== '') {
            return $t;
        }

        return self::getPaymentProofVerifiedMessageTemplate($params);
    }

    /**
     * Variables for invoice notification (one recipient).
     *
     * @param   object  $invoice    Row from #__ordenproduccion_invoices
     * @param   User    $recipient  Notify this user
     * @param   int     $invoiceId  Invoice PK
     *
     * @return  array<string,string>
     */
    public static function buildInvoiceTemplateVars(object $invoice, User $recipient, int $invoiceId): array
    {
        $invoiceId = (int) $invoiceId;
        $amount    = isset($invoice->invoice_amount) ? number_format((float) $invoice->invoice_amount, 2, '.', '') : '0.00';
        $currency  = trim((string) ($invoice->currency ?? 'Q'));

        [$ordenIds, $ordenLabels] = self::collectOrdenIdsAndLabelsForInvoice($invoice, $invoiceId);

        $site = '';
        try {
            $site = (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable $e) {
        }

        return [
            'username'         => trim((string) $recipient->name),
            'user_login'       => trim((string) $recipient->username),
            'invoice_id'       => (string) $invoiceId,
            'invoice_number'   => trim((string) ($invoice->invoice_number ?? '')),
            'invoice_amount'   => $amount,
            'currency'         => $currency,
            'client_name'      => trim((string) ($invoice->client_name ?? '')),
            'sales_agent'      => trim((string) ($invoice->sales_agent ?? '')),
            'orden_id'         => $ordenIds,
            'orden_de_trabajo' => $ordenLabels,
            'site_name'        => $site,
        ];
    }

    /**
     * Invoice template vars when no DM recipient produced vars (e.g. FEL XML import: no linked orden, no sales_agent).
     * Uses created_by user for {username}/{user_login} when available, else sales_agent or an em dash.
     *
     * @param   object  $invoice    Row from #__ordenproduccion_invoices
     * @param   int     $invoiceId  Invoice PK
     *
     * @return  array<string,string>
     */
    protected static function buildInvoiceTemplateVarsChannelFallback(object $invoice, int $invoiceId): array
    {
        $invoiceId = (int) $invoiceId;
        $amount     = isset($invoice->invoice_amount) ? number_format((float) $invoice->invoice_amount, 2, '.', '') : '0.00';
        $currency   = trim((string) ($invoice->currency ?? 'Q'));

        [$ordenIds, $ordenLabels] = self::collectOrdenIdsAndLabelsForInvoice($invoice, $invoiceId);

        $site = '';
        try {
            $site = (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable $e) {
        }

        $username   = '—';
        $user_login = '';
        $cb         = (int) ($invoice->created_by ?? 0);
        if ($cb > 0) {
            try {
                $u = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($cb);
                if (!$u->guest && (int) $u->id === $cb) {
                    $username   = trim((string) $u->name);
                    $user_login = trim((string) $u->username);
                }
            } catch (\Throwable $e) {
            }
        }
        if ($username === '—') {
            $sa = trim((string) ($invoice->sales_agent ?? ''));
            if ($sa !== '') {
                $username = $sa;
            }
        }

        return [
            'username'         => $username,
            'user_login'       => $user_login,
            'invoice_id'       => (string) $invoiceId,
            'invoice_number'   => trim((string) ($invoice->invoice_number ?? '')),
            'invoice_amount'   => $amount,
            'currency'         => $currency,
            'client_name'      => trim((string) ($invoice->client_name ?? '')),
            'sales_agent'      => trim((string) ($invoice->sales_agent ?? '')),
            'orden_id'         => $ordenIds,
            'orden_de_trabajo' => $ordenLabels,
            'site_name'        => $site,
        ];
    }

    /**
     * @return  array{0: string, 1: string}  [comma-separated ids, comma-separated orden_de_trabajo labels]
     */
    protected static function collectOrdenIdsAndLabelsForInvoice(object $invoice, int $invoiceId): array
    {
        $ids    = [];
        $labels = [];

        try {
            $orderIds = AccessHelper::getOrderIdsLinkedToInvoice($invoiceId);
        } catch (\Throwable $e) {
            $orderIds = [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($orderIds as $oid) {
            $oid = (int) $oid;
            if ($oid < 1) {
                continue;
            }
            $ids[] = (string) $oid;
            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('orden_de_trabajo'))
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('id') . ' = ' . $oid)
                        ->where($db->quoteName('state') . ' = 1')
                );
                $ot = trim((string) $db->loadResult());
                $labels[] = $ot !== '' ? $ot : '#' . $oid;
            } catch (\Throwable $e) {
                $labels[] = '#' . $oid;
            }
        }

        if ($ids === [] && !empty($invoice->orden_id)) {
            $oid = (int) $invoice->orden_id;
            if ($oid > 0) {
                $ids[] = (string) $oid;
                $ot    = trim((string) ($invoice->orden_de_trabajo ?? ''));
                $labels[] = $ot !== '' ? $ot : '#' . $oid;
            }
        }

        return [implode(', ', $ids), implode(', ', $labels)];
    }

    /**
     * Variables for envío notification.
     *
     * @param   object    $orden            Work order row
     * @param   User|null $recipient        Owner Joomla user, or null if not resolved (uses sales_agent / placeholders)
     * @param   int       $ordenId          Order PK
     * @param   string    $tipoEnvio        completo / parcial / …
     * @param   string    $tipoMensajeria   propio / …
     *
     * @return  array<string,string>
     */
    public static function buildEnvioTemplateVars(object $orden, ?User $recipient, int $ordenId, string $tipoEnvio, string $tipoMensajeria): array
    {
        $site = '';
        try {
            $site = (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable $e) {
        }

        $ot = trim((string) ($orden->orden_de_trabajo ?? ''));
        $salesAgent = trim((string) ($orden->sales_agent ?? ''));

        if ($recipient !== null && !$recipient->guest) {
            $username   = trim((string) $recipient->name);
            $user_login = trim((string) $recipient->username);
        } else {
            $username   = $salesAgent !== '' ? $salesAgent : '—';
            $user_login = '';
        }

        return [
            'username'          => $username,
            'user_login'        => $user_login,
            'sales_agent'       => $salesAgent,
            'orden_id'          => (string) (int) $ordenId,
            'orden_de_trabajo'  => $ot !== '' ? $ot : '#' . (int) $ordenId,
            'client_name'       => trim((string) ($orden->nombre_del_cliente ?? $orden->client_name ?? '')),
            'tipo_envio'        => $tipoEnvio !== '' ? $tipoEnvio : '—',
            'tipo_mensajeria'   => $tipoMensajeria !== '' ? $tipoMensajeria : '—',
            'site_name'         => $site,
        ];
    }

    /**
     * Demo values for "test invoice" from Grimpsa bot (current user as recipient).
     *
     * @param   User  $user  Current user
     *
     * @return  array<string,string>
     */
    public static function getSampleInvoiceTemplateVars(User $user): array
    {
        self::ensureTelegramLanguageLoaded();

        return [
            'username'         => trim((string) $user->name),
            'user_login'       => trim((string) $user->username),
            'invoice_id'       => '0',
            'invoice_number'   => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_INVOICE_NUMBER'),
            'invoice_amount'   => '1234.56',
            'currency'         => 'Q',
            'client_name'      => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_CLIENT_NAME'),
            'sales_agent'      => trim((string) $user->name),
            'orden_id'         => '999001, 999002',
            'orden_de_trabajo' => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_ORDEN_LABELS'),
            'site_name'        => (string) Factory::getApplication()->get('sitename', 'Joomla'),
        ];
    }

    /**
     * Demo values for "test envío" from Grimpsa bot.
     *
     * @param   User  $user  Current user
     *
     * @return  array<string,string>
     */
    public static function getSampleEnvioTemplateVars(User $user): array
    {
        self::ensureTelegramLanguageLoaded();

        return [
            'username'         => trim((string) $user->name),
            'user_login'       => trim((string) $user->username),
            'sales_agent'      => trim((string) $user->name),
            'orden_id'         => '999001',
            'orden_de_trabajo' => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_ORDEN_SINGLE'),
            'client_name'      => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_CLIENT_NAME'),
            'tipo_envio'       => 'completo',
            'tipo_mensajeria'  => 'propio',
            'site_name'        => (string) Factory::getApplication()->get('sitename', 'Joomla'),
        ];
    }

    /**
     * Demo values for "test payment proof entered" from Grimpsa bot.
     *
     * @param   User  $user  Current user
     *
     * @return  array<string,string>
     */
    public static function getSamplePaymentProofEnteredTemplateVars(User $user): array
    {
        self::ensureTelegramLanguageLoaded();

        return [
            'username'          => trim((string) $user->name),
            'user_login'        => trim((string) $user->username),
            'proof_id'          => '0',
            'proof_number'      => 'PA-00000',
            'payment_amount'    => '1500.00',
            'currency'          => 'Q',
            'proof_status'      => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_PROOF_STATUS_ENTERED'),
            'orden_id'          => '999001, 999002',
            'orden_de_trabajo'  => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_ORDEN_LABELS'),
            'order_numbers'     => 'A-100, A-101',
            'client_names'      => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_CLIENT_NAME'),
            'sales_agents'      => trim((string) $user->name),
            'registrant_name'   => trim((string) $user->name),
            'registrant_login'  => trim((string) $user->username),
            'verifier_name'     => '—',
            'verifier_login'    => '',
            'verified_date'     => '—',
            'proof_note'        => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_PROOF_NOTE'),
            'site_name'         => (string) Factory::getApplication()->get('sitename', 'Joomla'),
        ];
    }

    /**
     * Demo values for "test payment proof verified" from Grimpsa bot.
     *
     * @param   User  $user  Current user
     *
     * @return  array<string,string>
     */
    public static function getSamplePaymentProofVerifiedTemplateVars(User $user): array
    {
        self::ensureTelegramLanguageLoaded();

        return [
            'username'          => trim((string) $user->name),
            'user_login'        => trim((string) $user->username),
            'proof_id'          => '0',
            'proof_number'      => 'PA-00000',
            'payment_amount'    => '1500.00',
            'currency'          => 'Q',
            'proof_status'      => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_PROOF_STATUS_VERIFIED'),
            'orden_id'          => '999001, 999002',
            'orden_de_trabajo'  => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_ORDEN_LABELS'),
            'order_numbers'     => 'A-100, A-101',
            'client_names'      => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_CLIENT_NAME'),
            'sales_agents'      => trim((string) $user->name),
            'registrant_name'   => trim((string) $user->name),
            'registrant_login'  => trim((string) $user->username),
            'verifier_name'     => trim((string) $user->name),
            'verifier_login'    => trim((string) $user->username),
            'verified_date'     => Factory::getDate()->format('Y-m-d H:i'),
            'proof_note'        => Text::_('COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_PROOF_NOTE'),
            'site_name'         => (string) Factory::getApplication()->get('sitename', 'Joomla'),
        ];
    }

    /**
     * Template variables for payment proof notifications (one DM recipient = order owner).
     *
     * @param   object   $proof              Row from #__ordenproduccion_payment_proofs
     * @param   User     $recipient          Owner / notified user
     * @param   int      $proofId            Proof PK
     * @param   int|null $verifiedByUserId   User who verified (verified event only); null or 0 if unknown
     * @param   bool     $isVerifiedEvent    True when notifying verification
     *
     * @return  array<string,string>
     */
    public static function buildPaymentProofTemplateVars(
        object $proof,
        User $recipient,
        int $proofId,
        ?int $verifiedByUserId,
        bool $isVerifiedEvent
    ): array {
        $proofId = (int) $proofId;
        $amount  = isset($proof->payment_amount) ? number_format((float) $proof->payment_amount, 2, '.', '') : '0.00';
        $currency = 'Q';
        if (isset($proof->currency)) {
            $currency = trim((string) $proof->currency);
        }

        $site = '';
        try {
            $site = (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable $e) {
        }

        $orders = self::getLinkedOrdersForPaymentProof($proofId);
        $ids    = [];
        $labels = [];
        $orderNums = [];
        $clients  = [];
        $agents   = [];
        foreach ($orders as $o) {
            $oid = (int) ($o->id ?? 0);
            if ($oid > 0) {
                $ids[] = (string) $oid;
            }
            $ot = trim((string) ($o->orden_de_trabajo ?? ''));
            $labels[] = $ot !== '' ? $ot : ($oid > 0 ? '#' . $oid : '');
            $on = trim((string) ($o->order_number ?? ''));
            if ($on !== '') {
                $orderNums[] = $on;
            }
            $cn = trim((string) ($o->client_name ?? ''));
            if ($cn !== '' && !\in_array($cn, $clients, true)) {
                $clients[] = $cn;
            }
            $sa = trim((string) ($o->sales_agent ?? ''));
            if ($sa !== '' && !\in_array($sa, $agents, true)) {
                $agents[] = $sa;
            }
        }

        $status = trim((string) ($proof->verification_status ?? ''));
        if ($isVerifiedEvent || strtolower($status) === 'verificado') {
            $proofStatusLabel = Text::_('COM_ORDENPRODUCCION_TELEGRAM_PROOF_STATUS_VERIFIED');
        } else {
            $proofStatusLabel = $status !== '' ? $status : Text::_('COM_ORDENPRODUCCION_TELEGRAM_PROOF_STATUS_ENTERED');
        }

        $cb = (int) ($proof->created_by ?? 0);
        $regName = '';
        $regLogin = '';
        if ($cb > 0) {
            try {
                $ru = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($cb);
                if (!$ru->guest) {
                    $regName  = trim((string) $ru->name);
                    $regLogin = trim((string) $ru->username);
                }
            } catch (\Throwable $e) {
            }
        }

        $verifierName  = '—';
        $verifierLogin = '';
        $vuid          = (int) ($verifiedByUserId ?? 0);
        if ($isVerifiedEvent && $vuid > 0) {
            try {
                $vu = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($vuid);
                if (!$vu->guest) {
                    $verifierName  = trim((string) $vu->name);
                    $verifierLogin = trim((string) $vu->username);
                }
            } catch (\Throwable $e) {
            }
        }

        $verifiedDate = '—';
        if ($isVerifiedEvent && !empty($proof->verified_date)) {
            $verifiedDate = trim((string) $proof->verified_date);
        }

        $proofNumber = 'PA-' . str_pad((string) $proofId, 5, '0', STR_PAD_LEFT);

        // Nota / acciones (stored as mismatch_note; includes ISR notes, admin text, etc.)
        $proofNote = '';
        if (isset($proof->mismatch_note)) {
            $proofNote = trim((string) $proof->mismatch_note);
        }
        if ($proofNote === '') {
            $proofNote = '—';
        } elseif (\strlen($proofNote) > 3500) {
            $proofNote = \substr($proofNote, 0, 3497) . '…';
        }

        return [
            'username'          => trim((string) $recipient->name),
            'user_login'        => trim((string) $recipient->username),
            'proof_id'          => (string) $proofId,
            'proof_number'      => $proofNumber,
            'payment_amount'    => $amount,
            'currency'          => $currency,
            'proof_status'      => $proofStatusLabel,
            'proof_note'        => $proofNote,
            'orden_id'          => implode(', ', $ids),
            'orden_de_trabajo'  => implode(', ', array_filter($labels, static fn ($x) => $x !== '')),
            'order_numbers'     => implode(', ', array_unique($orderNums)),
            'client_names'      => implode(', ', $clients),
            'sales_agents'      => implode(', ', $agents),
            'registrant_name'   => $regName !== '' ? $regName : '—',
            'registrant_login'  => $regLogin,
            'verifier_name'     => $verifierName,
            'verifier_login'    => $verifierLogin,
            'verified_date'     => $verifiedDate,
            'site_name'         => $site,
        ];
    }

    /**
     * Orders linked to a payment proof (for labels and recipient resolution).
     *
     * @param   int  $proofId  Payment proof PK
     *
     * @return  object[]  Rows with id, orden_de_trabajo, order_number, client_name, sales_agent
     */
    protected static function getLinkedOrdersForPaymentProof(int $proofId): array
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
            $hasClientName = isset($cols['client_name']);

            $qo = $db->quoteName('o');
            $query = $db->getQuery(true)
                ->select(
                    [
                        $qo . '.' . $db->quoteName('id'),
                        $qo . '.' . $db->quoteName('orden_de_trabajo'),
                        $qo . '.' . $db->quoteName('order_number'),
                        $qo . '.' . $db->quoteName('sales_agent'),
                    ]
                )
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->innerJoin($db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = po.order_id')
                ->where('o.' . $db->quoteName('state') . ' = 1')
                ->where('po.' . $db->quoteName('payment_proof_id') . ' = ' . $proofId);

            if ($hasClientName) {
                $query->select($qo . '.' . $db->quoteName('client_name'));
            } else {
                $query->select($qo . '.' . $db->quoteName('nombre_del_cliente') . ' AS ' . $db->quoteName('client_name'));
            }

            $db->setQuery($query);

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Load an active payment proof row.
     *
     * @param   int  $proofId  PK
     *
     * @return  object|null
     */
    protected static function loadPaymentProofRow(int $proofId): ?object
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->where($db->quoteName('id') . ' = ' . $proofId)
                    ->where($db->quoteName('state') . ' = 1')
            );

            $row = $db->loadObject();

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Distinct order-owner user ids for orders linked to this payment proof.
     *
     * @param   int  $proofId  Payment proof PK
     *
     * @return  int[]
     */
    public static function collectRecipientUserIdsForPaymentProof(int $proofId): array
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return [];
        }

        $ids  = [];
        $seen = [];

        foreach (self::getLinkedOrdersForPaymentProof($proofId) as $orden) {
            $uid = self::resolveOwnerUserIdFromOrden($orden);
            if ($uid !== null && $uid > 0 && empty($seen[$uid])) {
                $seen[$uid] = true;
                $ids[] = $uid;
            }
        }

        return $ids;
    }

    /**
     * Distinct Joomla user ids for owners of órdenes linked to this invoice (sales_agent / created_by per orden).
     * Does not use invoice importer, invoice.sales_agent alone, or users without a linked orden.
     *
     * @param   object  $invoice  Row from #__ordenproduccion_invoices
     *
     * @return  int[]
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

        if ($orderIds === []) {
            return [];
        }

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
                if ($uid !== null && $uid > 0 && empty($seen[$uid])) {
                    $seen[$uid] = true;
                    $ids[] = $uid;
                }
            }
        } catch (\Throwable $e) {
        }

        return $ids;
    }

    /**
     * Owner: match sales_agent to Joomla user (name exact, username exact, name case-insensitive), else created_by.
     */
    public static function resolveOwnerUserIdFromOrden(object $orden): ?int
    {
        $sales = trim((string) ($orden->sales_agent ?? ''));
        if ($sales !== '') {
            if (($uid = self::findUserIdByExactName($sales)) !== null) {
                return $uid;
            }
            if (($uid = self::findUserIdByUsername($sales)) !== null) {
                return $uid;
            }
            if (($uid = self::findUserIdByNameCaseInsensitive($sales)) !== null) {
                return $uid;
            }
        }

        $cb = (int) ($orden->created_by ?? 0);

        return $cb > 0 ? $cb : null;
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
     * Match sales_agent to Joomla login (username).
     *
     * @since  3.109.5
     */
    public static function findUserIdByUsername(string $username): ?int
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('username') . ' = ' . $db->quote($username))
                    ->where($db->quoteName('block') . ' = 0')
            );
            $id = (int) $db->loadResult();

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Case-insensitive name match (first unblocked hit).
     *
     * @since  3.109.5
     */
    public static function findUserIdByNameCaseInsensitive(string $name): ?int
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
                    ->where('LOWER(' . $db->quoteName('name') . ') = ' . $db->quote(strtolower($name)))
                    ->where($db->quoteName('block') . ' = 0')
                    ->setLimit(1)
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
     * Tries field **name** `telegram_chat_id` then `telegram-chat-id`, then any field whose name contains "telegram"
     * with a numeric chat_id value (e.g. `id-telegram`, `telegram-id`).
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

            if (!class_exists(\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::class)) {
                return null;
            }

            $fields = \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::getFields('com_users.user', $user, true);
            if (!\is_array($fields)) {
                return null;
            }

            $preferred = ['telegram_chat_id', 'telegram-chat-id'];

            foreach ($preferred as $fieldName) {
                foreach ($fields as $field) {
                    if (!isset($field->name, $field->value) || (string) $field->name !== $fieldName) {
                        continue;
                    }
                    $raw = \is_string($field->value) ? $field->value : (string) $field->value;
                    $ok  = self::normalizeTelegramChatId($raw);
                    if ($ok !== null) {
                        return $ok;
                    }
                }
            }

            foreach ($fields as $field) {
                $nm = isset($field->name) ? (string) $field->name : '';
                if ($nm === '' || stripos($nm, 'telegram') === false) {
                    continue;
                }
                if (\in_array($nm, $preferred, true)) {
                    continue;
                }
                $raw = $field->value ?? '';
                $raw = \is_string($raw) ? $raw : (string) $raw;
                $ok  = self::normalizeTelegramChatId($raw);
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
     * Reverse lookup: Telegram private chat id → Joomla user id (component table only).
     *
     * @param   string  $chatId  chat.id from update
     *
     * @return  int|null  Joomla user id or null
     *
     * @since   3.109.22
     */
    public static function getUserIdForChatId(string $chatId): ?int
    {
        $norm = self::normalizeTelegramChatId(trim($chatId));
        if ($norm === null) {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            if (self::telegramUsersTableExists($db)) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('user_id'))
                        ->from($db->quoteName('#__ordenproduccion_telegram_users'))
                        ->where($db->quoteName('chat_id') . ' = ' . $db->quote($norm))
                );
                $uid = (int) $db->loadResult();
                if ($uid > 0) {
                    return $uid;
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Joomla user ids who should receive the mismatch anchor DM: linked order owner(s) with Telegram only.
     *
     * @param   int  $proofId  Payment proof PK
     *
     * @return  int[]
     *
     * @since   3.109.22
     */
    public static function collectMismatchAnchorRecipientUserIds(int $proofId): array
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return [];
        }

        $seen = [];
        $out  = [];

        foreach (self::collectRecipientUserIdsForPaymentProof($proofId) as $uid) {
            $uid = (int) $uid;
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            if (self::getChatIdForUser($uid) === null) {
                continue;
            }
            $seen[$uid] = true;
            $out[]      = $uid;
        }

        return $out;
    }

    /**
     * Queue a dedicated anchor DM per linked order owner (Telegram); cron sends and registers (chat_id, message_id).
     * Call when a payment proof is saved with mismatch note/difference.
     *
     * @param   int  $proofId  Payment proof PK
     *
     * @return  void
     *
     * @since   3.109.22
     */
    public static function notifyMismatchTicketAnchors(int $proofId): void
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return;
        }
        if ((int) $params->get('telegram_mismatch_anchor_enabled', 1) !== 1) {
            return;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return;
        }

        if (!TelegramMismatchAnchorHelper::tableExists($db)) {
            return;
        }

        $token = trim((string) $params->get('telegram_bot_token', ''));

        $proof = self::loadPaymentProofRow($proofId);
        if (!$proof) {
            return;
        }

        $hasMismatch = false;
        if (isset($proof->mismatch_note) && trim((string) $proof->mismatch_note) !== '') {
            $hasMismatch = true;
        }
        if (isset($proof->mismatch_difference) && trim((string) $proof->mismatch_difference) !== '') {
            $hasMismatch = true;
        }
        if (!$hasMismatch) {
            return;
        }

        self::ensureTelegramLanguageLoaded();
        $label    = 'PA-' . str_pad((string) $proofId, 6, '0', STR_PAD_LEFT);
        $template = Text::_('COM_ORDENPRODUCCION_TELEGRAM_MISMATCH_ANCHOR_BODY');
        if (strpos($template, 'COM_ORDENPRODUCCION_') === 0) {
            $template = "Caso {proof_label} — diferencia de pago\n\n" .
                'Responda a este mensaje en Telegram para publicar un comentario en el caso.';
        }
        $body = str_replace('{proof_label}', $label, $template);

        foreach (self::collectMismatchAnchorRecipientUserIds($proofId) as $uid) {
            $chatId = self::getChatIdForUser($uid);
            if ($chatId === null) {
                continue;
            }
            if (TelegramQueueHelper::enqueueMismatchAnchor($chatId, $body, $proofId, $uid)) {
                continue;
            }
            if ($token === '') {
                continue;
            }
            $res = TelegramApiHelper::sendMessage($token, $chatId, $body);
            if (!empty($res['ok']) && isset($res['message_id'])) {
                TelegramMismatchAnchorHelper::insertAnchor($chatId, (int) $res['message_id'], $proofId, $uid);
            }
        }
    }

    /**
     * Recipients for mismatch-ticket **comment** DMs: linked order owners with Telegram (same as anchor list)
     * plus Administración/Admon users with Telegram, excluding the author (so others are pinged, not an echo).
     * Callers: if this list is empty but the author has Telegram, still enqueue one DM to the author (solo stakeholder).
     *
     * @param   int  $proofId        Payment proof PK
     * @param   int  $excludeUserId  Joomla user who wrote the comment (not notified)
     *
     * @return  int[]
     *
     * @since   3.109.31
     */
    public static function collectMismatchTicketTelegramNotifyUserIds(int $proofId, int $excludeUserId): array
    {
        $proofId        = (int) $proofId;
        $excludeUserId  = (int) $excludeUserId;
        if ($proofId < 1) {
            return [];
        }

        $seen = [];
        $out  = [];

        foreach (self::collectMismatchAnchorRecipientUserIds($proofId) as $uid) {
            $uid = (int) $uid;
            if ($uid < 1 || $uid === $excludeUserId || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $out[]      = $uid;
        }

        foreach (AccessHelper::getAdministracionGroupUserIds() as $uid) {
            $uid = (int) $uid;
            if ($uid < 1 || $uid === $excludeUserId || isset($seen[$uid])) {
                continue;
            }
            if (self::getChatIdForUser($uid) === null) {
                continue;
            }
            $seen[$uid] = true;
            $out[]      = $uid;
        }

        return $out;
    }

    /**
     * Queue Telegram DMs when someone posts a new mismatch-ticket comment (site UI or Telegram webhook).
     *
     * @param   int     $proofId           Payment proof PK
     * @param   string  $body              Comment body (trimmed; may be truncated in DM)
     * @param   int     $actorJoomlaUserId Author Joomla user id
     *
     * @return  void
     *
     * @since   3.109.31
     */
    public static function notifyMismatchTicketCommentAdded(int $proofId, string $body, int $actorJoomlaUserId): void
    {
        $proofId           = (int) $proofId;
        $actorJoomlaUserId = (int) $actorJoomlaUserId;
        if ($proofId < 1 || $actorJoomlaUserId < 1) {
            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return;
        }

        $body = trim(strip_tags($body));
        if ($body === '') {
            return;
        }

        self::ensureTelegramLanguageLoaded();
        $label = 'PA-' . str_pad((string) $proofId, 6, '0', STR_PAD_LEFT);

        $authorName = '';
        try {
            $u = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorJoomlaUserId);
            $authorName = trim((string) ($u->name ?? ''));
        } catch (\Throwable $e) {
        }
        if ($authorName === '') {
            $authorName = 'User ' . $actorJoomlaUserId;
        }

        $preview = trim((string) \preg_replace('/\s+/u', ' ', $body));
        if (\function_exists('mb_strlen') && \mb_strlen($preview) > 450) {
            $preview = \mb_substr($preview, 0, 447) . '...';
        } elseif (\strlen($preview) > 450) {
            $preview = \substr($preview, 0, 447) . '...';
        }

        $template = Text::_('COM_ORDENPRODUCCION_TELEGRAM_MISMATCH_NEW_COMMENT_DM');
        if ($template === '' || strpos($template, 'COM_ORDENPRODUCCION_') === 0) {
            $template = "Grimpsa — caso {proof_label}\n\n{author} escribió:\n\n{preview}";
        }
        $text = str_replace(['{proof_label}', '{author}', '{preview}'], [$label, $authorName, $preview], $template);

        $recipientIds = self::collectMismatchTicketTelegramNotifyUserIds($proofId, $actorJoomlaUserId);

        // Author is normally excluded so they do not get a Telegram echo of their own comment. When they are the
        // only linked owner/admin with Telegram (common for tests or single-user workflows), the list is empty
        // and nothing is queued — still deliver one outbound row so Cola/Telegram reflect the new thread line.
        if ($recipientIds === [] && self::getChatIdForUser($actorJoomlaUserId) !== null) {
            $recipientIds = [$actorJoomlaUserId];
        }

        foreach ($recipientIds as $uid) {
            $chatId = self::getChatIdForUser((int) $uid);
            if ($chatId === null) {
                continue;
            }
            TelegramQueueHelper::enqueue($chatId, $text);
        }
    }

    /**
     * Public HTTPS origin for the Bot API inbound webhook (no trailing slash).
     * When `telegram_webhook_public_base` is set (e.g. https://telegram.example.com), use it so setWebhook
     * and UI match a dedicated host; otherwise the current site root ({@see Uri::root()}).
     *
     * @return  string
     *
     * @since   3.109.37
     */
    public static function getTelegramWebhookPublicRoot(): string
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $raw    = trim((string) $params->get('telegram_webhook_public_base', ''));

        if ($raw === '') {
            return rtrim(Uri::root(), '/');
        }

        $raw = rtrim($raw, '/');
        $p   = @parse_url($raw);
        if (!\is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return rtrim(Uri::root(), '/');
        }
        if (strtolower((string) $p['scheme']) !== 'https') {
            return rtrim(Uri::root(), '/');
        }
        $host = (string) $p['host'];
        if ($host === '' || preg_match('/[<>\s]/u', $host)) {
            return rtrim(Uri::root(), '/');
        }

        $root = 'https://' . $host;
        if (!empty($p['port']) && (int) $p['port'] > 0 && (int) $p['port'] !== 443) {
            $root .= ':' . (int) $p['port'];
        }
        if (!empty($p['path']) && $p['path'] !== '/') {
            $root .= rtrim((string) $p['path'], '/');
        }

        return $root;
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

        TelegramQueueHelper::enqueue($chatId, $text);
    }
}
