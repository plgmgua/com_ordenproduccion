<?php

/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\Database\DatabaseInterface;

/**
 * SQL helpers for payment proof ↔ order amounts (junction + legacy).
 *
 * @since  3.119.99
 */
class PaymentOrderQueryHelper
{
    /**
     * Per-row amount credited to an order: prefer amount_applied; if zero and the proof
     * applies to a single order, fall back to payment_amount (legacy/migration data).
     *
     * @param   DatabaseInterface  $db
     * @param   string             $poAlias  Junction alias (e.g. po)
     * @param   string             $ppAlias  Proof alias (e.g. pp)
     *
     * @return  string  SQL expression
     */
    public static function effectiveAppliedAmountExpr(DatabaseInterface $db, string $poAlias = 'po', string $ppAlias = 'pp'): string
    {
        $poCnt = $db->quoteName('#__ordenproduccion_payment_orders', 'po_cnt');

        return 'CASE WHEN COALESCE(' . $poAlias . '.' . $db->quoteName('amount_applied') . ', 0) > 0'
            . ' THEN ' . $poAlias . '.' . $db->quoteName('amount_applied')
            . ' WHEN (SELECT COUNT(*) FROM ' . $poCnt
            . ' WHERE po_cnt.' . $db->quoteName('payment_proof_id') . ' = ' . $ppAlias . '.' . $db->quoteName('id') . ') = 1'
            . ' THEN ' . $ppAlias . '.' . $db->quoteName('payment_amount')
            . ' ELSE 0 END';
    }

    /**
     * Subquery: total paid for one work order (matches Registro de comprobantes totals).
     *
     * @param   DatabaseInterface  $db
     * @param   string             $orderIdColumn  e.g. o.id
     * @param   bool               $hasPaymentOrdersTable
     *
     * @return  string
     */
    public static function totalPaidSubqueryForOrder(DatabaseInterface $db, string $orderIdColumn, bool $hasPaymentOrdersTable): string
    {
        $pp = $db->quoteName('#__ordenproduccion_payment_proofs', 'pp');
        $po = $db->quoteName('#__ordenproduccion_payment_orders', 'po');
        $poX = $db->quoteName('#__ordenproduccion_payment_orders', 'po_x');
        $effective = self::effectiveAppliedAmountExpr($db, 'po', 'pp');

        if ($hasPaymentOrdersTable) {
            $junctionSum = '(SELECT COALESCE(SUM(' . $effective . '), 0) FROM ' . $po
                . ' INNER JOIN ' . $pp . ' ON pp.' . $db->quoteName('id') . ' = po.' . $db->quoteName('payment_proof_id')
                . ' AND pp.' . $db->quoteName('state') . ' = 1'
                . ' WHERE po.' . $db->quoteName('order_id') . ' = ' . $orderIdColumn . ')';

            $legacySum = '(SELECT COALESCE(SUM(pp.' . $db->quoteName('payment_amount') . '), 0) FROM ' . $pp
                . ' WHERE pp.' . $db->quoteName('order_id') . ' = ' . $orderIdColumn
                . ' AND pp.' . $db->quoteName('state') . ' = 1'
                . ' AND NOT EXISTS (SELECT 1 FROM ' . $poX . ' WHERE po_x.' . $db->quoteName('payment_proof_id') . ' = pp.' . $db->quoteName('id') . '))';

            return '(' . $junctionSum . ' + ' . $legacySum . ')';
        }

        return '(SELECT COALESCE(SUM(pp.' . $db->quoteName('payment_amount') . '), 0) FROM ' . $pp
            . ' WHERE pp.' . $db->quoteName('order_id') . ' = ' . $orderIdColumn
            . ' AND pp.' . $db->quoteName('state') . ' = 1)';
    }
}
