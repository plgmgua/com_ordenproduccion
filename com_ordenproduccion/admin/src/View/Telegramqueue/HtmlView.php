<?php

/**
 * Admin: Telegram queue (pending) and sent log.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Telegramqueue;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View telegramqueue.
 *
 * @since  3.109.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var    array<int, object>
     * @since  3.109.0
     */
    protected $pendingItems = [];

    /**
     * @var    array<int, object>
     * @since  3.109.0
     */
    protected $sentItems = [];

    /**
     * @var    int
     * @since  3.109.0
     */
    protected $pendingTotal = 0;

    /**
     * @var    int
     * @since  3.109.0
     */
    protected $sentTotal = 0;

    /**
     * @var    bool
     * @since  3.109.0
     */
    protected $queueTableOk = false;

    /**
     * @var    bool
     * @since  3.109.0
     */
    protected $sentLogTableOk = false;

    /**
     * @param   string|null  $tpl  Template name
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $user = Factory::getUser();
        if (!$user->authorise('core.manage', 'com_ordenproduccion')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var \Grimpsa\Component\Ordenproduccion\Administrator\Model\TelegramqueueModel $model */
        $model = $this->getModel('Telegramqueue');

        $this->queueTableOk   = $model->isQueueTablePresent();
        $this->sentLogTableOk = $model->isSentLogTablePresent();
        $this->pendingItems   = $model->getPendingItems();
        $this->sentItems      = $model->getSentItems();
        $this->pendingTotal   = $model->getPendingCount();
        $this->sentTotal      = $model->getSentCount();

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_TITLE'), 'comments ordenproduccion');

        if (Factory::getUser()->authorise('core.admin', 'com_ordenproduccion')) {
            ToolbarHelper::preferences('com_ordenproduccion');
        }
    }
}
