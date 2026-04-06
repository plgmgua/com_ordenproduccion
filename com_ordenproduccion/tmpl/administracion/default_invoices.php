<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Grimpsa\Component\Ordenproduccion\Site\Helper\FelInvoiceHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceListHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel;

// Get invoices data from view (get() ensures value when layout data is used)
$invoices = $this->get('invoices');
if (!is_array($invoices)) {
    $invoices = isset($this->invoices) && is_array($this->invoices) ? $this->invoices : [];
}
$pagination = $this->get('invoicesPagination');
if ($pagination === null && isset($this->invoicesPagination)) {
    $pagination = $this->invoicesPagination;
}
$importReport = Factory::getApplication()->getSession()->get('com_ordenproduccion.import_report', null);
if ($importReport !== null) {
    Factory::getApplication()->getSession()->set('com_ordenproduccion.import_report', null);
}

$invoicesSubtab = $this->get('invoicesSubtab');
if ($invoicesSubtab !== 'lista' && $invoicesSubtab !== 'match' && $invoicesSubtab !== 'cola') {
    $invoicesSubtab = 'lista';
}
$matchTableOk = (bool) $this->get('invoiceOrdenMatchTableAvailable');
$matchStatusFilter = (string) $this->get('invoiceOrdenMatchStatusFilter');
$matchClientFilter = (string) $this->get('invoiceOrdenMatchClientFilter');
$matchClientOptions = $this->get('invoiceOrdenMatchClientOptions');
if (!is_array($matchClientOptions)) {
    $matchClientOptions = [];
}
$canAccessInvoiceMatchSubtab = (bool) $this->get('canAccessInvoiceMatchSubtab');
$matchGrouped = $this->get('invoiceOrdenMatchGrouped');
if (!is_array($matchGrouped)) {
    $matchGrouped = [];
}
$dropdownOpts = $this->get('invoiceOrdenMatchDropdownOptions');
if (!is_array($dropdownOpts)) {
    $dropdownOpts = [];
}
$matchFelInvoiceTotal = (int) $this->get('invoiceOrdenMatchFelInvoiceTotal');
$listaUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices&invoices_subtab=lista');
$matchUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices&invoices_subtab=match');
$colaUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices&invoices_subtab=cola');
$invoiceFelQueueRows = $this->get('invoiceFelQueueRows');
if (!is_array($invoiceFelQueueRows)) {
    $invoiceFelQueueRows = isset($this->invoiceFelQueueRows) && is_array($this->invoiceFelQueueRows) ? $this->invoiceFelQueueRows : [];
}
$invoiceFelQueuePagination = $this->get('invoiceFelQueuePagination');
if ($invoiceFelQueuePagination === null && isset($this->invoiceFelQueuePagination)) {
    $invoiceFelQueuePagination = $this->invoiceFelQueuePagination;
}
$matchClientHidden = htmlspecialchars($matchClientFilter, ENT_QUOTES, 'UTF-8');
$matchStatusHidden = htmlspecialchars($matchStatusFilter, ENT_QUOTES, 'UTF-8');
?>

<style>
.invoices-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.invoices-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.invoices-header h2 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.btn-create-invoice {
    padding: 10px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.3s;
}

.btn-create-invoice:hover {
    background: #5568d3;
    color: white;
    text-decoration: none;
}

.search-filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-filter-bar input,
.search-filter-bar select {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    font-size: 14px;
}

.search-filter-bar button {
    padding: 8px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

.search-filter-bar button:hover {
    background: #5568d3;
}

.import-xml-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.import-xml-bar .form-control {
    padding: 8px 12px;
    font-size: 14px;
}

.invoices-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.invoices-table thead {
    background: #f8f9fa;
}

.invoices-table th {
    padding: 6px 8px;
    text-align: left;
    font-weight: bold;
    font-size: 0.75rem;
    color: #666;
    border-bottom: 2px solid #dee2e6;
}

.invoices-table td {
    padding: 6px 8px;
    font-size: 0.8125rem;
    border-bottom: 1px solid #dee2e6;
}

.invoices-table tbody tr {
    cursor: pointer;
    transition: background 0.2s;
}

.invoices-table tbody tr:hover {
    background: #f8f9fa;
}

.invoice-number,
.invoice-serie-numero {
    font-weight: bold;
    color: #667eea;
}

.invoice-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.status-draft { background: #e3f2fd; color: #1976d2; }
.status-sent { background: #fff3e0; color: #f57c00; }
.status-paid { background: #e8f5e9; color: #388e3c; }
.status-cancelled { background: #ffebee; color: #c62828; }

.invoice-amount {
    font-size: 0.8125rem;
    font-weight: bold;
    color: #28a745;
    text-align: right;
}

.invoice-fel-file-link {
    line-height: 1;
    vertical-align: middle;
}
.invoice-fel-file-link .fa-file-pdf {
    color: #c0392b;
}
.invoice-fel-file-link .fa-file-code {
    color: #2c3e50;
}
.invoice-fel-file-link:hover .fa-file-pdf,
.invoice-fel-file-link:hover .fa-file-code {
    opacity: 0.85;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state p {
    font-size: 18px;
    margin-bottom: 20px;
}

.invoices-pagination-wrapper {
    margin-top: 20px;
    padding: 15px;
    text-align: center;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}
.invoices-pagination-info {
    margin-bottom: 10px;
    font-size: 0.875rem;
    color: #666;
}
.invoices-pagination-links .pagination {
    margin: 0;
    flex-wrap: wrap;
    justify-content: center;
}
.invoices-pagination-links .page-link {
    padding: 6px 12px;
}
/* Ensure pagination block is visible (avoid template overrides hiding it) */
.invoices-pagination-wrapper {
    display: block !important;
    visibility: visible !important;
    margin-top: 1rem;
    padding: 1rem 0;
    border-top: 1px solid #dee2e6;
}
.invoices-cliente-autocomplete { display: inline-block; min-width: 180px; }
.invoices-cliente-suggestions { list-style: none; margin: 2px 0 0; padding: 0; border: 1px solid #dee2e6; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: #fff; }
.invoices-cliente-suggestions .list-group-item { cursor: pointer; padding: 8px 12px; border: none; border-bottom: 1px solid #eee; }
.invoices-cliente-suggestions .list-group-item:last-child { border-bottom: none; }
.invoices-cliente-suggestions .list-group-item:hover { background-color: #f0f0f0; }
.invoices-subtabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.invoices-subtabs a {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border: 1px solid #dee2e6;
    color: #555;
    background: #fff;
}
.invoices-subtabs a:hover { color: #667eea; border-color: #667eea; }
.invoices-subtabs a.active { background: #667eea; color: #fff; border-color: #667eea; }
.match-reasons { font-size: 0.75rem; color: #666; }
.match-client-group { border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 1.25rem; overflow: hidden; }
.match-client-group-header { background: #f1f3f5; padding: 10px 14px; font-weight: 600; border-bottom: 1px solid #dee2e6; }
.match-invoice-block { padding: 14px 16px; border-bottom: 1px solid #eee; }
.match-invoice-block:last-child { border-bottom: none; }
.invoice-lines-desc { font-size: 0.8125rem; color: #444; max-width: 42rem; word-break: break-word; }

/* Cola de facturas: compact type + buttons to reduce horizontal scroll */
.invoice-fel-queue-table {
    font-size: 0.6875rem;
    table-layout: auto;
}
.invoice-fel-queue-table thead th {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.3rem 0.4rem;
    white-space: nowrap;
    vertical-align: middle;
}
.invoice-fel-queue-table tbody td {
    padding: 0.3rem 0.4rem;
    vertical-align: middle;
    line-height: 1.25;
}
.invoice-fel-queue-table tbody td:nth-child(2) {
    max-width: 9rem;
    word-break: break-word;
}
.invoice-fel-queue-table .btn {
    font-size: 0.625rem;
    padding: 0.15rem 0.4rem;
    line-height: 1.2;
    border-radius: 0.2rem;
}
.invoice-fel-queue-table .btn + .btn {
    margin-left: 0.15rem;
}
.invoice-tipo-badge { font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; display: inline-block; white-space: nowrap; }
.invoice-tipo-valid { background: #e7f5ee; color: #1e6f4a; }
.invoice-tipo-mockup { background: #fff4e6; color: #b35900; }
tr.invoice-row-mockup { background: #fffbf5; }
</style>

<div class="invoices-section">
    <div class="invoices-header">
        <h2>
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_TITLE'); ?>
        </h2>
    </div>

    <div class="invoices-subtabs" role="tablist">
        <a href="<?php echo $listaUrl; ?>" class="<?php echo $invoicesSubtab === 'lista' ? 'active' : ''; ?>">
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_SUBTAB_LISTA'); ?>
        </a>
        <a href="<?php echo $colaUrl; ?>" class="<?php echo $invoicesSubtab === 'cola' ? 'active' : ''; ?>">
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_SUBTAB_QUEUE'); ?>
        </a>
        <?php if ($canAccessInvoiceMatchSubtab): ?>
        <a href="<?php echo $matchUrl; ?>" class="<?php echo $invoicesSubtab === 'match' ? 'active' : ''; ?>">
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_SUBTAB_MATCH'); ?>
        </a>
        <?php endif; ?>
    </div>

    <?php if ($invoicesSubtab === 'cola'): ?>
    <?php $felProcessQueueUrl = Route::_('index.php?option=com_ordenproduccion&task=invoice.processFelIssuance&format=json', false); ?>

    <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_INTRO'); ?></p>

    <?php if (empty($invoiceFelQueueRows)) : ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_EMPTY'); ?></p>
        </div>
    <?php else : ?>
        <form id="fel-queue-token-form" class="d-none" aria-hidden="true"><?php echo HTMLHelper::_('form.token'); ?></form>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle invoice-fel-queue-table">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_QUOTATION'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_CLIENT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_INVOICE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_AMOUNT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_STATUS'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_SCHEDULED'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceFelQueueRows as $qr) :
                        $qnum = trim((string) ($qr->quotation_number ?? ''));
                        if ($qnum === '') {
                            $qnum = 'COT-' . (int) ($qr->quotation_id ?? 0);
                        }
                        $st = (string) ($qr->fel_issue_status ?? '');
                        $stLabel = $st;
                        if ($st === 'scheduled') {
                            $stLabel = Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_STATUS_SCHEDULED');
                        } elseif ($st === 'pending') {
                            $stLabel = Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_STATUS_PENDING_SHORT');
                        } elseif ($st === 'processing') {
                            $stLabel = Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_STATUS_PROCESSING');
                        }
                        $sched = '';
                        if (!empty($qr->fel_scheduled_at)) {
                            $sched = HTMLHelper::_('date', $qr->fel_scheduled_at, Text::_('DATE_FORMAT_LC2'));
                        }
                        $canProcessNow = ($st === 'scheduled' || $st === 'pending');
                        ?>
                    <tr>
                        <td><?php echo htmlspecialchars($qnum); ?></td>
                        <td><?php echo htmlspecialchars((string) ($qr->client_name ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($qr->client_nit ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($qr->invoice_number ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float) ($qr->invoice_amount ?? 0), 2)); ?></td>
                        <td><?php echo htmlspecialchars($stLabel); ?></td>
                        <td><?php echo htmlspecialchars($sched !== '' ? $sched : '—'); ?></td>
                        <td class="text-nowrap">
                            <?php if (!empty($qr->quotation_id)) : ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $qr->quotation_id); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_QUOTE'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($qr->id)) : ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . (int) $qr->id); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_INVOICE'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($qr->id) && $canProcessNow) : ?>
                            <button type="button" class="btn btn-sm btn-primary fel-queue-process-now" data-invoice-id="<?php echo (int) $qr->id; ?>">
                                <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_PROCESS_NOW'); ?>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($invoiceFelQueuePagination && $invoiceFelQueuePagination->pagesTotal > 1) : ?>
            <div class="com-content-pagination"><?php echo $invoiceFelQueuePagination->getPagesLinks(); ?></div>
        <?php endif; ?>
        <script>
        (function() {
            var url = <?php echo json_encode($felProcessQueueUrl); ?>;
            var msgErr = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_PROCESS_ERROR')); ?>;
            document.querySelectorAll('.fel-queue-process-now').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = parseInt(btn.getAttribute('data-invoice-id') || '0', 10);
                    if (id < 1) {
                        return;
                    }
                    var f = document.getElementById('fel-queue-token-form');
                    var fd = f ? new FormData(f) : new FormData();
                    fd.append('invoice_id', String(id));
                    fd.append('force', '1');
                    btn.disabled = true;
                    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data && data.success) {
                                window.location.reload();
                            } else {
                                window.alert((data && data.message) ? data.message : msgErr);
                                btn.disabled = false;
                            }
                        })
                        .catch(function() {
                            window.alert(msgErr);
                            btn.disabled = false;
                        });
                });
            });
        })();
        </script>
    <?php endif; ?>

    <?php elseif ($invoicesSubtab === 'match'): ?>

    <?php if (!$matchTableOk): ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_TABLE_MISSING'); ?></div>
    <?php else: ?>

    <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_INTRO'); ?></p>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.analyzeInvoiceOrdenMatches'); ?>" class="d-inline">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="match_status" value="<?php echo $matchStatusHidden; ?>" />
            <input type="hidden" name="match_client" value="<?php echo $matchClientHidden; ?>" />
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-sync-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_RUN_ANALYSIS'); ?>
            </button>
        </form>
        <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.approveAllInvoiceOrdenMatchesHighScore'); ?>" class="d-inline" onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_APPROVE_HIGH_SCORE_CONFIRM')); ?>);">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="match_status" value="<?php echo $matchStatusHidden; ?>" />
            <input type="hidden" name="match_client" value="<?php echo $matchClientHidden; ?>" />
            <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check-double"></i> <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_APPROVE_HIGH_SCORE'); ?>
            </button>
        </form>
    </div>

    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion'); ?>" class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="invoices" />
        <input type="hidden" name="invoices_subtab" value="match" />
        <label for="match_status" class="mb-0 small"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_FILTER_STATUS'); ?></label>
        <select name="match_status" id="match_status" class="form-control form-control-sm" style="max-width: 200px;" onchange="this.form.submit()">
            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_STATUS_ALL'); ?></option>
            <option value="pending"<?php echo $matchStatusFilter === 'pending' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_STATUS_PENDING'); ?></option>
            <option value="approved"<?php echo $matchStatusFilter === 'approved' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_STATUS_APPROVED'); ?></option>
            <option value="rejected"<?php echo $matchStatusFilter === 'rejected' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_STATUS_REJECTED'); ?></option>
        </select>
        <label for="match_client" class="mb-0 small"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_FILTER_CLIENT'); ?></label>
        <select name="match_client" id="match_client" class="form-control form-control-sm" style="max-width: 320px;" onchange="this.form.submit()">
            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_FILTER_CLIENT_ALL'); ?></option>
            <?php foreach ($matchClientOptions as $mo):
                $mv = (string) ($mo['value'] ?? '');
                $ml = (string) ($mo['label'] ?? '');
                if ($mv === '') {
                    continue;
                }
                ?>
            <option value="<?php echo htmlspecialchars($mv, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $matchClientFilter === $mv ? ' selected' : ''; ?>><?php echo htmlspecialchars($ml, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($matchGrouped)): ?>
        <div class="empty-state">
            <i class="fas fa-link"></i>
            <p><?php echo Text::_($matchFelInvoiceTotal > 0
                ? 'COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_EMPTY_ALL_LINKED'
                : 'COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_EMPTY_GROUPS'); ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($matchGrouped as $grp):
            $gName = htmlspecialchars((string) ($grp['client_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $gNit = htmlspecialchars(trim((string) ($grp['nit_display'] ?? '')), ENT_QUOTES, 'UTF-8');
            ?>
        <div class="match-client-group">
            <div class="match-client-group-header">
                <?php echo $gName !== '' ? $gName : '—'; ?>
                <?php if ($gNit !== ''): ?><span class="text-muted fw-normal ms-2"><?php echo $gNit; ?></span><?php endif; ?>
            </div>
            <?php foreach (($grp['invoices'] ?? []) as $block):
                $inv = $block['invoice'] ?? null;
                if (!$inv || !is_object($inv)) {
                    continue;
                }
                $iid = (int) ($inv->id ?? 0);
                $desc = (string) ($block['description'] ?? '');
                $sugs = isset($block['suggestions']) && is_array($block['suggestions']) ? $block['suggestions'] : [];
                $opts = $dropdownOpts[$iid] ?? [];
                $hasPendingSuggestions = false;
                foreach ($sugs as $_sug) {
                    if ((string) ($_sug->status ?? '') === 'pending') {
                        $hasPendingSuggestions = true;
                        break;
                    }
                }
                ?>
            <div class="match-invoice-block">
                <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.associateSelectedInvoiceOrdenMatches'); ?>" class="match-invoice-associate-form mb-0" onsubmit="var c=this.querySelectorAll('.match-suggestion-cb:checked'); if(!c.length){window.alert(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ASSOCIATE_NONE')); ?>); return false;} return true;">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="invoice_id" value="<?php echo $iid; ?>" />
                    <input type="hidden" name="match_status" value="<?php echo $matchStatusHidden; ?>" />
                    <input type="hidden" name="match_client" value="<?php echo $matchClientHidden; ?>" />
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $iid); ?>" class="fw-bold">
                                <?php echo htmlspecialchars($inv->invoice_number ?? ('#' . $iid), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php
                            $invDateRaw = $inv->fel_fecha_emision ?? $inv->invoice_date ?? null;
                            if (!empty($invDateRaw)) :
                                ?>
                            <span class="text-muted small ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_INVOICE_DATE_LABEL'); ?>:
                                <strong><?php echo HTMLHelper::_('date', $invDateRaw, Text::_('DATE_FORMAT_LC4')); ?></strong>
                            </span>
                            <?php endif; ?>
                            <span class="text-muted small ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_INV_TOTAL'); ?>: <strong><?php echo number_format((float) ($inv->invoice_amount ?? 0), 2); ?></strong> Q</span>
                        </div>
                        <?php if ($hasPendingSuggestions): ?>
                        <button type="submit" class="btn btn-primary btn-sm shrink-0">
                            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ASSOCIATE_BUTTON'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                <?php if ($desc !== ''): ?>
                    <p class="invoice-lines-desc mb-3"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.8125rem;">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_SCORE'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_STATUS'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_ORDER'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_ORDER_DATE'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_AMOUNTS'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_REASONS'); ?></th>
                                    <th class="text-center" style="width: 1%;"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_SELECT'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sugs)): ?>
                                <tr>
                                    <td colspan="7" class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_NO_SUGGESTIONS_FOR_INVOICE'); ?></td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($sugs as $row):
                                    $reasons = isset($row->reasons_list) && is_array($row->reasons_list) ? $row->reasons_list : [];
                                    $reasonLabels = [];
                                    foreach ($reasons as $code) {
                                        $key = 'COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_REASON_' . strtoupper((string) $code);
                                        $reasonLabels[] = Text::_($key) !== $key ? Text::_($key) : (string) $code;
                                    }
                                    $st = (string) ($row->status ?? '');
                                    $badgeClass = $st === 'approved' ? 'success' : ($st === 'rejected' ? 'secondary' : 'warning');
                                    $sid = (int) ($row->id ?? 0);
                                    ?>
                                <tr>
                                    <td><strong><?php echo number_format((float) ($row->score ?? 0), 1); ?></strong></td>
                                    <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . (int) ($row->orden_id ?? 0)); ?>">
                                            <?php echo htmlspecialchars($row->orden_de_trabajo ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <div class="small"><?php
                                            $wd = (string) ($row->orden_work_description ?? '');
                                            echo htmlspecialchars(function_exists('mb_strimwidth') ? mb_strimwidth($wd, 0, 120, '…') : substr($wd, 0, 120), ENT_QUOTES, 'UTF-8');
                                        ?></div>
                                    </td>
                                    <td class="text-nowrap small">
                                        <?php
                                        $of = $row->orden_fecha ?? null;
                                        echo !empty($of) ? HTMLHelper::_('date', $of, Text::_('DATE_FORMAT_LC4')) : '—';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_ORD_VALUE'); ?>: <?php echo number_format((float) ($row->orden_valor_facturar ?? 0), 2); ?> Q</div>
                                    </td>
                                    <td class="match-reasons"><?php echo htmlspecialchars(implode(', ', $reasonLabels), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center align-middle">
                                        <?php if ($st === 'pending' && $sid > 0): ?>
                                        <input type="checkbox" class="form-check-input match-suggestion-cb" name="cid[]" value="<?php echo $sid; ?>" aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_COL_SELECT'); ?>" />
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="d-flex flex-wrap align-items-end gap-2">
                    <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.addManualInvoiceOrdenMatch'); ?>" class="d-flex flex-wrap align-items-end gap-2">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="invoice_id" value="<?php echo $iid; ?>" />
                        <input type="hidden" name="match_status" value="<?php echo $matchStatusHidden; ?>" />
                        <input type="hidden" name="match_client" value="<?php echo $matchClientHidden; ?>" />
                        <div>
                            <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_MANUAL_ADD_LABEL'); ?></label>
                            <select name="orden_id" class="form-control form-control-sm" style="min-width: 220px;">
                                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_SELECT_ORDEN'); ?></option>
                                <?php foreach ($opts as $opt):
                                    $oid = (int) ($opt['id'] ?? 0);
                                    $lab = (string) ($opt['label'] ?? '');
                                    if ($oid <= 0) {
                                        continue;
                                    }
                                    ?>
                                <option value="<?php echo $oid; ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_MANUAL_ADD'); ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>

    <?php else: ?>

    <!-- Filters: NIT, Cliente, Fecha, Total (id="adminForm" and limit/limitstart for pagination) -->
    <?php
    $state = $this->state ?? new \Joomla\Registry\Registry();
    $listLimit   = (int) $state->get('list.limit', 20) ?: 20;
    $listStart   = (int) $state->get('list.start', 0);
    $filterNit     = $state->get('filter.nit', '');
    $filterCliente = $state->get('filter.cliente', '');
    $filterFechaFrom = $state->get('filter.fecha_from', '');
    $filterFechaTo   = $state->get('filter.fecha_to', '');
    $filterTotalMin  = $state->get('filter.total_min', '');
    $filterTotalMax  = $state->get('filter.total_max', '');
    $filterTipo      = (string) $state->get('filter.tipo', '');
    ?>
    <form id="adminForm" method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" 
          class="search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="invoices" />
        <input type="hidden" name="invoices_subtab" value="lista" />
        <input type="hidden" name="limit" value="<?php echo (int) $listLimit; ?>" />
        <input type="hidden" name="limitstart" value="0" />
        <input type="text" name="filter_nit" placeholder="NIT" value="<?php echo htmlspecialchars($filterNit); ?>" />
        <div class="invoices-cliente-autocomplete position-relative">
            <input type="text" id="invoices-filter-cliente" name="filter_cliente" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?> (Cliente)" value="<?php echo htmlspecialchars($filterCliente); ?>" autocomplete="off" data-suggest-url="<?php echo htmlspecialchars(Route::_('index.php?option=com_ordenproduccion&task=administracion.suggestInvoiceClients&format=json&' . \Joomla\CMS\Session\Session::getFormToken() . '=1&q=')); ?>" />
            <ul id="invoices-cliente-suggestions" class="invoices-cliente-suggestions list-group position-absolute" style="display:none; z-index: 1000; max-height: 220px; overflow-y: auto; min-width: 100%;"></ul>
        </div>
        <input type="date" name="filter_fecha_from" placeholder="Fecha desde" value="<?php echo htmlspecialchars($filterFechaFrom); ?>" title="Fecha desde" />
        <input type="date" name="filter_fecha_to" placeholder="Fecha hasta" value="<?php echo htmlspecialchars($filterFechaTo); ?>" title="Fecha hasta" />
        <input type="number" name="filter_total_min" placeholder="Total min" value="<?php echo htmlspecialchars($filterTotalMin); ?>" step="0.01" min="0" title="Total mínimo (Q)" />
        <input type="number" name="filter_total_max" placeholder="Total max" value="<?php echo htmlspecialchars($filterTotalMax); ?>" step="0.01" min="0" title="Total máximo (Q)" />
        <select name="filter_tipo" class="form-select form-select-sm" style="max-width: 12rem;" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICES_FILTER_TIPO'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICES_FILTER_TIPO'), ENT_QUOTES, 'UTF-8'); ?>">
            <option value=""><?php echo Text::_('JALL'); ?></option>
            <option value="valid"<?php echo $filterTipo === 'valid' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TIPO_VALID'); ?></option>
            <option value="mockup"<?php echo $filterTipo === 'mockup' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TIPO_MOCKUP'); ?></option>
        </select>
        <button type="submit">
            <i class="fas fa-search"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER'); ?>
        </button>
    </form>

    <!-- Import XML and Export Excel -->
    <div class="import-xml-bar d-flex flex-wrap gap-2 align-items-center">
        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.importInvoicesXml'); ?>" 
              method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="file" name="invoice_xml[]" accept=".xml,.zip" multiple="multiple" class="form-control form-control-sm" style="max-width: 320px;" title="<?php echo Text::_('COM_ORDENPRODUCCION_IMPORT_XML_MULTIPLE_HINT'); ?>" />
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-import"></i> <?php echo Text::_('COM_ORDENPRODUCCION_IMPORT_XML'); ?>
            </button>
        </form>
        <?php
        $state = $this->state ?? new \Joomla\Registry\Registry();
        $exportUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.exportInvoicesExcel&format=raw');
        $exportUrl .= '&filter_nit=' . rawurlencode($state->get('filter.nit', ''));
        $exportUrl .= '&filter_cliente=' . rawurlencode($state->get('filter.cliente', ''));
        $exportUrl .= '&filter_fecha_from=' . rawurlencode($state->get('filter.fecha_from', ''));
        $exportUrl .= '&filter_fecha_to=' . rawurlencode($state->get('filter.fecha_to', ''));
        $exportUrl .= '&filter_total_min=' . rawurlencode($state->get('filter.total_min', ''));
        $exportUrl .= '&filter_total_max=' . rawurlencode($state->get('filter.total_max', ''));
        $exportUrl .= '&filter_tipo=' . rawurlencode($state->get('filter.tipo', ''));
        ?>
        <a href="<?php echo $exportUrl; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-file-excel"></i> <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_EXPORT_EXCEL'); ?>
        </a>
    </div>

    <?php if (!empty($importReport) && is_array($importReport)): ?>
    <div class="import-report-box mb-3">
        <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_REPORT'); ?></h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>Archivo</th>
                        <th>Resultado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($importReport as $r):
                        $file = isset($r['file']) ? $r['file'] : '';
                        $status = isset($r['status']) ? $r['status'] : '';
                        $msg = isset($r['message']) ? $r['message'] : '';
                        $statusLabel = $status === 'imported' ? Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_IMPORTED') : ($status === 'skipped' ? Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_SKIPPED') : Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_ERROR'));
                        $rowClass = $status === 'imported' ? 'table-success' : ($status === 'skipped' ? 'table-warning' : 'table-danger');
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $statusLabel; ?></td>
                        <td><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Invoices Table: Serie|Numero, Fecha de Emision, NIT, Description (FEL lines), Total Factura (Q) -->
    <?php if (!empty($invoices)): ?>
        <table class="invoices-table">
            <thead>
                <tr>
                    <th>Serie | Número</th>
                    <th>Fecha de Emisión</th>
                    <th>NIT</th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_COL_TIPO'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_COL_CLIENT_NAME'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_COL_DESCRIPTION'); ?></th>
                    <th style="text-align: right;">Total Factura (Q)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice):
                    $felExtra = [];
                    if (!empty($invoice->fel_extra) && is_string($invoice->fel_extra)) {
                        $felExtra = json_decode($invoice->fel_extra, true) ?: [];
                    }
                    $serie  = $felExtra['autorizacion_serie'] ?? '';
                    $numero = $felExtra['autorizacion_numero_dte'] ?? '';
                    $fechaEmision = !empty($invoice->fel_fecha_emision) ? $invoice->fel_fecha_emision : ($invoice->invoice_date ?? null);
                    if ($fechaEmision) {
                        $fechaEmision = HTMLHelper::_('date', $fechaEmision, 'd-m-Y H:i:s');
                    } else {
                        $fechaEmision = '—';
                    }
                    $nit = trim($invoice->client_nit ?? $invoice->fel_receptor_id ?? '');
                    if ($nit === '') {
                        $nit = '—';
                    }
                    $moneda = $invoice->currency ?? 'Q';
                    $isMockup = InvoiceListHelper::isMockupInvoice($invoice);
                    $displayClient = InvoiceListHelper::displayClientName($invoice);
                    if ($displayClient === '') {
                        $displayClient = '—';
                    }
                ?>
                    <tr class="<?php echo $isMockup ? 'invoice-row-mockup' : ''; ?>" onclick="window.location.href='<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . (int) $invoice->id); ?>'">
                        <td>
                            <span class="invoice-serie-numero"><?php echo htmlspecialchars($serie ?: '—'); ?> | <?php echo htmlspecialchars($numero ?: '—'); ?></span>
                        </td>
                        <td><?php echo $fechaEmision; ?></td>
                        <td><?php echo htmlspecialchars($nit); ?></td>
                        <td>
                            <?php if ($isMockup) : ?>
                                <span class="invoice-tipo-badge invoice-tipo-mockup"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TIPO_MOCKUP'); ?></span>
                            <?php else : ?>
                                <span class="invoice-tipo-badge invoice-tipo-valid"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TIPO_VALID'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($displayClient, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="invoice-lines-desc"><?php
                            $lineDesc = InvoiceOrdenMatchModel::getInvoiceLinesDescription($invoice);
                            echo $lineDesc !== '' ? htmlspecialchars($lineDesc, ENT_QUOTES, 'UTF-8') : '—';
                        ?></td>
                        <td class="invoice-amount text-end text-nowrap">
                            <?php
                            $pdfRel = trim((string) ($invoice->fel_local_pdf_path ?? ''));
                            $xmlRel = trim((string) ($invoice->fel_local_xml_path ?? ''));
                            $pdfAbs = $pdfRel !== '' ? JPATH_ROOT . '/' . ltrim(str_replace('\\', '/', $pdfRel), '/') : '';
                            $xmlAbs = $xmlRel !== '' ? JPATH_ROOT . '/' . ltrim(str_replace('\\', '/', $xmlRel), '/') : '';
                            $pdfOk  = $pdfAbs !== '' && is_file($pdfAbs);
                            $xmlOk  = $xmlAbs !== '' && is_file($xmlAbs);
                            $invNum = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) ($invoice->invoice_number ?? (string) (int) ($invoice->id ?? 0)));
                            $pdfHref = $pdfOk ? htmlspecialchars(FelInvoiceHelper::downloadFelArtifactUrl((int) $invoice->id, 'pdf'), ENT_QUOTES, 'UTF-8') : '';
                            $xmlHref = $xmlOk ? htmlspecialchars(FelInvoiceHelper::downloadFelArtifactUrl((int) $invoice->id, 'xml', true), ENT_QUOTES, 'UTF-8') : '';
                            ?>
                            <div class="d-inline-flex align-items-center justify-content-end gap-1 flex-wrap">
                                <span><?php echo number_format((float) ($invoice->invoice_amount ?? 0), 2); ?> <?php echo htmlspecialchars($moneda); ?></span>
                                <?php if ($pdfOk) : ?>
                                <a href="<?php echo $pdfHref; ?>"
                                   class="p-0 ms-1 text-decoration-none invoice-fel-file-link"
                                   title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_DOWNLOAD_PDF'), ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   onclick="event.stopPropagation();"><i class="fas fa-file-pdf" aria-hidden="true"></i><span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_DOWNLOAD_PDF'); ?></span></a>
                                <?php endif; ?>
                                <?php if ($xmlOk) : ?>
                                <a href="<?php echo $xmlHref; ?>"
                                   class="p-0 text-decoration-none invoice-fel-file-link"
                                   title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_DOWNLOAD_XML'), ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   onclick="event.stopPropagation();"><i class="fas fa-file-code" aria-hidden="true"></i><span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_DOWNLOAD_XML'); ?></span></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination: always show when we have a pagination object (results counter + page links) -->
        <?php if ($pagination): ?>
            <div class="invoices-pagination-wrapper" role="navigation" aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_PAGINATION'); ?>">
                <div class="invoices-pagination-info">
                    <?php echo $pagination->getResultsCounter(); ?>
                </div>
                <div class="invoices-pagination-links">
                    <?php
                    $listFooter = $pagination->getListFooter();
                    if (trim((string) $listFooter) !== '') {
                        echo $listFooter;
                    } else {
                        // Fallback: build prev/next and page counter when getListFooter() is empty (e.g. frontend)
                        $total   = (int) $pagination->total;
                        $limit   = (int) $pagination->limit ?: 20;
                        $start   = (int) $pagination->limitstart;
                        $pagesTotal = $limit > 0 ? (int) ceil($total / $limit) : 1;
                        $currentPage = $pagesTotal > 0 ? (int) floor($start / $limit) + 1 : 1;
                        $baseUrl = 'index.php?option=com_ordenproduccion&view=administracion&tab=invoices&invoices_subtab=lista&limit=' . $limit;
                        $baseUrl .= '&filter_nit=' . rawurlencode($state->get('filter.nit', ''));
                        $baseUrl .= '&filter_cliente=' . rawurlencode($state->get('filter.cliente', ''));
                        $baseUrl .= '&filter_fecha_from=' . rawurlencode($state->get('filter.fecha_from', ''));
                        $baseUrl .= '&filter_fecha_to=' . rawurlencode($state->get('filter.fecha_to', ''));
                        $baseUrl .= '&filter_total_min=' . rawurlencode($state->get('filter.total_min', ''));
                        $baseUrl .= '&filter_total_max=' . rawurlencode($state->get('filter.total_max', ''));
                        $baseUrl .= '&filter_tipo=' . rawurlencode($state->get('filter.tipo', ''));
                        ?>
                        <nav aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_PAGINATION'); ?>">
                            <ul class="pagination justify-content-center flex-wrap">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo Route::_($baseUrl . '&limitstart=' . max(0, $start - $limit)); ?>">&laquo; <?php echo Text::_('JPREV'); ?></a>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item disabled"><span class="page-link"><?php echo $pagination->getPagesCounter(); ?></span></li>
                                <?php if ($currentPage < $pagesTotal): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo Route::_($baseUrl . '&limitstart=' . ($start + $limit)); ?>"><?php echo Text::_('JNEXT'); ?> &raquo;</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php } ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_NO_INVOICES_FOUND'); ?></p>
            <p>
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&layout=create'); ?>" 
                   class="btn-create-invoice">
                    <i class="fas fa-plus"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_CREATE_FIRST_INVOICE'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <script>
    (function() {
        var input = document.getElementById('invoices-filter-cliente');
        var list = document.getElementById('invoices-cliente-suggestions');
        if (!input || !list) return;
        var baseUrl = input.getAttribute('data-suggest-url') || '';
        var debounceTimer = null;
        var hideTimer = null;

        function hideSuggestions() {
            list.style.display = 'none';
            list.innerHTML = '';
        }

        function showSuggestions(items) {
            list.innerHTML = '';
            if (!items || items.length === 0) {
                list.style.display = 'none';
                return;
            }
            items.forEach(function(name) {
                var li = document.createElement('li');
                li.className = 'list-group-item';
                li.textContent = name;
                li.addEventListener('click', function() {
                    input.value = name;
                    hideSuggestions();
                    input.focus();
                });
                list.appendChild(li);
            });
            list.style.display = 'block';
        }

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var q = (input.value || '').trim();
            if (q.length < 2) {
                hideSuggestions();
                return;
            }
            debounceTimer = setTimeout(function() {
                var url = baseUrl + encodeURIComponent(q);
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(arr) { showSuggestions(Array.isArray(arr) ? arr : []); })
                    .catch(function() { hideSuggestions(); });
            }, 280);
        });

        input.addEventListener('focus', function() {
            var q = (input.value || '').trim();
            if (q.length >= 2 && list.children.length) list.style.display = 'block';
        });

        input.addEventListener('blur', function() {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(hideSuggestions, 180);
        });

        list.addEventListener('mousedown', function(e) {
            e.preventDefault();
        });
    })();
    </script>

    <?php endif; ?>

</div>

