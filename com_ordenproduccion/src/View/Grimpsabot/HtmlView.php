<?php

/**
 * Grimpsa bot — Telegram configuration (frontend).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\Grimpsabot
 * @since       3.105.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Grimpsabot;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramQueueHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * View grimpsabot.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var    \Joomla\CMS\Form\Form|null
     * @since  3.105.0
     */
    protected $form;

    /**
     * @var    bool
     * @since  3.105.0
     */
    protected $canManageBotSettings = false;

    /**
     * @var    bool
     * @since  3.105.0
     */
    protected $telegramTableOk = false;

    /**
     * Current user's stored Telegram chat_id (for display).
     *
     * @var    string
     * @since  3.105.0
     */
    protected $myChatId = '';

    /**
     * One-line crontab example: real URL when a cron secret is saved, else YOUR_SECRET placeholder.
     *
     * @var    string
     * @since  3.108.3
     */
    protected $telegramCronCrontabLine = '';

    /**
     * True when component params contain a non-empty telegram_queue_cron_key.
     *
     * @var    bool
     * @since  3.108.3
     */
    protected $telegramCronCrontabKeyConfigured = false;

    /**
     * Current page (1-based) for pending queue table.
     *
     * @var    int
     * @since  3.109.13
     */
    protected $telegramQueuePendingPage = 1;

    /**
     * Total pages for pending queue (0 if empty).
     *
     * @var    int
     * @since  3.109.13
     */
    protected $telegramQueuePendingTotalPages = 0;

    /**
     * Current page (1-based) for sent log table.
     *
     * @var    int
     * @since  3.109.13
     */
    protected $telegramQueueSentPage = 1;

    /**
     * Total pages for sent log (0 if empty).
     *
     * @var    int
     * @since  3.109.13
     */
    protected $telegramQueueSentTotalPages = 0;

    /**
     * @param   string|null  $tpl  Template name
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $user = Factory::getUser();
        if ($user->guest) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_LOGIN_REQUIRED'), 'notice');
            $ret = base64_encode(Uri::getInstance()->toString());
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $ret, false));

            return;
        }

        // Load site + component (+ admin) language so form field labels and template strings resolve (see Invoice HtmlView).
        $app  = Factory::getApplication();
        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE, $lang->getTag(), true);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion', $lang->getTag(), true);
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', $lang->getTag(), true);

        $this->canManageBotSettings = AccessHelper::isSuperUser() || AccessHelper::isInAdministracionOrAdmonGroup();
        $this->telegramTableOk      = TelegramNotificationHelper::telegramUsersTableExists(
            Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)
        );

        $model = $this->getModel('Grimpsabot');
        $this->form = $model ? $model->getForm() : null;
        $cid = TelegramNotificationHelper::getChatIdForUser((int) $user->id);
        $this->myChatId = $cid ?? '';

        if ($this->canManageBotSettings) {
            $params = ComponentHelper::getParams('com_ordenproduccion');
            $key    = trim((string) $params->get('telegram_queue_cron_key', ''));
            $base   = rtrim(Uri::root(), '/') . '/index.php?option=com_ordenproduccion&controller=telegram&task=processQueue&format=raw&cron_key=';
            if ($key !== '') {
                $this->telegramCronCrontabLine          = '*/2 * * * * wget -q -O - ' . \escapeshellarg($base . $key);
                $this->telegramCronCrontabKeyConfigured = true;
            } else {
                $this->telegramCronCrontabLine = '*/2 * * * * wget -q -O - ' . \escapeshellarg($base . 'YOUR_SECRET');
            }

            $db     = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $input  = Factory::getApplication()->input;
            $pageSz = TelegramQueueHelper::QUEUE_PAGE_SIZE;

            $this->telegramQueueTableOk   = TelegramQueueHelper::telegramQueueTableExists($db);
            $this->telegramSentLogTableOk = TelegramQueueHelper::telegramSentLogTableExists($db);
            $this->telegramQueuePendingTotal = TelegramQueueHelper::countPendingQueue($db);
            $this->telegramQueueSentTotal   = TelegramQueueHelper::countSentLog($db);

            $this->telegramQueuePendingTotalPages = $this->telegramQueuePendingTotal > 0
                ? (int) \ceil($this->telegramQueuePendingTotal / $pageSz)
                : 0;
            $this->telegramQueueSentTotalPages = $this->telegramQueueSentTotal > 0
                ? (int) \ceil($this->telegramQueueSentTotal / $pageSz)
                : 0;

            $this->telegramQueuePendingPage = \max(1, (int) $input->getInt('tg_qp', 1));
            if ($this->telegramQueuePendingTotalPages > 0 && $this->telegramQueuePendingPage > $this->telegramQueuePendingTotalPages) {
                $this->telegramQueuePendingPage = $this->telegramQueuePendingTotalPages;
            }
            $this->telegramQueueSentPage = \max(1, (int) $input->getInt('tg_qs', 1));
            if ($this->telegramQueueSentTotalPages > 0 && $this->telegramQueueSentPage > $this->telegramQueueSentTotalPages) {
                $this->telegramQueueSentPage = $this->telegramQueueSentTotalPages;
            }

            $pendingStart = ($this->telegramQueuePendingPage - 1) * $pageSz;
            $sentStart    = ($this->telegramQueueSentPage - 1) * $pageSz;

            $this->telegramQueuePending = TelegramQueueHelper::getPendingQueueItemsForDisplay($db, $pageSz, $pendingStart);
            $this->telegramQueueSent    = TelegramQueueHelper::getSentLogItemsForDisplay($db, $pageSz, $sentStart);
        }

        $this->setLayout('default');
        $this->_prepareDocument();
        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function _prepareDocument()
    {
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TITLE'));
    }
}
