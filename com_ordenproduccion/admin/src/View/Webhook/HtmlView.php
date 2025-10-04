<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Webhook;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Session\Session;

/**
 * Webhook view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The webhook statistics
     *
     * @var    array
     * @since  1.0.0
     */
    protected $statistics;

    /**
     * The webhook logs
     *
     * @var    array
     * @since  1.0.0
     */
    protected $logs;

    /**
     * The webhook configuration
     *
     * @var    array
     * @since  1.0.0
     */
    protected $config;

    /**
     * The webhook endpoints
     *
     * @var    array
     * @since  1.0.0
     */
    protected $endpoints;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null)
    {
        $this->statistics = $this->get('Statistics');
        $this->logs = $this->get('Logs');
        $this->config = $this->get('Config');
        $this->endpoints = $this->get('Endpoints');

        $this->addToolbar();
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        $user = Factory::getUser();

        // Set the title
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_WEBHOOK_CONFIG'), 'link ordenproduccion');

        $toolbar = Toolbar::getInstance('toolbar');

        // Add test webhook button
        $toolbar->standardButton('test')
            ->text('COM_ORDENPRODUCCION_TEST_WEBHOOK')
            ->icon('icon-play')
            ->task('webhook.testWebhook');

        // Add export logs button
        if ($user->authorise('core.export', 'com_ordenproduccion')) {
            $toolbar->standardButton('export')
                ->text('COM_ORDENPRODUCCION_EXPORT_LOGS')
                ->icon('icon-download')
                ->task('webhook.exportLogs');
        }

        // Add clear logs button
        if ($user->authorise('core.delete', 'com_ordenproduccion')) {
            $toolbar->standardButton('clear')
                ->text('COM_ORDENPRODUCCION_CLEAR_LOGS')
                ->icon('icon-trash')
                ->task('webhook.clearLogs');
        }

        // Add help button
        if ($user->authorise('core.admin', 'com_ordenproduccion')) {
            ToolbarHelper::preferences('com_ordenproduccion');
        }

        ToolbarHelper::help('', false, 'https://grimpsa.com/docs/com_ordenproduccion');
    }

    /**
     * Prepare the document
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function _prepareDocument()
    {
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_WEBHOOK_CONFIG'));

        // Load Bootstrap
        HTMLHelper::_('bootstrap.framework');

        // Load component assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.webhook', 'media/com_ordenproduccion/css/webhook.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.webhook', 'media/com_ordenproduccion/js/webhook.js', [], ['version' => 'auto']);

        // Add inline JavaScript for webhook functionality
        $this->document->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize webhook
                if (typeof OrdenproduccionWebhook !== 'undefined') {
                    OrdenproduccionWebhook.init({
                        ajaxUrl: '" . \Joomla\CMS\Uri\Uri::root() . "administrator/index.php?option=com_ordenproduccion&task=webhook.getStats&format=json&" . Session::getFormToken() . "=1'
                    });
                }
            });
        ");
    }

    /**
     * Format date for display
     *
     * @param   string  $date  The date string
     *
     * @return  string  Formatted date
     *
     * @since   1.0.0
     */
    protected function formatDate($date)
    {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            return Factory::getDate($date)->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Get log type color class
     *
     * @param   string  $type  The log type
     *
     * @return  string  CSS class
     *
     * @since   1.0.0
     */
    protected function getLogTypeColor($type)
    {
        $colors = [
            'webhook_request' => 'primary',
            'webhook_error' => 'danger',
            'order_created' => 'success',
            'order_updated' => 'info'
        ];

        return $colors[$type] ?? 'secondary';
    }

    /**
     * Get log type text
     *
     * @param   string  $type  The log type
     *
     * @return  string  Log type text
     *
     * @since   1.0.0
     */
    protected function getLogTypeText($type)
    {
        $texts = [
            'webhook_request' => 'COM_ORDENPRODUCCION_LOG_WEBHOOK_REQUEST',
            'webhook_error' => 'COM_ORDENPRODUCCION_LOG_WEBHOOK_ERROR',
            'order_created' => 'COM_ORDENPRODUCCION_LOG_ORDER_CREATED',
            'order_updated' => 'COM_ORDENPRODUCCION_LOG_ORDER_UPDATED'
        ];

        return Text::_($texts[$type] ?? $type);
    }

    /**
     * Check if user has permission
     *
     * @param   string  $action  The action to check
     *
     * @return  boolean  True if user has permission
     *
     * @since   1.0.0
     */
    protected function hasPermission($action)
    {
        $user = Factory::getUser();
        return $user->authorise($action, 'com_ordenproduccion');
    }
}
