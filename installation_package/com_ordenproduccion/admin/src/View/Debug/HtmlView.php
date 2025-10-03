<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Debug;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Grimpsa\Component\Ordenproduccion\Administrator\Helper\DebugHelper;

/**
 * Debug view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The debug configuration
     *
     * @var    array
     * @since  1.0.0
     */
    protected $config;

    /**
     * The debug logs
     *
     * @var    array
     * @since  1.0.0
     */
    protected $logs;

    /**
     * The debug statistics
     *
     * @var    array
     * @since  1.0.0
     */
    protected $stats;

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
        $this->config = $this->get('Config');
        $this->logs = $this->get('Logs');
        $this->stats = $this->get('Stats');

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
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_DEBUG_CONSOLE'), 'bug ordenproduccion');

        $toolbar = Toolbar::getInstance('toolbar');

        // Add toggle debug button
        $toolbar->standardButton('toggle')
            ->text($this->config['enabled'] ? 'COM_ORDENPRODUCCION_DISABLE_DEBUG' : 'COM_ORDENPRODUCCION_ENABLE_DEBUG')
            ->icon($this->config['enabled'] ? 'icon-pause' : 'icon-play')
            ->task('debug.toggleDebug');

        // Add test logging button
        $toolbar->standardButton('test')
            ->text('COM_ORDENPRODUCCION_TEST_LOGGING')
            ->icon('icon-checkmark')
            ->task('debug.testLogging');

        // Add export logs button
        if ($user->authorise('core.export', 'com_ordenproduccion')) {
            $toolbar->standardButton('export')
                ->text('COM_ORDENPRODUCCION_EXPORT_LOGS')
                ->icon('icon-download')
                ->task('debug.exportLogs');
        }

        // Add clear logs button
        if ($user->authorise('core.delete', 'com_ordenproduccion')) {
            $toolbar->standardButton('clear')
                ->text('COM_ORDENPRODUCCION_CLEAR_LOGS')
                ->icon('icon-trash')
                ->task('debug.clearLogs');
        }

        // Add cleanup logs button
        $toolbar->standardButton('cleanup')
            ->text('COM_ORDENPRODUCCION_CLEANUP_LOGS')
            ->icon('icon-refresh')
            ->task('debug.cleanupLogs');

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
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_DEBUG_CONSOLE'));

        // Load Bootstrap
        HTMLHelper::_('bootstrap.framework');

        // Load component assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.debug', 'media/com_ordenproduccion/css/debug.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.debug', 'media/com_ordenproduccion/js/debug.js', [], ['version' => 'auto']);

        // Add inline JavaScript for debug functionality
        $this->document->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize debug console
                if (typeof OrdenproduccionDebug !== 'undefined') {
                    OrdenproduccionDebug.init({
                        ajaxUrl: '" . \Joomla\CMS\Uri\Uri::root() . "administrator/index.php?option=com_ordenproduccion&task=debug.getLogs&format=json&" . Session::getFormToken() . "=1',
                        statsUrl: '" . \Joomla\CMS\Uri\Uri::root() . "administrator/index.php?option=com_ordenproduccion&task=debug.getStats&format=json&" . Session::getFormToken() . "=1'
                    });
                }
            });
        ");
    }

    /**
     * Get log level color class
     *
     * @param   string  $level  The log level
     *
     * @return  string  CSS class
     *
     * @since   1.0.0
     */
    protected function getLogLevelColor($level)
    {
        $colors = [
            'ERROR' => 'danger',
            'WARNING' => 'warning',
            'INFO' => 'info',
            'DEBUG' => 'secondary'
        ];

        return $colors[$level] ?? 'secondary';
    }

    /**
     * Get log level text
     *
     * @param   string  $level  The log level
     *
     * @return  string  Log level text
     *
     * @since   1.0.0
     */
    protected function getLogLevelText($level)
    {
        $texts = [
            'ERROR' => 'COM_ORDENPRODUCCION_LOG_LEVEL_ERROR',
            'WARNING' => 'COM_ORDENPRODUCCION_LOG_LEVEL_WARNING',
            'INFO' => 'COM_ORDENPRODUCCION_LOG_LEVEL_INFO',
            'DEBUG' => 'COM_ORDENPRODUCCION_LOG_LEVEL_DEBUG'
        ];

        return Text::_($texts[$level] ?? $level);
    }

    /**
     * Format log line for display
     *
     * @param   string  $line  Log line
     *
     * @return  array  Parsed log data
     *
     * @since   1.0.0
     */
    protected function parseLogLine($line)
    {
        $parsed = [
            'timestamp' => '',
            'level' => '',
            'version' => '',
            'user' => '',
            'message' => '',
            'context' => '',
            'raw' => $line
        ];

        // Parse log line format: [timestamp] [level] [version] [User:id:name] message context
        if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[User:([^:]+):([^\]]+)\] (.+?)(?:\s+(.+))?$/', $line, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $parsed['level'] = $matches[2];
            $parsed['version'] = $matches[3];
            $parsed['user_id'] = $matches[4];
            $parsed['user_name'] = $matches[5];
            $parsed['message'] = $matches[6];
            $parsed['context'] = isset($matches[7]) ? $matches[7] : '';
        }

        return $parsed;
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
