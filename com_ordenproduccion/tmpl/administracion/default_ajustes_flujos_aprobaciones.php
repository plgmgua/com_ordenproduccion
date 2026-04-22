<?php
/**
 * Ajustes → Flujos de aprobaciones: list or redirect to edit include.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$schemaOk = !empty($this->approvalWorkflowSchemaAvailable);
$editId   = isset($this->approvalWorkflowEditId) ? (int) $this->approvalWorkflowEditId : 0;
$rows     = isset($this->approvalWorkflowsListSummary) && is_array($this->approvalWorkflowsListSummary)
    ? $this->approvalWorkflowsListSummary
    : [];

$entityLabel = static function (string $entityType): string {
    $map = [
        'cotizacion_confirmation' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_CONFIRMATION',
        'orden_status'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_STATUS',
        'timesheet'               => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_TIMESHEET',
        'payment_proof'           => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_PAYMENT_PROOF',
        'solicitud_descuento'     => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_DESCUENTO',
        'solicitud_cotizacion'    => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_COTIZACION',
    ];
    $key = $map[$entityType] ?? 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_GENERIC';

    return Text::_($key);
};
?>
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="card-title mb-0">
            <i class="fas fa-project-diagram"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_TITLE'); ?>
        </h2>
        <?php if ($schemaOk && $editId < 1) : ?>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-users-cog"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_GRUPOS_APROBACIONES'); ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$schemaOk) : ?>
            <div class="alert alert-warning mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'); ?></div>
        <?php elseif ($editId > 0) : ?>
            <?php include __DIR__ . '/default_ajustes_flujos_aprobaciones_edit.php'; ?>
        <?php elseif ($rows === []) : ?>
            <div class="alert alert-info mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_EMPTY'); ?></div>
        <?php else : ?>
            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOWS_LIST_INTRO'); ?></p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WF_NAME'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WORKFLOW_ENTITY_READONLY'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WF_PUBLISHED'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_STEPS_COUNT'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_LIST_ACTIONS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $wf) :
                            $wid = (int) ($wf->id ?? 0);
                            $etype = (string) ($wf->entity_type ?? '');
                            $pub = !empty($wf->published);
                            $sc = (int) ($wf->steps_count ?? 0);
                            ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($wf->name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="text-muted"><?php echo htmlspecialchars($entityLabel($etype), ENT_QUOTES, 'UTF-8'); ?></span>
                                <code class="small ms-1"><?php echo htmlspecialchars($etype, ENT_QUOTES, 'UTF-8'); ?></code>
                            </td>
                            <td><?php echo $pub ? Text::_('JYES') : Text::_('JNO'); ?></td>
                            <td class="text-end"><?php echo $sc; ?></td>
                            <td class="text-end">
                                <a class="btn btn-primary btn-sm" href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones&wf_id=' . $wid); ?>">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_EDIT_WORKFLOW'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
