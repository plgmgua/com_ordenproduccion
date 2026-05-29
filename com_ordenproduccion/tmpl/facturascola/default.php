<?php
/**
 * Cola de facturas — standalone view.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$adminUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false);
$showAdminBackLink = AccessHelper::isInAdministracionOrAdmonGroup() || AccessHelper::isSuperUser();
$felProcessQueueUrl = Route::_('index.php?option=com_ordenproduccion&task=invoice.processFelIssuance&format=json', false);
$invoiceQueueDateTimeFormat = 'Y-m-d\TH:i';
$invoiceQueueQuoteDateFormat = 'Y-m-d';

$invoiceEnvioFelPendingSectionAvailable = (bool) $this->get('invoiceEnvioFelPendingSectionAvailable');
$invoiceEnvioFelPendingRows = $this->get('invoiceEnvioFelPendingRows');
if (!is_array($invoiceEnvioFelPendingRows)) {
    $invoiceEnvioFelPendingRows = [];
}
$invoiceEnvioFelPendingPagination = $this->get('invoiceEnvioFelPendingPagination');

$invoiceFelQueueAvailable = (bool) $this->get('invoiceFelQueueAvailable');
$invoiceFelQueueRows = $this->get('invoiceFelQueueRows');
if (!is_array($invoiceFelQueueRows)) {
    $invoiceFelQueueRows = [];
}
$invoiceFelQueuePagination = $this->get('invoiceFelQueuePagination');
?>
<div class="facturascola-page container py-3">
    <?php if ($showAdminBackLink) : ?>
    <p class="mb-3">
        <a href="<?php echo htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_BACK_CONTROL_VENTAS'); ?>
        </a>
    </p>
    <?php endif; ?>

    <div class="invoices-section">
        <div class="invoices-header mb-4">
            <h2 class="h4 mb-0">
                <i class="fas fa-file-invoice me-2"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_FACTURASCOLA_HEADING'); ?>
            </h2>
        </div>

        <?php if ($invoiceEnvioFelPendingSectionAvailable) : ?>
        <h3 class="h5 fw-semibold mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_SECTION_TITLE'); ?></h3>
        <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_INTRO'); ?></p>
        <?php if (empty($invoiceEnvioFelPendingRows)) : ?>
            <div class="empty-state py-4 mb-4">
                <i class="fas fa-truck-loading"></i>
                <p class="mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_EMPTY'); ?></p>
            </div>
        <?php else : ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped table-hover align-middle invoice-fel-queue-table">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_QUOTATION'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_COL_QUOTE_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_COL_QUEUED_AT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_CLIENT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_AMOUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_COL_OT'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoiceEnvioFelPendingRows as $er) :
                            $eqnum = trim((string) ($er->quotation_number ?? ''));
                            if ($eqnum === '') {
                                $eqnum = 'COT-' . (int) ($er->quotation_id ?? 0);
                            }
                            $otTot = (int) ($er->ordenes_total ?? 0);
                            $otDone = (int) ($er->ordenes_envio_completo ?? 0);
                            $eqQuoteDate = !empty($er->quote_date) ? HTMLHelper::_('date', $er->quote_date, $invoiceQueueQuoteDateFormat) : '—';
                            $eqQueuedAt = !empty($er->quotation_created) ? HTMLHelper::_('date', $er->quotation_created, $invoiceQueueDateTimeFormat) : '—';
                            ?>
                        <tr>
                            <td><?php echo htmlspecialchars($eqnum); ?></td>
                            <td class="text-nowrap invoice-queue-quote-date"><?php echo htmlspecialchars($eqQuoteDate); ?></td>
                            <td class="text-nowrap"><?php echo htmlspecialchars($eqQueuedAt); ?></td>
                            <td><?php echo htmlspecialchars((string) ($er->client_name ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($er->client_nit ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float) ($er->total_amount ?? 0), 2)); ?></td>
                            <td><?php echo htmlspecialchars(Text::sprintf('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_OT_PROGRESS_FMT', $otDone, $otTot)); ?></td>
                            <td class="text-nowrap">
                                <div class="d-inline-flex flex-wrap align-items-center gap-1">
                                <?php if (!empty($er->quotation_id)) : ?>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $er->quotation_id); ?>"
                                   title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_QUOTE'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                                    <span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_QUOTE'); ?></span>
                                </a>
                                <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.cancelQuotationEnvioFelQueue'); ?>" class="d-inline js-invoice-queue-cancel-form"
                                      data-confirm="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_CANCEL_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="quotation_id" value="<?php echo (int) $er->quotation_id; ?>" />
                                    <input type="hidden" name="return_view" value="facturascola" />
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_CANCEL_BUTTON'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                        <span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_CANCEL_BUTTON'); ?></span>
                                    </button>
                                </form>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($invoiceEnvioFelPendingPagination && $invoiceEnvioFelPendingPagination->pagesTotal > 1) : ?>
                <div class="com-content-pagination mb-4"><?php echo $invoiceEnvioFelPendingPagination->getPagesLinks(); ?></div>
            <?php endif; ?>
        <?php endif; ?>
        <hr class="my-4" />
        <?php endif; ?>

        <?php if ($invoiceFelQueueAvailable) : ?>
        <h3 class="h5 fw-semibold mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_SECTION_INVOICES_TITLE'); ?></h3>
        <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_INTRO'); ?></p>

        <?php if (empty($invoiceFelQueueRows)) : ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_EMPTY'); ?></p>
            </div>
        <?php else : ?>
            <form id="fel-queue-token-form" class="d-none" aria-hidden="true"><?php echo HTMLHelper::_('form.token'); ?></form>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle invoice-fel-queue-table">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_COL_QUOTATION'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_COL_QUOTE_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_COL_QUEUED_AT'); ?></th>
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
                            $canProcessNow = ($st === 'scheduled' || $st === 'pending') && AccessHelper::isSuperUser();
                            $qqd = !empty($qr->quotation_quote_date) ? HTMLHelper::_('date', $qr->quotation_quote_date, $invoiceQueueQuoteDateFormat) : '—';
                            $invQueuedAt = !empty($qr->created) ? HTMLHelper::_('date', $qr->created, $invoiceQueueDateTimeFormat) : '—';
                            ?>
                        <tr>
                            <td><?php echo htmlspecialchars($qnum); ?></td>
                            <td class="text-nowrap invoice-queue-quote-date"><?php echo htmlspecialchars($qqd); ?></td>
                            <td class="text-nowrap"><?php echo htmlspecialchars($invQueuedAt); ?></td>
                            <td><?php echo htmlspecialchars((string) ($qr->client_name ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($qr->client_nit ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($qr->invoice_number ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(number_format((float) ($qr->invoice_amount ?? 0), 2)); ?></td>
                            <td><?php echo htmlspecialchars($stLabel); ?></td>
                            <td><?php echo htmlspecialchars($sched !== '' ? $sched : '—'); ?></td>
                            <td class="text-nowrap">
                                <div class="d-inline-flex flex-wrap align-items-center gap-1">
                                <?php if (!empty($qr->quotation_id)) : ?>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $qr->quotation_id); ?>"
                                   title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_QUOTE'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-file-alt" aria-hidden="true"></i>
                                    <span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_QUOTE'); ?></span>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($qr->id)) : ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . (int) $qr->id); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_OPEN_INVOICE'); ?></a>
                                <?php endif; ?>
                                <?php if (!empty($qr->id) && $canProcessNow) : ?>
                                <button type="button" class="btn btn-sm btn-primary fel-queue-process-now" data-invoice-id="<?php echo (int) $qr->id; ?>">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_PROCESS_NOW'); ?>
                                </button>
                                <?php endif; ?>
                                <?php if (!empty($qr->id) && \in_array($st, ['scheduled', 'pending', 'processing'], true)) : ?>
                                <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.cancelInvoiceFelQueue'); ?>" class="d-inline js-invoice-queue-cancel-form"
                                      data-confirm="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_CANCEL_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="invoice_id" value="<?php echo (int) $qr->id; ?>" />
                                    <input type="hidden" name="return_view" value="facturascola" />
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_CANCEL_BUTTON'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                        <span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_QUEUE_CANCEL_BUTTON'); ?></span>
                                    </button>
                                </form>
                                <?php endif; ?>
                                </div>
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
        <?php endif; ?>

        <script>
        (function() {
            document.querySelectorAll('.js-invoice-queue-cancel-form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    var msg = form.getAttribute('data-confirm') || '';
                    if (msg !== '' && !window.confirm(msg)) {
                        e.preventDefault();
                    }
                });
            });
        })();
        </script>
    </div>
</div>

<style>
.facturascola-page .invoices-section {
    background: white;
    padding: 1rem 1.15rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.facturascola-page .invoices-header h2 {
    font-size: 1.1rem;
}
.facturascola-page h3.h5 {
    font-size: 0.95rem;
    margin-bottom: 0.35rem;
}
.facturascola-page .text-muted.small {
    font-size: 0.7rem;
    line-height: 1.3;
    margin-bottom: 0.65rem !important;
}
.facturascola-page hr.my-4 {
    margin: 0.85rem 0 !important;
}
.facturascola-page .empty-state {
    text-align: center;
    padding: 1.25rem 0.75rem;
    color: #6c757d;
    font-size: 0.75rem;
}
.facturascola-page .empty-state i {
    font-size: 1.5rem;
    margin-bottom: 0.35rem;
    opacity: 0.5;
}
/* Cola tables: compact (aligned with Control de Ventas → Facturas → Cola) */
.facturascola-page .invoice-fel-queue-table {
    font-size: 0.55rem;
    table-layout: auto;
    margin-bottom: 0;
}
.facturascola-page .invoice-fel-queue-table thead th {
    font-size: 0.55rem;
    font-weight: 600;
    padding: 0.28rem 0.35rem;
    white-space: nowrap;
    vertical-align: middle;
}
.facturascola-page .invoice-fel-queue-table tbody td {
    padding: 0.28rem 0.35rem;
    vertical-align: middle;
    line-height: 1.2;
    font-size: 0.55rem;
}
.facturascola-page .invoice-fel-queue-table td.invoice-queue-quote-date {
    max-width: 6.5rem;
    word-break: break-word;
}
.facturascola-page .invoice-fel-queue-table td:nth-child(4) {
    max-width: 11rem;
    line-height: 1.15;
}
.facturascola-page .invoice-fel-queue-table .btn {
    font-size: 0.55rem;
    padding: 0.1rem 0.3rem;
    line-height: 1.15;
    border-radius: 0.2rem;
}
.facturascola-page .invoice-fel-queue-table .btn i.fas,
.facturascola-page .invoice-fel-queue-table .btn i.far {
    font-size: inherit;
    line-height: 1;
    vertical-align: -0.05em;
}
.facturascola-page .invoice-fel-queue-table .d-inline-flex.gap-1 {
    gap: 0.15rem !important;
}
.facturascola-page .com-content-pagination {
    font-size: 0.7rem;
    margin-top: 0.5rem;
}
</style>
