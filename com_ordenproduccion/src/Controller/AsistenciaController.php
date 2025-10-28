<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die;

/**
 * Asistencia Controller
 *
 * @since  3.2.0
 */
class AsistenciaController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     * @since  3.2.0
     */
    protected $default_view = 'asistencia';

    /**
     * Display the view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters
     *
     * @return  BaseController  This object to support chaining.
     *
     * @since   3.2.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', 'asistencia', 'cmd');
        $this->input->set('view', $view);

        parent::display($cachable, $urlparams);

        return $this;
    }

    /**
     * Recalculate summaries for a date range
     *
     * @return  void
     *
     * @since   3.2.0
     */
    public function recalculate()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Check authorization
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }

        $dateFrom = $this->input->getString('date_from', '');
        $dateTo = $this->input->getString('date_to', '');

        if (empty($dateFrom) || empty($dateTo)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ERROR_DATE_RANGE_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }

        $model = $this->getModel('Asistencia');
        
        if ($model->recalculateSummaries($dateFrom, $dateTo)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_RECALCULATE_SUCCESS'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_RECALCULATE_ERROR'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
    }

    /**
     * Export attendance data to CSV
     *
     * @return  void
     *
     * @since   3.2.0
     */
    public function export()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Check authorization
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }

        $model = $this->getModel('Asistencia');
        
        // Get filtered items
        $items = $model->getItems();

        if (empty($items)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_DATA_TO_EXPORT'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }

        // Generate CSV
        $filename = 'asistencia_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, [
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_CARDNO'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_PERSONNAME'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_WORK_DATE'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_FIRST_ENTRY'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LAST_EXIT'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_HOURS'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EXPECTED_HOURS'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_HOURS_DIFFERENCE'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_STATUS'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LATE'),
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EARLY_EXIT')
        ]);
        
        // Data rows
        foreach ($items as $item) {
            fputcsv($output, [
                $item->cardno,
                $item->personname,
                $item->work_date,
                $item->first_entry,
                $item->last_exit,
                $item->total_hours,
                $item->expected_hours,
                $item->hours_difference,
                $item->is_complete ? Text::_('COM_ORDENPRODUCCION_ASISTENCIA_COMPLETE') : Text::_('COM_ORDENPRODUCCION_ASISTENCIA_INCOMPLETE'),
                $item->is_late ? Text::_('JYES') : Text::_('JNO'),
                $item->is_early_exit ? Text::_('JYES') : Text::_('JNO')
            ]);
        }
        
        fclose($output);
        $app->close();
    }
}

