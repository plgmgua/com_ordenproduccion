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
     * Telegram Bot API webhook: reply to anchor DM → mismatch ticket comment.
     * Configure Bot API setWebhook with secret_token; same value in component param telegram_webhook_secret.
     * Header: X-Telegram-Bot-Api-Secret-Token must match.
     *
     * @return  void
     *
     * @since   3.109.22
     */
    public function webhook(): void
    {
        $app = Factory::getApplication();

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method !== 'POST') {
            $this->emitPlainResponse(
                405,
                "Method Not Allowed\n\nThis URL accepts HTTP POST only (Telegram sends JSON updates). A browser uses GET, so you see this message — that is expected."
            );

            return;
        }

        $params   = ComponentHelper::getParams('com_ordenproduccion');
        $expected = trim((string) $params->get('telegram_webhook_secret', ''));
        $hdr      = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
            ? trim((string) $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
            : '';

        if ($expected === '' || $hdr === '' || !\hash_equals($expected, $hdr)) {
            $this->emitPlainResponse(
                403,
                "Forbidden\n\n" .
                'Send HTTP header X-Telegram-Bot-Api-Secret-Token with the exact value saved in ' .
                "component options (Telegram webhook secret) and in Telegram setWebhook secret_token."
            );

            return;
        }

        $raw  = (string) file_get_contents('php://input');
        $data = json_decode($raw !== '' ? $raw : '[]', true);
        if (!\is_array($data)) {
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $msg = $data['message'] ?? null;
        if (!\is_array($msg)) {
            $msg = $data['edited_message'] ?? null;
        }
        if (!\is_array($msg)) {
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $text = isset($msg['text']) ? trim((string) $msg['text']) : '';
        if ($text === '') {
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
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $replyMid = (int) $replyTo['message_id'];
        $proofId  = TelegramMismatchAnchorHelper::findPaymentProofIdByReply($chatId, $replyMid);
        if ($proofId < 1) {
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        $joomlaUserId = TelegramNotificationHelper::getUserIdForChatId($chatId);
        if ($joomlaUserId === null || $joomlaUserId < 1) {
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Payments', 'Site', ['ignore_request' => true]);
        } catch (\Throwable $e) {
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        if ($model === null || !\method_exists($model, 'addMismatchTicketCommentAsUser')) {
            $this->emitPlainResponse(200, 'OK');

            return;
        }

        if (!$model->addMismatchTicketCommentAsUser($proofId, $text, $joomlaUserId)) {
            $errRaw = $model->getError();
            $err    = \is_array($errRaw) ? \implode(' ', $errRaw) : \trim((string) $errRaw);
            if ($token !== '' && $chatId !== '' && $err !== '') {
                TelegramApiHelper::sendMessage($token, $chatId, $err);
            }
        }

        $this->emitPlainResponse(200, 'OK');
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
