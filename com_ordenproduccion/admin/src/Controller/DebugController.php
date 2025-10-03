<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Grimpsa\Component\Ordenproduccion\Administrator\Helper\DebugHelper;
use Grimpsa\Component\Ordenproduccion\Administrator\Helper\SecurityHelper;

/**
 * Debug controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class DebugController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'debug';

    /**
     * Display the debug console view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  BaseController  This object to support chaining
     *
     * @since   1.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        // Set the default view if not set
        $view = $this->input->get('view', $this->default_view);
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }

    /**
     * Toggle debug mode
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function toggleDebug()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        try {
            $currentConfig = DebugHelper::getConfig();
            $newEnabled = !$currentConfig['enabled'];
            
            $config = ['debug_mode' => $newEnabled ? '1' : '0'];
            $result = DebugHelper::updateConfig($config);
            
            if ($result) {
                $message = $newEnabled ? 
                    Text::_('COM_ORDENPRODUCCION_DEBUG_ENABLED') : 
                    Text::_('COM_ORDENPRODUCCION_DEBUG_DISABLED');
                $this->app->enqueueMessage($message, 'success');
                
                // Log the action
                DebugHelper::info('Debug mode ' . ($newEnabled ? 'enabled' : 'disabled') . ' by admin');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_UPDATING_DEBUG_CONFIG'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_UPDATING_DEBUG_CONFIG', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
    }

    /**
     * Update debug configuration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function updateConfig()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        try {
            $config = [
                'debug_mode' => $this->input->getString('debug_mode', '0'),
                'debug_log_level' => $this->input->getString('debug_log_level', 'DEBUG'),
                'debug_log_retention_days' => $this->input->getInt('debug_log_retention_days', 7)
            ];

            // Validate log level
            $validLevels = ['ERROR', 'WARNING', 'INFO', 'DEBUG'];
            if (!in_array($config['debug_log_level'], $validLevels)) {
                $config['debug_log_level'] = 'DEBUG';
            }

            // Validate retention days
            if ($config['debug_log_retention_days'] < 1 || $config['debug_log_retention_days'] > 365) {
                $config['debug_log_retention_days'] = 7;
            }

            $result = DebugHelper::updateConfig($config);
            
            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_DEBUG_CONFIG_UPDATED'), 'success');
                DebugHelper::info('Debug configuration updated by admin', $config);
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_UPDATING_DEBUG_CONFIG'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_UPDATING_DEBUG_CONFIG', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
    }

    /**
     * Clear debug logs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function clearLogs()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        try {
            $result = DebugHelper::clearLogs();
            
            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_DEBUG_LOGS_CLEARED'), 'success');
                DebugHelper::info('Debug logs cleared by admin');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_CLEARING_DEBUG_LOGS'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_CLEARING_DEBUG_LOGS', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
    }

    /**
     * Cleanup old debug logs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function cleanupLogs()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        try {
            $removedCount = DebugHelper::cleanupLogs();
            
            if ($removedCount > 0) {
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_DEBUG_LOGS_CLEANED', $removedCount),
                    'success'
                );
                DebugHelper::info('Debug logs cleaned by admin', ['removed_lines' => $removedCount]);
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_NO_OLD_DEBUG_LOGS'), 'info');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_CLEANING_DEBUG_LOGS', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
    }

    /**
     * Get debug logs via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getLogs()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->app->close();
        }

        try {
            $lines = $this->input->getInt('lines', 100);
            $logs = DebugHelper::getLogs($lines);
            
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => true,
                'data' => $logs
            ]);
            
        } catch (\Exception $e) {
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $this->app->close();
    }

    /**
     * Get debug statistics via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getStats()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->app->close();
        }

        try {
            $stats = DebugHelper::getLogStats();
            $config = DebugHelper::getConfig();
            
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'config' => $config
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $this->app->close();
    }

    /**
     * Test debug logging
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testLogging()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        try {
            // Test different log levels
            DebugHelper::debug('Test debug message', ['test' => true]);
            DebugHelper::info('Test info message', ['test' => true]);
            DebugHelper::warning('Test warning message', ['test' => true]);
            DebugHelper::error('Test error message', ['test' => true]);
            
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_DEBUG_TEST_MESSAGES_ADDED'), 'success');

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_TESTING_DEBUG', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
    }

    /**
     * Export debug logs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function exportLogs()
    {
        // Check for request forgeries
        if (!SecurityHelper::validateCSRF()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        // Check permissions
        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
            return;
        }

        try {
            $logs = DebugHelper::getLogs(1000); // Export last 1000 lines
            $filename = 'debug_logs_' . date('Y-m-d_H-i-s') . '.txt';

            // Set headers for file download
            $this->app->setHeader('Content-Type', 'text/plain');
            $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->app->setHeader('Cache-Control', 'no-cache, must-revalidate');
            $this->app->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

            echo implode('', $logs);
            $this->app->close();

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_EXPORTING_DEBUG_LOGS', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=debug'));
        }
    }
}
