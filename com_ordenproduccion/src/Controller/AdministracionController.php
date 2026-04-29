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
use Joomla\CMS\Uri\Uri;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceListHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;

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
     * Export invoices list to Excel (.xlsx) or CSV (respects current filters).
     *
     * @return  void
     * @since   3.97.0
     */
    public function exportInvoicesExcel()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false);

        if ($user->guest || !AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirectUrl);
            return;
        }

        $input = $app->input;
        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Invoices', 'Site', ['ignore_request' => false]);
        if (!$model) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICES_LOAD_ERROR'), 'error');
            $app->redirect($redirectUrl);
            return;
        }
        $model->setState('filter.nit', $input->getString('filter_nit', ''));
        $model->setState('filter.cliente', $input->getString('filter_cliente', ''));
        $model->setState('filter.fecha_from', $input->getString('filter_fecha_from', ''));
        $model->setState('filter.fecha_to', $input->getString('filter_fecha_to', ''));
        $model->setState('filter.total_min', $input->getString('filter_total_min', ''));
        $model->setState('filter.total_max', $input->getString('filter_total_max', ''));
        $tipoRaw = strtolower(trim($input->getString('filter_tipo', '')));
        $model->setState('filter.tipo', \in_array($tipoRaw, ['valid', 'mockup'], true) ? $tipoRaw : '');
        $model->setState('list.limit', 999999);
        $model->setState('list.start', 0);

        $items = $model->getItems();
        if (!is_array($items)) {
            $items = [];
        }

        $cols = ['Serie | Número', 'Fecha de Emisión', 'NIT', 'Tipo', 'Cliente', 'Total Factura (Q)'];
        $rows = [];
        foreach ($items as $invoice) {
            $felExtra = [];
            if (!empty($invoice->fel_extra) && is_string($invoice->fel_extra)) {
                $felExtra = json_decode($invoice->fel_extra, true) ?: [];
            }
            $serie = $felExtra['autorizacion_serie'] ?? '';
            $numero = $felExtra['autorizacion_numero_dte'] ?? '';
            $serieNumero = trim($serie . ' | ' . $numero) ?: '—';
            $fecha = !empty($invoice->fel_fecha_emision) ? $invoice->fel_fecha_emision : ($invoice->invoice_date ?? null);
            $fechaStr = $fecha ? Factory::getDate($fecha)->format('d-m-Y H:i:s') : '—';
            $nit = trim($invoice->client_nit ?? $invoice->fel_receptor_id ?? '') ?: '—';
            $tipoLabel = InvoiceListHelper::isMockupInvoice($invoice)
                ? Text::_('COM_ORDENPRODUCCION_INVOICE_TIPO_MOCKUP')
                : Text::_('COM_ORDENPRODUCCION_INVOICE_TIPO_VALID');
            $cliente = InvoiceListHelper::displayClientName($invoice);
            if ($cliente === '') {
                $cliente = '—';
            }
            $moneda = $invoice->currency ?? 'Q';
            $total = number_format((float) ($invoice->invoice_amount ?? 0), 2, '.', '') . ' ' . $moneda;
            $rows[] = [$serieNumero, $fechaStr, $nit, $tipoLabel, $cliente, $total];
        }

        $autoload = JPATH_ROOT . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                try {
                    $this->exportInvoicesXlsx($cols, $rows, $app);
                    return;
                } catch (\Throwable $e) {
                    // Fall through to CSV
                }
            }
        }
        $this->exportInvoicesCsv($cols, $rows, $app);
    }

    /**
     * Suggest invoice client names for autocomplete (JSON; partial match on client_name).
     *
     * @return  void
     * @since   3.97.0
     */
    public function suggestInvoiceClients()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest || !AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            echo json_encode([]);
            $app->close();
            return;
        }

        $q = trim($app->input->getString('q', ''));
        $suggestions = [];
        if ($q !== '') {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('client_name'))
                ->from($db->quoteName('#__ordenproduccion_invoices'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('client_name') . ' IS NOT NULL')
                ->where($db->quoteName('client_name') . ' != ' . $db->quote(''))
                ->where($db->quoteName('client_name') . ' LIKE ' . $db->quote('%' . $db->escape($q, true) . '%'))
                ->order($db->quoteName('client_name') . ' ASC')
                ->setLimit(25);
            $db->setQuery($query);
            $rows = $db->loadColumn() ?: [];
            $suggestions = array_values(array_map('trim', array_filter($rows)));
        }

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode($suggestions);
        $app->close();
    }

    /**
     * Export invoices to CSV.
     */
    protected function exportInvoicesCsv(array $cols, array $rows, $app)
    {
        $filename = 'facturas-' . date('Y-m-d-His') . '.csv';
        @ob_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, $cols);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        $app->close();
    }

    /**
     * Export invoices to Excel .xlsx
     */
    protected function exportInvoicesXlsx(array $cols, array $rows, $app)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facturas');
        $sheet->fromArray($cols, null, 'A1');
        $headerStyle = $sheet->getStyle('A1:F1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FF667eea');
        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowIndex);
            $rowIndex++;
        }
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $filename = 'facturas-' . date('Y-m-d-His') . '.xlsx';
        @ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $app->close();
    }

    /**
     * Export Financiero → Listado PRE to Excel (.xlsx) or CSV (same filters as on screen; super user only).
     *
     * @return  void
     *
     * @since   3.115.26
     */
    public function exportFinancieroExcel()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=listado', false);

        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirectUrl);

            return;
        }

        $input = $app->input;
        $f     = AdministracionModel::normalizeFinancieroFilters([
            'financiero_filter_date_from' => $input->getString('financiero_filter_date_from', ''),
            'financiero_filter_date_to' => $input->getString('financiero_filter_date_to', ''),
            'financiero_filter_agent' => $input->getString('financiero_filter_agent', ''),
            'financiero_filter_facturar' => $input->getString('financiero_filter_facturar', ''),
        ]);

        try {
            /** @var AdministracionModel $model */
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
            if (!$model) {
                throw new \RuntimeException('Administracion model unavailable');
            }
            $pack = $model->getFinancieroPrecotizacionesData(200000, 0, $f);
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FINANCIERO_EXPORT_ERROR'), 'error');
            $app->redirect($redirectUrl);

            return;
        }

        $rows = $pack['rows'] ?? [];

        if (!is_array($rows)) {
            $rows = [];
        }

        $lang = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage('es-ES');
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $cols = [
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_PRECOT'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_FACTURAR'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_AGENTE'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_SUBTOTAL'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_MARGEN'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_MARGEN_TOTAL_REF'),
            $lang->_('COM_ORDENPRODUCCION_PARAM_IVA'),
            $lang->_('COM_ORDENPRODUCCION_PARAM_ISR'),
            $lang->_('COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA'),
            $lang->_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_ADICIONAL'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_TOTAL'),
            $lang->_('COM_ORDENPRODUCCION_PRE_COTIZACION_COMISION_MARGEN_ADICIONAL'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_COTIZ'),
            $lang->_('COM_ORDENPRODUCCION_FINANCIERO_COL_CONFIRM'),
        ];

        $numFmt = static function (float $v): string {
            return number_format(round($v, 2), 2, '.', '');
        };

        $rowGrand = static function (object $r): float {
            $tf = isset($r->total_final) && $r->total_final !== null && $r->total_final !== '' ? (float) $r->total_final : null;
            $t  = isset($r->total) ? (float) $r->total : 0.0;
            $ma = isset($r->margen_adicional) && $r->margen_adicional !== null && $r->margen_adicional !== '' ? (float) $r->margen_adicional : 0.0;
            $base = $tf !== null ? $tf : $t;

            return round($base + $ma, 2);
        };

        $rowPrecotLabel = static function (object $r): string {
            $pid = isset($r->id) ? (int) $r->id : 0;
            $raw = isset($r->number) ? trim((string) $r->number) : '';

            return $raw !== '' ? $raw : ('PRE-' . str_pad((string) max(1, $pid), 5, '0', STR_PAD_LEFT));
        };

        $facturarLabel = static function (object $r) use ($lang): string {
            if (!property_exists($r, 'facturar') || $r->facturar === null || $r->facturar === '') {
                return '—';
            }
            $v  = $r->facturar;
            $on = ($v === true || $v === 1 || $v === '1' || (string) $v === '1');

            return $on ? $lang->_('COM_ORDENPRODUCCION_FINANCIERO_FACTURAR_SI') : $lang->_('COM_ORDENPRODUCCION_FINANCIERO_FACTURAR_NO');
        };

        $confirmLabel = static function (object $r) use ($lang): string {
            if (!property_exists($r, 'cotizacion_confirmada') || $r->cotizacion_confirmada === null) {
                return '—';
            }

            return (int) $r->cotizacion_confirmada === 1 ? $lang->_('JYES') : $lang->_('JNO');
        };

        $outRows = [];

        foreach ($rows as $r) {
            if (!is_object($r)) {
                continue;
            }
            $margenAm           = isset($r->margen_amount) ? (float) $r->margen_amount : 0.0;
            $margenAd           = isset($r->margen_adicional) && $r->margen_adicional !== null && $r->margen_adicional !== '' ? (float) $r->margen_adicional : 0.0;
            $margenTotDisplay   = round($margenAm + $margenAd, 2);
            $qnum               = isset($r->linked_quotation_number) ? trim((string) $r->linked_quotation_number) : '';
            $ag                 = isset($r->financiero_agent_label) ? trim((string) $r->financiero_agent_label) : '';

            $outRows[] = [
                $rowPrecotLabel($r),
                $facturarLabel($r),
                $ag !== '' ? $ag : '—',
                $numFmt((float) ($r->lines_subtotal ?? 0)),
                $numFmt($margenAm),
                $numFmt($margenTotDisplay),
                $numFmt((float) ($r->iva_amount ?? 0)),
                $numFmt((float) ($r->isr_amount ?? 0)),
                $numFmt((float) ($r->comision_amount ?? 0)),
                $numFmt($margenAd),
                $numFmt($rowGrand($r)),
                $numFmt((float) ($r->comision_margen_adicional ?? 0)),
                $qnum !== '' ? $qnum : '—',
                $confirmLabel($r),
            ];
        }

        $autoload = JPATH_ROOT . '/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;

            if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                try {
                    $this->exportFinancieroListXlsx($cols, $outRows, $app);

                    return;
                } catch (\Throwable $e) {
                    // Fall through to CSV
                }
            }
        }

        $this->exportFinancieroListCsv($cols, $outRows, $app);
    }

    /**
     * @param   array<int, string>   $cols
     * @param   array<int, mixed[]>  $rows
     *
     * @since   3.115.26
     */
    protected function exportFinancieroListCsv(array $cols, array $rows, $app): void
    {
        $filename = 'financiero-pre-' . date('Y-m-d-His') . '.csv';
        @ob_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, $cols);

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        $app->close();
    }

    /**
     * @param   array<int, string>   $cols
     * @param   array<int, mixed[]>  $rows
     *
     * @since   3.115.26
     */
    protected function exportFinancieroListXlsx(array $cols, array $rows, $app): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Listado PRE');
        $sheet->fromArray($cols, null, 'A1');

        $nCols          = max(1, count($cols));
        $lastColLetter  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nCols);
        $headerStyle    = $sheet->getStyle('A1:' . $lastColLetter . '1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FF667eea');

        $rowIndex = 2;

        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowIndex);
            $rowIndex++;
        }

        for ($ci = 1; $ci <= $nCols; $ci++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
        }

        $filename = 'financiero-pre-' . date('Y-m-d-His') . '.xlsx';
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
     * Save work order numbering (next sequence, prefix, format) — Ajustes → Numeración órdenes.
     *
     * @return  void
     *
     * @since   3.113.94
     */
    public function saveWorkOrderNumbering()
    {
        $app  = Factory::getApplication();
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

        $returnSubtab = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=numeracion_ordenes', false);

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        $jform = $app->input->post->get('jform', [], 'array');
        $next  = isset($jform['next_order_number']) ? (int) $jform['next_order_number'] : 0;
        $prefix = isset($jform['order_prefix']) ? trim((string) $jform['order_prefix']) : '';
        $format = isset($jform['order_format']) ? trim((string) $jform['order_format']) : '';

        if ($next < 1 || $next > 999999) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_INVALID_NEXT'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        if ($prefix === '' || \strlen($prefix) > 10) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_INVALID_PREFIX'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        $allowed = ['PREFIX-NUMBER', 'NUMBER', 'PREFIX-NUMBER-YEAR', 'NUMBER-YEAR'];

        if (!\in_array($format, $allowed, true)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_INVALID_FORMAT'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        $settingsModel = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();

        if ($settingsModel->saveWorkOrderNumbering($next, $prefix, $format)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SAVED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_SAVE_ERROR'), 'error');
        }

        $returnUrl = $app->input->post->getString('return_url', '');
        if ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) {
            $app->redirect($returnUrl);
        } else {
            $app->redirect($returnSubtab);
        }
    }

    /**
     * Set next_order_number from MAX existing órdenes numeric suffix + 1 (Ajustes → Numeración órdenes).
     *
     * @return  void
     *
     * @since   3.113.94
     */
    public function resyncWorkOrderNumbering()
    {
        $app  = Factory::getApplication();
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

        $returnSubtab = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=numeracion_ordenes', false);
        $returnUrl    = $app->input->post->getString('return_url', '');
        $redirectOk   = ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) ? $returnUrl : $returnSubtab;

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirectOk);

            return;
        }

        $settingsModel = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
        $newCounter    = $settingsModel->resyncOrderCounter();

        if ($newCounter > 0) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SYNC_OK', $newCounter), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SYNC_FAIL'), 'error');
        }

        $app->redirect($redirectOk);
    }

    /**
     * Save orden de compra numbering (Ajustes / Productos → Numeración).
     *
     * @return  void
     *
     * @since   3.113.96
     */
    public function saveOrdenCompraNumbering()
    {
        $app  = Factory::getApplication();
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

        $returnSubtab = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=numeracion_ordenes', false);

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        $jform = $app->input->post->get('jform_oc', [], 'array');
        $next  = isset($jform['next_orden_compra_number']) ? (int) $jform['next_orden_compra_number'] : 0;
        $prefix = isset($jform['orden_compra_prefix']) ? trim((string) $jform['orden_compra_prefix']) : '';
        $width = isset($jform['orden_compra_number_width']) ? (int) $jform['orden_compra_number_width'] : 5;

        if ($next < 1 || $next > 999999) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_INVALID_NEXT'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        if ($prefix === '' || \strlen($prefix) > 10) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_INVALID_PREFIX'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        if ($width < 3 || $width > 8) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_INVALID_WIDTH'), 'error');
            $app->redirect($returnSubtab);

            return;
        }

        $settingsModel = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();

        if ($settingsModel->saveOrdenCompraNumbering($next, $prefix, $width)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SAVED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_SAVE_ERROR'), 'error');
        }

        $returnUrl = $app->input->post->getString('return_url', '');
        if ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) {
            $app->redirect($returnUrl);
        } else {
            $app->redirect($returnSubtab);
        }
    }

    /**
     * Resync orden de compra counter from existing `number` values.
     *
     * @return  void
     *
     * @since   3.113.96
     */
    public function resyncOrdenCompraNumbering()
    {
        $app  = Factory::getApplication();
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

        $returnSubtab = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=numeracion_ordenes', false);
        $returnUrl    = $app->input->post->getString('return_url', '');
        $redirectOk   = ($returnUrl !== '' && strpos($returnUrl, 'option=com_ordenproduccion') !== false) ? $returnUrl : $returnSubtab;

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirectOk);

            return;
        }

        $settingsModel = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
        $newCounter    = $settingsModel->resyncOrdenCompraCounter();

        if ($newCounter > 0) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SYNC_OK', $newCounter), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SYNC_FAIL'), 'error');
        }

        $app->redirect($redirectOk);
    }

    /**
     * Save approval workflow definitions (Ajustes → Flujos de aprobaciones).
     *
     * @return  void
     *
     * @since   3.109.58
     */
    public function saveApprovalWorkflows()
    {
        $app = Factory::getApplication();
        $returnWfId = $app->input->post->getInt('awf_return_wf_id', 0);
        $redirect   = $returnWfId > 0
            ? Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones&wf_id=' . $returnWfId, false)
            : Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $svc = new ApprovalWorkflowService();
        if (!$svc->hasSchema()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'), 'error');
            $app->redirect($redirect);
            return;
        }

        $workflows = $app->input->post->get('awf_workflow', [], 'array');
        $steps     = $app->input->post->get('awf_step', [], 'array');

        $fail = false;

        foreach ($workflows as $wid => $fields) {
            $wid = (int) $wid;
            if ($wid < 1 || !is_array($fields)) {
                continue;
            }
            if (!$svc->adminUpdateWorkflow($wid, $fields)) {
                $fail = true;
            }
        }

        foreach ($steps as $sid => $fields) {
            $sid = (int) $sid;
            if ($sid < 1 || !is_array($fields)) {
                continue;
            }
            $atype = strtolower(trim((string) ($fields['approver_type'] ?? '')));
            if ($atype === 'user' && isset($fields['approver_user_ids']) && is_array($fields['approver_user_ids'])) {
                $picked = [];
                foreach ($fields['approver_user_ids'] as $raw) {
                    $picked[] = (int) $raw;
                }
                $picked = array_values(array_unique(array_filter($picked, static function ($id) {
                    return $id > 0;
                })));
                $fields['approver_value'] = implode(',', $picked);
                unset($fields['approver_user_ids']);
            }
            if (!$svc->adminUpdateWorkflowStep($sid, $fields)) {
                $fail = true;
            }
        }

        if ($fail) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_SAVE_PARTIAL'), 'warning');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_SAVED'), 'success');
        }

        $app->redirect($redirect);
    }

    /**
     * Add a step to a workflow (Ajustes → Flujos → edit).
     *
     * @return  void
     *
     * @since   3.109.64
     */
    public function addApprovalWorkflowStep()
    {
        $app   = Factory::getApplication();
        $wfId  = $app->input->post->getInt('workflow_id', 0);
        $redir = $wfId > 0
            ? Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones&wf_id=' . $wfId, false)
            : Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redir);
            return;
        }

        $svc = new ApprovalWorkflowService();
        if (!$svc->hasSchema() || $wfId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'), 'error');
            $app->redirect($redir);
            return;
        }

        $newStepId = $svc->adminAddWorkflowStep($wfId);
        if ($newStepId > 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_ADDED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_ADD_FAIL'), 'error');
        }

        $app->redirect($redir);
    }

    /**
     * Delete a workflow step if more than one step remains.
     *
     * @return  void
     *
     * @since   3.109.64
     */
    public function deleteApprovalWorkflowStep()
    {
        $app   = Factory::getApplication();
        $wfId  = $app->input->post->getInt('workflow_id', 0);
        $redir = $wfId > 0
            ? Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones&wf_id=' . $wfId, false)
            : Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redir);
            return;
        }

        $stepId = $app->input->post->getInt('step_id', 0);
        $svc    = new ApprovalWorkflowService();

        if (!$svc->hasSchema() || $stepId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_DELETE_FAIL'), 'error');
            $app->redirect($redir);
            return;
        }

        if ($svc->adminDeleteWorkflowStep($stepId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_DELETED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_DELETE_FAIL'), 'warning');
        }

        $app->redirect($redir);
    }

    /**
     * Save a component-scoped approval group (Ajustes → Grupos de aprobaciones).
     *
     * @return  void
     *
     * @since   3.109.64
     */
    public function saveComponentApprovalGroup()
    {
        $app = Factory::getApplication();
        $listUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($listUrl);
            return;
        }

        $svc = new ApprovalWorkflowService();
        if (!$svc->hasApprovalGroupsSchema()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUPS_SCHEMA_MISSING'), 'error');
            $app->redirect($listUrl);
            return;
        }

        $groupId     = $app->input->post->getInt('approval_group_id', 0);
        $title       = trim($app->input->post->getString('title', ''));
        $description = trim($app->input->post->getString('description', ''));
        $published   = $app->input->post->getInt('published', 0) === 1;
        $userIds     = [];
        $membersPost = $app->input->post->get('member_user_ids', [], 'array');
        if ($membersPost !== []) {
            foreach ($membersPost as $p) {
                $v = (int) $p;
                if ($v > 0) {
                    $userIds[] = $v;
                }
            }
        } else {
            $membersRaw = $app->input->post->getString('member_user_ids', '');
            $parts      = preg_split('/[\s,;]+/', $membersRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $p) {
                $userIds[] = (int) $p;
            }
        }

        $newId = $svc->adminSaveComponentApprovalGroup($groupId, [
            'title'       => $title,
            'description' => $description,
            'published'   => $published,
        ], $userIds);

        if ($newId > 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_SAVED'), 'success');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones&approval_group_id=' . $newId, false));
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_SAVE_FAIL'), 'error');
            $editUrl = $groupId > 0
                ? Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones&approval_group_id=' . $groupId, false)
                : Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones&approval_group_id=0', false);
            $app->redirect($editUrl);
        }
    }

    /**
     * Delete a component approval group if unused by workflows.
     *
     * @return  void
     *
     * @since   3.109.64
     */
    public function deleteComponentApprovalGroup()
    {
        $app     = Factory::getApplication();
        $listUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($listUrl);
            return;
        }

        $groupId = $app->input->post->getInt('approval_group_id', 0);
        $svc     = new ApprovalWorkflowService();

        if (!$svc->hasApprovalGroupsSchema() || $groupId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_DELETE_FAIL'), 'error');
            $app->redirect($listUrl);
            return;
        }

        if ($svc->adminDeleteComponentApprovalGroup($groupId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_DELETED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_DELETE_IN_USE'), 'warning');
        }

        $app->redirect($listUrl);
    }

    /**
     * Set a work order status to Anulada by order number (Ajustes > Anular orden).
     * Anulada orders are excluded from Estado de cuenta, Comprobantes de pago, and Rango de días.
     *
     * @return  void
     * @since   3.99.0
     */
    public function anularOrden()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if (!$user->authorise('core.admin', 'com_ordenproduccion') && !AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=anular_orden', false));
            return;
        }

        $jform = $app->input->post->get('jform', [], 'array');
        $ordenDeTrabajo = isset($jform['orden_de_trabajo']) ? trim((string) $jform['orden_de_trabajo']) : '';
        if ($ordenDeTrabajo === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_EMPTY'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=anular_orden', false));
            return;
        }

        try {
            $model = $this->getModel('Administracion');
            $result = $model->anularOrdenByInput($ordenDeTrabajo, $user->name ?: 'Usuario', (int) $user->id);
            if (!empty($result['success'])) {
                $app->enqueueMessage($result['message'], 'success');
            } else {
                $app->enqueueMessage($result['message'], 'error');
            }
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_SAVE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=anular_orden', false));
    }

    /**
     * Redirect URL for Facturas > Conciliar, preserving match filters from POST.
     *
     * @return  string
     * @since   3.100.7
     */
    protected function getInvoiceMatchSubtabRedirectUrl(): string
    {
        $app = Factory::getApplication();
        $matchStatus = $app->input->post->getString('match_status', '');
        $matchClient = trim($app->input->post->getString('match_client', ''));

        $url = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices&invoices_subtab=match', false);
        if ($matchStatus !== '' && in_array($matchStatus, ['pending', 'approved', 'rejected'], true)) {
            $url .= '&match_status=' . rawurlencode($matchStatus);
        }
        if ($matchClient !== '' && InvoiceOrdenMatchModel::isValidMatchClientGroupKey($matchClient)) {
            $url .= '&match_client=' . rawurlencode($matchClient);
        }

        return $url;
    }

    /**
     * Run automatic scoring for FEL invoices vs órdenes (same NIT + amount + description heuristics).
     *
     * @return  void
     * @since   3.99.0
     */
    public function analyzeInvoiceOrdenMatches()
    {
        $app = Factory::getApplication();
        $redirect = $this->getInvoiceMatchSubtabRedirectUrl();

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch');
            if (!$model) {
                $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $app->redirect($redirect);
                return;
            }
            $res = $model->runAnalysis();
            $app->enqueueMessage(Text::sprintf(
                'COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ANALYZE_DONE',
                (int) $res['invoices_processed'],
                (int) $res['suggestions_upserted']
            ), 'success');
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ANALYZE_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect($redirect);
    }

    /**
     * Approve all pending invoice ↔ orden suggestions with score ≥ 95%.
     *
     * @return  void
     * @since   3.99.2
     */
    public function approveAllInvoiceOrdenMatchesHighScore()
    {
        $app = Factory::getApplication();
        $redirect = $this->getInvoiceMatchSubtabRedirectUrl();

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch');
            if (!$model) {
                $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $app->redirect($redirect);
                return;
            }
            $count = $model->approveAllPendingAboveScore(95.0);
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_APPROVE_HIGH_SCORE_DONE', $count), 'success');
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ACTION_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect($redirect);
    }

    /**
     * Approve a pending invoice ↔ orden suggestion.
     *
     * @return  void
     * @since   3.99.0
     */
    public function approveInvoiceOrdenMatch()
    {
        $this->processInvoiceOrdenMatchDecision('approve');
    }

    /**
     * Reject a pending invoice ↔ orden suggestion.
     *
     * @return  void
     * @since   3.99.0
     */
    public function rejectInvoiceOrdenMatch()
    {
        $this->processInvoiceOrdenMatchDecision('reject');
    }

    /**
     * @param   string  $action  approve|reject
     *
     * @return  void
     */
    protected function processInvoiceOrdenMatchDecision(string $action)
    {
        $app = Factory::getApplication();
        $redirect = $this->getInvoiceMatchSubtabRedirectUrl();

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        $id = $app->input->post->getInt('cid', 0);
        if ($id <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_INVALID_ID'), 'warning');
            $app->redirect($redirect);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch');
            if (!$model) {
                $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $app->redirect($redirect);
                return;
            }
            $ok = $action === 'approve' ? $model->approveSuggestion($id) : $model->rejectSuggestion($id);
            if ($ok) {
                $app->enqueueMessage(
                    $action === 'approve'
                        ? Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_APPROVED')
                        : Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_REJECTED'),
                    'success'
                );
            } else {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_NO_CHANGE'), 'warning');
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ACTION_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect($redirect);
    }

    /**
     * Manually link a work order to a FEL invoice (same NIT). Super Users only.
     *
     * @return  void
     * @since   3.100.0
     */
    public function addManualInvoiceOrdenMatch()
    {
        $app = Factory::getApplication();
        $redirect = $this->getInvoiceMatchSubtabRedirectUrl();

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        $invoiceId = $app->input->post->getInt('invoice_id', 0);
        $ordenId = $app->input->post->getInt('orden_id', 0);
        if ($invoiceId <= 0 || $ordenId <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_MANUAL_ADD_INVALID'), 'warning');
            $app->redirect($redirect);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch');
            if (!$model) {
                $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $app->redirect($redirect);
                return;
            }
            $ok = $model->addManualInvoiceOrdenAssociation($invoiceId, $ordenId);
            $app->enqueueMessage(
                $ok ? Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_MANUAL_ADD_SUCCESS') : Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_MANUAL_ADD_NOOP'),
                $ok ? 'success' : 'notice'
            );
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ACTION_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect($redirect);
    }

    /**
     * Remove a suggestion row (unlink invoice ↔ orden). Super Users only.
     *
     * @return  void
     * @since   3.100.0
     */
    public function removeInvoiceOrdenMatch()
    {
        $app = Factory::getApplication();
        $redirect = $this->getInvoiceMatchSubtabRedirectUrl();

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        $id = $app->input->post->getInt('cid', 0);
        if ($id <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_INVALID_ID'), 'warning');
            $app->redirect($redirect);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch');
            if (!$model) {
                $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $app->redirect($redirect);
                return;
            }
            $ok = $model->deleteSuggestion($id);
            $app->enqueueMessage(
                $ok ? Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_REMOVE_SUCCESS') : Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_NO_CHANGE'),
                $ok ? 'success' : 'notice'
            );
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ACTION_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect($redirect);
    }

    /**
     * Approve selected pending suggestions for one invoice (checkbox batch). Super Users only.
     *
     * @return  void
     * @since   3.100.2
     */
    public function associateSelectedInvoiceOrdenMatches()
    {
        $app = Factory::getApplication();
        $redirect = $this->getInvoiceMatchSubtabRedirectUrl();

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        $invoiceId = $app->input->post->getInt('invoice_id', 0);
        $cid = $app->input->post->get('cid', [], 'array');
        if (!is_array($cid)) {
            $cid = [];
        }

        if ($invoiceId <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_INVALID_ID'), 'warning');
            $app->redirect($redirect);
            return;
        }

        if ($cid === []) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ASSOCIATE_NONE'), 'notice');
            $app->redirect($redirect);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch');
            if (!$model) {
                $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $app->redirect($redirect);
                return;
            }
            $n = $model->approveSuggestionsForInvoice($invoiceId, $cid);
            if ($n > 0) {
                $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ASSOCIATE_DONE', $n), 'success');
            } else {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_NO_CHANGE'), 'notice');
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ACTION_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $app->redirect($redirect);
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

        $toProcess = self::expandUploadedFilesToXmlList($files);
        if (empty($toProcess)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_NO_XML'), 'warning');
            $app->redirect($redirectUrl);
            return;
        }
        $tempDirs = [];
        foreach ($toProcess as $item) {
            if (!empty($item['temp_dir'])) {
                $tempDirs[$item['temp_dir']] = true;
            }
        }

        $imported = 0;
        $skipped = 0;
        $report = [];
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        foreach ($toProcess as $file) {
            $tmpPath = $file['tmp_name'] ?? '';
            $fileName = $file['name'] ?? 'file';
            $isExtracted = !empty($file['is_extracted']);
            $canRead = $tmpPath !== '' && (($isExtracted && is_file($tmpPath)) || is_uploaded_file($tmpPath));

            if (!$canRead) {
                $report[] = ['file' => $fileName, 'status' => 'error', 'message' => Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_READ_ERROR')];
                continue;
            }

            try {
                $xmlContent = @file_get_contents($tmpPath);
                if ($xmlContent === false || trim($xmlContent) === '') {
                    $report[] = ['file' => $fileName, 'status' => 'error', 'message' => Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_READ_ERROR')];
                    continue;
                }
                $xmlContent = self::ensureUtf8($xmlContent);
                $xmlContent = self::normalizeXmlEncodingDeclaration($xmlContent);

                $result = \Grimpsa\Component\Ordenproduccion\Site\Helper\FelXmlHelper::parseFelXml($xmlContent);
                if (!$result['success']) {
                    $report[] = ['file' => $fileName, 'status' => 'error', 'message' => $result['error'] ?? 'Parse error'];
                    continue;
                }

                $data = $result['data'];
                $data['line_items'] = is_array($data['line_items']) ? json_encode($data['line_items'], JSON_UNESCAPED_UNICODE) : ($data['line_items'] ?? '[]');
                $data['created'] = $data['invoice_date'] ?? date('Y-m-d H:i:s');
                $data['created_by'] = $user->id;
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

                $exists = false;
                if (!empty($obj->fel_autorizacion_uuid)) {
                    $query = $db->getQuery(true)
                        ->select('1')
                        ->from($db->quoteName('#__ordenproduccion_invoices'))
                        ->where($db->quoteName('fel_autorizacion_uuid') . ' = ' . $db->quote($obj->fel_autorizacion_uuid));
                    $db->setQuery($query);
                    $exists = (bool) $db->loadResult();
                }
                if (!$exists && !empty($obj->invoice_number)) {
                    $query = $db->getQuery(true)
                        ->select('1')
                        ->from($db->quoteName('#__ordenproduccion_invoices'))
                        ->where($db->quoteName('invoice_number') . ' = ' . $db->quote($obj->invoice_number));
                    $db->setQuery($query);
                    $exists = (bool) $db->loadResult();
                }
                if ($exists) {
                    $skipped++;
                    $report[] = ['file' => $fileName, 'status' => 'skipped', 'message' => Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_ALREADY_EXISTS')];
                    continue;
                }

                $db->insertObject('#__ordenproduccion_invoices', $obj, 'id');
                $newInvId = (int) $db->insertid();
                if ($newInvId > 0) {
                    try {
                        TelegramNotificationHelper::notifyInvoiceCreated($newInvId);
                    } catch (\Throwable $e) {
                    }
                }
                $imported++;
                $report[] = ['file' => $fileName, 'status' => 'imported', 'message' => ''];
            } catch (\Throwable $e) {
                $report[] = ['file' => $fileName, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        foreach (array_keys($tempDirs) as $dir) {
            if (is_dir($dir)) {
                $filesInDir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($filesInDir as $f) {
                    if ($f->isDir()) {
                        @rmdir($f->getRealPath());
                    } else {
                        @unlink($f->getRealPath());
                    }
                }
                @rmdir($dir);
            }
        }

        $app->getSession()->set('com_ordenproduccion.import_report', $report);
        if ($imported > 0) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_INVOICES_IMPORTED_COUNT', $imported), 'success');
        }
        if ($skipped > 0) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_INVOICES_IMPORT_SKIPPED_COUNT', $skipped), 'notice');
        }
        $errorCount = count(array_filter($report, function ($r) { return $r['status'] === 'error'; }));
        if ($errorCount > 0) {
            $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_INVOICES_IMPORT_ERROR_COUNT', $errorCount), 'error');
        }

        $app->redirect($redirectUrl);
    }

    /**
     * Expand uploaded files to a flat list of XML files (extract ZIP contents).
     *
     * @param   array  $files  From normalizeUploadedFiles()
     * @return  array  List of ['name' => string, 'tmp_name' => string, 'is_extracted' => bool, 'temp_dir' => string|null]
     */
    private static function expandUploadedFilesToXmlList(array $files)
    {
        $list = [];
        foreach ($files as $file) {
            $name = $file['name'] ?? '';
            $tmpName = $file['tmp_name'] ?? '';
            if ($tmpName === '') {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === 'zip' && class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($tmpName, \ZipArchive::RDONLY) !== true) {
                    continue;
                }
                $tempDir = sys_get_temp_dir() . '/op_import_' . uniqid('', true);
                if (!@mkdir($tempDir, 0700, true)) {
                    $zip->close();
                    continue;
                }
                $zip->extractTo($tempDir);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if ($entry === false || strpos($entry, '..') !== false) {
                        continue;
                    }
                    if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'xml') {
                        $fullPath = $tempDir . '/' . $entry;
                        if (is_file($fullPath)) {
                            $list[] = [
                                'name'         => basename($entry),
                                'tmp_name'     => $fullPath,
                                'is_extracted' => true,
                                'temp_dir'     => $tempDir,
                            ];
                        }
                    }
                }
                $zip->close();
                continue;
            }
            if ($ext === 'xml') {
                $list[] = array_merge($file, ['is_extracted' => false, 'temp_dir' => null]);
            }
        }
        return $list;
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

    /**
     * Approve a pending internal approval workflow request (Administración tab Aprobaciones).
     *
     * @return  void
     *
     * @since   3.102.1
     */
    public function approveApprovalWorkflow()
    {
        $this->processApprovalWorkflowDecision('approve');
    }

    /**
     * Reject a pending internal approval workflow request.
     *
     * @return  void
     *
     * @since   3.102.1
     */
    public function rejectApprovalWorkflow()
    {
        $this->processApprovalWorkflowDecision('reject');
    }

    /**
     * Remove a pending approval from the list (pre-cotización or orden de compra rows on Aprobaciones).
     *
     * @return  void
     *
     * @since   3.114.8
     */
    public function cancelApprovalWorkflow()
    {
        $app      = Factory::getApplication();
        $redirect = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones', false);

        $returnPost = $app->input->post->get('return', '', 'string');
        if ($returnPost !== '') {
            $decoded = base64_decode($returnPost, true);
            if ($decoded === false || $decoded === '') {
                $decoded = base64_decode($returnPost);
            }
            if (is_string($decoded) && $decoded !== '' && Uri::isInternal($decoded)) {
                $redirect = $decoded;
            }
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);

            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);

            return;
        }

        $requestId = $app->input->post->getInt('request_id', 0);
        if ($requestId <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_INVALID_REQUEST'), 'warning');
            $app->redirect($redirect);

            return;
        }

        $svc = new ApprovalWorkflowService();
        if (!$svc->hasSchema()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'), 'error');
            $app->redirect($redirect);

            return;
        }

        if ($svc->cancelPendingRequestByApprover($requestId, (int) $user->id)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_DISMISSED_OK'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_DISMISS_FAILED'), 'warning');
        }

        $app->redirect($redirect);
    }

    /**
     * @param   string  $action  approve|reject
     *
     * @return  void
     *
     * @since   3.102.1
     */
    protected function processApprovalWorkflowDecision(string $action)
    {
        $app = Factory::getApplication();
        $redirect = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones', false);

        $returnPost = $app->input->post->get('return', '', 'string');
        if ($returnPost !== '') {
            $decoded = base64_decode($returnPost, true);
            if ($decoded === false || $decoded === '') {
                $decoded = base64_decode($returnPost);
            }
            if (is_string($decoded) && $decoded !== '' && Uri::isInternal($decoded)) {
                $redirect = $decoded;
            }
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);
            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect($redirect);
            return;
        }

        $requestId = $app->input->post->getInt('request_id', 0);
        if ($requestId <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_INVALID_REQUEST'), 'warning');
            $app->redirect($redirect);
            return;
        }

        $comment = trim($app->input->post->getString('comment', ''));

        $svc = new ApprovalWorkflowService();
        if (!$svc->hasSchema()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'), 'error');
            $app->redirect($redirect);
            return;
        }

        $ok = $action === 'approve'
            ? $svc->approve($requestId, (int) $user->id, $comment)
            : $svc->reject($requestId, (int) $user->id, $comment);

        if ($ok) {
            $app->enqueueMessage(
                $action === 'approve'
                    ? Text::_('COM_ORDENPRODUCCION_APPROVAL_APPROVED_OK')
                    : Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECTED_OK'),
                'success'
            );
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_ACTION_FAILED'), 'warning');
        }

        $app->redirect($redirect);
    }

    /**
     * Save vendor quote message templates (email / cellphone / PDF). Ajustes — Administración / Admon only.
     *
     * @return  void
     *
     * @since   3.113.0
     */
    public function saveVendorQuoteTemplates()
    {
        $app      = Factory::getApplication();
        $redirect = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=solicitud_cotizacion', false);

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($redirect);

            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
        $data  = [
            'email_subject'  => $app->input->post->getString('email_subject', ''),
            'email_body'     => (string) $app->input->post->get('email_body', '', 'raw'),
            'cellphone_body' => (string) $app->input->post->get('cellphone_body', '', 'raw'),
            'pdf_body'       => (string) $app->input->post->get('pdf_body', '', 'raw'),
        ];

        if (!$model->saveVendorQuoteTemplates($data)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TEMPLATES_SAVE_ERROR'), 'error');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TEMPLATES_SAVED'));
        }

        $app->redirect($redirect);
    }
}
