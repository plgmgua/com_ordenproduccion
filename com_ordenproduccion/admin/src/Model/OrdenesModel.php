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
 * Supports both schema variants: nombre_del_cliente/client_name on ordenes,
 * numero_de_orden (or orden_de_trabajo) on info table.
 *
 * @since  1.0.0
 */
class OrdenesModel extends ListModel
{
    /** @var string|null Cached client column name for #__ordenproduccion_ordenes */
    protected $ordenesClientColumn;

    /** @var string|null Cached order-number column name for #__ordenproduccion_info */
    protected $infoOrderColumn;

    /** @var string[]|null Cached column list for #__ordenproduccion_ordenes */
    protected $ordenesColumnNames;
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
                'client_name', 'o.client_name',
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
     * Get the client name column for #__ordenproduccion_ordenes (supports client_name and nombre_del_cliente).
     * Returns null if neither column exists (caller should not select/search by client).
     *
     * @return  string|null
     * @since   1.0.0
     */
    protected function getOrdenesClientColumn()
    {
        if ($this->ordenesClientColumn !== null) {
            return $this->ordenesClientColumn;
        }
        try {
            $db = $this->getDbo();
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (!is_array($cols)) {
                $this->ordenesClientColumn = '';
                return $this->ordenesClientColumn;
            }
            $colNames = array_keys($cols);
            if (in_array('nombre_del_cliente', $colNames)) {
                $this->ordenesClientColumn = 'nombre_del_cliente';
                return $this->ordenesClientColumn;
            }
            if (in_array('client_name', $colNames)) {
                $this->ordenesClientColumn = 'client_name';
                return $this->ordenesClientColumn;
            }
            // Mark as no client column so we don't query again; use empty string to mean "skip"
            $this->ordenesClientColumn = '';
        } catch (\Throwable $e) {
            $this->ordenesClientColumn = '';
        }
        return $this->ordenesClientColumn;
    }

    /**
     * Get the order-number column for #__ordenproduccion_info (supports numero_de_orden and orden_de_trabajo).
     * Returns null if neither exists (caller should skip info joins).
     *
     * @return  string|null
     * @since   1.0.0
     */
    protected function getInfoOrderColumn()
    {
        if ($this->infoOrderColumn !== null) {
            return $this->infoOrderColumn;
        }
        try {
            $db = $this->getDbo();
            $cols = $db->getTableColumns('#__ordenproduccion_info', false);
            if (!is_array($cols)) {
                $this->infoOrderColumn = '';
                return $this->infoOrderColumn;
            }
            $colNames = array_keys($cols);
            if (in_array('numero_de_orden', $colNames)) {
                $this->infoOrderColumn = 'numero_de_orden';
                return $this->infoOrderColumn;
            }
            if (in_array('orden_de_trabajo', $colNames)) {
                $this->infoOrderColumn = 'orden_de_trabajo';
                return $this->infoOrderColumn;
            }
            $this->infoOrderColumn = '';
        } catch (\Throwable $e) {
            $this->infoOrderColumn = '';
        }
        return $this->infoOrderColumn;
    }

    /**
     * Get column names for #__ordenproduccion_ordenes (cached).
     *
     * @return  string[]
     * @since   1.0.0
     */
    protected function getOrdenesColumnNames()
    {
        if ($this->ordenesColumnNames !== null) {
            return $this->ordenesColumnNames;
        }
        try {
            $db = $this->getDbo();
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $this->ordenesColumnNames = is_array($cols) ? array_keys($cols) : [];
        } catch (\Throwable $e) {
            $this->ordenesColumnNames = [];
        }
        return $this->ordenesColumnNames;
    }

    /**
     * Check if a column exists on #__ordenproduccion_ordenes.
     *
     * @param   string  $name  Column name
     * @return  bool
     */
    protected function ordenesHasColumn($name)
    {
        return in_array($name, $this->getOrdenesColumnNames(), true);
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
        $clientCol = $this->getOrdenesClientColumn();
        $infoOrderCol = $this->getInfoOrderColumn();

        $select = [
            $db->quoteName('o.id'),
            $db->quoteName('o.orden_de_trabajo'),
            $db->quoteName('o.created'),
            $db->quoteName('o.created_by'),
            $db->quoteName('o.state'),
        ];
        if ($this->ordenesHasColumn('fecha_de_entrega')) {
            $select[] = $db->quoteName('o.fecha_de_entrega');
        } else {
            $select[] = $db->quote('NULL') . ' AS ' . $db->quoteName('fecha_de_entrega');
        }
        if ($this->ordenesHasColumn('agente_de_ventas')) {
            $select[] = $db->quoteName('o.agente_de_ventas');
        } else {
            $select[] = $db->quote('') . ' AS ' . $db->quoteName('agente_de_ventas');
        }
        if ($this->ordenesHasColumn('descripcion_de_trabajo')) {
            $select[] = $db->quoteName('o.descripcion_de_trabajo');
        } else {
            $select[] = $db->quote('') . ' AS ' . $db->quoteName('descripcion_de_trabajo');
        }
        if ($clientCol !== '') {
            $select[] = $db->quoteName('o.' . $clientCol, 'nombre_del_cliente');
        } else {
            $select[] = $db->quote('') . ' AS ' . $db->quoteName('nombre_del_cliente');
        }
        if ($infoOrderCol !== '') {
            $select[] = $db->quoteName('i.valor', 'status');
            $select[] = $db->quoteName('t.valor', 'type');
        } else {
            $select[] = $db->quote('NULL') . ' AS ' . $db->quoteName('status');
            $select[] = $db->quote('NULL') . ' AS ' . $db->quoteName('type');
        }
        $query->select($select);

        $query->from($db->quoteName('#__ordenproduccion_ordenes', 'o'));

        if ($infoOrderCol !== '') {
            $query->leftJoin(
                $db->quoteName('#__ordenproduccion_info', 'i') . ' ON ' .
                $db->quoteName('i.' . $infoOrderCol) . ' = ' . $db->quoteName('o.orden_de_trabajo') . ' AND ' .
                $db->quoteName('i.tipo_de_campo') . ' = ' . $db->quote('estado')
            );
            $query->leftJoin(
                $db->quoteName('#__ordenproduccion_info', 't') . ' ON ' .
                $db->quoteName('t.' . $infoOrderCol) . ' = ' . $db->quoteName('o.orden_de_trabajo') . ' AND ' .
                $db->quoteName('t.tipo_de_campo') . ' = ' . $db->quote('tipo')
            );
        }

        $query->where($db->quoteName('o.state') . ' = 1');

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where($db->quoteName('o.id') . ' = ' . (int) substr($search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $conds = [$db->quoteName('o.orden_de_trabajo') . ' LIKE ' . $search];
                if ($clientCol !== '') {
                    $conds[] = $db->quoteName('o.' . $clientCol) . ' LIKE ' . $search;
                }
                if ($this->ordenesHasColumn('descripcion_de_trabajo')) {
                    $conds[] = $db->quoteName('o.descripcion_de_trabajo') . ' LIKE ' . $search;
                }
                $query->where('(' . implode(' OR ', $conds) . ')');
            }
        }

        if ($infoOrderCol !== '') {
            $status = $this->getState('filter.status');
            if (!empty($status)) {
                $query->where($db->quoteName('i.valor') . ' = ' . $db->quote($status));
            }
            $type = $this->getState('filter.type');
            if (!empty($type)) {
                $query->where($db->quoteName('t.valor') . ' = ' . $db->quote($type));
            }
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
        // Normalize client column sort (template may use a.* or o.*)
        if (in_array($orderCol, ['a.nombre_del_cliente', 'o.nombre_del_cliente', 'a.client_name', 'o.client_name'], true)) {
            $orderCol = $clientCol !== '' ? 'o.' . $clientCol : 'o.orden_de_trabajo';
        }
        // If ordering by a column that doesn't exist, use o.created
        if (preg_match('/^o\.(\w+)$/', $orderCol, $m) && !$this->ordenesHasColumn($m[1])) {
            $orderCol = 'o.created';
        }
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

        // Ensure we always return an array, even if parent::getItems() returns false
        if (!$items) {
            return [];
        }

        if ($items) {
            $db = $this->getDbo();
            
            foreach ($items as $item) {
                $item->technicians = '';
                $item->notes_count = 0;

                try {
                    $techCols = $db->getTableColumns('#__ordenproduccion_technicians', false);
                    $techColNames = is_array($techCols) ? array_keys($techCols) : [];
                    $techNameCol = in_array('technician_name', $techColNames, true) ? 'technician_name' : (in_array('person_name', $techColNames, true) ? 'person_name' : null);
                    $techOrderCol = in_array('numero_de_orden', $techColNames, true) ? 'numero_de_orden' : (in_array('orden_de_trabajo', $techColNames, true) ? 'orden_de_trabajo' : null);
                    if ($techNameCol !== null && $techOrderCol !== null) {
                        $query = $db->getQuery(true)
                            ->select($db->quoteName($techNameCol))
                            ->from($db->quoteName('#__ordenproduccion_technicians'))
                            ->where($db->quoteName($techOrderCol) . ' = ' . $db->quote($item->orden_de_trabajo))
                            ->where($db->quoteName('state') . ' = 1');
                        $db->setQuery($query);
                        $technicians = $db->loadColumn();
                        $item->technicians = $technicians ? implode(', ', $technicians) : '';
                    }
                } catch (\Throwable $e) {
                    // Table or columns missing
                }

                try {
                    $notesCols = $db->getTableColumns('#__ordenproduccion_production_notes', false);
                    $notesColNames = is_array($notesCols) ? array_keys($notesCols) : [];
                    $notesOrderCol = in_array('numero_de_orden', $notesColNames, true) ? 'numero_de_orden' : (in_array('orden_de_trabajo', $notesColNames, true) ? 'orden_de_trabajo' : null);
                    if ($notesOrderCol !== null) {
                        $query = $db->getQuery(true)
                            ->select('COUNT(*)')
                            ->from($db->quoteName('#__ordenproduccion_production_notes'))
                            ->where($db->quoteName($notesOrderCol) . ' = ' . $db->quote($item->orden_de_trabajo))
                            ->where($db->quoteName('state') . ' = 1');
                        $db->setQuery($query);
                        $item->notes_count = (int) $db->loadResult();
                    }
                } catch (\Throwable $e) {
                    // Table or columns missing
                }

                if (!empty($item->fecha_de_entrega)) {
                    $item->fecha_de_entrega_formatted = Factory::getDate($item->fecha_de_entrega)->format('d/m/Y');
                } else {
                    $item->fecha_de_entrega_formatted = '';
                }
                if (!empty($item->created)) {
                    $item->created_formatted = Factory::getDate($item->created)->format('d/m/Y H:i');
                } else {
                    $item->created_formatted = '';
                }
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
