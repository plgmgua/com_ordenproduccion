<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;

/**
 * Ordenes model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenesModel extends ListModel
{
    /**
     * Constructor
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'o.id',
                'orden_de_trabajo', 'o.orden_de_trabajo',
                'nombre_del_cliente', 'o.nombre_del_cliente',
                'fecha_de_entrega', 'o.fecha_de_entrega',
                'created', 'o.created',
                'created_by', 'o.created_by',
                'state', 'o.state',
                'status', 'i.valor',
                'type', 't.valor'
            ];
        }

        parent::__construct($config);
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
    protected function populateState($ordering = 'o.created', $direction = 'desc')
    {
        $app = Factory::getApplication();

        // Load the filter state
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $status = $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string');
        $this->setState('filter.status', $status);

        $type = $app->getUserStateFromRequest($this->context . '.filter.type', 'filter_type', '', 'string');
        $this->setState('filter.type', $type);

        $dateFrom = $app->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);

        $dateTo = $app->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);

        // Load the parameters
        $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_ordenproduccion');
        $this->setState('params', $params);

        // List state information
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

        // Ordering
        $orderCol = $app->getUserStateFromRequest($this->context . '.ordercol', 'filter_order', $ordering);
        $this->setState('list.ordering', $orderCol);

        $listOrder = $app->getUserStateFromRequest($this->context . '.orderdirn', 'filter_order_Dir', $direction);
        $this->setState('list.direction', $listOrder);
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
        // Compile the store id
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.status');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');
        $id .= ':' . $this->getState('list.ordering');
        $id .= ':' . $this->getState('list.direction');

        return parent::getStoreId($id);
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
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table
        $query->select([
            $db->quoteName('o.id'),
            $db->quoteName('o.orden_de_trabajo'),
            $db->quoteName('o.nombre_del_cliente'),
            $db->quoteName('o.fecha_de_entrega'),
            $db->quoteName('o.agente_de_ventas'),
            $db->quoteName('o.descripcion_de_trabajo'),
            $db->quoteName('o.created'),
            $db->quoteName('o.created_by'),
            $db->quoteName('o.state'),
            $db->quoteName('i.valor', 'status'),
            $db->quoteName('t.valor', 'type')
        ]);

        $query->from($db->quoteName('#__ordenproduccion_ordenes', 'o'));

        // Join with status information
        $query->leftJoin(
            $db->quoteName('#__ordenproduccion_info', 'i') . ' ON ' .
            $db->quoteName('i.numero_de_orden') . ' = ' . $db->quoteName('o.orden_de_trabajo') . ' AND ' .
            $db->quoteName('i.tipo_de_campo') . ' = ' . $db->quote('estado')
        );

        // Join with type information
        $query->leftJoin(
            $db->quoteName('#__ordenproduccion_info', 't') . ' ON ' .
            $db->quoteName('t.numero_de_orden') . ' = ' . $db->quoteName('o.orden_de_trabajo') . ' AND ' .
            $db->quoteName('t.tipo_de_campo') . ' = ' . $db->quote('tipo')
        );

        // Filter by published state
        $query->where($db->quoteName('o.state') . ' = 1');

        // Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where($db->quoteName('o.id') . ' = ' . (int) substr($search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(' . $db->quoteName('o.orden_de_trabajo') . ' LIKE ' . $search . 
                             ' OR ' . $db->quoteName('o.nombre_del_cliente') . ' LIKE ' . $search . 
                             ' OR ' . $db->quoteName('o.descripcion_de_trabajo') . ' LIKE ' . $search . ')');
            }
        }

        // Filter by status
        $status = $this->getState('filter.status');
        if (!empty($status)) {
            $query->where($db->quoteName('i.valor') . ' = ' . $db->quote($status));
        }

        // Filter by type
        $type = $this->getState('filter.type');
        if (!empty($type)) {
            $query->where($db->quoteName('t.valor') . ' = ' . $db->quote($type));
        }

        // Filter by date range
        $dateFrom = $this->getState('filter.date_from');
        if (!empty($dateFrom)) {
            $query->where($db->quoteName('o.created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }

        $dateTo = $this->getState('filter.date_to');
        if (!empty($dateTo)) {
            $query->where($db->quoteName('o.created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }

        // Add the list ordering clause
        $orderCol = $this->state->get('list.ordering', 'o.created');
        $orderDirn = $this->state->get('list.direction', 'desc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Get items with additional data
     *
     * @return  array  Array of objects
     *
     * @since   1.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items) {
            $db = $this->getDbo();
            
            foreach ($items as $item) {
                // Get technician assignments
                $query = $db->getQuery(true)
                    ->select('technician_name')
                    ->from($db->quoteName('#__ordenproduccion_technicians'))
                    ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($item->orden_de_trabajo))
                    ->where($db->quoteName('state') . ' = 1');
                
                $db->setQuery($query);
                $technicians = $db->loadColumn();
                $item->technicians = $technicians ? implode(', ', $technicians) : '';

                // Get production notes count
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_production_notes'))
                    ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($item->orden_de_trabajo))
                    ->where($db->quoteName('state') . ' = 1');
                
                $db->setQuery($query);
                $item->notes_count = (int) $db->loadResult();

                // Format dates
                if ($item->fecha_de_entrega) {
                    $item->fecha_de_entrega_formatted = Factory::getDate($item->fecha_de_entrega)->format('d/m/Y');
                }
                
                $item->created_formatted = Factory::getDate($item->created)->format('d/m/Y H:i');
            }
        }

        return $items;
    }

    /**
     * Get available status options
     *
     * @return  array  Status options
     *
     * @since   1.0.0
     */
    public function getStatusOptions()
    {
        return [
            '' => 'COM_ORDENPRODUCCION_FILTER_SELECT_STATUS',
            'nueva' => 'COM_ORDENPRODUCCION_STATUS_NUEVA',
            'en_proceso' => 'COM_ORDENPRODUCCION_STATUS_EN_PROCESO',
            'terminada' => 'COM_ORDENPRODUCCION_STATUS_TERMINADA',
            'cerrada' => 'COM_ORDENPRODUCCION_STATUS_CERRADA'
        ];
    }

    /**
     * Get available type options
     *
     * @return  array  Type options
     *
     * @since   1.0.0
     */
    public function getTypeOptions()
    {
        return [
            '' => 'COM_ORDENPRODUCCION_FILTER_SELECT_TYPE',
            'interna' => 'COM_ORDENPRODUCCION_TYPE_INTERNA',
            'externa' => 'COM_ORDENPRODUCCION_TYPE_EXTERNA'
        ];
    }
}
