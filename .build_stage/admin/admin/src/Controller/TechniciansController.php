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
 * Technicians controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class TechniciansController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'technicians';

    /**
     * Display the technicians view
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
     * Method to sync attendance data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function syncAttendance()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
            return;
        }

        try {
            $model = $this->getModel('Technicians');
            $result = $model->syncAttendanceData();

            if ($result['success']) {
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_ATTENDANCE_SYNCED', $result['count']),
                    'success'
                );
            } else {
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_ERROR_SYNCING_ATTENDANCE', $result['message']),
                    'error'
                );
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_SYNCING_ATTENDANCE', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
    }

    /**
     * Method to get today's technicians via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getTodaysTechnicians()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        try {
            $model = $this->getModel('Technicians');
            $technicians = $model->getTodaysTechnicians();
            
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => true,
                'data' => $technicians
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
     * Method to assign technician to order
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function assignToOrder()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
            return;
        }

        $technicianId = $this->input->getInt('technician_id');
        $orderId = $this->input->getInt('order_id');

        if (empty($technicianId) || empty($orderId)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
            return;
        }

        try {
            $model = $this->getModel('Technicians');
            $result = $model->assignToOrder($technicianId, $orderId);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_TECHNICIAN_ASSIGNED_SUCCESSFULLY'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ASSIGNING_TECHNICIAN'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_ASSIGNING_TECHNICIAN', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
    }

    /**
     * Method to remove technician from order
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function removeFromOrder()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
            return;
        }

        $technicianId = $this->input->getInt('technician_id');
        $orderId = $this->input->getInt('order_id');

        if (empty($technicianId) || empty($orderId)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
            return;
        }

        try {
            $model = $this->getModel('Technicians');
            $result = $model->removeFromOrder($technicianId, $orderId);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_TECHNICIAN_REMOVED_SUCCESSFULLY'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_REMOVING_TECHNICIAN'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_REMOVING_TECHNICIAN', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
    }

    /**
     * Method to get technician statistics
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getTechnicianStats()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        $technicianId = $this->input->getInt('technician_id');

        try {
            $model = $this->getModel('Technicians');
            $stats = $model->getTechnicianStats($technicianId);
            
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => true,
                'data' => $stats
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
     * Method to export technician data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function exportTechnicians()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
            return;
        }

        try {
            $model = $this->getModel('Technicians');
            $technicians = $model->getTechnicians();

            $filename = 'technicians_' . date('Y-m-d_H-i-s') . '.csv';

            // Set headers for CSV download
            $this->app->setHeader('Content-Type', 'text/csv');
            $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->app->setHeader('Cache-Control', 'no-cache, must-revalidate');
            $this->app->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

            $output = fopen('php://output', 'w');

            // Write CSV headers
            if (!empty($technicians)) {
                $headers = [
                    'ID',
                    'Name',
                    'Email',
                    'Phone',
                    'Specialization',
                    'Active Orders',
                    'Completed Orders',
                    'Last Attendance',
                    'Status'
                ];
                fputcsv($output, $headers);

                // Write data
                foreach ($technicians as $technician) {
                    $row = [
                        $technician->id,
                        $technician->name,
                        $technician->email ?? '',
                        $technician->phone ?? '',
                        $technician->specialization ?? '',
                        $technician->active_orders ?? 0,
                        $technician->completed_orders ?? 0,
                        $technician->last_attendance ?? '',
                        $technician->status ?? 'active'
                    ];
                    fputcsv($output, $row);
                }
            }

            fclose($output);
            $this->app->close();

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_EXPORTING_TECHNICIANS', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=technicians'));
        }
    }
}
