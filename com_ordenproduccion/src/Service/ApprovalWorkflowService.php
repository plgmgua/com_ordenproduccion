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
                ApprovalWorkflowEntityHelper::applyPaymentProofVerified($eid);
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
            $uid = (int) $value;

            return $uid > 0 ? [$uid] : [];
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
}
