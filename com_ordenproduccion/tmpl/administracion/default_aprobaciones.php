<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$rows = $this->get('approvalPendingRows');
if (!is_array($rows)) {
    $rows = [];
}
$schemaOk = (bool) $this->get('approvalWorkflowSchemaAvailable');

$entityLabel = static function (string $entityType): string {
    $entityType = ApprovalWorkflowService::normalizeEntityType($entityType);
    $map        = [
        'cotizacion_confirmation' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_CONFIRMATION',
        'cotizacion_facturacion_manual' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_FACTURACION_MANUAL',
        'orden_status'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_STATUS',
        'timesheet'               => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_TIMESHEET',
        'payment_proof'           => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_PAYMENT_PROOF',
        'solicitud_descuento'     => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_DESCUENTO',
        'solicitud_cotizacion'    => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_COTIZACION',
        'orden_compra'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_COMPRA',
        'creacion_orden_trabajo'  => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_CREACION_ORDEN_TRABAJO',
        'servicios_elementos_externos' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SERVICIOS_ELEMENTOS_EXTERNOS',
    ];
    $key = $map[$entityType] ?? 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_GENERIC';

    return Text::_($key);
};

$approveAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.approveApprovalWorkflow');
$rejectAction  = Route::_('index.php?option=com_ordenproduccion&task=administracion.rejectApprovalWorkflow');
$cancelAction  = Route::_('index.php?option=com_ordenproduccion&task=administracion.cancelApprovalWorkflow');
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
                        $etype = ApprovalWorkflowService::normalizeEntityType(isset($row->entity_type) ? (string) $row->entity_type : '');
                        $eid = isset($row->entity_id) ? (int) $row->entity_id : 0;
                        $workflowTypeLabel = isset($row->workflow_pending_type_label) ? trim((string) $row->workflow_pending_type_label) : '';
                        $typeDisplay = $workflowTypeLabel !== '' ? $workflowTypeLabel : $entityLabel($etype);
                        $created = isset($row->created) ? (string) $row->created : '';
                        if ($etype === 'solicitud_descuento' || $etype === 'solicitud_cotizacion' || $etype === 'creacion_orden_trabajo') {
                            $refDisplay = (string) ($row->precotizacion_number ?? '');
                            if ($refDisplay === '') {
                                $refDisplay = (string) (int) $eid;
                            }
                        } elseif ($etype === 'servicios_elementos_externos') {
                            $refDisplay = (string) ($row->precotizacion_number ?? '');
                            if ($refDisplay === '') {
                                $refDisplay = (string) (int) $eid;
                            }
                        } elseif ($etype === 'orden_compra') {
                            $refDisplay = trim((string) ($row->orden_compra_number ?? ''));
                            if ($refDisplay === '') {
                                $refDisplay = (string) (int) $eid;
                            }
                        } elseif ($etype === ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL) {
                            $refDisplay = trim((string) ($row->quotation_number ?? ''));
                            if ($refDisplay === '') {
                                $refDisplay = $eid > 0
                                    ? ('COT-' . str_pad((string) $eid, 6, '0', STR_PAD_LEFT))
                                    : '';
                            }
                        } else {
                            $refDisplay = (string) (int) $eid;
                        }
                        $docUrl = ApprovalWorkflowService::resolvePendingApprovalDocumentUrl($row);
                        $subName = isset($row->submitter_name) ? trim((string) $row->submitter_name) : '';
                        $subUser = isset($row->submitter_username) ? trim((string) $row->submitter_username) : '';
                        if ($subUser !== '') {
                            $submitterDisplay = $subUser;
                        } elseif ($subName !== '') {
                            $submitterDisplay = $subName;
                        } else {
                            $submitterDisplay = '—';
                        }
                        $odooPartnerIdApproval = isset($row->odoo_partner_id) ? (int) $row->odoo_partner_id : 0;
                        $showOdooFinanceApproval = (
                            $odooPartnerIdApproval > 0
                            && (
                                $etype === 'solicitud_descuento'
                                || $etype === 'solicitud_cotizacion'
                                || $etype === 'creacion_orden_trabajo'
                                || $etype === 'servicios_elementos_externos'
                            )
                        );
                        ?>
                        <tr<?php echo $docUrl !== '' ? ' class="com-ordenproduccion-approval-row-link" data-approval-doc-url="' . htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8') . '" style="cursor:pointer;"' : ''; ?>>
                            <td class="text-nowrap small"><?php echo htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($typeDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($submitterDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="approval-col-doc text-nowrap"><?php if ($docUrl !== '') : ?><a href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($refDisplay, ENT_QUOTES, 'UTF-8'); ?></a><?php else : ?><?php echo htmlspecialchars($refDisplay, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></td>
                            <td style="min-width:260px;">
                                <?php if ($showOdooFinanceApproval) : ?>
                                <div class="op-appr-odoo-finance small border rounded p-2 mb-2 bg-light"
                                     role="note"
                                     data-op-odoo-partner="<?php echo (int) $odooPartnerIdApproval; ?>"
                                     data-op-odoo-done="0">
                                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_ODOO_FINANCE_LABEL'); ?>:</strong>
                                    <div class="mt-1 op-appr-odoo-finance-body text-muted">…</div>
                                </div>
                                <?php endif; ?>
                                <?php if ($etype === 'solicitud_cotizacion') : ?>
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <?php if ($docUrl !== '') : ?>
                                        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_PRE_COT'); ?>
                                        </a>
                                        <?php endif; ?>
                                        <form method="post" action="<?php echo $cancelAction; ?>" class="d-inline" onsubmit="return confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_APPROVAL_DISMISS_CONFIRM')); ?>);">
                                            <?php echo HTMLHelper::_('form.token'); ?>
                                            <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                            <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_DISMISS'); ?></button>
                                        </form>
                                    </div>
                                    <form method="post" action="<?php echo $approveAction; ?>" class="mb-0">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                        <label class="form-label small mb-0" for="approval-approve-sc-<?php echo (int) $rid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_APPROVE_NOTE'); ?></label>
                                        <textarea class="form-control form-control-sm mb-1" id="approval-approve-sc-<?php echo (int) $rid; ?>" name="comment" rows="2" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COMMENT_PLACEHOLDER'); ?>"></textarea>
                                        <button type="submit" class="btn btn-success btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_APPROVE'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo $rejectAction; ?>" class="mb-0">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                        <label class="form-label small mb-0" for="approval-reject-sc-<?php echo (int) $rid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_NOTE'); ?></label>
                                        <textarea class="form-control form-control-sm mb-1" id="approval-reject-sc-<?php echo (int) $rid; ?>" name="comment" rows="2" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_COMMENT_PLACEHOLDER'); ?>"></textarea>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_REJECT'); ?></button>
                                    </form>
                                </div>
                                <?php elseif ($etype === 'solicitud_descuento' || $etype === 'creacion_orden_trabajo' || $etype === 'servicios_elementos_externos') : ?>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <?php if ($docUrl !== '') : ?>
                                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_PRE_COT'); ?>
                                    </a>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo $cancelAction; ?>" class="d-inline" onsubmit="return confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_APPROVAL_DISMISS_CONFIRM')); ?>);">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_DISMISS'); ?></button>
                                    </form>
                                </div>
                                <?php elseif ($etype === 'orden_compra') : ?>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <?php if ($docUrl !== '') : ?>
                                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_ORDEN_COMPRA'); ?></a>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo $cancelAction; ?>" class="d-inline" onsubmit="return confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_APPROVAL_DISMISS_CONFIRM')); ?>);">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_DISMISS'); ?></button>
                                    </form>
                                </div>
                                <?php else : ?>
                                <?php if ($docUrl !== '') : ?>
                                <div class="d-flex flex-wrap gap-1 align-items-center mb-2">
                                    <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_DOCUMENT'); ?></a>
                                </div>
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
<script>
(function () {
  document.querySelectorAll('tr.com-ordenproduccion-approval-row-link').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, textarea, label, form')) {
        return;
      }
      var u = tr.getAttribute('data-approval-doc-url');
      if (u) {
        window.location.href = u;
      }
    });
  });
})();
(function () {
  var ajaxUrlTpl = <?php echo json_encode((string) Route::_('index.php?option=com_ordenproduccion&task=cliente.getCreditLimit&format=json')); ?>;
  function fillOdooFinanceBox(wrapper) {
    var pid = parseInt(wrapper.getAttribute('data-op-odoo-partner') || '0', 10);
    if (pid < 1) return;
    if (wrapper.getAttribute('data-op-odoo-done') === '1') return;

    wrapper.setAttribute('data-op-odoo-done', '1');
    var inner = wrapper.querySelector('.op-appr-odoo-finance-body');
    if (!inner) return;

    fetch(ajaxUrlTpl + '&id=' + pid)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          inner.textContent = '';
          wrapper.style.display = 'none';
          return;
        }

        var lines = [];
        var cl = null;

        if (data.credit_limit !== null && data.credit_limit !== undefined) {
          cl = parseFloat(data.credit_limit);

          if (!isNaN(cl) && cl >= 0) {
            lines.push(new Intl.NumberFormat('es-GT', {
              style: 'currency',
              currency: 'GTQ',
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }).format(cl));
          }
        }

        var ptn = data.payment_term_name ? String(data.payment_term_name).trim() : '';

        if (ptn !== '') {
          var tid = '';

          if (data.payment_term_id !== null && data.payment_term_id !== undefined && data.payment_term_id !== '') {
            var n = parseInt(data.payment_term_id, 10);

            if (!isNaN(n) && n > 0) {
              tid = ' (' + n + ')';
            }
          }

          lines.push(ptn + tid);
        }

        var isl = (data.invoice_sending_method_label !== null && data.invoice_sending_method_label !== undefined)
          ? String(data.invoice_sending_method_label).trim()
          : '';
        if (isl !== '') {
          lines.push(isl);
        }

        inner.textContent = lines.join(' · ');

        if (lines.length === 0) {
          wrapper.style.display = 'none';
        }
      })
      .catch(function () {
        inner.textContent = '';
        wrapper.style.display = 'none';
      });
  }

  document.querySelectorAll('.op-appr-odoo-finance[data-op-odoo-partner]').forEach(fillOdooFinanceBox);
})();
</script>
