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

        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->params = $app->getParams('com_ordenproduccion');
        $this->user = $user;

        if (count($errors = $this->get('Errors'))) {
            $app->enqueueMessage(implode("\n", $errors), 'error');
            return;
        }

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

<?php
/**
 * Payment Proof View for Com Orden Produccion
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof
 * @subpackage  PaymentProof
 * @since       3.1.3
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
    /**
     * The model state
     *
     * @var    \Joomla\Registry\Registry
     * @since  3.1.3
     */
    protected $state;

    /**
     * The item object
     *
     * @var    object
     * @since  3.1.3
     */
    protected $item;

    /**
     * The component parameters
     *
     * @var    \Joomla\Registry\Registry
     * @since  3.1.3
     */
    protected $params;

    /**
     * The user object
     *
     * @var    \Joomla\CMS\User\User
     * @since  3.1.3
     */
    protected $user;

    /**
     * The order ID
     *
     * @var    integer
     * @since  3.1.3
     */
    protected $orderId;

    /**
     * The order data
     *
     * @var    object
     * @since  3.1.3
     */
    protected $order;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   3.1.3
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Check if user is logged in
        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login'));
            return;
        }

        // Get order ID
        $this->orderId = $app->input->getInt('order_id', 0);
        
        if (empty($this->orderId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ORDER_ID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Get order data
        $orderModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
            ->getMVCFactory()->createModel('Orden', 'Site');
        $orderModel->setState('orden.id', $this->orderId);
        $this->order = $orderModel->getItem();

        if (!$this->order) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ORDER_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->params = $app->getParams('com_ordenproduccion');
        $this->user = $user;

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            $app->enqueueMessage(implode("\n", $errors), 'error');
            return;
        }

        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return  void
     *
     * @since   3.1.3
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $title = null;

        // Because the application sets a default page title,
        // we need to get it from the menu item itself
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

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->document->setMetadata('robots', $this->params->get('robots'));
        }

        // Load assets
        HTMLHelper::_('bootstrap.framework');
        
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.paymentproof', 'media/com_ordenproduccion/css/paymentproof.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.paymentproof', 'media/com_ordenproduccion/js/paymentproof.js', [], ['version' => 'auto']);
    }

    /**
     * Get back to order route
     *
     * @return  string  The route URL
     *
     * @since   3.1.3
     */
    public function getBackToOrderRoute()
    {
        return Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . $this->orderId);
    }

    /**
     * Get payment type options
     *
     * @return  array  Payment type options
     *
     * @since   3.1.3
     */
    public function getPaymentTypeOptions()
    {
        $model = $this->getModel();
        return $model->getPaymentTypeOptions();
    }

    /**
     * Get bank options
     *
     * @return  array  Bank options
     *
     * @since   3.1.3
     */
    public function getBankOptions()
    {
        $model = $this->getModel();
        return $model->getBankOptions();
    }
}
