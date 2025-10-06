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
use Joomla\CMS\MVC\Model\ListModel;

/**
 * Technician model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class TechnicianModel extends ListModel
{
    /**
     * Get today's technicians from attendance
     *
     * @return  array  Today's technicians
     *
     * @since   1.0.0
     */
    public function getTodaysTechnicians()
    {
        $db = $this->getDbo();
        $today = Factory::getDate()->format('Y-m-d');
        
        try {
            $query = $db->getQuery(true)
                ->select('DISTINCT person_name')
                ->from($db->quoteName('#__ordenproduccion_attendance'))
                ->where($db->quoteName('auth_date') . ' = ' . $db->quote($today))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('person_name'));
            
            $db->setQuery($query);
            return $db->loadColumn();
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading today\'s technicians: ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Get technician assignments for an order
     *
     * @param   string  $orderNumber  The order number
     *
     * @return  array  Technician assignments
     *
     * @since   1.0.0
     */
    public function getOrderTechnicians($orderNumber)
    {
        $db = $this->getDbo();
        
        try {
            $query = $db->getQuery(true)
                ->select(['technician_name', 'assigned_date', 'status', 'notes'])
                ->from($db->quoteName('#__ordenproduccion_technicians'))
                ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('assigned_date') . ' DESC');
            
            $db->setQuery($query);
            return $db->loadObjectList();
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading order technicians: ' . $e->getMessage(),
                'error'
            );
            return [];
        }
    }

    /**
     * Assign technicians to an order
     *
     * @param   string  $orderNumber  The order number
     * @param   array   $technicians  Array of technician names
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function assignTechnicians($orderNumber, $technicians)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        try {
            // Remove existing assignments
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_technicians'))
                ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
            
            $db->setQuery($query);
            $db->execute();
            
            // Add new assignments
            foreach ($technicians as $technician) {
                if (!empty($technician)) {
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_technicians'))
                        ->columns(['numero_de_orden', 'technician_name', 'assigned_by', 'created_by'])
                        ->values([
                            $db->quote($orderNumber),
                            $db->quote($technician),
                            $db->quote($user->id),
                            $db->quote($user->id)
                        ]);
                    
                    $db->setQuery($query);
                    $db->execute();
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error assigning technicians: ' . $e->getMessage(),
                'error'
            );
            return false;
        }
    }
}
