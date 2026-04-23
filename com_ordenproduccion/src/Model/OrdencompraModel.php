<?php
/**
 * Orden de compra (purchase order) — ORC numbering, lines from pre-cot P.Unit Proveedor.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Model
 * @since       3.113.47
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class OrdencompraModel extends BaseDatabaseModel
{
    public function hasSchema(): bool
    {
        $db     = $this->getDatabase();
        $prefix = $db->getPrefix();
        $name   = $prefix . 'ordenproduccion_orden_compra';

        try {
            $db->setQuery('SHOW TABLES LIKE ' . $db->quote($name));
            $db->execute();

            return (string) $db->loadResult() !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getNextNumber(): string
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('MAX(CAST(SUBSTRING(' . $db->quoteName('number') . ', 5) AS UNSIGNED))')
            ->from($db->quoteName('#__ordenproduccion_orden_compra'))
            ->where($db->quoteName('number') . ' LIKE ' . $db->quote('ORC-%'));

        $db->setQuery($query);
        $max  = (int) $db->loadResult();
        $next = $max + 1;

        return 'ORC-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Pending approval row for same pre-cot + vendor (blocks duplicate submit).
     */
    public function getPendingIdForPrecotProveedor(int $precotId, int $proveedorId): int
    {
        if (!$this->hasSchema() || $precotId < 1 || $proveedorId < 1) {
            return 0;
        }

        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ordenproduccion_orden_compra'))
            ->where($db->quoteName('precotizacion_id') . ' = ' . $precotId)
            ->where($db->quoteName('proveedor_id') . ' = ' . $proveedorId)
            ->where($db->quoteName('workflow_status') . ' = ' . $db->quote('pending_approval'))
            ->setLimit(1);
        $db->setQuery($q);

        return (int) $db->loadResult();
    }

    /**
     * How many órdenes de compra exist for this pre-cotización (any vendor, any workflow status).
     *
     * @since  3.113.48
     */
    public function countForPrecotizacion(int $precotId): int
    {
        if (!$this->hasSchema() || $precotId < 1) {
            return 0;
        }

        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_orden_compra'))
            ->where($db->quoteName('precotizacion_id') . ' = ' . $precotId);
        $db->setQuery($q);

        return (int) $db->loadResult();
    }

    /**
     * @return array<int, object>
     */
    public function getListItems(): array
    {
        if (!$this->hasSchema()) {
            return [];
        }

        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select('o.*, p.number AS precot_number')
            ->from($db->quoteName('#__ordenproduccion_orden_compra', 'o'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_pre_cotizacion', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('o.precotizacion_id')
            )
            ->order($db->quoteName('o.id') . ' DESC');

        $db->setQuery($q, 0, 500);

        return $db->loadObjectList() ?: [];
    }

    public function getItemById(int $id): ?object
    {
        if (!$this->hasSchema() || $id < 1) {
            return null;
        }

        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select('o.*, p.number AS precot_number')
            ->from($db->quoteName('#__ordenproduccion_orden_compra', 'o'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_pre_cotizacion', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('o.precotizacion_id')
            )
            ->where($db->quoteName('o.id') . ' = ' . $id)
            ->setLimit(1);
        $db->setQuery($q);
        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * @return array<int, object>
     */
    public function getLines(int $ordenCompraId): array
    {
        if (!$this->hasSchema() || $ordenCompraId < 1) {
            return [];
        }

        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_orden_compra_line'))
            ->where($db->quoteName('orden_compra_id') . ' = ' . $ordenCompraId)
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($q);

        return $db->loadObjectList() ?: [];
    }

    public function deleteOrden(int $id): bool
    {
        if (!$this->hasSchema() || $id < 1) {
            return false;
        }

        $row = $this->getItemById($id);
        if (!$row || (string) ($row->workflow_status ?? '') !== 'pending_approval') {
            return false;
        }

        $reqId  = (int) ($row->approval_request_id ?? 0);
        $userId = (int) Factory::getUser()->id;
        if ($reqId > 0 && $userId > 0) {
            $wf = new ApprovalWorkflowService($this->getDatabase());
            if (!$wf->cancelRequest($reqId, $userId, 'orden_compra_deleted')) {
                return false;
            }
        }

        $db = $this->getDatabase();

        try {
            $db->transactionStart();
            $delL = $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_orden_compra_line'))
                ->where($db->quoteName('orden_compra_id') . ' = ' . $id);
            $db->setQuery($delL);
            $db->execute();

            $delO = $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_orden_compra'))
                ->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($delO);
            $db->execute();
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            return false;
        }

        return true;
    }

    public function setApprovalRequestId(int $ordenCompraId, int $requestId): void
    {
        if (!$this->hasSchema() || $ordenCompraId < 1 || $requestId < 1) {
            return;
        }

        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_orden_compra'))
                ->set($db->quoteName('approval_request_id') . ' = ' . $requestId)
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getUser()->id)
                ->where($db->quoteName('id') . ' = ' . $ordenCompraId)
        );
        $db->execute();
    }

    public function updateWorkflowStatus(int $ordenCompraId, string $status): void
    {
        if (!$this->hasSchema() || $ordenCompraId < 1) {
            return;
        }

        $status = strtolower(trim($status));
        if (!in_array($status, ['pending_approval', 'approved', 'rejected'], true)) {
            return;
        }

        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_orden_compra'))
                ->set($db->quoteName('workflow_status') . ' = ' . $db->quote($status))
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getUser()->id)
                ->where($db->quoteName('id') . ' = ' . $ordenCompraId)
        );
        $db->execute();
    }

    /**
     * Proveedor ids that already have a pending-approval orden de compra for this pre-cotización.
     *
     * @return array<int, true>
     */
    public function getPendingProveedorIdsForPrecot(int $precotId): array
    {
        if (!$this->hasSchema() || $precotId < 1) {
            return [];
        }

        $db = $this->getDatabase();
        $q  = $db->getQuery(true)
            ->select($db->quoteName('proveedor_id'))
            ->from($db->quoteName('#__ordenproduccion_orden_compra'))
            ->where($db->quoteName('precotizacion_id') . ' = ' . $precotId)
            ->where($db->quoteName('workflow_status') . ' = ' . $db->quote('pending_approval'));
        $db->setQuery($q);
        $ids = $db->loadColumn() ?: [];
        $out = [];
        foreach ($ids as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $out[$pid] = true;
            }
        }

        return $out;
    }

    /**
     * Insert header + lines in one transaction. Returns new orden_compra id or 0.
     *
     * @param array<int, array{precotizacion_line_id:int, quantity:int, descripcion:string, vendor_unit_price:float, line_total:float}> $lines
     */
    public function createPendingOrdenCompra(
        int $precotizacionId,
        int $proveedorId,
        ?int $vendorQuoteEventId,
        string $condicionesEntrega,
        string $proveedorSnapshotJson,
        string $currency,
        array $lines,
        int $userId
    ): int {
        if (!$this->hasSchema() || $precotizacionId < 1 || $proveedorId < 1 || $userId < 1 || $lines === []) {
            return 0;
        }

        $currency = trim($currency) !== '' ? strtoupper(substr(trim($currency), 0, 8)) : 'Q';
        $number   = $this->getNextNumber();
        $now      = Factory::getDate()->toSql();
        $total    = 0.0;
        foreach ($lines as $L) {
            $total += (float) ($L['line_total'] ?? 0);
        }
        $total = round($total, 2);

        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            $evtVal = $vendorQuoteEventId !== null && $vendorQuoteEventId > 0
                ? (string) (int) $vendorQuoteEventId
                : 'NULL';

            $ins = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_orden_compra'))
                ->columns([
                    $db->quoteName('number'),
                    $db->quoteName('precotizacion_id'),
                    $db->quoteName('proveedor_id'),
                    $db->quoteName('vendor_quote_event_id'),
                    $db->quoteName('condiciones_entrega'),
                    $db->quoteName('proveedor_snapshot'),
                    $db->quoteName('currency'),
                    $db->quoteName('total_amount'),
                    $db->quoteName('workflow_status'),
                    $db->quoteName('approval_request_id'),
                    $db->quoteName('state'),
                    $db->quoteName('created'),
                    $db->quoteName('created_by'),
                    $db->quoteName('modified_by'),
                ])
                ->values(implode(',', [
                    $db->quote($number),
                    (string) (int) $precotizacionId,
                    (string) (int) $proveedorId,
                    $evtVal === 'NULL' ? 'NULL' : $evtVal,
                    $db->quote($condicionesEntrega),
                    $db->quote($proveedorSnapshotJson),
                    $db->quote($currency),
                    $db->quote(number_format($total, 2, '.', '')),
                    $db->quote('pending_approval'),
                    'NULL',
                    '1',
                    $db->quote($now),
                    (string) (int) $userId,
                    '0',
                ]));
            $db->setQuery($ins);
            $db->execute();
            $ocId = (int) $db->insertid();
            if ($ocId < 1) {
                throw new \RuntimeException('insert oc');
            }

            foreach ($lines as $L) {
                $lid = (int) ($L['precotizacion_line_id'] ?? 0);
                $qty = (int) ($L['quantity'] ?? 0);
                if ($lid < 1 || $qty < 1) {
                    throw new \RuntimeException('line');
                }
                $desc = (string) ($L['descripcion'] ?? '');
                $pup  = round((float) ($L['vendor_unit_price'] ?? 0), 2);
                $lt   = round((float) ($L['line_total'] ?? 0), 2);
                $insL = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_orden_compra_line'))
                    ->columns([
                        $db->quoteName('orden_compra_id'),
                        $db->quoteName('precotizacion_line_id'),
                        $db->quoteName('quantity'),
                        $db->quoteName('descripcion'),
                        $db->quoteName('vendor_unit_price'),
                        $db->quoteName('line_total'),
                    ])
                    ->values(implode(',', [
                        (string) $ocId,
                        (string) $lid,
                        (string) $qty,
                        $db->quote($desc),
                        $db->quote(number_format($pup, 2, '.', '')),
                        $db->quote(number_format($lt, 2, '.', '')),
                    ]));
                $db->setQuery($insL);
                $db->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            return 0;
        }

        return $ocId;
    }
}
