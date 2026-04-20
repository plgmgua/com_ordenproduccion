<?php
/**
 * Ajustes → Grupos de aprobaciones: component-managed groups (CRUD) + workflow usage reference.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$schemaOk = !empty($this->approvalGroupsSchemaAvailable);
$editorId = isset($this->approvalGroupEditorId) ? (int) $this->approvalGroupEditorId : -1;
$row      = isset($this->approvalGroupEditorRow) ? $this->approvalGroupEditorRow : null;
$memberIds = isset($this->approvalGroupEditorMemberIds) && is_array($this->approvalGroupEditorMemberIds)
    ? $this->approvalGroupEditorMemberIds
    : [];
$groups = isset($this->approvalReferenceJoomlaGroups) && is_array($this->approvalReferenceJoomlaGroups)
    ? $this->approvalReferenceJoomlaGroups
    : [];
$stepRows = isset($this->approvalWorkflowStepsApproverRows) && is_array($this->approvalWorkflowStepsApproverRows)
    ? $this->approvalWorkflowStepsApproverRows
    : [];
$wfSchemaOk = !empty($this->approvalWorkflowSchemaAvailable);

$listUrl  = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones');
$newUrl   = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones&approval_group_id=0');
$saveTask = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveComponentApprovalGroup', false);
$delTask  = Route::_('index.php?option=com_ordenproduccion&task=administracion.deleteComponentApprovalGroup', false);
?>
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="card-title mb-0">
            <i class="fas fa-users-cog"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_TITLE'); ?>
        </h2>
        <?php if ($schemaOk && $editorId < 0) : ?>
            <a href="<?php echo $newUrl; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_NEW'); ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$schemaOk) : ?>
            <div class="alert alert-warning mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUPS_SCHEMA_MISSING'); ?></div>
        <?php elseif ($editorId >= 0 && $row) :
            $gid = (int) ($row->id ?? 0);
            $membersStr = implode(', ', $memberIds);
            ?>
            <p class="mb-3">
                <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_BACK_LIST'); ?>
                </a>
            </p>
            <form action="<?php echo htmlspecialchars($saveTask, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="form-validate">
                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="approval_group_id" value="<?php echo $gid; ?>" />

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="ag-title"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_TITLE'); ?></label>
                        <input type="text" class="form-control" name="title" id="ag-title" required maxlength="255"
                            value="<?php echo htmlspecialchars((string) ($row->title ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input type="hidden" name="published" value="0" />
                            <input class="form-check-input" type="checkbox" name="published" value="1" id="ag-pub"
                                <?php echo !empty($row->published) ? ' checked' : ''; ?> />
                            <label class="form-check-label" for="ag-pub"><?php echo Text::_('JPUBLISHED'); ?></label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ag-desc"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_DESCRIPTION'); ?></label>
                        <textarea class="form-control" name="description" id="ag-desc" rows="3"><?php echo htmlspecialchars((string) ($row->description ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ag-members"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_MEMBERS'); ?></label>
                        <textarea class="form-control font-monospace" name="member_user_ids" id="ag-members" rows="4"
                            placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_MEMBERS_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($membersStr, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_MEMBERS_HELP'); ?></p>
                    </div>
                </div>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo Text::_('JSAVE'); ?>
                    </button>
                </div>
            </form>
            <?php if ($gid > 0) : ?>
            <form action="<?php echo htmlspecialchars($delTask, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mt-2"
                  onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_DELETE_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>');">
                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="approval_group_id" value="<?php echo $gid; ?>" />
                <button type="submit" class="btn btn-outline-danger"><?php echo Text::_('JACTION_DELETE'); ?></button>
            </form>
            <?php endif; ?>
        <?php else : ?>
            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_INTRO_COMPONENT'); ?></p>
            <?php if ($groups === []) : ?>
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUPS_LIST_EMPTY'); ?></div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_COL_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_TITLE'); ?></th>
                                <th scope="col"><?php echo Text::_('JPUBLISHED'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_COL_MEMBERS'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_LIST_ACTIONS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $g) :
                                $id = (int) ($g->id ?? 0);
                                ?>
                            <tr>
                                <td><code><?php echo $id; ?></code></td>
                                <td><?php echo htmlspecialchars((string) ($g->title ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo !empty($g->published) ? Text::_('JYES') : Text::_('JNO'); ?></td>
                                <td class="text-end"><?php echo (int) ($g->member_count ?? 0); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-primary btn-sm" href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones&approval_group_id=' . $id); ?>">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_EDIT'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 class="h5 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_TITLE'); ?></h3>
            <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_HELP_COMPONENT'); ?></p>
            <?php if (!$wfSchemaOk) : ?>
                <div class="alert alert-warning mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'); ?></div>
            <?php elseif ($stepRows === []) : ?>
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_EMPTY'); ?></div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_ENTITY'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_NUMBER'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEP_NAME'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_TYPE'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_APPROVER_VALUE'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_RESOLVED'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stepRows as $st) : ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars((string) ($st->entity_type ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td><?php echo (int) ($st->step_number ?? 0); ?></td>
                                <td><?php echo htmlspecialchars((string) ($st->step_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($st->approver_type ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="small"><?php echo htmlspecialchars((string) ($st->approver_value ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="small"><?php echo htmlspecialchars((string) ($st->approver_display ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <p class="mt-3 mb-0">
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones'); ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-sitemap"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_LINK_FLOWS'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>
