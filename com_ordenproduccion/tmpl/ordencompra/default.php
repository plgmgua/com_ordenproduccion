<?php
/**
 * Orden de compra — list and detail.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Ordencompra\HtmlView $this */

$listUrl    = Route::_('index.php?option=com_ordenproduccion&view=ordencompra', false);
$schemaOk   = !empty($this->schemaOk);
$item       = isset($this->item) && is_object($this->item) ? $this->item : null;
$lines      = isset($this->lines) && is_array($this->lines) ? $this->lines : [];
$items      = isset($this->items) && is_array($this->items) ? $this->items : [];
$deleteUrl  = Route::_('index.php?option=com_ordenproduccion&task=ordencompra.delete', false);

$statusLabel = static function (string $s): string {
    $s = strtolower(trim($s));
    if ($s === 'draft') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_DRAFT');
    }
    if ($s === 'pending_approval') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_PENDING');
    }
    if ($s === 'approved') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_APPROVED');
    }
    if ($s === 'rejected') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_REJECTED');
    }
    if ($s === 'deleted') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_DELETED');
    }

    return $s;
};

$proveedorNameFromSnapshot = static function (?string $json): string {
    if ($json === null || trim($json) === '') {
        return '';
    }
    $d = json_decode($json, true);

    return is_array($d) ? trim((string) ($d['name'] ?? '')) : '';
};
?>
<div class="ordencompra-page container py-3 com-ordenproduccion-ordencompra">
    <h1 class="h4 mb-3">
        <i class="fas fa-money-bill-wave me-2"></i>
        <?php echo $item ? Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_TITLE') . ' ' . htmlspecialchars((string) ($item->number ?? ''), ENT_QUOTES, 'UTF-8') : Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LIST_TITLE'); ?>
    </h1>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_SCHEMA_MISSING'); ?></div>
    <?php elseif ($item) : ?>
        <?php
        $ocWf = strtolower((string) ($item->workflow_status ?? ''));
        $canActApproval = !empty($this->canActOnOrdenCompraApproval);
        $ocApprovalReqId = (int) ($item->approval_request_id ?? 0);
        $approveWfUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.approveApprovalWorkflow', false);
        $rejectWfUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.rejectApprovalWorkflow', false);
        $ocReturnB64  = base64_encode(Route::_('index.php?option=com_ordenproduccion&view=ordencompra&id=' . (int) ($item->id ?? 0), false));
        ?>
        <p class="mb-2">
            <a href="<?php echo htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
                <?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_BACK_LIST'); ?>
            </a>
        </p>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PRECOT'); ?></dt>
                    <dd class="col-sm-9"><?php
                        $detailPreId = (int) ($item->precotizacion_id ?? 0);
                        $detailPreNum = trim((string) ($item->precot_number ?? ''));
                        if ($detailPreNum === '' && $detailPreId > 0) {
                            $detailPreNum = 'PRE-' . $detailPreId;
                        }
                        if ($detailPreId > 0) :
                            ?><a href="#" class="precotizacion-detail-link" data-pre-id="<?php echo $detailPreId; ?>" data-pre-number="<?php echo htmlspecialchars($detailPreNum, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($detailPreNum, ENT_QUOTES, 'UTF-8'); ?></a><?php
                        else :
                            echo htmlspecialchars($detailPreNum !== '' ? $detailPreNum : '—', ENT_QUOTES, 'UTF-8');
                        endif;
                    ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PROVEEDOR'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($proveedorNameFromSnapshot(isset($item->proveedor_snapshot) ? (string) $item->proveedor_snapshot : '') ?: ('#' . (int) ($item->proveedor_id ?? 0)), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_TOTAL'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) ($item->currency ?? 'Q'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(number_format((float) ($item->total_amount ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_STATUS'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($statusLabel((string) ($item->workflow_status ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <?php if (trim((string) ($item->condiciones_entrega ?? '')) !== '') : ?>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_CONDICIONES'); ?></dt>
                    <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars((string) $item->condiciones_entrega, ENT_QUOTES, 'UTF-8')); ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if ($ocWf === 'pending_approval' && $canActApproval && $ocApprovalReqId > 0) : ?>
                <div class="mt-3 pt-3 border-top ordencompra-approval-actions">
                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVAL_ACTIONS_INTRO'); ?></p>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6">
                            <form method="post" action="<?php echo htmlspecialchars($approveWfUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mb-0">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="request_id" value="<?php echo $ocApprovalReqId; ?>">
                                <input type="hidden" name="return" value="<?php echo htmlspecialchars($ocReturnB64, ENT_QUOTES, 'UTF-8'); ?>">
                                <label class="form-label small mb-0" for="oc-approval-approve-comment"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_APPROVE_NOTE'); ?></label>
                                <textarea class="form-control form-control-sm mb-2" id="oc-approval-approve-comment" name="comment" rows="2" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_APPROVAL_COMMENT_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                                <button type="submit" class="btn btn-success btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_APPROVE'); ?></button>
                            </form>
                        </div>
                        <div class="col-12 col-md-6">
                            <form method="post" action="<?php echo htmlspecialchars($rejectWfUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mb-0"
                                  onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_REJECT_CONFIRM')); ?>);">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="request_id" value="<?php echo $ocApprovalReqId; ?>">
                                <input type="hidden" name="return" value="<?php echo htmlspecialchars($ocReturnB64, ENT_QUOTES, 'UTF-8'); ?>">
                                <label class="form-label small mb-0" for="oc-approval-reject-comment"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_NOTE'); ?></label>
                                <textarea class="form-control form-control-sm mb-2" id="oc-approval-reject-comment" name="comment" rows="2" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_APPROVAL_REJECT_COMMENT_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                                <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_BTN_REJECT'); ?></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($ocWf === 'approved') :
                    $ocPdfHref = Route::_('index.php?option=com_ordenproduccion&task=ordencompra.pdf&id=' . (int) ($item->id ?? 0) . '&tmpl=component&' . Session::getFormToken() . '=1', false);
                ?>
                <p class="mb-0 mt-2">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($ocPdfHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DOWNLOAD_APPROVED_PDF'); ?></a>
                </p>
                <?php endif; ?>
                <?php if ($ocWf === 'pending_approval' || $ocWf === 'draft' || $ocWf === 'rejected' || $ocWf === 'approved') : ?>
                <form method="post" action="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mt-3"
                      onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE_CONFIRM')); ?>);">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="id" value="<?php echo (int) ($item->id ?? 0); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE'); ?></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_PRECOT_LINE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_QTY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_DESC'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_PUP'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $ln) : ?>
                    <tr>
                        <td class="text-muted small"><?php echo (int) ($ln->precotizacion_line_id ?? 0); ?></td>
                        <td class="text-end"><?php echo (int) ($ln->quantity ?? 0); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string) ($ln->descripcion ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars(number_format((float) ($ln->vendor_unit_price ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars(number_format((float) ($ln->line_total ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $vqUrl  = isset($this->vendorQuoteUrl) ? trim((string) $this->vendorQuoteUrl) : '';
        $vqKind = isset($this->vendorQuoteKind) ? strtolower(trim((string) $this->vendorQuoteKind)) : '';
        ?>
        <?php if ($vqUrl !== '' && ($vqKind === 'pdf' || $vqKind === 'image')) : ?>
        <section class="ordencompra-vendor-quote-preview mt-4 pt-3 border-top" aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_VENDOR_PREVIEW_TITLE'), ENT_QUOTES, 'UTF-8'); ?>">
            <h2 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_VENDOR_PREVIEW_TITLE'); ?></h2>
            <?php if ($vqKind === 'pdf') : ?>
            <div class="border rounded overflow-hidden bg-light shadow-sm">
                <iframe src="<?php echo htmlspecialchars($vqUrl, ENT_QUOTES, 'UTF-8'); ?>#toolbar=1"
                        class="w-100 border-0 d-block"
                        style="min-height: 60vh;"
                        title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_VENDOR_PREVIEW_TITLE'), ENT_QUOTES, 'UTF-8'); ?>"></iframe>
            </div>
            <?php else : ?>
            <div class="text-center bg-light rounded border p-2">
                <img src="<?php echo htmlspecialchars($vqUrl, ENT_QUOTES, 'UTF-8'); ?>"
                     alt=""
                     class="img-fluid rounded shadow-sm"
                     style="max-height: 85vh; width: auto;">
            </div>
            <?php endif; ?>
            <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DETAIL_VENDOR_QUOTE_HINT'); ?></p>
        </section>
        <?php elseif ((int) ($item->vendor_quote_event_id ?? 0) > 0) : ?>
        <p class="small text-muted mt-4 pt-3 border-top mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DETAIL_VENDOR_QUOTE_NONE'); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_NUMBER'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PRECOT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PROVEEDOR'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_TOTAL'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_STATUS'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []) : ?>
                    <tr><td colspan="6" class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_EMPTY'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $row) : ?>
                        <?php
                        $oid    = (int) ($row->id ?? 0);
                        $detail = Route::_('index.php?option=com_ordenproduccion&view=ordencompra&id=' . $oid, false);
                        $pname  = $proveedorNameFromSnapshot(isset($row->proveedor_snapshot) ? (string) $row->proveedor_snapshot : '');
                        if ($pname === '') {
                            $pname = '#' . (int) ($row->proveedor_id ?? 0);
                        }
                        $listPreId = (int) ($row->precotizacion_id ?? 0);
                        $listPreNum = trim((string) ($row->precot_number ?? ''));
                        if ($listPreNum === '' && $listPreId > 0) {
                            $listPreNum = 'PRE-' . $listPreId;
                        }
                        ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($row->number ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php
                        if ($listPreId > 0) :
                            ?><a href="#" class="precotizacion-detail-link" data-pre-id="<?php echo $listPreId; ?>" data-pre-number="<?php echo htmlspecialchars($listPreNum, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($listPreNum, ENT_QUOTES, 'UTF-8'); ?></a><?php
                        else :
                            echo htmlspecialchars($listPreNum !== '' ? $listPreNum : '—', ENT_QUOTES, 'UTF-8');
                        endif;
                        ?></td>
                        <td><?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars((string) ($row->currency ?? 'Q'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(number_format((float) ($row->total_amount ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($statusLabel((string) ($row->workflow_status ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-nowrap">
                            <?php $rwf = strtolower((string) ($row->workflow_status ?? '')); ?>
                            <div class="d-inline-flex flex-wrap align-items-center gap-1">
                                <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_VIEW'); ?></a>
                                <?php if ($rwf === 'pending_approval' || $rwf === 'draft' || $rwf === 'rejected' || $rwf === 'approved') : ?>
                                <form method="post" action="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>" class="d-inline m-0"
                                      onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE_CONFIRM')); ?>);">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="id" value="<?php echo $oid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('JACTION_DELETE'); ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($this->pagination) && $this->pagination->total > 0) : ?>
        <div class="ordencompra-pagination row mt-3">
            <div class="col-12">
                <?php if ($this->pagination->pagesTotal > 1) : ?>
                <nav aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_PAGINATION'); ?>">
                    <?php echo $this->pagination->getPagesLinks(); ?>
                </nav>
                <?php endif; ?>
                <div class="pagination-info text-center mt-2">
                    <?php echo $this->pagination->getResultsCounter(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php if ($schemaOk) : ?>
<!-- Modal: Pre-Cotización details (same as cotización view — ajax.getPrecotizacionDetails) -->
<div class="modal fade" id="precotizacionDetailModal" tabindex="-1" aria-labelledby="precotizacionDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="precotizacionDetailModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(Text::_('JLIB_HTML_BEHAVIOR_CLOSE'), ENT_QUOTES, 'UTF-8'); ?>"></button>
            </div>
            <div class="modal-body p-0">
                <div id="precotizacionDetailContent" class="overflow-auto" style="max-height: 70vh;"></div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var precotizacionDetailBase = <?php echo json_encode(Uri::root()); ?>;
    var precotizacionDetailToken = <?php echo json_encode(Session::getFormToken() . '=1'); ?>;
    var msgLoading = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_LOADING')); ?>;
    var msgNotFound = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND')); ?>;
    var msgLoadError = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_ERROR_LOADING_QUOTATION')); ?>;
    document.addEventListener('click', function(e) {
        var link = e.target && e.target.closest && e.target.closest('.precotizacion-detail-link');
        if (!link) return;
        e.preventDefault();
        var preId = link.getAttribute('data-pre-id');
        var preNumber = link.getAttribute('data-pre-number') || ('PRE-' + preId);
        if (!preId) return;
        var modal = document.getElementById('precotizacionDetailModal');
        var contentEl = document.getElementById('precotizacionDetailContent');
        var titleEl = document.getElementById('precotizacionDetailModalLabel');
        if (!modal || !contentEl) return;
        if (titleEl) titleEl.textContent = preNumber;
        contentEl.innerHTML = '<div class="p-3 text-muted text-center"><span class="spinner-border spinner-border-sm me-2"></span>' + msgLoading + '</div>';
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } else {
            modal.classList.add('show');
            modal.style.display = 'block';
        }
        var url = precotizacionDetailBase + 'index.php?option=com_ordenproduccion&task=ajax.getPrecotizacionDetails&format=raw&id=' + encodeURIComponent(preId) + '&' + precotizacionDetailToken;
        fetch(url).then(function(r) { return r.text(); }).then(function(html) {
            contentEl.innerHTML = html || '<p class="p-3 text-muted">' + msgNotFound + '</p>';
        }).catch(function() {
            contentEl.innerHTML = '<p class="p-3 text-danger">' + msgLoadError + '</p>';
        });
    });
})();
</script>
<?php endif; ?>
