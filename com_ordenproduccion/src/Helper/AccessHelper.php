<?php
/**
 * Access Helper for com_ordenproduccion
 * 
 * Handles user group-based access control for orders
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @subpackage  Access
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Access Helper Class
 */
class AccessHelper
{
    /**
     * Check if user is in Ventas group
     *
     * @return  boolean
     */
    public static function isInVentasGroup()
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();
        
        // Get group names to check for 'Ventas'
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title')
            ->from('#__usergroups')
            ->where('id IN (' . implode(',', $userGroups) . ')');
        $db->setQuery($query);
        $groups = $db->loadObjectList();
        
        foreach ($groups as $group) {
            if ($group->title === 'Ventas') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is in Produccion group
     *
     * @return  boolean
     */
    public static function isInProduccionGroup()
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();
        
        // Get group names to check for 'Produccion'
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title')
            ->from('#__usergroups')
            ->where('id IN (' . implode(',', $userGroups) . ')');
        $db->setQuery($query);
        $groups = $db->loadObjectList();
        
        foreach ($groups as $group) {
            if ($group->title === 'Produccion') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is in Administracion group
     *
     * @return  boolean
     */
    public static function isInAdministracionGroup()
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();
        
        // Check for Administracion group (ID 12) or by name
        if (in_array(12, $userGroups)) {
            return true;
        }
        
        // Also check by name for safety
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title')
            ->from('#__usergroups')
            ->where('id IN (' . implode(',', $userGroups) . ')');
        $db->setQuery($query);
        $groups = $db->loadObjectList();
        
        foreach ($groups as $group) {
            if ($group->title === 'Administracion') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user is in both Ventas and Produccion groups
     *
     * @return  boolean
     */
    public static function isInBothGroups()
    {
        return self::isInVentasGroup() && self::isInProduccionGroup();
    }

    /**
     * Check if user can see all orders (not just their own)
     *
     * @return  boolean
     */
    public static function canSeeAllOrders()
    {
        // Can see all orders if in Administracion, Produccion group, or both Ventas+Produccion groups
        return self::isInAdministracionGroup() || self::isInProduccionGroup() || self::isInBothGroups();
    }

    /**
     * Check if user can see valor_factura field
     *
     * @param   string  $salesAgent  The sales agent name from the order
     * @return  boolean
     */
    public static function canSeeValorFactura($salesAgent = null)
    {
        $user = Factory::getUser();
        
        // If user is in Administracion group, can see valor_factura for all orders
        if (self::isInAdministracionGroup()) {
            return true;
        }
        
        // If user is in Produccion group only, cannot see valor_factura
        if (self::isInProduccionGroup() && !self::isInVentasGroup()) {
            return false;
        }
        
        // If user is in Ventas group (alone or with Produccion), can see valor_factura only for their own orders
        if (self::isInVentasGroup()) {
            // If no sales agent provided, assume it's their own order
            if ($salesAgent === null) {
                return true;
            }
            
            // Check if the sales agent matches the user's name
            return $salesAgent === $user->name;
        }
        
        return false;
    }

    /**
     * Get the sales agent filter for the current user
     *
     * @return  string|null
     */
    public static function getSalesAgentFilter()
    {
        $user = Factory::getUser();
        
        // If user can see all orders, no filter needed
        if (self::canSeeAllOrders()) {
            return null;
        }
        
        // If user is only in Ventas group, filter by their name
        if (self::isInVentasGroup() && !self::isInProduccionGroup()) {
            return $user->name;
        }
        
        return null;
    }

    /**
     * Get user's access level description
     *
     * @return  string
     */
    public static function getAccessLevelDescription()
    {
        if (self::isInAdministracionGroup()) {
            return 'Administracion: Can see all orders with valor_factura visible for all orders';
        }
        
        if (self::isInBothGroups()) {
            return 'Ventas + Produccion: Can see all orders, valor_factura visible only for own orders';
        }
        
        if (self::isInVentasGroup() && !self::isInProduccionGroup()) {
            return 'Ventas only: Can see only own orders with valor_factura visible';
        }
        
        if (self::isInProduccionGroup() && !self::isInVentasGroup()) {
            return 'Produccion only: Can see all orders, but valor_factura is hidden';
        }
        
        return 'No access: Cannot see orders';
    }

    /**
     * Check if user has any access to orders
     *
     * @return  boolean
     */
    public static function hasOrderAccess()
    {
        return self::isInVentasGroup() || self::isInProduccionGroup() || self::isInAdministracionGroup();
    }

    /**
     * Check if user can register a payment proof (comprobante de pago).
     * Available to everyone with order access so sales can register payments for their own work orders.
     *
     * @return  boolean
     */
    public static function canRegisterPaymentProof()
    {
        return self::hasOrderAccess();
    }
}
