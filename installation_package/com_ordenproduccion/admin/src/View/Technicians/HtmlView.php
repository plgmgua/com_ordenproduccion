<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Technicians;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Date\Date;

/**
 * Technicians view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The technicians data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $technicians;

    /**
     * The todays technicians data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $todaysTechnicians;

    /**
     * The attendance data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $attendanceData;

    /**
     * The statistics data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $statistics;

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
        $this->technicians = $this->get('Technicians');
        $this->todaysTechnicians = $this->get('TodaysTechnicians');
        $this->attendanceData = $this->get('AttendanceData');
        $this->statistics = $this->get('Statistics');

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
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_TECHNICIANS_TITLE'), 'users ordenproduccion');

        $toolbar = Toolbar::getInstance('toolbar');

        // Add sync attendance button
        $toolbar->standardButton('sync')
            ->text('COM_ORDENPRODUCCION_SYNC_ATTENDANCE')
            ->icon('icon-sync')
            ->task('technicians.syncAttendance');

        // Add export button
        if ($user->authorise('core.export', 'com_ordenproduccion')) {
            $toolbar->standardButton('export')
                ->text('COM_ORDENPRODUCCION_EXPORT_TECHNICIANS')
                ->icon('icon-download')
                ->task('technicians.exportTechnicians');
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
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_TECHNICIANS_TITLE'));

        // Load Bootstrap
        HTMLHelper::_('bootstrap.framework');

        // Load component assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.technicians', 'media/com_ordenproduccion/css/technicians.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.technicians', 'media/com_ordenproduccion/js/technicians.js', [], ['version' => 'auto']);

        // Add inline JavaScript for technicians functionality
        $this->document->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize technicians
                if (typeof OrdenproduccionTechnicians !== 'undefined') {
                    OrdenproduccionTechnicians.init({
                        ajaxUrl: '" . \Joomla\CMS\Uri\Uri::root() . "administrator/index.php?option=com_ordenproduccion&task=technicians.getTodaysTechnicians&format=json&" . Session::getFormToken() . "=1'
                    });
                }
            });
        ");
    }

    /**
     * Get status color class
     *
     * @param   string  $status  The status
     *
     * @return  string  CSS class
     *
     * @since   1.0.0
     */
    protected function getStatusColor($status)
    {
        $colors = [
            'active' => 'success',
            'inactive' => 'secondary',
            'busy' => 'warning',
            'offline' => 'danger'
        ];

        return $colors[$status] ?? 'secondary';
    }

    /**
     * Get status text
     *
     * @param   string  $status  The status
     *
     * @return  string  Status text
     *
     * @since   1.0.0
     */
    protected function getStatusText($status)
    {
        $texts = [
            'active' => 'COM_ORDENPRODUCCION_TECHNICIAN_STATUS_ACTIVE',
            'inactive' => 'COM_ORDENPRODUCCION_TECHNICIAN_STATUS_INACTIVE',
            'busy' => 'COM_ORDENPRODUCCION_TECHNICIAN_STATUS_BUSY',
            'offline' => 'COM_ORDENPRODUCCION_TECHNICIAN_STATUS_OFFLINE'
        ];

        return Text::_($texts[$status] ?? $status);
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
            return Factory::getDate($date)->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format time for display
     *
     * @param   string  $time  The time string
     *
     * @return  string  Formatted time
     *
     * @since   1.0.0
     */
    protected function formatTime($time)
    {
        if (empty($time) || $time === '00:00:00') {
            return '-';
        }

        try {
            return Factory::getDate($time)->format('H:i');
        } catch (\Exception $e) {
            return $time;
        }
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

    /**
     * Get technician workload percentage
     *
     * @param   object  $technician  The technician object
     *
     * @return  int  Workload percentage
     *
     * @since   1.0.0
     */
    protected function getWorkloadPercentage($technician)
    {
        $maxOrders = 10; // Maximum orders per technician
        $activeOrders = $technician->active_orders ?? 0;
        
        return min(100, ($activeOrders / $maxOrders) * 100);
    }

    /**
     * Get workload color class
     *
     * @param   int  $percentage  The workload percentage
     *
     * @return  string  CSS class
     *
     * @since   1.0.0
     */
    protected function getWorkloadColor($percentage)
    {
        if ($percentage >= 90) {
            return 'danger';
        } elseif ($percentage >= 70) {
            return 'warning';
        } elseif ($percentage >= 50) {
            return 'info';
        } else {
            return 'success';
        }
    }
}
