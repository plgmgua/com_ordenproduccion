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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

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
     * Configured URL for the anulación de orden action
     *
     * @var    string
     * @since  1.0.0
     */
    public $anulacionUrl = '';

    /**
     * The user object
     *
     * @var    \Joomla\CMS\User\User
     * @since  1.0.0
     */
    protected $user;

    /**
     * Allowed group IDs per ordenes list action button (from backend settings).
     * Keys: crear_factura, registrar_pago, payment_info, solicitar_anulacion.
     * Value: int[] or empty array if use component default.
     *
     * @var    array
     * @since  1.0.0
     */
    protected $ordenesButtonAccess = [];

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

        // Check if user has access to ordenes using custom AccessHelper
        if (!AccessHelper::hasOrderAccess()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $app->redirect(Route::_('index.php'));
            return;
        }

        $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->params = $app->getParams('com_ordenproduccion');
        $this->user = $user;
        $this->anulacionUrl = $this->params->get(
            'anulacion_url',
            'https://grimpsa_webserver.grantsolutions.cc/index.php/anulacion-de-orden'
        );
        $this->ordenesButtonAccess = $this->getOrdenesButtonAccessConfig();

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
        $wa->registerAndUseScript('com_ordenproduccion.paymentinfo', 'media/com_ordenproduccion/js/payment-info.js', [], ['version' => 'auto']);
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

        // For DATE fields (YYYY-MM-DD), use direct PHP date formatting to avoid timezone conversion issues
        // Joomla's HTMLHelper::_('date') applies timezone conversion which can shift dates by one day
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // This is a DATE field (no time component), format directly without timezone conversion
            $timestamp = strtotime($date);
            return date('d F Y', $timestamp); // e.g., "14 October 2025"
        }

        // For DATETIME fields, use Joomla's date helper with timezone conversion
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
            case 'anulada':
                return 'badge-danger';
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
     * Check if user can see invoice value (and payment info)
     *
     * @param   object  $item  The order item
     *
     * @return  boolean  True if user can see invoice value
     *
     * @since   1.0.0
     */
    public function canSeeInvoiceValue($item)
    {
        return AccessHelper::canSeeValorFactura($item->sales_agent ?? '');
    }

    /**
     * Whether the user may open the cotización PDF for this order (list/detail).
     * Administracion: all. Ventas (including Ventas+Produccion): own only. Produccion-only: none.
     *
     * @param   object  $item  Order row
     *
     * @return  boolean
     */
    public function canViewCotizacionPdf($item)
    {
        return AccessHelper::canViewCotizacionPdfForOrder($item->sales_agent ?? '');
    }

    /**
     * Load allowed user group IDs per ordenes list action button from #__ordenproduccion_config.
     *
     * @return  array  Keys: crear_factura, registrar_pago, payment_info, solicitar_anulacion. Values: int[].
     * @since   1.0.0
     */
    protected function getOrdenesButtonAccessConfig()
    {
        $keys = [
            'crear_factura' => 'ordenes_btn_crear_factura_groups',
            'registrar_pago' => 'ordenes_btn_registrar_pago_groups',
            'payment_info'   => 'ordenes_btn_payment_info_groups',
            'solicitar_anulacion' => 'ordenes_btn_solicitar_anulacion_groups',
        ];
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('setting_key'), $db->quoteName('setting_value')])
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->whereIn($db->quoteName('setting_key'), array_values($keys));
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $out = [
            'crear_factura' => [],
            'registrar_pago' => [],
            'payment_info'   => [],
            'solicitar_anulacion' => [],
        ];
        $keyToOut = array_flip($keys);
        foreach ($rows as $row) {
            $k = $keyToOut[$row->setting_key] ?? null;
            if ($k === null) {
                continue;
            }
            $decoded = json_decode($row->setting_value, true);
            $out[$k] = is_array($decoded) ? array_map('intval', array_values($decoded)) : [];
        }
        return $out;
    }

    /**
     * Whether the current user is in any of the given group IDs.
     *
     * @param   int[]  $groupIds  Allowed group IDs.
     * @return  bool
     */
    protected function userInGroups(array $groupIds)
    {
        if (empty($groupIds)) {
            return false;
        }
        $userGroups = $this->user->groups ?? [];
        return !empty(array_intersect($groupIds, $userGroups));
    }

    /**
     * Whether to show the "Crear Factura" button. Uses backend-configured groups if set; else Administración.
     *
     * @return  bool
     */
    public function canShowCrearFactura()
    {
        $groups = $this->ordenesButtonAccess['crear_factura'] ?? [];
        if ($groups === []) {
            return AccessHelper::isInAdministracionGroup();
        }
        return $this->userInGroups($groups);
    }

    /**
     * Whether to show the "Registrar comprobante de pago" button. Uses backend-configured groups if set; else default.
     *
     * @return  bool
     */
    public function canShowRegistrarPago()
    {
        $groups = $this->ordenesButtonAccess['registrar_pago'] ?? [];
        if ($groups === []) {
            return $this->canRegisterPaymentProof();
        }
        return $this->userInGroups($groups);
    }

    /**
     * Whether to show the "View payment information" button. Uses backend-configured groups if set; else canSeeInvoiceValue.
     *
     * @param   object  $item  The order row (for sales_agent when using default logic).
     * @return  bool
     */
    public function canShowPaymentInfo($item)
    {
        $groups = $this->ordenesButtonAccess['payment_info'] ?? [];
        if ($groups === []) {
            return $this->canSeeInvoiceValue($item);
        }
        return $this->userInGroups($groups);
    }

    /**
     * Whether to show the "Solicitar anulación" button. Uses backend-configured groups if set; else super user or owner.
     *
     * @param   object|null  $item  The order row (for owner check); can be null when only checking by group.
     * @return  bool
     */
    public function canShowSolicitarAnulacion($item = null)
    {
        $groups = $this->ordenesButtonAccess['solicitar_anulacion'] ?? [];
        if ($groups === []) {
            return $this->canRequestAnulacion() || ($item !== null && $this->isOrderOwner($item));
        }
        return $this->userInGroups($groups) || ($item !== null && $this->isOrderOwner($item));
    }

    /**
     * Check if user can register payment proof (comprobante de pago).
     * True for everyone with order access so sales can register payments for their own work orders.
     *
     * @return  boolean
     */
    public function canRegisterPaymentProof()
    {
        return AccessHelper::canRegisterPaymentProof();
    }

    /**
     * Check if the current user can request an anulación de orden.
     * Currently restricted to super users only.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function canRequestAnulacion()
    {
        return $this->user->authorise('core.admin');
    }

    /**
     * Check whether the current user is the owner (sales agent) of a given order.
     * Used together with canRequestAnulacion() so owners can also request anulacion.
     *
     * @param   object  $item  The order row from the list.
     *
     * @return  bool
     *
     * @since   3.71.0
     */
    public function isOrderOwner($item): bool
    {
        $salesAgent = trim((string) ($item->sales_agent ?? ''));
        if ($salesAgent === '') {
            return false;
        }
        return (trim($this->user->name) === $salesAgent);
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
            // Spanish values (current)
            'Nueva' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'En Proceso' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'Terminada' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'Cerrada' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            // English values (legacy)
            'New' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'In Process' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'Completed' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'Closed' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            // Lowercase variants
            'nueva' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'en proceso' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'terminada' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'cerrada' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            'new' => 'COM_ORDENPRODUCCION_STATUS_NEW',
            'in process' => 'COM_ORDENPRODUCCION_STATUS_IN_PROCESS',
            'completed' => 'COM_ORDENPRODUCCION_STATUS_COMPLETED',
            'closed' => 'COM_ORDENPRODUCCION_STATUS_CLOSED',
            // Anulada
            'Anulada' => 'COM_ORDENPRODUCCION_STATUS_ANULADA',
            'anulada' => 'COM_ORDENPRODUCCION_STATUS_ANULADA',
        ];

        if (isset($statusMap[$status])) {
            $key        = $statusMap[$status];
            $translated = Text::_($key);
            // If Joomla returned the key itself the string file isn't loaded;
            // fall back to the raw status value which is already human-readable.
            return ($translated !== $key) ? $translated : $status;
        }

        // If status is already a language key, try to translate it directly
        if (strpos($status, 'COM_ORDENPRODUCCION_STATUS_') === 0) {
            $translated = Text::_($status);
            return ($translated !== $status) ? $translated : $status;
        }

        // Fallback: return the status as-is
        return $status;
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseModel  The model.
     *
     * @since   1.0.0
     */
    public function getModel($name = 'Ordenes', $prefix = '', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
