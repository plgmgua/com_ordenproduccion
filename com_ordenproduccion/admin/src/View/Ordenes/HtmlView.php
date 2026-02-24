<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Ordenes;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Date\Date;

/**
 * Ordenes view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The search tools form
     *
     * @var    \JForm
     * @since  1.0.0
     */
    public $filterForm;

    /**
     * The active filters
     *
     * @var    array
     * @since  1.0.0
     */
    public $activeFilters = [];

    /**
     * An array of items
     *
     * @var    array
     * @since  1.0.0
     */
    protected $items = [];

    /**
     * The pagination object
     *
     * @var    Pagination
     * @since  1.0.0
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    \JObject
     * @since  1.0.0
     */
    protected $state;

    /**
     * Is this view an Empty State
     *
     * @var  boolean
     * @since 1.0.0
     */
    private $isEmptyState = false;

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
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        if ((!is_array($this->items) || !count($this->items)) && $this->isEmptyState = $this->get('IsEmptyState')) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        $canDo = \JHelperContent::getActions('com_ordenproduccion');
        $user  = Factory::getUser();

        // Get the toolbar object instance
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_ORDENES_TITLE'), 'list ordenproduccion');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('orden.add');
        }

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            $childBar->publish('ordenes.publish')->listCheck(true);
            $childBar->unpublish('ordenes.unpublish')->listCheck(true);
            $childBar->archive('ordenes.archive')->listCheck(true);

            if ($user->authorise('core.admin')) {
                $childBar->checkin('ordenes.checkin')->listCheck(true);
            }

            if ($this->state->get('filter.published') != -2) {
                $childBar->trash('ordenes.trash')->listCheck(true);
            }
        }

        if ($this->state->get('filter.published') == -2 && $canDo->get('core.delete')) {
            $toolbar->delete('ordenes.delete')
                ->text('JTOOLBAR_EMPTY_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.create')) {
            $toolbar->standardButton('duplicate')
                ->text('JTOOLBAR_DUPLICATE')
                ->icon('icon-copy')
                ->task('ordenes.duplicate')
                ->listCheck(true);
        }

        // Add export dropdown
        if ($canDo->get('core.export')) {
            $toolbar->dropdownButton('export-group')
                ->text('COM_ORDENPRODUCCION_EXPORT')
                ->toggleSplit(false)
                ->icon('icon-download')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $toolbar->getChildToolbar();

            $childBar->standardButton('export-csv')
                ->text('COM_ORDENPRODUCCION_EXPORT_CSV')
                ->icon('icon-file-text')
                ->task('ordenes.exportCsv');

            $childBar->standardButton('export-excel')
                ->text('COM_ORDENPRODUCCION_EXPORT_EXCEL')
                ->icon('icon-file-excel')
                ->task('ordenes.exportExcel');
        }

        // Add batch operations
        if ($user->authorise('core.edit')) {
            $toolbar->standardButton('batch')
                ->text('JTOOLBAR_BATCH')
                ->icon('icon-check-square')
                ->task('ordenes.batchStatus')
                ->listCheck(true);
        }

        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_ordenproduccion');
        }

        $toolbar->help('', false, 'https://grimpsa.com/docs/com_ordenproduccion');
    }

    /**
     * Returns an array of fields the table can be sorted by
     *
     * @return  array  Array containing the field name to sort by as the key and display text as value
     *
     * @since   1.0.0
     */
    protected function getSortFields()
    {
        return [
            'a.orden_de_trabajo' => Text::_('COM_ORDENPRODUCCION_HEADING_ORDER_NUMBER'),
            'a.nombre_del_cliente' => Text::_('COM_ORDENPRODUCCION_HEADING_CLIENT'),
            'a.fecha_de_entrega' => Text::_('COM_ORDENPRODUCCION_HEADING_DELIVERY_DATE'),
            'a.status' => Text::_('COM_ORDENPRODUCCION_HEADING_STATUS'),
            'a.type' => Text::_('COM_ORDENPRODUCCION_HEADING_TYPE'),
            'a.created' => Text::_('COM_ORDENPRODUCCION_HEADING_CREATED'),
            'a.created_by' => Text::_('COM_ORDENPRODUCCION_HEADING_CREATED_BY'),
            'a.id' => Text::_('JGRID_HEADING_ID')
        ];
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
     * Get type color class
     *
     * @param   string  $type  The type
     *
     * @return  string  CSS class
     *
     * @since   1.0.0
     */
    protected function getTypeColor($type)
    {
        $colors = [
            'externa' => 'primary',
            'interna' => 'info'
        ];

        return $colors[$type] ?? 'secondary';
    }

    /**
     * Get type text
     *
     * @param   string  $type  The type
     *
     * @return  string  Type text
     *
     * @since   1.0.0
     */
    protected function getTypeText($type)
    {
        $texts = [
            'externa' => 'COM_ORDENPRODUCCION_TYPE_EXTERNA',
            'interna' => 'COM_ORDENPRODUCCION_TYPE_INTERNA'
        ];

        return Text::_($texts[$type] ?? $type);
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
            return Factory::getDate($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format datetime for display
     *
     * @param   string  $datetime  The datetime string
     *
     * @return  string  Formatted datetime
     *
     * @since   1.0.0
     */
    protected function formatDateTime($datetime)
    {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '-';
        }

        try {
            return Factory::getDate($datetime)->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return $datetime;
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
}
