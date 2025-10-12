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

        // Format month and year for queries
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $startDate = $year . '-' . $monthStr . '-01';
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

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

        // 6. Sales by Sales Agent for selected month
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('sales_agent'),
                'COUNT(*) as order_count',
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
        $stats->salesByAgent = $db->loadObjectList() ?: [];

        return $stats;
    }
}

