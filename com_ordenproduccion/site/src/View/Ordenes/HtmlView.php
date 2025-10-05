<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Ordenes;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;

/**
 * Ordenes view class for com_ordenproduccion
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
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
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
            $this->params->def('page_heading', Text::_('COM_ORDENPRODUCCION_ORDENES_DEFAULT_PAGE_TITLE'));
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
        $wa->registerAndUseStyle('com_ordenproduccion.ordenes', 'media/com_ordenproduccion/css/ordenes.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.ordenes', 'media/com_ordenproduccion/js/ordenes.js', [], ['version' => 'auto']);
    }

    /**
     * Get the route for an order detail view
     *
     * @param   integer  $id  The order ID
     *
     * @return  string  The route URL
     *
     * @since   1.0.0
     */
    public function getOrderRoute($id)
    {
        return Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . (int) $id);
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
     * @param   object  $item  The order item
     *
     * @return  boolean  True if user can see invoice value
     *
     * @since   1.0.0
     */
    public function canSeeInvoiceValue($item)
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
            return $item->sales_agent === $this->user->get('name');
        }

        return false;
    }
}
