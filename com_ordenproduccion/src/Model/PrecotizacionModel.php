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

/**
 * Pre-Cotización model: list (user-scoped), single item, lines, add line, next number.
 *
 * @since  3.70.0
 */
class PrecotizacionModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   3.70.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'number', 'created', 'created_by'];
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
     * @since   3.70.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
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
     * @since   3.70.0
     */
    protected function populateState($ordering = 'id', $direction = 'desc')
    {
        $app = Factory::getApplication();

        $params = $app->getParams();
        $this->setState('params', $params);

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

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

        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);
    }

    /**
     * Build list query (only current user's Pre-Cotizaciones).
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   3.70.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $cols = ['a.id', 'a.number', 'a.created_by', 'a.created', 'a.modified', 'a.state'];
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        if (is_array($tableCols) && array_key_exists('descripcion', array_change_key_case($tableCols, CASE_LOWER))) {
            $cols[] = 'a.descripcion';
        }
        $query->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
            ->where($db->quoteName('a.state') . ' = 1');

        $user = Factory::getUser();
        $query->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id);

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(' . $db->quoteName('a.number') . ' LIKE ' . $search . ')');
            }
        }

        $orderCol = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'desc');
        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));
        }

        return $query;
    }

    /**
     * Get one Pre-Cotización by id (only if owned by current user).
     *
     * @param   int  $id  Pre-Cotización id.
     *
     * @return  \stdClass|null
     *
     * @since   3.70.0
     */
    public function getItem($id)
    {
        $id = (int) $id;
        if ($id < 1) {
            return null;
        }

        $user = Factory::getUser();
        $db   = $this->getDatabase();
        $cols = ['a.id', 'a.number', 'a.created_by', 'a.created', 'a.modified', 'a.state'];
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        if (is_array($tableCols) && array_key_exists('descripcion', array_change_key_case($tableCols, CASE_LOWER))) {
            $cols[] = 'a.descripcion';
        }
        $query = $db->getQuery(true)
            ->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
            ->where($db->quoteName('a.id') . ' = ' . $id)
            ->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id);

        $db->setQuery($query);
        $item = $db->loadObject();
        return $item ?: null;
    }

    /**
     * Get next global number in format PRE-00001.
     *
     * @return  string
     *
     * @since   3.70.0
     */
    public function getNextNumber()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('MAX(CAST(SUBSTRING(' . $db->quoteName('number') . ', 5) AS UNSIGNED))')
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
            ->where($db->quoteName('number') . ' LIKE ' . $db->quote('PRE-%'));

        $db->setQuery($query);
        $max = (int) $db->loadResult();
        $next = $max + 1;
        return 'PRE-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check if this pre-cotización is used in any quotation (quotation_items.pre_cotizacion_id).
     * When true, the pre-cotización must not be modified or deleted.
     *
     * @param   int  $preCotizacionId  Pre-Cotización id.
     * @return  bool
     * @since   3.75.0
     */
    public function isAssociatedWithQuotation($preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return false;
        }
        $db = $this->getDatabase();
        $cols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['pre_cotizacion_id'])) {
            return false;
        }
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $preCotizacionId)
            ->setLimit(1);
        $db->setQuery($query);
        return (bool) $db->loadResult();
    }

    /**
     * Get total amount for a Pre-Cotización (sum of all line totals).
     *
     * @param   int  $preCotizacionId  Pre-Cotización id.
     * @return  float
     * @since   3.74.0
     */
    public function getTotalForPreCotizacion($preCotizacionId)
    {
        $lines = $this->getLines((int) $preCotizacionId);
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float) ($line->total ?? 0);
        }
        return $total;
    }

    /**
     * Get lines for a Pre-Cotización (only if document is owned by current user).
     *
     * @param   int  $preCotizacionId  Pre-Cotización id.
     *
     * @return  \stdClass[]
     *
     * @since   3.70.0
     */
    public function getLines($preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return [];
        }

        $item = $this->getItem($preCotizacionId);
        if (!$item) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('l.*')
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line', 'l'))
            ->where($db->quoteName('l.pre_cotizacion_id') . ' = ' . $preCotizacionId)
            ->order($db->quoteName('l.ordering') . ' ASC, ' . $db->quoteName('l.id') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $columns = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        $hasElementoCols = isset($columns['line_type']) && isset($columns['elemento_id']);
        $productosModel = null;

        foreach ($rows as $row) {
            if (!empty($row->process_ids)) {
                $row->process_ids_array = json_decode($row->process_ids, true);
                if (!is_array($row->process_ids_array)) {
                    $row->process_ids_array = [];
                }
            } else {
                $row->process_ids_array = [];
            }
            if (!empty($row->calculation_breakdown)) {
                $row->breakdown = json_decode($row->calculation_breakdown, true);
                if (!is_array($row->breakdown)) {
                    $row->breakdown = [];
                }
            } else {
                $row->breakdown = [];
            }
            if ($hasElementoCols) {
                $r = array_change_key_case((array) $row, CASE_LOWER);
                $lineType = $r['line_type'] ?? 'pliego';
                $elementoId = isset($r['elemento_id']) ? (int) $r['elemento_id'] : 0;
                if ($lineType === 'elementos' && $elementoId > 0) {
                    $qty = (int) ($r['quantity'] ?? 1);
                    $storedTotal = (float) ($r['total'] ?? 0);
                    if ($qty > 0 && $storedTotal <= 0) {
                        if ($productosModel === null) {
                            $productosModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                                ->getMVCFactory()->createModel('Productos', 'Site', ['ignore_request' => true]);
                        }
                        if ($productosModel->elementosTableExists()) {
                            $unitPrice = $productosModel->getElementoUnitPrice($elementoId, $qty);
                            $recalc = $qty * $unitPrice;
                            if ($recalc > 0) {
                                $row->total = $recalc;
                                $row->price_per_sheet = $unitPrice;
                                $updateLine = (object) [
                                    'id' => (int) $row->id,
                                    'total' => $recalc,
                                    'price_per_sheet' => $unitPrice,
                                ];
                                $db->updateObject('#__ordenproduccion_pre_cotizacion_line', $updateLine, 'id');
                            }
                        }
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * Get one line by id (only if its Pre-Cotización is owned by current user).
     *
     * @param   int  $lineId  Line id.
     *
     * @return  \stdClass|null
     *
     * @since   3.70.0
     */
    public function getLine($lineId)
    {
        $lineId = (int) $lineId;
        if ($lineId < 1) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('l.*')
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line', 'l'))
            ->where($db->quoteName('l.id') . ' = ' . $lineId);
        $db->setQuery($query);
        $line = $db->loadObject();
        if (!$line) {
            return null;
        }

        $item = $this->getItem($line->pre_cotizacion_id);
        if (!$item) {
            return null;
        }

        $line->process_ids_array = [];
        if (!empty($line->process_ids)) {
            $decoded = json_decode($line->process_ids, true);
            if (is_array($decoded)) {
                $line->process_ids_array = $decoded;
            }
        }
        $line->breakdown = [];
        if (!empty($line->calculation_breakdown)) {
            $decoded = json_decode($line->calculation_breakdown, true);
            if (is_array($decoded)) {
                $line->breakdown = $decoded;
            }
        }
        return $line;
    }

    /**
     * Update a line (only if its Pre-Cotización is owned by current user).
     *
     * @param   int    $lineId  Line id.
     * @param   array  $data   Same structure as addLine.
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function updateLine($lineId, array $data)
    {
        $line = $this->getLine($lineId);
        if (!$line) {
            return false;
        }

        $db = $this->getDatabase();

        $processIds = isset($data['process_ids']) && is_array($data['process_ids'])
            ? json_encode(array_values(array_map('intval', $data['process_ids'])))
            : '[]';
        $breakdown = isset($data['calculation_breakdown']) && is_array($data['calculation_breakdown'])
            ? json_encode($data['calculation_breakdown'])
            : $line->calculation_breakdown;

        $obj = (object) [
            'id'                      => (int) $lineId,
            'quantity'                => (int) ($data['quantity'] ?? $line->quantity),
            'paper_type_id'           => (int) ($data['paper_type_id'] ?? $line->paper_type_id),
            'size_id'                 => (int) ($data['size_id'] ?? $line->size_id),
            'tiro_retiro'             => (isset($data['tiro_retiro']) && $data['tiro_retiro'] === 'retiro') ? 'retiro' : 'tiro',
            'lamination_type_id'      => isset($data['lamination_type_id']) ? (int) $data['lamination_type_id'] : null,
            'lamination_tiro_retiro'  => (isset($data['lamination_tiro_retiro']) && $data['lamination_tiro_retiro'] === 'retiro') ? 'retiro' : 'tiro',
            'process_ids'             => $processIds,
            'price_per_sheet'         => (float) ($data['price_per_sheet'] ?? $line->price_per_sheet),
            'total'                   => (float) ($data['total'] ?? $line->total),
            'calculation_breakdown'   => $breakdown,
        ];

        try {
            $db->updateObject('#__ordenproduccion_pre_cotizacion_line', $obj, 'id');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new Pre-Cotización (assign next number, set created_by to current user).
     *
     * @return  int|false  New id on success, false on failure.
     *
     * @since   3.70.0
     */
    public function create()
    {
        $user = Factory::getUser();
        if ($user->guest) {
            return false;
        }

        $number = $this->getNextNumber();
        $db     = $this->getDatabase();
        $data   = (object) [
            'number'     => $number,
            'created_by' => (int) $user->id,
            'state'      => 1,
        ];

        try {
            $db->insertObject('#__ordenproduccion_pre_cotizacion', $data, 'id');
            return (int) $data->id;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add a line to a Pre-Cotización (only if document is owned by current user).
     *
     * @param   int    $preCotizacionId  Pre-Cotización id.
     * @param   array  $data             Line data: quantity, paper_type_id, size_id, tiro_retiro,
     *                                   lamination_type_id, lamination_tiro_retiro, process_ids (array),
     *                                   price_per_sheet, total, calculation_breakdown (array of rows).
     *
     * @return  int|false  New line id on success, false on failure.
     *
     * @since   3.70.0
     */
    public function addLine($preCotizacionId, array $data)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return false;
        }

        $item = $this->getItem($preCotizacionId);
        if (!$item) {
            return false;
        }

        $db = $this->getDatabase();

        $ordering = (int) ($data['ordering'] ?? 0);
        if ($ordering < 1) {
            $q = $db->getQuery(true)
                ->select('COALESCE(MAX(ordering), 0) + 1')
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $preCotizacionId);
            $db->setQuery($q);
            $ordering = (int) $db->loadResult();
        }

        $lineType = (isset($data['line_type']) && $data['line_type'] === 'elementos') ? 'elementos' : 'pliego';
        $elementoId = $lineType === 'elementos' ? (int) ($data['elemento_id'] ?? 0) : null;

        $processIds = isset($data['process_ids']) && is_array($data['process_ids'])
            ? json_encode(array_values(array_map('intval', $data['process_ids'])))
            : '[]';
        $breakdown = isset($data['calculation_breakdown']) && is_array($data['calculation_breakdown'])
            ? json_encode($data['calculation_breakdown'])
            : null;

        $line = (object) [
            'pre_cotizacion_id'       => $preCotizacionId,
            'quantity'               => (int) ($data['quantity'] ?? 1),
            'paper_type_id'          => $lineType === 'pliego' ? (int) ($data['paper_type_id'] ?? 0) : 0,
            'size_id'                => $lineType === 'pliego' ? (int) ($data['size_id'] ?? 0) : 0,
            'tiro_retiro'            => $lineType === 'pliego' ? (($data['tiro_retiro'] ?? 'tiro') === 'retiro' ? 'retiro' : 'tiro') : 'tiro',
            'lamination_type_id'     => $lineType === 'pliego' && isset($data['lamination_type_id']) ? (int) $data['lamination_type_id'] : null,
            'lamination_tiro_retiro' => $lineType === 'pliego' && isset($data['lamination_tiro_retiro']) && $data['lamination_tiro_retiro'] === 'retiro' ? 'retiro' : 'tiro',
            'process_ids'            => $lineType === 'pliego' ? $processIds : '[]',
            'price_per_sheet'        => (float) ($data['price_per_sheet'] ?? 0),
            'total'                  => (float) ($data['total'] ?? 0),
            'calculation_breakdown'  => $breakdown,
            'ordering'               => $ordering,
        ];
        $db = $this->getDatabase();
        $columns = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        if (isset($columns['line_type'])) {
            $line->line_type = $lineType;
            $line->elemento_id = $elementoId > 0 ? $elementoId : null;
        }

        try {
            $db->insertObject('#__ordenproduccion_pre_cotizacion_line', $line, 'id');
            return (int) $line->id;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a Pre-Cotización (only if owned by current user). Lines are deleted by application logic or CASCADE.
     *
     * @param   int  $id  Pre-Cotización id.
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function delete($id)
    {
        $id = (int) $id;
        if ($id < 1) {
            return false;
        }

        $item = $this->getItem($id);
        if (!$item) {
            return false;
        }

        if ($this->isAssociatedWithQuotation($id)) {
            return false;
        }

        $db = $this->getDatabase();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $id)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . $id)
        )->execute();

        return true;
    }

    /**
     * Delete a single line (only if its Pre-Cotización is owned by current user).
     *
     * @param   int  $lineId  Line id.
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function deleteLine($lineId)
    {
        $lineId = (int) $lineId;
        if ($lineId < 1) {
            return false;
        }

        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->select('pre_cotizacion_id')
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('id') . ' = ' . $lineId)
        );
        $preCotizacionId = (int) $db->loadResult();
        if ($preCotizacionId < 1) {
            return false;
        }

        $item = $this->getItem($preCotizacionId);
        if (!$item) {
            return false;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('id') . ' = ' . $lineId)
        )->execute();

        return true;
    }
}
