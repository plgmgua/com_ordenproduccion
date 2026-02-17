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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

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
     * Sync recent data from biometric system (last 7 days)
     *
     * @return  void
     *
     * @since   3.2.0
     */
    public function sync()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Check authorization
        if ($user->guest) {
            if ($app->input->get('format') === 'json' || $app->input->server->get('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
                exit;
            }
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }

        $model = $this->getModel('Asistencia');
        
        // Sync last 7 days
        $dateTo = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        
        $isAjax = $app->input->get('format') === 'json' || $app->input->server->get('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest';
        
        if ($isAjax) {
            // Return JSON response for AJAX requests
            header('Content-Type: application/json');
            
            try {
                if ($model->syncRecentData($dateFrom, $dateTo)) {
                    echo json_encode([
                        'success' => true,
                        'message' => Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_SUCCESS')
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_ERROR')
                    ]);
                }
            } catch (\Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
        } else {
            // Regular request - redirect as before
            if ($model->syncRecentData($dateFrom, $dateTo)) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_SUCCESS'), 'success');
            } else {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_ERROR'), 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
        }
    }

    /**
     * Regenerate ALL attendance summaries from biometric system
     *
     * @return  void
     *
     * @since   3.4.0
     */
    public function regenerateAll()
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
        
        // Get date range from asistencia table
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                'MIN(' . $db->quoteName('authdate') . ') AS min_date',
                'MAX(' . $db->quoteName('authdate') . ') AS max_date'
            ])
            ->from($db->quoteName('asistencia'))
            ->where($db->quoteName('authdate') . ' IS NOT NULL');
        
        $db->setQuery($query);
        $dateRange = $db->loadObject();
        
        if (!$dateRange || !$dateRange->min_date || !$dateRange->max_date) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_DATA_FOUND'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }
        
        $dateFrom = $dateRange->min_date;
        $dateTo = $dateRange->max_date;
        
        // Show progress message
        $app->enqueueMessage(
            Text::sprintf('COM_ORDENPRODUCCION_ASISTENCIA_REGENERATING_ALL', $dateFrom, $dateTo),
            'info'
        );
        
        // Regenerate all summaries
        if ($model->syncRecentData($dateFrom, $dateTo)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_REGENERATE_ALL_SUCCESS'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_REGENERATE_ALL_ERROR'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
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
            AsistenciaHelper::safeText('COM_ORDENPRODUCCION_DAY', 'Day', 'Día'),
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
                AsistenciaHelper::getDayName($item->work_date),
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

    /**
     * Export attendance data to Excel format (True XLSX using PhpSpreadsheet)
     *
     * @return  void
     *
     * @since   3.4.0
     */
    public function exportExcel()
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

        // Query database directly to bypass all model limitations
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        
        $query = $db->getQuery(true);
        $query->select([
            'a.*',
            'e.department',
            'e.position',
            'e.group_id',
            'g.name AS group_name',
            'g.color AS group_color'
        ])
            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('a.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employee_groups', 'g') . ' ON ' .
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.work_date') . ' >= ' . $db->quote($dateFrom))
            ->where($db->quoteName('a.work_date') . ' <= ' . $db->quote($dateTo))
            ->order($db->quoteName('a.work_date') . ' DESC, ' . $db->quoteName('a.personname') . ' ASC');
        
        $db->setQuery($query);
        $items = $db->loadObjectList();

        if (empty($items)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_DATA_TO_EXPORT'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return;
        }
        
        // Calculate hours_difference for each item
        foreach ($items as $item) {
            $item->hours_difference = ($item->total_hours ?? 0) - ($item->expected_hours ?? 8);
        }

        // Load PhpSpreadsheet
        require_once JPATH_ROOT . '/vendor/autoload.php';
        
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Asistencia');

            // Set headers
            $headers = [
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_PERSONNAME'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_WORK_DATE'),
                AsistenciaHelper::safeText('COM_ORDENPRODUCCION_DAY', 'Day', 'Día'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_FIRST_ENTRY'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LAST_EXIT'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_HOURS'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EXPECTED_HOURS'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_HOURS_DIFFERENCE'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_STATUS'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_GROUP'),
                Text::_('COM_ORDENPRODUCCION_EMPLOYEE_DEPARTMENT'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LATE'),
                Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EARLY_EXIT')
            ];
            
            $sheet->fromArray($headers, null, 'A1');
            
            // Style headers
            $headerStyle = $sheet->getStyle('A1:M1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
            $headerStyle->getFill()->getStartColor()->setARGB('FF4CAF50');
            
            // Add data rows
            $rowIndex = 2;
            foreach ($items as $item) {
                $sheet->fromArray([
                    $item->personname,
                    $item->work_date,
                    AsistenciaHelper::getDayName($item->work_date),
                    $item->first_entry,
                    $item->last_exit,
                    number_format($item->total_hours, 2),
                    number_format($item->expected_hours, 2),
                    number_format($item->hours_difference, 2),
                    $item->is_complete ? Text::_('COM_ORDENPRODUCCION_ASISTENCIA_COMPLETE') : Text::_('COM_ORDENPRODUCCION_ASISTENCIA_INCOMPLETE'),
                    $item->group_name ?? Text::_('COM_ORDENPRODUCCION_EMPLOYEE_NO_GROUP'),
                    $item->department ?? '',
                    $item->is_late ? Text::_('JYES') : Text::_('JNO'),
                    $item->is_early_exit ? Text::_('JYES') : Text::_('JNO')
                ], null, 'A' . $rowIndex);
                
                // Color code status (column I after day name)
                if ($item->is_complete) {
                    $sheet->getStyle('I' . $rowIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $sheet->getStyle('I' . $rowIndex)->getFill()->getStartColor()->setARGB('FFC8E6C9');
                } else {
                    $sheet->getStyle('I' . $rowIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $sheet->getStyle('I' . $rowIndex)->getFill()->getStartColor()->setARGB('FFFFCDD2');
                }
                
                // Highlight late entries (column L)
                if ($item->is_late) {
                    $sheet->getStyle('L' . $rowIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $sheet->getStyle('L' . $rowIndex)->getFill()->getStartColor()->setARGB('FFFFF9C4');
                }
                
                $rowIndex++;
            }
            
            // Auto-size columns
            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Add borders
            $styleArray = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ];
            $sheet->getStyle('A1:M' . ($rowIndex - 1))->applyFromArray($styleArray);
            
            // Generate filename with date range
            $filename = 'asistencia_' . $dateFrom . '_to_' . $dateTo . '.xlsx';
            
            // Send to browser
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            
            $app->close();
            
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::sprintf('Error creating Excel file: %s', $e->getMessage()), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
        }
    }
}

