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

/**
 * Dashboard controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class DashboardController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'dashboard';

    /**
     * Display the dashboard
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
     * Refresh dashboard statistics
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function refreshStats()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
            return;
        }

        try {
            $model = $this->getModel('Dashboard');
            $statistics = $model->getStatistics();
            
            // Store statistics in session for immediate display
            $this->app->getSession()->set('com_ordenproduccion.dashboard.stats', $statistics);
            
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_DASHBOARD_STATS_REFRESHED'), 'success');
            
        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_REFRESHING_STATS', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
    }

    /**
     * Export dashboard data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function exportData()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
            return;
        }

        $format = $this->input->get('format', 'csv', 'cmd');
        $type = $this->input->get('type', 'statistics', 'cmd');

        try {
            $model = $this->getModel('Dashboard');
            
            switch ($type) {
                case 'statistics':
                    $data = $model->getStatistics();
                    $filename = 'dashboard_statistics_' . date('Y-m-d_H-i-s') . '.' . $format;
                    break;
                    
                case 'recent_orders':
                    $data = $model->getRecentOrders(100);
                    $filename = 'recent_orders_' . date('Y-m-d_H-i-s') . '.' . $format;
                    break;
                    
                case 'calendar':
                    $year = $this->input->get('year', date('Y'), 'int');
                    $month = $this->input->get('month', date('m'), 'int');
                    $data = $model->getCalendarData($year, $month);
                    $filename = 'calendar_data_' . $year . '-' . $month . '_' . date('Y-m-d_H-i-s') . '.' . $format;
                    break;
                    
                default:
                    throw new \Exception('Invalid export type');
            }

            $this->exportToFile($data, $filename, $format);
            
        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_EXPORTING_DATA', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
        }
    }

    /**
     * Export data to file
     *
     * @param   array   $data      The data to export
     * @param   string  $filename  The filename
     * @param   string  $format    The export format
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function exportToFile($data, $filename, $format)
    {
        $app = Factory::getApplication();
        
        // Set headers for file download
        $app->setHeader('Content-Type', $format === 'csv' ? 'text/csv' : 'application/json');
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $app->setHeader('Cache-Control', 'no-cache, must-revalidate');
        $app->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
        
        if ($format === 'csv') {
            $this->exportToCSV($data);
        } else {
            $this->exportToJSON($data);
        }
        
        $app->close();
    }

    /**
     * Export data to CSV format
     *
     * @param   array  $data  The data to export
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function exportToCSV($data)
    {
        $output = fopen('php://output', 'w');
        
        if (is_array($data) && !empty($data)) {
            // Write headers
            if (is_object($data[0])) {
                fputcsv($output, array_keys(get_object_vars($data[0])));
            } elseif (is_array($data[0])) {
                fputcsv($output, array_keys($data[0]));
            }
            
            // Write data
            foreach ($data as $row) {
                if (is_object($row)) {
                    fputcsv($output, get_object_vars($row));
                } else {
                    fputcsv($output, $row);
                }
            }
        }
        
        fclose($output);
    }

    /**
     * Export data to JSON format
     *
     * @param   array  $data  The data to export
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function exportToJSON($data)
    {
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Get calendar data via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getCalendarData()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        $year = $this->input->get('year', date('Y'), 'int');
        $month = $this->input->get('month', date('m'), 'int');

        try {
            $model = $this->getModel('Dashboard');
            $data = $model->getCalendarData($year, $month);
            
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => true,
                'data' => $data,
                'year' => $year,
                'month' => $month
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
     * Quick action: Create new order
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function quickNewOrder()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
            return;
        }

        // Check permissions
        $user = Factory::getUser();
        if (!$user->authorise('core.create', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit'));
    }

    /**
     * Quick action: View orders
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function quickViewOrders()
    {
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
    }

    /**
     * Quick action: View technicians
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function quickViewTechnicians()
    {
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
    }
}
