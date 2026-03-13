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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

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
        $paymentStatus = $app->input->getString('filter_report_payment_status', '');
        // Ventas: only export own data
        $salesAgentFilter = AccessHelper::getSalesAgentFilter();
        if ($salesAgentFilter !== null) {
            $salesAgent = $salesAgentFilter;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
            $rows = $model->getReportWorkOrders($dateFrom, $dateTo, $client, $nit, $salesAgent, 0, 0, $paymentStatus);
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
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_PAYMENT_RECORD'),
            $lang->_('COM_ORDENPRODUCCION_REPORTES_COL_DIFERENCIA'),
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
            $totalPaid = isset($row->total_paid) ? (float) $row->total_paid : 0;
            $diferencia = $totalPaid - $invoiceVal;
            $paymentCol = !empty($row->payment_record_numbers) ? $row->payment_record_numbers : '—';
            fputcsv($out, [
                $row->orden_de_trabajo ?? '',
                $row->client_name ?? '',
                $requestDate,
                $deliveryDate,
                $row->work_description ?? '',
                number_format($invoiceVal, 2, '.', ''),
                $paymentCol,
                number_format($diferencia, 2, '.', ''),
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
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FF667eea');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $requestDate = !empty($row->request_date) ? Factory::getDate($row->request_date)->format('Y-m-d') : '';
            $deliveryDate = !empty($row->delivery_date) ? Factory::getDate($row->delivery_date)->format('Y-m-d') : '';
            $invoiceVal = isset($row->invoice_value) ? (float) $row->invoice_value : 0;
            $totalPaid = isset($row->total_paid) ? (float) $row->total_paid : 0;
            $diferencia = $totalPaid - $invoiceVal;
            $paymentCol = !empty($row->payment_record_numbers) ? $row->payment_record_numbers : '—';
            $sheet->fromArray([
                $row->orden_de_trabajo ?? '',
                $row->client_name ?? '',
                $requestDate,
                $deliveryDate,
                $row->work_description ?? '',
                number_format($invoiceVal, 2, '.', ''),
                $paymentCol,
                number_format($diferencia, 2, '.', ''),
            ], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'H') as $col) {
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

    /**
     * Reset all pre-cotizaciones and cotizaciones records (delete all, start numbering from 1).
     * Administracion only. Requires POST with token and confirm=1.
     *
     * @return  void
     */
    public function resetCotizacionesPrecotizaciones()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=cotizaciones', false));
            return;
        }

        if ($app->input->post->getInt('confirm', 0) !== 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_CONFIRM_REQUIRED'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=cotizaciones', false));
            return;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $tableList = array_map('strtolower', $db->getTableList());

            $tables = [
                '#__ordenproduccion_quotation_items',
                '#__ordenproduccion_quotations',
                '#__ordenproduccion_pre_cotizacion_line',
                '#__ordenproduccion_pre_cotizacion',
            ];

            // Disable foreign key checks so truncate can run (quotation_items references quotations)
            $db->setQuery('SET FOREIGN_KEY_CHECKS = 0')->execute();

            try {
                foreach ($tables as $tableName) {
                    $fullName = $db->replacePrefix($tableName);
                    if ($fullName === null || $fullName === '') {
                        continue;
                    }
                    if (!in_array(strtolower($fullName), $tableList, true)) {
                        continue;
                    }
                    $db->truncateTable($fullName);
                }
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_COTIZACIONES_SUCCESS'), 'success');
            } finally {
                $db->setQuery('SET FOREIGN_KEY_CHECKS = 1')->execute();
            }
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_COTIZACIONES_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $returnUrl = $app->input->post->getString('return_url', '');
        if ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) {
            $app->redirect($returnUrl);
        } else {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=cotizaciones', false));
        }
    }

    /**
     * Save Ajustes de Cotización PDF template (Encabezado, Términos y Condiciones, Pie de página).
     * Administracion only. Requires POST with token.
     *
     * @return  void
     * @since   3.78.0
     */
    public function saveAjustesCotizacionPdf()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=ajustes_cotizacion', false));
            return;
        }

        $jform = $app->input->post->get('jform', [], 'array');
        $formatVersion = isset($jform['format_version']) ? max(1, min(2, (int) $jform['format_version'])) : 1;
        $data = [
            'format_version' => $formatVersion,
            'logo_path'  => isset($jform['logo_path'])  ? trim((string) $jform['logo_path'])  : '',
            'logo_x'     => isset($jform['logo_x'])     ? (float) $jform['logo_x']     : 15,
            'logo_y'     => isset($jform['logo_y'])     ? (float) $jform['logo_y']     : 15,
            'logo_width' => isset($jform['logo_width']) ? (float) $jform['logo_width'] : 50,
            'encabezado' => isset($jform['encabezado']) ? (string) $jform['encabezado'] : '',
            'terminos_condiciones' => isset($jform['terminos_condiciones']) ? (string) $jform['terminos_condiciones'] : '',
            'pie_pagina' => isset($jform['pie_pagina']) ? (string) $jform['pie_pagina'] : '',
            'encabezado_x' => isset($jform['encabezado_x']) ? (float) $jform['encabezado_x'] : 15,
            'encabezado_y' => isset($jform['encabezado_y']) ? (float) $jform['encabezado_y'] : 15,
            'table_x' => isset($jform['table_x']) ? (float) $jform['table_x'] : 0,
            'table_y' => isset($jform['table_y']) ? (float) $jform['table_y'] : 0,
            'terminos_x' => isset($jform['terminos_x']) ? (float) $jform['terminos_x'] : 0,
            'terminos_y' => isset($jform['terminos_y']) ? (float) $jform['terminos_y'] : 0,
            'pie_x' => isset($jform['pie_x']) ? (float) $jform['pie_x'] : 0,
            'pie_y' => isset($jform['pie_y']) ? (float) $jform['pie_y'] : 0,
        ];

        try {
            $model = $this->getModel('Administracion');
            $model->saveCotizacionPdfSettings($data);
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_SAVED'), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_SAVE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $returnUrl = $app->input->post->getString('return_url', '');
        if ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) {
            $app->redirect($returnUrl);
        } else {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=ajustes_cotizacion', false));
        }
    }

    /**
     * Save Solicitud de Orden URL (webhook URL for when user finishes confirmar steps).
     *
     * @return  void
     * @since   3.92.0
     */
    public function saveSolicitudOrden()
    {
        $app = Factory::getApplication();

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=solicitud_orden', false));
            return;
        }

        $jform = $app->input->post->get('jform', [], 'array');
        $url = isset($jform['solicitud_orden_url']) ? trim((string) $jform['solicitud_orden_url']) : '';

        try {
            $model = $this->getModel('Administracion');
            $model->saveSolicitudOrdenUrl($url);
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_SOLICITUD_ORDEN_SAVED'), 'success');
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_SAVE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $returnUrl = $app->input->post->getString('return_url', '');
        if ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) {
            $app->redirect($returnUrl);
        } else {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=solicitud_orden', false));
        }
    }

    /**
     * Import invoices from SAT Guatemala FEL XML file(s).
     * Expects POST with invoice_xml (file) or invoice_xml[] (multiple files).
     *
     * @return  void
     * @since   3.97.0
     */
    public function importInvoicesXml()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false);

        if (!Session::checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirectUrl);
            return;
        }

        if ($user->guest || !AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirectUrl);
            return;
        }

        $files = self::normalizeUploadedFiles($app->input->files->get('invoice_xml', [], 'array'));
        if (empty($files)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_NO_FILE'), 'warning');
            $app->redirect($redirectUrl);
            return;
        }

        $imported = 0;
        $errors = [];
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        foreach ($files as $file) {
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }
            $xmlContent = @file_get_contents($file['tmp_name']);
            if ($xmlContent === false || trim($xmlContent) === '') {
                $errors[] = ($file['name'] ?? 'file') . ': ' . Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_READ_ERROR');
                continue;
            }
            $xmlContent = self::ensureUtf8($xmlContent);
            // Force declaration to UTF-8 so libxml interprets bytes correctly (avoids ñ/á → ?)
            $xmlContent = self::normalizeXmlEncodingDeclaration($xmlContent);

            $result = \Grimpsa\Component\Ordenproduccion\Site\Helper\FelXmlHelper::parseFelXml($xmlContent);
            if (!$result['success']) {
                $errors[] = ($file['name'] ?? 'file') . ': ' . ($result['error'] ?? 'Parse error');
                continue;
            }

            $data = $result['data'];
            $data['line_items'] = is_array($data['line_items']) ? json_encode($data['line_items'], JSON_UNESCAPED_UNICODE) : ($data['line_items'] ?? '[]');
            $data['created'] = $data['invoice_date'] ?? date('Y-m-d H:i:s');
            $data['created_by'] = $user->id;
            // Encode fel_extra as UTF-8 JSON so ñ, á etc. are stored correctly
            if (isset($data['fel_extra']) && is_array($data['fel_extra'])) {
                $data['fel_extra'] = json_encode($data['fel_extra'], JSON_UNESCAPED_UNICODE);
            }

            $cols = $db->getTableColumns('#__ordenproduccion_invoices', false);
            $cols = $cols ? array_change_key_case($cols, CASE_LOWER) : [];
            $row = [];
            foreach ($data as $k => $v) {
                if (array_key_exists(strtolower($k), $cols)) {
                    $row[$k] = $v;
                }
            }
            $obj = (object) $row;
            try {
                $db->insertObject('#__ordenproduccion_invoices', $obj, 'id');
                $imported++;
            } catch (\Exception $e) {
                $errors[] = ($file['name'] ?? 'file') . ': ' . $e->getMessage();
            }
        }

        if ($imported > 0) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_INVOICES_IMPORTED_COUNT', $imported), 'success');
        }
        foreach ($errors as $err) {
            $app->enqueueMessage($err, 'error');
        }

        $app->redirect($redirectUrl);
    }

    /**
     * Normalize uploaded file(s) to a list of file arrays (handles single file, multiple files, and PHP multipart structure).
     *
     * @param   array  $input  Raw from input->files->get('invoice_xml', [], 'array')
     * @return  array  List of ['name' => ..., 'tmp_name' => ..., 'error' => ..., 'size' => ..., 'type' => ...]
     * @since   3.97.0
     */
    private static function normalizeUploadedFiles($input)
    {
        if (empty($input) || !is_array($input)) {
            return [];
        }
        // PHP multipart: name="invoice_xml[]" gives ['name' => [n1,n2], 'tmp_name' => [t1,t2], ...]
        if (isset($input['name']) && is_array($input['name'])) {
            $list = [];
            foreach ($input['name'] as $i => $name) {
                if (($input['tmp_name'][$i] ?? '') !== '' && (int) ($input['error'][$i] ?? 4) === UPLOAD_ERR_OK) {
                    $list[] = [
                        'name'     => $name,
                        'tmp_name' => $input['tmp_name'][$i] ?? '',
                        'error'    => (int) ($input['error'][$i] ?? 4),
                        'size'     => (int) ($input['size'][$i] ?? 0),
                        'type'     => $input['type'][$i] ?? '',
                    ];
                }
            }
            return $list;
        }
        // Single file: ['name' => 'x.xml', 'tmp_name' => '...', ...]
        if (isset($input['tmp_name']) && is_string($input['tmp_name']) && ($input['tmp_name'] !== '') && ((int) ($input['error'] ?? 4) === UPLOAD_ERR_OK)) {
            return [
                [
                    'name'     => $input['name'] ?? '',
                    'tmp_name' => $input['tmp_name'],
                    'error'    => (int) ($input['error'] ?? 0),
                    'size'     => (int) ($input['size'] ?? 0),
                    'type'     => $input['type'] ?? '',
                ],
            ];
        }
        // Already list of file arrays
        if (isset($input[0]) && is_array($input[0]) && isset($input[0]['tmp_name'])) {
            return array_values($input);
        }
        return [];
    }

    /**
     * Ensure string is valid UTF-8 so á, é, í, ó, ú, ñ etc. import correctly from XML.
     *
     * @param   string  $str  Raw file content (may be UTF-8, ISO-8859-1, or Windows-1252)
     * @return  string  UTF-8 string
     * @since   3.97.0
     */
    private static function ensureUtf8($str)
    {
        if ($str === '' || $str === null) {
            return (string) $str;
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }
        if (function_exists('mb_convert_encoding')) {
            foreach (['Windows-1252', 'ISO-8859-1', 'ISO-8859-15', 'CP1252'] as $from) {
                $utf8 = @mb_convert_encoding($str, 'UTF-8', $from);
                if ($utf8 !== false && mb_check_encoding($utf8, 'UTF-8')) {
                    return $utf8;
                }
            }
        }
        return $str;
    }

    /**
     * Set XML declaration encoding to UTF-8 so libxml interprets content correctly after ensureUtf8().
     *
     * @param   string  $xml  XML string (content already converted to UTF-8)
     * @return  string
     * @since   3.97.0
     */
    private static function normalizeXmlEncodingDeclaration($xml)
    {
        $xml = (string) $xml;
        // Replace encoding attribute value with UTF-8 so ñ, á etc. are not misinterpreted
        if (preg_match('/<\?xml\s/i', $xml)) {
            $xml = preg_replace(
                '/(<\?xml\s[^?]*encoding\s*=\s*["\'])[^"\']*(["\'])/i',
                '${1}UTF-8${2}',
                $xml,
                1
            );
        }
        return $xml;
    }
}
