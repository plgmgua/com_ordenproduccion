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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Date\Date;

/**
 * Dashboard model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class DashboardModel extends BaseDatabaseModel
{
    /**
     * Get dashboard statistics
     *
     * @return  array  Statistics data
     *
     * @since   1.0.0
     */
    public function getStatistics()
    {
        $db = $this->getDbo();
        $statistics = [];

        try {
            // Total orders
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['total_orders'] = (int) $db->loadResult();

            // Pending orders (nueva)
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('attribute_name') . ' = ' . $db->quote('estado'))
                ->where($db->quoteName('attribute_value') . ' = ' . $db->quote('nueva'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['pending_orders'] = (int) $db->loadResult();

            // In process orders
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('attribute_name') . ' = ' . $db->quote('estado'))
                ->where($db->quoteName('attribute_value') . ' = ' . $db->quote('en_proceso'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['in_process_orders'] = (int) $db->loadResult();

            // Completed orders
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('attribute_name') . ' = ' . $db->quote('estado'))
                ->where($db->quoteName('attribute_value') . ' = ' . $db->quote('terminada'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['completed_orders'] = (int) $db->loadResult();

            // Closed orders
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('attribute_name') . ' = ' . $db->quote('estado'))
                ->where($db->quoteName('attribute_value') . ' = ' . $db->quote('cerrada'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['closed_orders'] = (int) $db->loadResult();

            // Active technicians today
            $today = Factory::getDate()->format('Y-m-d');
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ' . $db->quoteName('technician_id') . ')')
                ->from($db->quoteName('#__ordenproduccion_attendance'))
                ->where($db->quoteName('attendance_date') . ' = ' . $db->quote($today))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['active_technicians'] = (int) $db->loadResult();

            // Orders due today
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('delivery_date') . ' = ' . $db->quote($today))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['orders_due_today'] = (int) $db->loadResult();

            // Overdue orders
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('delivery_date') . ' < ' . $db->quote($today))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['overdue_orders'] = (int) $db->loadResult();

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading dashboard statistics: ' . $e->getMessage(),
                'error'
            );
            
            // Return default values on error
            $statistics = [
                'total_orders' => 0,
                'pending_orders' => 0,
                'in_process_orders' => 0,
                'completed_orders' => 0,
                'closed_orders' => 0,
                'active_technicians' => 0,
                'orders_due_today' => 0,
                'overdue_orders' => 0
            ];
        }

        return $statistics;
    }

    /**
     * Get recent orders
     *
     * @param   int  $limit  Number of orders to retrieve
     *
     * @return  array  Recent orders data
     *
     * @since   1.0.0
     */
    public function getRecentOrders($limit = 10)
    {
        $db = $this->getDbo();
        
        try {
            $query = $db->getQuery(true)
                ->select([
                    'o.id',
                    'o.order_number',
                    'o.client_name',
                    'o.delivery_date',
                    'o.created',
                    'i.attribute_value as status'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_info', 'i') . ' ON ' .
                    $db->quoteName('i.order_id') . ' = ' . $db->quoteName('o.id') . ' AND ' .
                    $db->quoteName('i.attribute_name') . ' = ' . $db->quote('estado')
                )
                ->where($db->quoteName('o.state') . ' = 1')
                ->order($db->quoteName('o.created') . ' DESC')
                ->setLimit($limit);
            
            $db->setQuery($query);
            return $db->loadObjectList();
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading recent orders: ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Get calendar data for orders
     *
     * @param   string  $year   Year
     * @param   string  $month  Month
     *
     * @return  array  Calendar data
     *
     * @since   1.0.0
     */
    public function getCalendarData($year = null, $month = null)
    {
        $db = $this->getDbo();
        
        if (!$year) {
            $year = Factory::getDate()->format('Y');
        }
        if (!$month) {
            $month = Factory::getDate()->format('m');
        }
        
        try {
            $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
            $endDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-31';
            
            $query = $db->getQuery(true)
                ->select([
                    'DATE(o.created) as order_date',
                    'COUNT(*) as order_count'
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->where($db->quoteName('o.state') . ' = 1')
                ->where($db->quoteName('o.created') . ' >= ' . $db->quote($startDate))
                ->where($db->quoteName('o.created') . ' <= ' . $db->quote($endDate))
                ->group('DATE(o.created)')
                ->order('DATE(o.created)');
            
            $db->setQuery($query);
            $results = $db->loadObjectList();
            
            $calendarData = [];
            foreach ($results as $result) {
                $calendarData[$result->order_date] = (int) $result->order_count;
            }
            
            return $calendarData;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading calendar data: ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Get component version information
     *
     * @return  array  Version information
     *
     * @since   1.0.0
     */
    public function getVersionInfo()
    {
        $db = $this->getDbo();
        
        try {
            $query = $db->getQuery(true)
                ->select('config_value')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('config_key') . ' = ' . $db->quote('component_version'));
            
            $db->setQuery($query);
            $version = $db->loadResult();
            
            return [
                'version' => $version ?: '1.0.0-STABLE',
                'joomla_version' => JVERSION,
                'php_version' => PHP_VERSION,
                'database_version' => $db->getVersion()
            ];
            
        } catch (\Exception $e) {
            return [
                'version' => '1.0.0-STABLE',
                'joomla_version' => JVERSION,
                'php_version' => PHP_VERSION,
                'database_version' => 'Unknown'
            ];
        }
    }
}
