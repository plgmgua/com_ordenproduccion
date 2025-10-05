<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\User\UserHelper;

/**
 * Ordenes model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenesModel extends ListModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_ORDENES';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'order_number', 'client_name', 'request_date', 'delivery_date', 'status'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.status');
        $id .= ':' . $this->getState('filter.client_name');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');

        return parent::getStoreId($id);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = 'request_date', $direction = 'desc')
    {
        $app = Factory::getApplication();

        // Load the parameters.
        $params = $app->getParams();
        $this->setState('params', $params);

        // List state information.
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

        // Ordering
        $orderCol = $app->input->get('filter_order', $ordering);
        if (!in_array($orderCol, $this->filter_fields)) {
            $orderCol = $ordering;
        }
        $this->setState('list.ordering', $orderCol);

        $listOrder = $app->input->get('filter_order_Dir', $direction);
        if (!in_array(strtoupper($listOrder), ['ASC', 'DESC', ''])) {
            $listOrder = $direction;
        }
        $this->setState('list.direction', $listOrder);

        // Filters
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $status = $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string');
        $this->setState('filter.status', $status);

        $clientName = $app->getUserStateFromRequest($this->context . '.filter.client_name', 'filter_client_name', '', 'string');
        $this->setState('filter.client_name', $clientName);

        $dateFrom = $app->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);

        $dateTo = $app->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);
    }

    /**
     * Method to get an array of data items.
     *
     * @return  mixed  An array of data items on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items === false) {
            return false;
        }

        // Add access control and field visibility
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();
        
        foreach ($items as &$item) {
            // Apply field visibility based on user groups
            $this->applyFieldVisibility($item, $userGroups, $user);
        }

        return $items;
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   1.0.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.id, a.order_number, a.orden_de_trabajo, a.client_name, a.nit, ' .
                'a.invoice_value, a.work_description, a.print_color, a.dimensions, ' .
                'a.delivery_date, a.material, a.request_date, a.status, a.order_type, ' .
                'a.assigned_technician, a.production_notes, a.sales_agent, a.created, ' .
                'a.created_by, a.modified, a.modified_by, a.state, a.version'
            )
        );
        $query->from($db->quoteName('#__ordenproduccion_ordenes', 'a'));

        // Filter by published state
        $query->where($db->quoteName('a.state') . ' = 1');

        // Apply user group-based filtering
        $this->applyUserGroupFilter($query, $db);

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(' . $db->quoteName('a.order_number') . ' LIKE ' . $search .
                    ' OR ' . $db->quoteName('a.client_name') . ' LIKE ' . $search .
                    ' OR ' . $db->quoteName('a.work_description') . ' LIKE ' . $search . ')');
            }
        }

        // Filter by status
        $status = $this->getState('filter.status');
        if (!empty($status)) {
            $query->where($db->quoteName('a.status') . ' = ' . $db->quote($status));
        }

        // Filter by client name
        $clientName = $this->getState('filter.client_name');
        if (!empty($clientName)) {
            $query->where($db->quoteName('a.client_name') . ' LIKE ' . $db->quote('%' . $db->escape($clientName, true) . '%'));
        }

        // Filter by date range
        $dateFrom = $this->getState('filter.date_from');
        if (!empty($dateFrom)) {
            $query->where($db->quoteName('a.request_date') . ' >= ' . $db->quote($dateFrom));
        }

        $dateTo = $this->getState('filter.date_to');
        if (!empty($dateTo)) {
            $query->where($db->quoteName('a.request_date') . ' <= ' . $db->quote($dateTo));
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'request_date');
        $orderDirn = $this->state->get('list.direction', 'desc');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));
        }

        return $query;
    }

    /**
     * Apply user group-based filtering to the query
     *
     * @param   \Joomla\Database\DatabaseQuery  $query  The query object
     * @param   \Joomla\Database\DatabaseDriver  $db     The database driver
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function applyUserGroupFilter($query, $db)
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();

        // Check if user is in ventas group (group ID 2 is typically Registered users, adjust as needed)
        $isVentas = in_array(2, $userGroups); // Adjust group ID as needed
        $isProduccion = in_array(3, $userGroups); // Adjust group ID as needed

        if ($isVentas && !$isProduccion) {
            // Sales users can only see their own orders
            $userName = $user->get('name');
            $query->where($db->quoteName('a.sales_agent') . ' = ' . $db->quote($userName));
        }
        // Production users and users in both groups can see all orders (no additional filter)
    }

    /**
     * Apply field visibility based on user groups
     *
     * @param   object  $item        The item object
     * @param   array   $userGroups  User's group IDs
     * @param   object  $user        The user object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function applyFieldVisibility($item, $userGroups, $user)
    {
        $isVentas = in_array(2, $userGroups); // Adjust group ID as needed
        $isProduccion = in_array(3, $userGroups); // Adjust group ID as needed

        // If user is in both groups, they can see all orders but restricted fields only for their own
        if ($isVentas && $isProduccion) {
            $userName = $user->get('name');
            if ($item->sales_agent !== $userName) {
                // Hide restricted fields for orders not belonging to the user
                unset($item->invoice_value);
            }
        } elseif ($isProduccion && !$isVentas) {
            // Production users cannot see invoice value
            unset($item->invoice_value);
        }
        // Sales users can see all fields (no restrictions)
    }

    /**
     * Get available status options
     *
     * @return  array  Array of status options
     *
     * @since   1.0.0
     */
    public function getStatusOptions()
    {
        return [
            '' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_SELECT_STATUS'),
            'New' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_NEW'),
            'In Process' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_IN_PROCESS'),
            'Completed' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_COMPLETED'),
            'Closed' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_CLOSED')
        ];
    }
}
