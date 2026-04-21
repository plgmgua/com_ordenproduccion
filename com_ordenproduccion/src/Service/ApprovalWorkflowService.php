<?php
/**
 * Internal approval workflow engine (Option B).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Service
 * @since       3.102.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\ApprovalAuditHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\ApprovalEmailQueueHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\ApprovalWorkflowEntityHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/**
 * Creates and advances approval requests; entity side-effects are applied in later hooks.
 */
class ApprovalWorkflowService
{
    public const ENTITY_COTIZACION_CONFIRMATION = 'cotizacion_confirmation';

    public const ENTITY_ORDEN_STATUS = 'orden_status';

    public const ENTITY_TIMESHEET = 'timesheet';

    public const ENTITY_PAYMENT_PROOF = 'payment_proof';

    /** Pre-cotización: solicitud de descuento (entity_id = pre_cotización id). */
    public const ENTITY_SOLICITUD_DESCUENTO = 'solicitud_descuento';

    /** @var DatabaseInterface */
    protected $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Whether approval tables exist (migration applied).
     */
    public function hasSchema(): bool
    {
        $prefix = $this->db->getPrefix();
        $name   = $prefix . 'ordenproduccion_approval_requests';

        try {
            $this->db->setQuery('SHOW TABLES LIKE ' . $this->db->quote($name));
            $this->db->execute();

            return (string) $this->db->loadResult() !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Open pending request for an entity, if any.
     */
    public function getOpenPendingRequest(string $entityType, int $entityId): ?object
    {
        if (!$this->hasSchema()) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_requests'))
            ->where($this->db->quoteName('entity_type') . ' = ' . $this->db->quote($entityType))
            ->where($this->db->quoteName('entity_id') . ' = ' . (int) $entityId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'))
            ->setLimit(1);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Pending approval rows for a user (for UI).
     *
     * @return  array<int, object>
     */
    public function getMyPendingApprovalRows(int $userId): array
    {
        if ($userId < 1 || !$this->hasSchema()) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select('r.*, s.`id` AS step_row_id, s.`step_number` AS step_row_step')
            ->from($this->db->quoteName('#__ordenproduccion_approval_request_steps', 's'))
            ->innerJoin(
                $this->db->quoteName('#__ordenproduccion_approval_requests', 'r') . ' ON '
                . $this->db->quoteName('r.id') . ' = ' . $this->db->quoteName('s.request_id')
            )
            ->where($this->db->quoteName('s.approver_user_id') . ' = ' . (int) $userId)
            ->where($this->db->quoteName('s.status') . ' = ' . $this->db->quote('pending'))
            ->where($this->db->quoteName('r.status') . ' = ' . $this->db->quote('pending'))
            ->where($this->db->quoteName('s.step_number') . ' = ' . $this->db->quoteName('r.current_step_number'))
            ->order($this->db->quoteName('r.created') . ' DESC');

        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Start a new approval request for an entity.
     *
     * @param   string|null  $metadataJson  Optional JSON (e.g. orden status transition)
     *
     * @return  int  New request id, or 0 on failure
     */
    public function createRequest(string $entityType, int $entityId, int $submitterId, ?string $metadataJson = null): int
    {
        if (!$this->hasSchema() || $entityId < 1 || $submitterId < 1) {
            return 0;
        }

        if ($this->getOpenPendingRequest($entityType, $entityId) !== null) {
            return 0;
        }

        $wf = $this->getPublishedWorkflowByEntityType($entityType);

        if ($wf === null) {
            return 0;
        }

        $step = $this->getWorkflowStep((int) $wf->id, 1);

        if ($step === null) {
            return 0;
        }

        $approverIds = $this->resolveApproverUserIds($step);

        if ($approverIds === []) {
            return 0;
        }

        $this->db->transactionStart();

        try {
            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__ordenproduccion_approval_requests'))
                ->columns([
                    $this->db->quoteName('entity_type'),
                    $this->db->quoteName('entity_id'),
                    $this->db->quoteName('workflow_id'),
                    $this->db->quoteName('status'),
                    $this->db->quoteName('submitter_id'),
                    $this->db->quoteName('current_step_number'),
                    $this->db->quoteName('metadata'),
                    $this->db->quoteName('created'),
                ])
                ->values(implode(',', [
                    $this->db->quote($entityType),
                    (string) (int) $entityId,
                    (string) (int) $wf->id,
                    $this->db->quote('pending'),
                    (string) (int) $submitterId,
                    '1',
                    $metadataJson === null ? 'NULL' : $this->db->quote($metadataJson),
                    $this->db->quote(Factory::getDate()->toSql()),
                ]));

            $this->db->setQuery($query);
            $this->db->execute();
            $requestId = (int) $this->db->insertid();

            foreach ($approverIds as $aid) {
                $aid = (int) $aid;
                if ($aid < 1) {
                    continue;
                }
                $q2 = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                    ->columns([
                        $this->db->quoteName('request_id'),
                        $this->db->quoteName('step_number'),
                        $this->db->quoteName('approver_user_id'),
                        $this->db->quoteName('status'),
                        $this->db->quoteName('created'),
                    ])
                    ->values(implode(',', [
                        (string) $requestId,
                        '1',
                        (string) $aid,
                        $this->db->quote('pending'),
                        $this->db->quote(Factory::getDate()->toSql()),
                    ]));
                $this->db->setQuery($q2);
                $this->db->execute();
            }

            ApprovalAuditHelper::log($requestId, 'created', $submitterId, null, 'pending', '');

            foreach ($approverIds as $aid) {
                $aid = (int) $aid;
                if ($aid < 1) {
                    continue;
                }
                $user = Factory::getUser($aid);
                if ($user->guest) {
                    continue;
                }
                ApprovalEmailQueueHelper::notifyAssign($requestId, $user, $entityId, $entityType);
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return 0;
        }

        return $requestId;
    }

    /**
     * Whether a published workflow exists for the entity type.
     *
     * @since   3.109.59
     */
    public function isWorkflowPublishedForEntity(string $entityType): bool
    {
        return $this->getPublishedWorkflowByEntityType($entityType) !== null;
    }

    /**
     * When Aprobaciones Ventas saves pliego breakdown subtotals, mark any open solicitud de descuento as approved (no binary approve in UI).
     *
     * @return  bool  True if a pending request was closed
     *
     * @since   3.109.59
     */
    public function completePendingSolicitudDescuentoIfAny(int $preCotizacionId, int $actorUserId): bool
    {
        if (!$this->hasSchema() || $preCotizacionId < 1 || $actorUserId < 1) {
            return false;
        }

        $req = $this->getOpenPendingRequest(self::ENTITY_SOLICITUD_DESCUENTO, $preCotizacionId);

        if ($req === null) {
            return false;
        }

        $requestId = (int) $req->id;
        $now       = Factory::getDate()->toSql();

        $this->db->transactionStart();

        try {
            $q = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('approved'))
                ->set($this->db->quoteName('comments') . ' = ' . $this->db->quote('subtotals_saved'))
                ->set($this->db->quoteName('decided_date') . ' = ' . $this->db->quote($now))
                ->where($this->db->quoteName('request_id') . ' = ' . $requestId)
                ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'));
            $this->db->setQuery($q);
            $this->db->execute();

            $q2 = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_requests'))
                ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('approved'))
                ->set($this->db->quoteName('completed') . ' = ' . $this->db->quote($now))
                ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
                ->where($this->db->quoteName('id') . ' = ' . $requestId);
            $this->db->setQuery($q2);
            $this->db->execute();

            ApprovalAuditHelper::log($requestId, 'discount_subtotals_saved', $actorUserId, 'pending', 'approved', '');
            $reqFresh = $this->loadRequest($requestId);

            if ($reqFresh !== null) {
                $this->onRequestFullyApproved($reqFresh, $actorUserId);
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return false;
        }

        return true;
    }

    /**
     * Approve a pending step row for the current user.
     */
    public function approve(int $requestId, int $userId, string $comments = ''): bool
    {
        return $this->decide($requestId, $userId, 'approved', $comments);
    }

    /**
     * Reject the whole request.
     */
    public function reject(int $requestId, int $userId, string $comments = ''): bool
    {
        return $this->decide($requestId, $userId, 'rejected', $comments);
    }

    /**
     * Cancel request (e.g. entity deleted).
     */
    public function cancelRequest(int $requestId, int $actorId, string $reason = ''): bool
    {
        if (!$this->hasSchema() || $requestId < 1) {
            return false;
        }

        $req = $this->loadRequest($requestId);

        if ($req === null || $req->status !== 'pending') {
            return false;
        }

        $old = $req->status;

        $this->db->transactionStart();

        try {
            $q = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_requests'))
                ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('cancelled'))
                ->set($this->db->quoteName('completed') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                ->where($this->db->quoteName('id') . ' = ' . (int) $requestId);
            $this->db->setQuery($q);
            $this->db->execute();

            $q2 = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('skipped'))
                ->set($this->db->quoteName('decided_date') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                ->where($this->db->quoteName('request_id') . ' = ' . (int) $requestId)
                ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'));
            $this->db->setQuery($q2);
            $this->db->execute();

            ApprovalAuditHelper::log($requestId, 'cancelled', $actorId, $old, 'cancelled', $reason);
            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return false;
        }

        return true;
    }

    /**
     * Apply entity-side updates when approved.
     *
     * @param   int  $actorUserId  User who completed the final approval step
     */
    protected function onRequestFullyApproved(object $request, int $actorUserId): void
    {
        $type = (string) ($request->entity_type ?? '');
        $eid  = (int) ($request->entity_id ?? 0);
        $meta = isset($request->metadata) ? (string) $request->metadata : null;

        if ($eid < 1 || $actorUserId < 1) {
            return;
        }

        switch ($type) {
            case self::ENTITY_TIMESHEET:
                ApprovalWorkflowEntityHelper::applyTimesheetWeekApprovedFromMetadata(
                    $this->db,
                    $meta,
                    $eid,
                    $actorUserId
                );
                break;

            case self::ENTITY_PAYMENT_PROOF:
                ApprovalWorkflowEntityHelper::applyPaymentProofVerified($eid, $actorUserId);
                break;

            case self::ENTITY_COTIZACION_CONFIRMATION:
                ApprovalWorkflowEntityHelper::applyCotizacionConfirmationApproved(
                    $this->db,
                    $eid,
                    $actorUserId,
                    $meta
                );
                break;

            default:
                break;
        }
    }

    /**
     * Apply entity-side updates when rejected.
     *
     * @param   int  $actorUserId  User who rejected
     */
    protected function onRequestRejected(object $request, int $actorUserId): void
    {
        $type = (string) ($request->entity_type ?? '');
        $eid  = (int) ($request->entity_id ?? 0);
        $meta = isset($request->metadata) ? (string) $request->metadata : null;

        if ($eid < 1) {
            return;
        }

        switch ($type) {
            case self::ENTITY_TIMESHEET:
                ApprovalWorkflowEntityHelper::applyTimesheetWeekRejectedFromMetadata($this->db, $meta, $eid);
                break;

            default:
                break;
        }
    }

    /**
     * @param   string  $decision  approved|rejected
     */
    protected function decide(int $requestId, int $userId, string $decision, string $comments): bool
    {
        if (!$this->hasSchema() || $requestId < 1 || $userId < 1) {
            return false;
        }

        $req = $this->loadRequest($requestId);

        if ($req === null || $req->status !== 'pending') {
            return false;
        }

        $stepNum = (int) $req->current_step_number;

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
            ->where($this->db->quoteName('request_id') . ' = ' . (int) $requestId)
            ->where($this->db->quoteName('step_number') . ' = ' . $stepNum)
            ->where($this->db->quoteName('approver_user_id') . ' = ' . (int) $userId)
            ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'))
            ->setLimit(1);
        $this->db->setQuery($q);
        $myRow = $this->db->loadObject();

        if ($myRow === null) {
            return false;
        }

        $wfStep = $this->getWorkflowStep((int) $req->workflow_id, $stepNum);

        if ($wfStep === null) {
            return false;
        }

        $requireAll = (int) $wfStep->require_all === 1;

        $this->db->transactionStart();

        try {
            if ($decision === 'rejected') {
                $q2 = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                    ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('rejected'))
                    ->set($this->db->quoteName('comments') . ' = ' . $this->db->quote($comments))
                    ->set($this->db->quoteName('decided_date') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->where($this->db->quoteName('id') . ' = ' . (int) $myRow->id);
                $this->db->setQuery($q2);
                $this->db->execute();

                $q3 = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                    ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('skipped'))
                    ->set($this->db->quoteName('decided_date') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->where($this->db->quoteName('request_id') . ' = ' . (int) $requestId)
                    ->where($this->db->quoteName('step_number') . ' = ' . $stepNum)
                    ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'));
                $this->db->setQuery($q3);
                $this->db->execute();

                $q4 = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_approval_requests'))
                    ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('rejected'))
                    ->set($this->db->quoteName('completed') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->where($this->db->quoteName('id') . ' = ' . (int) $requestId);
                $this->db->setQuery($q4);
                $this->db->execute();

                ApprovalAuditHelper::log($requestId, 'rejected', $userId, 'pending', 'rejected', $comments);
                $this->onRequestRejected($req, $userId);
                $this->db->transactionCommit();
                ApprovalEmailQueueHelper::notifySubmitterOutcome($requestId, 'rejected', $userId, $comments);

                return true;
            }

            // approved
            $q5 = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('approved'))
                ->set($this->db->quoteName('comments') . ' = ' . $this->db->quote($comments))
                ->set($this->db->quoteName('decided_date') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                ->where($this->db->quoteName('id') . ' = ' . (int) $myRow->id);
            $this->db->setQuery($q5);
            $this->db->execute();

            if (!$requireAll) {
                $q6 = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                    ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('skipped'))
                    ->set($this->db->quoteName('decided_date') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->where($this->db->quoteName('request_id') . ' = ' . (int) $requestId)
                    ->where($this->db->quoteName('step_number') . ' = ' . $stepNum)
                    ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('pending'));
                $this->db->setQuery($q6);
                $this->db->execute();
            }

            $stepComplete = $this->isCurrentStepComplete((int) $requestId, $stepNum, $requireAll);

            if (!$stepComplete) {
                ApprovalAuditHelper::log($requestId, 'approved_partial', $userId, 'pending', 'pending', $comments);
                $this->db->transactionCommit();

                return true;
            }

            // Step complete — advance or finish
            $maxStep = $this->getMaxStepNumberForWorkflow((int) $req->workflow_id);

            if ($stepNum >= $maxStep) {
                $q7 = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_approval_requests'))
                    ->set($this->db->quoteName('status') . ' = ' . $this->db->quote('approved'))
                    ->set($this->db->quoteName('completed') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                    ->where($this->db->quoteName('id') . ' = ' . (int) $requestId);
                $this->db->setQuery($q7);
                $this->db->execute();

                ApprovalAuditHelper::log($requestId, 'approved_final', $userId, 'pending', 'approved', $comments);
                $reqFresh = $this->loadRequest($requestId);
                if ($reqFresh !== null) {
                    $this->onRequestFullyApproved($reqFresh, $userId);
                }
                $this->db->transactionCommit();
                ApprovalEmailQueueHelper::notifySubmitterOutcome($requestId, 'approved', $userId, $comments);

                return true;
            }

            $next = $stepNum + 1;
            $q8 = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_requests'))
                ->set($this->db->quoteName('current_step_number') . ' = ' . (int) $next)
                ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                ->where($this->db->quoteName('id') . ' = ' . (int) $requestId);
            $this->db->setQuery($q8);
            $this->db->execute();

            $nextStep = $this->getWorkflowStep((int) $req->workflow_id, $next);

            if ($nextStep === null) {
                $this->db->transactionRollback();

                return false;
            }

            $nextApprovers = $this->resolveApproverUserIds($nextStep);

            foreach ($nextApprovers as $aid) {
                $aid = (int) $aid;
                if ($aid < 1) {
                    continue;
                }
                $q9 = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
                    ->columns([
                        $this->db->quoteName('request_id'),
                        $this->db->quoteName('step_number'),
                        $this->db->quoteName('approver_user_id'),
                        $this->db->quoteName('status'),
                        $this->db->quoteName('created'),
                    ])
                    ->values(implode(',', [
                        (string) $requestId,
                        (string) $next,
                        (string) $aid,
                        $this->db->quote('pending'),
                        $this->db->quote(Factory::getDate()->toSql()),
                    ]));
                $this->db->setQuery($q9);
                $this->db->execute();
            }

            foreach ($nextApprovers as $aid) {
                $aid = (int) $aid;
                if ($aid < 1) {
                    continue;
                }
                $user = Factory::getUser($aid);
                if ($user->guest) {
                    continue;
                }
                ApprovalEmailQueueHelper::notifyAssign(
                    $requestId,
                    $user,
                    (int) $req->entity_id,
                    (string) $req->entity_type
                );
            }

            ApprovalAuditHelper::log($requestId, 'step_advanced', $userId, 'pending', 'pending', (string) $stepNum . '->' . (string) $next);
            $this->db->transactionCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return false;
        }
    }

    protected function isCurrentStepComplete(int $requestId, int $stepNum, bool $requireAll): bool
    {
        $q = $this->db->getQuery(true)
            ->select($this->db->quoteName('status'))
            ->from($this->db->quoteName('#__ordenproduccion_approval_request_steps'))
            ->where($this->db->quoteName('request_id') . ' = ' . $requestId)
            ->where($this->db->quoteName('step_number') . ' = ' . $stepNum);
        $this->db->setQuery($q);
        $statuses = $this->db->loadColumn() ?: [];

        if ($statuses === []) {
            return false;
        }

        if ($requireAll) {
            foreach ($statuses as $st) {
                if ($st !== 'approved') {
                    return false;
                }
            }

            return true;
        }

        foreach ($statuses as $st) {
            if ($st === 'approved') {
                return true;
            }
        }

        return false;
    }

    protected function getMaxStepNumberForWorkflow(int $workflowId): int
    {
        $q = $this->db->getQuery(true)
            ->select('MAX(' . $this->db->quoteName('step_number') . ')')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
            ->where($this->db->quoteName('workflow_id') . ' = ' . $workflowId);
        $this->db->setQuery($q);
        $m = (int) $this->db->loadResult();

        return $m > 0 ? $m : 1;
    }

    protected function loadRequest(int $id): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_requests'))
            ->where($this->db->quoteName('id') . ' = ' . $id)
            ->setLimit(1);
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    protected function getPublishedWorkflowByEntityType(string $entityType): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflows'))
            ->where($this->db->quoteName('entity_type') . ' = ' . $this->db->quote($entityType))
            ->where($this->db->quoteName('published') . ' = 1')
            ->order($this->db->quoteName('id') . ' ASC')
            ->setLimit(1);
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    protected function getWorkflowStep(int $workflowId, int $stepNumber): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
            ->where($this->db->quoteName('workflow_id') . ' = ' . $workflowId)
            ->where($this->db->quoteName('step_number') . ' = ' . $stepNumber)
            ->setLimit(1);
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * @return  int[]
     */
    protected function resolveApproverUserIds(object $step): array
    {
        $type  = strtolower(trim((string) ($step->approver_type ?? '')));
        $value = trim((string) ($step->approver_value ?? ''));

        if ($value === '') {
            return [];
        }

        if ($type === 'user') {
            $uids = array_unique(array_filter(array_map('intval', explode(',', $value)), static function ($id) {
                return $id > 0;
            }));

            return array_values($uids);
        }

        if ($type === 'joomla_group') {
            $ids = array_filter(array_map('intval', explode(',', $value)));

            return $this->getUserIdsInJoomlaGroups($ids);
        }

        if ($type === 'named_group') {
            $titles = array_map('trim', explode(',', $value));
            $titles = array_filter($titles);

            return $this->getUserIdsInNamedGroups($titles);
        }

        if ($type === 'approval_group') {
            $ids = array_filter(array_map('intval', explode(',', $value)));

            return $this->getUserIdsInComponentApprovalGroups($ids);
        }

        return [];
    }

    /**
     * @param   int[]  $groupIds
     *
     * @return  int[]
     */
    protected function getUserIdsInJoomlaGroups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $groupIds = array_unique(array_map('intval', $groupIds));
        $in       = implode(',', $groupIds);

        $q = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('user_id'))
            ->from($this->db->quoteName('#__user_usergroup_map'))
            ->where($this->db->quoteName('group_id') . ' IN (' . $in . ')');
        $this->db->setQuery($q);
        $uids = $this->db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $uids)));
    }

    /**
     * @param   string[]  $titles  Exact Joomla user group titles
     *
     * @return  int[]
     */
    protected function getUserIdsInNamedGroups(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $groupIds = [];
        foreach ($titles as $t) {
            $q = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__usergroups'))
                ->where($this->db->quoteName('title') . ' = ' . $this->db->quote($t))
                ->setLimit(1);
            $this->db->setQuery($q);
            $gid = (int) $this->db->loadResult();
            if ($gid > 0) {
                $groupIds[] = $gid;
            }
        }

        return $this->getUserIdsInJoomlaGroups($groupIds);
    }

    /**
     * Active Joomla users for workflow step picker (block = 0, id &gt; 0).
     *
     * @return  array<int, object>  Objects: id, name, username
     *
     * @since   3.109.65
     */
    public function listJoomlaUsersForApprovalPicker(): array
    {
        $q = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('name'),
                $this->db->quoteName('username'),
            ])
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('block') . ' = 0')
            ->where($this->db->quoteName('id') . ' > 0')
            ->order($this->db->quoteName('name') . ' ASC, ' . $this->db->quoteName('username') . ' ASC');
        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Whether component approval-group tables exist (3.109.64+).
     *
     * @since   3.109.64
     */
    public function hasApprovalGroupsSchema(): bool
    {
        $prefix = $this->db->getPrefix();
        $name   = $prefix . 'ordenproduccion_approval_groups';

        try {
            $this->db->setQuery('SHOW TABLES LIKE ' . $this->db->quote($name));
            $this->db->execute();

            return (string) $this->db->loadResult() !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * User IDs assigned to component approval groups (union of all given group ids).
     *
     * @param   int[]  $groupIds
     *
     * @return  int[]
     *
     * @since   3.109.64
     */
    protected function getUserIdsInComponentApprovalGroups(array $groupIds): array
    {
        if ($groupIds === [] || !$this->hasApprovalGroupsSchema()) {
            return [];
        }

        $groupIds = array_unique(array_values(array_filter(array_map('intval', $groupIds), static function ($v) {
            return $v > 0;
        })));

        if ($groupIds === []) {
            return [];
        }

        $in = implode(',', $groupIds);

        $q = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('user_id'))
            ->from($this->db->quoteName('#__ordenproduccion_approval_group_users'))
            ->where($this->db->quoteName('group_id') . ' IN (' . $in . ')');
        $this->db->setQuery($q);
        $uids = $this->db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $uids)));
    }

    /**
     * All workflows with ordered steps (Control de Ventas → Ajustes → Flujos de aprobaciones).
     *
     * @return  array<int, array{workflow: object, steps: object[]}>
     *
     * @since   3.109.58
     */
    public function getAllWorkflowsWithStepsForAdmin(): array
    {
        if (!$this->hasSchema()) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflows'))
            ->order($this->db->quoteName('entity_type') . ' ASC');
        $this->db->setQuery($q);
        $wfs = $this->db->loadObjectList() ?: [];
        $out = [];

        foreach ($wfs as $wf) {
            $wid = (int) $wf->id;
            $q2  = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                ->where($this->db->quoteName('workflow_id') . ' = ' . $wid)
                ->order($this->db->quoteName('step_number') . ' ASC');
            $this->db->setQuery($q2);
            $steps = $this->db->loadObjectList() ?: [];
            $out[] = ['workflow' => $wf, 'steps' => $steps];
        }

        return $out;
    }

    /**
     * Workflow list rows with step counts (Ajustes → Flujos list).
     *
     * @return  array<int, object>  Rows: workflow fields + steps_count
     *
     * @since   3.109.64
     */
    public function getWorkflowsListSummaryForAdmin(): array
    {
        if (!$this->hasSchema()) {
            return [];
        }

        $sub = '(SELECT COUNT(*) FROM ' . $this->db->quoteName('#__ordenproduccion_approval_workflow_steps', 's')
            . ' WHERE ' . $this->db->quoteName('s.workflow_id') . ' = ' . $this->db->quoteName('w.id') . ')';

        $q = $this->db->getQuery(true)
            ->select(['w.*', $sub . ' AS ' . $this->db->quoteName('steps_count')])
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflows', 'w'))
            ->order($this->db->quoteName('w.entity_type') . ' ASC');
        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * One workflow and its steps for the edit screen.
     *
     * @return  array{workflow: object, steps: object[]}|null
     *
     * @since   3.109.64
     */
    public function getWorkflowBundleForAdmin(int $workflowId): ?array
    {
        if (!$this->hasSchema() || $workflowId < 1) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflows'))
            ->where($this->db->quoteName('id') . ' = ' . $workflowId)
            ->setLimit(1);
        $this->db->setQuery($q);
        $wf = $this->db->loadObject();

        if (!$wf) {
            return null;
        }

        $q2 = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
            ->where($this->db->quoteName('workflow_id') . ' = ' . $workflowId)
            ->order($this->db->quoteName('step_number') . ' ASC');
        $this->db->setQuery($q2);
        $steps = $this->db->loadObjectList() ?: [];

        return ['workflow' => $wf, 'steps' => $steps];
    }

    /**
     * Append a new step at the end of a workflow (placeholder approver: current user id or 1).
     *
     * @return  int  New step row id, or 0 on failure
     *
     * @since   3.109.64
     */
    public function adminAddWorkflowStep(int $workflowId): int
    {
        if (!$this->hasSchema() || $workflowId < 1) {
            return 0;
        }

        $q0 = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflows'))
            ->where($this->db->quoteName('id') . ' = ' . $workflowId);
        $this->db->setQuery($q0);
        if ((int) $this->db->loadResult() < 1) {
            return 0;
        }

        $next = $this->getMaxStepNumberForWorkflow($workflowId) + 1;
        $user = Factory::getUser();
        $uid  = (int) $user->id;
        $ph   = $uid > 0 ? $uid : 1;
        $now  = Factory::getDate()->toSql();
        $by   = $uid;

        try {
            $ins = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                ->columns([
                    $this->db->quoteName('workflow_id'),
                    $this->db->quoteName('step_number'),
                    $this->db->quoteName('step_name'),
                    $this->db->quoteName('approver_type'),
                    $this->db->quoteName('approver_value'),
                    $this->db->quoteName('require_all'),
                    $this->db->quoteName('timeout_hours'),
                    $this->db->quoteName('timeout_action'),
                    $this->db->quoteName('created'),
                    $this->db->quoteName('created_by'),
                ])
                ->values(implode(',', [
                    (string) $workflowId,
                    (string) $next,
                    $this->db->quote('Paso ' . (string) $next),
                    $this->db->quote('user'),
                    $this->db->quote((string) $ph),
                    '0',
                    '0',
                    $this->db->quote('escalate'),
                    $this->db->quote($now),
                    (string) $by,
                ]));
            $this->db->setQuery($ins);
            $this->db->execute();

            return (int) $this->db->insertid();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Delete a step if the workflow still has another step; renumbers step_number.
     *
     * @since   3.109.64
     */
    public function adminDeleteWorkflowStep(int $stepId): bool
    {
        if (!$this->hasSchema() || $stepId < 1) {
            return false;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
            ->where($this->db->quoteName('id') . ' = ' . $stepId)
            ->setLimit(1);
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        if (!$row) {
            return false;
        }

        $wid = (int) $row->workflow_id;

        $q2 = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
            ->where($this->db->quoteName('workflow_id') . ' = ' . $wid);
        $this->db->setQuery($q2);
        if ((int) $this->db->loadResult() <= 1) {
            return false;
        }

        try {
            $this->db->transactionStart();
            $del = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                ->where($this->db->quoteName('id') . ' = ' . $stepId);
            $this->db->setQuery($del);
            $this->db->execute();

            $q3 = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                ->where($this->db->quoteName('workflow_id') . ' = ' . $wid)
                ->order($this->db->quoteName('step_number') . ' ASC');
            $this->db->setQuery($q3);
            $rest = $this->db->loadObjectList() ?: [];
            $n    = 1;
            foreach ($rest as $st) {
                $sid = (int) $st->id;
                if ((int) $st->step_number !== $n) {
                    $up = $this->db->getQuery(true)
                        ->update($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                        ->set($this->db->quoteName('step_number') . ' = ' . (string) $n)
                        ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote(Factory::getDate()->toSql()))
                        ->set($this->db->quoteName('modified_by') . ' = ' . (string) (int) Factory::getUser()->id)
                        ->where($this->db->quoteName('id') . ' = ' . $sid);
                    $this->db->setQuery($up);
                    $this->db->execute();
                }
                $n++;
            }
            $this->db->transactionCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return false;
        }
    }

    /**
     * Component approval groups with member counts.
     *
     * @return  array<int, object>
     *
     * @since   3.109.64
     */
    public function listComponentApprovalGroupsWithMemberCount(): array
    {
        if (!$this->hasApprovalGroupsSchema()) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select([
                'g.*',
                'COUNT(' . $this->db->quoteName('m.id') . ') AS ' . $this->db->quoteName('member_count'),
            ])
            ->from($this->db->quoteName('#__ordenproduccion_approval_groups', 'g'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__ordenproduccion_approval_group_users', 'm')
                . ' ON ' . $this->db->quoteName('m.group_id') . ' = ' . $this->db->quoteName('g.id')
            )
            ->group($this->db->quoteName('g.id'))
            ->order($this->db->quoteName('g.title') . ' ASC');
        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    public function getComponentApprovalGroup(int $groupId): ?object
    {
        if (!$this->hasApprovalGroupsSchema() || $groupId < 1) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_approval_groups'))
            ->where($this->db->quoteName('id') . ' = ' . $groupId)
            ->setLimit(1);
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * @return  int[]
     */
    public function getComponentApprovalGroupMemberIds(int $groupId): array
    {
        if (!$this->hasApprovalGroupsSchema() || $groupId < 1) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select($this->db->quoteName('user_id'))
            ->from($this->db->quoteName('#__ordenproduccion_approval_group_users'))
            ->where($this->db->quoteName('group_id') . ' = ' . $groupId)
            ->order($this->db->quoteName('user_id') . ' ASC');
        $this->db->setQuery($q);
        $col = $this->db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $col)));
    }

    /**
     * True if any workflow step references this component group id in approver_value.
     *
     * @since   3.109.64
     */
    public function isComponentApprovalGroupUsedInWorkflows(int $groupId): bool
    {
        if (!$this->hasSchema() || $groupId < 1) {
            return false;
        }

        $q = $this->db->getQuery(true)
            ->select([$this->db->quoteName('approver_value')])
            ->from($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
            ->where($this->db->quoteName('approver_type') . ' = ' . $this->db->quote('approval_group'));
        $this->db->setQuery($q);
        $vals = $this->db->loadColumn() ?: [];

        foreach ($vals as $v) {
            $ids = array_filter(array_map('intval', explode(',', (string) $v)));
            if (in_array($groupId, $ids, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Insert or update a component approval group and replace members.
     *
     * @param   int   $groupId  0 = create
     * @param   int[]  $userIds
     *
     * @return  int  Group id, or 0 on failure
     *
     * @since   3.109.64
     */
    public function adminSaveComponentApprovalGroup(int $groupId, array $data, array $userIds): int
    {
        if (!$this->hasApprovalGroupsSchema()) {
            return 0;
        }

        $title = isset($data['title']) ? trim((string) $data['title']) : '';
        if ($title === '' || strlen($title) > 255) {
            return 0;
        }

        $description = isset($data['description']) ? trim((string) $data['description']) : '';
        $published   = !empty($data['published']) ? 1 : 0;
        $user        = Factory::getUser();
        $uid         = (int) $user->id;
        $now         = Factory::getDate()->toSql();

        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function ($v) {
            return $v > 0;
        })));

        try {
            $this->db->transactionStart();

            if ($groupId < 1) {
                $ins = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__ordenproduccion_approval_groups'))
                    ->columns([
                        $this->db->quoteName('title'),
                        $this->db->quoteName('description'),
                        $this->db->quoteName('published'),
                        $this->db->quoteName('created'),
                        $this->db->quoteName('created_by'),
                    ])
                    ->values(implode(',', [
                        $this->db->quote($title),
                        $this->db->quote($description),
                        (string) $published,
                        $this->db->quote($now),
                        (string) $uid,
                    ]));
                $this->db->setQuery($ins);
                $this->db->execute();
                $groupId = (int) $this->db->insertid();
            } else {
                $exists = $this->getComponentApprovalGroup($groupId);
                if ($exists === null) {
                    $this->db->transactionRollback();

                    return 0;
                }
                $up = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_approval_groups'))
                    ->set($this->db->quoteName('title') . ' = ' . $this->db->quote($title))
                    ->set($this->db->quoteName('description') . ' = ' . $this->db->quote($description))
                    ->set($this->db->quoteName('published') . ' = ' . (string) $published)
                    ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
                    ->set($this->db->quoteName('modified_by') . ' = ' . (string) $uid)
                    ->where($this->db->quoteName('id') . ' = ' . $groupId);
                $this->db->setQuery($up);
                $this->db->execute();
            }

            if ($groupId < 1) {
                $this->db->transactionRollback();

                return 0;
            }

            $del = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ordenproduccion_approval_group_users'))
                ->where($this->db->quoteName('group_id') . ' = ' . $groupId);
            $this->db->setQuery($del);
            $this->db->execute();

            foreach ($userIds as $mUid) {
                $insM = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__ordenproduccion_approval_group_users'))
                    ->columns([
                        $this->db->quoteName('group_id'),
                        $this->db->quoteName('user_id'),
                        $this->db->quoteName('created'),
                    ])
                    ->values(implode(',', [
                        (string) $groupId,
                        (string) $mUid,
                        $this->db->quote($now),
                    ]));
                $this->db->setQuery($insM);
                $this->db->execute();
            }

            $this->db->transactionCommit();

            return $groupId;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return 0;
        }
    }

    /**
     * Delete group and members if not referenced by a workflow step.
     *
     * @since   3.109.64
     */
    public function adminDeleteComponentApprovalGroup(int $groupId): bool
    {
        if (!$this->hasApprovalGroupsSchema() || $groupId < 1) {
            return false;
        }

        if ($this->getComponentApprovalGroup($groupId) === null) {
            return false;
        }

        if ($this->isComponentApprovalGroupUsedInWorkflows($groupId)) {
            return false;
        }

        try {
            $this->db->transactionStart();
            $d1 = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ordenproduccion_approval_group_users'))
                ->where($this->db->quoteName('group_id') . ' = ' . $groupId);
            $this->db->setQuery($d1);
            $this->db->execute();
            $d2 = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ordenproduccion_approval_groups'))
                ->where($this->db->quoteName('id') . ' = ' . $groupId);
            $this->db->setQuery($d2);
            $this->db->execute();
            $this->db->transactionCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            return false;
        }
    }

    /**
     * Update workflow row from Ajustes UI (does not change entity_type).
     *
     * @since   3.109.58
     */
    public function adminUpdateWorkflow(int $workflowId, array $data): bool
    {
        if (!$this->hasSchema() || $workflowId < 1) {
            return false;
        }

        $user = Factory::getUser();
        $uid  = (int) $user->id;
        $now  = Factory::getDate()->toSql();

        $name        = isset($data['name']) ? trim((string) $data['name']) : '';
        $description = isset($data['description']) ? trim((string) $data['description']) : '';
        $published   = !empty($data['published']) ? 1 : 0;

        if ($name === '') {
            return false;
        }
        if (strlen($name) > 255) {
            $name = substr($name, 0, 255);
        }

        $emailSubjectAssign  = isset($data['email_subject_assign']) ? trim((string) $data['email_subject_assign']) : '';
        $emailBodyAssign     = isset($data['email_body_assign']) ? (string) $data['email_body_assign'] : '';
        $emailSubjectDecided = isset($data['email_subject_decided']) ? trim((string) $data['email_subject_decided']) : '';
        $emailBodyDecided    = isset($data['email_body_decided']) ? (string) $data['email_body_decided'] : '';

        if (strlen($emailSubjectAssign) > 255) {
            $emailSubjectAssign = substr($emailSubjectAssign, 0, 255);
        }
        if (strlen($emailSubjectDecided) > 255) {
            $emailSubjectDecided = substr($emailSubjectDecided, 0, 255);
        }

        try {
            $q = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_workflows'))
                ->set($this->db->quoteName('name') . ' = ' . $this->db->quote($name))
                ->set($this->db->quoteName('description') . ' = ' . $this->db->quote($description))
                ->set($this->db->quoteName('published') . ' = ' . (string) (int) $published)
                ->set(
                    $this->db->quoteName('email_subject_assign') . ' = '
                    . ($emailSubjectAssign === '' ? 'NULL' : $this->db->quote($emailSubjectAssign))
                )
                ->set(
                    $this->db->quoteName('email_body_assign') . ' = '
                    . ($emailBodyAssign === '' ? 'NULL' : $this->db->quote($emailBodyAssign))
                )
                ->set(
                    $this->db->quoteName('email_subject_decided') . ' = '
                    . ($emailSubjectDecided === '' ? 'NULL' : $this->db->quote($emailSubjectDecided))
                )
                ->set(
                    $this->db->quoteName('email_body_decided') . ' = '
                    . ($emailBodyDecided === '' ? 'NULL' : $this->db->quote($emailBodyDecided))
                )
                ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
                ->set($this->db->quoteName('modified_by') . ' = ' . (string) $uid)
                ->where($this->db->quoteName('id') . ' = ' . $workflowId);
            $this->db->setQuery($q);
            $this->db->execute();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Update one workflow step row from Ajustes UI.
     *
     * @since   3.109.58
     */
    public function adminUpdateWorkflowStep(int $stepId, array $data): bool
    {
        if (!$this->hasSchema() || $stepId < 1) {
            return false;
        }

        $user = Factory::getUser();
        $uid  = (int) $user->id;
        $now  = Factory::getDate()->toSql();

        $stepName = isset($data['step_name']) ? trim((string) $data['step_name']) : '';
        if ($stepName === '') {
            return false;
        }
        if (strlen($stepName) > 255) {
            $stepName = substr($stepName, 0, 255);
        }

        $approverType = isset($data['approver_type']) ? strtolower(trim((string) $data['approver_type'])) : '';
        if (!in_array($approverType, ['user', 'joomla_group', 'named_group', 'approval_group'], true)) {
            return false;
        }

        $approverValue = isset($data['approver_value']) ? trim((string) $data['approver_value']) : '';
        if ($approverValue === '' || strlen($approverValue) > 512) {
            return false;
        }

        if ($approverType === 'user') {
            if (!preg_match('/^\d+(,\d+)*$/', $approverValue)) {
                return false;
            }
            $uids = array_unique(array_filter(array_map('intval', explode(',', $approverValue)), static function ($id) {
                return $id > 0;
            }));
            if ($uids === []) {
                return false;
            }
            $in = implode(',', $uids);
            $qchk = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__users'))
                ->where($this->db->quoteName('id') . ' IN (' . $in . ')')
                ->where($this->db->quoteName('block') . ' = 0');
            $this->db->setQuery($qchk);
            if ((int) $this->db->loadResult() !== count($uids)) {
                return false;
            }
        }

        if ($approverType === 'approval_group') {
            if (!preg_match('/^\d+(,\d+)*$/', $approverValue)) {
                return false;
            }
            if ($this->hasApprovalGroupsSchema()) {
                $gids = array_unique(array_filter(array_map('intval', explode(',', $approverValue))));
                if ($gids === []) {
                    return false;
                }
                $in   = implode(',', $gids);
                $qchk = $this->db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($this->db->quoteName('#__ordenproduccion_approval_groups'))
                    ->where($this->db->quoteName('id') . ' IN (' . $in . ')');
                $this->db->setQuery($qchk);
                if ((int) $this->db->loadResult() !== count($gids)) {
                    return false;
                }
            }
        }

        $requireAll = !empty($data['require_all']) ? 1 : 0;

        $timeoutHours = isset($data['timeout_hours']) ? (int) $data['timeout_hours'] : 0;
        if ($timeoutHours < 0) {
            $timeoutHours = 0;
        }

        $timeoutAction = isset($data['timeout_action']) ? strtolower(trim((string) $data['timeout_action'])) : 'escalate';
        if (!in_array($timeoutAction, ['escalate', 'auto_approve', 'auto_reject'], true)) {
            $timeoutAction = 'escalate';
        }

        try {
            $q = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ordenproduccion_approval_workflow_steps'))
                ->set($this->db->quoteName('step_name') . ' = ' . $this->db->quote($stepName))
                ->set($this->db->quoteName('approver_type') . ' = ' . $this->db->quote($approverType))
                ->set($this->db->quoteName('approver_value') . ' = ' . $this->db->quote($approverValue))
                ->set($this->db->quoteName('require_all') . ' = ' . (string) $requireAll)
                ->set($this->db->quoteName('timeout_hours') . ' = ' . (string) $timeoutHours)
                ->set($this->db->quoteName('timeout_action') . ' = ' . $this->db->quote($timeoutAction))
                ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
                ->set($this->db->quoteName('modified_by') . ' = ' . (string) $uid)
                ->where($this->db->quoteName('id') . ' = ' . $stepId);
            $this->db->setQuery($q);
            $this->db->execute();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
