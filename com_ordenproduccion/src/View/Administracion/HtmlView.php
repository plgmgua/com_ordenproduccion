<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Administracion;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Administracion Dashboard View
 *
 * @since  3.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Dashboard statistics
     *
     * @var    object
     * @since  3.1.0
     */
    protected $stats;

    /**
     * Current month
     *
     * @var    string
     * @since  3.1.0
     */
    protected $currentMonth;

    /**
     * Current year
     *
     * @var    string
     * @since  3.1.0
     */
    protected $currentYear;

    /**
     * Invoices list
     *
     * @var    array
     * @since  3.2.0
     */
    protected $invoices;

    /**
     * Invoices pagination
     *
     * @var    object
     * @since  3.2.0
     */
    protected $invoicesPagination;

    /**
     * Model state
     *
     * @var    object
     * @since  3.2.0
     */
    protected $state;

    /**
     * Work orders list
     *
     * @var    array
     * @since  3.2.0
     */
    protected $workOrders;

    /**
     * Work orders pagination
     *
     * @var    object
     * @since  3.2.0
     */
    protected $workOrdersPagination;

    /**
     * Banks list
     *
     * @var    array
     * @since  3.5.1
     */
    protected $banks = [];

    /**
     * Active subtab for herramientas tab
     *
     * @var    string
     * @since  3.5.2
     */
    protected $activeSubTab = 'banks';

    /**
     * Activity statistics (for resumen tab)
     *
     * @var    object
     * @since  3.6.0
     */
    protected $activityStats;

    /**
     * Selected period for activity statistics (day, week, month)
     *
     * @var    string
     * @since  3.6.0
     */
    protected $selectedPeriod = 'day';

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.1.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $input = $app->input;

        // Load component language files - standard Joomla way
        $lang = $app->getLanguage();
        // Load site language (usually in components/com_ordenproduccion/language)
        $lang->load('com_ordenproduccion', JPATH_SITE);
        // Also try loading from component directory directly
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        // Load admin language
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        // Get filter parameters
        $this->currentMonth = $input->getInt('month', 0); // 0 = All Year, 1-12 = specific month
        $this->currentYear = $input->getInt('year', date('Y'));

        // Get active tab - default to resumen for better UX
        $activeTab = $input->get('tab', 'resumen', 'string');
        
        // Get active subtab for herramientas tab - default to banks
        $activeSubTab = $input->get('subtab', 'banks', 'string');
        $this->activeSubTab = $activeSubTab;

        // Get statistics model and data
        $statsModel = $this->getModel('Administracion');
        $this->stats = $statsModel->getStatistics($this->currentMonth, $this->currentYear);

        // Load activity statistics if resumen tab is active
        if ($activeTab === 'resumen') {
            try {
                // Get selected period from request, default to 'day'
                $selectedPeriod = $input->get('period', 'day', 'string'); // day, week, month
                $this->activityStats = $statsModel->getActivityStatistics($selectedPeriod);
                // Store selected period for template
                $this->selectedPeriod = $selectedPeriod;
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading activity statistics: ' . $e->getMessage(), 'error');
                $this->activityStats = (object) [
                    'daily' => [],
                    'weekly' => [],
                    'monthly' => []
                ];
                $this->selectedPeriod = 'day';
            }
        }

        // Initialize data arrays - ensure all properties are set before parent::display()
        $this->invoices = [];
        $this->invoicesPagination = null;
        $this->workOrders = [];
        $this->workOrdersPagination = null;
        $this->state = new \Joomla\Registry\Registry();
        $this->banks = [];
        
        // Ensure banks is always an array to prevent undefined array key errors
        if (!isset($this->banks) || !is_array($this->banks)) {
            $this->banks = [];
        }

        // Load invoices data if invoices tab is active
        if ($activeTab === 'invoices') {
            try {
                $invoicesModel = $this->getModel('Invoices');
                if ($invoicesModel) {
                    $this->invoices = $invoicesModel->getItems();
                    $this->invoicesPagination = $invoicesModel->getPagination();
                    $this->state = $invoicesModel->getState();
                }
            } catch (\Exception $e) {
                // If invoices model fails, log error but continue with empty data
                $app->enqueueMessage('Invoices feature is not yet fully configured. Please run the SQL script: helpers/create_invoices_table.sql', 'warning');
            }
        }

        // Load work orders data if workorders tab is active
        if ($activeTab === 'workorders') {
            try {
                // Get work orders directly from database (bypass user group filtering for admin view)
                $db = Factory::getDbo();
                $query = $db->getQuery(true);
                
                // Select all work order fields
                $query->select([
                    'id', 'orden_de_trabajo', 'order_number', 'client_name', 'nit',
                    'invoice_value', 'work_description', 'print_color', 'dimensions',
                    'delivery_date', 'material', 'request_date', 'sales_agent', 'status',
                    'invoice_number', 'quotation_files', 'created', 'created_by', 'modified', 'modified_by', 'state', 'version'
                ]);
                $query->from($db->quoteName('#__ordenproduccion_ordenes'));
                
                // Only show published orders
                $query->where($db->quoteName('state') . ' = 1');
                
                // Apply search filter if provided
                $search = $input->getString('filter_search', '');
                if (!empty($search)) {
                    $searchEscaped = $db->quote('%' . $db->escape($search, true) . '%');
                    $query->where('(' . $db->quoteName('orden_de_trabajo') . ' LIKE ' . $searchEscaped .
                        ' OR ' . $db->quoteName('client_name') . ' LIKE ' . $searchEscaped . ')');
                }
                
                // Apply status filter if provided
                $status = $input->getString('filter_status', '');
                if (!empty($status)) {
                    $query->where($db->quoteName('status') . ' = ' . $db->quote($status));
                }
                
                // Order by orden_de_trabajo descending
                $query->order($db->quoteName('orden_de_trabajo') . ' DESC');
                
                // Get total count first
                $countQuery = clone $query;
                $countQuery->clear('select');
                $countQuery->select('COUNT(*)');
                $db->setQuery($countQuery);
                $total = $db->loadResult();
                
                // Set limit for pagination
                $limit = $input->getInt('limit', 20);
                $start = $input->getInt('limitstart', 0);
                $query->setLimit($limit, $start);
                
                $db->setQuery($query);
                $this->workOrders = $db->loadObjectList() ?: [];
                
                // Create a proper pagination object
                $this->workOrdersPagination = new \Joomla\CMS\Pagination\Pagination($total, $start, $limit);
                
                // Create state object
                $this->state = new \Joomla\Registry\Registry();
                $this->state->set('filter.search', $search);
                $this->state->set('filter.status', $status);
                
                
            } catch (\Exception $e) {
                // If query fails, log error and show empty array
                $app->enqueueMessage('Error loading work orders: ' . $e->getMessage(), 'error');
                $this->workOrders = [];
                $this->workOrdersPagination = null;
            }
        }

        // Load banks data if herramientas tab is active and banks subtab is selected
        if ($activeTab === 'herramientas' && $activeSubTab === 'banks') {
            try {
                // Get Bank model using MVC factory
                $component = $app->bootComponent('com_ordenproduccion');
                $mvcFactory = $component->getMVCFactory();
                $bankModel = $mvcFactory->createModel('Bank', 'Site', ['ignore_request' => true]);
                
                if ($bankModel && method_exists($bankModel, 'getBanks')) {
                    $this->banks = $bankModel->getBanks();
                } else {
                    $this->banks = [];
                }
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading banks: ' . $e->getMessage(), 'warning');
                $this->banks = [];
            }
        } else {
            // Initialize banks as empty array if not banks subtab
            $this->banks = [];
        }

        // Final safeguard: Ensure banks is always an array before parent::display()
        // This prevents "Undefined array key 'bank'" errors in Joomla's AbstractView
        // Joomla's AbstractView may access properties via array notation, so we need to ensure
        // the property exists and is properly initialized
        if (!isset($this->banks) || !is_array($this->banks)) {
            $this->banks = [];
        }
        
        // Also check via reflection to ensure property is initialized (PHP 7.4+)
        try {
            $reflection = new \ReflectionClass($this);
            $property = $reflection->getProperty('banks');
            if (!$property->isInitialized($this)) {
                $this->banks = [];
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, just ensure it's an array
            $this->banks = [];
        } catch (\Error $e) {
            // PHP 7.3 compatibility - ReflectionProperty::isInitialized() doesn't exist
            $this->banks = [];
        }
        
        // Prepare document
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepare document
     *
     * @return  void
     *
     * @since   3.1.0
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TITLE'));

        // Load Bootstrap and jQuery
        \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.framework');
    }
}

