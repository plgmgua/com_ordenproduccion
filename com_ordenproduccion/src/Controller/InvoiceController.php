<?php
/**
 * Invoice Controller for Com Orden Produccion
 * 
 * Handles invoice creation and management
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @subpackage  Invoice
 * @since       3.42.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;

class InvoiceController extends BaseController
{
    /**
     * Create a new invoice from quotation form data
     */
    public function create()
    {
        // Check CSRF token
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
            return false;
        }

        // Facturas: only Administrator or Admon user groups may create invoices
        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return false;
        }

        try {
            $input = $this->app->input;
            
            // Get form data
            $orderId = $input->getInt('order_id', 0);
            $orderNumber = $input->getString('order_number', '');
            $cliente = $input->getString('cliente', '');
            $nit = $input->getString('nit', '');
            $direccion = $input->getString('direccion', '');
            $items = $input->get('items', [], 'array');
            
            // Validate required fields
            if (empty($orderId) || empty($orderNumber) || empty($cliente) || empty($nit)) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_REQUIRED_FIELDS'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
                return false;
            }

            // Get work order data directly from database
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);
            
            $db->setQuery($query);
            $workOrder = $db->loadObject();
            
            if (!$workOrder) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ORDER_NOT_FOUND'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
                return false;
            }

            // Generate autonumeric invoice number
            $invoiceNumber = $this->generateInvoiceNumber();
            
            // Calculate total amount from items
            $totalAmount = 0;
            $lineItems = [];
            
            foreach ($items as $item) {
                if (!empty($item['cantidad']) && !empty($item['precio_unitario'])) {
                    $cantidad = floatval($item['cantidad']);
                    $precioUnitario = floatval($item['precio_unitario']);
                    $subtotal = $cantidad * $precioUnitario;
                    
                    $lineItems[] = [
                        'cantidad' => $cantidad,
                        'descripcion' => $item['descripcion'] ?? '',
                        'precio_unitario' => $precioUnitario,
                        'subtotal' => $subtotal
                    ];
                    
                    $totalAmount += $subtotal;
                }
            }

            // Prepare invoice data (matching actual database schema)
            $invoiceData = [
                'invoice_number' => $invoiceNumber,
                'orden_id' => $orderId,
                'orden_de_trabajo' => $orderNumber,
                'client_name' => $cliente,
                'client_nit' => $nit,
                // Note: Your schema doesn't have client_address column in invoices table
                'sales_agent' => $workOrder->sales_agent ?? '',
                'request_date' => $workOrder->request_date ? Factory::getDate($workOrder->request_date)->format('Y-m-d') : null,
                'delivery_date' => $workOrder->delivery_date ?? null,
                'invoice_date' => Factory::getDate()->format('Y-m-d'),  // DATE format, not DATETIME
                'invoice_amount' => $totalAmount,
                'currency' => 'Q',
                'work_description' => $workOrder->work_description ?? '',
                'material' => $workOrder->material ?? '',
                'dimensions' => $workOrder->dimensions ?? '',  // Your schema uses 'dimensions', not 'medidas'
                'print_color' => $workOrder->print_color ?? '',
                'line_items' => json_encode($lineItems),  // TEXT column, must be JSON string
                'quotation_file' => $workOrder->quotation_files ?? '',
                'extraction_status' => 'manual',
                'status' => 'created',
                'notes' => 'Created from quotation form',
                'state' => 1
            ];

            // Save invoice
            $invoiceModel = $this->getModel('Invoice');
            $result = $invoiceModel->save($invoiceData);

            if ($result) {
                // Update work order with invoice number
                $this->updateWorkOrderInvoiceNumber($orderId, $invoiceNumber);
                
                $message = Text::sprintf('COM_ORDENPRODUCCION_INVOICE_CREATED_SUCCESS', $invoiceNumber);
                $this->app->enqueueMessage($message, 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_CREATING_INVOICE'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage('Error: ' . $e->getMessage(), 'error');
        }

        // Redirect back to work orders
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
        return true;
    }

    /**
     * Associate a work order to the current FEL invoice (same NIT). Redirects back to invoice detail.
     *
     * @return  void
     * @since   3.100.4
     */
    public function associateOrden()
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));
            return;
        }

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        $invoiceId = $this->input->post->getInt('invoice_id', 0);
        $ordenId = $this->input->post->getInt('orden_id', 0);
        $back = Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . (int) $invoiceId, false);

        if ($invoiceId <= 0 || $ordenId <= 0) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_INVALID'), 'warning');
            $this->setRedirect($back);
            return;
        }

        try {
            $model = $this->getModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            if (!$model) {
                $model = $this->app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site');
            }
            if (!$model || !$model->isTableAvailable()) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'), 'error');
                $this->setRedirect($back);
                return;
            }
            $ok = $model->addManualInvoiceOrdenAssociation($invoiceId, $ordenId);
            $this->app->enqueueMessage(
                $ok ? Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_SUCCESS') : Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_NOOP'),
                $ok ? 'success' : 'notice'
            );
        } catch (\Throwable $e) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $this->setRedirect($back);
    }

    /**
     * Queue FEL mock invoice from cotización (JSON). Step 1: create pending row.
     *
     * @return  void
     *
     * @since   3.101.50
     */
    public function issueFromQuotation()
    {
        $app = $this->app;
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        if (!AccessHelper::isInVentasGroup() && !AccessHelper::isInAdministracionOrAdmonGroup() && !AccessHelper::isSuperUser()) {
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $app->close();
        }

        $quotationId = $this->input->getInt('quotation_id', 0);
        if ($quotationId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_QUOTATION_INVALID')]);
            $app->close();
        }

        $service = new FelInvoiceIssuanceService();
        if (!$service->isEngineAvailable() || !$service->hasQuotationIdColumn()) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_ENGINE_UNAVAILABLE')]);
            $app->close();
        }

        $existing = $service->getInvoiceByQuotationId($quotationId);
        if ($existing) {
            echo json_encode([
                'success'    => true,
                'invoice_id' => (int) $existing->id,
                'existing'   => true,
                'status'     => (string) ($existing->fel_issue_status ?? ''),
            ]);
            $app->close();
        }

        $invoiceId = $service->createPendingInvoiceFromQuotation($quotationId, (int) $user->id);
        if ($invoiceId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_CREATE_FAILED')]);
            $app->close();
        }

        echo json_encode([
            'success'    => true,
            'invoice_id' => $invoiceId,
            'existing'   => false,
            'status'     => 'pending',
        ]);
        $app->close();
    }

    /**
     * Process pending FEL mock invoice (JSON). Step 2: mock certify + files.
     *
     * @return  void
     *
     * @since   3.101.50
     */
    public function processFelIssuance()
    {
        $app = $this->app;
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        if (!AccessHelper::isInVentasGroup() && !AccessHelper::isInAdministracionOrAdmonGroup() && !AccessHelper::isSuperUser()) {
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $app->close();
        }

        $invoiceId = $this->input->getInt('invoice_id', 0);
        if ($invoiceId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID')]);
            $app->close();
        }

        $forceScheduled = $this->input->getInt('force', 0) === 1;

        $service = new FelInvoiceIssuanceService();
        $result = $service->processInvoice($invoiceId, $forceScheduled);
        if (!empty($result['success'])) {
            $result['message'] = Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_SUCCESS');
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * JSON status for polling (fel_issue_*).
     *
     * @return  void
     *
     * @since   3.101.50
     */
    public function felIssuanceStatus()
    {
        $app = $this->app;
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        if (!AccessHelper::isInVentasGroup() && !AccessHelper::isInAdministracionOrAdmonGroup() && !AccessHelper::isSuperUser()) {
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $app->close();
        }

        $invoiceId = $this->input->getInt('invoice_id', 0);
        if ($invoiceId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID')]);
            $app->close();
        }

        $model = $this->getModel('Invoice');
        $inv = $model ? $model->getItem($invoiceId) : null;
        if (!$inv) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_NOT_FOUND')]);
            $app->close();
        }

        echo json_encode([
            'success'              => true,
            'fel_issue_status'     => (string) ($inv->fel_issue_status ?? ''),
            'fel_issue_error'      => (string) ($inv->fel_issue_error ?? ''),
            'invoice_number'       => (string) ($inv->invoice_number ?? ''),
            'fel_local_pdf_path'   => (string) ($inv->fel_local_pdf_path ?? ''),
            'fel_local_xml_path'   => (string) ($inv->fel_local_xml_path ?? ''),
        ]);
        $app->close();
    }

    /**
     * Stream mock FEL PDF/XML from disk with correct Content-Type (avoids saving HTML/login pages as .pdf).
     *
     * GET: invoice_id, type=pdf|xml, CSRF token, optional download=1 (same as cotizacion.downloadPdf: inline in browser by default).
     *
     * @return  void
     *
     * @since   3.101.57
     */
    public function downloadFelArtifact()
    {
        $app = $this->app;

        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));

            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        if (!AccessHelper::isInVentasGroup() && !AccessHelper::isInAdministracionOrAdmonGroup() && !AccessHelper::isSuperUser()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));

            return;
        }

        $invoiceId = $this->input->getInt('invoice_id', 0);
        $kind      = $this->input->getCmd('type', 'pdf');
        if ($invoiceId < 1 || !\in_array($kind, ['pdf', 'xml'], true)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));

            return;
        }

        $model = $this->getModel('Invoice');
        $inv   = $model ? $model->getItem($invoiceId) : null;
        if (!$inv) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));

            return;
        }

        $rel = $kind === 'xml' ? \trim((string) ($inv->fel_local_xml_path ?? '')) : \trim((string) ($inv->fel_local_pdf_path ?? ''));
        if ($rel === '') {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $invoiceId, false));

            return;
        }

        $abs = JPATH_ROOT . '/' . \ltrim(\str_replace('\\', '/', $rel), '/');
        if (!\is_file($abs)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $invoiceId, false));

            return;
        }

        $size = @\filesize($abs);
        if ($size === false || $size < 24) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $invoiceId, false));

            return;
        }

        if ($kind === 'pdf') {
            $head = @\file_get_contents($abs, false, null, 0, 5);
            if ($head !== '%PDF-') {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID'), 'error');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $invoiceId, false));

                return;
            }
        }

        // Binary body must not be prefixed by buffer output or zlib (breaks Chrome PDF viewer).
        if (\function_exists('ini_set')) {
            @\ini_set('zlib.output_compression', '0');
        }
        while (\ob_get_level() > 0) {
            \ob_end_clean();
        }

        $invNum = \preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) ($inv->invoice_number ?? ('FAC-' . $invoiceId)));
        $fname  = $kind === 'xml' ? $invNum . '-fel.xml' : $invNum . '-fel.pdf';
        $mime   = $kind === 'xml' ? 'application/xml' : 'application/pdf';
        // Match cotizacion.downloadPdf: open in browser unless download=1 (force attachment).
        $forceDownload = (int) $this->input->getInt('download', 0) === 1;
        $disposition     = $forceDownload ? 'attachment' : 'inline';

        if (\method_exists($app, 'clearHeaders')) {
            $app->clearHeaders();
        }
        $app->setHeader('Content-Type', $mime, true);
        $app->setHeader('Content-Disposition', $disposition . '; filename="' . $fname . '"', true);
        $app->setHeader('Content-Length', (string) $size, true);
        $app->setHeader('Cache-Control', 'private, max-age=0', true);
        $app->sendHeaders();
        \readfile($abs);
        $app->close();
    }

    /**
     * Generate autonumeric invoice number based on table ID
     */
    private function generateInvoiceNumber()
    {
        $db = Factory::getDbo();
        
        // Get the next ID from the invoice table
        $query = $db->getQuery(true)
            ->select('AUTO_INCREMENT')
            ->from('INFORMATION_SCHEMA.TABLES')
            ->where($db->quoteName('TABLE_SCHEMA') . ' = ' . $db->quote($db->getDatabase()))
            ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($db->getPrefix() . 'ordenproduccion_invoices'));

        $db->setQuery($query);
        $nextId = $db->loadResult();

        if (!$nextId) {
            // Fallback: get max ID + 1
            $query = $db->getQuery(true)
                ->select('MAX(id) + 1')
                ->from($db->quoteName('#__ordenproduccion_invoices'));

            $db->setQuery($query);
            $nextId = $db->loadResult() ?: 1;
        }

        return 'FAC-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update work order with invoice number
     */
    private function updateWorkOrderInvoiceNumber($orderId, $invoiceNumber)
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->set($db->quoteName('invoice_number') . ' = ' . $db->quote($invoiceNumber))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);

            $db->setQuery($query);
            $db->execute();
            
            return true;
        } catch (\Exception $e) {
            // Log error but don't fail the invoice creation
            Factory::getApplication()->enqueueMessage('Warning: Could not update work order with invoice number', 'warning');
            return false;
        }
    }
}
