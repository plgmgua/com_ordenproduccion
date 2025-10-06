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
        return in_array('Ventas', $user->getAuthorisedGroups());
    }

    /**
     * Check if user is in Produccion group
     *
     * @return  boolean
     */
    public static function isInProduccionGroup()
    {
        $user = Factory::getUser();
        return in_array('Produccion', $user->getAuthorisedGroups());
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
        // Can see all orders if in Produccion group OR both groups
        return self::isInProduccionGroup() || self::isInBothGroups();
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
        return self::isInVentasGroup() || self::isInProduccionGroup();
    }
}
