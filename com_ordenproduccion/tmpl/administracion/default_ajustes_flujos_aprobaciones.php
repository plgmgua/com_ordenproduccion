<?php
/**
 * Ajustes → Flujos de aprobaciones: edit workflow definitions and steps.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$bundles = isset($this->approvalWorkflowsAdmin) && is_array($this->approvalWorkflowsAdmin) ? $this->approvalWorkflowsAdmin : [];
$schemaOk = !empty($this->approvalWorkflowSchemaAvailable);

$entityLabel = static function (string $entityType): string {
    $map = [
        'cotizacion_confirmation' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_CONFIRMATION',
        'orden_status'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_STATUS',
        'timesheet'               => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_TIMESHEET',
        'payment_proof'           => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_PAYMENT_PROOF',
    ];
    $key = $map[$entityType] ?? 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_GENERIC';

    return Text::_($key);
};

$saveAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveApprovalWorkflows', false);
?>
<div class="card mb-4">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-project-diagram"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_INTRO'); ?></p>

        <?php if (!$schemaOk) : ?>
            <div class="alert alert-warning mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'); ?></div>
        <?php elseif ($bundles === []) : ?>
            <div class="alert alert-info mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_EMPTY'); ?></div>
        <?php else : ?>
            <form action="<?php echo htmlspecialchars($saveAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" name="adminFormApprovalWorkflows" id="adminFormApprovalWorkflows" class="form-validate">
                <?php echo HTMLHelper::_('form.token'); ?>

                <?php foreach ($bundles as $bundle) :
                    $wf = $bundle['workflow'] ?? null;
                    $steps = isset($bundle['steps']) && is_array($bundle['steps']) ? $bundle['steps'] : [];
                    if (!$wf || !isset($wf->id)) {
                        continue;
                    }
                    $wid = (int) $wf->id;
                    $etype = (string) ($wf->entity_type ?? '');
                    $pub   = !empty($wf->published);
                    ?>
                <div class="border rounded p-3 mb-4 bg-light">
                    <h3 class="h5 mb-3"><?php echo htmlspecialchars($entityLabel($etype), ENT_QUOTES, 'UTF-8'); ?>
                        <span class="badge bg-secondary ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOW_ENTITY_READONLY'); ?>:
                            <code><?php echo htmlspecialchars($etype, ENT_QUOTES, 'UTF-8'); ?></code></span>
                    </h3>

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
                        <div class="col-md-6">
                            <label class="form-label" for="awf-em-as-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_EMAIL_ASSIGN_SUBJECT'); ?></label>
                            <input type="text" class="form-control" name="awf_workflow[<?php echo $wid; ?>][email_subject_assign]" id="awf-em-as-<?php echo $wid; ?>"
                                value="<?php echo htmlspecialchars((string) ($wf->email_subject_assign ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="awf-em-ds-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_EMAIL_DECIDED_SUBJECT'); ?></label>
                            <input type="text" class="form-control" name="awf_workflow[<?php echo $wid; ?>][email_subject_decided]" id="awf-em-ds-<?php echo $wid; ?>"
                                value="<?php echo htmlspecialchars((string) ($wf->email_subject_decided ?? ''), ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="awf-em-ab-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_EMAIL_ASSIGN_BODY'); ?></label>
                            <textarea class="form-control font-monospace small" name="awf_workflow[<?php echo $wid; ?>][email_body_assign]" id="awf-em-ab-<?php echo $wid; ?>" rows="4"><?php echo htmlspecialchars((string) ($wf->email_body_assign ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="awf-em-db-<?php echo $wid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_EMAIL_DECIDED_BODY'); ?></label>
                            <textarea class="form-control font-monospace small" name="awf_workflow[<?php echo $wid; ?>][email_body_decided]" id="awf-em-db-<?php echo $wid; ?>" rows="4"><?php echo htmlspecialchars((string) ($wf->email_body_decided ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>

                    <h4 class="h6 mt-3"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_SECTION'); ?></h4>
                    <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_HELP_APPROVER'); ?></p>

                    <?php foreach ($steps as $st) :
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
                            <div class="row g-2">
                                <div class="col-md-2">
                                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_NUMBER'); ?></label>
                                    <input type="text" class="form-control" value="<?php echo (int) ($st->step_number ?? 0); ?>" readonly disabled />
                                </div>
                                <div class="col-md-10">
                                    <label class="form-label" for="awf-st-name-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_NAME'); ?></label>
                                    <input type="text" class="form-control" name="awf_step[<?php echo $sid; ?>][step_name]" id="awf-st-name-<?php echo $sid; ?>"
                                        value="<?php echo htmlspecialchars((string) ($st->step_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="awf-st-at-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_TYPE'); ?></label>
                                    <select class="form-select" name="awf_step[<?php echo $sid; ?>][approver_type]" id="awf-st-at-<?php echo $sid; ?>">
                                        <option value="user"<?php echo $atype === 'user' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_USER'); ?></option>
                                        <option value="joomla_group"<?php echo $atype === 'joomla_group' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_JOOMLA_GROUP'); ?></option>
                                        <option value="named_group"<?php echo $atype === 'named_group' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_NAMED_GROUP'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="awf-st-av-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_VALUE'); ?></label>
                                    <input type="text" class="form-control" name="awf_step[<?php echo $sid; ?>][approver_value]" id="awf-st-av-<?php echo $sid; ?>"
                                        value="<?php echo htmlspecialchars((string) ($st->approver_value ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="512"
                                        autocomplete="off" />
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="hidden" name="awf_step[<?php echo $sid; ?>][require_all]" value="0" />
                                        <input class="form-check-input" type="checkbox" name="awf_step[<?php echo $sid; ?>][require_all]" value="1" id="awf-st-ra-<?php echo $sid; ?>"<?php echo $reqAll ? ' checked' : ''; ?> />
                                        <label class="form-check-label" for="awf-st-ra-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_REQUIRE_ALL'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="awf-st-th-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_HOURS'); ?></label>
                                    <input type="number" class="form-control" name="awf_step[<?php echo $sid; ?>][timeout_hours]" id="awf-st-th-<?php echo $sid; ?>"
                                        value="<?php echo (int) ($st->timeout_hours ?? 0); ?>" min="0" step="1" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="awf-st-ta-<?php echo $sid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_ACTION'); ?></label>
                                    <select class="form-select" name="awf_step[<?php echo $sid; ?>][timeout_action]" id="awf-st-ta-<?php echo $sid; ?>">
                                        <option value="escalate"<?php echo $toAct === 'escalate' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_ESCALATE'); ?></option>
                                        <option value="auto_approve"<?php echo $toAct === 'auto_approve' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_AUTO_APPROVE'); ?></option>
                                        <option value="auto_reject"<?php echo $toAct === 'auto_reject' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TIMEOUT_AUTO_REJECT'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_SAVE'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>
