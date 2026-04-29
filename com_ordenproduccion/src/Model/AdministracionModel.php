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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Administracion Model
 *
 * @since  3.1.0
 */
class AdministracionModel extends BaseDatabaseModel
{
    /**
     * Get dashboard statistics
     *
     * @param   int     $month        Month number (1-12)
     * @param   int     $year         Year (e.g., 2025)
     * @param   string  $salesAgent   Optional sales agent filter (Ventas: own data only)
     *
     * @return  object  Statistics data
     *
     * @since   3.1.0
     */
    public function getStatistics($month, $year, $salesAgent = null)
    {
        $db = Factory::getDbo();
        $stats = new \stdClass();
        $salesAgentFilter = ($salesAgent !== null && $salesAgent !== '') ? $salesAgent : null;

        // Handle "All Year" case (month = 0) vs specific month
        if ($month == 0) {
            $startDate = $year . '-01-01';
            $endDate = $year . '-12-31';
        } else {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $startDate = $year . '-' . $monthStr . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
        }

        // Ventas: only show statistics from Jan 1, 2026 onward
        if ($salesAgentFilter !== null) {
            $ventasMinDate = '2026-01-01';
            if ($startDate < $ventasMinDate) {
                $startDate = $ventasMinDate;
            }
            if ($startDate > $endDate) {
                $stats->totalOrders = 0;
                $stats->totalInvoiceValue = 0;
                $stats->topOrders = [];
                $stats->ordersByStatus = [];
                $stats->averageInvoiceValue = 0;
                $stats->salesAgentsWithClients = [];
                $stats->agentTrend = $this->getAgentAnnualTrend((int) $year, $salesAgentFilter);
                $stats->clientTrend = $this->getClientAnnualTrend((int) $year, $salesAgentFilter);
                return $stats;
            }
        }

        // 1. Total work orders in selected month
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'));
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $stats->totalOrders = $db->loadResult() ?: 0;

        // 2. Total invoice value for selected month
        $query = $db->getQuery(true)
            ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'));
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $stats->totalInvoiceValue = $db->loadResult() ?: 0;

        // 3. Top 10 orders by invoice value
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('orden_de_trabajo'),
                $db->quoteName('client_name'),
                $db->quoteName('work_description'),
                $db->quoteName('invoice_value'),
                $db->quoteName('sales_agent'),
                $db->quoteName('created'),
                $db->quoteName('status')
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
            ->where($db->quoteName('invoice_value') . ' > 0')
            ->order($db->quoteName('invoice_value') . ' DESC')
            ->setLimit(10);
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $stats->topOrders = $db->loadObjectList() ?: [];

        // 4. Orders by status for selected month
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('status'),
                'COUNT(*) as count'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
            ->group($db->quoteName('status'));
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $stats->ordersByStatus = $db->loadObjectList() ?: [];

        // 5. Average invoice value
        $stats->averageInvoiceValue = $stats->totalOrders > 0 
            ? ($stats->totalInvoiceValue / $stats->totalOrders) 
            : 0;

        // 6. Sales Agents with their Top 5 Clients for selected month
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('sales_agent'),
                'COUNT(*) as total_orders',
                'SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total_sales'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
            ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''));
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $query->group($db->quoteName('sales_agent'))
            ->order('total_sales DESC');
        $db->setQuery($query);
        $salesAgents = $db->loadObjectList() ?: [];

        // Get top 5 clients for each sales agent
        $stats->salesAgentsWithClients = [];
        foreach ($salesAgents as $agent) {
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('client_name'),
                    'COUNT(*) as order_count',
                    'SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total_value'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
                ->where($db->quoteName('sales_agent') . ' = ' . $db->quote($agent->sales_agent))
                ->where($db->quoteName('client_name') . ' IS NOT NULL')
                ->where($db->quoteName('client_name') . ' != ' . $db->quote(''))
                ->group($db->quoteName('client_name'))
                ->order('total_value DESC')
                ->setLimit(5);
            $db->setQuery($query);
            $agent->topClients = $db->loadObjectList() ?: [];
            
            $stats->salesAgentsWithClients[] = $agent;
        }

        // 7. Sales Agents Annual Trend Data (yearly view only)
        $currentYear = (int) $year;
        $stats->agentTrend = $this->getAgentAnnualTrend($currentYear, $salesAgentFilter);
        
        // 8. Top 10 Clients Annual Trend Data (yearly view only)
        $stats->clientTrend = $this->getClientAnnualTrend($currentYear, $salesAgentFilter);

        return $stats;
    }

    /**
     * Get distinct client names for report filters
     *
     * @return  array  List of client names
     *
     * @since   3.6.0
     */
    public function getReportClients()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('client_name'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('client_name') . ' IS NOT NULL')
            ->where($db->quoteName('client_name') . ' != ' . $db->quote(''))
            ->group($db->quoteName('client_name'))
            ->order($db->quoteName('client_name') . ' ASC');
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_values($rows);
    }

    /**
     * Get distinct sales agent names for report filter dropdown
     *
     * @param   string|null  $salesAgentFilter  When set (Ventas), return only this agent
     *
     * @return  array  List of sales agent names
     *
     * @since   3.6.0
     */
    public function getReportSalesAgents($salesAgentFilter = null)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(' '))
            ->where($db->quoteName('created') . ' >= ' . $db->quote('2026-01-01 00:00:00'))
            ->group($db->quoteName('sales_agent'))
            ->order($db->quoteName('sales_agent') . ' ASC');
        if ($salesAgentFilter !== null && $salesAgentFilter !== '') {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_values($rows);
    }

    /**
     * Get work orders for report by date range and optional client, NIT, sales agent
     *
     * @param   string  $dateFrom    Start date (Y-m-d)
     * @param   string  $dateTo      End date (Y-m-d)
     * @param   string  $clientName  Optional client name filter
     * @param   string  $nit         Optional NIT filter
     * @param   string  $salesAgent  Optional sales agent filter
     *
     * @return  array  List of objects with orden_de_trabajo, work_description, invoice_value, client_name
     *
     * @since   3.6.0
     */
    public function getReportWorkOrders($dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '', $limit = 0, $offset = 0, $paymentStatus = '')
    {
        $db = Factory::getDbo();
        $query = $this->buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName, $nit, $salesAgent, $paymentStatus);
        $totalPaidSub = $this->getReportTotalPaidSubquery($db, 'o.id');
        $paymentNumbersSub = $this->getReportPaymentRecordNumbersSubquery($db, 'o.id');
        $query->select([
            $db->quoteName('o.id', 'id'),
            $db->quoteName('o.orden_de_trabajo', 'orden_de_trabajo'),
            $db->quoteName('o.work_description', 'work_description'),
            $db->quoteName('o.invoice_value', 'invoice_value'),
            $db->quoteName('o.client_name', 'client_name'),
            $db->quoteName('o.request_date', 'request_date'),
            $db->quoteName('o.delivery_date', 'delivery_date'),
            $totalPaidSub . ' AS total_paid',
            $paymentNumbersSub . ' AS payment_record_numbers',
        ])->order($db->quoteName('o.orden_de_trabajo') . ' DESC');
        if ((int) $limit > 0) {
            $db->setQuery($query, (int) $offset, (int) $limit);
        } else {
            $db->setQuery($query);
        }
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get total count of work orders for the report (same filters as getReportWorkOrders).
     *
     * @param   string  $dateFrom    Date from (Y-m-d)
     * @param   string  $dateTo      Date to (Y-m-d)
     * @param   string  $clientName  Optional client name filter
     * @param   string  $nit         Optional NIT filter
     * @param   string  $salesAgent  Optional sales agent filter
     *
     * @return  int
     * @since   3.78.0
     */
    public function getReportWorkOrdersTotal($dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '', $paymentStatus = '')
    {
        $db = Factory::getDbo();
        $query = $this->buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName, $nit, $salesAgent, $paymentStatus);
        $query->select('COUNT(*)');
        $db->setQuery($query);
        return (int) $db->loadResult();
    }

    /**
     * Get total invoice value for the report (same filters as getReportWorkOrders).
     *
     * @param   string  $dateFrom    Date from (Y-m-d)
     * @param   string  $dateTo      Date to (Y-m-d)
     * @param   string  $clientName  Optional client name filter
     * @param   string  $nit         Optional NIT filter
     * @param   string  $salesAgent  Optional sales agent filter
     *
     * @return  float
     * @since   3.78.0
     */
    public function getReportWorkOrdersTotalValue($dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '', $paymentStatus = '')
    {
        $db = Factory::getDbo();
        $query = $this->buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName, $nit, $salesAgent, $paymentStatus);
        $query->select('COALESCE(SUM(CAST(' . $db->quoteName('o.invoice_value') . ' AS DECIMAL(15,2))), 0)');
        $db->setQuery($query);
        return (float) $db->loadResult();
    }

    /**
     * Build base query for report work orders (FROM + WHERE only). Uses alias 'o' for ordenes.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   string  $dateFrom
     * @param   string  $dateTo
     * @param   string  $clientName
     * @param   string  $nit
     * @param   string  $salesAgent
     * @param   string  $paymentStatus  '' = all, 'paid' = fully paid, 'unpaid' = no payment, 'balance_due' = has payment but balance remaining
     *
     * @return  \Joomla\Database\DatabaseQuery
     * @since   3.78.0
     */
    protected function buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '', $paymentStatus = '')
    {
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where($db->quoteName('o.state') . ' = 1');

        if (!empty($dateFrom)) {
            $query->where($db->quoteName('o.created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }
        if (!empty($dateTo)) {
            $query->where($db->quoteName('o.created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }
        if (!empty($clientName)) {
            $query->where($db->quoteName('o.client_name') . ' = ' . $db->quote($clientName));
        }
        if (!empty($nit)) {
            $query->where($db->quoteName('o.nit') . ' = ' . $db->quote($nit));
        }
        if (!empty($salesAgent)) {
            $query->where($db->quoteName('o.sales_agent') . ' = ' . $db->quote($salesAgent));
        }

        $invoiceCol = $db->quoteName('o.invoice_value');
        $totalPaidSub = $this->getReportTotalPaidSubquery($db, 'o.id');
        if ($paymentStatus === 'unpaid') {
            $query->where('(' . $totalPaidSub . ') <= 0');
        } elseif ($paymentStatus === 'paid') {
            $query->where('(' . $totalPaidSub . ') >= CAST(' . $invoiceCol . ' AS DECIMAL(15,2)) - 0.01');
        } elseif ($paymentStatus === 'balance_due') {
            $query->where('(' . $totalPaidSub . ') > 0');
            $query->where('(' . $totalPaidSub . ') < CAST(' . $invoiceCol . ' AS DECIMAL(15,2)) - 0.01');
        }

        return $query;
    }

    /**
     * Subquery for total amount paid per order (report). Supports payment_orders junction or legacy payment_proofs.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   string  $orderIdColumn  Column reference e.g. 'o.id'
     * @return  string  SQL expression
     * @since   1.0.0
     */
    protected function getReportTotalPaidSubquery($db, $orderIdColumn = 'o.id')
    {
        if ($this->hasTable($db, '#__ordenproduccion_payment_orders')) {
            return '(SELECT COALESCE(SUM(po.amount_applied), 0) FROM ' . $db->quoteName('#__ordenproduccion_payment_orders', 'po') .
                ' INNER JOIN ' . $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') .
                ' ON pp.id = po.payment_proof_id AND pp.state = 1 WHERE po.order_id = ' . $orderIdColumn . ')';
        }
        return '(SELECT COALESCE(SUM(pp.payment_amount), 0) FROM ' . $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') .
            ' WHERE pp.order_id = ' . $orderIdColumn . ' AND pp.state = 1)';
    }

    /**
     * Subquery for comma-separated payment record numbers (PA-00001 format) per order. NULL when none.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   string  $orderIdColumn  Column reference e.g. 'o.id'
     * @return  string  SQL expression
     * @since   1.0.0
     */
    protected function getReportPaymentRecordNumbersSubquery($db, $orderIdColumn = 'o.id')
    {
        $q = $db->quoteName('#__ordenproduccion_payment_proofs', 'pp');
        $fmt = "CONCAT('PA-', LPAD(pp.id, 5, '0'))";
        if ($this->hasTable($db, '#__ordenproduccion_payment_orders')) {
            return '(SELECT GROUP_CONCAT(' . $fmt . ' ORDER BY pp.id SEPARATOR ", ") FROM ' . $db->quoteName('#__ordenproduccion_payment_orders', 'po') .
                ' INNER JOIN ' . $q . ' ON pp.id = po.payment_proof_id AND pp.state = 1 WHERE po.order_id = ' . $orderIdColumn . ')';
        }
        return '(SELECT GROUP_CONCAT(' . $fmt . ' ORDER BY pp.id SEPARATOR ", ") FROM ' . $q .
            ' WHERE pp.order_id = ' . $orderIdColumn . ' AND pp.state = 1)';
    }

    /**
     * Get total count of envios (shipping print events from historial) for Reportes > Envios.
     * When $salesAgent is set (Ventas), only envios for that agent's orders are counted.
     *
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own orders only).
     * @param   string       $client      Optional client filter (substring match on client_name).
     * @param   string       $tipo        Optional tipo filter: 'completo', 'parcial', or '' for all.
     * @param   string       $dateFrom    Optional date from (Y-m-d).
     * @param   string       $dateTo      Optional date to (Y-m-d).
     * @return  int
     * @since   1.0.0
     */
    public function getEnviosTotal($salesAgent = null, $client = '', $tipo = '', $dateFrom = '', $dateTo = '')
    {
        $db = Factory::getDbo();
        $query = $this->buildEnviosListQuery($db, $salesAgent, $client, $tipo, $dateFrom, $dateTo);
        $query->clear('select')->select('COUNT(*)');
        $db->setQuery($query);
        return (int) $db->loadResult();
    }

    /**
     * Build base query for envios list (shared by getEnviosTotal and getEnviosList).
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   string|null  $salesAgent
     * @param   string       $client
     * @param   string       $tipo
     * @param   string       $dateFrom
     * @param   string       $dateTo
     * @return  \Joomla\Database\DatabaseQuery
     */
    protected function buildEnviosListQuery($db, $salesAgent = null, $client = '', $tipo = '', $dateFrom = '', $dateTo = '')
    {
        $clientCol = $db->quoteName('o.client_name');
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($cols['nombre_del_cliente']) && !isset($cols['client_name'])) {
                $clientCol = $db->quoteName('o.nombre_del_cliente');
            }
        } catch (\Exception $e) {
        }
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.' . $db->quoteName('id') . ' = h.' . $db->quoteName('order_id')
                . ' AND o.' . $db->quoteName('state') . ' = 1'
            )
            ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('shipping_print'))
            ->where('h.' . $db->quoteName('state') . ' = 1');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        if ($client !== '') {
            $query->where($clientCol . ' LIKE ' . $db->quote('%' . $db->escape(trim($client), true) . '%'));
        }
        if ($tipo === 'parcial') {
            $query->where('(' . $db->quoteName('h.metadata') . ' LIKE ' . $db->quote('%"tipo_envio":"parcial"%') . ' OR ' . $db->quoteName('h.event_description') . ' LIKE ' . $db->quote('%Envio parcial%') . ')');
        } elseif ($tipo === 'completo') {
            $query->where('(' . $db->quoteName('h.metadata') . ' LIKE ' . $db->quote('%"tipo_envio":"completo"%') . ' OR ' . $db->quoteName('h.event_description') . ' LIKE ' . $db->quote('%Envio completo%') . ')');
        }
        if ($dateFrom !== '') {
            $query->where($db->quoteName('h.created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }
        if ($dateTo !== '') {
            $query->where($db->quoteName('h.created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }
        return $query;
    }

    /**
     * Get list of envios (shipping print events) for Reportes > Envios subtab.
     * Each row = one historial shipping_print entry with order data.
     *
     * @param   int          $limit       Page size (0 = no limit)
     * @param   int          $offset      Offset for pagination
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own orders only).
     * @param   string       $client      Optional client filter (substring match).
     * @param   string       $tipo        Optional tipo: 'completo', 'parcial', or ''.
     * @param   string       $dateFrom    Optional date from (Y-m-d).
     * @param   string       $dateTo      Optional date to (Y-m-d).
     * @return  array  List of objects: envio_id, order_id, order_number, client_name, work_description, tipo (completo|parcial), partial_description, created
     * @since   1.0.0
     */
    public function getEnviosList($limit = 50, $offset = 0, $salesAgent = null, $client = '', $tipo = '', $dateFrom = '', $dateTo = '')
    {
        $db = Factory::getDbo();
        $orderNumberCol = $db->quoteName('o.orden_de_trabajo');
        $clientCol = $db->quoteName('o.client_name');
        $descCol = $db->quoteName('o.work_description');
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($cols['order_number']) && !isset($cols['orden_de_trabajo'])) {
                $orderNumberCol = $db->quoteName('o.order_number');
            }
            if (isset($cols['nombre_del_cliente']) && !isset($cols['client_name'])) {
                $clientCol = $db->quoteName('o.nombre_del_cliente');
            }
            if (isset($cols['descripcion_de_trabajo']) && !isset($cols['work_description'])) {
                $descCol = $db->quoteName('o.descripcion_de_trabajo');
            }
        } catch (\Exception $e) {
            // use defaults
        }
        $query = $this->buildEnviosListQuery($db, $salesAgent, $client, $tipo, $dateFrom, $dateTo);
        $query->select([
            'h.' . $db->quoteName('id') . ' AS envio_id',
            'h.' . $db->quoteName('order_id'),
            'h.' . $db->quoteName('event_description'),
            'h.' . $db->quoteName('metadata'),
            'h.' . $db->quoteName('created'),
            $orderNumberCol . ' AS order_number',
            $clientCol . ' AS client_name',
            $descCol . ' AS work_description',
        ])->order('h.' . $db->quoteName('created') . ' DESC');
        if ((int) $limit > 0) {
            $db->setQuery($query, (int) $offset, (int) $limit);
        } else {
            $db->setQuery($query);
        }
        $rows = $db->loadObjectList() ?: [];
        foreach ($rows as $row) {
            $row->tipo = 'completo';
            $row->partial_description = '';
            if (!empty($row->metadata)) {
                $meta = json_decode($row->metadata, true);
                if (is_array($meta) && isset($meta['tipo_envio'])) {
                    $row->tipo = $meta['tipo_envio'] === 'parcial' ? 'parcial' : 'completo';
                    $row->partial_description = isset($meta['descripcion']) ? (string) $meta['descripcion'] : '';
                }
            }
            if ($row->tipo === 'completo' && !empty($row->event_description) && stripos($row->event_description, 'parcial') !== false) {
                $row->tipo = 'parcial';
            }
        }
        return $rows;
    }

    /**
     * Get clients with totals (and optional filters / pagination).
     * Accounting: Saldo = Total invoiced - (initial_paid_to_dec31_2025 + payments from Jan 1 2026)
     *
     * @param   string   $ordering         Sort column: name, compras, saldo (saldo uses the displayed amount, i.e. negated balance)
     * @param   string   $direction        Sort direction: asc, desc
     * @param   boolean  $hideZeroSaldo    Hide clients with Saldo = 0
     * @param   string|null  $salesAgent   Optional sales agent filter
     * @param   string   $clientNameFilter Optional client name substring filter (case-insensitive)
     * @param   string   $nitFilter        Optional NIT substring filter (case-insensitive)
     * @param   int      $limit            Page size (0 = return all)
     * @param   int      $offset           Offset for pagination
     * @return  array
     * @since   3.54.0
     * @since   3.79.0  Added clientNameFilter, nitFilter, limit, offset
     */
    public function getClientsWithTotals($ordering = 'name', $direction = 'asc', $hideZeroSaldo = false, $salesAgent = null, $clientNameFilter = '', $nitFilter = '', $limit = 0, $offset = 0)
    {
        $clients = $this->buildClientsWithBalances($salesAgent);
        $this->syncClientBalances($clients);

        if ($hideZeroSaldo) {
            $clients = array_filter($clients, function ($c) {
                return (float) ($c->saldo ?? 0) > 0;
            });
            $clients = array_values($clients);
        }

        if ($clientNameFilter !== '') {
            $needle = mb_strtolower(trim($clientNameFilter));
            $clients = array_filter($clients, function ($c) use ($needle) {
                return $needle === '' || mb_strpos(mb_strtolower((string) ($c->client_name ?? '')), $needle) !== false;
            });
            $clients = array_values($clients);
        }
        if ($nitFilter !== '') {
            $needle = mb_strtolower(trim($nitFilter));
            $clients = array_filter($clients, function ($c) use ($needle) {
                return $needle === '' || mb_strpos(mb_strtolower((string) ($c->nit ?? '')), $needle) !== false;
            });
            $clients = array_values($clients);
        }

        $dir = strtolower($direction ?? 'asc') === 'desc' ? -1 : 1;
        usort($clients, function ($a, $b) use ($ordering, $dir) {
            $o = strtolower($ordering ?? 'name');
            if ($o === 'compras') {
                $va = (float) ($a->compras ?? 0);
                $vb = (float) ($b->compras ?? 0);
                return $dir * ($va <=> $vb);
            }
            if ($o === 'saldo') {
                // Match the column display (template uses -saldo for Q. amounts)
                $va = (float) -($a->saldo ?? 0);
                $vb = (float) -($b->saldo ?? 0);
                return $dir * ($va <=> $vb);
            }
            $na = trim($a->client_name ?? '') . '|' . trim($a->nit ?? '');
            $nb = trim($b->client_name ?? '') . '|' . trim($b->nit ?? '');
            return $dir * strcasecmp($na, $nb);
        });

        if ((int) $limit > 0) {
            return array_slice($clients, (int) $offset, (int) $limit);
        }

        return $clients;
    }

    /**
     * Get total count of clients after filters (for clientes pagination).
     * Uses same filters as getClientsWithTotals but returns count only.
     *
     * @param   string       $ordering         Sort column (unused for count)
     * @param   string       $direction       Sort direction (unused for count)
     * @param   boolean      $hideZeroSaldo   Hide clients with Saldo = 0
     * @param   string|null  $salesAgent      Optional sales agent filter
     * @param   string       $clientNameFilter Optional client name substring filter
     * @param   string       $nitFilter       Optional NIT substring filter
     * @return  int
     * @since   3.79.0
     */
    public function getClientsWithTotalsCount($ordering = 'name', $direction = 'asc', $hideZeroSaldo = false, $salesAgent = null, $clientNameFilter = '', $nitFilter = '')
    {
        $clients = $this->getClientsWithTotals($ordering, $direction, $hideZeroSaldo, $salesAgent, $clientNameFilter, $nitFilter, 0, 0);
        return count($clients);
    }

    /**
     * Get summary of work orders (from Jan 1, 2026) that have no payment proof, grouped by age in days.
     * Buckets: 0-15 days, 16-30 days, 31-45 days, >45 days (days since order creation).
     * Returns only counts and total values per bucket, no order details.
     *
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own orders only)
     * @return  array  ['0_15' => ['count' => N, 'total_value' => X], '16_30' => ..., '31_45' => ..., '45_plus' => ...]
     * @since   3.99.0
     */
    public function getOrdersWithoutPaymentProofByAgeBuckets($salesAgent = null)
    {
        $db = Factory::getDbo();
        $emptySummary = ['0_15' => ['count' => 0, 'total_value' => 0.0], '16_30' => ['count' => 0, 'total_value' => 0.0], '31_45' => ['count' => 0, 'total_value' => 0.0], '45_plus' => ['count' => 0, 'total_value' => 0.0]];
        if (!$this->hasTable($db, '#__ordenproduccion_payment_orders')) {
            return $emptySummary;
        }
        $invoiceCol = $db->quoteName('o.invoice_value');
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($cols['valor_a_facturar']) && !isset($cols['invoice_value'])) {
                $invoiceCol = $db->quoteName('o.valor_a_facturar');
            }
        } catch (\Exception $e) {
        }
        $noProofCond = 'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ordenproduccion_payment_orders', 'po') .
            ' INNER JOIN ' . $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') .
            ' ON pp.id = po.payment_proof_id AND pp.state = 1 WHERE po.order_id = o.id)';
        $query = $db->getQuery(true)
            ->select([
                'CAST(' . $invoiceCol . ' AS DECIMAL(15,2)) AS invoice_value',
                'DATEDIFF(CURDATE(), DATE(o.created)) AS days_old'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('created') . ' >= ' . $db->quote('2026-01-01 00:00:00'))
            ->where('(' . $noProofCond . ')');
        try {
            $oCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($oCols['status'])) {
                $query->where('o.' . $db->quoteName('status') . ' != ' . $db->quote('Anulada'));
            }
        } catch (\Exception $e) {
        }
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $buckets = ['0_15' => ['count' => 0, 'total_value' => 0.0], '16_30' => ['count' => 0, 'total_value' => 0.0], '31_45' => ['count' => 0, 'total_value' => 0.0], '45_plus' => ['count' => 0, 'total_value' => 0.0]];
        foreach ($rows as $row) {
            $days = (int) ($row->days_old ?? 0);
            $val = (float) ($row->invoice_value ?? 0);
            if ($days <= 15) {
                $buckets['0_15']['count']++;
                $buckets['0_15']['total_value'] += $val;
            } elseif ($days <= 30) {
                $buckets['16_30']['count']++;
                $buckets['16_30']['total_value'] += $val;
            } elseif ($days <= 45) {
                $buckets['31_45']['count']++;
                $buckets['31_45']['total_value'] += $val;
            } else {
                $buckets['45_plus']['count']++;
                $buckets['45_plus']['total_value'] += $val;
            }
        }
        return $buckets;
    }

    /**
     * Get summary of work orders (from Jan 1, 2026) without payment proof, grouped by client with per-bucket breakdown.
     * Same filters as getOrdersWithoutPaymentProofByAgeBuckets. Returns list with client_name and count/value per range (0_15, 16_30, 31_45, 45_plus) plus total.
     *
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own orders only)
     * @return  array  List of objects: client_name, count_0_15, total_value_0_15, count_16_30, total_value_16_30, count_31_45, total_value_31_45, count_45_plus, total_value_45_plus, order_count, total_value
     * @since   3.99.0
     */
    public function getOrdersWithoutPaymentProofSummaryByClient($salesAgent = null)
    {
        $db = Factory::getDbo();
        if (!$this->hasTable($db, '#__ordenproduccion_payment_orders')) {
            return [];
        }
        $clientCol = $db->quoteName('o.client_name');
        $invoiceCol = $db->quoteName('o.invoice_value');
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($cols['nombre_del_cliente']) && !isset($cols['client_name'])) {
                $clientCol = $db->quoteName('o.nombre_del_cliente');
            }
            if (isset($cols['valor_a_facturar']) && !isset($cols['invoice_value'])) {
                $invoiceCol = $db->quoteName('o.valor_a_facturar');
            }
        } catch (\Exception $e) {
        }
        $noProofCond = 'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ordenproduccion_payment_orders', 'po') .
            ' INNER JOIN ' . $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') .
            ' ON pp.id = po.payment_proof_id AND pp.state = 1 WHERE po.order_id = o.id)';
        $days = 'DATEDIFF(CURDATE(), DATE(o.created))';
        $val = 'CAST(' . $invoiceCol . ' AS DECIMAL(15,2))';
        $selectFields = [
            $clientCol . ' AS client_name',
            'SUM(CASE WHEN ' . $days . ' <= 15 THEN 1 ELSE 0 END) AS count_0_15',
            'SUM(CASE WHEN ' . $days . ' <= 15 THEN ' . $val . ' ELSE 0 END) AS total_value_0_15',
            'SUM(CASE WHEN ' . $days . ' > 15 AND ' . $days . ' <= 30 THEN 1 ELSE 0 END) AS count_16_30',
            'SUM(CASE WHEN ' . $days . ' > 15 AND ' . $days . ' <= 30 THEN ' . $val . ' ELSE 0 END) AS total_value_16_30',
            'SUM(CASE WHEN ' . $days . ' > 30 AND ' . $days . ' <= 45 THEN 1 ELSE 0 END) AS count_31_45',
            'SUM(CASE WHEN ' . $days . ' > 30 AND ' . $days . ' <= 45 THEN ' . $val . ' ELSE 0 END) AS total_value_31_45',
            'SUM(CASE WHEN ' . $days . ' > 45 THEN 1 ELSE 0 END) AS count_45_plus',
            'SUM(CASE WHEN ' . $days . ' > 45 THEN ' . $val . ' ELSE 0 END) AS total_value_45_plus',
            'COUNT(*) AS order_count',
            'SUM(' . $val . ') AS total_value'
        ];
        $groupBy = $clientCol;
        $orderBy = $clientCol . ' ASC';
        if ($salesAgent === null || $salesAgent === '') {
            $selectFields = array_merge([$db->quoteName('o.sales_agent') . ' AS sales_agent'], $selectFields);
            $groupBy = [$db->quoteName('o.sales_agent'), $clientCol];
            $orderBy = $db->quoteName('o.sales_agent') . ' ASC, ' . $clientCol . ' ASC';
        }
        $query = $db->getQuery(true)
            ->select($selectFields)
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('created') . ' >= ' . $db->quote('2026-01-01 00:00:00'))
            ->where('(' . $noProofCond . ')')
            ->group($groupBy)
            ->order($orderBy);
        try {
            $oCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($oCols['status'])) {
                $query->where('o.' . $db->quoteName('status') . ' != ' . $db->quote('Anulada'));
            }
        } catch (\Exception $e) {
        }
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        return $rows;
    }

    /**
     * Get summary of work orders (from Jan 1, 2026) without payment proof, grouped by sales agent with per-bucket breakdown.
     * Same filters as getOrdersWithoutPaymentProofByAgeBuckets. Returns list with sales_agent and count/value per range (0_15, 16_30, 31_45, 45_plus) plus total.
     *
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own orders only). When set, returns one row for that agent.
     * @return  array  List of objects: sales_agent, count_0_15, total_value_0_15, count_16_30, total_value_16_30, count_31_45, total_value_31_45, count_45_plus, total_value_45_plus, order_count, total_value
     * @since   3.99.0
     */
    public function getOrdersWithoutPaymentProofSummaryByAgent($salesAgent = null)
    {
        $db = Factory::getDbo();
        if (!$this->hasTable($db, '#__ordenproduccion_payment_orders')) {
            return [];
        }
        $invoiceCol = $db->quoteName('o.invoice_value');
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($cols['valor_a_facturar']) && !isset($cols['invoice_value'])) {
                $invoiceCol = $db->quoteName('o.valor_a_facturar');
            }
        } catch (\Exception $e) {
        }
        $noProofCond = 'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ordenproduccion_payment_orders', 'po') .
            ' INNER JOIN ' . $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') .
            ' ON pp.id = po.payment_proof_id AND pp.state = 1 WHERE po.order_id = o.id)';
        $days = 'DATEDIFF(CURDATE(), DATE(o.created))';
        $val = 'CAST(' . $invoiceCol . ' AS DECIMAL(15,2))';
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('o.sales_agent') . ' AS sales_agent',
                'SUM(CASE WHEN ' . $days . ' <= 15 THEN 1 ELSE 0 END) AS count_0_15',
                'SUM(CASE WHEN ' . $days . ' <= 15 THEN ' . $val . ' ELSE 0 END) AS total_value_0_15',
                'SUM(CASE WHEN ' . $days . ' > 15 AND ' . $days . ' <= 30 THEN 1 ELSE 0 END) AS count_16_30',
                'SUM(CASE WHEN ' . $days . ' > 15 AND ' . $days . ' <= 30 THEN ' . $val . ' ELSE 0 END) AS total_value_16_30',
                'SUM(CASE WHEN ' . $days . ' > 30 AND ' . $days . ' <= 45 THEN 1 ELSE 0 END) AS count_31_45',
                'SUM(CASE WHEN ' . $days . ' > 30 AND ' . $days . ' <= 45 THEN ' . $val . ' ELSE 0 END) AS total_value_31_45',
                'SUM(CASE WHEN ' . $days . ' > 45 THEN 1 ELSE 0 END) AS count_45_plus',
                'SUM(CASE WHEN ' . $days . ' > 45 THEN ' . $val . ' ELSE 0 END) AS total_value_45_plus',
                'COUNT(*) AS order_count',
                'SUM(' . $val . ') AS total_value'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('created') . ' >= ' . $db->quote('2026-01-01 00:00:00'))
            ->where('(' . $noProofCond . ')')
            ->group($db->quoteName('o.sales_agent'))
            ->order($db->quoteName('o.sales_agent') . ' ASC');
        try {
            $oCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            if (isset($oCols['status'])) {
                $query->where('o.' . $db->quoteName('status') . ' != ' . $db->quote('Anulada'));
            }
        } catch (\Exception $e) {
        }
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        return $rows;
    }

    /**
     * Sort Rango de días / días de crédito summary rows (by client name or sales agent, or by bucket/total value).
     *
     * @param   array   $rows         List of stdClass rows from getOrdersWithoutPaymentProofSummaryByClient/ByAgent
     * @param   string  $ordering     client | 0_15 | 16_30 | 31_45 | 45_plus | total
     * @param   string  $direction    asc | desc
     * @param   bool    $isAgentRow   true: first column uses sales_agent; false: uses client_name
     *
     * @return  array
     *
     * @since   3.101.8
     */
    public function sortDiasCreditoRows(array $rows, string $ordering, string $direction, bool $isAgentRow = false): array
    {
        $dir = strtolower($direction ?? 'asc') === 'desc' ? -1 : 1;
        $ordering = strtolower($ordering ?? 'client');
        $allowed = ['client', '0_15', '16_30', '31_45', '45_plus', 'total'];
        if (!in_array($ordering, $allowed, true)) {
            $ordering = 'client';
        }
        $rows = array_values($rows);
        usort($rows, function ($a, $b) use ($ordering, $dir, $isAgentRow) {
            if ($ordering === 'client') {
                $ka = $isAgentRow ? (string) ($a->sales_agent ?? '') : (string) ($a->client_name ?? '');
                $kb = $isAgentRow ? (string) ($b->sales_agent ?? '') : (string) ($b->client_name ?? '');

                return $dir * strcasecmp($ka, $kb);
            }
            $map = [
                '0_15' => 'total_value_0_15',
                '16_30' => 'total_value_16_30',
                '31_45' => 'total_value_31_45',
                '45_plus' => 'total_value_45_plus',
                'total' => 'total_value',
            ];
            $field = $map[$ordering] ?? 'total_value';
            $va = (float) ($a->{$field} ?? 0);
            $vb = (float) ($b->{$field} ?? 0);

            return $dir * ($va <=> $vb);
        });

        return $rows;
    }

    /**
     * Build clients list with calculated balances (used internally and for sync).
     *
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own orders only)
     * @return  array
     * @since   3.57.0
     */
    protected function buildClientsWithBalances($salesAgent = null)
    {
        $db = Factory::getDbo();
        $this->ensureClientOpeningBalanceTableExists($db);

        $clientCol = 'o.' . $db->quoteName('client_name');
        $nitCol = 'o.' . $db->quoteName('nit');
        $invoiceCol = $db->quoteName('o.invoice_value');

        $query = $db->getQuery(true)
            ->select([
                $clientCol . ' AS client_name',
                $nitCol . ' AS nit',
                'COUNT(*) AS order_count',
                'SUM(CAST(' . $invoiceCol . ' AS DECIMAL(15,2))) AS total_invoice_value',
                'SUM(CASE WHEN o.created <= ' . $db->quote('2025-12-31 23:59:59') . ' THEN CAST(' . $invoiceCol . ' AS DECIMAL(15,2)) ELSE 0 END) AS invoice_value_to_dec31_2025'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('status') . ' != ' . $db->quote('Anulada'))
            ->where('o.' . $db->quoteName('created') . ' >= ' . $db->quote('2025-10-01 00:00:00'))
            ->where('(o.' . $db->quoteName('client_name') . ' IS NOT NULL AND o.' . $db->quoteName('client_name') . ' != ' . $db->quote('') . ')')
            ->group([$clientCol, $nitCol])
            ->order($clientCol . ' ASC, ' . $nitCol . ' ASC');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }

        $db->setQuery($query);
        $clients = $db->loadObjectList() ?: [];

        $openingMap = $this->getOpeningBalancesMap($db);
        $paidFromJan2026Map = $this->getPaidFromJan2026ByClientMap($db);
        $registradoFromJan2026Map = $this->getRegistradoFromJan2026ByClientMap($db);
        $invoiceOctDec2025Map = $this->getInvoiceValueOctDec2025ByClientMap($db);
        $invoiceFromJan2026Map = $this->getInvoiceValueFromJan2026ByClientMap($db);

        foreach ($clients as $c) {
            $key = $this->clientKey($c->client_name ?? '', $c->nit ?? '');
            $initialPaid = (float) ($openingMap[$key] ?? 0);
            $paidFromJan = (float) ($paidFromJan2026Map[$key] ?? 0);
            $registradoFromJan = (float) ($registradoFromJan2026Map[$key] ?? 0);
            $totalInvoiced = (float) ($c->total_invoice_value ?? 0);
            $invoiceOctDec2025 = (float) ($invoiceOctDec2025Map[$key] ?? 0);
            $compras = (float) ($invoiceFromJan2026Map[$key] ?? 0);
            $c->invoice_value_oct_dec_2025 = $invoiceOctDec2025;
            $c->initial_paid_to_dec31_2025 = $initialPaid;
            $c->display_pagado = $initialPaid > 0 ? $initialPaid : $invoiceOctDec2025;
            $c->paid_from_jan2026 = $paidFromJan;
            $c->registrado_from_jan2026 = $registradoFromJan;
            $c->verificado_from_jan2026 = $paidFromJan;
            $c->compras = $compras;
            // Saldo = total compras (invoiced) minus total verificado (verified payments only). Positive = client owes.
            $c->saldo = round($totalInvoiced - $initialPaid - $paidFromJan, 2);
            $c->invoice_value_to_dec31_2025 = (float) ($c->invoice_value_to_dec31_2025 ?? 0);
        }

        return $clients;
    }

    /**
     * Sync client balances to the reusable table for other views/modules.
     *
     * @param   array  $clients  Clients with saldo property
     *
     * @return  void
     * @since   3.57.0
     */
    protected function syncClientBalances(array $clients)
    {
        $db = Factory::getDbo();
        $this->ensureClientBalanceTableExists($db);

        foreach ($clients as $c) {
            $clientName = trim($c->client_name ?? '');
            $nit = trim($c->nit ?? '');
            $saldo = round((float) ($c->saldo ?? 0), 2);
            if ($clientName === '') {
                continue;
            }
            $this->upsertClientBalance($db, $clientName, $nit, $saldo);
        }
    }

    /**
     * Upsert a single client balance row.
     */
    protected function upsertClientBalance($db, $clientName, $nit, $saldo)
    {
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_client_balance'))
            ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
        if ($nit !== '') {
            $query->where($db->quoteName('nit') . ' = ' . $db->quote($nit));
        } else {
            $query->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
        }
        $db->setQuery($query);
        $id = (int) $db->loadResult();

        if ($id > 0) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_client_balance'))
                    ->set($db->quoteName('saldo') . ' = ' . $saldo)
                    ->where($db->quoteName('id') . ' = ' . $id)
            );
            $db->execute();
        } else {
            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_client_balance'))
                    ->columns([$db->quoteName('client_name'), $db->quoteName('nit'), $db->quoteName('saldo')])
                    ->values($db->quote($clientName) . ',' . $db->quote($nit) . ',' . $saldo)
            );
            $db->execute();
        }
    }

    /**
     * Ensure client_balance table exists.
     */
    protected function ensureClientBalanceTableExists($db)
    {
        if ($this->hasTable($db, '#__ordenproduccion_client_balance')) {
            return;
        }
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName($db->replacePrefix('#__ordenproduccion_client_balance')) . ' (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_name varchar(255) NOT NULL,
            nit varchar(100) DEFAULT NULL,
            saldo decimal(15,2) NOT NULL DEFAULT 0.00,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_client_nit (client_name(191), nit(50)),
            KEY idx_client_name (client_name(191)),
            KEY idx_nit (nit(50)),
            KEY idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->setQuery($sql);
        $db->execute();
    }

    /**
     * Refresh the client_balance table (call after opening balance save, init, or merge).
     *
     * @return  void
     * @since   3.57.0
     */
    public function refreshClientBalances()
    {
        $clients = $this->buildClientsWithBalances();
        $this->syncClientBalances($clients);
    }

    /**
     * Get all client balances from the reusable table (for other views/modules).
     *
     * @return  array  List of objects with client_name, nit, saldo, updated_at
     * @since   3.57.0
     */
    public function getClientBalances()
    {
        $db = Factory::getDbo();
        $this->ensureClientBalanceTableExists($db);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('client_name'),
                $db->quoteName('nit'),
                $db->quoteName('saldo'),
                $db->quoteName('updated_at')
            ])
            ->from($db->quoteName('#__ordenproduccion_client_balance'))
            ->order($db->quoteName('client_name') . ' ASC, ' . $db->quoteName('nit') . ' ASC');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get balance for a single client (for use by modules).
     *
     * @param   string  $clientName  Client name
     * @param   string  $nit         Client NIT
     *
     * @return  float  Saldo or 0 if not found
     * @since   3.57.0
     */
    public function getClientBalance($clientName, $nit = '')
    {
        $db = Factory::getDbo();
        if (!$this->hasTable($db, '#__ordenproduccion_client_balance')) {
            return 0.0;
        }
        $query = $db->getQuery(true)
            ->select($db->quoteName('saldo'))
            ->from($db->quoteName('#__ordenproduccion_client_balance'))
            ->where($db->quoteName('client_name') . ' = ' . $db->quote(trim($clientName ?? '')));
        if (trim($nit ?? '') !== '') {
            $query->where($db->quoteName('nit') . ' = ' . $db->quote(trim($nit)));
        } else {
            $query->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
        }
        $db->setQuery($query);
        $row = $db->loadObject();
        return $row ? (float) ($row->saldo ?? 0) : 0.0;
    }

    /**
     * Create a unique key for client (name + nit)
     */
    protected function clientKey($clientName, $nit)
    {
        return trim($clientName ?? '') . '|' . trim($nit ?? '');
    }

    /**
     * Get map of client_key => amount_paid_to_dec31_2025 from opening balance table
     */
    protected function getOpeningBalancesMap($db)
    {
        if (!$this->hasTable($db, '#__ordenproduccion_client_opening_balance')) {
            return [];
        }
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('client_name'),
                $db->quoteName('nit'),
                $db->quoteName('amount_paid_to_dec31_2025')
            ])
            ->from($db->quoteName('#__ordenproduccion_client_opening_balance'));
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[$this->clientKey($r->client_name ?? '', $r->nit ?? '')] = (float) ($r->amount_paid_to_dec31_2025 ?? 0);
        }
        return $map;
    }

    /**
     * Get map of client_key => sum of payments from Jan 1 2026 (verified only; used for Saldo).
     */
    protected function getPaidFromJan2026ByClientMap($db)
    {
        if (!$this->hasTable($db, '#__ordenproduccion_payment_orders') || !$this->hasTable($db, '#__ordenproduccion_payment_proofs')) {
            return [];
        }
        $verifiedCondition = '';
        $ppCols = $db->getTableColumns('#__ordenproduccion_payment_proofs', false);
        $ppCols = is_array($ppCols) ? array_change_key_case($ppCols, CASE_LOWER) : [];
        if (isset($ppCols['verification_status'])) {
            $verifiedCondition = " AND pp.verification_status = 'verificado'";
        }
        $query = $db->getQuery(true)
            ->select([
                'o.' . $db->quoteName('client_name'),
                'o.' . $db->quoteName('nit'),
                'SUM(CAST(po.' . $db->quoteName('amount_applied') . ' AS DECIMAL(15,2))) AS total_paid'
            ])
            ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') . ' ON pp.id = po.payment_proof_id AND pp.state = 1'
                . ' AND pp.created >= ' . $db->quote('2026-01-01 00:00:00') . $verifiedCondition
            )
            ->innerJoin($db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = po.order_id AND o.state = 1')
            ->group(['o.client_name', 'o.nit']);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[$this->clientKey($r->client_name ?? '', $r->nit ?? '')] = (float) ($r->total_paid ?? 0);
        }
        return $map;
    }

    /**
     * Get map of client_key => sum of all registered payments from Jan 1 2026 (Registrado; any verification status).
     */
    protected function getRegistradoFromJan2026ByClientMap($db)
    {
        if (!$this->hasTable($db, '#__ordenproduccion_payment_orders') || !$this->hasTable($db, '#__ordenproduccion_payment_proofs')) {
            return [];
        }
        $query = $db->getQuery(true)
            ->select([
                'o.' . $db->quoteName('client_name'),
                'o.' . $db->quoteName('nit'),
                'SUM(CAST(po.' . $db->quoteName('amount_applied') . ' AS DECIMAL(15,2))) AS total_paid'
            ])
            ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') . ' ON pp.id = po.payment_proof_id AND pp.state = 1'
                . ' AND pp.created >= ' . $db->quote('2026-01-01 00:00:00')
            )
            ->innerJoin($db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = po.order_id AND o.state = 1')
            ->group(['o.client_name', 'o.nit']);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[$this->clientKey($r->client_name ?? '', $r->nit ?? '')] = (float) ($r->total_paid ?? 0);
        }
        return $map;
    }

    /**
     * Get map of client_key => sum of invoice_value for orders from Jan 1 2026 (Compras)
     */
    protected function getInvoiceValueFromJan2026ByClientMap($db)
    {
        $query = $db->getQuery(true)
            ->select([
                'o.' . $db->quoteName('client_name'),
                'o.' . $db->quoteName('nit'),
                'SUM(CAST(o.' . $db->quoteName('invoice_value') . ' AS DECIMAL(15,2))) AS total_invoice'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('status') . ' != ' . $db->quote('Anulada'))
            ->where('o.' . $db->quoteName('created') . ' >= ' . $db->quote('2026-01-01 00:00:00'))
            ->where('(o.' . $db->quoteName('client_name') . ' IS NOT NULL AND o.' . $db->quoteName('client_name') . ' != ' . $db->quote('') . ')')
            ->group(['o.client_name', 'o.nit']);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[$this->clientKey($r->client_name ?? '', $r->nit ?? '')] = (float) ($r->total_invoice ?? 0);
        }
        return $map;
    }

    /**
     * Get map of client_key => sum of invoice_value for orders Oct 1 - Dec 31 2025
     */
    protected function getInvoiceValueOctDec2025ByClientMap($db)
    {
        $query = $db->getQuery(true)
            ->select([
                'o.' . $db->quoteName('client_name'),
                'o.' . $db->quoteName('nit'),
                'SUM(CAST(o.' . $db->quoteName('invoice_value') . ' AS DECIMAL(15,2))) AS total_invoice'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('status') . ' != ' . $db->quote('Anulada'))
            ->where('o.' . $db->quoteName('created') . ' >= ' . $db->quote('2025-10-01 00:00:00'))
            ->where('o.' . $db->quoteName('created') . ' <= ' . $db->quote('2025-12-31 23:59:59'))
            ->where('(o.' . $db->quoteName('client_name') . ' IS NOT NULL AND o.' . $db->quoteName('client_name') . ' != ' . $db->quote('') . ')')
            ->group(['o.client_name', 'o.nit']);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];
        $map = [];
        foreach ($rows as $r) {
            $map[$this->clientKey($r->client_name ?? '', $r->nit ?? '')] = (float) ($r->total_invoice ?? 0);
        }
        return $map;
    }

    /**
     * Check if table exists
     */
    protected function hasTable($db, $tableName)
    {
        $t = $db->replacePrefix($tableName);
        foreach ($db->getTableList() as $name) {
            if (strcasecmp($name, $t) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensure client_opening_balance table exists
     */
    protected function ensureClientOpeningBalanceTableExists($db)
    {
        if ($this->hasTable($db, '#__ordenproduccion_client_opening_balance')) {
            return;
        }
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName($db->replacePrefix('#__ordenproduccion_client_opening_balance')) . ' (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_name varchar(255) NOT NULL,
            nit varchar(100) DEFAULT NULL,
            amount_paid_to_dec31_2025 decimal(15,2) NOT NULL DEFAULT 0.00,
            created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by int(11) NOT NULL DEFAULT 0,
            modified datetime DEFAULT NULL,
            modified_by int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_client_nit (client_name(191), nit(50)),
            KEY idx_client_name (client_name(191)),
            KEY idx_nit (nit(50))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->setQuery($sql);
        $db->execute();
    }

    /**
     * Save or update opening balance for a client (amount paid up to Dec 31 2025)
     *
     * @param   string  $clientName  Client name
     * @param   string  $nit         Client NIT
     * @param   float   $amount      Amount paid to Dec 31 2025
     *
     * @return  bool
     *
     * @since   3.56.0
     */
    public function saveOpeningBalance($clientName, $nit, $amount, $refreshBalances = true)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $this->ensureClientOpeningBalanceTableExists($db);

        $clientName = trim($clientName ?? '');
        $nit = trim($nit ?? '');
        $amount = (float) $amount;

        if ($clientName === '') {
            return false;
        }

        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;

        $existing = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_client_opening_balance'))
            ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
        if ($nit !== '') {
            $existing->where($db->quoteName('nit') . ' = ' . $db->quote($nit));
        } else {
            $existing->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
        }
        $db->setQuery($existing);
        $id = (int) $db->loadResult();

        if ($id > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_client_opening_balance'))
                ->set($db->quoteName('amount_paid_to_dec31_2025') . ' = ' . $amount)
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . $userId)
                ->where($db->quoteName('id') . ' = ' . $id);
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_client_opening_balance'))
                ->columns([
                    $db->quoteName('client_name'),
                    $db->quoteName('nit'),
                    $db->quoteName('amount_paid_to_dec31_2025'),
                    $db->quoteName('created_by')
                ])
                ->values(
                    $db->quote($clientName) . ',' .
                    $db->quote($nit) . ',' .
                    $amount . ',' .
                    $userId
                );
        }
        $db->setQuery($query);
        $db->execute();
        if ($refreshBalances) {
            $this->refreshClientBalances();
        }
        return true;
    }

    /**
     * Initialize opening balances: set amount_paid_to_dec31_2025 = sum of invoice_value
     * for orders Oct 1 - Dec 31 2025 per client. Ensures balance starts at 0 on Jan 1 2026.
     *
     * @return  int  Number of clients updated
     *
     * @since   3.56.0
     */
    public function initializeOpeningBalances()
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $this->ensureClientOpeningBalanceTableExists($db);

        $map = $this->getInvoiceValueOctDec2025ByClientMap($db);
        $count = 0;
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;

        foreach ($map as $key => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $parts = explode('|', $key, 2);
            $clientName = $parts[0] ?? '';
            $nit = $parts[1] ?? '';
            if ($clientName === '') {
                continue;
            }
            $this->saveOpeningBalance($clientName, $nit, $amount, false);
            $count++;
        }

        $this->refreshClientBalances();
        return $count;
    }

    /**
     * Merge multiple clients into one target. Updates ordenes and logs to client_merges.
     * Only call from controller after super user check.
     *
     * @param   array   $sources  Array of ['client_name' => x, 'nit' => y] to merge from
     * @param   string  $targetClientName  Target client name
     * @param   string  $targetNit  Target NIT
     *
     * @return  int  Total orders updated
     *
     * @since   3.55.0
     */
    public function mergeClients(array $sources, $targetClientName, $targetNit)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $totalUpdated = 0;

        $this->ensureClientMergesTableExists($db);
        $this->ensureClientOpeningBalanceTableExists($db);

        $openingBalanceToAdd = 0.0;

        foreach ($sources as $src) {
            $srcName = trim($src['client_name'] ?? '');
            $srcNit = isset($src['nit']) ? trim($src['nit']) : null;

            if ($srcName === '' || ($srcName === $targetClientName && ($srcNit === $targetNit || ($srcNit === null && $targetNit === null)))) {
                continue;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->set($db->quoteName('client_name') . ' = ' . $db->quote($targetClientName))
                ->set($db->quoteName('nit') . ' = ' . $db->quote($targetNit ?? ''))
                ->where($db->quoteName('client_name') . ' = ' . $db->quote($srcName))
                ->where($db->quoteName('state') . ' = 1');

            if ($srcNit !== null && $srcNit !== '') {
                $query->where($db->quoteName('nit') . ' = ' . $db->quote($srcNit));
            } else {
                $query->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
            }

            $db->setQuery($query);
            $updated = $db->execute();
            $affected = $updated ? $db->getAffectedRows() : 0;
            $totalUpdated += $affected;

            if ($affected > 0) {
                $this->logClientMerge($db, $srcName, $srcNit, $targetClientName, $targetNit, $affected, $user->id);
                $openingBalanceToAdd += $this->getAndRemoveOpeningBalance($db, $srcName, $srcNit);
                $this->removeClientBalance($db, $srcName, $srcNit);
            }
        }

        if ($openingBalanceToAdd > 0) {
            $this->addToTargetOpeningBalance($db, $targetClientName, $targetNit, $openingBalanceToAdd, $user->id);
        }

        $this->refreshClientBalances();
        return $totalUpdated;
    }

    /**
     * Remove client balance row (used when merging - source client no longer exists).
     */
    protected function removeClientBalance($db, $clientName, $nit)
    {
        if (!$this->hasTable($db, '#__ordenproduccion_client_balance')) {
            return;
        }
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ordenproduccion_client_balance'))
            ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
        if ($nit !== '' && $nit !== null) {
            $query->where($db->quoteName('nit') . ' = ' . $db->quote($nit));
        } else {
            $query->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
        }
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Get and remove opening balance for a client (used when merging into another).
     *
     * @param   object  $db   Database
     * @param   string  $clientName  Client name
     * @param   string  $nit         Client NIT
     *
     * @return  float  Amount that was stored
     */
    protected function getAndRemoveOpeningBalance($db, $clientName, $nit)
    {
        if (!$this->hasTable($db, '#__ordenproduccion_client_opening_balance')) {
            return 0.0;
        }
        $key = $this->clientKey($clientName, $nit);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id') . ',' . $db->quoteName('amount_paid_to_dec31_2025'))
            ->from($db->quoteName('#__ordenproduccion_client_opening_balance'))
            ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
        if ($nit !== '' && $nit !== null) {
            $query->where($db->quoteName('nit') . ' = ' . $db->quote($nit));
        } else {
            $query->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
        }
        $db->setQuery($query);
        $row = $db->loadObject();
        if (!$row) {
            return 0.0;
        }
        $amount = (float) ($row->amount_paid_to_dec31_2025 ?? 0);
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_client_opening_balance'))
                ->where($db->quoteName('id') . ' = ' . (int) $row->id)
        );
        $db->execute();
        return $amount;
    }

    /**
     * Add amount to target client's opening balance (create or update).
     */
    protected function addToTargetOpeningBalance($db, $targetClientName, $targetNit, $amountToAdd, $userId)
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id') . ',' . $db->quoteName('amount_paid_to_dec31_2025'))
            ->from($db->quoteName('#__ordenproduccion_client_opening_balance'))
            ->where($db->quoteName('client_name') . ' = ' . $db->quote($targetClientName));
        if (($targetNit ?? '') !== '') {
            $query->where($db->quoteName('nit') . ' = ' . $db->quote($targetNit));
        } else {
            $query->where('(' . $db->quoteName('nit') . ' IS NULL OR ' . $db->quoteName('nit') . ' = ' . $db->quote('') . ')');
        }
        $db->setQuery($query);
        $row = $db->loadObject();
        $now = Factory::getDate()->toSql();
        if ($row) {
            $newAmount = (float) ($row->amount_paid_to_dec31_2025 ?? 0) + $amountToAdd;
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_client_opening_balance'))
                    ->set($db->quoteName('amount_paid_to_dec31_2025') . ' = ' . $newAmount)
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . $userId)
                    ->where($db->quoteName('id') . ' = ' . (int) $row->id)
            );
            $db->execute();
        } else {
            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_client_opening_balance'))
                    ->columns([
                        $db->quoteName('client_name'),
                        $db->quoteName('nit'),
                        $db->quoteName('amount_paid_to_dec31_2025'),
                        $db->quoteName('created_by')
                    ])
                    ->values(
                        $db->quote($targetClientName) . ',' .
                        $db->quote($targetNit ?? '') . ',' .
                        $amountToAdd . ',' .
                        (int) $userId
                    )
            );
            $db->execute();
        }
    }

    /**
     * Ensure client_merges table exists; create if not.
     *
     * @param   object  $db  Database instance
     *
     * @since   3.55.0
     */
    protected function ensureClientMergesTableExists($db)
    {
        $table = $db->replacePrefix('#__ordenproduccion_client_merges');
        $tables = $db->getTableList();
        foreach ($tables as $t) {
            if (strcasecmp($t, $table) === 0) {
                return;
            }
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $db->quoteName($table) . ' (
            id int(11) NOT NULL AUTO_INCREMENT,
            source_client_name varchar(255) NOT NULL,
            source_nit varchar(100) DEFAULT NULL,
            target_client_name varchar(255) NOT NULL,
            target_nit varchar(100) DEFAULT NULL,
            orders_updated int(11) NOT NULL DEFAULT 0,
            merged_by int(11) NOT NULL,
            merged_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_merged_at (merged_at),
            KEY idx_merged_by (merged_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->setQuery($sql);
        $db->execute();
    }

    /**
     * Log a client merge to the audit table.
     *
     * @param   object  $db       Database instance
     * @param   string  $srcName  Source client name
     * @param   string  $srcNit   Source NIT
     * @param   string  $tgtName  Target client name
     * @param   string  $tgtNit   Target NIT
     * @param   int     $ordersUpdated  Count of orders updated
     * @param   int     $mergedBy  User ID
     *
     * @since   3.55.0
     */
    protected function logClientMerge($db, $srcName, $srcNit, $tgtName, $tgtNit, $ordersUpdated, $mergedBy)
    {
        $srcNitVal = ($srcNit !== null && $srcNit !== '') ? $db->quote($srcNit) : 'NULL';
        $tgtNitVal = ($tgtNit !== null && $tgtNit !== '') ? $db->quote($tgtNit) : 'NULL';
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ordenproduccion_client_merges'))
            ->columns([$db->quoteName('source_client_name'), $db->quoteName('source_nit'), $db->quoteName('target_client_name'), $db->quoteName('target_nit'), $db->quoteName('orders_updated'), $db->quoteName('merged_by')])
            ->values($db->quote($srcName) . ',' . $srcNitVal . ',' . $db->quote($tgtName) . ',' . $tgtNitVal . ',' . (int) $ordersUpdated . ',' . (int) $mergedBy);
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Get distinct client names that have work orders in the given date range (for report autocomplete)
     *
     * @param   string  $dateFrom  Start date (Y-m-d)
     * @param   string  $dateTo    End date (Y-m-d)
     * @param   string  $search    Optional search string to filter client names (LIKE %search%)
     *
     * @return  array  List of client names
     *
     * @since   3.6.0
     */
    public function getReportClientsInDateRange($dateFrom, $dateTo, $search = '')
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('client_name'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('client_name') . ' IS NOT NULL')
            ->where($db->quoteName('client_name') . ' != ' . $db->quote(''));

        if (!empty($dateFrom)) {
            $query->where($db->quoteName('created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }
        if (!empty($dateTo)) {
            $query->where($db->quoteName('created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }
        if ($search !== '') {
            $searchEscaped = $db->quote('%' . $db->escape(trim($search), true) . '%');
            $query->where($db->quoteName('client_name') . ' LIKE ' . $searchEscaped);
        }

        $query->group($db->quoteName('client_name'))
            ->order($db->quoteName('client_name') . ' ASC')
            ->setLimit(30);
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_values($rows);
    }

    /**
     * Get distinct NITs that have work orders in the given date range (for report autocomplete)
     *
     * @param   string  $dateFrom  Start date (Y-m-d)
     * @param   string  $dateTo    End date (Y-m-d)
     * @param   string  $search    Optional search string to filter NIT (LIKE %search%)
     *
     * @return  array  List of NIT strings
     *
     * @since   3.6.0
     */
    public function getReportNitsInDateRange($dateFrom, $dateTo, $search = '')
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('nit'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('(' . $db->quoteName('nit') . ' IS NOT NULL AND ' . $db->quoteName('nit') . ' != ' . $db->quote('') . ')');

        if (!empty($dateFrom)) {
            $query->where($db->quoteName('created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }
        if (!empty($dateTo)) {
            $query->where($db->quoteName('created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }
        if ($search !== '') {
            $searchEscaped = $db->quote('%' . $db->escape(trim($search), true) . '%');
            $query->where($db->quoteName('nit') . ' LIKE ' . $searchEscaped);
        }

        $query->group($db->quoteName('nit'))
            ->order($db->quoteName('nit') . ' ASC')
            ->setLimit(30);
        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];
        return array_values($rows);
    }

    /**
     * Get top 10 clients trend data
     *
     * @param   int  $year   Year
     * @param   int  $month  Month (0 for yearly view)
     *
     * @return  array  Trend data by client
     *
     * @since   3.52.7
     */
    protected function getClientTrend($year, $month)
    {
        $db = Factory::getDbo();
        
        // Determine if we're showing yearly (12 months) or monthly (days) view
        $isMonthlyView = ($month > 0);
        
        if ($isMonthlyView) {
            // Monthly view: show daily data for selected month
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $labels = range(1, $daysInMonth);
            
            // Get top 10 clients for this month
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $startDate = $year . '-' . $monthStr . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('client_name'),
                    'SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total_value'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
                ->where($db->quoteName('client_name') . ' IS NOT NULL')
                ->where($db->quoteName('client_name') . ' != ' . $db->quote(''))
                ->group($db->quoteName('client_name'))
                ->order('total_value DESC')
                ->setLimit(10);
            $db->setQuery($query);
            $topClients = $db->loadObjectList('client_name') ?: [];
            
            // Get daily data for each client
            $trendData = [];
            foreach (array_keys($topClients) as $clientName) {
                $clientTrend = ['client_name' => $clientName, 'data' => []];
                
                foreach ($labels as $day) {
                    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $dateStr = $year . '-' . $monthStr . '-' . $dayStr;
                    
                    $query = $db->getQuery(true)
                        ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('state') . ' = 1')
                        ->where('DATE(' . $db->quoteName('created') . ') = ' . $db->quote($dateStr))
                        ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
                    $db->setQuery($query);
                    $total = $db->loadResult() ?: 0;
                    
                    $clientTrend['data'][] = (float) $total;
                }
                
                $trendData[] = $clientTrend;
            }
        } else {
            // Yearly view: show monthly data for selected year
            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            
            // Get top 10 clients for this year
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('client_name'),
                    'SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total_value'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->where('YEAR(' . $db->quoteName('created') . ') = ' . $year)
                ->where($db->quoteName('client_name') . ' IS NOT NULL')
                ->where($db->quoteName('client_name') . ' != ' . $db->quote(''))
                ->group($db->quoteName('client_name'))
                ->order('total_value DESC')
                ->setLimit(10);
            $db->setQuery($query);
            $topClients = $db->loadObjectList('client_name') ?: [];
            
            // Get monthly data for each client
            $trendData = [];
            foreach (array_keys($topClients) as $clientName) {
                $clientTrend = ['client_name' => $clientName, 'data' => []];
                
                for ($m = 1; $m <= 12; $m++) {
                    $monthStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $startDate = $year . '-' . $monthStr . '-01';
                    $endDate = date('Y-m-t', strtotime($startDate));
                    
                    $query = $db->getQuery(true)
                        ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('state') . ' = 1')
                        ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                        ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
                        ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
                    $db->setQuery($query);
                    $total = $db->loadResult() ?: 0;
                    
                    $clientTrend['data'][] = (float) $total;
                }
                
                $trendData[] = $clientTrend;
            }
        }

        return [
            'labels' => $labels,
            'clients' => $trendData,
            'view' => $isMonthlyView ? 'daily' : 'monthly'
        ];
    }

    /**
     * Get sales agents annual trend data (yearly view only)
     *
     * @param   int  $year   Year
     *
     * @return  array  Annual trend data by agent
     *
     * @since   3.52.8
     */
    protected function getAgentAnnualTrend($year, $salesAgent = null)
    {
        $db = Factory::getDbo();
        
        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('YEAR(' . $db->quoteName('created') . ') = ' . (int) $year)
            ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->order($db->quoteName('sales_agent'));
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $agents = $db->loadColumn() ?: [];
        
        // Get monthly data for each agent
        $trendData = [];
        foreach ($agents as $agentName) {
            $agentTrend = ['agent_name' => $agentName, 'data' => []];
            
            for ($m = 1; $m <= 12; $m++) {
                $monthStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                $startDate = $year . '-' . $monthStr . '-01';
                $endDate = date('Y-m-t', strtotime($startDate));
                
                $query = $db->getQuery(true)
                    ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
                    ->where($db->quoteName('sales_agent') . ' = ' . $db->quote($agentName));
                $db->setQuery($query);
                $total = $db->loadResult() ?: 0;
                
                // Ensure we're not getting inflated values
                $cleanTotal = (float) $total;
                
                // Debug output (remove in production)
                if (Factory::getApplication()->get('debug')) {
                    error_log("Agent: $agentName, Month: $m, Total: $cleanTotal");
                }
                
                $agentTrend['data'][] = $cleanTotal;
            }
            
            $trendData[] = $agentTrend;
        }

        return [
            'labels' => $labels,
            'agents' => $trendData
        ];
    }

    /**
     * Get top 10 clients annual trend data (yearly view only)
     *
     * @param   int  $year   Year
     *
     * @return  array  Annual trend data by client
     *
     * @since   3.52.11
     */
    protected function getClientAnnualTrend($year, $salesAgent = null)
    {
        $db = Factory::getDbo();
        
        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('client_name'),
                'SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total_value'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('YEAR(' . $db->quoteName('created') . ') = ' . (int) $year)
            ->where($db->quoteName('client_name') . ' IS NOT NULL')
            ->where($db->quoteName('client_name') . ' != ' . $db->quote(''))
            ->group($db->quoteName('client_name'))
            ->order('total_value DESC')
            ->setLimit(10);
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $topClients = $db->loadObjectList('client_name') ?: [];
        
        // Get monthly data for each client
        $trendData = [];
        foreach (array_keys($topClients) as $clientName) {
            $clientTrend = ['client_name' => $clientName, 'data' => []];
            
            for ($m = 1; $m <= 12; $m++) {
                $monthStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                $startDate = $year . '-' . $monthStr . '-01';
                $endDate = date('Y-m-t', strtotime($startDate));
                
                $query = $db->getQuery(true)
                    ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
                    ->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
                $db->setQuery($query);
                $total = $db->loadResult() ?: 0;
                
                // Ensure we're not getting inflated values
                $cleanTotal = (float) $total;
                
                // Debug output (remove in production)
                if (Factory::getApplication()->get('debug')) {
                    error_log("Client: $clientName, Month: $m, Total: $cleanTotal");
                }
                
                $clientTrend['data'][] = $cleanTotal;
            }
            
            $trendData[] = $clientTrend;
        }

        return [
            'labels' => $labels,
            'clients' => $trendData
        ];
    }

    /**
     * Get activity statistics for daily, weekly, and monthly views
     *
     * @param   string       $period      Period to get stats for: 'day', 'week', or 'month'
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: own data only)
     *
     * @return  object  Activity statistics data
     *
     * @since   3.6.0
     */
    public function getActivityStatistics($period = 'week', $salesAgent = null)
    {
        $weekStart = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
        $weekEnd = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
        $previousWeekStart = date('Y-m-d', strtotime('monday last week')) . ' 00:00:00';
        $previousWeekEnd = date('Y-m-d', strtotime('sunday last week')) . ' 23:59:59';
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd = date('Y-m-t') . ' 23:59:59';
        $yearStart = date('Y-01-01') . ' 00:00:00';
        $yearEnd = date('Y-12-31') . ' 23:59:59';

        $stats = new \stdClass();
        $stats->weekly = $this->getActivityStatsForPeriod($weekStart, $weekEnd, $salesAgent);
        $stats->previousWeekly = $this->getActivityStatsForPeriod($previousWeekStart, $previousWeekEnd, $salesAgent);
        $stats->monthly = $this->getActivityStatsForPeriod($monthStart, $monthEnd, $salesAgent);
        $stats->yearly = $this->getActivityStatsForPeriod($yearStart, $yearEnd, $salesAgent);

        return $stats;
    }

    /**
     * Get activity statistics grouped by sales agent for a period
     *
     * @param   string       $period      Period to get stats for: 'day', 'week', or 'month'
     * @param   string|null  $salesAgent  Optional sales agent filter (Ventas: only this agent)
     *
     * @return  array  Activity statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    public function getActivityStatisticsByAgent($period = 'week', $salesAgent = null)
    {
        switch ($period) {
            case 'previous_week':
                $startDate = date('Y-m-d', strtotime('monday last week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday last week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'year':
                $startDate = date('Y-01-01') . ' 00:00:00';
                $endDate = date('Y-12-31') . ' 23:59:59';
                break;
            case 'week':
            default:
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
        }

        return $this->getActivityStatsByAgentForPeriod($startDate, $endDate, $salesAgent);
    }

    /**
     * Get activity statistics for a specific date range
     *
     * @param   string       $startDate   Start date (Y-m-d H:i:s)
     * @param   string       $endDate     End date (Y-m-d H:i:s)
     * @param   string|null  $salesAgent  Optional sales agent filter
     *
     * @return  object  Activity statistics for the period
     *
     * @since   3.6.0
     */
    protected function getActivityStatsForPeriod($startDate, $endDate, $salesAgent = null)
    {
        $db = Factory::getDbo();
        $stats = new \stdClass();
        $salesAgentFilter = null;
        if (func_num_args() >= 3) {
            $arg = func_get_arg(2);
            if ($arg !== null && $arg !== '') {
                $salesAgentFilter = $arg;
            }
        }

        // 1. Work orders created
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $stats->workOrdersCreated = (int) $db->loadResult();

        // 2. Status changes (from historial table)
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_historial'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
        $db->setQuery($query);
        $stats->statusChanges = (int) $db->loadResult();

        // 3. Payment proofs recorded
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
        $db->setQuery($query);
        $stats->paymentProofsRecorded = (int) $db->loadResult();

        // 4. Amount of money generated from new orders (invoice_value sum)
        $query = $db->getQuery(true)
            ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where($db->quoteName('invoice_value') . ' IS NOT NULL')
            ->where($db->quoteName('invoice_value') . ' > 0');
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }
        $db->setQuery($query);
        $stats->moneyGenerated = (float) ($db->loadResult() ?: 0);

        // 5. Value of money collected via payment proofs (payment_amount sum)
        $query = $db->getQuery(true)
            ->select('SUM(CAST(' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))) as total')
            ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where($db->quoteName('payment_amount') . ' IS NOT NULL')
            ->where($db->quoteName('payment_amount') . ' > 0');
        $db->setQuery($query);
        $stats->moneyCollected = (float) ($db->loadResult() ?: 0);

        // 6. Shipping slips printed - full (completo)
        // Check historial for print events with tipo_envio = completo
        // The event_description contains "Envio completo impreso via" or metadata has tipo_envio = completo
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_historial'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio completo%') . 
                    ' OR ' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio":"completo"%') . 
                    ' OR ' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%\"tipo_envio\":\"completo\"%') . ')');
        $db->setQuery($query);
        $stats->shippingSlipsFull = (int) $db->loadResult();

        // 7. Shipping slips printed - partial (parcial)
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_historial'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio parcial%') . 
                    ' OR ' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio":"parcial"%') . 
                    ' OR ' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%\"tipo_envio\":\"parcial\"%') . ')');
        $db->setQuery($query);
        $stats->shippingSlipsPartial = (int) $db->loadResult();

        // 8. Alternative: Check for print events with tipo_envio in metadata
        // If the above doesn't work well, we'll also check for any print events related to shipping
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_historial'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('event_type') . ' = ' . $db->quote('print'))
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%impreso%') . 
                    ' OR ' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio"%') . ')');
        $db->setQuery($query);
        $printEvents = (int) $db->loadResult();

        // If we didn't get good results from the specific queries, use print events as fallback
        if ($stats->shippingSlipsFull == 0 && $stats->shippingSlipsPartial == 0 && $printEvents > 0) {
            // Try to parse metadata to determine tipo_envio
            $query = $db->getQuery(true)
                ->select($db->quoteName('metadata'))
                ->from($db->quoteName('#__ordenproduccion_historial'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('event_type') . ' = ' . $db->quote('print'))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->where($db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio"%'));
            $db->setQuery($query);
            $metadataList = $db->loadColumn() ?: [];
            
            $fullCount = 0;
            $partialCount = 0;
            foreach ($metadataList as $metadata) {
                $meta = json_decode($metadata, true);
                if (isset($meta['tipo_envio'])) {
                    if ($meta['tipo_envio'] === 'completo') {
                        $fullCount++;
                    } elseif ($meta['tipo_envio'] === 'parcial') {
                        $partialCount++;
                    }
                }
            }
            
            if ($fullCount > 0 || $partialCount > 0) {
                $stats->shippingSlipsFull = $fullCount;
                $stats->shippingSlipsPartial = $partialCount;
            }
        }

        return $stats;
    }

    /**
     * Get activity statistics grouped by sales agent for a specific date range
     *
     * @param   string       $startDate   Start date (Y-m-d H:i:s)
     * @param   string       $endDate     End date (Y-m-d H:i:s)
     * @param   string|null  $salesAgent  Optional sales agent filter (only this agent)
     *
     * @return  array  Activity statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    protected function getActivityStatsByAgentForPeriod($startDate, $endDate, $salesAgent = null)
    {
        $db = Factory::getDbo();
        $agentsStats = [];

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(' '))
            ->order($db->quoteName('sales_agent') . ' ASC');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $agents = $db->loadColumn() ?: [];

        foreach ($agents as $agent) {
            $agentStats = new \stdClass();
            $agentStats->salesAgent = $agent;

            // Get orders for this agent in the period
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('orden_de_trabajo'),
                    $db->quoteName('order_number'),
                    $db->quoteName('work_description'),
                    $db->quoteName('invoice_value'),
                    $db->quoteName('status'),
                    $db->quoteName('created'),
                    $db->quoteName('client_name')
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->order($db->quoteName('orden_de_trabajo') . ' DESC');
            $db->setQuery($query);
            $orders = $db->loadObjectList() ?: [];

            $agentStats->orders = $orders;
            $agentStats->workOrdersCreated = count($orders);
            
            // Calculate money generated (sum of invoice_value)
            $moneyGenerated = 0;
            foreach ($orders as $order) {
                if (!empty($order->invoice_value) && $order->invoice_value > 0) {
                    $moneyGenerated += (float) $order->invoice_value;
                }
            }
            $agentStats->moneyGenerated = $moneyGenerated;

            // Get status changes for orders of this agent
            if (!empty($orders)) {
                $orderIds = array_map(function($order) { return $order->id; }, $orders);
                $query = $db->getQuery(true)
                    ->select('COUNT(*) as total')
                    ->from($db->quoteName('#__ordenproduccion_historial'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
                    ->where($db->quoteName('order_id') . ' IN (' . implode(',', array_map('intval', $orderIds)) . ')')
                    ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
                $db->setQuery($query);
                $agentStats->statusChanges = (int) $db->loadResult();
            } else {
                $agentStats->statusChanges = 0;
            }

            // Get payment proofs for orders of this agent
            if (!empty($orders)) {
                $orderIds = array_map(function($order) { return $order->id; }, $orders);
                $query = $db->getQuery(true)
                    ->select('COUNT(*) as total')
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('order_id') . ' IN (' . implode(',', array_map('intval', $orderIds)) . ')')
                    ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
                $db->setQuery($query);
                $agentStats->paymentProofsRecorded = (int) $db->loadResult();

                // Get money collected for this agent
                $query = $db->getQuery(true)
                    ->select('SUM(CAST(' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))) as total')
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('order_id') . ' IN (' . implode(',', array_map('intval', $orderIds)) . ')')
                    ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
                    ->where($db->quoteName('payment_amount') . ' IS NOT NULL')
                    ->where($db->quoteName('payment_amount') . ' > 0');
                $db->setQuery($query);
                $agentStats->moneyCollected = (float) ($db->loadResult() ?: 0);
            } else {
                $agentStats->paymentProofsRecorded = 0;
                $agentStats->moneyCollected = 0;
            }

            $agentsStats[] = $agentStats;
        }

        // Also get stats for orders without sales agent
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('orden_de_trabajo'),
                $db->quoteName('order_number'),
                $db->quoteName('work_description'),
                $db->quoteName('invoice_value'),
                $db->quoteName('status'),
                $db->quoteName('created'),
                $db->quoteName('client_name')
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('(' . $db->quoteName('sales_agent') . ' IS NULL OR ' . 
                   $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR ' .
                   $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->order($db->quoteName('orden_de_trabajo') . ' DESC');
        $db->setQuery($query);
        $noAgentOrders = $db->loadObjectList() ?: [];

        if (!empty($noAgentOrders)) {
            $noAgentStats = new \stdClass();
            $noAgentStats->salesAgent = null; // or 'Sin Agente'
            $noAgentStats->orders = $noAgentOrders;
            $noAgentStats->workOrdersCreated = count($noAgentOrders);
            
            $moneyGenerated = 0;
            foreach ($noAgentOrders as $order) {
                if (!empty($order->invoice_value) && $order->invoice_value > 0) {
                    $moneyGenerated += (float) $order->invoice_value;
                }
            }
            $noAgentStats->moneyGenerated = $moneyGenerated;

            $orderIds = array_map(function($order) { return $order->id; }, $noAgentOrders);
            $query = $db->getQuery(true)
                ->select('COUNT(*) as total')
                ->from($db->quoteName('#__ordenproduccion_historial'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
                ->where($db->quoteName('order_id') . ' IN (' . implode(',', array_map('intval', $orderIds)) . ')')
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
            $db->setQuery($query);
            $noAgentStats->statusChanges = (int) $db->loadResult();

            $query = $db->getQuery(true)
                ->select('COUNT(*) as total')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('order_id') . ' IN (' . implode(',', array_map('intval', $orderIds)) . ')')
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
            $db->setQuery($query);
            $noAgentStats->paymentProofsRecorded = (int) $db->loadResult();

            $query = $db->getQuery(true)
                ->select('SUM(CAST(' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))) as total')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('order_id') . ' IN (' . implode(',', array_map('intval', $orderIds)) . ')')
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->where($db->quoteName('payment_amount') . ' IS NOT NULL')
                ->where($db->quoteName('payment_amount') . ' > 0');
            $db->setQuery($query);
            $noAgentStats->moneyCollected = (float) ($db->loadResult() ?: 0);

            $agentsStats[] = $noAgentStats;
        }

        return $agentsStats;
    }

    /**
     * Get status changes statistics grouped by sales agent for a period
     *
     * @param   string  $period  Period to get stats for: 'day', 'week', or 'month'
     *
     * @return  array  Status changes statistics grouped by sales agent and status
     *
     * @since   3.6.0
     */
    public function getStatusChangesByAgent($period = 'week', $salesAgent = null)
    {
        switch ($period) {
            case 'previous_week':
                $startDate = date('Y-m-d', strtotime('monday last week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday last week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'year':
                $startDate = date('Y-01-01') . ' 00:00:00';
                $endDate = date('Y-12-31') . ' 23:59:59';
                break;
            case 'week':
            default:
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
        }

        return $this->getStatusChangesByAgentForPeriod($startDate, $endDate, $salesAgent);
    }

    /**
     * Get status changes statistics grouped by sales agent for a specific date range
     *
     * @param   string  $startDate  Start date (Y-m-d H:i:s)
     * @param   string  $endDate    End date (Y-m-d H:i:s)
     *
     * @return  array  Status changes statistics grouped by sales agent and status
     *
     * @since   3.6.0
     */
    protected function getStatusChangesByAgentForPeriod($startDate, $endDate, $salesAgent = null)
    {
        $db = Factory::getDbo();
        $agentsStats = [];

        $query = $db->getQuery(true)
            ->select('DISTINCT o.' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('h.' . $db->quoteName('state') . ' = 1')
            ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
            ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('o.' . $db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(' '))
            ->order('o.' . $db->quoteName('sales_agent') . ' ASC');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $agents = $db->loadColumn() ?: [];

        // Get all status values that have occurred
        $query = $db->getQuery(true)
            ->select('DISTINCT h.' . $db->quoteName('event_description'))
            ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
            ->where('h.' . $db->quoteName('state') . ' = 1')
            ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
            ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->order('h.' . $db->quoteName('event_description') . ' ASC');
        $db->setQuery($query);
        $allStatuses = $db->loadColumn() ?: [];

        foreach ($agents as $agent) {
            $agentStats = new \stdClass();
            $agentStats->salesAgent = $agent;
            $agentStats->statusCounts = [];

            // Get status counts for this agent
            foreach ($allStatuses as $status) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*) as total')
                    ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
                    ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                    ->where('h.' . $db->quoteName('state') . ' = 1')
                    ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
                    ->where('h.' . $db->quoteName('event_description') . ' = ' . $db->quote($status))
                    ->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                    ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate));
                $db->setQuery($query);
                $agentStats->statusCounts[$status] = (int) $db->loadResult();
            }

            // Get total status changes
            $query = $db->getQuery(true)
                ->select('COUNT(*) as total')
                ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
                ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                ->where('h.' . $db->quoteName('state') . ' = 1')
                ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
                ->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate));
            $db->setQuery($query);
            $agentStats->totalStatusChanges = (int) $db->loadResult();

            $agentsStats[] = $agentStats;
        }

        // Get status changes without agent
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('h.' . $db->quoteName('state') . ' = 1')
            ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
            ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . 
                   $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' .
                   $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')');
        $db->setQuery($query);
        $noAgentCount = (int) $db->loadResult();

        if ($noAgentCount > 0) {
            $noAgentStats = new \stdClass();
            $noAgentStats->salesAgent = null;
            $noAgentStats->statusCounts = [];
            foreach ($allStatuses as $status) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*) as total')
                    ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
                    ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                    ->where('h.' . $db->quoteName('state') . ' = 1')
                    ->where('h.' . $db->quoteName('event_type') . ' = ' . $db->quote('status_change'))
                    ->where('h.' . $db->quoteName('event_description') . ' = ' . $db->quote($status))
                    ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . 
                           $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' .
                           $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
                    ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate));
                $db->setQuery($query);
                $noAgentStats->statusCounts[$status] = (int) $db->loadResult();
            }
            $noAgentStats->totalStatusChanges = $noAgentCount;
            $agentsStats[] = $noAgentStats;
        }

        // Add all statuses to the result for easy access in template
        $result = new \stdClass();
        $result->agents = $agentsStats;
        $result->allStatuses = $allStatuses;

        return $result;
    }

    /**
     * Get payment proofs statistics grouped by sales agent for a period
     *
     * @param   string  $period  Period to get stats for: 'day', 'week', or 'month'
     *
     * @return  array  Payment proofs statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    public function getPaymentProofsByAgent($period = 'week', $salesAgent = null)
    {
        switch ($period) {
            case 'previous_week':
                $startDate = date('Y-m-d', strtotime('monday last week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday last week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'year':
                $startDate = date('Y-01-01') . ' 00:00:00';
                $endDate = date('Y-12-31') . ' 23:59:59';
                break;
            case 'week':
            default:
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
        }

        return $this->getPaymentProofsByAgentForPeriod($startDate, $endDate, $salesAgent);
    }

    /**
     * Get payment proofs statistics grouped by sales agent for a specific date range
     *
     * @param   string  $startDate  Start date (Y-m-d H:i:s)
     * @param   string  $endDate    End date (Y-m-d H:i:s)
     *
     * @return  array  Payment proofs statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    protected function getPaymentProofsByAgentForPeriod($startDate, $endDate, $salesAgent = null)
    {
        $db = Factory::getDbo();
        $agentsStats = [];
        $ppCols = $db->getTableColumns('#__ordenproduccion_payment_proofs', false);
        $hasVerificationStatus = isset($ppCols['verification_status']);
        $hasVerifiedDate = isset($ppCols['verified_date']);
        $hasPaymentOrders = $this->hasTable($db, '#__ordenproduccion_payment_orders');

        // Agent is always from the orden de trabajo (work order), not who entered the payment.
        // When payment_orders exists, link proofs via that table and use first linked order's sales_agent.
        if ($hasPaymentOrders) {
            return $this->getPaymentProofsByAgentForPeriodViaPaymentOrders(
                $db,
                $startDate,
                $endDate,
                $salesAgent,
                $hasVerificationStatus,
                $hasVerifiedDate
            );
        }

        // Legacy: proofs linked to orders only via pp.order_id
        $query = $db->getQuery(true)
            ->select('DISTINCT o.' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('pp.' . $db->quoteName('state') . ' = 1')
            ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('o.' . $db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(' '))
            ->order('o.' . $db->quoteName('sales_agent') . ' ASC');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $agents = $db->loadColumn() ?: [];

        $verificadoWhere = 'LOWER(TRIM(pp.' . $db->quoteName('verification_status') . ')) = ' . $db->quote('verificado');
        if ($hasVerifiedDate) {
            $verificadoWhere .= ' AND pp.' . $db->quoteName('verified_date') . ' >= ' . $db->quote($startDate)
                . ' AND pp.' . $db->quoteName('verified_date') . ' <= ' . $db->quote($endDate);
        }

        foreach ($agents as $agent) {
            $agentStats = new \stdClass();
            $agentStats->salesAgent = $agent;

            $selectProofs = [
                'pp.' . $db->quoteName('id'),
                'pp.' . $db->quoteName('order_id'),
                'pp.' . $db->quoteName('payment_amount'),
                'pp.' . $db->quoteName('created'),
                'o.' . $db->quoteName('orden_de_trabajo'),
                'o.' . $db->quoteName('order_number'),
                'o.' . $db->quoteName('work_description')
            ];
            if ($hasVerificationStatus) {
                $selectProofs[] = 'pp.' . $db->quoteName('verification_status');
            }
            $query = $db->getQuery(true)
                ->select($selectProofs)
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                ->where('pp.' . $db->quoteName('state') . ' = 1')
                ->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->order('pp.' . $db->quoteName('created') . ' DESC');
            $db->setQuery($query);
            $paymentProofs = $db->loadObjectList() ?: [];

            $agentStats->paymentProofs = $paymentProofs;
            $agentStats->paymentProofsCount = count($paymentProofs);

            $query = $db->getQuery(true)
                ->select('SUM(CAST(pp.' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))) as total')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                ->where('pp.' . $db->quoteName('state') . ' = 1')
                ->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->where('pp.' . $db->quoteName('payment_amount') . ' IS NOT NULL')
                ->where('pp.' . $db->quoteName('payment_amount') . ' > 0');
            $db->setQuery($query);
            $agentStats->moneyCollected = (float) ($db->loadResult() ?: 0);

            // Ingresado = all payment proofs recorded in the period (regardless of verification status)
            $agentStats->ingresadoCount = $agentStats->paymentProofsCount;
            $agentStats->ingresadoAmount = $agentStats->moneyCollected;
            $agentStats->verificadoCount = 0;
            $agentStats->verificadoAmount = 0.0;
            if ($hasVerificationStatus) {
                $baseQuery = function () use ($db, $agent, $startDate, $endDate) {
                    return $db->getQuery(true)
                        ->select('COUNT(*) as cnt, COALESCE(SUM(CAST(pp.' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))), 0) as total')
                        ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                        ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                        ->where('pp.' . $db->quoteName('state') . ' = 1')
                        ->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                        ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                        ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate));
                };
                $query = $baseQuery()->where($verificadoWhere);
                $db->setQuery($query);
                $row = $db->loadObject();
                if ($row) {
                    $agentStats->verificadoCount = (int) $row->cnt;
                    $agentStats->verificadoAmount = (float) $row->total;
                }
            }

            $agentsStats[] = $agentStats;
        }

        // Proofs without agent (legacy: only when joined via pp.order_id and that order has no agent)
        $selectNoAgent = [
            'pp.' . $db->quoteName('id'),
            'pp.' . $db->quoteName('order_id'),
            'pp.' . $db->quoteName('payment_amount'),
            'pp.' . $db->quoteName('created'),
            'o.' . $db->quoteName('orden_de_trabajo'),
            'o.' . $db->quoteName('order_number'),
            'o.' . $db->quoteName('work_description')
        ];
        if ($hasVerificationStatus) {
            $selectNoAgent[] = 'pp.' . $db->quoteName('verification_status');
        }
        $query = $db->getQuery(true)
            ->select($selectNoAgent)
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('pp.' . $db->quoteName('state') . ' = 1')
            ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
            ->order('pp.' . $db->quoteName('created') . ' DESC');
        $db->setQuery($query);
        $noAgentPaymentProofs = $db->loadObjectList() ?: [];

        if (!empty($noAgentPaymentProofs)) {
            $noAgentStats = $this->buildNoAgentStatsLegacy(
                $db,
                $noAgentPaymentProofs,
                $startDate,
                $endDate,
                $hasVerificationStatus,
                $verificadoWhere
            );
            $agentsStats[] = $noAgentStats;
        }

        return $agentsStats;
    }

    /**
     * Build stats for proofs with no agent (legacy path: join via pp.order_id).
     */
    protected function buildNoAgentStatsLegacy($db, $noAgentPaymentProofs, $startDate, $endDate, $hasVerificationStatus, $verificadoWhere)
    {
        $noAgentStats = new \stdClass();
        $noAgentStats->salesAgent = null;
        $noAgentStats->paymentProofs = $noAgentPaymentProofs;
        $noAgentStats->paymentProofsCount = count($noAgentPaymentProofs);

        $noAgentWhere = 'pp.state = 1 AND pp.created >= ' . $db->quote($startDate) . ' AND pp.created <= ' . $db->quote($endDate)
            . ' AND (o.sales_agent IS NULL OR o.sales_agent = ' . $db->quote('') . ' OR o.sales_agent = ' . $db->quote(' ') . ')';
        $query = $db->getQuery(true)
            ->select('SUM(CAST(pp.' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))) as total')
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('pp.' . $db->quoteName('state') . ' = 1')
            ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
            ->where('pp.' . $db->quoteName('payment_amount') . ' IS NOT NULL')
            ->where('pp.' . $db->quoteName('payment_amount') . ' > 0');
        $db->setQuery($query);
        $noAgentStats->moneyCollected = (float) ($db->loadResult() ?: 0);

        // Ingresado = all payment proofs recorded in the period (regardless of verification status)
        $noAgentStats->ingresadoCount = $noAgentStats->paymentProofsCount;
        $noAgentStats->ingresadoAmount = $noAgentStats->moneyCollected;
        $noAgentStats->verificadoCount = 0;
        $noAgentStats->verificadoAmount = 0.0;
        if ($hasVerificationStatus) {
            $noAgentBaseQuery = function () use ($db, $startDate, $endDate) {
                return $db->getQuery(true)
                    ->select('COUNT(*) as cnt, COALESCE(SUM(CAST(pp.' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))), 0) as total')
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                    ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                    ->where('pp.' . $db->quoteName('state') . ' = 1')
                    ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                    ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
                    ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')');
            };
            $query = $noAgentBaseQuery()->where($verificadoWhere);
            $db->setQuery($query);
            $row = $db->loadObject();
            if ($row) {
                $noAgentStats->verificadoCount = (int) $row->cnt;
                $noAgentStats->verificadoAmount = (float) $row->total;
            }
        }
        return $noAgentStats;
    }

    /**
     * Get payment proofs by agent when payment_orders table exists.
     * Agent = sales_agent of the work order (first linked order), not who entered the payment.
     * Ingresado = proofs created in period with status ingresado; Verificado = proofs verified in period (verified_date).
     */
    protected function getPaymentProofsByAgentForPeriodViaPaymentOrders($db, $startDate, $endDate, $salesAgent, $hasVerificationStatus, $hasVerifiedDate)
    {
        $subQuery = $db->getQuery(true)
            ->select($db->quoteName('po.payment_proof_id') . ', MIN(' . $db->quoteName('po.order_id') . ') AS ' . $db->quoteName('order_id'))
            ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
            ->group($db->quoteName('po.payment_proof_id'));

        $selectCols = [
            'pp.' . $db->quoteName('id'),
            'pp.' . $db->quoteName('order_id'),
            'pp.' . $db->quoteName('payment_amount'),
            'pp.' . $db->quoteName('created'),
            'o.' . $db->quoteName('id') . ' AS ' . $db->quoteName('first_order_id'),
            'o.' . $db->quoteName('orden_de_trabajo'),
            'o.' . $db->quoteName('order_number'),
            'o.' . $db->quoteName('work_description'),
            'o.' . $db->quoteName('sales_agent')
        ];
        if ($hasVerificationStatus) {
            $selectCols[] = 'pp.' . $db->quoteName('verification_status');
        }
        if ($hasVerifiedDate) {
            $selectCols[] = 'pp.' . $db->quoteName('verified_date');
        }

        $query = $db->getQuery(true)
            ->select($selectCols)
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->join('LEFT', '(' . (string) $subQuery . ') AS first_po ON pp.' . $db->quoteName('id') . ' = first_po.' . $db->quoteName('payment_proof_id'))
            ->join('LEFT', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.' . $db->quoteName('id') . ' = COALESCE(first_po.' . $db->quoteName('order_id') . ', pp.' . $db->quoteName('order_id') . ')')
            ->where('pp.' . $db->quoteName('state') . ' = 1')
            ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->order('pp.' . $db->quoteName('created') . ' DESC');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $byAgent = [];
        foreach ($rows as $row) {
            $agent = isset($row->sales_agent) && trim((string) $row->sales_agent) !== ''
                ? trim((string) $row->sales_agent)
                : null;
            if (!isset($byAgent[$agent])) {
                $byAgent[$agent] = [];
            }
            $byAgent[$agent][] = $row;
        }

        $agentsStats = [];
        foreach ($byAgent as $agentKey => $proofRows) {
            $agentStats = new \stdClass();
            $agentStats->salesAgent = $agentKey;
            $agentStats->paymentProofs = $proofRows;
            $agentStats->paymentProofsCount = count($proofRows);
            $agentStats->moneyCollected = 0.0;
            foreach ($proofRows as $r) {
                $agentStats->moneyCollected += (float) ($r->payment_amount ?? 0);
            }

            // Ingresado = all payment proofs recorded in the period (regardless of verification status)
            $agentStats->ingresadoCount = $agentStats->paymentProofsCount;
            $agentStats->ingresadoAmount = $agentStats->moneyCollected;
            $agentStats->verificadoCount = 0;
            $agentStats->verificadoAmount = 0.0;
            if ($hasVerificationStatus) {
                foreach ($proofRows as $r) {
                    $status = isset($r->verification_status) ? strtolower(trim((string) $r->verification_status)) : '';
                    $isVerificado = ($status === 'verificado');
                    if ($isVerificado) {
                        if ($hasVerifiedDate && !empty($r->verified_date)) {
                            $vd = $r->verified_date;
                            if ($vd >= $startDate && $vd <= $endDate) {
                                $agentStats->verificadoCount++;
                                $agentStats->verificadoAmount += (float) ($r->payment_amount ?? 0);
                            }
                        } else {
                            $agentStats->verificadoCount++;
                            $agentStats->verificadoAmount += (float) ($r->payment_amount ?? 0);
                        }
                    }
                }
            }

            $agentsStats[] = $agentStats;
        }

        // Sort so null agent (no agent) is last; others by name
        usort($agentsStats, function ($a, $b) {
            if ($a->salesAgent === null) {
                return 1;
            }
            if ($b->salesAgent === null) {
                return -1;
            }
            return strcmp($a->salesAgent, $b->salesAgent);
        });

        return $agentsStats;
    }

    /**
     * Get shipping slips statistics grouped by sales agent for a period
     *
     * @param   string  $period  Period to get stats for: 'week', 'month', or 'year'
     *
     * @return  array  Shipping slips statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    public function getShippingSlipsByAgent($period = 'week', $salesAgent = null)
    {
        switch ($period) {
            case 'previous_week':
                $startDate = date('Y-m-d', strtotime('monday last week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday last week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'year':
                $startDate = date('Y-01-01') . ' 00:00:00';
                $endDate = date('Y-12-31') . ' 23:59:59';
                break;
            case 'week':
            default:
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
        }

        return $this->getShippingSlipsByAgentForPeriod($startDate, $endDate, $salesAgent);
    }

    /**
     * Get shipping slips statistics grouped by sales agent for a specific date range
     *
     * @param   string  $startDate  Start date (Y-m-d H:i:s)
     * @param   string  $endDate    End date (Y-m-d H:i:s)
     *
     * @return  array  Shipping slips statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    protected function getShippingSlipsByAgentForPeriod($startDate, $endDate, $salesAgent = null)
    {
        $db = Factory::getDbo();
        $agentsStats = [];

        $query = $db->getQuery(true)
            ->select('DISTINCT o.' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('h.' . $db->quoteName('state') . ' = 1')
            ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(h.' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio completo%') . 
                   ' OR h.' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio parcial%') .
                   ' OR h.' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio"%') . ')')
            ->where('o.' . $db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(' '))
            ->order('o.' . $db->quoteName('sales_agent') . ' ASC');
        if ($salesAgent !== null && $salesAgent !== '') {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }
        $db->setQuery($query);
        $agents = $db->loadColumn() ?: [];

        foreach ($agents as $agent) {
            $agentStats = new \stdClass();
            $agentStats->salesAgent = $agent;

            // Get shipping slips for this agent
            $query = $db->getQuery(true)
                ->select([
                    'h.' . $db->quoteName('id'),
                    'h.' . $db->quoteName('order_id'),
                    'h.' . $db->quoteName('event_description'),
                    'h.' . $db->quoteName('metadata'),
                    'h.' . $db->quoteName('created'),
                    'o.' . $db->quoteName('orden_de_trabajo'),
                    'o.' . $db->quoteName('order_number'),
                    'o.' . $db->quoteName('work_description'),
                    'o.' . $db->quoteName('client_name'),
                    'o.' . $db->quoteName('invoice_value')
                ])
                ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
                ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                ->where('h.' . $db->quoteName('state') . ' = 1')
                ->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($agent))
                ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->where('(h.' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio completo%') . 
                       ' OR h.' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio parcial%') .
                       ' OR h.' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio"%') . ')')
                ->order('h.' . $db->quoteName('created') . ' DESC');
            $db->setQuery($query);
            $shippingSlips = $db->loadObjectList() ?: [];

            // Process shipping slips and categorize
            $fullSlips = [];
            $partialSlips = [];
            foreach ($shippingSlips as $slip) {
                $isFull = false;
                $isPartial = false;

                // Check metadata first
                if (!empty($slip->metadata)) {
                    $meta = json_decode($slip->metadata, true);
                    if (isset($meta['tipo_envio'])) {
                        if ($meta['tipo_envio'] === 'completo') {
                            $isFull = true;
                        } elseif ($meta['tipo_envio'] === 'parcial') {
                            $isPartial = true;
                        }
                    }
                }

                // Check event description if metadata doesn't have tipo_envio
                if (!$isFull && !$isPartial) {
                    if (stripos($slip->event_description, 'completo') !== false) {
                        $isFull = true;
                    } elseif (stripos($slip->event_description, 'parcial') !== false) {
                        $isPartial = true;
                    }
                }

                if ($isFull) {
                    $fullSlips[] = $slip;
                } elseif ($isPartial) {
                    $partialSlips[] = $slip;
                }
            }

            $agentStats->shippingSlips = array_merge($fullSlips, $partialSlips);
            $agentStats->shippingSlipsFull = count($fullSlips);
            $agentStats->shippingSlipsPartial = count($partialSlips);
            $agentStats->shippingSlipsTotal = count($agentStats->shippingSlips);

            $agentsStats[] = $agentStats;
        }

        // Get shipping slips without agent
        $query = $db->getQuery(true)
            ->select([
                'h.' . $db->quoteName('id'),
                'h.' . $db->quoteName('order_id'),
                'h.' . $db->quoteName('event_description'),
                'h.' . $db->quoteName('metadata'),
                'h.' . $db->quoteName('created'),
                'o.' . $db->quoteName('orden_de_trabajo'),
                'o.' . $db->quoteName('order_number'),
                'o.' . $db->quoteName('work_description'),
                'o.' . $db->quoteName('client_name'),
                'o.' . $db->quoteName('invoice_value')
            ])
            ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON h.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('h.' . $db->quoteName('state') . ' = 1')
            ->where('h.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('h.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(h.' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio completo%') . 
                   ' OR h.' . $db->quoteName('event_description') . ' LIKE ' . $db->quote('%Envio parcial%') .
                   ' OR h.' . $db->quoteName('metadata') . ' LIKE ' . $db->quote('%"tipo_envio"%') . ')')
            ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . 
                   $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' .
                   $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
            ->order('h.' . $db->quoteName('created') . ' DESC');
        $db->setQuery($query);
        $noAgentShippingSlips = $db->loadObjectList() ?: [];

        if (!empty($noAgentShippingSlips)) {
            $fullSlips = [];
            $partialSlips = [];
            foreach ($noAgentShippingSlips as $slip) {
                $isFull = false;
                $isPartial = false;

                if (!empty($slip->metadata)) {
                    $meta = json_decode($slip->metadata, true);
                    if (isset($meta['tipo_envio'])) {
                        if ($meta['tipo_envio'] === 'completo') {
                            $isFull = true;
                        } elseif ($meta['tipo_envio'] === 'parcial') {
                            $isPartial = true;
                        }
                    }
                }

                if (!$isFull && !$isPartial) {
                    if (stripos($slip->event_description, 'completo') !== false) {
                        $isFull = true;
                    } elseif (stripos($slip->event_description, 'parcial') !== false) {
                        $isPartial = true;
                    }
                }

                if ($isFull) {
                    $fullSlips[] = $slip;
                } elseif ($isPartial) {
                    $partialSlips[] = $slip;
                }
            }

            $noAgentStats = new \stdClass();
            $noAgentStats->salesAgent = null;
            $noAgentStats->shippingSlips = array_merge($fullSlips, $partialSlips);
            $noAgentStats->shippingSlipsFull = count($fullSlips);
            $noAgentStats->shippingSlipsPartial = count($partialSlips);
            $noAgentStats->shippingSlipsTotal = count($noAgentStats->shippingSlips);

            $agentsStats[] = $noAgentStats;
        }

        return $agentsStats;
    }

    /**
     * Get cotización PDF template settings (Encabezado, Términos y Condiciones, Pie de página + positions).
     * Stored in #__ordenproduccion_config.
     *
     * @return  array  Keys: logo_path, logo_x, logo_y, logo_width, encabezado, terminos_condiciones, pie_pagina, encabezado_x, encabezado_y, terminos_x, terminos_y, pie_x, pie_y
     * @since   3.78.0
     */
    public function getCotizacionPdfSettings()
    {
        $db = Factory::getDbo();
        $keys = [
            'cotizacion_pdf_format_version',
            'cotizacion_pdf_logo_path',
            'cotizacion_pdf_logo_x',
            'cotizacion_pdf_logo_y',
            'cotizacion_pdf_logo_width',
            'cotizacion_pdf_encabezado',
            'cotizacion_pdf_terminos_condiciones',
            'cotizacion_pdf_pie_pagina',
            'cotizacion_pdf_encabezado_x',
            'cotizacion_pdf_encabezado_y',
            'cotizacion_pdf_table_x',
            'cotizacion_pdf_table_y',
            'cotizacion_pdf_terminos_x',
            'cotizacion_pdf_terminos_y',
            'cotizacion_pdf_pie_x',
            'cotizacion_pdf_pie_y',
        ];
        $query = $db->getQuery(true)
            ->select($db->quoteName(['setting_key', 'setting_value']))
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->whereIn($db->quoteName('setting_key'), array_map([$db, 'quote'], $keys));
        $db->setQuery($query);
        $rows = $db->loadObjectList('setting_key') ?: [];
        $getFloat = function ($key, $default) use ($rows) {
            if (!isset($rows[$key]) || $rows[$key]->setting_value === '') {
                return $default;
            }
            $v = (float) $rows[$key]->setting_value;
            return $v;
        };
        $formatVersion = 1;
        if (isset($rows['cotizacion_pdf_format_version']) && $rows['cotizacion_pdf_format_version']->setting_value !== '') {
            $v = (int) $rows['cotizacion_pdf_format_version']->setting_value;
            if ($v >= 1 && $v <= 2) {
                $formatVersion = $v;
            }
        }
        return [
            'format_version' => $formatVersion,
            'logo_path'  => isset($rows['cotizacion_pdf_logo_path']) ? $rows['cotizacion_pdf_logo_path']->setting_value : '',
            'logo_x'     => $getFloat('cotizacion_pdf_logo_x', 15),
            'logo_y'     => $getFloat('cotizacion_pdf_logo_y', 15),
            'logo_width' => $getFloat('cotizacion_pdf_logo_width', 50),
            'encabezado' => isset($rows['cotizacion_pdf_encabezado']) ? $rows['cotizacion_pdf_encabezado']->setting_value : '',
            'terminos_condiciones' => isset($rows['cotizacion_pdf_terminos_condiciones']) ? $rows['cotizacion_pdf_terminos_condiciones']->setting_value : '',
            'pie_pagina' => isset($rows['cotizacion_pdf_pie_pagina']) ? $rows['cotizacion_pdf_pie_pagina']->setting_value : '',
            'encabezado_x' => $getFloat('cotizacion_pdf_encabezado_x', 15),
            'encabezado_y' => $getFloat('cotizacion_pdf_encabezado_y', 15),
            'table_x' => $getFloat('cotizacion_pdf_table_x', 0),
            'table_y' => $getFloat('cotizacion_pdf_table_y', 0),
            'terminos_x' => $getFloat('cotizacion_pdf_terminos_x', 0),
            'terminos_y' => $getFloat('cotizacion_pdf_terminos_y', 0),
            'pie_x' => $getFloat('cotizacion_pdf_pie_x', 0),
            'pie_y' => $getFloat('cotizacion_pdf_pie_y', 0),
        ];
    }

    /**
     * Save cotización PDF template settings to #__ordenproduccion_config.
     *
     * @param   array  $data  Keys: logo_path, logo_x, logo_y, logo_width, encabezado, terminos_condiciones, pie_pagina (raw HTML allowed)
     * @return  bool
     * @since   3.78.0
     */
    public function saveCotizacionPdfSettings(array $data)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $map = [
            'format_version' => 'cotizacion_pdf_format_version',
            'logo_path'  => 'cotizacion_pdf_logo_path',
            'logo_x'     => 'cotizacion_pdf_logo_x',
            'logo_y'     => 'cotizacion_pdf_logo_y',
            'logo_width' => 'cotizacion_pdf_logo_width',
            'encabezado' => 'cotizacion_pdf_encabezado',
            'terminos_condiciones' => 'cotizacion_pdf_terminos_condiciones',
            'pie_pagina' => 'cotizacion_pdf_pie_pagina',
            'encabezado_x' => 'cotizacion_pdf_encabezado_x',
            'encabezado_y' => 'cotizacion_pdf_encabezado_y',
            'table_x' => 'cotizacion_pdf_table_x',
            'table_y' => 'cotizacion_pdf_table_y',
            'terminos_x' => 'cotizacion_pdf_terminos_x',
            'terminos_y' => 'cotizacion_pdf_terminos_y',
            'pie_x' => 'cotizacion_pdf_pie_x',
            'pie_y' => 'cotizacion_pdf_pie_y',
        ];
        foreach ($map as $inputKey => $settingKey) {
            $value = isset($data[$inputKey]) ? $data[$inputKey] : '';
            if (in_array($inputKey, ['logo_x', 'logo_y', 'logo_width', 'encabezado_x', 'encabezado_y', 'table_x', 'table_y', 'terminos_x', 'terminos_y', 'pie_x', 'pie_y'], true)) {
                $value = (string) (float) $value;
            } elseif ($inputKey === 'format_version') {
                $value = (string) max(1, min(2, (int) $value));
            } else {
                $value = is_string($value) ? $value : '';
            }
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote($settingKey));
            $db->setQuery($query);
            $id = $db->loadResult();
            if ($id) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_config'))
                    ->set($db->quoteName('setting_value') . ' = ' . $db->quote($value))
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                    ->where($db->quoteName('id') . ' = ' . (int) $id);
                $db->setQuery($query);
                $db->execute();
            } else {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_config'))
                    ->columns([
                        $db->quoteName('setting_key'),
                        $db->quoteName('setting_value'),
                        $db->quoteName('state'),
                        $db->quoteName('created_by'),
                        $db->quoteName('modified'),
                        $db->quoteName('modified_by'),
                    ])
                    ->values(
                        $db->quote($settingKey) . ',' .
                        $db->quote($value) . ',1,' .
                        (int) $user->id . ',' .
                        $db->quote($now) . ',' .
                        (int) $user->id
                    );
                $db->setQuery($query);
                $db->execute();
            }
        }
        return true;
    }

    /**
     * Get Solicitud de Orden URL from config (webhook URL called when finishing confirmar steps with next order number).
     *
     * @return  string
     * @since   3.92.0
     */
    public function getSolicitudOrdenUrl()
    {
        $db = Factory::getDbo();
        $key = 'solicitud_orden_url';
        $query = $db->getQuery(true)
            ->select($db->quoteName('setting_value'))
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $v = $db->loadResult();
        return $v !== null ? trim((string) $v) : '';
    }

    /**
     * Save Solicitud de Orden URL to config.
     *
     * @param   string  $url  URL to notify (e.g. webhook) with next order number when finishing steps.
     * @return  bool
     * @since   3.92.0
     */
    public function saveSolicitudOrdenUrl($url)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $key = 'solicitud_orden_url';
        $value = is_string($url) ? trim($url) : '';
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $id = $db->loadResult();
        if ($id) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_config'))
                ->set($db->quoteName('setting_value') . ' = ' . $db->quote($value))
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $db->execute();
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_config'))
                ->columns([
                    $db->quoteName('setting_key'),
                    $db->quoteName('setting_value'),
                    $db->quoteName('state'),
                    $db->quoteName('created_by'),
                    $db->quoteName('modified'),
                    $db->quoteName('modified_by'),
                ])
                ->values(
                    $db->quote($key) . ',' .
                    $db->quote($value) . ',1,' .
                    (int) $user->id . ',' .
                    $db->quote($now) . ',' .
                    (int) $user->id
                );
            $db->setQuery($query);
            $db->execute();
        }
        return true;
    }

    /**
     * Find a work order by orden_de_trabajo or order_number (case-insensitive).
     * Used by Ajustes > Anular orden to resolve user input (e.g. "ord-006448").
     *
     * @param   string  $input  Order number / orden de trabajo (e.g. ord-006448, ORD-006448)
     * @return  \stdClass|null  Order row (id, status, orden_de_trabajo, order_number if column exists) or null
     * @since   3.99.0
     */
    public function findOrderByOrdenDeTrabajoOrNumber($input)
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        $db = Factory::getDbo();
        $normalized = $db->escape(strtolower($input));
        $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $cols = $cols ? array_change_key_case($cols, CASE_LOWER) : [];
        $hasOrderNumber = isset($cols['order_number']);
        $select = ['id', 'status', $db->quoteName('orden_de_trabajo')];
        if ($hasOrderNumber) {
            $select[] = $db->quoteName('order_number');
        }
        $query = $db->getQuery(true)
            ->select($select)
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1');
        if ($hasOrderNumber) {
            $query->where('(LOWER(TRIM(' . $db->quoteName('orden_de_trabajo') . ')) = ' . $db->quote($normalized) .
                ' OR LOWER(TRIM(' . $db->quoteName('order_number') . ')) = ' . $db->quote($normalized) . ')');
        } else {
            $query->where('LOWER(TRIM(' . $db->quoteName('orden_de_trabajo') . ')) = ' . $db->quote($normalized));
        }
        $db->setQuery($query);
        $row = $db->loadObject();
        if ($row && !$hasOrderNumber) {
            $row->order_number = $row->orden_de_trabajo ?? null;
        }
        return $row;
    }

    /**
     * Set a work order status to Anulada by orden de trabajo / order number input.
     * Orders with status Anulada are excluded from Estado de cuenta, Comprobantes de pago, and Rango de días.
     *
     * @param   string  $input        Order number (e.g. ord-006448)
     * @param   string  $requestedBy  User name for historial
     * @param   int     $requesterId  User ID
     * @return  array{success: bool, message: string}
     * @since   3.99.0
     */
    public function anularOrdenByInput($input, $requestedBy = '', $requesterId = 0)
    {
        $order = $this->findOrderByOrdenDeTrabajoOrNumber($input);
        if (!$order) {
            return ['success' => false, 'message' => 'Orden no encontrada con el número indicado.'];
        }
        if (strtolower((string) $order->status) === 'anulada') {
            return ['success' => false, 'message' => 'La orden ya está anulada.'];
        }
        $db = Factory::getDbo();
        try {
            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('Anulada'))
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $requesterId)
                ->where($db->quoteName('id') . ' = ' . (int) $order->id);
            $db->setQuery($updateQuery);
            $db->execute();
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
        }
        try {
            $historialColumns = $db->getTableColumns('#__ordenproduccion_historial', false);
            if (!empty($historialColumns)) {
                $meta = json_encode(['requester' => $requestedBy, 'requester_id' => $requesterId, 'source' => 'ajustes_anular_orden'], JSON_UNESCAPED_UNICODE);
                $db->setQuery($db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_historial'))
                    ->set($db->quoteName('order_id') . ' = ' . (int) $order->id)
                    ->set($db->quoteName('event_type') . ' = ' . $db->quote('anulacion'))
                    ->set($db->quoteName('event_title') . ' = ' . $db->quote('Orden Anulada'))
                    ->set($db->quoteName('event_description') . ' = ' . $db->quote('Anulada desde Ajustes. Solicitante: ' . $requestedBy))
                    ->set($db->quoteName('metadata') . ' = ' . $db->quote($meta))
                    ->set($db->quoteName('created_by') . ' = ' . (int) $requesterId)
                    ->set($db->quoteName('state') . ' = 1'));
                $db->execute();
            }
        } catch (\Exception $e) {
            // non-fatal
        }
        $displayNumber = $order->orden_de_trabajo ?? $order->order_number ?? $input;
        return ['success' => true, 'message' => 'La orden ' . $displayNumber . ' fue marcada como Anulada. No se incluirá en Estado de cuenta, Comprobantes de pago ni Rango de días.'];
    }

    /**
     * Whether proveedores tables exist (3.110.0+).
     *
     * @since  3.110.0
     */
    public function hasProveedoresSchema(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $db = Factory::getDbo();
        try {
            $tables = $db->getTableList();
            $want   = $db->getPrefix() . 'ordenproduccion_proveedores';
            foreach ($tables as $t) {
                if (strcasecmp((string) $t, $want) === 0) {
                    return $cache = true;
                }
            }
        } catch (\Throwable $e) {
        }

        return $cache = false;
    }

    /**
     * List vendors for Administración → Proveedores.
     *
     * @param   string    $search       Substring match on name, NIT, contact name
     * @param   int|null  $stateFilter  null = all, 1 = active, 0 = inactive
     *
     * @return  array<int, object>
     *
     * @since   3.110.0
     */
    public function getProveedoresList(string $search = '', ?int $stateFilter = null): array
    {
        if (!$this->hasProveedoresSchema()) {
            return [];
        }
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_proveedores'))
            ->order($db->quoteName('name') . ' ASC');
        if ($stateFilter !== null) {
            $q->where($db->quoteName('state') . ' = ' . (int) $stateFilter);
        }
        $s = trim($search);
        if ($s !== '') {
            $like = '%' . $db->escape($s, true) . '%';
            $q->where(
                '(' . $db->quoteName('name') . ' LIKE ' . $db->quote($like)
                . ' OR ' . $db->quoteName('nit') . ' LIKE ' . $db->quote($like)
                . ' OR ' . $db->quoteName('contact_name') . ' LIKE ' . $db->quote($like) . ')'
            );
        }
        $db->setQuery($q);

        return $db->loadObjectList() ?: [];
    }

    /**
     * @since  3.110.0
     */
    public function getProveedorById(int $id): ?object
    {
        if (!$this->hasProveedoresSchema() || $id < 1) {
            return null;
        }
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_proveedores'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->setLimit(1);
        $db->setQuery($q);
        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * Product lines for a vendor (ordered).
     *
     * @return  array<int, object>
     *
     * @since   3.110.0
     */
    public function getProveedorProductos(int $proveedorId): array
    {
        if (!$this->hasProveedoresSchema() || $proveedorId < 1) {
            return [];
        }
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_proveedor_productos'))
            ->where($db->quoteName('proveedor_id') . ' = ' . $proveedorId)
            ->order($db->quoteName('ordering') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($q);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Save vendor and replace product lines.
     *
     * @param   array<int, string>  $productLines  Trimmed non-empty strings, max 500 chars each
     *
     * @return  int|false  New or updated id, or false on validation / DB error
     *
     * @since   3.110.0
     */
    public function saveProveedor(array $data, array $productLines, int $userId)
    {
        if (!$this->hasProveedoresSchema() || $userId < 1) {
            return false;
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return false;
        }
        $email = trim((string) ($data['contact_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $id    = (int) ($data['id'] ?? 0);
        $now   = Factory::getDate()->toSql();
        $state = (int) ($data['state'] ?? 1) === 0 ? 0 : 1;

        $row = [
            'name'              => $name,
            'nit'               => substr(trim((string) ($data['nit'] ?? '')), 0, 64),
            'address'           => trim((string) ($data['address'] ?? '')),
            'phone'             => substr(trim((string) ($data['phone'] ?? '')), 0, 64),
            'contact_name'      => substr(trim((string) ($data['contact_name'] ?? '')), 0, 255),
            'contact_cellphone' => substr(trim((string) ($data['contact_cellphone'] ?? '')), 0, 64),
            'contact_email'     => substr($email, 0, 255),
            'state'             => $state,
        ];

        $cleanProducts = [];
        foreach ($productLines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $cleanProducts[] = substr($line, 0, 500);
        }

        $db = Factory::getDbo();

        try {
            $db->transactionStart();

            if ($id < 1) {
                $q = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_proveedores'))
                    ->columns([
                        $db->quoteName('name'),
                        $db->quoteName('nit'),
                        $db->quoteName('address'),
                        $db->quoteName('phone'),
                        $db->quoteName('contact_name'),
                        $db->quoteName('contact_cellphone'),
                        $db->quoteName('contact_email'),
                        $db->quoteName('state'),
                        $db->quoteName('created'),
                        $db->quoteName('created_by'),
                    ])
                    ->values(implode(',', [
                        $db->quote($row['name']),
                        $db->quote($row['nit']),
                        $db->quote($row['address']),
                        $db->quote($row['phone']),
                        $db->quote($row['contact_name']),
                        $db->quote($row['contact_cellphone']),
                        $db->quote($row['contact_email']),
                        (string) (int) $row['state'],
                        $db->quote($now),
                        (string) (int) $userId,
                    ]));
                $db->setQuery($q);
                $db->execute();
                $id = (int) $db->insertid();
            } else {
                $exists = $this->getProveedorById($id);
                if ($exists === null) {
                    $db->transactionRollback();

                    return false;
                }
                $q = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_proveedores'))
                    ->set($db->quoteName('name') . ' = ' . $db->quote($row['name']))
                    ->set($db->quoteName('nit') . ' = ' . $db->quote($row['nit']))
                    ->set($db->quoteName('address') . ' = ' . $db->quote($row['address']))
                    ->set($db->quoteName('phone') . ' = ' . $db->quote($row['phone']))
                    ->set($db->quoteName('contact_name') . ' = ' . $db->quote($row['contact_name']))
                    ->set($db->quoteName('contact_cellphone') . ' = ' . $db->quote($row['contact_cellphone']))
                    ->set($db->quoteName('contact_email') . ' = ' . $db->quote($row['contact_email']))
                    ->set($db->quoteName('state') . ' = ' . (string) (int) $row['state'])
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) $userId)
                    ->where($db->quoteName('id') . ' = ' . $id);
                $db->setQuery($q);
                $db->execute();

                $db->setQuery($db->getQuery(true)
                    ->delete($db->quoteName('#__ordenproduccion_proveedor_productos'))
                    ->where($db->quoteName('proveedor_id') . ' = ' . $id));
                $db->execute();
            }

            $ord = 0;
            foreach ($cleanProducts as $pv) {
                $db->setQuery($db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_proveedor_productos'))
                    ->columns([
                        $db->quoteName('proveedor_id'),
                        $db->quoteName('product_value'),
                        $db->quoteName('ordering'),
                    ])
                    ->values(implode(',', [
                        (string) $id,
                        $db->quote($pv),
                        (string) ($ord++),
                    ])));
                $db->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            return false;
        }

        return $id;
    }

    /**
     * Delete vendor and its product rows.
     *
     * @since  3.110.0
     */
    public function deleteProveedor(int $id): bool
    {
        if (!$this->hasProveedoresSchema() || $id < 1) {
            return false;
        }
        $db = Factory::getDbo();
        try {
            $db->transactionStart();
            $db->setQuery($db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_proveedor_productos'))
                ->where($db->quoteName('proveedor_id') . ' = ' . $id));
            $db->execute();
            $db->setQuery($db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_proveedores'))
                ->where($db->quoteName('id') . ' = ' . $id));
            $db->execute();
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            return false;
        }

        return true;
    }

    /**
     * Vendor quote message templates table (3.113.0+).
     *
     * @since  3.113.0
     */
    public function hasVendorQuoteTemplatesSchema(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $db = Factory::getDbo();
        try {
            $tables = $db->getTableList();
            $want   = $db->getPrefix() . 'ordenproduccion_vendor_quote_templates';
            foreach ($tables as $t) {
                if (strcasecmp((string) $t, $want) === 0) {
                    return $cache = true;
                }
            }
        } catch (\Throwable $e) {
        }

        return $cache = false;
    }

    /**
     * @return  array<string, \stdClass>  Keyed by channel (email|cellphone|pdf)
     *
     * @since   3.113.0
     */
    public function getVendorQuoteTemplates(): array
    {
        if (!$this->hasVendorQuoteTemplatesSchema()) {
            return [];
        }
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_vendor_quote_templates'))
        );
        $rows = $db->loadObjectList('channel');

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param   array<string, string>  $data  keys: email_subject, email_body, cellphone_body, pdf_body
     *
     * @since   3.113.0
     */
    public function saveVendorQuoteTemplates(array $data): bool
    {
        if (!$this->hasVendorQuoteTemplatesSchema()) {
            return false;
        }
        $db  = Factory::getDbo();
        $uid = (int) Factory::getUser()->id;
        $now = Factory::getDate()->toSql();

        $channels = [
            'email' => [
                'subject' => substr(trim((string) ($data['email_subject'] ?? '')), 0, 512),
                'body'    => (string) ($data['email_body'] ?? ''),
            ],
            'cellphone' => [
                'subject' => '',
                'body'    => (string) ($data['cellphone_body'] ?? ''),
            ],
            'pdf' => [
                'subject' => '',
                'body'    => (string) ($data['pdf_body'] ?? ''),
            ],
        ];

        try {
            foreach ($channels as $ch => $parts) {
                $obj = (object) [
                    'channel'     => $ch,
                    'subject'     => $parts['subject'],
                    'body'        => $parts['body'],
                    'modified'    => $now,
                    'modified_by' => $uid,
                ];
                $db->setQuery(
                    $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__ordenproduccion_vendor_quote_templates'))
                        ->where($db->quoteName('channel') . ' = ' . $db->quote($ch))
                );
                $exists = (int) $db->loadResult() > 0;
                if ($exists) {
                    $db->updateObject('#__ordenproduccion_vendor_quote_templates', $obj, 'channel');
                } else {
                    $db->insertObject('#__ordenproduccion_vendor_quote_templates', $obj);
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Normalize Financiero list/export filter input (date range on PRE created, agent label, facturar flag).
     *
     * @param   array<string, mixed>  $in  Request keys financiero_filter_* or generic filter_*
     *
     * @return  array<string, string>  keys: date_from, date_to, agent, facturar ('' | '0' | '1')
     *
     * @since   3.115.26
     */
    public static function normalizeFinancieroFilters(array $in): array
    {
        $df = trim((string) ($in['financiero_filter_date_from'] ?? $in['filter_date_from'] ?? ''));
        $dt = trim((string) ($in['financiero_filter_date_to'] ?? $in['filter_date_to'] ?? ''));
        $agent = trim((string) ($in['financiero_filter_agent'] ?? $in['filter_agent'] ?? ''));
        $facturar = trim((string) ($in['financiero_filter_facturar'] ?? $in['filter_facturar'] ?? ''));

        if ($facturar !== '' && $facturar !== '0' && $facturar !== '1') {
            $facturar = '';
        }
        if ($df !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
            $df = '';
        }
        if ($dt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
            $dt = '';
        }

        return [
            'date_from' => $df,
            'date_to' => $dt,
            'agent' => $agent,
            'facturar' => $facturar,
        ];
    }

    /**
     * Schema + expressions for Financiero PRE queries (counts, aggregates, exports).
     *
     * @return  array<string, mixed>|null
     *
     * @since   3.115.26
     */
    protected function financieroPrecotFinanceContext(): ?array
    {
        $db     = $this->getDatabase();
        $prefix = $db->getPrefix();

        try {
            $tables = $db->getTableList();
        } catch (\Throwable $e) {
            return null;
        }

        $hasPc = false;
        foreach ($tables as $t) {
            if (strcasecmp((string) $t, $prefix . 'ordenproduccion_pre_cotizacion') === 0) {
                $hasPc = true;
                break;
            }
        }

        if (!$hasPc) {
            return null;
        }

        $qiCols = [];
        $qCols  = [];

        try {
            $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false) ?: [];
            $qCols  = $db->getTableColumns('#__ordenproduccion_quotations', false) ?: [];
        } catch (\Throwable $e) {
            $qiCols = [];
            $qCols  = [];
        }

        $qiCols           = is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
        $qCols            = is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];
        $hasQiPrec        = isset($qiCols['pre_cotizacion_id']);
        $joinQuotations   = $hasQiPrec && isset($qCols['quotation_number']);
        $hasQSalesAgent   = isset($qCols['sales_agent']);

        $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $pcCols = is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];

        $hasTotalFinal = isset($pcCols['total_final']);
        $hasTotal      = isset($pcCols['total']);
        $hasMargenAd   = isset($pcCols['margen_adicional']);
        $pcAlias       = $db->quoteName('pc');
        $tf            = $pcAlias . '.' . $db->quoteName('total_final');
        $tt            = $pcAlias . '.' . $db->quoteName('total');
        $ma            = $pcAlias . '.' . $db->quoteName('margen_adicional');

        if ($hasTotalFinal && $hasTotal && $hasMargenAd) {
            $exprGrand = 'ROUND(COALESCE(COALESCE(' . $tf . ', ' . $tt . '), 0) + COALESCE(' . $ma . ', 0), 2)';
        } elseif ($hasTotal && $hasMargenAd) {
            $exprGrand = 'ROUND(COALESCE(' . $tt . ', 0) + COALESCE(' . $ma . ', 0), 2)';
        } elseif ($hasTotal) {
            $exprGrand = 'ROUND(COALESCE(' . $tt . ', 0), 2)';
        } else {
            $exprGrand = '0';
        }

        $subSql = '';

        if ($joinQuotations) {
            $subSql = '(SELECT MIN(' . $db->quoteName('qi2') . '.' . $db->quoteName('quotation_id') . ') AS ' . $db->quoteName('qid')
                . ', ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id')
                . ' FROM ' . $db->quoteName('#__ordenproduccion_quotation_items', 'qi2')
                . ' WHERE ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id') . ' IS NOT NULL'
                . ' AND ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id') . ' > 0'
                . ' GROUP BY ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id') . ')';
        }

        return [
            'db' => $db,
            'pc_cols' => $pcCols,
            'q_cols' => $qCols,
            'join_quotations' => $joinQuotations,
            'has_q_sales_agent' => $hasQSalesAgent,
            'expr_grand' => $exprGrand,
            'sub_sql' => $subSql,
        ];
    }

    /**
     * SQL expression for financiero_agent_label (quoted table aliases pc, u, optionally q).
     *
     * @since  3.115.26
     */
    protected function financieroAgentSqlExpr($db, bool $quotationJoined, bool $hasQSalesAgent): string
    {
        $uNm = $db->quoteName('u') . '.' . $db->quoteName('name');

        if ($quotationJoined && $hasQSalesAgent) {
            return 'COALESCE(NULLIF(TRIM(' . $db->quoteName('q') . '.' . $db->quoteName('sales_agent')
                . "), ''), NULLIF(TRIM({$uNm}), ''))";
        }

        return 'NULLIF(TRIM(' . $uNm . "), '')";
    }

    /**
     * LEFT JOIN users (+ optional quotations / qmap for linked cotización).
     *
     * @since  3.115.26
     */
    protected function financieroAttachListJoins($db, $query, bool $joinQuotations, string $subSql): void
    {
        $query->join(
            'LEFT',
            $db->quoteName('#__users', 'u'),
            $db->quoteName('u') . '.' . $db->quoteName('id') . ' = ' . $db->quoteName('pc') . '.' . $db->quoteName('created_by')
        );

        if ($joinQuotations && $subSql !== '') {
            $query->join(
                'LEFT',
                $subSql . ' AS ' . $db->quoteName('qmap'),
                $db->quoteName('qmap') . '.' . $db->quoteName('pre_cotizacion_id') . ' = ' . $db->quoteName('pc') . '.' . $db->quoteName('id')
            );
            $query->join(
                'LEFT',
                $db->quoteName('#__ordenproduccion_quotations', 'q'),
                $db->quoteName('q') . '.' . $db->quoteName('id') . ' = ' . $db->quoteName('qmap') . '.' . $db->quoteName('qid')
            );
        }
    }

    /**
     * WHERE for date range (pc.created), facturar, agent label (must match list SELECT).
     *
     * @since  3.115.26
     */
    protected function financieroApplyListFilters($db, $query, array $filters, array $pcCols, bool $joinQuotations, bool $hasQSalesAgent): void
    {
        if (isset($pcCols['created'])) {
            $created = $db->quoteName('pc') . '.' . $db->quoteName('created');
            $df      = isset($filters['date_from']) ? (string) $filters['date_from'] : '';

            if ($df !== '') {
                $query->where('DATE(' . $created . ') >= ' . $db->quote($df));
            }

            $dt = isset($filters['date_to']) ? (string) $filters['date_to'] : '';

            if ($dt !== '') {
                $query->where('DATE(' . $created . ') <= ' . $db->quote($dt));
            }
        }

        $facturar = isset($filters['facturar']) ? (string) $filters['facturar'] : '';

        if ($facturar !== '' && isset($pcCols['facturar'])) {
            $query->where($db->quoteName('pc') . '.' . $db->quoteName('facturar') . ' = ' . (int) $facturar);
        }

        $agent = isset($filters['agent']) ? trim((string) $filters['agent']) : '';

        if ($agent !== '') {
            $expr = $this->financieroAgentSqlExpr($db, $joinQuotations, $hasQSalesAgent);
            $query->where('(' . $expr . ') = ' . $db->quote($agent));
        }
    }

    /**
     * Distinct agent labels for Financiero filter dropdown (respects date + facturar; ignores agent filter).
     *
     * @param   array<string, string>  $filters  Normalized filters
     *
     * @return  string[]
     *
     * @since   3.115.26
     */
    public function getFinancieroAgentDistinctLabels(array $filters = []): array
    {
        $ctx = $this->financieroPrecotFinanceContext();

        if ($ctx === null) {
            return [];
        }

        $db               = $ctx['db'];
        $filters          = self::normalizeFinancieroFilters($filters);
        $filters['agent'] = '';
        $pcCols           = $ctx['pc_cols'];
        $joinQuotations   = $ctx['join_quotations'];
        $hasQSalesAgent   = $ctx['has_q_sales_agent'];
        $subSql           = $ctx['sub_sql'];
        $agentExpr        = $this->financieroAgentSqlExpr($db, $joinQuotations, $hasQSalesAgent);

        $pcTbl = $db->quoteName('#__ordenproduccion_pre_cotizacion');

        try {
            $q = $db->getQuery(true)
                ->select('DISTINCT ' . $agentExpr . ' AS ' . $db->quoteName('lbl'))
                ->from($pcTbl . ' AS ' . $db->quoteName('pc'));
            $this->financieroAttachListJoins($db, $q, $joinQuotations, $subSql);
            $this->financieroApplyListFilters($db, $q, $filters, $pcCols, $joinQuotations, $hasQSalesAgent);
            $q->where('(' . $agentExpr . ') IS NOT NULL')->where('(' . $agentExpr . ") <> ''");
            $q->order($db->quoteName('lbl') . ' ASC');
            $db->setQuery($q, 0, 500);
            $col = $db->loadColumn() ?: [];

            return array_values(array_filter(array_map('trim', $col)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Control de ventas → Financiero: paginated pre-cotizaciones with financial snapshot + linked quotation.
     * Super-user-only at controller; do not call for other roles.
     *
     * @param   int                  $limit    Page size (max 200 in UI; use large value for exports)
     * @param   int                  $start    Offset
     * @param   array<string, mixed> $filters  Raw or normalized financiero filters
     *
     * @return  array{rows: object[], total: int, aggregates: \stdClass|null, precotTableOk: bool, joinQuotations: bool}
     *
     * @since   3.115.24
     */
    public function getFinancieroPrecotizacionesData(int $limit, int $start, array $filters = []): array
    {
        $empty = [
            'rows' => [],
            'total' => 0,
            'aggregates' => null,
            'precotTableOk' => false,
            'joinQuotations' => false,
        ];

        $ctx = $this->financieroPrecotFinanceContext();

        if ($ctx === null) {
            return $empty;
        }

        $filters = self::normalizeFinancieroFilters(is_array($filters) ? $filters : []);

        $db             = $ctx['db'];
        $pcCols         = $ctx['pc_cols'];
        $joinQuotations = $ctx['join_quotations'];
        $exprGrand      = $ctx['expr_grand'];
        $subSql         = $ctx['sub_sql'];
        $hasQSalesAgent = $ctx['has_q_sales_agent'];
        $qCols          = $ctx['q_cols'];

        if ($limit > 1000) {
            $limit = max(1, min(200000, $limit));
        } else {
            $limit = max(1, min(200, $limit));
        }

        $start = max(0, $start);

        $pcTbl   = $db->quoteName('#__ordenproduccion_pre_cotizacion');
        $fromPc  = $pcTbl . ' AS ' . $db->quoteName('pc');

        $countQ = $db->getQuery(true)->select('COUNT(*)')->from($fromPc);
        $this->financieroAttachListJoins($db, $countQ, $joinQuotations, $subSql);
        $this->financieroApplyListFilters($db, $countQ, $filters, $pcCols, $joinQuotations, $hasQSalesAgent);

        try {
            $db->setQuery($countQ);
            $total = (int) $db->loadResult();
        } catch (\Throwable $e) {
            $total = 0;
        }

        $confirmSel = isset($qCols['cotizacion_confirmada'])
            ? $db->quoteName('q') . '.' . $db->quoteName('cotizacion_confirmada')
            : 'CAST(NULL AS UNSIGNED)';

        if ($joinQuotations) {
            if ($hasQSalesAgent) {
                $agentSelect = 'COALESCE(NULLIF(TRIM(' . $db->quoteName('q') . '.' . $db->quoteName('sales_agent')
                    . '), \'\'), NULLIF(TRIM(' . $db->quoteName('u') . '.' . $db->quoteName('name') . '), \'\')) AS '
                    . $db->quoteName('financiero_agent_label');
            } else {
                $agentSelect = 'NULLIF(TRIM(' . $db->quoteName('u') . '.' . $db->quoteName('name') . '), \'\') AS '
                    . $db->quoteName('financiero_agent_label');
            }

            $sel = [
                $db->quoteName('pc') . '.*',
                $db->quoteName('q') . '.' . $db->quoteName('id') . ' AS ' . $db->quoteName('linked_quotation_id'),
                $db->quoteName('q') . '.' . $db->quoteName('quotation_number') . ' AS ' . $db->quoteName('linked_quotation_number'),
                $confirmSel . ' AS ' . $db->quoteName('cotizacion_confirmada'),
                $agentSelect,
            ];
        } else {
            $agentSelect = 'NULLIF(TRIM(' . $db->quoteName('u') . '.' . $db->quoteName('name') . '), \'\') AS ' . $db->quoteName('financiero_agent_label');

            $sel = [
                $db->quoteName('pc') . '.*',
                'CAST(NULL AS UNSIGNED) AS ' . $db->quoteName('linked_quotation_id'),
                'CAST(NULL AS CHAR) AS ' . $db->quoteName('linked_quotation_number'),
                'CAST(NULL AS UNSIGNED) AS ' . $db->quoteName('cotizacion_confirmada'),
                $agentSelect,
            ];
        }

        try {
            $main = $db->getQuery(true)
                ->select($sel)
                ->from($fromPc);
            $this->financieroAttachListJoins($db, $main, $joinQuotations, $subSql);
            $this->financieroApplyListFilters($db, $main, $filters, $pcCols, $joinQuotations, $hasQSalesAgent);
            $main->order($db->quoteName('pc') . '.' . $db->quoteName('id') . ' DESC');
            $db->setQuery($main, $start, $limit);

            try {
                $rows = $db->loadObjectList() ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
        } catch (\Throwable $e) {
            $rows = [];
        }

        $agg = $this->buildFinancieroAggregates(
            $db,
            $exprGrand,
            $filters,
            $joinQuotations,
            $hasQSalesAgent,
            $pcCols,
            $subSql
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'aggregates' => $agg,
            'precotTableOk' => true,
            'joinQuotations' => $joinQuotations,
        ];
    }

    /**
     * SUM() across filtered pre_cotizaciones for footer totals.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db            DB
     * @param   string                              $exprGrand     SQL expression for displayed grand total
     * @param   array<string, string>              $filters       Normalized filters
     * @param   bool                               $joinQuotations
     * @param   bool                               $hasQSalesAgent
     * @param   array<string, mixed>                $pcCols
     * @param   string                             $subSql        Quotation-per-PRE map subquery
     *
     * @return  \stdClass|null
     *
     * @since   3.115.24
     */
    protected function buildFinancieroAggregates(
        $db,
        string $exprGrand,
        array $filters,
        bool $joinQuotations,
        bool $hasQSalesAgent,
        array $pcCols,
        string $subSql
    ): ?\stdClass {
        $pcTbl = $db->quoteName('#__ordenproduccion_pre_cotizacion');
        $pcCols = array_change_key_case($pcCols, CASE_LOWER);

        $sumLines = isset($pcCols['lines_subtotal'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('lines_subtotal') . ', 0))'
            : 'SUM(0)';
        $sumMargen = isset($pcCols['margen_amount'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('margen_amount') . ', 0))'
            : 'SUM(0)';
        $sumIva = isset($pcCols['iva_amount'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('iva_amount') . ', 0))'
            : 'SUM(0)';
        $sumIsr = isset($pcCols['isr_amount'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('isr_amount') . ', 0))'
            : 'SUM(0)';
        $sumCom = isset($pcCols['comision_amount'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_amount') . ', 0))'
            : 'SUM(0)';
        $sumMa = isset($pcCols['margen_adicional'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('margen_adicional') . ', 0))'
            : 'SUM(0)';
        $sumCma = isset($pcCols['comision_margen_adicional'])
            ? 'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_margen_adicional') . ', 0))'
            : 'SUM(0)';

        $q = $db->getQuery(true)
            ->select([
                'COUNT(*) AS ' . $db->quoteName('cnt'),
                $sumLines . ' AS sum_lines_subtotal',
                $sumMargen . ' AS sum_margen_amount',
                $sumIva . ' AS sum_iva_amount',
                $sumIsr . ' AS sum_isr_amount',
                $sumCom . ' AS sum_comision_amount',
                $sumMa . ' AS sum_margen_adicional',
                $sumCma . ' AS sum_comision_margen_adicional',
                'SUM(' . $exprGrand . ') AS sum_grand_total',
            ])
            ->from($pcTbl . ' AS ' . $db->quoteName('pc'));
        $this->financieroAttachListJoins($db, $q, $joinQuotations, $subSql);
        $this->financieroApplyListFilters($db, $q, $filters, $pcCols, $joinQuotations, $hasQSalesAgent);

        try {
            $db->setQuery($q);
            $row = $db->loadObject();

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Financiero → Bonos: SUM(bono venta) and SUM(bono margen adicional) grouped by sales agent label.
     *
     * @return  array<int, object>  Each: agent_label, sum_bono_venta, sum_bono_margen_adicional, sum_bonos_total
     *
     * @since   3.115.24
     */
    public function getFinancieroBonosByAgentSummary(): array
    {
        $db = $this->getDatabase();
        try {
            $tables = $db->getTableList();
        } catch (\Throwable $e) {
            return [];
        }
        $prefix = $db->getPrefix();
        $hasPc = false;
        foreach ($tables as $t) {
            if (strcasecmp((string) $t, $prefix . 'ordenproduccion_pre_cotizacion') === 0) {
                $hasPc = true;
                break;
            }
        }
        if (!$hasPc) {
            return [];
        }

        $qiCols = [];
        $qCols  = [];
        try {
            $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false) ?: [];
            $qCols  = $db->getTableColumns('#__ordenproduccion_quotations', false) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $qiCols    = is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
        $qCols     = is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];
        $hasQiPrec = isset($qiCols['pre_cotizacion_id']) && isset($qiCols['quotation_id']);
        $hasSales  = isset($qCols['sales_agent']);

        // Agent label: quotation.sales_agent when linked, else Joomla user name of pre.created_by
        if ($hasQiPrec && isset($qCols['quotation_number'])) {
            $sub = '(SELECT MIN(' . $db->quoteName('qi2') . '.' . $db->quoteName('quotation_id') . ') AS ' . $db->quoteName('qid')
                . ', ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id')
                . ' FROM ' . $db->quoteName('#__ordenproduccion_quotation_items', 'qi2')
                . ' WHERE ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id') . ' IS NOT NULL'
                . ' AND ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id') . ' > 0'
                . ' GROUP BY ' . $db->quoteName('qi2') . '.' . $db->quoteName('pre_cotizacion_id') . ')';

            $unknown = $db->quote('-');

            $agentExpr = $hasSales
                ? 'COALESCE(NULLIF(TRIM(' . $db->quoteName('q') . '.' . $db->quoteName('sales_agent') . '), \'\'), NULLIF(TRIM('
                . $db->quoteName('u') . '.' . $db->quoteName('name') . '), \'\'), ' . $unknown . ')'
                : 'COALESCE(NULLIF(TRIM(' . $db->quoteName('u') . '.' . $db->quoteName('name') . '), \'\'), ' . $unknown . ')';

            $q = $db->getQuery(true)
                ->select([
                    $agentExpr . ' AS ' . $db->quoteName('agent_label'),
                    'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_amount') . ', 0)) AS ' . $db->quoteName('sum_bono_venta'),
                    'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_margen_adicional') . ', 0)) AS ' . $db->quoteName('sum_bono_margen_adicional'),
                    'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_amount') . ', 0) + COALESCE('
                    . $db->quoteName('pc') . '.' . $db->quoteName('comision_margen_adicional') . ', 0)) AS ' . $db->quoteName('sum_bonos_total'),
                ])
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'pc'))
                ->join(
                    'LEFT',
                    $sub . ' AS ' . $db->quoteName('qmap'),
                    $db->quoteName('qmap') . '.' . $db->quoteName('pre_cotizacion_id') . ' = ' . $db->quoteName('pc') . '.' . $db->quoteName('id')
                )
                ->join(
                    'LEFT',
                    $db->quoteName('#__ordenproduccion_quotations', 'q'),
                    $db->quoteName('q') . '.' . $db->quoteName('id') . ' = ' . $db->quoteName('qmap') . '.' . $db->quoteName('qid')
                )
                ->join(
                    'LEFT',
                    $db->quoteName('#__users', 'u'),
                    $db->quoteName('u') . '.' . $db->quoteName('id') . ' = ' . $db->quoteName('pc') . '.' . $db->quoteName('created_by')
                )
                ->group($agentExpr)
                ->order($db->quoteName('sum_bonos_total') . ' DESC');
        } else {
            $unknown   = $db->quote('-');
            $agentExpr = 'COALESCE(NULLIF(TRIM(' . $db->quoteName('u') . '.' . $db->quoteName('name') . '), \'\'), ' . $unknown . ')';

            $q = $db->getQuery(true)
                ->select([
                    $agentExpr . ' AS ' . $db->quoteName('agent_label'),
                    'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_amount') . ', 0)) AS ' . $db->quoteName('sum_bono_venta'),
                    'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_margen_adicional') . ', 0)) AS ' . $db->quoteName('sum_bono_margen_adicional'),
                    'SUM(COALESCE(' . $db->quoteName('pc') . '.' . $db->quoteName('comision_amount') . ', 0) + COALESCE('
                    . $db->quoteName('pc') . '.' . $db->quoteName('comision_margen_adicional') . ', 0)) AS ' . $db->quoteName('sum_bonos_total'),
                ])
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'pc'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__users', 'u'),
                    $db->quoteName('u') . '.' . $db->quoteName('id') . ' = ' . $db->quoteName('pc') . '.' . $db->quoteName('created_by')
                )
                ->group($agentExpr)
                ->order($db->quoteName('sum_bonos_total') . ' DESC');
        }

        try {
            $db->setQuery($q);

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

