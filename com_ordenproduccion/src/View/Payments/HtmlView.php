<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Payments;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

/**
 * Payments view class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The model state
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $state;

    /**
     * The list of items
     *
     * @var    array
     * @since  1.0.0
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  1.0.0
     */
    protected $pagination;

    /**
     * The component parameters
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $params;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login'));
            return;
        }

        if (!AccessHelper::hasOrderAccess()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $app->redirect(Route::_('index.php'));
            return;
        }

        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->params = $app->getParams('com_ordenproduccion');

        if (count($errors = $this->get('Errors'))) {
            $app->enqueueMessage(implode("\n", $errors), 'error');
            return;
        }

        $this->_prepareDocument();

        // Modal labels with fallbacks for when language file keys are not loaded
        $t = function ($key, $fallback) {
            $v = Text::_($key);
            return ($v !== $key) ? $v : $fallback;
        };
        $this->modalDeleteTitle = $t('COM_ORDENPRODUCCION_PAYMENT_DELETE_CONFIRM_TITLE', 'Confirmar eliminación de pago');
        $this->modalDeleteDesc = $t('COM_ORDENPRODUCCION_PAYMENT_DELETE_CONFIRM_DESC', 'Revise los datos del pago antes de eliminar. Se generará un PDF como comprobante de eliminación.');
        $this->modalConfirmDelete = $t('COM_ORDENPRODUCCION_CONFIRM_DELETE', 'Confirmar eliminación');
        $this->modalCancel = $t('COM_ORDENPRODUCCION_CANCEL', 'Cancelar');

        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_ORDENPRODUCCION_PAYMENTS_TITLE'));
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

        HTMLHelper::_('bootstrap.framework');
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.ordenes', 'media/com_ordenproduccion/css/ordenes.css', [], ['version' => 'auto']);
    }

    /**
     * Format date for display
     *
     * @param   string  $date  The date string
     *
     * @return  string  Formatted date
     *
     * @since   1.0.0
     */
    public function formatDate($date)
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $timestamp = strtotime($date);
            return date('d F Y', $timestamp);
        }

        return HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC3'));
    }

    /**
     * Get route to order detail
     *
     * @param   int  $id  Order ID
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function getOrderRoute($id)
    {
        return Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . (int) $id);
    }

    /**
     * Get route to payment proof for an order
     *
     * @param   int  $orderId  Order ID
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function getPaymentProofRoute($orderId)
    {
        return Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . (int) $orderId);
    }

    /**
     * Get the model
     *
     * @param   string  $name    Model name
     * @param   string  $prefix  Model prefix
     * @param   array   $config  Configuration
     *
     * @return  \Joomla\CMS\MVC\Model\BaseModel
     *
     * @since   1.0.0
     */
    public function getModel($name = 'Payments', $prefix = '', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Translate payment type to language string
     *
     * @param   string  $type  Payment type key
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function translatePaymentType($type)
    {
        $map = [
            'efectivo' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_CASH',
            'cheque' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_CHECK',
            'transferencia' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_TRANSFER',
            'deposito' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_DEPOSIT',
            'nota_credito_fiscal' => 'COM_ORDENPRODUCCION_PAYMENT_TYPE_TAX_CREDIT_NOTE',
        ];
        $key = $map[strtolower($type ?? '')] ?? null;
        return $key ? Text::_($key) : htmlspecialchars($type ?? '');
    }
}
