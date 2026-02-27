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
    public function getReportWorkOrders($dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '', $limit = 0, $offset = 0)
    {
        $db = Factory::getDbo();
        $query = $this->buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName, $nit, $salesAgent);
        $query->select([
            $db->quoteName('orden_de_trabajo'),
            $db->quoteName('work_description'),
            $db->quoteName('invoice_value'),
            $db->quoteName('client_name'),
            $db->quoteName('request_date'),
            $db->quoteName('delivery_date'),
        ])->order($db->quoteName('orden_de_trabajo') . ' ASC');
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
    public function getReportWorkOrdersTotal($dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '')
    {
        $db = Factory::getDbo();
        $query = $this->buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName, $nit, $salesAgent);
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
    public function getReportWorkOrdersTotalValue($dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '')
    {
        $db = Factory::getDbo();
        $query = $this->buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName, $nit, $salesAgent);
        $query->select('COALESCE(SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(15,2))), 0)');
        $db->setQuery($query);
        return (float) $db->loadResult();
    }

    /**
     * Build base query for report work orders (FROM + WHERE only).
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   string  $dateFrom
     * @param   string  $dateTo
     * @param   string  $clientName
     * @param   string  $nit
     * @param   string  $salesAgent
     *
     * @return  \Joomla\Database\DatabaseQuery
     * @since   3.78.0
     */
    protected function buildReportWorkOrdersQuery($db, $dateFrom, $dateTo, $clientName = '', $nit = '', $salesAgent = '')
    {
        $query = $db->getQuery(true)
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1');

        if (!empty($dateFrom)) {
            $query->where($db->quoteName('created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }
        if (!empty($dateTo)) {
            $query->where($db->quoteName('created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }
        if (!empty($clientName)) {
            $query->where($db->quoteName('client_name') . ' = ' . $db->quote($clientName));
        }
        if (!empty($nit)) {
            $query->where($db->quoteName('nit') . ' = ' . $db->quote($nit));
        }
        if (!empty($salesAgent)) {
            $query->where($db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgent));
        }

        return $query;
    }

    /**
     * Get clients with totals (and optional filters / pagination).
     * Accounting: Saldo = Total invoiced - (initial_paid_to_dec31_2025 + payments from Jan 1 2026)
     *
     * @param   string   $ordering         Sort column: name, compras, saldo
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
                $va = (float) ($a->saldo ?? 0);
                $vb = (float) ($b->saldo ?? 0);
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
        $invoiceOctDec2025Map = $this->getInvoiceValueOctDec2025ByClientMap($db);
        $invoiceFromJan2026Map = $this->getInvoiceValueFromJan2026ByClientMap($db);

        foreach ($clients as $c) {
            $key = $this->clientKey($c->client_name ?? '', $c->nit ?? '');
            $initialPaid = (float) ($openingMap[$key] ?? 0);
            $paidFromJan = (float) ($paidFromJan2026Map[$key] ?? 0);
            $totalInvoiced = (float) ($c->total_invoice_value ?? 0);
            $invoiceOctDec2025 = (float) ($invoiceOctDec2025Map[$key] ?? 0);
            $compras = (float) ($invoiceFromJan2026Map[$key] ?? 0);
            $c->invoice_value_oct_dec_2025 = $invoiceOctDec2025;
            $c->initial_paid_to_dec31_2025 = $initialPaid;
            $c->display_pagado = $initialPaid > 0 ? $initialPaid : $invoiceOctDec2025;
            $c->paid_from_jan2026 = $paidFromJan;
            $c->compras = $compras;
            // Accounting convention: positive = client owes, negative = client has credit (advance). Display as -1 * saldo (unpaid = negative, in favor = positive).
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
     * Get map of client_key => sum of payments from Jan 1 2026
     */
    protected function getPaidFromJan2026ByClientMap($db)
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
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd = date('Y-m-t') . ' 23:59:59';
        $yearStart = date('Y-01-01') . ' 00:00:00';
        $yearEnd = date('Y-12-31') . ' 23:59:59';

        $stats = new \stdClass();
        $stats->weekly = $this->getActivityStatsForPeriod($weekStart, $weekEnd, $salesAgent);
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

        foreach ($agents as $agent) {
            $agentStats = new \stdClass();
            $agentStats->salesAgent = $agent;

            // Get payment proofs for this agent
            $query = $db->getQuery(true)
                ->select([
                    'pp.' . $db->quoteName('id'),
                    'pp.' . $db->quoteName('order_id'),
                    'pp.' . $db->quoteName('payment_amount'),
                    'pp.' . $db->quoteName('created'),
                    'o.' . $db->quoteName('orden_de_trabajo'),
                    'o.' . $db->quoteName('order_number'),
                    'o.' . $db->quoteName('work_description')
                ])
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

            // Get total money collected
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

            $agentsStats[] = $agentStats;
        }

        // Get payment proofs without agent
        $query = $db->getQuery(true)
            ->select([
                'pp.' . $db->quoteName('id'),
                'pp.' . $db->quoteName('order_id'),
                'pp.' . $db->quoteName('payment_amount'),
                'pp.' . $db->quoteName('created'),
                'o.' . $db->quoteName('orden_de_trabajo'),
                'o.' . $db->quoteName('order_number'),
                'o.' . $db->quoteName('work_description')
            ])
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
            ->where('pp.' . $db->quoteName('state') . ' = 1')
            ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
            ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . 
                   $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' .
                   $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
            ->order('pp.' . $db->quoteName('created') . ' DESC');
        $db->setQuery($query);
        $noAgentPaymentProofs = $db->loadObjectList() ?: [];

        if (!empty($noAgentPaymentProofs)) {
            $noAgentStats = new \stdClass();
            $noAgentStats->salesAgent = null;
            $noAgentStats->paymentProofs = $noAgentPaymentProofs;
            $noAgentStats->paymentProofsCount = count($noAgentPaymentProofs);

            $query = $db->getQuery(true)
                ->select('SUM(CAST(pp.' . $db->quoteName('payment_amount') . ' AS DECIMAL(10,2))) as total')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                ->join('INNER', $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON pp.' . $db->quoteName('order_id') . ' = o.' . $db->quoteName('id'))
                ->where('pp.' . $db->quoteName('state') . ' = 1')
                ->where('pp.' . $db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where('pp.' . $db->quoteName('created') . ' <= ' . $db->quote($endDate))
                ->where('(o.' . $db->quoteName('sales_agent') . ' IS NULL OR o.' . 
                       $db->quoteName('sales_agent') . ' = ' . $db->quote('') . ' OR o.' .
                       $db->quoteName('sales_agent') . ' = ' . $db->quote(' ') . ')')
                ->where('pp.' . $db->quoteName('payment_amount') . ' IS NOT NULL')
                ->where('pp.' . $db->quoteName('payment_amount') . ' > 0');
            $db->setQuery($query);
            $noAgentStats->moneyCollected = (float) ($db->loadResult() ?: 0);

            $agentsStats[] = $noAgentStats;
        }

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
        return [
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
}

