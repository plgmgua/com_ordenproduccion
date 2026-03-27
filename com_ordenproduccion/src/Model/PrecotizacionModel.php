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

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * Pre-Cotizaci?n model: list (user-scoped), single item, lines, add line, next number.
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

        $defaultLimit = (int) $app->get('list_limit');
        if ($defaultLimit < 5) {
            $defaultLimit = 20;
        }
        $limit = $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', $defaultLimit, 'uint');
        if ($limit < 1) {
            $limit = 20;
        }
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

        $user = Factory::getUser();
        $isAdministracion = AccessHelper::isInAdministracionOrAdmonGroup() || $user->authorise('core.admin');

        $cols = ['a.id', 'a.number', 'a.created_by', 'a.created', 'a.modified', 'a.state'];
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        if (is_array($tableCols) && array_key_exists('descripcion', array_change_key_case($tableCols, CASE_LOWER))) {
            $cols[] = 'a.descripcion';
        }
        if (is_array($tableCols) && array_key_exists('oferta', array_change_key_case($tableCols, CASE_LOWER))) {
            $cols[] = 'a.oferta';
        }
        if ($isAdministracion) {
            $cols[] = 'u.name AS created_by_name';
        }
        $query->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
            ->where($db->quoteName('a.state') . ' = 1');

        if ($isAdministracion) {
            $query->leftJoin(
                $db->quoteName('#__users', 'u') . ' ON u.id = a.created_by'
            );
        } else {
            $query->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id);
        }

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
     * Get one Pre-Cotizaci?n by id (only if owned by current user).
     *
     * @param   int  $id  Pre-Cotizaci?n id.
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
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (isset($tableCols['descripcion'])) {
            $cols[] = 'a.descripcion';
        }
        if (isset($tableCols['facturar'])) {
            $cols[] = 'a.facturar';
        }
        if (isset($tableCols['oferta'])) {
            $cols[] = 'a.oferta';
        }
        if (isset($tableCols['oferta_expires'])) {
            $cols[] = 'a.oferta_expires';
        }
        foreach (['lines_subtotal', 'margen_amount', 'iva_amount', 'isr_amount', 'comision_amount', 'total', 'total_final', 'margen_adicional', 'comision_margen_adicional'] as $snapCol) {
            if (isset($tableCols[$snapCol])) {
                $cols[] = 'a.' . $snapCol;
            }
        }
        foreach (['tarjeta_credito_cuotas', 'tarjeta_credito_tasa', 'tarjeta_credito_monto', 'total_con_tarjeta'] as $tcCol) {
            if (isset($tableCols[$tcCol])) {
                $cols[] = 'a.' . $tcCol;
            }
        }
        $query = $db->getQuery(true)
            ->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
            ->where($db->quoteName('a.id') . ' = ' . $id)
            ->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id);

        $db->setQuery($query);
        $item = $db->loadObject();
        if ($item && !isset($item->facturar)) {
            $item->facturar = 0;
        }
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
     * Check if this pre-cotizaci?n is used in any quotation (quotation_items.pre_cotizacion_id).
     * When true, the pre-cotizaci?n must not be modified or deleted.
     *
     * @param   int  $preCotizacionId  Pre-Cotizaci?n id.
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
     * Get pre-cotizaciones for the quotation line selector: current user's + all with oferta=1.
     * Used so "Oferta" pre-cotizaciones can be selected by any user even if already in another quotation.
     *
     * @return  \stdClass[]  List of objects with id, number, total, descripcion, oferta
     * @since   3.95.0
     */
    public function getItemsForQuotationLineSelector()
    {
        $db   = $this->getDatabase();
        $user = Factory::getUser();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['oferta'])) {
            return $this->getItems() ?: [];
        }
        $cols = ['a.id', 'a.number', 'a.descripcion', 'a.oferta', 'a.created_by'];
        if (isset($tableCols['total'])) {
            $cols[] = 'a.total AS total_snapshot';
        }
        $query = $db->getQuery(true)
            ->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where('(' . $db->quoteName('a.created_by') . ' = ' . (int) $user->id . ' OR ' . $db->quoteName('a.oferta') . ' = 1)')
            ->order($db->quoteName('a.id') . ' DESC');
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        $list = [];
        foreach ($rows ?: [] as $row) {
            $total = isset($row->total_snapshot) && $row->total_snapshot !== null && $row->total_snapshot !== ''
                ? round((float) $row->total_snapshot, 2)
                : $this->getTotalForPreCotizacion((int) $row->id);
            $list[] = (object) [
                'id'          => (int) $row->id,
                'number'      => $row->number ?? ('PRE-' . $row->id),
                'total'       => $total,
                'descripcion' => isset($row->descripcion) ? trim((string) $row->descripcion) : '',
                'oferta'      => !empty($row->oferta),
            ];
        }
        return $list;
    }

    /**
     * Get total amount for a Pre-Cotizaci?n: subtotal (lines excluding envio) + params (Margen, IVA, ISR, Comisi?n).
     * When facturar=1, IVA and ISR are included; when 0, they are excluded.
     *
     * @param   int  $preCotizacionId  Pre-Cotizaci?n id.
     * @return  float
     * @since   3.74.0
     */
    public function getTotalForPreCotizacion($preCotizacionId)
    {
        $item = $this->getItem((int) $preCotizacionId);
        if ($item && isset($item->total) && $item->total !== null && $item->total !== '') {
            return round((float) $item->total, 2);
        }
        $lines = $this->getLines((int) $preCotizacionId);
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
            if ($lineType !== 'envio') {
                $subtotal += (float) ($line->total ?? 0);
            }
        }
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $margen = (float) $params->get('margen_ganancia', 0);
        $iva = (float) $params->get('iva', 0);
        $isr = (float) $params->get('isr', 0);
        $comision = (float) $params->get('comision_venta', 0);
        $facturar = $item && !empty($item->facturar);
        if ($facturar) {
            $total = $subtotal + $subtotal * ($margen + $iva + $isr + $comision) / 100;
        } else {
            $total = $subtotal + $subtotal * ($margen + $comision) / 100;
        }
        return round($total, 2);
    }

    /**
     * Refresh and save the totals snapshot on the pre_cotizacion (lines_subtotal, margen_amount, iva_amount, isr_amount, comision_amount, total, total_final).
     * Call after addLine, updateLine, deleteLine, or saveFacturar so stored totals stay in sync and remain historical if prices change later.
     *
     * @param   int  $preCotizacionId  Pre-Cotizaci?n id.
     * @return  bool  True if snapshot columns exist and were updated.
     * @since   3.86.0
     */
    public function refreshPreCotizacionTotalsSnapshot($preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return false;
        }

        $db = $this->getDatabase();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['lines_subtotal']) || !isset($tableCols['total'])) {
            return false;
        }

        $lines = $this->getLines($preCotizacionId);
        $linesSubtotal = 0.0;
        foreach ($lines as $line) {
            $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
            if ($lineType !== 'envio') {
                $linesSubtotal += (float) ($line->total ?? 0);
            }
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $paramMargen = (float) $params->get('margen_ganancia', 0);
        $paramIva = (float) $params->get('iva', 0);
        $paramIsr = (float) $params->get('isr', 0);
        $paramComision = (float) $params->get('comision_venta', 0);
        $item = $this->getItem($preCotizacionId);
        $facturar = $item && !empty($item->facturar);

        $margenAmount = round($linesSubtotal * ($paramMargen / 100), 2);
        $ivaAmount = $facturar ? round($linesSubtotal * ($paramIva / 100), 2) : 0.0;
        $isrAmount = $facturar ? round($linesSubtotal * ($paramIsr / 100), 2) : 0.0;
        $comisionAmount = round($linesSubtotal * ($paramComision / 100), 2);
        $total = round($linesSubtotal + $margenAmount + $ivaAmount + $isrAmount + $comisionAmount, 2);

        $obj = (object) [
            'id' => $preCotizacionId,
            'lines_subtotal' => $linesSubtotal,
            'margen_amount' => $margenAmount,
            'iva_amount' => $ivaAmount,
            'isr_amount' => $isrAmount,
            'comision_amount' => $comisionAmount,
            'total' => $total,
        ];
        if (isset($tableCols['total_final'])) {
            $obj->total_final = $total;
        }

        try {
            $db->updateObject('#__ordenproduccion_pre_cotizacion', $obj, 'id');
        } catch (\Exception $e) {
            return false;
        }

        // Tarjeta de credito: % sobre total con impuestos/comisiones + margen adicional (igual que total en document.php).
        if (isset($tableCols['tarjeta_credito_cuotas']) && isset($tableCols['tarjeta_credito_monto']) && isset($tableCols['total_con_tarjeta'])) {
            $productosModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()->createModel('Productos', 'Site', ['ignore_request' => true]);
            $cuotasTc = isset($item->tarjeta_credito_cuotas) && $item->tarjeta_credito_cuotas !== null && $item->tarjeta_credito_cuotas !== ''
                ? (int) $item->tarjeta_credito_cuotas
                : 0;
            $margenAdic = isset($tableCols['margen_adicional']) && isset($item->margen_adicional) && $item->margen_adicional !== null && $item->margen_adicional !== ''
                ? (float) $item->margen_adicional
                : 0.0;
            $baseSinTarjeta = round((float) $total + $margenAdic, 2);
            $qTc = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . $preCotizacionId);
            if ($cuotasTc > 0 && $productosModel->tarjetaCreditoTableExists()) {
                $tasaTc = $productosModel->getTarjetaCreditoTasaForCuotas($cuotasTc);
                if ($tasaTc !== null) {
                    $montoTc = round($baseSinTarjeta * ($tasaTc / 100.0), 2);
                    $totalConTc = round($baseSinTarjeta + $montoTc, 2);
                    if (isset($tableCols['tarjeta_credito_tasa'])) {
                        $qTc->set($db->quoteName('tarjeta_credito_tasa') . ' = ' . $db->quote($tasaTc));
                    }
                    $qTc->set($db->quoteName('tarjeta_credito_monto') . ' = ' . $db->quote($montoTc));
                    $qTc->set($db->quoteName('total_con_tarjeta') . ' = ' . $db->quote($totalConTc));
                } else {
                    if (isset($tableCols['tarjeta_credito_tasa'])) {
                        $qTc->set($db->quoteName('tarjeta_credito_tasa') . ' = NULL');
                    }
                    $qTc->set($db->quoteName('tarjeta_credito_monto') . ' = NULL');
                    $qTc->set($db->quoteName('total_con_tarjeta') . ' = NULL');
                }
            } else {
                if (isset($tableCols['tarjeta_credito_tasa'])) {
                    $qTc->set($db->quoteName('tarjeta_credito_tasa') . ' = NULL');
                }
                $qTc->set($db->quoteName('tarjeta_credito_monto') . ' = NULL');
                $qTc->set($db->quoteName('total_con_tarjeta') . ' = NULL');
            }
            try {
                $db->setQuery($qTc);
                $db->execute();
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set plazo tarjeta de credito (cuotas) and refresh montos. Null/0 clears selection.
     *
     * @return  bool
     * @since   3.101.0
     */
    public function saveTarjetaCreditoCuotas(int $preCotizacionId, ?int $cuotas)
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
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['tarjeta_credito_cuotas'])) {
            return false;
        }
        $now = Factory::getDate()->toSql();
        $uid = (int) Factory::getUser()->id;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_pre_cotizacion'));
        if ($cuotas !== null && $cuotas > 0) {
            $query->set($db->quoteName('tarjeta_credito_cuotas') . ' = ' . (int) $cuotas);
        } else {
            $query->set($db->quoteName('tarjeta_credito_cuotas') . ' = NULL');
        }
        if (isset($tableCols['modified'])) {
            $query->set($db->quoteName('modified') . ' = ' . $db->quote($now));
        }
        if (isset($tableCols['modified_by'])) {
            $query->set($db->quoteName('modified_by') . ' = ' . $uid);
        }
        $query->where($db->quoteName('id') . ' = ' . $preCotizacionId);
        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            return false;
        }

        return $this->refreshPreCotizacionTotalsSnapshot($preCotizacionId);
    }

    /**
     * Get lines for a Pre-Cotizaci?n (only if document is owned by current user).
     *
     * @param   int  $preCotizacionId  Pre-Cotizaci?n id.
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
        $hasEnvioCols = isset($columns['line_type']) && isset($columns['envio_id']);
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
            if ($hasEnvioCols) {
                $r = array_change_key_case((array) $row, CASE_LOWER);
                $lineType = $r['line_type'] ?? 'pliego';
                if ($lineType === 'envio' && !empty($r['envio_id'])) {
                    $envioId = (int) $r['envio_id'];
                    if ($productosModel === null) {
                        $productosModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                            ->getMVCFactory()->createModel('Productos', 'Site', ['ignore_request' => true]);
                    }
                    if ($productosModel->enviosTableExists()) {
                        $envio = $productosModel->getEnvio($envioId);
                        $row->envio_name = $envio ? $envio->name : '';
                    } else {
                        $row->envio_name = '';
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * Get one line by id (only if its Pre-Cotizaci?n is owned by current user).
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
     * Update a line (only if its Pre-Cotizaci?n is owned by current user).
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
        $columns = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $columns = is_array($columns) ? array_change_key_case($columns, CASE_LOWER) : [];
        if (isset($columns['tipo_elemento'])) {
            $obj->tipo_elemento = trim((string) ($data['tipo_elemento'] ?? $line->tipo_elemento ?? ''));
            if ($obj->tipo_elemento === '') {
                $obj->tipo_elemento = null;
            }
        }

        try {
            $db->updateObject('#__ordenproduccion_pre_cotizacion_line', $obj, 'id');
            $preCotizacionId = (int) $line->pre_cotizacion_id;
            if ($preCotizacionId > 0) {
                $this->refreshPreCotizacionTotalsSnapshot($preCotizacionId);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new Pre-Cotizaci?n (assign next number, set created_by to current user).
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
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        if (is_array($tableCols) && array_key_exists('facturar', array_change_key_case($tableCols, CASE_LOWER))) {
            $data->facturar = 1;
        }

        try {
            $db->insertObject('#__ordenproduccion_pre_cotizacion', $data, 'id');
            return (int) $data->id;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get pre-cotizaciones marked as template (oferta=1) for "Nueva Pre-Cotizaci?n" template selector.
     *
     * @return  \stdClass[]  List with id, number, descripcion, oferta_expires
     * @since   3.95.0
     */
    public function getTemplates()
    {
        $db = $this->getDatabase();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['oferta'])) {
            return [];
        }
        $cols = [$db->quoteName('id'), $db->quoteName('number'), $db->quoteName('descripcion')];
        if (isset($tableCols['oferta_expires'])) {
            $cols[] = $db->quoteName('oferta_expires');
        }
        $query = $db->getQuery(true)
            ->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
            ->where($db->quoteName('oferta') . ' = 1')
            ->where($db->quoteName('state') . ' = 1');
        if (isset($tableCols['oferta_expires'])) {
            $query->where('(' . $db->quoteName('oferta_expires') . ' IS NULL OR ' . $db->quoteName('oferta_expires') . ' >= CURDATE())');
        }
        $query->order($db->quoteName('number') . ' ASC');
        $db->setQuery($query);
        $list = $db->loadObjectList() ?: [];
        return $list;
    }

    /**
     * Create a new Pre-Cotizaci?n by copying a template (oferta=1). Copies header and all lines.
     * New pre-cotizaci?n has current user as created_by and oferta=0.
     *
     * @param   int  $templateId  Template pre-cotizaci?n id (must have oferta=1).
     * @return  int|false  New pre-cotizaci?n id on success, false on failure.
     * @since   3.95.0
     */
    public function createFromTemplate($templateId)
    {
        $templateId = (int) $templateId;
        if ($templateId < 1) {
            return false;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            return false;
        }
        $db = $this->getDatabase();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['oferta'])) {
            return false;
        }
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
            ->where($db->quoteName('id') . ' = ' . $templateId)
            ->where($db->quoteName('oferta') . ' = 1')
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $template = $db->loadObject();
        if (!$template) {
            return false;
        }
        $newNumber = $this->getNextNumber();
        $newRow = (object) [
            'number'      => $newNumber,
            'created_by'  => (int) $user->id,
            'state'       => 1,
            'descripcion' => isset($template->descripcion) ? (string) $template->descripcion : '',
            'oferta'      => 0,
        ];
        if (isset($tableCols['facturar'])) {
            $newRow->facturar = isset($template->facturar) ? (int) $template->facturar : 1;
        }
        try {
            $db->insertObject('#__ordenproduccion_pre_cotizacion', $newRow, 'id');
        } catch (\Exception $e) {
            return false;
        }
        $newId = (int) $newRow->id;
        $lineCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $lineCols = is_array($lineCols) ? array_keys(array_change_key_case($lineCols, CASE_LOWER)) : [];
        $copyCols = array_values(array_diff($lineCols, ['id', 'pre_cotizacion_id']));
        $selectList = implode(', ', array_map(function ($c) use ($db) {
            return $db->quoteName($c);
        }, $copyCols));
        $query = $db->getQuery(true)
            ->select($selectList)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
            ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $templateId)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);
        $lines = $db->loadObjectList() ?: [];
        foreach ($lines as $line) {
            $newLine = (object) ['pre_cotizacion_id' => $newId];
            foreach ($copyCols as $col) {
                $newLine->{$col} = $line->{$col};
            }
            $db->insertObject('#__ordenproduccion_pre_cotizacion_line', $newLine, 'id');
        }
        $this->refreshPreCotizacionTotalsSnapshot($newId);
        return $newId;
    }

    /**
     * Add a line to a Pre-Cotizaci?n (only if document is owned by current user).
     *
     * @param   int    $preCotizacionId  Pre-Cotizaci?n id.
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
        if (isset($data['line_type']) && $data['line_type'] === 'envio') {
            $lineType = 'envio';
            $elementoId = null;
        }

        $totalLine = (float) ($data['total'] ?? 0);
        if ($lineType === 'envio') {
            $envioId = (int) ($data['envio_id'] ?? 0);
            if ($envioId > 0) {
                $productosModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()->createModel('Productos', 'Site', ['ignore_request' => true]);
                $envio = $productosModel->enviosTableExists() ? $productosModel->getEnvio($envioId) : null;
                if ($envio) {
                    $tipoEnvio = isset($envio->tipo) ? (string) $envio->tipo : 'fixed';
                    if ($tipoEnvio === 'custom') {
                        $totalLine = (float) ($data['envio_valor'] ?? 0);
                    } else {
                        $totalLine = (float) ($envio->valor ?? 0);
                    }
                }
            }
        }
        $processIds = isset($data['process_ids']) && is_array($data['process_ids'])
            ? json_encode(array_values(array_map('intval', $data['process_ids'])))
            : '[]';
        $breakdown = $lineType !== 'envio' && isset($data['calculation_breakdown']) && is_array($data['calculation_breakdown'])
            ? json_encode($data['calculation_breakdown'])
            : null;

        $line = (object) [
            'pre_cotizacion_id'       => $preCotizacionId,
            'quantity'               => $lineType === 'envio' ? 1 : (int) ($data['quantity'] ?? 1),
            'paper_type_id'          => $lineType === 'pliego' ? (int) ($data['paper_type_id'] ?? 0) : 0,
            'size_id'                => $lineType === 'pliego' ? (int) ($data['size_id'] ?? 0) : 0,
            'tiro_retiro'            => $lineType === 'pliego' ? (($data['tiro_retiro'] ?? 'tiro') === 'retiro' ? 'retiro' : 'tiro') : 'tiro',
            'lamination_type_id'     => $lineType === 'pliego' && isset($data['lamination_type_id']) ? (int) $data['lamination_type_id'] : null,
            'lamination_tiro_retiro' => $lineType === 'pliego' && isset($data['lamination_tiro_retiro']) && $data['lamination_tiro_retiro'] === 'retiro' ? 'retiro' : 'tiro',
            'process_ids'            => $lineType === 'pliego' ? $processIds : '[]',
            'price_per_sheet'        => $lineType === 'envio' ? $totalLine : (float) ($data['price_per_sheet'] ?? 0),
            'total'                  => $totalLine,
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
        if (isset($columns['envio_id']) && $lineType === 'envio') {
            $line->envio_id = (int) ($data['envio_id'] ?? 0) ?: null;
            $line->envio_valor = isset($data['envio_valor']) ? (float) $data['envio_valor'] : null;
        }
        if (isset($columns['tipo_elemento'])) {
            $line->tipo_elemento = trim((string) ($data['tipo_elemento'] ?? ''));
            if ($line->tipo_elemento === '') {
                $line->tipo_elemento = null;
            }
        }

        try {
            $db->insertObject('#__ordenproduccion_pre_cotizacion_line', $line, 'id');
            $this->refreshPreCotizacionTotalsSnapshot($preCotizacionId);
            return (int) $line->id;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a Pre-Cotizaci?n (only if owned by current user). Lines are deleted by application logic or CASCADE.
     *
     * @param   int  $id  Pre-Cotizaci?n id.
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
     * Delete a single line (only if its Pre-Cotizaci?n is owned by current user).
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

        $this->refreshPreCotizacionTotalsSnapshot($preCotizacionId);
        return true;
    }

    /**
     * Get concepts (element labels) that require "Detalles" for a given line.
     * Pliego: one per breakdown row (label). Elementos/Env?o: one single "Detalles" input per line (each is a single element).
     *
     * @param   \stdClass  $line  Line object (breakdown, line_type)
     * @return  array  List of [concepto_key => concepto_label]
     * @since   3.91.0
     */
    public function getConceptsForLine($line)
    {
        $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
        if ($lineType === 'envio') {
            return ['detalle_envio' => 'Detalles env?o'];
        }
        if ($lineType === 'elementos') {
            return ['detalle' => 'Detalles'];
        }
        $concepts = [];
        $breakdown = isset($line->breakdown) && is_array($line->breakdown) ? $line->breakdown : [];
        foreach ($breakdown as $row) {
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            if ($label === '') {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($label));
            $key = trim(preg_replace('/_+/', '_', $key), '_');
            if ($key === '') {
                $key = 'concepto_' . count($concepts);
            }
            $concepts[$key] = $label;
        }
        return $concepts;
    }

    /**
     * Get saved Detalles for multiple lines (for instrucciones form).
     *
     * @param   int[]  $lineIds  Pre-cotizacion line ids
     * @return  array  [line_id => [concepto_key => detalle]]
     * @since   3.91.0
     */
    public function getDetallesForLines(array $lineIds)
    {
        if (empty($lineIds)) {
            return [];
        }
        $db = $this->getDatabase();
        $tbl = $db->quoteName('#__ordenproduccion_pre_cotizacion_line_detalles');
        try {
            $cols = $db->getTableColumns($db->replacePrefix('#__ordenproduccion_pre_cotizacion_line_detalles'), false);
        } catch (\Exception $e) {
            return [];
        }
        if (empty($cols)) {
            return [];
        }
        $lineIds = array_map('intval', $lineIds);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['pre_cotizacion_line_id', 'concepto_key', 'detalle']))
            ->from($tbl)
            ->whereIn($db->quoteName('pre_cotizacion_line_id'), $lineIds);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $out = [];
        foreach ($lineIds as $lid) {
            $out[$lid] = [];
        }
        foreach ($rows as $r) {
            $lid = (int) $r->pre_cotizacion_line_id;
            if (isset($out[$lid])) {
                $out[$lid][$r->concepto_key] = $r->detalle ?? '';
            }
        }
        return $out;
    }

    /**
     * Save Detalles for one line. Replaces all existing detalles for that line with the given map.
     *
     * @param   int    $lineId                Pre-cotizacion line id
     * @param   array  $conceptoKeyToDetalle  [concepto_key => detalle]; keys must match concepto_label for insert
     * @param   array  $conceptoKeyToLabel    [concepto_key => concepto_label] for new rows
     * @return  bool
     * @since   3.91.0
     */
    public function saveLineDetalles($lineId, array $conceptoKeyToDetalle, array $conceptoKeyToLabel = [])
    {
        $lineId = (int) $lineId;
        if ($lineId < 1) {
            return false;
        }
        $db = $this->getDatabase();
        $tbl = '#__ordenproduccion_pre_cotizacion_line_detalles';
        try {
            $cols = $db->getTableColumns($db->replacePrefix($tbl), false);
        } catch (\Exception $e) {
            return false;
        }
        if (empty($cols)) {
            return false;
        }
        $db->setQuery($db->getQuery(true)->delete($db->quoteName($tbl))->where($db->quoteName('pre_cotizacion_line_id') . ' = ' . $lineId))->execute();
        foreach ($conceptoKeyToDetalle as $key => $detalle) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $label = isset($conceptoKeyToLabel[$key]) ? trim((string) $conceptoKeyToLabel[$key]) : $key;
            $obj = (object) [
                'pre_cotizacion_line_id' => $lineId,
                'concepto_key'           => $key,
                'concepto_label'         => $label,
                'detalle'                => is_string($detalle) ? $detalle : '',
            ];
            $db->insertObject($tbl, $obj);
        }
        return true;
    }

    /**
     * Check if the line_detalles table exists.
     *
     * @return  bool
     * @since   3.91.0
     */
    public function lineDetallesTableExists()
    {
        $db = $this->getDatabase();
        try {
            $name = $db->replacePrefix('#__ordenproduccion_pre_cotizacion_line_detalles');
            $cols = $db->getTableColumns($name, false);
            return !empty($cols);
        } catch (\Exception $e) {
            return false;
        }
    }
}
