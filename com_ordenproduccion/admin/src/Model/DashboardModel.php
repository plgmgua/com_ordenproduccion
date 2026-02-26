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
    /** @var string|null Cached order-number column for #__ordenproduccion_ordenes */
    protected $ordenesOrderCol;

    /** @var string|null Cached client column for #__ordenproduccion_ordenes */
    protected $ordenesClientCol;

    /** @var string|null Cached delivery-date column for #__ordenproduccion_ordenes */
    protected $ordenesDeliveryDateCol;

    /** @var array|null Cached [type_col, value_col, link_col] for #__ordenproduccion_info */
    protected $infoEavCols;

    /**
     * Get ordenes table column names (order number, client, delivery date). Schema-aware.
     *
     * @return  void
     * @since   1.0.0
     */
    protected function detectOrdenesColumns()
    {
        if ($this->ordenesOrderCol !== null) {
            return;
        }
        try {
            $db = $this->getDbo();
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $names = is_array($cols) ? array_keys($cols) : [];
            $this->ordenesOrderCol = in_array('orden_de_trabajo', $names, true) ? 'orden_de_trabajo' : (in_array('order_number', $names, true) ? 'order_number' : 'orden_de_trabajo');
            $this->ordenesClientCol = in_array('nombre_del_cliente', $names, true) ? 'nombre_del_cliente' : (in_array('client_name', $names, true) ? 'client_name' : '');
            $this->ordenesDeliveryDateCol = in_array('fecha_de_entrega', $names, true) ? 'fecha_de_entrega' : (in_array('delivery_date', $names, true) ? 'delivery_date' : '');
        } catch (\Throwable $e) {
            $this->ordenesOrderCol = 'orden_de_trabajo';
            $this->ordenesClientCol = 'nombre_del_cliente';
            $this->ordenesDeliveryDateCol = 'fecha_de_entrega';
        }
    }

    /**
     * Get info table EAV column names. Returns [type_col, value_col, link_col] or null if not usable.
     *
     * @return  array|null  [type_col, value_col, link_col] or null
     * @since   1.0.0
     */
    protected function getInfoEavColumns()
    {
        if ($this->infoEavCols !== null) {
            return $this->infoEavCols;
        }
        try {
            $db = $this->getDbo();
            $cols = $db->getTableColumns('#__ordenproduccion_info', false);
            $names = is_array($cols) ? array_keys($cols) : [];
            if (in_array('tipo_de_campo', $names, true) && in_array('valor', $names, true)) {
                $linkCol = in_array('numero_de_orden', $names, true) ? 'numero_de_orden' : (in_array('orden_de_trabajo', $names, true) ? 'orden_de_trabajo' : null);
                $this->infoEavCols = $linkCol ? ['tipo_de_campo', 'valor', $linkCol] : null;
            } elseif (in_array('attribute_name', $names, true) && in_array('attribute_value', $names, true)) {
                $linkCol = in_array('order_id', $names, true) ? 'order_id' : null;
                $this->infoEavCols = $linkCol ? ['attribute_name', 'attribute_value', $linkCol] : null;
            } else {
                $this->infoEavCols = [];
            }
        } catch (\Throwable $e) {
            $this->infoEavCols = [];
        }
        return $this->infoEavCols;
    }

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

            $eav = $this->getInfoEavColumns();
            $typeCol = $eav[0] ?? 'tipo_de_campo';
            $valueCol = $eav[1] ?? 'valor';
            if ($eav === null || $eav === []) {
                $statistics['pending_orders'] = 0;
                $statistics['in_process_orders'] = 0;
                $statistics['completed_orders'] = 0;
                $statistics['closed_orders'] = 0;
            } else {
                // Pending orders (nueva)
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_info'))
                    ->where($db->quoteName($typeCol) . ' = ' . $db->quote('estado'))
                    ->where($db->quoteName($valueCol) . ' = ' . $db->quote('nueva'))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $statistics['pending_orders'] = (int) $db->loadResult();

                // In process orders
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_info'))
                    ->where($db->quoteName($typeCol) . ' = ' . $db->quote('estado'))
                    ->where($db->quoteName($valueCol) . ' = ' . $db->quote('en_proceso'))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $statistics['in_process_orders'] = (int) $db->loadResult();

                // Completed orders
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_info'))
                    ->where($db->quoteName($typeCol) . ' = ' . $db->quote('estado'))
                    ->where($db->quoteName($valueCol) . ' = ' . $db->quote('terminada'))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $statistics['completed_orders'] = (int) $db->loadResult();

                // Closed orders
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_info'))
                    ->where($db->quoteName($typeCol) . ' = ' . $db->quote('estado'))
                    ->where($db->quoteName($valueCol) . ' = ' . $db->quote('cerrada'))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $statistics['closed_orders'] = (int) $db->loadResult();
            }

            // Active technicians today
            $today = Factory::getDate()->format('Y-m-d');
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ' . $db->quoteName('technician_id') . ')')
                ->from($db->quoteName('#__ordenproduccion_attendance'))
                ->where($db->quoteName('attendance_date') . ' = ' . $db->quote($today))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $statistics['active_technicians'] = (int) $db->loadResult();

            $this->detectOrdenesColumns();
            $deliveryCol = $this->ordenesDeliveryDateCol;
            if ($deliveryCol !== '') {
                // Orders due today
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName($deliveryCol) . ' = ' . $db->quote($today))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $statistics['orders_due_today'] = (int) $db->loadResult();

                // Overdue orders
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName($deliveryCol) . ' < ' . $db->quote($today))
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $statistics['overdue_orders'] = (int) $db->loadResult();
            } else {
                $statistics['orders_due_today'] = 0;
                $statistics['overdue_orders'] = 0;
            }

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
        $this->detectOrdenesColumns();
        $eav = $this->getInfoEavColumns();

        try {
            $orderCol = $this->ordenesOrderCol;
            $select = [
                'o.id',
                $db->quoteName('o.' . $orderCol, 'orden_de_trabajo'),
                $db->quoteName('o.created'),
            ];
            if ($this->ordenesClientCol !== '') {
                $select[] = $db->quoteName('o.' . $this->ordenesClientCol, 'nombre_del_cliente');
            } else {
                $select[] = $db->quote("") . ' AS ' . $db->quoteName('nombre_del_cliente');
            }
            if ($this->ordenesDeliveryDateCol !== '') {
                $select[] = $db->quoteName('o.' . $this->ordenesDeliveryDateCol, 'fecha_de_entrega');
            } else {
                $select[] = $db->quoteName('o.created', 'fecha_de_entrega');
            }
            if ($eav !== null && $eav !== []) {
                list($typeCol, $valueCol, $linkCol) = $eav;
                $select[] = $db->quoteName('i.' . $valueCol, 'status');
            } else {
                $select[] = $db->quote('') . ' AS ' . $db->quoteName('status');
            }

            $query = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'));

            if ($eav !== null && $eav !== []) {
                list($typeCol, $valueCol, $linkCol) = $eav;
                $onCond = $db->quoteName('i.' . $linkCol) . ' = ' . $db->quoteName('o.' . $orderCol)
                    . ' AND ' . $db->quoteName('i.' . $typeCol) . ' = ' . $db->quote('estado');
                $query->leftJoin($db->quoteName('#__ordenproduccion_info', 'i') . ' ON ' . $onCond);
            }

            $query
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
            $start = Factory::getDate($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00');
            $end = clone $start;
            $end->modify('last day of this month');
            $end->setTime(23, 59, 59);

            $startDate = $start->format('Y-m-d H:i:s');
            $endDate = $end->format('Y-m-d H:i:s');
            
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
