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
     * @param   int  $month  Month number (1-12)
     * @param   int  $year   Year (e.g., 2025)
     *
     * @return  object  Statistics data
     *
     * @since   3.1.0
     */
    public function getStatistics($month, $year)
    {
        $db = Factory::getDbo();
        $stats = new \stdClass();

        // Handle "All Year" case (month = 0) vs specific month
        if ($month == 0) {
            // All Year: from January 1st to December 31st
            $startDate = $year . '-01-01';
            $endDate = $year . '-12-31';
        } else {
            // Specific month: first day to last day of month
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $startDate = $year . '-' . $monthStr . '-01';
            $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
        }

        // 1. Total work orders in selected month
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'));
        $db->setQuery($query);
        $stats->totalOrders = $db->loadResult() ?: 0;

        // 2. Total invoice value for selected month
        $query = $db->getQuery(true)
            ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'));
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
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->group($db->quoteName('sales_agent'))
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
        $stats->agentTrend = $this->getAgentAnnualTrend($currentYear);
        
        // 8. Top 10 Clients Annual Trend Data (yearly view only)
        $stats->clientTrend = $this->getClientAnnualTrend($currentYear);

        return $stats;
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
    protected function getAgentAnnualTrend($year)
    {
        $db = Factory::getDbo();
        
        // Always show monthly data for selected year
        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        // Get all agents for this year
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('YEAR(' . $db->quoteName('created') . ') = ' . $year)
            ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->order($db->quoteName('sales_agent'));
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
    protected function getClientAnnualTrend($year)
    {
        $db = Factory::getDbo();
        
        // Always show monthly data for selected year
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
     * @param   string  $period  Period to get stats for: 'day', 'week', or 'month'
     *
     * @return  object  Activity statistics data
     *
     * @since   3.6.0
     */
    public function getActivityStatistics($period = 'day')
    {
        $db = Factory::getDbo();
        $stats = new \stdClass();

        // Get today's date range
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // Get this week's date range (Monday to Sunday)
        $weekStart = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
        $weekEnd = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';

        // Get this month's date range
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthEnd = date('Y-m-t') . ' 23:59:59';

        // Daily statistics
        $stats->daily = $this->getActivityStatsForPeriod($todayStart, $todayEnd);

        // Weekly statistics
        $stats->weekly = $this->getActivityStatsForPeriod($weekStart, $weekEnd);

        // Monthly statistics
        $stats->monthly = $this->getActivityStatsForPeriod($monthStart, $monthEnd);

        return $stats;
    }

    /**
     * Get activity statistics grouped by sales agent for a period
     *
     * @param   string  $period  Period to get stats for: 'day', 'week', or 'month'
     *
     * @return  array  Activity statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    public function getActivityStatisticsByAgent($period = 'day')
    {
        // Determine date range based on period
        switch ($period) {
            case 'week':
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'day':
            default:
                $today = date('Y-m-d');
                $startDate = $today . ' 00:00:00';
                $endDate = $today . ' 23:59:59';
                break;
        }

        return $this->getActivityStatsByAgentForPeriod($startDate, $endDate);
    }

    /**
     * Get activity statistics for a specific date range
     *
     * @param   string  $startDate  Start date (Y-m-d H:i:s)
     * @param   string  $endDate    End date (Y-m-d H:i:s)
     *
     * @return  object  Activity statistics for the period
     *
     * @since   3.6.0
     */
    protected function getActivityStatsForPeriod($startDate, $endDate)
    {
        $db = Factory::getDbo();
        $stats = new \stdClass();

        // 1. Work orders created
        $query = $db->getQuery(true)
            ->select('COUNT(*) as total')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
            ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate));
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
     * @param   string  $startDate  Start date (Y-m-d H:i:s)
     * @param   string  $endDate    End date (Y-m-d H:i:s)
     *
     * @return  array  Activity statistics grouped by sales agent
     *
     * @since   3.6.0
     */
    protected function getActivityStatsByAgentForPeriod($startDate, $endDate)
    {
        $db = Factory::getDbo();
        $agentsStats = [];

        // Get all sales agents who have orders in this period
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
                    $db->quoteName('created')
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
                $db->quoteName('created')
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
    public function getStatusChangesByAgent($period = 'day')
    {
        // Determine date range based on period
        switch ($period) {
            case 'week':
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'day':
            default:
                $today = date('Y-m-d');
                $startDate = $today . ' 00:00:00';
                $endDate = $today . ' 23:59:59';
                break;
        }

        return $this->getStatusChangesByAgentForPeriod($startDate, $endDate);
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
    protected function getStatusChangesByAgentForPeriod($startDate, $endDate)
    {
        $db = Factory::getDbo();
        $agentsStats = [];

        // Get all sales agents who have status changes in this period
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
    public function getPaymentProofsByAgent($period = 'day')
    {
        // Determine date range based on period
        switch ($period) {
            case 'week':
                $startDate = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $endDate = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                break;
            case 'month':
                $startDate = date('Y-m-01') . ' 00:00:00';
                $endDate = date('Y-m-t') . ' 23:59:59';
                break;
            case 'day':
            default:
                $today = date('Y-m-d');
                $startDate = $today . ' 00:00:00';
                $endDate = $today . ' 23:59:59';
                break;
        }

        return $this->getPaymentProofsByAgentForPeriod($startDate, $endDate);
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
    protected function getPaymentProofsByAgentForPeriod($startDate, $endDate)
    {
        $db = Factory::getDbo();
        $agentsStats = [];

        // Get all sales agents who have payment proofs in this period
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
}

