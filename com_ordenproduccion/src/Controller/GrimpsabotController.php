<?php

/**
 * Grimpsa bot (Telegram) — save settings and profile.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @since       3.105.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramApiHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Grimpsabot controller.
 */
class GrimpsabotController extends BaseController
{
    /**
     * Save bot token and event toggles (Administración / Admon / superuser).
     *
     * @return  void
     */
    public function saveconfig(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $app = Factory::getApplication();

        if (!$this->allowManageBotSettings()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $data = $this->input->post->get('jform', [], 'array');
        $model = $this->getModel('Grimpsabot', 'Site', ['ignore_request' => true]);

        if (!$model || !$model->saveComponentParams($data)) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $app->enqueueMessage(Text::_('JLIB_APPLICATION_SAVE_SUCCESS'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));
    }

    /**
     * Save current user's Telegram chat_id.
     *
     * @return  void
     */
    public function saveprofile(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $app  = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        $chatId = trim((string) $this->input->post->getString('telegram_chat_id', ''));
        $model  = $this->getModel('Grimpsabot', 'Site', ['ignore_request' => true]);

        if (!$model || !$model->saveUserChatId($chatId)) {
            $msg = $model ? $model->getError() : '';
            $app->enqueueMessage($msg !== '' ? $msg : Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_PROFILE_SAVED'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));
    }

    /**
     * Send a test message to the current user's chat_id.
     *
     * @return  void
     */
    public function sendtest(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $app  = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_NO_TOKEN'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $chatId = TelegramNotificationHelper::getChatIdForUser((int) $user->id);
        if ($chatId === null) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_NO_CHAT'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $site = Factory::getApplication()->get('sitename', 'Joomla');
        $text = 'Grimpsa bot — prueba / test' . "\n" . 'Usuario: ' . $user->name . "\n" . 'Sitio: ' . $site;
        $res = TelegramApiHelper::sendMessage($token, $chatId, $text);

        if (!empty($res['ok'])) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_SENT'), 'success');
        } else {
            $detail = trim((string) ($res['description'] ?? $res['error'] ?? ''));
            if ($detail !== '') {
                $safe = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
                $app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_FAILED_REASON', $safe),
                    'error'
                );
            } else {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_FAILED'), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));
    }

    /**
     * Send a test message using the invoice or envío template and sample data.
     *
     * @return  void
     */
    public function sendtestevent(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $app  = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

        $event = $this->input->post->getCmd('telegram_test_event', 'invoice');
        if (!\in_array(
            $event,
            [
                TelegramNotificationHelper::EVENT_INVOICE,
                TelegramNotificationHelper::EVENT_ENVIO,
                TelegramNotificationHelper::EVENT_PAYMENT_PROOF_ENTERED,
                TelegramNotificationHelper::EVENT_PAYMENT_PROOF_VERIFIED,
            ],
            true
        )) {
            $event = TelegramNotificationHelper::EVENT_INVOICE;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_NO_TOKEN'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $chatId = TelegramNotificationHelper::getChatIdForUser((int) $user->id);
        if ($chatId === null) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_NO_CHAT'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        if ($event === TelegramNotificationHelper::EVENT_INVOICE) {
            $template   = TelegramNotificationHelper::getInvoiceMessageTemplate($params);
            $vars       = TelegramNotificationHelper::getSampleInvoiceTemplateVars($user);
            $eventLabel = Text::_('COM_ORDENPRODUCCION_TELEGRAM_EVENT_INVOICE');
        } elseif ($event === TelegramNotificationHelper::EVENT_ENVIO) {
            $template   = TelegramNotificationHelper::getEnvioMessageTemplate($params);
            $vars       = TelegramNotificationHelper::getSampleEnvioTemplateVars($user);
            $eventLabel = Text::_('COM_ORDENPRODUCCION_TELEGRAM_EVENT_ENVIO');
        } elseif ($event === TelegramNotificationHelper::EVENT_PAYMENT_PROOF_ENTERED) {
            $template   = TelegramNotificationHelper::getPaymentProofEnteredMessageTemplate($params);
            $vars       = TelegramNotificationHelper::getSamplePaymentProofEnteredTemplateVars($user);
            $eventLabel = Text::_('COM_ORDENPRODUCCION_TELEGRAM_EVENT_PAYMENT_PROOF_ENTERED');
        } else {
            $template   = TelegramNotificationHelper::getPaymentProofVerifiedMessageTemplate($params);
            $vars       = TelegramNotificationHelper::getSamplePaymentProofVerifiedTemplateVars($user);
            $eventLabel = Text::_('COM_ORDENPRODUCCION_TELEGRAM_EVENT_PAYMENT_PROOF_VERIFIED');
        }

        $body = TelegramNotificationHelper::replaceTemplatePlaceholders($template, $vars);
        $text = Text::_('COM_ORDENPRODUCCION_TELEGRAM_TEST_PREFIX') . "\n\n" . $body;
        $res  = TelegramApiHelper::sendMessage($token, $chatId, $text);

        if (!empty($res['ok'])) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_EVENT_SENT', $eventLabel), 'success');
        } else {
            $detail = trim((string) ($res['description'] ?? $res['error'] ?? ''));
            if ($detail !== '') {
                $safe = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
                $app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_FAILED_REASON', $safe),
                    'error'
                );
            } else {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_FAILED'), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));
    }

    /**
     * Send a test line to the Administración Telegram channel (Administración / Admon / superuser only).
     *
     * @return  void
     */
    public function sendtestbroadcast(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $app  = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        if (!$this->allowManageBotSettings()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));
        if ($token === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_NO_TOKEN'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $chatId = trim((string) $params->get('telegram_broadcast_chat_id', ''));
        if ($chatId === '' || TelegramNotificationHelper::normalizeTelegramChatId($chatId) === null) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_BROADCAST_NO_CHAT'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        if (str_starts_with($chatId, '@')) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_BROADCAST_NO_AT_USERNAME'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return;
        }

        $text = Text::_('COM_ORDENPRODUCCION_TELEGRAM_BROADCAST_TEST_BODY');
        $res  = TelegramApiHelper::sendMessage($token, $chatId, $text);

        if (!empty($res['ok'])) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_BROADCAST_SENT'), 'success');
        } else {
            $detail = trim((string) ($res['description'] ?? $res['error'] ?? ''));
            if ($detail !== '') {
                $lower = strtolower($detail);
                if (str_contains($lower, 'chat not found') || str_contains($lower, 'peer id invalid')) {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_BROADCAST_ERR_CHAT_NOT_FOUND'), 'error');
                } else {
                    $safe = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
                    $app->enqueueMessage(
                        Text::sprintf('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_FAILED_REASON', $safe),
                        'error'
                    );
                }
            } else {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_FAILED'), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));
    }

    /**
     * Fetch getMe + getWebhookInfo from Telegram (saved bot token) for diagnostics.
     *
     * @return  void
     *
     * @since   3.109.30
     */
    public function telegramBotInfo(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $this->loadGrimpsabotLanguageForMessages();
        $app = Factory::getApplication();

        if (!$this->allowManageBotSettings()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . '#grimpsabot-pane-webhook');

            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));

        if ($token === '') {
            $app->enqueueMessage(
                $this->translateOrPlain(
                    'COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_NO_TOKEN',
                    'Telegram bot token is not configured.'
                ),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . '#grimpsabot-pane-webhook');

            return;
        }

        $me = TelegramApiHelper::botApiGet($token, 'getMe');
        $wh = TelegramApiHelper::botApiGet($token, 'getWebhookInfo');

        $expectedUrl = rtrim(Uri::root(), '/') . '/index.php?option=com_ordenproduccion&controller=telegram&task=webhook&format=raw';

        $normalize = static function (array $r): array {
            return [
                'ok'          => !empty($r['ok']),
                'http_code'   => (int) ($r['http_code'] ?? 0),
                'raw_body'    => (string) ($r['raw_body'] ?? ''),
                'description' => (string) ($r['description'] ?? ''),
                'error'       => (string) ($r['error'] ?? ''),
                'result'      => $r['result'] ?? null,
            ];
        };

        $whResult        = \is_array($wh['result'] ?? null) ? $wh['result'] : [];
        $lastWebhookErr  = trim((string) ($whResult['last_error_message'] ?? ''));
        $pendingUpdates  = (int) ($whResult['pending_update_count'] ?? 0);
        $sslDeliveryFail = $lastWebhookErr !== '' && stripos($lastWebhookErr, 'ssl') !== false;

        $payload = [
            'phase'                        => 'getMe_getWebhookInfo',
            'expected_joomla_webhook_url'  => $expectedUrl,
            'getMe'                        => $normalize($me),
            'getWebhookInfo'               => $normalize($wh),
        ];

        if ($lastWebhookErr !== '' || $pendingUpdates > 0) {
            $payload['webhook_delivery'] = [
                'pending_update_count' => $pendingUpdates,
                'last_error_message'   => $lastWebhookErr !== '' ? $lastWebhookErr : null,
                'note'                 => 'If last_error_message is set, Telegram could not POST updates to your webhook URL. In-app replies from Telegram will not reach Joomla until delivery succeeds (often HTTPS / certificate).',
            ];
        }

        $this->stashTelegramBotInfoDebugPayload($payload);

        if (empty($me['ok']) || empty($wh['ok'])) {
            $app->enqueueMessage(
                $this->translateOrPlain(
                    'COM_ORDENPRODUCCION_TELEGRAM_BOT_INFO_ERR',
                    'getMe or getWebhookInfo returned an error. See the debug box below.'
                ),
                'warning'
            );
        } elseif ($lastWebhookErr !== '') {
            if ($sslDeliveryFail) {
                $app->enqueueMessage(
                    $this->translateOrPlain(
                        'COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SSL_VERIFY_FAILED',
                        'Telegram servers cannot verify the SSL certificate of your webhook URL. Fix TLS on the server, then check getWebhookInfo again.'
                    ),
                    'error'
                );
            } else {
                $safeErr = htmlspecialchars($lastWebhookErr, ENT_QUOTES, 'UTF-8');
                $app->enqueueMessage(
                    Text::sprintf(
                        'COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_DELIVERY_LAST_ERROR',
                        $safeErr
                    ),
                    'error'
                );
            }

            if ($pendingUpdates > 0) {
                $app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_PENDING_UPDATES_COUNT', $pendingUpdates),
                    'warning'
                );
            }
        } else {
            $app->enqueueMessage(
                $this->translateOrPlain(
                    'COM_ORDENPRODUCCION_TELEGRAM_BOT_INFO_OK',
                    'Telegram API responded OK. Compare getWebhookInfo.url to the expected Joomla URL in the debug box.'
                ),
                'success'
            );

            if ($pendingUpdates > 0) {
                $app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_PENDING_UPDATES_COUNT', $pendingUpdates),
                    'notice'
                );
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . '#grimpsabot-pane-webhook');
    }

    /**
     * Call Telegram setWebhook (Administración / Admon / superuser). Uses saved bot token + webhook secret.
     *
     * @return  void
     *
     * @since   3.109.25
     */
    public function setTelegramWebhook(): void
    {
        if (!$this->verifyToken()) {
            return;
        }
        $this->loadGrimpsabotLanguageForMessages();

        $app = Factory::getApplication();

        $returnHash = $this->getGrimpsabotWebhookReturnHash();

        if (!$this->allowManageBotSettings()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . $returnHash);

            return;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $token  = trim((string) $params->get('telegram_bot_token', ''));
        $secret = trim((string) $params->get('telegram_webhook_secret', ''));

        if ($token === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_NO_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . $returnHash);

            return;
        }
        if ($secret === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_NO_SECRET'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . '#grimpsabot-pane-webhook');

            return;
        }

        if (!TelegramApiHelper::isValidWebhookSecretToken($secret)) {
            $this->stashSetWebhookDebugPayload([
                'phase'  => 'validation_failed',
                'reason' => 'secret_token_character_set',
                'note'   => 'Telegram setWebhook was not called. secret_token must be 1-256 characters: A-Z, a-z, 0-9, underscore, hyphen only.',
            ]);
            $app->enqueueMessage(
                $this->translateOrPlain(
                    'COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_SECRET_TOKEN_RULE',
                    'Telegram only accepts webhook secret_token using letters, digits, underscore and hyphen (1-256 characters). Edit the secret on the Webhook tab, Save, and try again.'
                ),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . '#grimpsabot-pane-webhook');

            return;
        }

        $webhookUrl = rtrim(Uri::root(), '/') . '/index.php?option=com_ordenproduccion&controller=telegram&task=webhook&format=raw';
        $res        = TelegramApiHelper::setWebhook($token, $webhookUrl, $secret);

        $this->stashSetWebhookDebugPayload([
            'phase'                    => 'api_response',
            'ok'                       => !empty($res['ok']),
            'http_code'                => (int) ($res['http_code'] ?? 0),
            'telegram_response_raw'    => (string) ($res['raw_body'] ?? ''),
            'parsed_description'       => (string) ($res['description'] ?? ''),
            'parsed_error'             => (string) ($res['error'] ?? ''),
            'webhook_url_registered'   => $webhookUrl,
            'curl_example_redacted'    => 'curl -sS -X POST "https://api.telegram.org/bot<BOT_TOKEN>/setWebhook" '
                . '-H "Content-Type: application/x-www-form-urlencoded" '
                . '--data-urlencode "url=' . $webhookUrl . '" '
                . '--data-urlencode "secret_token=<same_as_saved_secret>"',
        ]);

        if (!empty($res['ok'])) {
            $app->enqueueMessage(
                $this->translateOrPlain(
                    'COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_OK',
                    'Telegram webhook configured successfully.'
                ) . ' '
                    . htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'),
                'success'
            );
        } else {
            $msg = trim((string) ($res['description'] ?? $res['error'] ?? ''));
            if ($msg === '') {
                $msg = 'setWebhook failed';
            }
            $code = (int) ($res['http_code'] ?? 0);
            if ($code > 0) {
                $msg .= ' (HTTP ' . $code . ')';
            }
            // Do not pass Telegram text through Text::sprintf — it may contain "%" and break Joomla translation.
            $app->enqueueMessage(
                $this->translateOrPlain(
                    'COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_ERR',
                    'Telegram setWebhook failed.'
                ) . ' '
                    . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false) . $returnHash);
    }

    /**
     * Tab hash after setWebhook redirect (POST grimpsabot_webhook_return: bot | webhook).
     *
     * @return  string
     *
     * @since   3.109.26
     */
    private function getGrimpsabotWebhookReturnHash(): string
    {
        $tab = $this->input->post->getWord('grimpsabot_webhook_return', 'webhook');

        return $tab === 'bot' ? '#grimpsabot-pane-bot' : '#grimpsabot-pane-webhook';
    }

    /**
     * Load component language the same way as Grimpsabot HtmlView (site + admin paths).
     *
     * @return  void
     *
     * @since   3.109.28
     */
    private function loadGrimpsabotLanguageForMessages(): void
    {
        $app  = Factory::getApplication();
        $lang = $app->getLanguage();
        $tag  = $lang->getTag();
        $lang->load('com_ordenproduccion', JPATH_SITE, $tag, true);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion', $tag, true);
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', $tag, true);
    }

    /**
     * @param   string  $key      Language constant
     * @param   string  $english  Fallback if constant missing or untranslated
     *
     * @return  string
     *
     * @since   3.109.28
     */
    private function translateOrPlain(string $key, string $english): string
    {
        $t = Text::_($key);

        if ($t === '' || $t === $key) {
            return $english;
        }

        return $t;
    }

    /**
     * One-shot JSON for the Grimpsabot Webhook tab debug box (no bot token).
     *
     * @param   array<string,mixed>  $payload
     *
     * @return  void
     *
     * @since   3.109.28
     */
    private function stashSetWebhookDebugPayload(array $payload): void
    {
        $json = \json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"error":"json_encode failed"}';
        }
        if (\strlen($json) > 12000) {
            $json = \substr($json, 0, 12000) . "\n… (truncated)";
        }

        Factory::getApplication()->setUserState('com_ordenproduccion.grimpsabot_setwebhook_debug', $json);
    }

    /**
     * One-shot JSON for getMe / getWebhookInfo (no bot token in payload).
     *
     * @param   array<string,mixed>  $payload
     *
     * @return  void
     *
     * @since   3.109.30
     */
    private function stashTelegramBotInfoDebugPayload(array $payload): void
    {
        $json = \json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"error":"json_encode failed"}';
        }
        if (\strlen($json) > 12000) {
            $json = \substr($json, 0, 12000) . "\n… (truncated)";
        }

        Factory::getApplication()->setUserState('com_ordenproduccion.grimpsabot_telegram_botinfo_debug', $json);
    }

    /**
     * @return  bool  True if token valid
     */
    protected function verifyToken(): bool
    {
        if (!Session::checkToken()) {
            Factory::getApplication()->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=grimpsabot', false));

            return false;
        }

        return true;
    }

    /**
     * @return  bool
     */
    protected function allowManageBotSettings(): bool
    {
        return AccessHelper::isSuperUser() || AccessHelper::isInAdministracionOrAdmonGroup();
    }
}
