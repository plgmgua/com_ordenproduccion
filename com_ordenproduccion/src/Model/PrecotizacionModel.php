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
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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
        foreach (['number', 'descripcion', 'oferta', 'facturar', 'created_from', 'created_to', 'created_by', 'has_cotizacion', 'quotation', 'client'] as $fk) {
            $id .= ':' . (string) $this->getState('filter.' . $fk, '');
        }

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

        if ((int) $app->input->get('filter_reset', 0) === 1) {
            foreach (['search', 'number', 'descripcion', 'oferta', 'facturar', 'created_from', 'created_to', 'has_cotizacion', 'quotation', 'client'] as $fk) {
                $app->setUserState($this->context . '.filter.' . $fk, '');
            }
            $app->setUserState($this->context . '.filter.created_by', 0);
        }

        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $this->setState(
            'filter.number',
            $app->getUserStateFromRequest($this->context . '.filter.number', 'filter_number', '', 'string')
        );
        $this->setState(
            'filter.descripcion',
            $app->getUserStateFromRequest($this->context . '.filter.descripcion', 'filter_descripcion', '', 'string')
        );
        $this->setState(
            'filter.oferta',
            $app->getUserStateFromRequest($this->context . '.filter.oferta', 'filter_oferta', '', 'string')
        );
        $this->setState(
            'filter.facturar',
            $app->getUserStateFromRequest($this->context . '.filter.facturar', 'filter_facturar', '', 'string')
        );
        $this->setState(
            'filter.created_from',
            $app->getUserStateFromRequest($this->context . '.filter.created_from', 'filter_created_from', '', 'string')
        );
        $this->setState(
            'filter.created_to',
            $app->getUserStateFromRequest($this->context . '.filter.created_to', 'filter_created_to', '', 'string')
        );
        $this->setState(
            'filter.created_by',
            $app->getUserStateFromRequest($this->context . '.filter.created_by', 'filter_created_by', 0, 'int')
        );
        $this->setState(
            'filter.has_cotizacion',
            $app->getUserStateFromRequest($this->context . '.filter.has_cotizacion', 'filter_has_cotizacion', '', 'string')
        );
        $this->setState(
            'filter.quotation',
            $app->getUserStateFromRequest($this->context . '.filter.quotation', 'filter_quotation', '', 'string')
        );
        $this->setState(
            'filter.client',
            $app->getUserStateFromRequest($this->context . '.filter.client', 'filter_client', '', 'string')
        );
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
        $tableColsLc = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (isset($tableColsLc['descripcion'])) {
            $cols[] = 'a.descripcion';
        }
        if (isset($tableColsLc['oferta'])) {
            $cols[] = 'a.oferta';
        }
        if (isset($tableColsLc['oferta_expires'])) {
            $cols[] = 'a.oferta_expires';
        }
        if (isset($tableColsLc['facturar'])) {
            $cols[] = 'a.facturar';
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
        } elseif (isset($tableColsLc['oferta'])) {
            $uid = (int) $user->id;
            $expClause = '';
            if (isset($tableColsLc['oferta_expires'])) {
                $expClause = ' AND (' . $db->quoteName('a.oferta_expires') . ' IS NULL OR ' . $db->quoteName('a.oferta_expires') . ' >= CURDATE())';
            }
            $query->where(
                '(' . $db->quoteName('a.created_by') . ' = ' . $uid
                . ' OR (' . $db->quoteName('a.oferta') . ' = 1' . $expClause . ')'
                . ')'
            );
        } else {
            $query->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id);
        }

        $numberFilter = trim((string) $this->getState('filter.number', ''));
        if ($numberFilter === '') {
            $numberFilter = trim((string) $this->getState('filter.search', ''));
        }
        if ($numberFilter !== '') {
            if (stripos($numberFilter, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($numberFilter, 3));
            } else {
                $like = $db->quote('%' . $db->escape($numberFilter, true) . '%');
                $query->where($db->quoteName('a.number') . ' LIKE ' . $like);
            }
        }

        $descFilter = trim((string) $this->getState('filter.descripcion', ''));
        if ($descFilter !== '' && isset($tableColsLc['descripcion'])) {
            $like = $db->quote('%' . $db->escape($descFilter, true) . '%');
            $query->where($db->quoteName('a.descripcion') . ' LIKE ' . $like);
        }

        $ofertaF = (string) $this->getState('filter.oferta', '');
        if ($ofertaF !== '' && $ofertaF !== '*' && isset($tableColsLc['oferta'])) {
            $query->where($db->quoteName('a.oferta') . ' = ' . (int) $ofertaF);
        }

        $facturarF = (string) $this->getState('filter.facturar', '');
        if ($facturarF !== '' && $facturarF !== '*' && isset($tableColsLc['facturar'])) {
            $query->where($db->quoteName('a.facturar') . ' = ' . (int) $facturarF);
        }

        $from = trim((string) $this->getState('filter.created_from', ''));
        if ($from !== '') {
            $query->where($db->quoteName('a.created') . ' >= ' . $db->quote($from . ' 00:00:00'));
        }
        $to = trim((string) $this->getState('filter.created_to', ''));
        if ($to !== '') {
            $query->where($db->quoteName('a.created') . ' <= ' . $db->quote($to . ' 23:59:59'));
        }

        $createdByF = (int) $this->getState('filter.created_by', 0);
        if ($isAdministracion && $createdByF > 0) {
            $query->where($db->quoteName('a.created_by') . ' = ' . $createdByF);
        }

        $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        $hasPreLink = isset($itemCols['pre_cotizacion_id']);
        $qCols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $qCols = is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];
        $hasClientName = isset($qCols['client_name']);

        $hasCotF = (string) $this->getState('filter.has_cotizacion', '');
        if ($hasPreLink && ($hasCotF === '1' || $hasCotF === '0')) {
            $qi = $db->quoteName('#__ordenproduccion_quotation_items', 'qi');
            if ($hasCotF === '1') {
                $query->where('EXISTS (SELECT 1 FROM ' . $qi . ' WHERE qi.pre_cotizacion_id = a.id)');
            } else {
                $query->where('NOT EXISTS (SELECT 1 FROM ' . $qi . ' WHERE qi.pre_cotizacion_id = a.id)');
            }
        }

        $quotF = trim((string) $this->getState('filter.quotation', ''));
        if ($quotF !== '' && $hasPreLink && isset($qCols['quotation_number'])) {
            $like = $db->quote('%' . $db->escape($quotF, true) . '%');
            $qi = $db->quoteName('#__ordenproduccion_quotation_items', 'qi');
            $qt = $db->quoteName('#__ordenproduccion_quotations', 'q');
            $query->where(
                'EXISTS (SELECT 1 FROM ' . $qi
                . ' INNER JOIN ' . $qt . ' ON ' . $db->quoteName('q.id') . ' = ' . $db->quoteName('qi.quotation_id')
                . ' AND ' . $db->quoteName('q.state') . ' = 1'
                . ' WHERE ' . $db->quoteName('qi.pre_cotizacion_id') . ' = a.id AND ' . $db->quoteName('q.quotation_number') . ' LIKE ' . $like . ')'
            );
        }

        $clientF = trim((string) $this->getState('filter.client', ''));
        if ($clientF !== '' && $hasPreLink && $hasClientName) {
            $like = $db->quote('%' . $db->escape($clientF, true) . '%');
            $qi = $db->quoteName('#__ordenproduccion_quotation_items', 'qi');
            $qt = $db->quoteName('#__ordenproduccion_quotations', 'q');
            $query->where(
                'EXISTS (SELECT 1 FROM ' . $qi
                . ' INNER JOIN ' . $qt . ' ON ' . $db->quoteName('q.id') . ' = ' . $db->quoteName('qi.quotation_id')
                . ' AND ' . $db->quoteName('q.state') . ' = 1'
                . ' WHERE ' . $db->quoteName('qi.pre_cotizacion_id') . ' = a.id AND ' . $db->quoteName('q.client_name') . ' LIKE ' . $like . ')'
            );
        }

        $orderCol = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'desc');
        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));
        }

        return $query;
    }

    /**
     * Get one Pre-Cotización by id if the current user may access it (same rules as the list query).
     *
     * Administración/Admon/super user: any published row. Others: own rows, or active offer templates
     * (oferta = 1, not expired) so Ventas can open others’ ofertas read-only.
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
        if ($user->guest) {
            return null;
        }
        $db   = $this->getDatabase();
        $cols = ['a.id', 'a.number', 'a.created_by', 'a.created', 'a.modified', 'a.state'];
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (isset($tableCols['descripcion'])) {
            $cols[] = 'a.descripcion';
        }
        if (isset($tableCols['medidas'])) {
            $cols[] = 'a.medidas';
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
            ->where($db->quoteName('a.state') . ' = 1');

        $isAdministracion = AccessHelper::isInAdministracionOrAdmonGroup() || $user->authorise('core.admin');

        if ($isAdministracion) {
            // Same as getListQuery: see all published rows.
        } elseif (isset($tableCols['oferta'])) {
            $uid = (int) $user->id;
            $expClause = '';
            if (isset($tableCols['oferta_expires'])) {
                $expClause = ' AND (' . $db->quoteName('a.oferta_expires') . ' IS NULL OR ' . $db->quoteName('a.oferta_expires') . ' >= CURDATE())';
            }
            $query->where(
                '(' . $db->quoteName('a.created_by') . ' = ' . $uid
                . ' OR (' . $db->quoteName('a.oferta') . ' = 1' . $expClause . ')'
                . ')'
            );
        } else {
            $query->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id);
        }

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
     * Pre-cotizaciones linked to this quotation's lines that have facturar = 1.
     *
     * @param   int  $quotationId  Quotation id.
     *
     * @return  array<int, array{id: int, number: string}>
     *
     * @since   3.101.44
     */
    public function getFacturarPreCotizacionesForQuotation(int $quotationId): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return [];
        }
        $db = $this->getDatabase();
        $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        if (!isset($itemCols['pre_cotizacion_id'])) {
            return [];
        }
        $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $pcCols = is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
        if (!isset($pcCols['facturar'])) {
            return [];
        }
        $q = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('p.id') . ', ' . $db->quoteName('p.number'))
            ->from($db->quoteName('#__ordenproduccion_quotation_items', 'i'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_pre_cotizacion', 'p'),
                $db->quoteName('p.id') . ' = ' . $db->quoteName('i.pre_cotizacion_id')
            )
            ->where($db->quoteName('i.quotation_id') . ' = ' . $quotationId)
            ->where($db->quoteName('i.pre_cotizacion_id') . ' > 0')
            ->where($db->quoteName('p.facturar') . ' = 1')
            ->order($db->quoteName('p.id') . ' ASC');
        $db->setQuery($q);
        $rows = $db->loadObjectList() ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            $num = trim((string) ($r->number ?? ''));
            if ($num === '') {
                $num = 'PRE-' . $id;
            }
            $out[] = ['id' => $id, 'number' => $num];
        }

        return $out;
    }

    /**
     * Whether an offer/template pre-cotización is past its expiration date (date-only comparison to today).
     *
     * @param   object|null  $item  Row with optional oferta, oferta_expires
     *
     * @return  bool  True if oferta=1 and oferta_expires is set and strictly before today
     *
     * @since   3.104.2
     */
    public static function isOfertaExpired($item)
    {
        if ($item === null || empty($item->oferta)) {
            return false;
        }
        if (!isset($item->oferta_expires) || $item->oferta_expires === null || $item->oferta_expires === '') {
            return false;
        }
        try {
            $exp = new \DateTimeImmutable(substr((string) $item->oferta_expires, 0, 10));
            $today = new \DateTimeImmutable('today');

            return $exp < $today;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Document editing: non–offer rows — owner or Administracion/Admon/super user. Offer rows — owner only.
     *
     * @param   int  $preCotizacionId  Published pre-cotización id
     *
     * @return  bool
     *
     * @since   3.104.2
     */
    public function canUserEditPreCotizacionDocument($preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return false;
        }
        $item = $this->getItem($preCotizacionId);
        if (!$item || (int) $item->state !== 1) {
            return false;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            return false;
        }
        $isOwner = (int) $item->created_by === (int) $user->id;
        $db      = $this->getDatabase();
        $cols    = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $colsLc  = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (isset($colsLc['oferta']) && !empty($item->oferta)) {
            return $isOwner;
        }

        return $isOwner
            || AccessHelper::isInAdministracionOrAdmonGroup()
            || $user->authorise('core.admin');
    }

    /**
     * Aprobaciones Ventas (group 16) may adjust Impresión subtotal on pliego lines when pre-cot is editable (not quoted).
     * Offer templates: owner only.
     *
     * @param   int  $preCotizacionId  Pre-cotización id
     *
     * @return  bool
     *
     * @since   3.109.19
     */
    public function canUserSaveImpresionOverrideOnPreCotizacion($preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return false;
        }
        $user = Factory::getUser();
        if ($user->guest || !AccessHelper::isInAprobacionesVentasGroup()) {
            return false;
        }
        if ($this->isAssociatedWithQuotation($preCotizacionId)) {
            return false;
        }
        $item = $this->getItem($preCotizacionId);
        if (!$item || (int) $item->state !== 1) {
            return false;
        }
        $db   = $this->getDatabase();
        $cols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['impresion_subtotal_base'])) {
            return false;
        }
        $colsPc = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $colsPc = is_array($colsPc) ? array_change_key_case($colsPc, CASE_LOWER) : [];
        if (isset($colsPc['oferta']) && !empty($item->oferta)) {
            return (int) $item->created_by === (int) $user->id;
        }

        return true;
    }

    /**
     * @param   int  $lineId  Line id
     *
     * @return  bool
     *
     * @since   3.109.19
     */
    public function canUserSaveImpresionOverrideOnLine($lineId)
    {
        $lineId = (int) $lineId;
        if ($lineId < 1) {
            return false;
        }
        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select($db->quoteName('pre_cotizacion_id'))
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
            ->where($db->quoteName('id') . ' = ' . $lineId);
        $db->setQuery($q);
        $pid = (int) $db->loadResult();

        return $pid > 0 && $this->canUserSaveImpresionOverrideOnPreCotizacion($pid);
    }

    /**
     * First breakdown row subtotal (Impresión) for pliego lines.
     *
     * @param   array|null  $breakdown  Decoded calculation_breakdown
     *
     * @return  float|null
     *
     * @since   3.109.19
     */
    protected function pliegoImpresionSubtotalFromBreakdown($breakdown)
    {
        if (!is_array($breakdown) || !isset($breakdown[0]) || !is_array($breakdown[0])) {
            return null;
        }
        if (!isset($breakdown[0]['subtotal'])) {
            return null;
        }
        $v = round((float) $breakdown[0]['subtotal'], 2);

        return $v > 0 ? $v : null;
    }

    /**
     * Save Impresión (first breakdown row) subtotal override: between 60% and 100% of stored base.
     *
     * @param   int    $lineId      Line id
     * @param   float  $newSubtotal New print row subtotal (line total part)
     *
     * @return  array{success:bool,message:string,total?:float,price_per_sheet?:float}
     *
     * @since   3.109.19
     */
    public function saveImpresionSubtotalOverride($lineId, $newSubtotal)
    {
        $lineId = (int) $lineId;
        if ($lineId < 1) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID')];
        }
        if (!$this->canUserSaveImpresionOverrideOnLine($lineId)) {
            return ['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')];
        }
        $line = $this->getLine($lineId);
        if (!$line) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID')];
        }
        $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
        if ($lineType !== 'pliego') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_NOT_PLIEGO')];
        }
        $breakdown = $line->breakdown ?? [];
        if (empty($breakdown[0]) || !is_array($breakdown[0])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_NO_BREAKDOWN')];
        }
        $db = $this->getDatabase();
        $cols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['impresion_subtotal_base'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_SCHEMA')];
        }

        $base = null;
        if (isset($line->impresion_subtotal_base) && $line->impresion_subtotal_base !== null && $line->impresion_subtotal_base !== '') {
            $base = round((float) $line->impresion_subtotal_base, 2);
        }
        if ($base === null || $base <= 0) {
            $base = $this->pliegoImpresionSubtotalFromBreakdown($breakdown);
        }
        if ($base === null || $base <= 0) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_NO_BASE')];
        }

        $minAllowed = round($base * 0.6, 2);
        $newSubtotal = round((float) $newSubtotal, 2);
        $eps          = 0.005;

        if ($newSubtotal < $minAllowed - $eps) {
            return [
                'success' => false,
                'message' => Text::sprintf('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_TOO_LOW', number_format($minAllowed, 2, '.', '')),
            ];
        }
        if ($newSubtotal > $base + $eps) {
            return [
                'success' => false,
                'message' => Text::sprintf('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_TOO_HIGH', number_format($base, 2, '.', '')),
            ];
        }

        $qty = max(1, (int) ($line->quantity ?? 1));
        $breakdown[0]['subtotal'] = $newSubtotal;
        $perSheet                 = $newSubtotal / $qty;
        $breakdown[0]['detail']   = 'Q ' . number_format($perSheet, 2);

        $otherSum = 0.0;
        $n        = count($breakdown);
        for ($i = 1; $i < $n; $i++) {
            if (isset($breakdown[$i]['subtotal'])) {
                $otherSum += (float) $breakdown[$i]['subtotal'];
            }
        }
        $lineTotal     = round($newSubtotal + $otherSum, 2);
        $pricePerSheet = round($lineTotal / $qty, 4);
        $overrideVal   = (abs($newSubtotal - $base) < $eps) ? null : $newSubtotal;

        $obj = (object) [
            'id'                        => $lineId,
            'calculation_breakdown'     => json_encode($breakdown),
            'total'                     => $lineTotal,
            'price_per_sheet'           => $pricePerSheet,
            'impresion_subtotal_base'   => $base,
            'impresion_subtotal_override' => $overrideVal,
        ];
        try {
            $db->updateObject('#__ordenproduccion_pre_cotizacion_line', $obj, 'id');
            $this->refreshPreCotizacionTotalsSnapshot((int) $line->pre_cotizacion_id);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_SAVE_ERROR')];
        }

        return [
            'success'         => true,
            'message'         => Text::_('COM_ORDENPRODUCCION_PRE_COT_IMPRESION_OVERRIDE_SAVED'),
            'total'           => $lineTotal,
            'price_per_sheet' => $pricePerSheet,
        ];
    }

    /**
     * Delete permission: offer rows — owner only; normal rows — owner or Administracion/Admon/super user.
     *
     * @param   int  $preCotizacionId  Pre-cotización id
     *
     * @return  bool
     *
     * @since   3.104.2
     */
    public function canUserDeletePreCotizacion($preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return false;
        }
        $item = $this->getItem($preCotizacionId);
        if (!$item) {
            return false;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            return false;
        }
        $isOwner = (int) $item->created_by === (int) $user->id;
        $db      = $this->getDatabase();
        $cols    = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $colsLc  = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (isset($colsLc['oferta']) && !empty($item->oferta)) {
            return $isOwner;
        }

        return $isOwner
            || AccessHelper::isInAdministracionOrAdmonGroup()
            || $user->authorise('core.admin');
    }

    /**
     * Validate pre-cotización ids used as quotation lines: must belong to current user, not be offer templates, published.
     *
     * @param   int[]  $preIds  Pre-cotización ids from lines
     *
     * @return  string|null  Language constant to translate, or null if ok
     *
     * @since   3.104.2
     */
    public function validatePreCotizacionIdsForQuotationLine(array $preIds)
    {
        $user = Factory::getUser();
        if ($user->guest) {
            return 'COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED';
        }
        $preIds = array_unique(array_filter(array_map('intval', $preIds)));
        if ($preIds === []) {
            return null;
        }
        $db     = $this->getDatabase();
        $cols   = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $colsLc = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        foreach ($preIds as $pid) {
            if ($pid < 1) {
                return 'COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID';
            }
            $select = [$db->quoteName('id'), $db->quoteName('created_by'), $db->quoteName('state')];
            if (isset($colsLc['oferta'])) {
                $select[] = $db->quoteName('oferta');
            }
            if (isset($colsLc['oferta_expires'])) {
                $select[] = $db->quoteName('oferta_expires');
            }
            $db->setQuery(
                $db->getQuery(true)
                    ->select($select)
                    ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                    ->where($db->quoteName('id') . ' = ' . $pid)
            );
            $row = $db->loadObject();
            if (!$row || (int) $row->state !== 1) {
                return 'COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID';
            }
            if (isset($colsLc['oferta']) && !empty($row->oferta)) {
                return 'COM_ORDENPRODUCCION_PRE_OFERTA_CANNOT_LINK_COTIZACION';
            }
            if ((int) $row->created_by !== (int) $user->id) {
                return 'COM_ORDENPRODUCCION_PRE_COT_LINE_OWNER_ONLY';
            }
        }

        return null;
    }


    /**
     * Get pre-cotizaciones for the quotation line selector: current user's non-offer rows only (offers cannot be linked to a cotización).
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
        if (isset($tableCols['total_con_tarjeta'])) {
            $cols[] = 'a.total_con_tarjeta AS total_con_tarjeta_snapshot';
        }
        $query = $db->getQuery(true)
            ->select($cols)
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.created_by') . ' = ' . (int) $user->id)
            ->where('(' . $db->quoteName('a.oferta') . ' = 0 OR ' . $db->quoteName('a.oferta') . ' IS NULL)')
            ->order($db->quoteName('a.id') . ' DESC');
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        $list = [];
        foreach ($rows ?: [] as $row) {
            $total = isset($row->total_snapshot) && $row->total_snapshot !== null && $row->total_snapshot !== ''
                ? round((float) $row->total_snapshot, 2)
                : $this->getTotalForPreCotizacion((int) $row->id);
            $totalConTarjeta = null;
            if (isset($row->total_con_tarjeta_snapshot) && $row->total_con_tarjeta_snapshot !== null && $row->total_con_tarjeta_snapshot !== '') {
                $tc = round((float) $row->total_con_tarjeta_snapshot, 2);
                $totalConTarjeta = $tc > 0 ? $tc : null;
            }
            $list[] = (object) [
                'id'                  => (int) $row->id,
                'number'              => $row->number ?? ('PRE-' . $row->id),
                'total'               => $total,
                'total_con_tarjeta'   => $totalConTarjeta,
                'descripcion'         => isset($row->descripcion) ? trim((string) $row->descripcion) : '',
                'oferta'              => !empty($row->oferta),
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
     * Stored total including credit card charge when a plazo is selected; null if no TC or not persisted.
     *
     * @param   int  $preCotizacionId  Pre-Cotizaci?n id.
     *
     * @return  float|null  Rounded amount or null.
     *
     * @since   3.101.1
     */
    public function getTotalConTarjetaForPreCotizacion($preCotizacionId)
    {
        $item = $this->getItem((int) $preCotizacionId);
        if (!$item || !isset($item->total_con_tarjeta) || $item->total_con_tarjeta === null || $item->total_con_tarjeta === '') {
            return null;
        }
        $v = round((float) $item->total_con_tarjeta, 2);

        return $v > 0 ? $v : null;
    }

    /**
     * Minimum allowed "valor final" for a cotizaci?n line tied to this pre-cotizaci?n:
     * total with card when TC applies, otherwise base {@see getTotalForPreCotizacion}.
     *
     * @param   int  $preCotizacionId  Pre-Cotizaci?n id.
     *
     * @return  float
     *
     * @since   3.101.1
     */
    public function getMinimumValorFinalForPreCotizacion($preCotizacionId)
    {
        $tc = $this->getTotalConTarjetaForPreCotizacion((int) $preCotizacionId);

        return $tc !== null ? $tc : $this->getTotalForPreCotizacion((int) $preCotizacionId);
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

        $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
        if (isset($columns['impresion_subtotal_base']) && $lineType === 'pliego'
            && isset($data['calculation_breakdown']) && is_array($data['calculation_breakdown']) && $data['calculation_breakdown'] !== []) {
            $impBase = $this->pliegoImpresionSubtotalFromBreakdown($data['calculation_breakdown']);
            if ($impBase !== null) {
                $obj->impresion_subtotal_base     = $impBase;
                $obj->impresion_subtotal_override = null;
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
        if (isset($tableCols['oferta_expires']) && self::isOfertaExpired($template)) {
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
        if (isset($tableCols['medidas'])) {
            $newRow->medidas = isset($template->medidas) ? (string) $template->medidas : '';
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
        if (isset($columns['impresion_subtotal_base']) && $lineType === 'pliego'
            && isset($data['calculation_breakdown']) && is_array($data['calculation_breakdown']) && $data['calculation_breakdown'] !== []) {
            $impBase = $this->pliegoImpresionSubtotalFromBreakdown($data['calculation_breakdown']);
            if ($impBase !== null) {
                $line->impresion_subtotal_base     = $impBase;
                $line->impresion_subtotal_override = null;
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
     * Pliego: one per breakdown row (label); if breakdown is empty, a single "Detalles" field is used. Elementos/Env?o: one single "Detalles" input per line (each is a single element).
     *
     * @param   \stdClass  $line  Line object (breakdown, line_type)
     * @return  array  List of [concepto_key => concepto_label]
     * @since   3.91.0
     */
    public function getConceptsForLine($line)
    {
        $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
        if ($lineType === 'envio') {
            return [
                'detalle_envio' => CotizacionHelper::labelOrFallback(
                    'COM_ORDENPRODUCCION_LINE_DETALLE_ENVIO_LABEL',
                    'Shipping details',
                    'Detalles env?o'
                ),
            ];
        }
        if ($lineType === 'elementos') {
            return [
                'detalle' => CotizacionHelper::labelOrFallback(
                    'COM_ORDENPRODUCCION_LINE_DETALLE_GENERIC',
                    'Details',
                    'Detalles'
                ),
            ];
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
        if (empty($concepts)) {
            return [
                'detalle' => CotizacionHelper::labelOrFallback(
                    'COM_ORDENPRODUCCION_LINE_DETALLE_GENERIC',
                    'Details',
                    'Detalles'
                ),
            ];
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
