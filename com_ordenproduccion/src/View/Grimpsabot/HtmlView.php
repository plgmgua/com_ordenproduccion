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

        $this->canManageBotSettings = AccessHelper::isSuperUser() || AccessHelper::isInAdministracionOrAdmonGroup();
        $this->telegramTableOk      = TelegramNotificationHelper::telegramUsersTableExists(
            Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)
        );

        $model = $this->getModel('Grimpsabot');
        $this->form = $model ? $model->getForm() : null;
        $cid = TelegramNotificationHelper::getChatIdForUser((int) $user->id);
        $this->myChatId = $cid ?? '';

        $this->setLayout('default');
        parent::display($tpl);
    }
}
