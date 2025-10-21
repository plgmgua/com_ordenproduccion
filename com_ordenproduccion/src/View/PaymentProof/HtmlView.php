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

        // Get component params
        $this->params = $app->getParams('com_ordenproduccion');
        $this->user = $user;
        
        // Initialize empty item for new payment proof
        $this->item = new \stdClass();
        $this->item->order_id = $this->orderId;

        $this->_prepareDocument();
        parent::display($tpl);
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
        return $model->getBankOptions();
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
     * Get available orders options for multi-select
     *
     * @return  string  HTML options for select
     *
     * @since   3.1.4
     */
    public function getAvailableOrdersOptions()
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $user = Factory::getUser();
        
        // Get orders accessible by current user (excluding the current order)
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('order_number'),
                $db->quoteName('client_name'),
                $db->quoteName('invoice_value')
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('id') . ' != ' . (int) $this->orderId)
            ->order($db->quoteName('order_number') . ' DESC')
            ->setLimit(100); // Limit to recent 100 orders
        
        // Apply access control - only show orders user has access to
        $userGroups = $user->getAuthorisedGroups();
        $isVentas = in_array(2, $userGroups); // Ventas group
        $isProduccion = in_array(3, $userGroups); // Produccion group
        
        if ($isVentas && !$isProduccion) {
            // Sales users can only see their own orders
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($user->get('name')));
        }
        // Production and admin users can see all orders (no additional filter)
        
        $db->setQuery($query);
        $orders = $db->loadObjectList();
        
        $html = '';
        foreach ($orders as $order) {
            $invoiceValue = !empty($order->invoice_value) ? 'Q.' . number_format($order->invoice_value, 2) : '';
            $label = sprintf(
                '%s - %s %s',
                htmlspecialchars($order->order_number),
                htmlspecialchars($order->client_name),
                $invoiceValue ? '(' . $invoiceValue . ')' : ''
            );
            $html .= '<option value="' . (int) $order->id . '">' . $label . '</option>';
        }
        
        return $html;
    }
}
