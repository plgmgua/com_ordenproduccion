<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$rows = $this->get('approvalPendingRows');
if (!is_array($rows)) {
    $rows = [];
}
$schemaOk = (bool) $this->get('approvalWorkflowSchemaAvailable');

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

$approveAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.approveApprovalWorkflow');
$rejectAction  = Route::_('index.php?option=com_ordenproduccion&task=administracion.rejectApprovalWorkflow');
?>

<div class="approval-workflow-section" style="background:#fff;padding:25px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
    <h2 class="h4 mb-3">
        <i class="fas fa-clipboard-check me-2"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_TAB_HEADING'); ?>
    </h2>
    <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_TAB_INTRO'); ?></p>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'); ?></div>
    <?php elseif ($rows === []) : ?>
        <div class="alert alert-info mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_EMPTY'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle com-ordenproduccion-approval-pending-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-nowrap"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COL_CREATED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COL_TYPE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COL_SUBMITTER'); ?></th>
                        <th scope="col" class="approval-col-doc"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COL_DOC'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COL_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $rid = isset($row->id) ? (int) $row->id : 0;
                        $etype = isset($row->entity_type) ? (string) $row->entity_type : '';
                        $eid = isset($row->entity_id) ? (int) $row->entity_id : 0;
                        $created = isset($row->created) ? (string) $row->created : '';
                        if ($etype === 'solicitud_descuento' || $etype === 'solicitud_cotizacion') {
                            $refDisplay = (string) ($row->precotizacion_number ?? '');
                            if ($refDisplay === '') {
                                $refDisplay = (string) (int) $eid;
                            }
                        } elseif ($etype === 'orden_compra') {
                            $refDisplay = trim((string) ($row->orden_compra_number ?? ''));
                            if ($refDisplay === '') {
                                $refDisplay = (string) (int) $eid;
                            }
                        } else {
                            $refDisplay = (string) (int) $eid;
                        }
                        $precotDocUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . (int) $eid, false);
                        $subName = isset($row->submitter_name) ? trim((string) $row->submitter_name) : '';
                        $subUser = isset($row->submitter_username) ? trim((string) $row->submitter_username) : '';
                        if ($subUser !== '') {
                            $submitterDisplay = $subUser;
                        } elseif ($subName !== '') {
                            $submitterDisplay = $subName;
                        } else {
                            $submitterDisplay = '—';
                        }
                        ?>
                        <tr>
                            <td class="text-nowrap small"><?php echo htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($entityLabel($etype), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($submitterDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="approval-col-doc text-nowrap"><?php echo htmlspecialchars($refDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="min-width:220px;">
                                <?php if ($etype === 'solicitud_descuento' || $etype === 'solicitud_cotizacion') : ?>
                                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($precotDocUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_PRE_COT'); ?>
                                </a>
                                <?php else : ?>
                                <?php if ($etype === 'orden_compra') :
                                    $ocOpenUrl = Route::_('index.php?option=com_ordenproduccion&view=ordencompra&id=' . (int) $eid, false);
                                    ?>
                                <p class="mb-2"><a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($ocOpenUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_ORDEN_COMPRA'); ?></a></p>
                                <?php endif; ?>
                                <form method="post" action="<?php echo $approveAction; ?>" class="mb-2">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                    <label class="form-label small mb-0" for="approval-approve-c-<?php echo (int) $rid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_APPROVE_NOTE'); ?></label>
                                    <textarea class="form-control form-control-sm mb-1" id="approval-approve-c-<?php echo (int) $rid; ?>" name="comment" rows="2" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COMMENT_PLACEHOLDER'); ?>"></textarea>
                                    <button type="submit" class="btn btn-success btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_APPROVE'); ?></button>
                                </form>
                                <form method="post" action="<?php echo $rejectAction; ?>">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                    <label class="form-label small mb-0" for="approval-reject-c-<?php echo (int) $rid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_NOTE'); ?></label>
                                    <textarea class="form-control form-control-sm mb-1" id="approval-reject-c-<?php echo (int) $rid; ?>" name="comment" rows="2" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_COMMENT_PLACEHOLDER'); ?>"></textarea>
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_REJECT'); ?></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<style>
.com-ordenproduccion-approval-pending-table td.approval-col-doc,
.com-ordenproduccion-approval-pending-table th.approval-col-doc {
    white-space: nowrap;
    max-width: 1%;
}
</style>
