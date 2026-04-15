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
     * https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&controller=telegram&task=processQueue&format=raw&cron_key=SECRET
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
            $app->setHeader('Status', '403', true);
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'Forbidden';
            $app->close();

            return;
        }

        $sent = TelegramQueueHelper::processBatch(100);

        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo 'OK ' . (int) $sent;
        $app->close();
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
            $app->setHeader('Status', '405', true);
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo "Method Not Allowed\n\nThis URL accepts HTTP POST only (Telegram sends JSON updates). A browser uses GET, so you see this message — that is expected.";
            $app->close();

            return;
        }

        $params   = ComponentHelper::getParams('com_ordenproduccion');
        $expected = trim((string) $params->get('telegram_webhook_secret', ''));
        $hdr      = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
            ? trim((string) $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
            : '';

        if ($expected === '' || $hdr === '' || !\hash_equals($expected, $hdr)) {
            $app->setHeader('Status', '403', true);
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'Forbidden';
            $app->close();

            return;
        }

        $raw  = (string) file_get_contents('php://input');
        $data = json_decode($raw !== '' ? $raw : '[]', true);
        if (!\is_array($data)) {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        $msg = $data['message'] ?? null;
        if (!\is_array($msg)) {
            $msg = $data['edited_message'] ?? null;
        }
        if (!\is_array($msg)) {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        $text = isset($msg['text']) ? trim((string) $msg['text']) : '';
        if ($text === '') {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

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
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        $replyMid = (int) $replyTo['message_id'];
        $proofId  = TelegramMismatchAnchorHelper::findPaymentProofIdByReply($chatId, $replyMid);
        if ($proofId < 1) {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        $joomlaUserId = TelegramNotificationHelper::getUserIdForChatId($chatId);
        if ($joomlaUserId === null || $joomlaUserId < 1) {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Payments', 'Site', ['ignore_request' => true]);
        } catch (\Throwable $e) {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        if ($model === null || !\method_exists($model, 'addMismatchTicketCommentAsUser')) {
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'OK';
            $app->close();

            return;
        }

        if (!$model->addMismatchTicketCommentAsUser($proofId, $text, $joomlaUserId)) {
            $errRaw = $model->getError();
            $err    = \is_array($errRaw) ? \implode(' ', $errRaw) : \trim((string) $errRaw);
            if ($token !== '' && $chatId !== '' && $err !== '') {
                TelegramApiHelper::sendMessage($token, $chatId, $err);
            }
        }

        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo 'OK';
        $app->close();
    }
}
