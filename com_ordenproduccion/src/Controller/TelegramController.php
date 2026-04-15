<?php

/**
 * Telegram queue cron endpoint (no session; secret key in URL).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @since       3.108.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramApiHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramMismatchAnchorHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramQueueHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramWebhookLogHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Telegram cron controller.
 */
class TelegramController extends BaseController
{
    /**
     * Process pending outbound Telegram messages. Call every ~2 minutes via server cron or Postman (GET):
     * https://telegram.grantsolutions.cc/index.php?option=com_ordenproduccion&controller=telegram&task=processQueue&format=raw&cron_key=SECRET
     *
     * @return  void
     */
    public function processQueue(): void
    {
        $app = Factory::getApplication();
        $key = $app->input->getString('cron_key', '');
        $key = \is_string($key) ? trim($key) : '';

        $params   = ComponentHelper::getParams('com_ordenproduccion');
        $expected = trim((string) $params->get('telegram_queue_cron_key', ''));

        if ($expected === '' || $key === '' || !\hash_equals($expected, $key)) {
            $this->emitPlainResponse(
                403,
                "Forbidden\n\nUse GET with query cron_key matching the component option «Telegram queue cron key»."
            );

            return;
        }

        $sent = TelegramQueueHelper::processBatch(100);

        $this->emitPlainResponse(200, 'OK ' . (int) $sent);
    }

    /**
     * Telegram Bot API webhook: inbound Update (core.telegram.org/bots/api#update) → mismatch-ticket comment when applicable.
     *
     * Telegram POSTs a JSON **Update** (top-level `update_id` plus at most one of `message`, `edited_message`,
     * `callback_query`, `channel_post`, etc.). This handler:
     *
     * 1. **403 Forbidden** only when `X-Telegram-Bot-Api-Secret-Token` is missing or does not match
     *    `telegram_webhook_secret` (same value as setWebhook `secret_token`). No other branch returns 403.
     * 2. After auth, parses `php://input` as JSON. Unsupported or non-message updates, empty `text`, or
     *    payloads that are not valid JSON objects still receive **200 OK** so Telegram does not treat delivery as failed.
     * 3. Processes `message` / `edited_message` with non-empty `text` and `reply_to_message` for the mismatch anchor flow.
     *
     * Configure setWebhook with `secret_token`; same value in component param `telegram_webhook_secret`.
     *
     * @return  void
     *
     * @since   3.109.22
     */
    public function webhook(): void
    {
        $app    = Factory::getApplication();
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        $logIp  = TelegramWebhookLogHelper::clientIp();
        $logUa  = TelegramWebhookLogHelper::clientUserAgent();

        if ($method !== 'POST') {
            TelegramWebhookLogHelper::record([
                'http_method'             => $method,
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => 0,
                'secret_header_present'   => false,
                'secret_valid'            => false,
                'http_status'             => 405,
                'outcome'                 => 'method_not_allowed',
            ]);
            $this->emitPlainResponse(
                405,
                "Method Not Allowed\n\nThis URL accepts HTTP POST only (Telegram sends JSON updates). A browser uses GET, so you see this message — that is expected."
            );

            return;
        }

        $raw     = (string) file_get_contents('php://input');
        $bodyLen = \strlen($raw);

        $params   = ComponentHelper::getParams('com_ordenproduccion');
        $expected = trim((string) $params->get('telegram_webhook_secret', ''));
        $hdr      = self::readTelegramWebhookSecretHeader();
        $hdrOk    = $hdr !== '';

        if ($expected === '') {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => $hdrOk,
                'secret_valid'            => false,
                'http_status'             => 403,
                'outcome'                 => 'forbidden_joomla_secret_empty',
            ]);
            $this->emitPlainResponse(
                403,
                "Forbidden\n\n" .
                'Send HTTP header X-Telegram-Bot-Api-Secret-Token with the exact value saved in ' .
                "component options (Telegram webhook secret) and in Telegram setWebhook secret_token."
            );

            return;
        }

        if ($hdr === '') {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => false,
                'secret_valid'            => false,
                'http_status'             => 403,
                'outcome'                 => 'forbidden_header_missing',
            ]);
            $this->emitPlainResponse(
                403,
                "Forbidden\n\n" .
                'Send HTTP header X-Telegram-Bot-Api-Secret-Token with the exact value saved in ' .
                "component options (Telegram webhook secret) and in Telegram setWebhook secret_token."
            );

            return;
        }

        if (!\hash_equals($expected, $hdr)) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => false,
                'http_status'             => 403,
                'outcome'                 => 'forbidden_secret_mismatch',
            ]);
            $this->emitPlainResponse(
                403,
                "Forbidden\n\n" .
                'Send HTTP header X-Telegram-Bot-Api-Secret-Token with the exact value saved in ' .
                "component options (Telegram webhook secret) and in Telegram setWebhook secret_token."
            );

            return;
        }

        // Update object: https://core.telegram.org/bots/api#update — tolerate empty body, invalid JSON, or types we do not handle.
        $data = json_decode($raw !== '' ? $raw : '[]', true);
        if (!\is_array($data)) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_invalid_json',
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $updateId = isset($data['update_id']) ? (int) $data['update_id'] : 0;

        $msg = $data['message'] ?? null;
        if (!\is_array($msg)) {
            $msg = $data['edited_message'] ?? null;
        }
        if (!\is_array($msg)) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_ignored_no_message',
                'update_id'               => $updateId > 0 ? $updateId : null,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $text = isset($msg['text']) ? trim((string) $msg['text']) : '';
        if ($text === '') {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_ignored_empty_text',
                'update_id'               => $updateId > 0 ? $updateId : null,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $chat    = $msg['chat'] ?? [];
        $chatId  = isset($chat['id']) ? (string) $chat['id'] : '';
        $replyTo = $msg['reply_to_message'] ?? null;
        $token   = trim((string) $params->get('telegram_bot_token', ''));

        if (!\is_array($replyTo) || !isset($replyTo['message_id'])) {
            TelegramNotificationHelper::ensureTelegramLanguageLoaded();
            $hint = Text::_('COM_ORDENPRODUCCION_TELEGRAM_MISMATCH_REPLY_NEEDS_THREAD');
            if (strpos($hint, 'COM_ORDENPRODUCCION_') === 0) {
                $hint = 'Responda al mensaje del caso (deslizar → Responder) para vincular el comentario.';
            }
            if ($token !== '' && $chatId !== '' && TelegramNotificationHelper::normalizeTelegramChatId($chatId) !== null) {
                TelegramApiHelper::sendMessage($token, $chatId, $hint);
            }
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_hinted_not_reply_to_anchor',
                'update_id'               => $updateId > 0 ? $updateId : null,
                'chat_id'                 => $chatId !== '' ? $chatId : null,
                'text_preview'            => $text,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $replyMid = (int) $replyTo['message_id'];
        $proofId  = TelegramMismatchAnchorHelper::findPaymentProofIdByReply($chatId, $replyMid);
        if ($proofId < 1) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_ignored_no_anchor_match',
                'update_id'               => $updateId > 0 ? $updateId : null,
                'chat_id'                 => $chatId !== '' ? $chatId : null,
                'text_preview'            => $text,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $joomlaUserId = TelegramNotificationHelper::getUserIdForChatId($chatId);
        if ($joomlaUserId === null || $joomlaUserId < 1) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_ignored_chat_not_linked',
                'update_id'               => $updateId > 0 ? $updateId : null,
                'chat_id'                 => $chatId !== '' ? $chatId : null,
                'text_preview'            => $text,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Payments', 'Site', ['ignore_request' => true]);
        } catch (\Throwable $e) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_ignored_model_boot_failed',
                'update_id'               => $updateId > 0 ? $updateId : null,
                'chat_id'                 => $chatId !== '' ? $chatId : null,
                'text_preview'            => $text,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        if ($model === null || !\method_exists($model, 'addMismatchTicketCommentAsUser')) {
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_ignored_model_missing',
                'update_id'               => $updateId > 0 ? $updateId : null,
                'chat_id'                 => $chatId !== '' ? $chatId : null,
                'text_preview'            => $text,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        if (!$model->addMismatchTicketCommentAsUser($proofId, $text, $joomlaUserId)) {
            $errRaw = $model->getError();
            $err    = \is_array($errRaw) ? \implode(' ', $errRaw) : \trim((string) $errRaw);
            if ($token !== '' && $chatId !== '' && $err !== '') {
                TelegramApiHelper::sendMessage($token, $chatId, $err);
            }
            TelegramWebhookLogHelper::record([
                'http_method'             => 'POST',
                'ip'                      => $logIp,
                'user_agent'              => $logUa,
                'body_length'             => $bodyLen,
                'secret_header_present'   => true,
                'secret_valid'            => true,
                'http_status'             => 200,
                'outcome'                 => 'ok_comment_save_failed',
                'update_id'               => $updateId > 0 ? $updateId : null,
                'chat_id'                 => $chatId !== '' ? $chatId : null,
                'text_preview'            => $text,
            ]);
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        TelegramWebhookLogHelper::record([
            'http_method'             => 'POST',
            'ip'                      => $logIp,
            'user_agent'              => $logUa,
            'body_length'             => $bodyLen,
            'secret_header_present'   => true,
            'secret_valid'            => true,
            'http_status'             => 200,
            'outcome'                 => 'ok_comment_saved',
            'update_id'               => $updateId > 0 ? $updateId : null,
            'chat_id'                 => $chatId !== '' ? $chatId : null,
            'text_preview'            => $text,
        ]);
        $this->emitPlainResponse(200, 'OK');
    }

    /**
     * Value of Telegram's X-Telegram-Bot-Api-Secret-Token header (some stacks omit HTTP_* in $_SERVER).
     *
     * @return  string
     *
     * @since   3.109.38
     */
    private static function readTelegramWebhookSecretHeader(): string
    {
        foreach (
            [
                'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN',
                'REDIRECT_HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN',
            ] as $serverKey
        ) {
            if (!empty($_SERVER[$serverKey])) {
                return trim((string) $_SERVER[$serverKey]);
            }
        }

        if (\function_exists('getallheaders')) {
            $headers = getallheaders();
            if (\is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (\is_string($name) && strcasecmp($name, 'X-Telegram-Bot-Api-Secret-Token') === 0) {
                        return trim((string) $value);
                    }
                }
            }
        }

        return '';
    }

    /**
     * Plain text response with an explicit PHP HTTP status code.
     *
     * Joomla's {@see \Joomla\CMS\Application\CMSApplication::setHeader()} with `Status` alone does not always
     * set the real response code seen by clients (e.g. Postman shows 200 while the body says Forbidden).
     *
     * @param   int     $status  HTTP status (e.g. 200, 403, 405)
     * @param   string  $body    UTF-8 body
     *
     * @return  void
     *
     * @since   3.109.36
     */
    private function emitPlainResponse(int $status, string $body): void
    {
        $app = Factory::getApplication();
        if (!\headers_sent()) {
            \http_response_code($status);
        }
        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo $body;
        $app->close();
    }
}
