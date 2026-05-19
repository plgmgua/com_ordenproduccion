<?php
/**
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.103.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;

/**
 * Applies business-side effects when approval requests are decided.
 */
class ApprovalWorkflowEntityHelper
{
    /**
     * Stable positive int for approval_requests.entity_id (timesheet is cardno + week).
     */
    public static function timesheetEntityId(string $cardno, string $weekStartYmd): int
    {
        $raw = crc32($cardno . '|' . $weekStartYmd);
        $id  = $raw & 0x7FFFFFFF;

        return $id > 0 ? $id : 1;
    }

    /**
     * Manager/direct approve: same rules as TimesheetsController::approve (scoped to manager unless admin).
     */
    public static function applyTimesheetWeekApproved(
        DatabaseInterface $db,
        string $cardno,
        string $dateFrom,
        string $dateTo,
        int $approvedByUserId,
        bool $scopeToManager,
        int $managerUserId
    ): void {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_asistencia_summary', 's'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_employee_groups', 'g') . ' ON ' .
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('approved'))
            ->set($db->quoteName('s.approved_by') . ' = ' . (int) $approvedByUserId)
            ->set($db->quoteName('s.approved_date') . ' = NOW()')
            ->set($db->quoteName('s.approved_hours') . ' = COALESCE(' . $db->quoteName('s.approved_hours') . ', ' . $db->quoteName('s.total_hours') . ')')
            ->where($db->quoteName('e.cardno') . ' = ' . $db->quote($cardno))
            ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($dateFrom))
            ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($dateTo));

        if ($scopeToManager) {
            $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $managerUserId);
        }

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Reject back to pending (same as TimesheetsController::reject).
     */
    public static function applyTimesheetWeekRejected(
        DatabaseInterface $db,
        string $cardno,
        string $dateFrom,
        string $dateTo,
        bool $scopeToManager,
        int $managerUserId
    ): void {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_asistencia_summary', 's'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_employee_groups', 'g') . ' ON ' .
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('pending'))
            ->set($db->quoteName('s.approved_by') . ' = NULL')
            ->set($db->quoteName('s.approved_date') . ' = NULL')
            ->where($db->quoteName('e.cardno') . ' = ' . $db->quote($cardno))
            ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($dateFrom))
            ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($dateTo));

        if ($scopeToManager) {
            $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $managerUserId);
        }

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Full approval: admin approver — no manager group scope (metadata is authoritative).
     */
    public static function applyTimesheetWeekApprovedFromMetadata(
        DatabaseInterface $db,
        ?string $metadataJson,
        int $entityId,
        int $approvedByUserId
    ): void {
        $meta = [];
        if ($metadataJson !== null && $metadataJson !== '') {
            $decoded = json_decode($metadataJson, true);
            $meta  = is_array($decoded) ? $decoded : [];
        }

        $cardno = isset($meta['cardno']) ? (string) $meta['cardno'] : '';
        $from   = isset($meta['date_from']) ? (string) $meta['date_from'] : '';
        $to     = isset($meta['date_to']) ? (string) $meta['date_to'] : '';

        if ($cardno === '' || $from === '' || $to === '') {
            return;
        }

        $weekStart = isset($meta['week_start']) ? (string) $meta['week_start'] : $from;
        if (self::timesheetEntityId($cardno, $weekStart) !== $entityId) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_asistencia_summary', 's'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('approved'))
            ->set($db->quoteName('s.approved_by') . ' = ' . (int) $approvedByUserId)
            ->set($db->quoteName('s.approved_date') . ' = NOW()')
            ->set($db->quoteName('s.approved_hours') . ' = COALESCE(' . $db->quoteName('s.approved_hours') . ', ' . $db->quoteName('s.total_hours') . ')')
            ->where($db->quoteName('e.cardno') . ' = ' . $db->quote($cardno))
            ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($from))
            ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($to));

        $db->setQuery($query);
        $db->execute();
    }

    public static function applyTimesheetWeekRejectedFromMetadata(
        DatabaseInterface $db,
        ?string $metadataJson,
        int $entityId
    ): void {
        $meta = [];
        if ($metadataJson !== null && $metadataJson !== '') {
            $decoded = json_decode($metadataJson, true);
            $meta  = is_array($decoded) ? $decoded : [];
        }

        $cardno = isset($meta['cardno']) ? (string) $meta['cardno'] : '';
        $from   = isset($meta['date_from']) ? (string) $meta['date_from'] : '';
        $to     = isset($meta['date_to']) ? (string) $meta['date_to'] : '';

        if ($cardno === '' || $from === '' || $to === '') {
            return;
        }

        $weekStart = isset($meta['week_start']) ? (string) $meta['week_start'] : $from;
        if (self::timesheetEntityId($cardno, $weekStart) !== $entityId) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_asistencia_summary', 's'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('pending'))
            ->set($db->quoteName('s.approved_by') . ' = NULL')
            ->set($db->quoteName('s.approved_date') . ' = NULL')
            ->where($db->quoteName('e.cardno') . ' = ' . $db->quote($cardno))
            ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($from))
            ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($to));

        $db->setQuery($query);
        $db->execute();
    }

    public static function applyPaymentProofVerified(int $proofId, int $verifiedByUserId = 0): bool
    {
        $proofId = (int) $proofId;
        if ($proofId < 1) {
            return false;
        }

        $app = Factory::getApplication();
        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Paymentproof', 'Site', ['ignore_request' => true]);
        if (! $model || !method_exists($model, 'setVerificado')) {
            return false;
        }

        if (! $model->setVerificado($proofId)) {
            return false;
        }

        try {
            $adminModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
            if ($adminModel && method_exists($adminModel, 'refreshClientBalances')) {
                $adminModel->refreshClientBalances();
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }

        try {
            TelegramNotificationHelper::notifyPaymentProofVerified($proofId, (int) $verifiedByUserId);
        } catch (\Throwable $e) {
        }

        return true;
    }

    public static function applyCotizacionConfirmationApproved(
        DatabaseInterface $db,
        int $quotationId,
        int $approvedByUserId,
        ?string $metadataJson
    ): void {
        $quotationId = (int) $quotationId;
        $app         = Factory::getApplication();
        $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if ($precotModel && method_exists($precotModel, 'quotationHasAnyLinkedPreCotizacion') && ! $precotModel->quotationHasAnyLinkedPreCotizacion($quotationId)) {
            Log::add(
                'Cotización confirmation approval skipped: quotation ' . $quotationId . ' has no linked pre-cotización.',
                Log::WARNING,
                'com_ordenproduccion'
            );

            return;
        }

        $meta = [];
        if ($metadataJson !== null && $metadataJson !== '') {
            $decoded = json_decode($metadataJson, true);
            $meta  = is_array($decoded) ? $decoded : [];
        }

        $facturacionModo = isset($meta['facturacion_modo']) ? (string) $meta['facturacion_modo'] : 'con_envio';
        $facturacionFechaSql = isset($meta['facturacion_fecha_sql']) ? $meta['facturacion_fecha_sql'] : null;
        if ($facturacionFechaSql !== null) {
            $facturacionFechaSql = (string) $facturacionFechaSql;
        }

        $exactaMeta = isset($meta['facturar_cotizacion_exacta']) ? (int) $meta['facturar_cotizacion_exacta'] : 1;
        $exactaMeta = $exactaMeta === 0 ? 0 : 1;

        $submitterUserId = (int) ($meta['submitter_user_id'] ?? $approvedByUserId);

        $q = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_quotations'))
            ->set($db->quoteName('cotizacion_confirmada') . ' = 1')
            ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->set($db->quoteName('modified_by') . ' = ' . (int) $approvedByUserId)
            ->where($db->quoteName('id') . ' = ' . (int) $quotationId);
        $db->setQuery($q);
        $db->execute();

        if ($exactaMeta === 1 && $facturacionModo === 'fecha_especifica' && $facturacionFechaSql !== null && $facturacionFechaSql !== '') {
            $felSvc = new FelInvoiceIssuanceService();
            if ($felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn() && $felSvc->hasFelScheduledAtColumn()) {
                $felSvc->scheduleOrUpdateInvoiceFromQuotation($quotationId, $submitterUserId, $facturacionFechaSql);
            }
        }

        if ($exactaMeta === 0) {
            $forceManual       = !empty($meta['manual_fact_queue_force']);
            $nitVerifyFromMeta = !empty($meta['nit_verify_failed']);
            $cfGtqFromMeta     = !empty($meta['cf_gtq2499_manual_required']);
            self::queueCotizacionFacturacionManualApproval(
                $db,
                $quotationId,
                $submitterUserId > 0 ? $submitterUserId : $approvedByUserId,
                $forceManual,
                $nitVerifyFromMeta,
                $cfGtqFromMeta
            );
        }
    }

    /**
     * Start manual factura approval when líneas con Facturación activa requieren factura sin monto exacto.
     *
     * @param  bool  $forceDespiteNoFacturarLines  When true (e.g. cliente NIT no verificado contra Digifact, CF + monto sobre límite GTQ), queue even sin líneas marcadas para facturar.
     * @param  bool  $nitVerifyFailed               When true, metadata records that manual facturación was driven by NIT/Digifact fallo.
     * @param  bool  $cfGtqExclusiveLimitManual    When true, metadata records CF/C/F + total sobre límite (debe capturarse CUI en aprobación manual).
     *
     * @since  3.118.26
     */
    public static function queueCotizacionFacturacionManualApproval(
        DatabaseInterface $db,
        int $quotationId,
        int $submitterUserId,
        bool $forceDespiteNoFacturarLines = false,
        bool $nitVerifyFailed = false,
        bool $cfGtqExclusiveLimitManual = false
    ): void {
        $quotationId     = (int) $quotationId;
        $submitterUserId = (int) $submitterUserId;
        if ($quotationId < 1 || $submitterUserId < 1) {
            return;
        }

        $app    = Factory::getApplication();
        $precot = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (!$precot || !\is_callable([$precot, 'getFacturarPreCotizacionesForQuotation'])) {
            return;
        }
        if (!$forceDespiteNoFacturarLines && $precot->getFacturarPreCotizacionesForQuotation($quotationId) === []) {
            return;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . $quotationId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $row = $db->loadObject();
        if (!$row) {
            return;
        }

        $wfSvc = new ApprovalWorkflowService($db);
        if (!$wfSvc->hasSchema() || !$wfSvc->isWorkflowPublishedForEntity(ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL)) {
            return;
        }
        if ($wfSvc->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL, $quotationId) !== null) {
            return;
        }

        $metaArr = [
            'quotation_id'               => $quotationId,
            'facturacion_modo'           => (string) ($row->facturacion_modo ?? 'con_envio'),
            'facturacion_fecha'          => isset($row->facturacion_fecha) ? (string) $row->facturacion_fecha : '',
            'instrucciones_facturacion'  => isset($row->instrucciones_facturacion) ? (string) $row->instrucciones_facturacion : '',
            'facturar_cotizacion_exacta' => (int) ($row->facturar_cotizacion_exacta ?? 0),
            'submitter_user_id'          => $submitterUserId,
            'nit_verify_failed'          => $nitVerifyFailed,
            'cf_gtq2499_manual_required' => $cfGtqExclusiveLimitManual,
        ];
        $wfSvc->createRequest(
            ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL,
            $quotationId,
            $submitterUserId,
            json_encode($metaArr, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Auto-approve open facturación manual approval when sum(completed FEL) >= cotización total.
     *
     * @since  3.119.71
     */
    public static function tryCompleteFacturacionManualApprovalWhenFullyInvoiced(
        DatabaseInterface $db,
        int $quotationId,
        int $actorUserId
    ): bool {
        $quotationId     = (int) $quotationId;
        $actorUserId     = (int) $actorUserId;
        if ($quotationId < 1 || $actorUserId < 1) {
            return false;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('total_amount'))
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . $quotationId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $quotationTotal = round((float) $db->loadResult(), 2);

        $targetTotal = $quotationTotal;
        $app         = Factory::getApplication();
        $precot      = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (
            $precot
            && \is_callable([$precot, 'getFacturarPreCotizacionesForQuotation'])
            && \is_callable([$precot, 'getFacturarBillableTotalForQuotation'])
            && $precot->getFacturarPreCotizacionesForQuotation($quotationId) !== []
        ) {
            $targetTotal = round($precot->getFacturarBillableTotalForQuotation($quotationId), 2);
        }
        if ($targetTotal <= 0) {
            return false;
        }

        $felSvc   = new FelInvoiceIssuanceService($db);
        $invoiced = $felSvc->sumCompletedInvoiceAmountsForQuotation($quotationId);
        if (round($invoiced, 2) < $targetTotal) {
            return false;
        }

        $wfSvc = new ApprovalWorkflowService($db);

        return $wfSvc->completePendingCotizacionFacturacionManualForInvoicedTotal($quotationId, $actorUserId);
    }

    /**
     * Close open Fact.Man. approvals when completed invoices already cover the billable total.
     * Use when loading Administración → Aprobaciones or mod_ordop_pending_approvals.
     *
     * @return  int  Number of requests auto-closed
     *
     * @since   3.119.76
     */
    public static function sweepOpenFacturacionManualApprovalsWhenFullyInvoiced(
        DatabaseInterface $db,
        int $actorUserId
    ): int {
        $actorUserId = (int) $actorUserId;
        if ($actorUserId < 1) {
            return 0;
        }

        $wfSvc = new ApprovalWorkflowService($db);
        if (!$wfSvc->hasSchema()) {
            return 0;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('entity_id'))
                ->from($db->quoteName('#__ordenproduccion_approval_requests'))
                ->where(
                    $db->quoteName('entity_type') . ' = '
                    . $db->quote(ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL)
                )
                ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
        );
        $quotationIds = $db->loadColumn() ?: [];
        $closed       = 0;

        foreach ($quotationIds as $qid) {
            $qid = (int) $qid;
            if ($qid < 1) {
                continue;
            }
            if ($wfSvc->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL, $qid) === null) {
                continue;
            }
            if (!self::tryCompleteFacturacionManualApprovalWhenFullyInvoiced($db, $qid, $actorUserId)) {
                continue;
            }
            if ($wfSvc->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL, $qid) === null) {
                $closed++;
            }
        }

        return $closed;
    }

    /**
     * @since  3.113.47
     */
    public static function applyOrdenCompraWorkflowOutcome(DatabaseInterface $db, int $ordenCompraId, string $status): void
    {
        $ordenCompraId = (int) $ordenCompraId;
        $status        = strtolower(trim($status));
        if ($ordenCompraId < 1 || !in_array($status, ['approved', 'rejected'], true)) {
            return;
        }

        $wfStatus = $status === 'approved' ? 'approved' : 'rejected';
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_orden_compra'))
                ->set($db->quoteName('workflow_status') . ' = ' . $db->quote($wfStatus))
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getUser()->id)
                ->where($db->quoteName('id') . ' = ' . $ordenCompraId)
        );
        $db->execute();

        if ($status === 'approved') {
            try {
                OrdencompraApprovedPdfBuilder::buildAndStore($ordenCompraId);
            } catch (\Throwable $e) {
                Log::add(
                    'Orden compra approved PDF: ' . $e->getMessage(),
                    Log::WARNING,
                    'com_ordenproduccion'
                );
            }

            try {
                OrdencompraApprovedMailHelper::sendApprovedNotification($ordenCompraId);
            } catch (\Throwable $e) {
                Log::add(
                    'Orden compra approved email: ' . $e->getMessage(),
                    Log::WARNING,
                    'com_ordenproduccion'
                );
            }
        }
    }
}
