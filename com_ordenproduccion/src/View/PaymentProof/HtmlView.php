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
    protected $existingPayment;
    protected $isReadOnly;

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

        // Check if payment already exists for this order
        $this->existingPayment = $this->checkExistingPayment();
        $this->isReadOnly = !empty($this->existingPayment);

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
     * Check if payment proof already exists for this order
     *
     * @return  object|null  Existing payment proof or null
     */
    protected function checkExistingPayment()
    {
        if (empty($this->order->payment_proof_id)) {
            return null;
        }

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                ->where($db->quoteName('id') . ' = ' . (int) $this->order->payment_proof_id);
            
            $db->setQuery($query);
            return $db->loadObject();
        } catch (\Exception $e) {
            return null;
        }
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
     * Get unpaid orders from same client for dropdown
     *
     * @return  array  Array of order objects
     *
     * @since   3.1.5
     */
    public function getUnpaidOrdersFromClient()
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        
        // Get client name from current order
        $clientName = $this->order->client_name ?? '';
        
        if (empty($clientName)) {
            return [];
        }
        
        // Get unpaid orders from same client
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('order_number'),
                $db->quoteName('orden_de_trabajo'),
                $db->quoteName('client_name'),
                $db->quoteName('invoice_value')
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName))
            ->where($db->quoteName('payment_proof_id') . ' IS NULL') // Only unpaid orders
            ->where($db->quoteName('id') . ' != ' . (int) $this->orderId) // Exclude current order
            ->order($db->quoteName('order_number') . ' DESC');
        
        $db->setQuery($query);
        return $db->loadObjectList();
    }
    
    /**
     * Get unpaid orders as JSON for JavaScript
     *
     * @return  string  JSON encoded array
     *
     * @since   3.1.5
     */
    public function getUnpaidOrdersJson()
    {
        $orders = $this->getUnpaidOrdersFromClient();
        $data = [];
        
        foreach ($orders as $order) {
            $data[] = [
                'id' => (int) $order->id,
                'order_number' => $order->order_number ?? $order->orden_de_trabajo ?? '',
                'invoice_value' => (float) ($order->invoice_value ?? 0)
            ];
        }
        
        return json_encode($data);
    }
}
