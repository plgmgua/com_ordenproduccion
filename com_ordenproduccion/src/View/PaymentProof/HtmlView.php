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
        $this->existingPayments = $this->getExistingPayments();

        // Get component params
        $this->params = $app->getParams('com_ordenproduccion');
        $this->user = $user;
        
        // Initialize empty item for new payment proof
        $this->item = new \stdClass();
        $this->item->order_id = $this->orderId;

        $this->_prepareDocument();
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

    public function getBackToOrderRoute()
    {
        return Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . $this->orderId);
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
     * Get orders with remaining balance from same client (for adding to payment)
     * Includes: orders where total paid < invoice_value, same client
     *
     * @return  array  Array of order objects with id, order_number, invoice_value, total_paid, remaining_balance
     *
     * @since   3.54.0
     */
    public function getOrdersWithRemainingBalanceFromClient()
    {
        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $clientName = $this->order->client_name ?? $this->order->nombre_del_cliente ?? '';
            if (empty($clientName)) {
                return [];
            }
            if (!$this->hasPaymentOrdersTable($db)) {
                return [];
            }
            $clientColumn = 'COALESCE(o.' . $db->quoteName('client_name') . ', o.' . $db->quoteName('nombre_del_cliente') . ')';
            
            // Get all orders from same client (excluding current for "additional" list)
            $query = $db->getQuery(true)
                ->select([
                    'o.id',
                    'COALESCE(o.order_number, o.orden_de_trabajo) AS order_number',
                    'COALESCE(o.invoice_value, 0) AS invoice_value',
                    'COALESCE(SUM(CASE WHEN pp.state = 1 THEN po.amount_applied ELSE 0 END), 0) AS total_paid'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_payment_orders', 'po') . ' ON po.order_id = o.id'
                )
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') . ' ON pp.id = po.payment_proof_id'
                )
                ->where('o.state = 1')
                ->where('(' . $clientColumn . ' = ' . $db->quote($clientName) . ')')
                ->where('o.id != ' . (int) $this->orderId)
                ->group('o.id')
                ->order('COALESCE(o.order_number, o.orden_de_trabajo) DESC');
            
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
