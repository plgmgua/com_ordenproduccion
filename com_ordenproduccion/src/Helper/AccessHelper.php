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
     * Global Super Users (core.admin), e.g. Conciliar facturas con órdenes subtab.
     *
     * @return  bool
     */
    public static function isSuperUser()
    {
        return Factory::getUser()->authorise('core.admin');
    }

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
        return self::isInAdministracionOrAdmonGroup();
    }

    /**
     * Check if user is in Administracion or Admon group (admin-only tabs: work orders, invoices, tools)
     *
     * @return  boolean
     */
    public static function isInAdministracionOrAdmonGroup()
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();
        
        // Check for Administracion group (ID 12) or by name
        if (in_array(12, $userGroups)) {
            return true;
        }
        
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, title')
            ->from('#__usergroups')
            ->where('id IN (' . implode(',', array_map('intval', $userGroups)) . ')');
        $db->setQuery($query);
        $groups = $db->loadObjectList() ?: [];
        
        foreach ($groups as $group) {
            if ($group->title === 'Administracion' || $group->title === 'Admon') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get email addresses of all users in the Administracion (or Admon) group.
     * Used to notify administration when a payment proof is saved with a totals mismatch.
     *
     * @return  string[]  Array of unique email addresses
     */
    public static function getAdministracionGroupEmails()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('g.id'))
            ->from($db->quoteName('#__usergroups', 'g'))
            ->where($db->quoteName('g.title') . ' IN (' . $db->quote('Administracion') . ',' . $db->quote('Admon') . ')');
        $db->setQuery($query);
        $groupIds = $db->loadColumn() ?: [];
        if (empty($groupIds)) {
            return [];
        }
        $groupIds = array_map('intval', $groupIds);
        $subQuery = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__user_usergroup_map'))
            ->where($db->quoteName('group_id') . ' IN (' . implode(',', $groupIds) . ')');
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('email'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' IN (' . (string) $subQuery . ')')
            ->where($db->quoteName('email') . ' != ' . $db->quote(''));
        $db->setQuery($query);
        $emails = $db->loadColumn() ?: [];
        return array_values(array_unique(array_filter($emails)));
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
     * Check if user can see valor_factura (Valor a Facturar) for a given order.
     * Administracion: see all. Produccion-only (no Ventas): never — they see all órdenes but not this field.
     * Ventas (including Ventas+Produccion): only when sales_agent matches the current user.
     *
     * @param   string|null  $salesAgent  The sales agent name from the order
     * @return  boolean
     */
    public static function canSeeValorFactura($salesAgent = null)
    {
        $user = Factory::getUser();

        if (self::isInAdministracionGroup()) {
            return true;
        }

        if (self::isInProduccionGroup() && !self::isInVentasGroup()) {
            return false;
        }

        if (self::isInVentasGroup()) {
            $agent = trim((string) $salesAgent);

            return $agent !== '' && $agent === trim($user->name);
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
     * Get the sales agent filter for the Administracion view only.
     * Only members of Administracion (or Admon) see everyone's data; all others see only their own records.
     *
     * @return  string|null  Null = see all (Administracion/Admon only), otherwise current user's name
     */
    public static function getSalesAgentFilterForAdministracionView()
    {
        if (self::isInAdministracionOrAdmonGroup()) {
            return null;
        }
        $user = Factory::getUser();
        return $user->name;
    }

    /**
     * Sales agent filter for comprobantes de pago (payment proofs list, single proof, delete, etc.).
     * Only Administracion (or Admon) see all records. Ventas (including Ventas+Produccion) and Produccion-only
     * see only proofs linked to orders where sales_agent matches the current user.
     *
     * @return  string|null  Null = see all; otherwise filter by this sales agent name
     */
    public static function getSalesAgentFilterForPaymentProofs()
    {
        if (self::isInAdministracionOrAdmonGroup()) {
            return null;
        }
        $user = Factory::getUser();
        return $user->name;
    }

    /**
     * Whether the current user may view/manage comprobantes de pago for an order (by sales_agent).
     *
     * @param   string|null  $salesAgent  Order's sales_agent value
     *
     * @return  boolean
     */
    public static function canAccessPaymentProofForOrder($salesAgent = null)
    {
        if (self::isInAdministracionOrAdmonGroup()) {
            return true;
        }
        $user = Factory::getUser();
        $agent = trim((string) $salesAgent);
        return $agent !== '' && $agent === trim($user->name);
    }

    /**
     * Whether the user may open the cotización (quotation) PDF for a work order in the list/detail UI.
     * Administracion/Admon: all. Ventas (including Ventas+Produccion): own orders only. Produccion-only: no access.
     *
     * @param   string|null  $salesAgent  Order's sales_agent value
     *
     * @return  boolean
     */
    public static function canViewCotizacionPdfForOrder($salesAgent = null)
    {
        if (self::isInAdministracionOrAdmonGroup()) {
            return true;
        }
        if (!self::isInVentasGroup()) {
            return false;
        }
        $user = Factory::getUser();
        $agent = trim((string) $salesAgent);
        return $agent !== '' && $agent === trim($user->name);
    }

    /**
     * Collect work order IDs linked to an invoice: legacy orden_id on the invoice row and/or approved rows in invoice_orden_suggestions.
     *
     * @param   int  $invoiceId  Published invoice id
     *
     * @return  int[]
     *
     * @since   3.103.8
     */
    public static function getOrderIdsLinkedToInvoice(int $invoiceId): array
    {
        if ($invoiceId <= 0) {
            return [];
        }

        $db   = Factory::getDbo();
        $seen = [];

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('orden_id'))
                    ->from($db->quoteName('#__ordenproduccion_invoices'))
                    ->where($db->quoteName('id') . ' = ' . (int) $invoiceId)
                    ->where($db->quoteName('state') . ' = 1')
            );
            $legacyOid = (int) $db->loadResult();
            if ($legacyOid > 0) {
                $seen[$legacyOid] = true;
            }
        } catch (\Throwable $e) {
            return [];
        }

        try {
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $want   = $prefix . 'ordenproduccion_invoice_orden_suggestions';
            $has    = false;
            foreach ($tables as $t) {
                if (strcasecmp((string) $t, $want) === 0) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                return array_map('intval', array_keys($seen));
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->select('DISTINCT ' . $db->quoteName('orden_id'))
                    ->from($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                    ->where($db->quoteName('invoice_id') . ' = ' . (int) $invoiceId)
                    ->where($db->quoteName('status') . ' = ' . $db->quote('approved'))
                    ->where($db->quoteName('state') . ' = 1')
            );
            $rows = $db->loadColumn() ?: [];
            foreach ($rows as $oid) {
                $oid = (int) $oid;
                if ($oid > 0) {
                    $seen[$oid] = true;
                }
            }
        } catch (\Throwable $e) {
        }

        return array_map('intval', array_keys($seen));
    }

    /**
     * Whether the user may open the single invoice detail view (view=invoice&id=).
     * Super user / Administracion/Admon: all invoices.
     * Produccion-only: any invoice linked to at least one published orden (any owner).
     * Ventas (including Ventas+Produccion): invoice linked to at least one published orden owned by the user (sales_agent).
     *
     * @param   int  $invoiceId  Invoice primary key
     *
     * @return  bool
     *
     * @since   3.103.8
     */
    public static function canViewInvoiceDetail(int $invoiceId): bool
    {
        if ($invoiceId <= 0) {
            return false;
        }

        if (self::isSuperUser()) {
            return true;
        }

        if (self::isInAdministracionOrAdmonGroup()) {
            return true;
        }

        $orderIds = self::getOrderIdsLinkedToInvoice($invoiceId);
        if ($orderIds === []) {
            return false;
        }

        $db  = Factory::getDbo();
        $ids = implode(',', array_map('intval', $orderIds));

        try {
            if (self::isInProduccionGroup() && !self::isInVentasGroup()) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('id') . ' IN (' . $ids . ')')
                        ->where($db->quoteName('state') . ' = 1')
                );

                return (int) $db->loadResult() > 0;
            }

            if (!self::isInVentasGroup()) {
                return false;
            }

            $user   = Factory::getUser();
            $myName = trim((string) $user->name);
            if ($myName === '') {
                return false;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->select([$db->quoteName('id'), $db->quoteName('sales_agent')])
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('id') . ' IN (' . $ids . ')')
                    ->where($db->quoteName('state') . ' = 1')
            );
            $rows = $db->loadObjectList() ?: [];
            foreach ($rows as $row) {
                if (trim((string) ($row->sales_agent ?? '')) === $myName) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
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
            return 'Ventas + Produccion: Can see all orders, valor_factura visible only for own orders; comprobantes de pago only for own orders; cotización PDF only for own orders';
        }
        
        if (self::isInVentasGroup() && !self::isInProduccionGroup()) {
            return 'Ventas only: Can see only own orders with valor_factura visible';
        }
        
        if (self::isInProduccionGroup() && !self::isInVentasGroup()) {
            return 'Produccion only: Can see all orders; Valor a Facturar hidden; linked invoices viewable; comprobantes de pago only for own orders; cotización PDF not available';
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

    /**
     * Count of pending approval step rows for the current user (internal workflow engine).
     *
     * @param   int|null  $userId  User id or null for current user
     *
     * @return  int
     */
    public static function getPendingApprovalCountForUser($userId = null)
    {
        $user = Factory::getUser();
        $uid  = $userId !== null ? (int) $userId : (int) $user->id;

        if ($uid < 1) {
            return 0;
        }

        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_approval_request_steps', 's'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_approval_requests', 'r') . ' ON '
                . $db->quoteName('r.id') . ' = ' . $db->quoteName('s.request_id')
            )
            ->where($db->quoteName('s.approver_user_id') . ' = ' . $uid)
            ->where($db->quoteName('s.status') . ' = ' . $db->quote('pending'))
            ->where($db->quoteName('r.status') . ' = ' . $db->quote('pending'))
            ->where($db->quoteName('s.step_number') . ' = ' . $db->quoteName('r.current_step_number'));

        try {
            $db->setQuery($q);

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Whether the Administración frontend may show the Aprobaciones tab (pending items or admin).
     *
     * @return  bool
     */
    public static function canViewApprovalWorkflowTab()
    {
        if (self::isSuperUser() || self::isInAdministracionOrAdmonGroup()) {
            return true;
        }

        return self::getPendingApprovalCountForUser() > 0;
    }

    /**
     * Cotizaciones list/detail: same rule as pre-cotizaciones list — Administracion/Admon or super user sees all;
     * everyone else only rows they created (created_by).
     *
     * @return  bool
     *
     * @since   3.104.1
     */
    public static function canViewAllCotizacionesLikePrecot()
    {
        return self::isInAdministracionOrAdmonGroup() || Factory::getUser()->authorise('core.admin');
    }

    /**
     * Whether the current user may view or act on this quotation row (ownership vs admin).
     *
     * @param   object|null  $quotation  Row from #__ordenproduccion_quotations (needs created_by when not admin).
     *
     * @return  bool
     *
     * @since   3.104.1
     */
    public static function userCanAccessQuotationRow($quotation)
    {
        if (!$quotation) {
            return false;
        }

        if (self::canViewAllCotizacionesLikePrecot()) {
            return true;
        }

        return (int) ($quotation->created_by ?? 0) === (int) Factory::getUser()->id;
    }
}
