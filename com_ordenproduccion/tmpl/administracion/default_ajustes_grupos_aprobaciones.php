<?php
/**
 * Ajustes → Grupos de aprobaciones: Joomla user groups reference for workflow approvers.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$groups = isset($this->approvalReferenceJoomlaGroups) && is_array($this->approvalReferenceJoomlaGroups)
    ? $this->approvalReferenceJoomlaGroups
    : [];
$stepRows = isset($this->approvalWorkflowStepsApproverRows) && is_array($this->approvalWorkflowStepsApproverRows)
    ? $this->approvalWorkflowStepsApproverRows
    : [];
$schemaOk = !empty($this->approvalWorkflowSchemaAvailable);
?>
<div class="card mb-4">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-users-cog"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_INTRO'); ?></p>
        <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_JOOMLA_ADMIN'); ?></p>

        <h3 class="h5 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_TABLE_TITLE'); ?></h3>
        <?php if ($groups === []) : ?>
            <div class="alert alert-warning mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_EMPTY'); ?></div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_COL_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_COL_TITLE'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_COL_MEMBERS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g) : ?>
                        <tr>
                            <td><code><?php echo (int) ($g->id ?? 0); ?></code></td>
                            <td><?php echo htmlspecialchars((string) ($g->title ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?php echo (int) ($g->member_count ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="h5 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_TITLE'); ?></h3>
        <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_USAGE_HELP'); ?></p>
        <?php if (!$schemaOk) : ?>
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
    </div>
</div>
