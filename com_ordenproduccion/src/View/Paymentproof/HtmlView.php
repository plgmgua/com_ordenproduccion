<?php
/**
 * Payment Proof View for Com Orden Produccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Paymentproof;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    protected $state;
    protected $item;
    protected $params;
    protected $user;
    protected $orderId;
    protected $order;
    protected $existingPayments = [];

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login'));
            return;
        }

        $this->orderId = $app->input->getInt('order_id', 0);
        if (empty($this->orderId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ORDER_ID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Load order data
        try {
            $orderModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()->createModel('Orden', 'Site');
            $this->order = $orderModel->getItem($this->orderId);

            if (!$this->order) {
                throw new \Exception(Text::sprintf('COM_ORDENPRODUCCION_ERROR_ORDER_NOT_FOUND_ID', $this->orderId));
            }
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Get existing payments for display (no longer blocks adding more - many-to-many)
        // Merge duplicate documents (same document_number for this order) into one row with summed amounts
        $this->existingPayments = $this->mergePaymentsByDocumentNumber($this->getExistingPayments());

        // Get component params
        $this->params = $app->getParams('com_ordenproduccion');
        $this->user = $user;
        
        // Initialize empty item for new payment proof
        $this->item = new \stdClass();
        $this->item->order_id = $this->orderId;

        $this->_prepareDocument();

        // Labels with fallbacks when language keys are not loaded
        $t = function ($key, $fallback) {
            $v = Text::_($key);
            return ($v !== $key) ? $v : $fallback;
        };
        $this->labelExistingPayments = $t('COM_ORDENPRODUCCION_EXISTING_PAYMENTS', 'Pagos Existentes para Esta Orden');
        $this->labelPaymentProofNoEdit = $t('COM_ORDENPRODUCCION_PAYMENT_PROOF_NO_EDIT', 'Los comprobantes guardados no se pueden modificar, solo eliminar (desde Control de Pagos).');
        $this->labelDocumentNumber = $t('COM_ORDENPRODUCCION_DOCUMENT_NUMBER', 'Número de Documento');
        $this->labelPaymentType = $t('COM_ORDENPRODUCCION_PAYMENT_TYPE', 'Tipo de Pago');
        $this->labelPaymentAmount = $t('COM_ORDENPRODUCCION_PAYMENT_AMOUNT', 'Monto del Pago');
        $this->labelValueToApply = $t('COM_ORDENPRODUCCION_VALUE_TO_APPLY', 'Valor a Aplicar');
        $this->labelAttachment = $t('COM_ORDENPRODUCCION_PAYMENT_PROOF_ATTACHMENT', 'Comprobante adjunto');
        $this->labelOrderInformation = $t('COM_ORDENPRODUCCION_ORDER_INFORMATION', 'Información de la Orden');
        $this->labelPaymentProofTitle = $t('COM_ORDENPRODUCCION_PAYMENT_PROOF_TITLE', 'Registro de Comprobante de Pago');
        $this->labelBackToOrder = $t('COM_ORDENPRODUCCION_BACK_TO_PAYMENTS', 'Volver a Control de Pagos');
        $fmt = Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_FOR_ORDER');
        $this->labelPaymentProofForOrder = (strpos($fmt, 'COM_ORDENPRODUCCION') === 0) ? 'Comprobante de Pago para Orden %s' : $fmt;
        $this->labelOrderNumber = $t('COM_ORDENPRODUCCION_ORDER_NUMBER', 'Orden #');
        $this->labelClientName = $t('COM_ORDENPRODUCCION_CLIENT_NAME', 'Nombre del Cliente');
        $this->labelInvoiceValue = $t('COM_ORDENPRODUCCION_INVOICE_VALUE', 'Valor de Factura');
        $this->labelRequestDate = $t('COM_ORDENPRODUCCION_REQUEST_DATE', 'Fecha de Solicitud');
        $this->labelPaymentProofRegistration = $t('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTRATION', 'Registro de Comprobante de Pago');
        $this->labelTotal = $t('COM_ORDENPRODUCCION_TOTAL', 'Total');
        $this->labelValueToApply = $t('COM_ORDENPRODUCCION_VALUE_TO_APPLY', 'Valor a Aplicar');
        $this->labelActions = $t('COM_ORDENPRODUCCION_ACTIONS', 'Acciones');
        $this->labelAddOrder = $t('COM_ORDENPRODUCCION_ADD_ORDER', 'Agregar orden');
        $this->labelRegisterPaymentProof = $t('COM_ORDENPRODUCCION_REGISTER_PAYMENT_PROOF', 'Registrar Comprobante de Pago');
        $this->labelCancel = $t('JCANCEL', 'Cancelar');
        $this->labelDelete = $t('JDELETE', 'Eliminar');
        $this->labelErrorInvalidFileType = $t('COM_ORDENPRODUCCION_ERROR_INVALID_FILE_TYPE', 'Tipo de archivo inválido. Solo se permiten JPG, PNG y PDF.');
        $this->labelErrorFileTooLarge = $t('COM_ORDENPRODUCCION_ERROR_FILE_TOO_LARGE', 'Archivo demasiado grande. Máximo 5MB.');

        parent::display($tpl);
    }

    /**
     * Get existing payment proofs for this order (many-to-many - can have multiple)
     *
     * @return  array  Array of payment proof objects with amount_applied
     */
    protected function getExistingPayments()
    {
        $model = $this->getModel();
        if (method_exists($model, 'getPaymentProofsByOrderId')) {
            return $model->getPaymentProofsByOrderId($this->orderId);
        }
        return [];
    }

    /**
     * Merge payment proofs that share the same document_number (duplicate rows in DB) into one display row per document.
     *
     * @param   array  $proofs  Raw list from getPaymentProofsByOrderId
     *
     * @return  array  List with one entry per document_number, amounts summed; each has _merged = true if any merge happened
     */
    protected function mergePaymentsByDocumentNumber(array $proofs)
    {
        if (empty($proofs)) {
            return [];
        }
        $byDoc = [];
        foreach ($proofs as $p) {
            $doc = trim((string) ($p->document_number ?? ''));
            $key = $doc !== '' ? $doc : 'proof_' . ($p->id ?? uniqid());
            if (!isset($byDoc[$key])) {
                $byDoc[$key] = clone $p;
                $byDoc[$key]->_merged = false;
            } else {
                $byDoc[$key]->payment_amount = (float) ($byDoc[$key]->payment_amount ?? 0) + (float) ($p->payment_amount ?? 0);
                $byDoc[$key]->amount_applied = (float) ($byDoc[$key]->amount_applied ?? 0) + (float) ($p->amount_applied ?? 0);
                $byDoc[$key]->_merged = true;
            }
        }
        return array_values($byDoc);
    }

    /**
     * Get file info for displaying an attached payment proof (image or PDF).
     *
     * @param   object  $proof  Payment proof object with file_path
     *
     * @return  array|null  ['url' => string, 'type' => 'image'|'pdf'] or null if no file
     */
    public function getPaymentProofFileInfo($proof)
    {
        $path = trim((string) ($proof->file_path ?? ''));
        if ($path === '') {
            return null;
        }
        $fullPath = JPATH_ROOT . '/' . ltrim($path, '/');
        if (!is_file($fullPath)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        $isPdf = ($ext === 'pdf');
        if (!$isImage && !$isPdf) {
            return null;
        }
        $url = Uri::root() . ltrim($path, '/');
        return [
            'url'  => $url,
            'type' => $isImage ? 'image' : 'pdf',
        ];
    }

    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_TITLE'));
        }

        $title = $this->params->get('page_title', '');
        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->document->setTitle($title);

        HTMLHelper::_('bootstrap.framework');
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.paymentproof', 'media/com_ordenproduccion/css/paymentproof.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.paymentproof', 'media/com_ordenproduccion/js/paymentproof.js', [], ['version' => 'auto']);
    }

    /**
     * Translate payment type code to display label
     *
     * @param   string  $type  Payment type code
     *
     * @return  string
     */
    public function translatePaymentType($type)
    {
        $options = $this->getPaymentTypeOptions();
        if (isset($options[$type ?? ''])) {
            return $options[$type];
        }
        $map = [
            'efectivo' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_CASH',
            'cheque' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_CHECK',
            'transferencia' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_TRANSFER',
            'deposito' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_DEPOSIT',
            'nota_credito_fiscal' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_TAX_CREDIT_NOTE',
        ];
        $key = strtolower($type ?? '');
        $langKey = $map[$key] ?? null;
        if ($langKey) {
            $v = Text::_($langKey);
            return ($v !== $langKey) ? $v : ($key === 'transferencia' ? 'Transferencia Bancaria' : htmlspecialchars($type ?? ''));
        }
        return htmlspecialchars($type ?? '');
    }

    /**
     * Route back to the payment proofs list (Control de Pagos).
     *
     * @return  string
     */
    public function getBackToOrderRoute()
    {
        return Route::_('index.php?option=com_ordenproduccion&view=payments');
    }

    public function getPaymentTypeOptions()
    {
        $model = $this->getModel();
        return $model->getPaymentTypeOptions();
    }

    public function getBankOptions()
    {
        $model = $this->getModel();
        $options = $model->getBankOptions();
        
        // Debug logging to verify what we're getting
        error_log("PaymentProofView::getBankOptions() - Returning " . count($options) . " banks");
        if (!empty($options)) {
            error_log("PaymentProofView::getBankOptions() - Bank codes: " . implode(', ', array_keys($options)));
        }
        
        return $options;
    }

    /**
     * Get default bank code
     *
     * @return  string|null  Default bank code or null if no default set
     *
     * @since   3.5.2
     */
    public function getDefaultBankCode()
    {
        try {
            $app = Factory::getApplication();
            $component = $app->bootComponent('com_ordenproduccion');
            $mvcFactory = $component->getMVCFactory();
            $bankModel = $mvcFactory->createModel('Bank', 'Site', ['ignore_request' => true]);
            
            if ($bankModel && method_exists($bankModel, 'getDefaultBankCode')) {
                $defaultCode = $bankModel->getDefaultBankCode();
                error_log("PaymentProofView::getDefaultBankCode() - Default bank code: " . ($defaultCode ?: 'none'));
                return $defaultCode;
            }
        } catch (\Exception $e) {
            error_log("PaymentProofView::getDefaultBankCode() - Exception: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Format currency value
     *
     * @param   float  $value  The currency value
     *
     * @return  string  Formatted currency
     *
     * @since   3.1.3
     */
    public function formatCurrency($value)
    {
        if (empty($value) || $value == 0) {
            return '-';
        }

        return 'Q.' . number_format($value, 2);
    }

    /**
     * Format date for display
     *
     * @param   string  $date  The date string
     *
     * @return  string  Formatted date
     *
     * @since   3.1.3
     */
    public function formatDate($date)
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }

        // For DATE fields (YYYY-MM-DD), use direct PHP date formatting to avoid timezone conversion issues
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $timestamp = strtotime($date);
            return date('d F Y', $timestamp);
        }

        // For DATETIME fields, use Joomla's date helper with timezone conversion
        return HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC3'));
    }

    /**
     * Get orders with remaining balance from same client (for adding to payment).
     *
     * Filtering (all must apply):
     * - Same client only: TRIM(client_name|nombre_del_cliente) = current order's client
     * - Excludes current order (already in first row)
     * - Pending balance only: invoice_value - total_paid > 0.01
     *
     * Supports both schema: invoice_value/valor_a_facturar, client_name/nombre_del_cliente
     *
     * @return  array  Array of order objects with id, order_number, invoice_value, total_paid, remaining_balance
     *
     * @since   3.54.0
     */
    public function getOrdersWithRemainingBalanceFromClient()
    {
        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $clientName = trim($this->order->client_name ?? $this->order->nombre_del_cliente ?? '');
            if (empty($clientName)) {
                return [];
            }

            // Detect columns (support both schema: invoice_value/valor_a_facturar, client_name/nombre_del_cliente)
            $orderColumns = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $orderColumns = array_change_key_case($orderColumns ?: [], CASE_LOWER);

            $invoiceCol = isset($orderColumns['invoice_value']) ? 'o.invoice_value'
                : (isset($orderColumns['valor_a_facturar']) ? 'o.valor_a_facturar' : '0');
            $clientCol = isset($orderColumns['client_name']) ? 'o.client_name'
                : (isset($orderColumns['nombre_del_cliente']) ? 'o.nombre_del_cliente' : 'o.nombre_del_cliente');
            $orderNumCol = isset($orderColumns['order_number']) ? 'o.order_number'
                : 'o.orden_de_trabajo';

            if ($this->hasPaymentOrdersTable($db)) {
                $totalPaidExpr = '(SELECT COALESCE(SUM(po2.amount_applied), 0) FROM ' .
                    $db->quoteName('#__ordenproduccion_payment_orders', 'po2') .
                    ' INNER JOIN ' . $db->quoteName('#__ordenproduccion_payment_proofs', 'pp2') .
                    ' ON pp2.id = po2.payment_proof_id AND pp2.state = 1 WHERE po2.order_id = o.id)';
            } else {
                $totalPaidExpr = '(SELECT COALESCE(SUM(pp2.payment_amount), 0) FROM ' .
                    $db->quoteName('#__ordenproduccion_payment_proofs', 'pp2') .
                    ' WHERE pp2.order_id = o.id AND pp2.state = 1)';
            }

            $query = $db->getQuery(true)
                ->select([
                    'o.id',
                    $orderNumCol . ' AS order_number',
                    'COALESCE(' . $invoiceCol . ', 0) AS invoice_value',
                    $totalPaidExpr . ' AS total_paid'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->where('o.state = 1')
                ->where('TRIM(' . $clientCol . ') = ' . $db->quote($clientName))
                ->where('o.id != ' . (int) $this->orderId)
                ->order($orderNumCol . ' DESC');

            $db->setQuery($query);
            $orders = $db->loadObjectList();
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($orders)) {
            return [];
        }

        // Filter: only orders with remaining balance (total_paid < invoice_value)
        $result = [];
        foreach ($orders as $order) {
            $invoiceValue = (float) ($order->invoice_value ?? 0);
            $totalPaid = (float) ($order->total_paid ?? 0);
            $remainingBalance = $invoiceValue - $totalPaid;
            if ($remainingBalance > 0.01) {
                $order->remaining_balance = $remainingBalance;
                $result[] = $order;
            }
        }
        return $result;
    }
    
    /**
     * Get orders with remaining balance as JSON for JavaScript
     *
     * @return  string  JSON encoded array
     *
     * @since   3.54.0
     */
    public function getUnpaidOrdersJson()
    {
        $orders = $this->getOrdersWithRemainingBalanceFromClient();
        $data = [];
        
        foreach ($orders as $order) {
            $data[] = [
                'id' => (int) $order->id,
                'order_number' => $order->order_number ?? $order->orden_de_trabajo ?? '',
                'invoice_value' => (float) ($order->invoice_value ?? 0),
                'remaining_balance' => (float) ($order->remaining_balance ?? $order->invoice_value ?? 0)
            ];
        }
        
        return json_encode($data);
    }

    /**
     * Check if payment_orders junction table exists (3.54.0+ schema)
     */
    protected function hasPaymentOrdersTable($db = null)
    {
        try {
            $db = $db ?: Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'ordenproduccion_payment_orders';
            foreach ($tables as $t) {
                if (strcasecmp($t, $tableName) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
