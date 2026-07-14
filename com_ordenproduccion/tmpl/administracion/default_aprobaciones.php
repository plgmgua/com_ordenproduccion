<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\Mt940PaymentMatchLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Grimpsa\Component\Ordenproduccion\Site\Service\Mt940PaymentMatchService;
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
$mt940SearchUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.searchMt940ForPaymentApproval&format=json');
$mt940PaymentVerifyEnabled = Mt940PaymentMatchLogHelper::isMt940VerificationEnabled();
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
                        } elseif ($etype === 'payment_proof') {
                            $refDisplay = $eid > 0 ? ('PA-' . str_pad((string) $eid, 5, '0', STR_PAD_LEFT)) : '';
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
                        $uiMode = isset($row->approval_pending_ui_mode) ? (string) $row->approval_pending_ui_mode : 'approver';
                        $isSubmitterOnly = ($uiMode === 'submitter');
                        ?>
                        <tr<?php echo $docUrl !== '' ? ' class="com-ordenproduccion-approval-row-link" data-approval-doc-url="' . htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8') . '" style="cursor:pointer;"' : ''; ?>>
                            <td class="text-nowrap small"><?php echo htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($typeDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($submitterDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="approval-col-doc text-nowrap"><?php if ($docUrl !== '') : ?><a href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($refDisplay, ENT_QUOTES, 'UTF-8'); ?></a><?php else : ?><?php echo htmlspecialchars($refDisplay, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></td>
                            <td style="min-width:260px;">
                                <?php if (!empty($isSubmitterOnly)) : ?>
                                <div class="small text-muted mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_SUBMITTER_AWAITING'); ?></div>
                                <?php if ($docUrl !== '') : ?>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_DOCUMENT'); ?></a>
                                <?php endif; ?>
                                <?php else : ?>
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
                                <?php elseif ($etype === 'payment_proof' && $mt940PaymentVerifyEnabled) : ?>
                                <?php
                                $ppMeta = Mt940PaymentMatchService::decodeVerificationMetadata(isset($row->metadata) ? (string) $row->metadata : '');
                                $ppLines = ($ppMeta !== null && !empty($ppMeta['lines']) && is_array($ppMeta['lines'])) ? $ppMeta['lines'] : [];
                                ?>
                                <div class="payment-proof-mt940-approval" data-request-id="<?php echo (int) $rid; ?>">
                                    <?php if ($ppLines !== []) : ?>
                                    <div class="table-responsive mb-2">
                                        <table class="table table-sm table-bordered mb-0 small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_PP_MT940_COL_COMPROBANTE'); ?></th>
                                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_PP_MT940_COL_MT940'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ppLines as $pl) :
                                                    if (!is_array($pl)) {
                                                        continue;
                                                    }
                                                    $mt = is_array($pl['mt940'] ?? null) ? $pl['mt940'] : [];
                                                    $amtFmt = number_format((float) ($pl['amount'] ?? 0), 2);
                                                    $mtAmtFmt = number_format((float) ($mt['amount'] ?? 0), 2);
                                                    $lineId = (int) ($pl['line_id'] ?? 0);
                                                    ?>
                                                <tr data-line-id="<?php echo $lineId; ?>">
                                                    <td>
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_NUMBER'); ?>:</strong> <?php echo htmlspecialchars((string) ($pl['document_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_DATE'); ?>:</strong> <?php echo htmlspecialchars((string) ($pl['document_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT'); ?>:</strong> Q <?php echo htmlspecialchars($amtFmt, ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_PP_MT940_ACCOUNT'); ?>:</strong> <?php echo htmlspecialchars((string) ($pl['account_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </td>
                                                    <td class="mt940-suggestion-cell">
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_PP_MT940_REFERENCE'); ?>:</strong> <span class="mt940-ref"><?php echo htmlspecialchars((string) ($mt['reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_DATE'); ?>:</strong> <span class="mt940-date"><?php echo htmlspecialchars((string) ($mt['transaction_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                                        <div><strong><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT'); ?>:</strong> Q <span class="mt940-amount"><?php echo htmlspecialchars($mtAmtFmt, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                                        <div class="text-muted mt940-desc"><?php echo htmlspecialchars((string) ($mt['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <input type="hidden" class="mt940-tx-id" value="<?php echo (int) ($pl['mt940_transaction_id'] ?? 0); ?>" />
                                                        <input type="hidden" class="mt940-bank-account-id" value="<?php echo (int) ($pl['bank_account_id'] ?? 0); ?>" />
                                                        <input type="hidden" class="mt940-line-amount" value="<?php echo htmlspecialchars((string) ($pl['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" />
                                                        <input type="hidden" class="mt940-line-date" value="<?php echo htmlspecialchars((string) ($pl['document_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                                                        <button type="button" class="btn btn-outline-secondary btn-sm mt-1 pp-mt940-search-btn"><?php echo Text::_('COM_ORDENPRODUCCION_PP_MT940_SEARCH_BTN'); ?></button>
                                                        <div class="pp-mt940-search-results mt-1 d-none"></div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else : ?>
                                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_PP_MT940_NO_SUGGESTION'); ?></p>
                                    <?php endif; ?>
                                    <?php if ($docUrl !== '') : ?>
                                    <div class="d-flex flex-wrap gap-1 align-items-center mb-2">
                                        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_LINK_OPEN_DOCUMENT'); ?></a>
                                    </div>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo $approveAction; ?>" class="mb-2 pp-mt940-approve-form">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                        <input type="hidden" name="mt940_manual_override" class="pp-mt940-manual-override" value="" />
                                        <label class="form-label small mb-0" for="approval-approve-pp-<?php echo (int) $rid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_APPROVE_NOTE'); ?></label>
                                        <textarea class="form-control form-control-sm mb-1" id="approval-approve-pp-<?php echo (int) $rid; ?>" name="comment" rows="2" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COMMENT_PLACEHOLDER'); ?>"></textarea>
                                        <button type="submit" class="btn btn-success btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_APPROVE'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo $rejectAction; ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="request_id" value="<?php echo (int) $rid; ?>" />
                                        <label class="form-label small mb-0" for="approval-reject-pp-<?php echo (int) $rid; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_NOTE'); ?></label>
                                        <textarea class="form-control form-control-sm mb-1" id="approval-reject-pp-<?php echo (int) $rid; ?>" name="comment" rows="2" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_COMMENT_PLACEHOLDER'); ?>"></textarea>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_REJECT'); ?></button>
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
(function () {
  var searchUrl = <?php echo json_encode((string) $mt940SearchUrl); ?>;

  function collectOverrides(container) {
    var lines = [];
    container.querySelectorAll('tr[data-line-id]').forEach(function (tr) {
      var lineId = parseInt(tr.getAttribute('data-line-id') || '0', 10);
      var txInput = tr.querySelector('.mt940-tx-id');
      var txId = txInput ? parseInt(txInput.value || '0', 10) : 0;
      if (lineId > 0 && txId > 0) {
        lines.push({ line_id: lineId, mt940_transaction_id: txId });
      }
    });
    return JSON.stringify({ lines: lines });
  }

  document.querySelectorAll('.payment-proof-mt940-approval').forEach(function (wrap) {
    var approveForm = wrap.querySelector('.pp-mt940-approve-form');
    if (approveForm) {
      approveForm.addEventListener('submit', function () {
        var hidden = approveForm.querySelector('.pp-mt940-manual-override');
        if (hidden) {
          hidden.value = collectOverrides(wrap);
        }
      });
    }

    wrap.querySelectorAll('.pp-mt940-search-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var cell = btn.closest('.mt940-suggestion-cell');
        if (!cell) return;
        var bankId = parseInt((cell.querySelector('.mt940-bank-account-id') || {}).value || '0', 10);
        var date = (cell.querySelector('.mt940-line-date') || {}).value || '';
        var amount = parseFloat((cell.querySelector('.mt940-line-amount') || {}).value || '0');
        var results = cell.querySelector('.pp-mt940-search-results');
        if (!results) return;
        results.classList.remove('d-none');
        results.innerHTML = '…';
        var qs = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?')
          + 'bank_account_id=' + encodeURIComponent(String(bankId))
          + '&date=' + encodeURIComponent(date)
          + '&amount=' + encodeURIComponent(String(amount));
        fetch(qs)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data || !data.success || !data.data || !data.data.rows || !data.data.rows.length) {
              results.innerHTML = '<span class="text-muted">Sin resultados</span>';
              return;
            }
            var html = '<ul class="list-unstyled mb-0">';
            data.data.rows.forEach(function (row) {
              html += '<li class="mb-1"><button type="button" class="btn btn-link btn-sm p-0 pp-mt940-pick" data-tx-id="' + row.id + '" data-ref="' + (row.reference || '') + '" data-date="' + (row.transaction_date || '') + '" data-amount="' + row.amount + '" data-desc="' + (row.description || '').replace(/"/g, '&quot;') + '">'
                + (row.reference || '—') + ' · ' + (row.transaction_date || '') + ' · Q ' + row.amount
                + '</button></li>';
            });
            html += '</ul>';
            results.innerHTML = html;
            results.querySelectorAll('.pp-mt940-pick').forEach(function (pick) {
              pick.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var txId = parseInt(pick.getAttribute('data-tx-id') || '0', 10);
                var ref = pick.getAttribute('data-ref') || '';
                var dt = pick.getAttribute('data-date') || '';
                var amt = pick.getAttribute('data-amount') || '';
                var desc = pick.getAttribute('data-desc') || '';
                var txInput = cell.querySelector('.mt940-tx-id');
                if (txInput) txInput.value = String(txId);
                var refEl = cell.querySelector('.mt940-ref');
                if (refEl) refEl.textContent = ref;
                var dateEl = cell.querySelector('.mt940-date');
                if (dateEl) dateEl.textContent = dt;
                var amtEl = cell.querySelector('.mt940-amount');
                if (amtEl) amtEl.textContent = amt;
                var descEl = cell.querySelector('.mt940-desc');
                if (descEl) descEl.textContent = desc;
                results.classList.add('d-none');
              });
            });
          })
          .catch(function () {
            results.innerHTML = '<span class="text-danger">Error</span>';
          });
      });
    });
  });
})();
</script>
