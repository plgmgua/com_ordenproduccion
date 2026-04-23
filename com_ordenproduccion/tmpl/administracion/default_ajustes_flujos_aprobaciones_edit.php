<?php
/**
 * Single workflow editor (included when wf_id set).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$bundle = isset($this->approvalWorkflowEditBundle) && is_array($this->approvalWorkflowEditBundle)
    ? $this->approvalWorkflowEditBundle
    : null;
$wf     = $bundle['workflow'] ?? null;
$steps  = isset($bundle['steps']) && is_array($bundle['steps']) ? $bundle['steps'] : [];
$groups = isset($this->approvalComponentGroupsForSelect) && is_array($this->approvalComponentGroupsForSelect)
    ? $this->approvalComponentGroupsForSelect
    : [];
$joomlaUsers = isset($this->approvalJoomlaUsersForSelect) && is_array($this->approvalJoomlaUsersForSelect)
    ? $this->approvalJoomlaUsersForSelect
    : [];
$hasGroupSchema = !empty($this->approvalGroupsSchemaAvailable);
$userPickerSize = min(14, max(6, count($joomlaUsers)));

if (!$wf || !isset($wf->id)) {
    return;
}

$wid = (int) $wf->id;
$etype = (string) ($wf->entity_type ?? '');
$pub   = !empty($wf->published);

$entityLabel = static function (string $entityType): string {
    $map = [
        'cotizacion_confirmation' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_CONFIRMATION',
        'orden_status'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_STATUS',
        'timesheet'               => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_TIMESHEET',
        'payment_proof'           => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_PAYMENT_PROOF',
        'solicitud_descuento'     => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_DESCUENTO',
        'solicitud_cotizacion'    => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_COTIZACION',
        'orden_compra'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_COMPRA',
    ];
    $key = $map[$entityType] ?? 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_GENERIC';

    return Text::_($key);
};

$saveAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveApprovalWorkflows', false);
$addStepAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.addApprovalWorkflowStep', false);
$delStepAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.deleteApprovalWorkflowStep', false);
$listUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones');
$formId = 'adminFormApprovalWorkflowsSave';
?>
<p class="mb-3">
    <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_BACK_TO_LIST'); ?>
    </a>
</p>

<div class="border rounded p-3 mb-4 bg-light">
    <h3 class="h5 mb-3"><?php echo htmlspecialchars($entityLabel($etype), ENT_QUOTES, 'UTF-8'); ?>
        <span class="badge bg-secondary ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOW_ENTITY_READONLY'); ?>:
            <code><?php echo htmlspecialchars($etype, ENT_QUOTES, 'UTF-8'); ?></code></span>
    </h3>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="h6 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_SECTION'); ?></h4>
        <form action="<?php echo htmlspecialchars($addStepAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="m-0 d-inline">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="workflow_id" value="<?php echo $wid; ?>" />
            <button type="submit" class="btn btn-outline-success btn-sm">
                <i class="fas fa-plus"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_ADD_STEP'); ?>
            </button>
        </form>
    </div>

    <form action="<?php echo htmlspecialchars($saveAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="<?php echo $formId; ?>" class="form-validate">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="awf_return_wf_id" value="<?php echo $wid; ?>" />
        <input type="hidden" name="awf_workflow[<?php echo $wid; ?>][email_subject_assign]" value="" />
        <input type="hidden" name="awf_workflow[<?php echo $wid; ?>][email_subject_decided]" value="" />

        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <label class="form-label" for="awf-name-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WF_NAME'); ?></label>
                <input type="text" class="form-control" name="awf_workflow[<?php echo $wid; ?>][name]" id="awf-name-<?php echo $wid; ?>"
                    value="<?php echo htmlspecialchars((string) ($wf->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" />
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="awf_workflow[<?php echo $wid; ?>][published]" value="0" />
                    <input class="form-check-input" type="checkbox" name="awf_workflow[<?php echo $wid; ?>][published]" value="1" id="awf-pub-<?php echo $wid; ?>"<?php echo $pub ? ' checked' : ''; ?> />
                    <label class="form-check-label" for="awf-pub-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WF_PUBLISHED'); ?></label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="awf-desc-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WF_DESCRIPTION'); ?></label>
                <textarea class="form-control" name="awf_workflow[<?php echo $wid; ?>][description]" id="awf-desc-<?php echo $wid; ?>" rows="2"><?php echo htmlspecialchars((string) ($wf->description ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-12">
                <h4 class="h6 mt-2 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_MESSAGES'); ?></h4>
                <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_INTRO'); ?></p>
            </div>
            <div class="col-12">
                <label class="form-label" for="awf-tg-assign-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_ASSIGN_LABEL'); ?></label>
                <textarea class="form-control font-monospace small" name="awf_workflow[<?php echo $wid; ?>][email_body_assign]" id="awf-tg-assign-<?php echo $wid; ?>" rows="6"><?php echo htmlspecialchars((string) ($wf->email_body_assign ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_VARS_ASSIGN'); ?></p>
                <?php if ($etype === 'payment_proof') : ?>
                <p class="small text-muted mb-0 mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_VARS_PAYMENT_PROOF'); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="awf-tg-outcome-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_OUTCOME_LABEL'); ?></label>
                <textarea class="form-control font-monospace small" name="awf_workflow[<?php echo $wid; ?>][email_body_decided]" id="awf-tg-outcome-<?php echo $wid; ?>" rows="6"><?php echo htmlspecialchars((string) ($wf->email_body_decided ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_VARS_OUTCOME'); ?></p>
                <?php if ($etype === 'payment_proof') : ?>
                <p class="small text-muted mb-0 mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_VARS_PAYMENT_PROOF'); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($etype === 'orden_compra') : ?>
            <div class="col-12">
                <h4 class="h6 mt-3 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_OC_APPROVED_EMAIL_SECTION'); ?></h4>
                <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_OC_APPROVED_EMAIL_INTRO'); ?></p>
            </div>
            <div class="col-12">
                <label class="form-label" for="awf-oc-mail-subj-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_OC_APPROVED_EMAIL_SUBJECT'); ?></label>
                <input type="text" class="form-control font-monospace small" name="awf_workflow[<?php echo $wid; ?>][email_ordencompra_approved_subject]" id="awf-oc-mail-subj-<?php echo $wid; ?>" maxlength="255"
                    value="<?php echo htmlspecialchars((string) ($wf->email_ordencompra_approved_subject ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
            <div class="col-12">
                <label class="form-label" for="awf-oc-mail-body-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_OC_APPROVED_EMAIL_BODY'); ?></label>
                <textarea class="form-control font-monospace small" name="awf_workflow[<?php echo $wid; ?>][email_ordencompra_approved_body]" id="awf-oc-mail-body-<?php echo $wid; ?>" rows="8"><?php echo htmlspecialchars((string) ($wf->email_ordencompra_approved_body ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_OC_APPROVED_EMAIL_VARS'); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <p class="small text-muted mt-3 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_HELP_APPROVER'); ?></p>
        <?php if ($hasGroupSchema && $groups === []) : ?>
            <div class="alert alert-warning small"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_NO_COMPONENT_GROUPS'); ?></div>
        <?php endif; ?>

        <?php
        $stepCount = count($steps);
        foreach ($steps as $st) :
            if (!isset($st->id)) {
                continue;
            }
            $sid = (int) $st->id;
            $atype = (string) ($st->approver_type ?? 'named_group');
            $reqAll = !empty($st->require_all);
            $toAct = (string) ($st->timeout_action ?? 'escalate');
            ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-info"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_NUMBER'); ?> <?php echo (int) ($st->step_number ?? 0); ?></span>
                    <?php if ($stepCount > 1) : ?>
                    <button type="button" class="btn btn-outline-danger btn-sm awf-delete-step-btn"
                        data-step-id="<?php echo $sid; ?>"
                        data-confirm="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_DELETE_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_DELETE'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="row g-2">
                    <div class="col-md-10">
                        <label class="form-label" for="awf-st-name-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_NAME'); ?></label>
                        <input type="text" class="form-control" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][step_name]" id="awf-st-name-<?php echo $sid; ?>"
                            value="<?php echo htmlspecialchars((string) ($st->step_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="awf-st-at-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_TYPE'); ?></label>
                        <select class="form-select awf-approver-type" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][approver_type]" id="awf-st-at-<?php echo $sid; ?>" data-step="<?php echo $sid; ?>">
                            <option value="user"<?php echo $atype === 'user' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_INDIVIDUAL_USERS'); ?></option>
                            <option value="approval_group"<?php echo $atype === 'approval_group' ? ' selected' : ''; ?><?php echo !$hasGroupSchema ? ' disabled' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_APPROVAL_GROUP'); ?></option>
                            <optgroup label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVER_OPTGROUP_LEGACY'), ENT_QUOTES, 'UTF-8'); ?>">
                                <option value="joomla_group"<?php echo $atype === 'joomla_group' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_JOOMLA_GROUP'); ?></option>
                                <option value="named_group"<?php echo $atype === 'named_group' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_NAMED_GROUP'); ?></option>
                            </optgroup>
                        </select>
                        <p class="small text-muted mb-0 mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_TYPE_HELP'); ?></p>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label awf-label-user-pick<?php echo $atype !== 'user' ? ' d-none' : ''; ?>" id="awf-lbl-user-<?php echo $sid; ?>" for="awf-st-us-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_USER_PICKER_LABEL'); ?></label>
                        <label class="form-label awf-label-legacy-val<?php echo in_array($atype, ['joomla_group', 'named_group'], true) ? '' : ' d-none'; ?>" id="awf-lbl-legacy-<?php echo $sid; ?>" for="awf-st-av-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_VALUE'); ?></label>
                        <label class="form-label awf-label-group-pick<?php echo $atype !== 'approval_group' ? ' d-none' : ''; ?>" id="awf-lbl-ag-<?php echo $sid; ?>" for="awf-st-ag-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_GROUP_PICK'); ?></label>
                        <select class="form-select awf-user-multiselect mb-2<?php echo $atype !== 'user' ? ' d-none' : ''; ?>"
                            form="<?php echo $formId; ?>"
                            name="awf_step[<?php echo $sid; ?>][approver_user_ids][]"
                            id="awf-st-us-<?php echo $sid; ?>"
                            multiple="multiple"
                            size="<?php echo (int) $userPickerSize; ?>"
                            data-step="<?php echo $sid; ?>"
                            <?php echo $atype !== 'user' ? 'disabled' : ''; ?>>
                            <?php
                            $selUids = array_unique(array_filter(array_map('intval', explode(',', (string) ($st->approver_value ?? ''))), static function ($id) {
                                return $id > 0;
                            }));
                            foreach ($joomlaUsers as $ju) :
                                $juid = (int) ($ju->id ?? 0);
                                $jnm  = (string) ($ju->name ?? '');
                                $jun  = (string) ($ju->username ?? '');
                                $label = $jnm . ' (' . $jun . ')';
                                ?>
                            <option value="<?php echo $juid; ?>"<?php echo in_array($juid, $selUids, true) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="small text-muted awf-help-user-pick mb-2<?php echo $atype !== 'user' ? ' d-none' : ''; ?>" id="awf-help-user-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_USER_PICKER_HELP'); ?></p>
                        <?php if ($hasGroupSchema) : ?>
                        <select class="form-select awf-approver-group-select mb-2<?php echo $atype !== 'approval_group' ? ' d-none' : ''; ?>" id="awf-st-ag-<?php echo $sid; ?>" data-step="<?php echo $sid; ?>">
                            <?php foreach ($groups as $g) :
                                $gid = (int) ($g->id ?? 0);
                                $gt  = (string) ($g->title ?? '');
                                $gp  = isset($g->published) ? (int) $g->published : 1;
                                $selVal = trim((string) ($st->approver_value ?? ''));
                                $isSel = $atype === 'approval_group' && $selVal === (string) $gid;
                                ?>
                            <option value="<?php echo $gid; ?>"<?php echo $isSel ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($gt, ENT_QUOTES, 'UTF-8'); ?><?php echo $gp ? '' : ' (' . Text::_('JUNPUBLISHED') . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <input type="text" class="form-control awf-approver-value-input<?php echo ($atype === 'user' || $atype === 'approval_group') ? ' d-none' : ''; ?>" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][approver_value]" id="awf-st-av-<?php echo $sid; ?>"
                            value="<?php echo htmlspecialchars((string) ($st->approver_value ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo in_array($atype, ['joomla_group', 'named_group'], true) ? 'required ' : ''; ?>
                            <?php echo $atype === 'user' ? 'disabled ' : ''; ?>
                            maxlength="512"
                            autocomplete="off"<?php echo ($hasGroupSchema && $atype === 'approval_group') ? ' readonly' : ''; ?> />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][require_all]" value="0" />
                            <input class="form-check-input" type="checkbox" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][require_all]" value="1" id="awf-st-ra-<?php echo $sid; ?>"<?php echo $reqAll ? ' checked' : ''; ?> />
                            <label class="form-check-label" for="awf-st-ra-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_REQUIRE_ALL'); ?></label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="awf-st-th-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_HOURS'); ?></label>
                        <input type="number" class="form-control" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][timeout_hours]" id="awf-st-th-<?php echo $sid; ?>"
                            value="<?php echo (int) ($st->timeout_hours ?? 0); ?>" min="0" step="1" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="awf-st-ta-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_ACTION'); ?></label>
                        <select class="form-select" form="<?php echo $formId; ?>" name="awf_step[<?php echo $sid; ?>][timeout_action]" id="awf-st-ta-<?php echo $sid; ?>">
                            <option value="escalate"<?php echo $toAct === 'escalate' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_ESCALATE'); ?></option>
                            <option value="auto_approve"<?php echo $toAct === 'auto_approve' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_AUTO_APPROVE'); ?></option>
                            <option value="auto_reject"<?php echo $toAct === 'auto_reject' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_AUTO_REJECT'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_SAVE'); ?>
        </button>
    </form>
</div>
<form id="awfDeleteStepForm" action="<?php echo htmlspecialchars($delStepAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="d-none" aria-hidden="true">
    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="workflow_id" value="<?php echo $wid; ?>" />
    <input type="hidden" name="step_id" id="awfDeleteStepId" value="" />
</form>
<script>
(function () {
  function toggleLabels(stepId, mode) {
    var lu = document.getElementById('awf-lbl-user-' + stepId);
    var ll = document.getElementById('awf-lbl-legacy-' + stepId);
    var lg = document.getElementById('awf-lbl-ag-' + stepId);
    var hu = document.getElementById('awf-help-user-' + stepId);
    if (lu) lu.classList.toggle('d-none', mode !== 'user');
    if (ll) ll.classList.toggle('d-none', mode !== 'joomla_group' && mode !== 'named_group');
    if (lg) lg.classList.toggle('d-none', mode !== 'approval_group');
    if (hu) hu.classList.toggle('d-none', mode !== 'user');
  }
  function syncStep(stepId) {
    var sel = document.getElementById('awf-st-at-' + stepId);
    var inp = document.getElementById('awf-st-av-' + stepId);
    var gsel = document.getElementById('awf-st-ag-' + stepId);
    var umul = document.getElementById('awf-st-us-' + stepId);
    if (!sel || !inp) return;
    var mode = sel.value;
    toggleLabels(stepId, mode);
    if (mode === 'user') {
      inp.classList.add('d-none');
      inp.removeAttribute('required');
      inp.setAttribute('disabled', 'disabled');
      if (umul) {
        umul.classList.remove('d-none');
        umul.removeAttribute('disabled');
      }
      if (gsel) gsel.classList.add('d-none');
      inp.removeAttribute('readonly');
    } else if (mode === 'approval_group' && gsel) {
      if (umul) {
        umul.classList.add('d-none');
        umul.setAttribute('disabled', 'disabled');
      }
      inp.classList.remove('d-none');
      inp.setAttribute('readonly', 'readonly');
      inp.removeAttribute('disabled');
      inp.removeAttribute('required');
      gsel.classList.remove('d-none');
      inp.value = gsel.value || '';
    } else {
      if (umul) {
        umul.classList.add('d-none');
        umul.setAttribute('disabled', 'disabled');
      }
      if (gsel) gsel.classList.add('d-none');
      inp.classList.remove('d-none');
      inp.removeAttribute('readonly');
      inp.removeAttribute('disabled');
      inp.setAttribute('required', 'required');
    }
  }
  document.querySelectorAll('.awf-approver-type').forEach(function (sel) {
    var id = sel.getAttribute('data-step');
    sel.addEventListener('change', function () { syncStep(id); });
    syncStep(id);
  });
  document.querySelectorAll('.awf-approver-group-select').forEach(function (gsel) {
    gsel.addEventListener('change', function () {
      var id = gsel.getAttribute('data-step');
      var inp = document.getElementById('awf-st-av-' + id);
      if (inp) inp.value = gsel.value;
    });
  });
  document.querySelectorAll('.awf-delete-step-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var msg = btn.getAttribute('data-confirm') || '';
      if (msg && !window.confirm(msg)) return;
      var sid = btn.getAttribute('data-step-id');
      var hid = document.getElementById('awfDeleteStepId');
      var f = document.getElementById('awfDeleteStepForm');
      if (hid && f && sid) {
        hid.value = sid;
        f.submit();
      }
    });
  });
})();
</script>
