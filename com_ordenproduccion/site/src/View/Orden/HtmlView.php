<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Orden;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * Orden view class for com_ordenproduccion
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
     * The item object
     *
     * @var    object
     * @since  1.0.0
     */
    protected $item;

    /**
     * The component parameters
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $params;

    /**
     * The user object
     *
     * @var    \Joomla\CMS\User\User
     * @since  1.0.0
     */
    protected $user;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   1.0.0
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

        // Check if user has access to ordenes
        if (!$user->authorise('core.view', 'com_ordenproduccion')) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $app->redirect(Route::_('index.php'));
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
     * @since   1.0.0
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
            $this->params->def('page_heading', Text::_('COM_ORDENPRODUCCION_ORDEN_DEFAULT_PAGE_TITLE'));
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
        $wa->registerAndUseStyle('com_ordenproduccion.orden', 'media/com_ordenproduccion/css/orden.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.orden', 'media/com_ordenproduccion/js/orden.js', [], ['version' => 'auto']);
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

        return HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC3'));
    }

    /**
     * Get status badge class
     *
     * @param   string  $status  The status
     *
     * @return  string  Badge class
     *
     * @since   1.0.0
     */
    public function getStatusBadgeClass($status)
    {
        switch (strtolower($status)) {
            case 'new':
                return 'badge-primary';
            case 'in process':
                return 'badge-warning';
            case 'completed':
                return 'badge-success';
            case 'closed':
                return 'badge-secondary';
            default:
                return 'badge-light';
        }
    }

    /**
     * Get order type badge class
     *
     * @param   string  $type  The order type
     *
     * @return  string  Badge class
     *
     * @since   1.0.0
     */
    public function getOrderTypeBadgeClass($type)
    {
        switch (strtolower($type)) {
            case 'internal':
                return 'badge-info';
            case 'external':
                return 'badge-success';
            default:
                return 'badge-light';
        }
    }

    /**
     * Get shipping status badge class
     *
     * @param   string  $status  The shipping status
     *
     * @return  string  Badge class
     *
     * @since   1.0.0
     */
    public function getShippingStatusBadgeClass($status)
    {
        switch (strtolower($status)) {
            case 'pending':
                return 'badge-warning';
            case 'shipped':
                return 'badge-info';
            case 'delivered':
                return 'badge-success';
            default:
                return 'badge-light';
        }
    }

    /**
     * Get user groups for access control
     *
     * @return  array  User group IDs
     *
     * @since   1.0.0
     */
    public function getUserGroups()
    {
        return $this->user->getAuthorisedGroups();
    }

    /**
     * Check if user can see invoice value
     *
     * @return  boolean  True if user can see invoice value
     *
     * @since   1.0.0
     */
    public function canSeeInvoiceValue()
    {
        $userGroups = $this->getUserGroups();
        $isVentas = in_array(2, $userGroups); // Adjust group ID as needed
        $isProduccion = in_array(3, $userGroups); // Adjust group ID as needed

        if ($isVentas && !$isProduccion) {
            // Sales users can see invoice value for their own orders
            return true;
        } elseif ($isProduccion && !$isVentas) {
            // Production users cannot see invoice value
            return false;
        } elseif ($isVentas && $isProduccion) {
            // Users in both groups can see invoice value only for their own orders
            return $this->item->sales_agent === $this->user->get('name');
        }

        return false;
    }

    /**
     * Get back to list route
     *
     * @return  string  The route URL
     *
     * @since   1.0.0
     */
    public function getBackToListRoute()
    {
        return Route::_('index.php?option=com_ordenproduccion&view=ordenes');
    }

    /**
     * Format currency value
     *
     * @param   float  $value  The currency value
     *
     * @return  string  Formatted currency
     *
     * @since   1.0.0
     */
    public function formatCurrency($value)
    {
        if (empty($value) || $value == 0) {
            return '-';
        }

        return '$' . number_format($value, 2);
    }

    /**
     * Get EAV attribute value
     *
     * @param   string  $attribute  The attribute name
     *
     * @return  string  The attribute value
     *
     * @since   1.0.0
     */
    public function getEAVValue($attribute)
    {
        if (isset($this->item->eav_data[$attribute])) {
            return $this->item->eav_data[$attribute];
        }

        return '-';
    }

    /**
     * Translate status value to human-readable text
     *
     * @param   string  $status  The status value
     *
     * @return  string  Translated status text
     *
     * @since   1.0.0
     */
    public function translateStatus($status)
    {
        // Map status values to language keys
        $statusMap = [
            'New' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'In Process' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'Completed' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'Closed' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            'new' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'in process' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'completed' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'closed' => 'COM_ORDENPRODUCCION_STATUS_CLOSED'
        ];

        if (isset($statusMap[$status])) {
            return Text::_($statusMap[$status]);
        }

        // If status is already a language key, try to translate it directly
        if (strpos($status, 'COM_ORDENPRODUCCION_STATUS_') === 0) {
            return Text::_($status);
        }

        // Fallback: return the status as-is
        return $status;
    }
}
