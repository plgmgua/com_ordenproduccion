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

        // Load component language
        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

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
            case 'nuevo':
                return 'badge-primary';
            case 'in process':
            case 'en proceso':
                return 'badge-warning';
            case 'completed':
            case 'terminada':
            case 'completada':
                return 'badge-success';
            case 'closed':
            case 'cerrada':
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

        return 'Q.' . number_format($value, 2);
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
        // Debug: Log the status value
        error_log("DEBUG: translateStatus called with status: " . var_export($status, true));
        
        // Handle empty or null status - default to "Nueva" for new orders
        if (empty($status) || $status === null || $status === '') {
            error_log("DEBUG: Status is empty, returning Nueva");
            return Text::_('COM_ORDENPRODUCCION_STATUS_NEW');
        }

        // Map status values to language keys
        $statusMap = [
            // Spanish values (current)
            'Nueva' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'En Proceso' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'Terminada' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'Cerrada' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            'Entregada' => 'COM_ORDENPRODUCCION_STATUS_DELIVERED',
            // English values (legacy)
            'New' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'In Process' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'Completed' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'Closed' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            'Delivered' => 'COM_ORDENPRODUCCION_STATUS_DELIVERED',
            // Lowercase variants
            'nueva' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'en proceso' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'terminada' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'cerrada' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            'entregada' => 'COM_ORDENPRODUCCION_STATUS_DELIVERED',
            'new' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'in process' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'completed' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'closed' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            'delivered' => 'COM_ORDENPRODUCCION_STATUS_DELIVERED'
        ];

        if (isset($statusMap[$status])) {
            $translated = Text::_($statusMap[$status]);
            error_log("DEBUG: Status mapped to: " . $statusMap[$status] . " -> " . $translated);
            return $translated;
        }

        // If status is already a language key, try to translate it directly
        if (strpos($status, 'COM_ORDENPRODUCCION_STATUS_') === 0) {
            $translated = Text::_($status);
            error_log("DEBUG: Status is language key: " . $status . " -> " . $translated);
            return $translated;
        }

        // Fallback: return the status as-is
        error_log("DEBUG: Status fallback: " . $status);
        return $status;
    }

    /**
     * Get component version
     *
     * @return  string  Component version
     *
     * @since   1.0.0
     */
    public function getComponentVersion()
    {
        try {
            // Try to get version from VERSION file first
            $versionFile = JPATH_ROOT . '/components/com_ordenproduccion/VERSION';
            if (file_exists($versionFile)) {
                $version = trim(file_get_contents($versionFile));
                if (!empty($version)) {
                    return $version;
                }
            }

            // Fallback to manifest version
            $manifestFile = JPATH_ROOT . '/components/com_ordenproduccion/com_ordenproduccion.xml';
            if (file_exists($manifestFile)) {
                $manifest = simplexml_load_file($manifestFile);
                if ($manifest && isset($manifest['version'])) {
                    return (string) $manifest['version'];
                }
            }

            // Final fallback
            return '1.0.0';
        } catch (Exception $e) {
            return '1.0.0';
        }
    }
}
