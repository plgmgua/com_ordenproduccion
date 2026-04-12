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
