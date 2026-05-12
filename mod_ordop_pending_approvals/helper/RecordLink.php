<?php
/**
 * Resolve frontend URL for an approval workflow row (entity_type + entity_id + metadata).
 *
 * @package     Joomla.Site
 * @subpackage  mod_ordop_pending_approvals
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Module\OrdopPendingApprovals\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\Database\DatabaseInterface;

/**
 * @since  1.1.0
 */
final class RecordLink
{
    /** @var bool|null */
    private static $hasPaymentOrdersTable;

    /** @var array<int,int> pre_cotizacion_line.id → pre_cotizacion_id */
    private static array $servExtLinePreCotCache = [];

    /**
     * Resolve parent pre-cotización PK for servicios_elementos_externos (metadata or line table).
     *
     * @since  1.2.12
     */
    public static function resolvePreCotizacionIdForServiciosExternosRow(DatabaseInterface $db, object $row): int
    {
        $meta = self::decodeMetadata($row);
        $preId = isset($meta['pre_cotizacion_id']) ? (int) $meta['pre_cotizacion_id'] : 0;
        if ($preId > 0) {
            return $preId;
        }
        $lineId = (int) ($row->entity_id ?? 0);
        if ($lineId < 1) {
            return 0;
        }
        if (isset(self::$servExtLinePreCotCache[$lineId])) {
            return (int) self::$servExtLinePreCotCache[$lineId];
        }
        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('pre_cotizacion_id'))
                    ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                    ->where($db->quoteName('id') . ' = ' . $lineId)
                    ->setLimit(1)
            );
            $preId = (int) $db->loadResult();
            self::$servExtLinePreCotCache[$lineId] = $preId;

            return $preId;
        } catch (\Throwable $e) {
            self::$servExtLinePreCotCache[$lineId] = 0;

            return 0;
        }
    }

    /**
     * Relative internal URL (for Route::_) or null when no safe target exists.
     */
    public static function relativeUrl(DatabaseInterface $db, object $row): ?string
    {
        $typeNorm = ApprovalWorkflowService::normalizeEntityType((string) ($row->entity_type ?? ''));
        $eid      = (int) ($row->entity_id ?? 0);

        if ($typeNorm === '' || $eid < 1) {
            return null;
        }

        switch ($typeNorm) {
            case ApprovalWorkflowService::ENTITY_COTIZACION_CONFIRMATION:
            case ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL:
                return 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $eid;

            case ApprovalWorkflowService::ENTITY_PAYMENT_PROOF:
                $orderId = self::firstOrderIdForPaymentProof($db, $eid);

                return $orderId > 0
                    ? 'index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId . '&proof_id=' . $eid
                    : null;

            case ApprovalWorkflowService::ENTITY_TIMESHEET:
                $meta = self::decodeMetadata($row);
                $week = isset($meta['week_start']) ? (string) $meta['week_start'] : '';
                if ($week === '' && isset($meta['date_from'])) {
                    $week = (string) $meta['date_from'];
                }
                $week = $week !== '' ? substr($week, 0, 10) : '';

                return $week !== ''
                    ? 'index.php?option=com_ordenproduccion&view=timesheets&week_start=' . $week
                    : 'index.php?option=com_ordenproduccion&view=timesheets';

            case ApprovalWorkflowService::ENTITY_ORDEN_STATUS:
                return 'index.php?option=com_ordenproduccion&view=orden&id=' . $eid;

            case ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO:
            case ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION:
            case ApprovalWorkflowService::ENTITY_CREACION_ORDEN_TRABAJO:
                return 'index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $eid;

            case ApprovalWorkflowService::ENTITY_ORDEN_COMPRA:
                return 'index.php?option=com_ordenproduccion&view=ordencompra&id=' . $eid;

            case ApprovalWorkflowService::ENTITY_SERVICIOS_ELEMENTOS_EXTERNOS:
                $preId = self::resolvePreCotizacionIdForServiciosExternosRow($db, $row);

                return $preId > 0
                    ? 'index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preId
                    : null;

            default:
                return null;
        }
    }

    /**
     * @return  array<string, mixed>
     */
    private static function decodeMetadata(object $row): array
    {
        $raw = isset($row->metadata) ? (string) $row->metadata : '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function firstOrderIdForPaymentProof(DatabaseInterface $db, int $proofId): int
    {
        if ($proofId < 1) {
            return 0;
        }

        try {
            if (self::$hasPaymentOrdersTable === null) {
                $tables = $db->getTableList();
                $want   = $db->getPrefix() . 'ordenproduccion_payment_orders';
                self::$hasPaymentOrdersTable = false;
                foreach ($tables as $t) {
                    if (strcasecmp((string) $t, $want) === 0) {
                        self::$hasPaymentOrdersTable = true;
                        break;
                    }
                }
            }
            if (!self::$hasPaymentOrdersTable) {
                return 0;
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('order_id'))
                    ->from($db->quoteName('#__ordenproduccion_payment_orders'))
                    ->where($db->quoteName('payment_proof_id') . ' = ' . $proofId)
                    ->order($db->quoteName('order_id') . ' ASC')
                    ->setLimit(1)
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
