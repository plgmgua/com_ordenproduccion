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
        $orderModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
            ->getMVCFactory()->createModel('Orden', 'Site');
        $orderModel->setState('orden.id', $this->orderId);
        $this->order = $orderModel->getItem();

        if (!$this->order) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ORDER_NOT_FOUND'), 'error');
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
}
