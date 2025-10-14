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

        // 7. Top 10 Clients Trend Data (supports yearly/monthly/daily views)
        $currentYear = (int) $year;
        $currentMonth = (int) $month;
        $stats->clientTrend = $this->getClientTrend($currentYear, $currentMonth);

        // 8. Sales Agents Trend Data (supports yearly/monthly/daily views)
        $stats->agentTrend = $this->getAgentTrend($currentYear, $currentMonth);

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
     * Get sales agents trend data
     *
     * @param   int  $year   Year
     * @param   int  $month  Month (0 for yearly view)
     *
     * @return  array  Trend data by agent
     *
     * @since   3.52.7
     */
    protected function getAgentTrend($year, $month)
    {
        $db = Factory::getDbo();
        
        // Determine if we're showing yearly (12 months) or monthly (days) view
        $isMonthlyView = ($month > 0);
        
        if ($isMonthlyView) {
            // Monthly view: show daily data for selected month
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $labels = range(1, $daysInMonth);
            
            // Get all agents for this month
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $startDate = $year . '-' . $monthStr . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('sales_agent'))
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('created') . ' <= ' . $db->quote($endDate . ' 23:59:59'))
                ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
                ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
                ->order($db->quoteName('sales_agent'));
            $db->setQuery($query);
            $agents = $db->loadColumn() ?: [];
            
            // Get daily data for each agent
            $trendData = [];
            foreach ($agents as $agentName) {
                $agentTrend = ['agent_name' => $agentName, 'data' => []];
                
                foreach ($labels as $day) {
                    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $dateStr = $year . '-' . $monthStr . '-' . $dayStr;
                    
                    $query = $db->getQuery(true)
                        ->select('SUM(CAST(' . $db->quoteName('invoice_value') . ' AS DECIMAL(10,2))) as total')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('state') . ' = 1')
                        ->where('DATE(' . $db->quoteName('created') . ') = ' . $db->quote($dateStr))
                        ->where($db->quoteName('sales_agent') . ' = ' . $db->quote($agentName));
                    $db->setQuery($query);
                    $total = $db->loadResult() ?: 0;
                    
                    $agentTrend['data'][] = (float) $total;
                }
                
                $trendData[] = $agentTrend;
            }
        } else {
            // Yearly view: show monthly data for selected year
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
                    
                    $agentTrend['data'][] = (float) $total;
                }
                
                $trendData[] = $agentTrend;
            }
        }

        return [
            'labels' => $labels,
            'agents' => $trendData,
            'view' => $isMonthlyView ? 'daily' : 'monthly'
        ];
    }
}

