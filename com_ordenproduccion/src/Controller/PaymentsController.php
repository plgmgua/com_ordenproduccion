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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

/**
 * Payments controller (export to Excel).
 *
 * @since  1.0.0
 */
class PaymentsController extends BaseController
{
    /**
     * Export payments report to Excel (.xlsx) or fallback to CSV.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function export()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        if (!AccessHelper::hasOrderAccess()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $app->redirect(Route::_('index.php', false));
            return;
        }

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Payments', 'Site', ['ignore_request' => false]);
            $items = $model->getItems();
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_REPORTES_EXPORT_ERROR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=payments', false));
            return;
        }

        $cols = ['Fecha', 'Cliente', 'Orden', 'Tipo', 'Nº Doc.', 'Monto', 'Agente de Ventas', 'Banco'];

        $rows = [];
        foreach ($items as $item) {
            $created = !empty($item->created) ? Factory::getDate($item->created)->format('Y-m-d H:i') : '';
            $rows[] = [
                $created,
                $item->client_name ?? '',
                $item->order_number ?? $item->orden_de_trabajo ?? '',
                $this->translatePaymentType($item->payment_type ?? ''),
                $item->document_number ?? '',
                number_format((float) ($item->payment_amount ?? 0), 2),
                $item->sales_agent ?? '',
                $item->bank ?? '',
            ];
        }

        $autoload = JPATH_ROOT . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            try {
                $this->exportXlsx($cols, $rows, $app);
                return;
            } catch (\Throwable $e) {
                // Fall through to CSV
            }
        }

        $this->exportCsv($cols, $rows, $app);
    }

    /**
     * Translate payment type for export
     *
     * @param   string  $type  Payment type code
     *
     * @return  string
     */
    protected function translatePaymentType($type)
    {
        $map = [
            'efectivo' => 'Efectivo',
            'cheque' => 'Cheque',
            'transferencia' => 'Transferencia',
            'deposito' => 'Depósito',
            'nota_credito_fiscal' => 'Nota Crédito Fiscal',
        ];
        return $map[strtolower($type ?? '')] ?? $type;
    }

    /**
     * Export to Excel .xlsx using PhpSpreadsheet
     *
     * @param   array   $cols  Column headers
     * @param   array   $rows  Data rows
     * @param   object  $app   Application
     *
     * @return  void
     */
    protected function exportXlsx($cols, $rows, $app)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pagos');

        $sheet->fromArray($cols, null, 'A1');
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FF667eea');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'pagos-' . date('Y-m-d-His') . '.xlsx';
        @ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $app->close();
    }

    /**
     * Export to CSV (fallback)
     *
     * @param   array   $cols  Column headers
     * @param   array   $rows  Data rows
     * @param   object  $app   Application
     *
     * @return  void
     */
    protected function exportCsv($cols, $rows, $app)
    {
        $filename = 'pagos-' . date('Y-m-d-His') . '.csv';
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
}
