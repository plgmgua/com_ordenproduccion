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
use Joomla\CMS\Session\Session;
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
                ->createModel('Payments', 'Site', ['ignore_request' => true]);
            $model->setState('filter.state', 1);
            $model->setState('filter.client', $app->input->get('filter_client', '', 'string'));
            $model->setState('filter.date_from', $app->input->get('filter_date_from', '', 'string'));
            $model->setState('filter.date_to', $app->input->get('filter_date_to', '', 'string'));
            $model->setState('filter.sales_agent', $app->input->get('filter_sales_agent', '', 'string'));
            $items = $model->getItems();
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_REPORTES_EXPORT_ERROR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=payments', false));
            return;
        }

        $cols = ['ID', 'Fecha', 'Cliente', 'Orden', 'Tipo', 'Nº Doc.', 'Monto', 'Agente de Ventas', 'Registrado por', 'Banco'];

        $rows = [];
        foreach ($items as $item) {
            $paymentId = 'PA-' . str_pad((int) ($item->id ?? 0), 6, '0', STR_PAD_LEFT);
            $created = !empty($item->created) ? Factory::getDate($item->created)->format('Y-m-d H:i') : '';
            $rows[] = [
                $paymentId,
                $created,
                $item->client_name ?? '',
                $item->order_number ?? $item->orden_de_trabajo ?? '',
                $this->translatePaymentType($item->payment_type ?? ''),
                $item->document_number ?? '',
                number_format((float) ($item->payment_amount ?? 0), 2),
                $item->sales_agent ?? '',
                $item->created_by_name ?? '',
                $this->translateBank($item->bank ?? ''),
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
     * Delete a payment and refresh client balances.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function delete()
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

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=payments', false));
            return;
        }

        $paymentId = $app->input->post->getInt('payment_id', 0);
        if ($paymentId <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ITEM'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=payments', false));
            return;
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Payments', 'Site', ['ignore_request' => true]);

        $details = $model->getPaymentDetailsForDelete($paymentId);
        if (!$details) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ITEM'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=payments', false));
            return;
        }

        if (!$model->deletePayment($paymentId)) {
            $app->enqueueMessage($model->getError() ?: Text::_('COM_ORDENPRODUCCION_ERROR_DELETE_FAILED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=payments', false));
            return;
        }

        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PAYMENT_DELETED_SUCCESS'), 'success');
        $deletedByUser = Factory::getUser();
        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=payments', false);
        $this->outputDeletionProofPdf($details, $redirectUrl, $deletedByUser);
    }

    /**
     * Get payment details for delete preview (AJAX JSON).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getPaymentDetails()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest || !AccessHelper::hasOrderAccess()) {
            $app->setHeader('Content-Type', 'application/json');
            echo json_encode(['error' => true, 'message' => 'Access denied']);
            $app->close();
            return;
        }

        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $app->setHeader('Content-Type', 'application/json');
            echo json_encode(['error' => true, 'message' => 'Invalid token']);
            $app->close();
            return;
        }

        $paymentId = $app->input->getInt('payment_id', 0);
        if ($paymentId <= 0) {
            $app->setHeader('Content-Type', 'application/json');
            echo json_encode(['error' => true, 'message' => 'Invalid payment ID']);
            $app->close();
            return;
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Payments', 'Site', ['ignore_request' => true]);
        $details = $model->getPaymentDetailsForDelete($paymentId);

        if (!$details) {
            $app->setHeader('Content-Type', 'application/json');
            echo json_encode(['error' => true, 'message' => 'Payment not found']);
            $app->close();
            return;
        }

        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $proofBank = $details->proof->bank ?? '';
        $data = [
            'proof' => [
                'id' => (int) $details->proof->id,
                'created' => $details->proof->created ?? '',
                'payment_type' => $details->proof->payment_type ?? '',
                'payment_type_label' => $this->translatePaymentType($details->proof->payment_type ?? ''),
                'bank' => $proofBank,
                'bank_label' => $this->translateBank($proofBank),
                'document_number' => $details->proof->document_number ?? '',
                'payment_amount' => (float) ($details->proof->payment_amount ?? 0),
                'client_name' => $details->proof->client_name ?? '',
                'sales_agent' => $details->proof->sales_agent ?? '',
                'orden_de_trabajo' => $details->proof->orden_de_trabajo ?? '',
                'order_number' => $details->proof->order_number ?? '',
                'created_by_name' => $details->proof->created_by_name ?? '',
            ],
            'lines' => [],
            'orders' => [],
        ];

        foreach ($details->lines as $line) {
            $amount = isset($line->amount) ? (float) $line->amount : (float) ($line->payment_amount ?? 0);
            $lineBank = $line->bank ?? '';
            $data['lines'][] = [
                'payment_type' => $line->payment_type ?? '',
                'payment_type_label' => $this->translatePaymentType($line->payment_type ?? $details->proof->payment_type ?? ''),
                'bank' => $lineBank,
                'bank_label' => $this->translateBank($lineBank),
                'document_number' => $line->document_number ?? '',
                'amount' => $amount,
            ];
        }

        foreach ($details->orders as $ord) {
            $data['orders'][] = [
                'order_id' => (int) $ord->order_id,
                'order_number' => $ord->order_number ?? '#' . $ord->order_id,
                'client_name' => $ord->client_name ?? '',
                'amount_applied' => (float) ($ord->amount_applied ?? 0),
            ];
        }

        echo json_encode($data);
        $app->close();
    }

    /**
     * Output deletion proof PDF and set X-Redirect header for client-side redirect.
     *
     * @param   object       $details       Payment details from getPaymentDetailsForDelete
     * @param   string       $redirectUrl   URL to redirect after PDF download
     * @param   object|null  $deletedByUser Joomla user who performed the deletion
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function outputDeletionProofPdf($details, $redirectUrl, $deletedByUser = null)
    {
        $pdfPath = $this->generateDeletionProofPdf($details, $deletedByUser);
        if (!$pdfPath || !is_file($pdfPath)) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_ORDENPRODUCCION_PDF_GENERATION_FAILED'),
                'error'
            );
            Factory::getApplication()->redirect($redirectUrl);
            return;
        }

        $filename = 'comprobante-eliminacion-pago-' . (int) $details->proof->id . '-' . date('Y-m-d-His') . '.pdf';
        @ob_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Redirect: ' . $redirectUrl);
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        @unlink($pdfPath);
        Factory::getApplication()->close();
    }

    /**
     * Generate deletion proof PDF. Returns temp file path.
     *
     * @param   object       $details       Payment details from getPaymentDetailsForDelete
     * @param   object|null  $deletedByUser Joomla user who performed the deletion
     *
     * @return  string|null  Temp file path or null on failure
     *
     * @since   1.0.0
     */
    protected function generateDeletionProofPdf($details, $deletedByUser = null)
    {
        $fpdfPath = JPATH_ROOT . '/fpdf/fpdf.php';
        if (!is_file($fpdfPath)) {
            return null;
        }
        require_once $fpdfPath;

        $fixSpanishChars = function ($text) {
            if (empty($text)) {
                return $text;
            }
            $map = [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
                'Ü' => 'U', 'ü' => 'u', 'Ç' => 'C', 'ç' => 'c'
            ];
            return strtr($text, $map);
        };

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'COMPROBANTE DE ELIMINACION DE PAGO', 0, 1, 'C');
        $pdf->Ln(4);

        $created = !empty($details->proof->created)
            ? Factory::getDate($details->proof->created)->format('d/m/Y H:i') : '-';
        $deletedAt = Factory::getDate()->format('d/m/Y H:i');
        $clientName = $fixSpanishChars($details->proof->client_name ?? 'N/A');
        $typeLabel = $fixSpanishChars($this->translatePaymentType($details->proof->payment_type ?? ''));
        $createdByName = $fixSpanishChars($details->proof->created_by_name ?? 'N/A');
        $deletedByName = $deletedByUser ? $fixSpanishChars($deletedByUser->name ?? $deletedByUser->username ?? 'N/A') : 'N/A';

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(45, 6, 'Fecha de eliminacion:', 0, 0, 'L');
        $pdf->Cell(0, 6, $deletedAt, 0, 1, 'L');
        $pdf->Cell(45, 6, 'Fecha del pago original:', 0, 0, 'L');
        $pdf->Cell(0, 6, $created, 0, 1, 'L');
        $pdf->Cell(45, 6, 'Cliente:', 0, 0, 'L');
        $pdf->Cell(0, 6, $clientName, 0, 1, 'L');
        $pdf->Cell(45, 6, 'Tipo de pago:', 0, 0, 'L');
        $pdf->Cell(0, 6, $typeLabel, 0, 1, 'L');
        $pdf->Cell(45, 6, 'Banco:', 0, 0, 'L');
        $pdf->Cell(0, 6, $fixSpanishChars($this->translateBank($details->proof->bank ?? '')), 0, 1, 'L');
        $pdf->Cell(45, 6, 'No. Documento:', 0, 0, 'L');
        $pdf->Cell(0, 6, $fixSpanishChars($details->proof->document_number ?? '-'), 0, 1, 'L');
        $pdf->Cell(45, 6, 'Monto total:', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Q ' . number_format((float) ($details->proof->payment_amount ?? 0), 2), 0, 1, 'L');
        $pdf->Cell(45, 6, 'Registrado por:', 0, 0, 'L');
        $pdf->Cell(0, 6, $createdByName, 0, 1, 'L');
        $pdf->Cell(45, 6, 'Eliminado por:', 0, 0, 'L');
        $pdf->Cell(0, 6, $deletedByName, 0, 1, 'L');
        $pdf->Ln(6);

        if (!empty($details->lines)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'LINEAS DE PAGO', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(45, 6, 'Tipo', 1, 0, 'L');
            $pdf->Cell(35, 6, 'Banco', 1, 0, 'L');
            $pdf->Cell(45, 6, 'No. Doc.', 1, 0, 'L');
            $pdf->Cell(0, 6, 'Monto', 1, 1, 'R');
            foreach ($details->lines as $line) {
                $amount = isset($line->amount) ? (float) $line->amount : (float) ($line->payment_amount ?? 0);
                $pdf->Cell(45, 6, $fixSpanishChars($this->translatePaymentType($line->payment_type ?? '')), 1, 0, 'L');
                $pdf->Cell(35, 6, $fixSpanishChars($this->translateBank($line->bank ?? '')), 1, 0, 'L');
                $pdf->Cell(45, 6, $fixSpanishChars($line->document_number ?? '-'), 1, 0, 'L');
                $pdf->Cell(0, 6, number_format($amount, 2), 1, 1, 'R');
            }
            $pdf->Ln(4);
        }

        if (!empty($details->orders)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'ORDENES ASOCIADAS', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(25, 6, 'Orden', 1, 0, 'L');
            $pdf->Cell(80, 6, 'Cliente', 1, 0, 'L');
            $pdf->Cell(0, 6, 'Monto aplicado', 1, 1, 'R');
            foreach ($details->orders as $ord) {
                $pdf->Cell(25, 6, $ord->order_number ?? '#' . $ord->order_id, 1, 0, 'L');
                $pdf->Cell(80, 6, $fixSpanishChars($ord->client_name ?? '-'), 1, 0, 'L');
                $pdf->Cell(0, 6, 'Q ' . number_format((float) ($ord->amount_applied ?? 0), 2), 1, 1, 'R');
            }
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'op_del_proof_') . '.pdf';
        $pdf->Output('F', $tmpFile);
        return $tmpFile;
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
        $langMap = [
            'efectivo' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_CASH',
            'cheque' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_CHECK',
            'transferencia' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_TRANSFER',
            'deposito' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_DEPOSIT',
            'nota_credito_fiscal' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_TAX_CREDIT_NOTE',
        ];
        $fallbackMap = [
            'efectivo' => 'Efectivo',
            'cheque' => 'Cheque',
            'transferencia' => 'Transferencia Bancaria',
            'deposito' => 'Depósito Bancario',
            'nota_credito_fiscal' => 'Nota Crédito Fiscal',
        ];
        $key = strtolower($type ?? '');
        $langKey = $langMap[$key] ?? null;
        if ($langKey) {
            $translated = Text::_($langKey);
            return ($translated !== $langKey) ? $translated : ($fallbackMap[$key] ?? htmlspecialchars($type ?? ''));
        }
        return htmlspecialchars($type ?? '');
    }

    /**
     * Translate bank code to display label (from BankModel or language constants)
     *
     * @param   string  $code  Bank code (e.g. banco_industrial)
     *
     * @return  string
     */
    protected function translateBank($code)
    {
        if (empty(trim($code ?? ''))) {
            return '-';
        }
        $code = trim($code);
        try {
            $model = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()->createModel('Bank', 'Site', ['ignore_request' => true]);
            if ($model && method_exists($model, 'getBankOptions')) {
                $options = $model->getBankOptions();
                if (isset($options[$code])) {
                    return $options[$code];
                }
            }
        } catch (\Throwable $e) {
            // Fall through to language constants
        }
        // Language keys use suffix after banco_ (e.g. banco_industrial -> COM_ORDENPRODUCCION_BANK_INDUSTRIAL)
        $suffix = preg_replace('/^banco_/', '', $code);
        $key = 'COM_ORDENPRODUCCION_BANK_' . strtoupper(str_replace(['-', ' '], '_', $suffix));
        $label = Text::_($key);
        return ($label !== $key) ? $label : $code;
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
        $headerStyle = $sheet->getStyle('A1:I1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB('FF667eea');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'I') as $col) {
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
