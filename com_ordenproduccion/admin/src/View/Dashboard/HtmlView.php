<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Session\Session;

/**
 * Dashboard view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The statistics data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $statistics;

    /**
     * The recent orders data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $recentOrders;

    /**
     * The calendar data
     *
     * @var    array
     * @since  1.0.0
     */
    protected $calendarData;

    /**
     * The version information
     *
     * @var    array
     * @since  1.0.0
     */
    protected $versionInfo;

    /**
     * The current year
     *
     * @var    int
     * @since  1.0.0
     */
    protected $currentYear;

    /**
     * The current month
     *
     * @var    int
     * @since  1.0.0
     */
    protected $currentMonth;

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
        $this->recentOrders = $this->get('RecentOrders');
        $this->calendarData = $this->get('CalendarData');
        $this->versionInfo = $this->get('VersionInfo');

        // Set current year and month
        $this->currentYear = Factory::getDate()->format('Y');
        $this->currentMonth = Factory::getDate()->format('m');

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
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_DASHBOARD'), 'dashboard ordenproduccion');

        $toolbar = Toolbar::getInstance('toolbar');

        // Add refresh button
        $toolbar->standardButton('refresh')
            ->text('COM_ORDENPRODUCCION_REFRESH_STATS')
            ->icon('icon-refresh')
            ->task('dashboard.refreshStats');

        // Add export buttons
        if ($user->authorise('core.export', 'com_ordenproduccion')) {
            $toolbar->standardButton('export-stats')
                ->text('COM_ORDENPRODUCCION_EXPORT_STATISTICS')
                ->icon('icon-chart')
                ->task('dashboard.exportData');
            
            $toolbar->standardButton('export-orders')
                ->text('COM_ORDENPRODUCCION_EXPORT_RECENT_ORDERS')
                ->icon('icon-list')
                ->task('dashboard.exportData');
            
            $toolbar->standardButton('export-calendar')
                ->text('COM_ORDENPRODUCCION_EXPORT_CALENDAR')
                ->icon('icon-calendar')
                ->task('dashboard.exportData');
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
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_DASHBOARD'));

        // Load Bootstrap
        HTMLHelper::_('bootstrap.framework');

        // Load component assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.dashboard', 'media/com_ordenproduccion/css/dashboard.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.dashboard', 'media/com_ordenproduccion/js/dashboard.js', [], ['version' => 'auto']);

        // Add inline JavaScript for dashboard functionality
        $this->document->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize dashboard
                if (typeof OrdenproduccionDashboard !== 'undefined') {
                    OrdenproduccionDashboard.init({
                        currentYear: " . $this->currentYear . ",
                        currentMonth: " . $this->currentMonth . ",
                        ajaxUrl: '" . \Joomla\CMS\Uri\Uri::root() . "administrator/index.php?option=com_ordenproduccion&task=dashboard.getCalendarData&format=json&" . Session::getFormToken() . "=1'
                    });
                }
            });
        ");
    }

    /**
     * Get formatted number for display
     *
     * @param   int  $number  The number to format
     *
     * @return  string  Formatted number
     *
     * @since   1.0.0
     */
    protected function formatNumber($number)
    {
        return number_format($number);
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
            'nueva' => 'success',
            'en_proceso' => 'warning',
            'terminada' => 'info',
            'cerrada' => 'secondary'
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
            'nueva' => 'COM_ORDENPRODUCCION_STATUS_NUEVA',
            'en_proceso' => 'COM_ORDENPRODUCCION_STATUS_EN_PROCESO',
            'terminada' => 'COM_ORDENPRODUCCION_STATUS_TERMINADA',
            'cerrada' => 'COM_ORDENPRODUCCION_STATUS_CERRADA'
        ];

        return Text::_($texts[$status] ?? $status);
    }

    /**
     * Get month name
     *
     * @param   int  $month  The month number
     *
     * @return  string  Month name
     *
     * @since   1.0.0
     */
    protected function getMonthName($month)
    {
        $months = [
            1 => 'COM_ORDENPRODUCCION_MONTH_JANUARY',
            2 => 'COM_ORDENPRODUCCION_MONTH_FEBRUARY',
            3 => 'COM_ORDENPRODUCCION_MONTH_MARCH',
            4 => 'COM_ORDENPRODUCCION_MONTH_APRIL',
            5 => 'COM_ORDENPRODUCCION_MONTH_MAY',
            6 => 'COM_ORDENPRODUCCION_MONTH_JUNE',
            7 => 'COM_ORDENPRODUCCION_MONTH_JULY',
            8 => 'COM_ORDENPRODUCCION_MONTH_AUGUST',
            9 => 'COM_ORDENPRODUCCION_MONTH_SEPTEMBER',
            10 => 'COM_ORDENPRODUCCION_MONTH_OCTOBER',
            11 => 'COM_ORDENPRODUCCION_MONTH_NOVEMBER',
            12 => 'COM_ORDENPRODUCCION_MONTH_DECEMBER'
        ];

        return Text::_($months[$month] ?? '');
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
