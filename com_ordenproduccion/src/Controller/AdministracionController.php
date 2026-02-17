<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Administracion controller (reportes export).
 *
 * @since  3.6.0
 */
class AdministracionController extends BaseController
{
    /**
     * Export report to Excel (.xlsx) or fallback to CSV if PhpSpreadsheet not available.
     *
     * @return  void
     *
     * @since   3.6.0
     */
    public function exportReport()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        $dateFrom = $app->input->getString('filter_report_date_from', '');
        $dateTo = $app->input->getString('filter_report_date_to', '');
        $client = $app->input->getString('filter_report_client', '');
        $nit = $app->input->getString('filter_report_nit', '');
        $salesAgent = $app->input->getString('filter_report_sales_agent', '');

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
            $rows = $model->getReportWorkOrders($dateFrom, $dateTo, $client, $nit, $salesAgent);
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_REPORTES_EXPORT_ERROR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=reportes', false));
            return;
        }

        // Use Spanish for Excel column headers (reportes)
        $lang = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage('es-ES');
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $cols = [
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_WORK_ORDER'),
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_CLIENT_NAME'),
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_REQUEST_DATE'),
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_DELIVERY_DATE'),
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_WORK_DESCRIPTION'),
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_INVOICE_VALUE'),
        ];

        $autoload = JPATH_ROOT . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            try {
                $this->exportReportXlsx($cols, $rows, $app);
                return;
            } catch (\Throwable $e) {
                // Fall through to CSV if XLSX fails
            }
        }

        $this->exportReportCsv($cols, $rows, $app);
    }

    /**
     * Export report data to CSV (fallback when PhpSpreadsheet not available).
     *
     * @param   array   $cols   Column headers
     * @param   array   $rows   Data rows (objects from getReportWorkOrders)
     * @param   object  $app    Application
     *
     * @return  void
     *
     * @since   3.6.0
     */
    protected function exportReportCsv($cols, $rows, $app)
    {
        $filename = 'reporte-ordenes-' . date('Y-m-d-His') . '.csv';
        @ob_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, $cols);
        foreach ($rows as $row) {
            $requestDate = !empty($row->request_date) ? Factory::getDate($row->request_date)->format('Y-m-d') : '';
            $deliveryDate = !empty($row->delivery_date) ? Factory::getDate($row->delivery_date)->format('Y-m-d') : '';
            $invoiceVal = isset($row->invoice_value) ? (float) $row->invoice_value : 0;
            fputcsv($out, [
                $row->orden_de_trabajo ?? '',
                $row->client_name ?? '',
                $requestDate,
                $deliveryDate,
                $row->work_description ?? '',
                number_format($invoiceVal, 2),
            ]);
        }
        fclose($out);
        $app->close();
    }

    /**
     * Export report data to Excel .xlsx using PhpSpreadsheet.
     *
     * @param   array   $cols   Column headers
     * @param   array   $rows   Data rows (objects from getReportWorkOrders)
     * @param   object  $app    Application
     *
     * @return  void
     *
     * @since   3.6.0
     */
    protected function exportReportXlsx($cols, $rows, $app)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte órdenes');

        $sheet->fromArray($cols, null, 'A1');
        $headerStyle = $sheet->getStyle('A1:F1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FF667eea');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $requestDate = !empty($row->request_date) ? Factory::getDate($row->request_date)->format('Y-m-d') : '';
            $deliveryDate = !empty($row->delivery_date) ? Factory::getDate($row->delivery_date)->format('Y-m-d') : '';
            $invoiceVal = isset($row->invoice_value) ? (float) $row->invoice_value : 0;
            $sheet->fromArray([
                $row->orden_de_trabajo ?? '',
                $row->client_name ?? '',
                $requestDate,
                $deliveryDate,
                $row->work_description ?? '',
                number_format($invoiceVal, 2),
            ], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'reporte-ordenes-' . date('Y-m-d-His') . '.xlsx';
        @ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $app->close();
    }

    /**
     * Merge selected clients into one (super user only).
     *
     * @return  void
     *
     * @since   3.55.0
     */
    public function mergeClients()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        if (!$user->authorise('core.admin')) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_SUPER_USER_ONLY'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        $sources = $app->input->post->get('merge_sources', [], 'array');
        $targetName = trim($app->input->post->getString('merge_target_name', ''));
        $targetNit = trim($app->input->post->getString('merge_target_nit', ''));

        if (empty($sources) || $targetName === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_SELECT_AT_LEAST_ONE'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
            $updated = $model->mergeClients($sources, $targetName, $targetNit !== '' ? $targetNit : null);
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_CLIENTES_MERGE_SUCCESS', $updated), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
    }

    /**
     * Save client opening balance (amount paid up to Dec 31 2025).
     *
     * @return  void
     *
     * @since   3.56.0
     */
    public function saveOpeningBalance()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        $clientName = trim($app->input->post->getString('client_name', ''));
        $nit = trim($app->input->post->getString('nit', ''));
        $amount = (float) $app->input->post->get('amount', 0, 'float');

        if ($clientName === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_OPENING_BALANCE_ERROR_CLIENT_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
            $model->saveOpeningBalance($clientName, $nit, $amount);
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_OPENING_BALANCE_SAVED'), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_OPENING_BALANCE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
    }

    /**
     * Initialize opening balances from Oct–Dec 2025 invoice totals (super user only).
     *
     * @return  void
     *
     * @since   3.56.0
     */
    public function initializeOpeningBalances()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        if (!$user->authorise('core.admin')) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_SUPER_USER_ONLY'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
            $count = $model->initializeOpeningBalances();
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_CLIENTES_INITIALIZE_SUCCESS', $count), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_INITIALIZE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes', false));
    }
}
