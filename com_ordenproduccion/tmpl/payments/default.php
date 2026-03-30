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
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Payments\HtmlView $this */
?>
<div class="com-ordenproduccion-payments">
    <div class="container-fluid">
        <?php
        $isDeletedView = (int) $this->state->get('filter.state', 1) === 0;
        $paymentsTab = $this->paymentsTab ?? 'list';
        $showPaymentsList = $isDeletedView || $paymentsTab === 'list';
        $paymentsListUrl = Route::_('index.php?option=com_ordenproduccion&view=payments');
        $paymentsNotesTabUrl = Route::_('index.php?option=com_ordenproduccion&view=payments&tab=notes');
        $paymentsClearUrl = Route::_('index.php?option=com_ordenproduccion&view=payments' . ($isDeletedView ? '&filter_state=0' : ''));
        ?>
        <div class="row mb-2">
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h1 class="page-title h4 mb-0"><?php echo $isDeletedView ? 'Pagos eliminados' : 'Control de Pagos'; ?></h1>
                <?php if ($isDeletedView) : ?>
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments'); ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left"></i> Volver a pagos activos
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$isDeletedView) : ?>
        <div class="row mb-3">
            <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=paymentproof'); ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-credit-card"></i> <?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTRATION')); ?>
                </a>
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments&filter_state=0'); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-trash"></i> Ver pagos eliminados
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isDeletedView) : ?>
        <ul class="nav nav-tabs mb-3 flex-wrap gap-1" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link<?php echo $paymentsTab === 'list' ? ' active' : ''; ?>"
                   href="<?php echo htmlspecialchars($paymentsListUrl); ?>"
                   <?php echo $paymentsTab === 'list' ? ' aria-current="page"' : ''; ?>>
                    <?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_TAB_LIST')); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link<?php echo $paymentsTab === 'notes' ? ' active' : ''; ?>"
                   href="<?php echo htmlspecialchars($paymentsNotesTabUrl); ?>"
                   <?php echo $paymentsTab === 'notes' ? ' aria-current="page"' : ''; ?>>
                    <?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_TAB_MISMATCH_NOTES')); ?>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <?php if ($showPaymentsList) : ?>
        <!-- Filters - compact -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="card card-compact">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0 small">
                            <i class="fas fa-filter"></i> Filtros
                        </h5>
                    </div>
                    <div class="card-body py-2">
                        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments'); ?>">
                            <input type="hidden" name="option" value="com_ordenproduccion">
                            <input type="hidden" name="view" value="payments">
                            <input type="hidden" name="filter_state" value="<?php echo (int) $this->state->get('filter.state', 1); ?>">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label for="filter_client" class="form-label small mb-0">Cliente</label>
                                    <input type="text" name="filter_client" id="filter_client" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.client')); ?>"
                                           placeholder="Filtrar por cliente...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_from" class="form-label small mb-0">Fecha Desde</label>
                                    <input type="date" name="filter_date_from" id="filter_date_from" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.date_from')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_to" class="form-label small mb-0">Fecha Hasta</label>
                                    <input type="date" name="filter_date_to" id="filter_date_to" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.date_to')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_sales_agent" class="form-label small mb-0">Agente de Ventas</label>
                                    <select name="filter_sales_agent" id="filter_sales_agent" class="form-select form-select-sm">
                                        <?php foreach ($this->getModel()->getSalesAgentOptions() as $value => $text) : ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo $this->state->get('filter.sales_agent') === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($text); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_estado" class="form-label small mb-0">Estado</label>
                                    <select name="filter_estado" id="filter_estado" class="form-select form-select-sm">
                                        <?php
                                        $lblAll = Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_ESTADO_ALL');
                                        $lblIngresado = Text::_('COM_ORDENPRODUCCION_PAYMENT_INGRESADO');
                                        $lblVerificado = Text::_('COM_ORDENPRODUCCION_PAYMENT_VERIFICADO');
                                        if ($lblAll === 'COM_ORDENPRODUCCION_PAYMENTS_FILTER_ESTADO_ALL') $lblAll = 'Todos';
                                        if ($lblIngresado === 'COM_ORDENPRODUCCION_PAYMENT_INGRESADO') $lblIngresado = 'Ingresado';
                                        if ($lblVerificado === 'COM_ORDENPRODUCCION_PAYMENT_VERIFICADO') $lblVerificado = 'Verificado';
                                        ?>
                                        <option value=""<?php echo $this->state->get('filter.estado') === '' ? ' selected' : ''; ?>><?php echo htmlspecialchars($lblAll); ?></option>
                                        <option value="ingresado"<?php echo $this->state->get('filter.estado') === 'ingresado' ? ' selected' : ''; ?>><?php echo htmlspecialchars($lblIngresado); ?></option>
                                        <option value="verificado"<?php echo $this->state->get('filter.estado') === 'verificado' ? ' selected' : ''; ?>><?php echo htmlspecialchars($lblVerificado); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_doc_number" class="form-label small mb-0">Doc. #</label>
                                    <input type="text" name="filter_doc_number" id="filter_doc_number" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.doc_number', '')); ?>"
                                           placeholder="Número de documento...">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i> Aplicar
                                    </button>
                                    <a href="<?php echo htmlspecialchars($paymentsClearUrl); ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Summary and Export -->
        <div class="row mb-2">
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="alert alert-info py-2 mb-0 small flex-grow-1">
                    <i class="fas fa-info-circle"></i> Se encontraron <?php echo count($this->items); ?> <?php echo $isDeletedView ? 'pagos eliminados' : 'pagos'; ?>
                </div>
                <?php if (!$isDeletedView) :
                    $exportUrl = Route::_('index.php?option=com_ordenproduccion&task=payments.export&format=raw');
                    $exportUrl .= '&filter_client=' . rawurlencode($this->state->get('filter.client', ''));
                    $exportUrl .= '&filter_date_from=' . rawurlencode($this->state->get('filter.date_from', ''));
                    $exportUrl .= '&filter_date_to=' . rawurlencode($this->state->get('filter.date_to', ''));
                    $exportUrl .= '&filter_sales_agent=' . rawurlencode($this->state->get('filter.sales_agent', ''));
                    $exportUrl .= '&filter_estado=' . rawurlencode($this->state->get('filter.estado', ''));
                    ?>
                <a href="<?php echo $exportUrl; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener">
                    <i class="fas fa-file-excel"></i> Exportar a Excel
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payments List -->
        <?php if (empty($this->items)) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning py-2 text-center small mb-0">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $isDeletedView ? 'No se encontraron pagos eliminados' : 'No se encontraron pagos'; ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover com-ordenproduccion-payments-table">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Nº Pago</th>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Orden</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Nº Doc.</th>
                                    <th scope="col">Monto</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Agente</th>
                                    <th scope="col">Registrado por</th>
                                    <?php if ($isDeletedView) : ?>
                                    <th scope="col">Eliminado</th>
                                    <th scope="col">Eliminado por</th>
                                    <?php endif; ?>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->items as $item) : ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $paymentIdFormatted = 'PA-' . str_pad((int) ($item->id ?? 0), 6, '0', STR_PAD_LEFT);
                                            if (!empty($item->order_id)) :
                                                ?><a href="<?php echo $this->getPaymentProofRoute($item->order_id, (int)($item->id ?? 0)); ?>" class="text-primary text-decoration-none"><?php echo htmlspecialchars($paymentIdFormatted); ?></a><?php
                                            else :
                                                echo htmlspecialchars($paymentIdFormatted);
                                            endif;
                                            ?>
                                        </td>
                                        <td><?php echo $this->formatDate($item->created); ?></td>
                                        <td><?php echo htmlspecialchars($item->client_name ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($item->order_id)) : ?>
                                                <a href="<?php echo $this->getOrderRoute($item->order_id); ?>" class="text-primary">
                                                    <?php echo htmlspecialchars($item->order_number ?? $item->orden_de_trabajo ?? '#' . $item->order_id); ?>
                                                </a>
                                            <?php else : ?>-<?php endif; ?>
                                        </td>
                                        <td><?php echo $this->translatePaymentType($item->payment_type ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item->document_number ?? '-'); ?></td>
                                        <td><?php echo number_format((float) ($item->payment_amount ?? 0), 2); ?></td>
                                        <td><?php
                                            $status = isset($item->verification_status) ? trim((string) $item->verification_status) : '';
                                            echo ($status !== '' && strtolower($status) === 'verificado') ? 'Verificado' : 'Ingresado';
                                        ?></td>
                                        <td><?php echo htmlspecialchars($item->sales_agent ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item->created_by_name ?? '-'); ?></td>
                                        <?php if ($isDeletedView) : ?>
                                        <td><?php echo !empty($item->modified) ? $this->formatDate($item->modified) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($item->modified_by_name ?? '-'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if (!$isDeletedView) : ?>
                                                <?php if (!empty($item->order_id)) : ?>
                                                    <a href="<?php echo $this->getPaymentProofRoute($item->order_id, (int)($item->id ?? 0)); ?>"
                                                       class="btn btn-sm btn-outline-primary py-0 px-1" title="Ver comprobante">
                                                        <i class="fas fa-credit-card"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-payment"
                                                        title="Eliminar pago" data-payment-id="<?php echo (int) $item->id; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
                <div class="row mt-2">
                    <div class="col-12">
                        <nav aria-label="Paginación" class="small">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </nav>
                        <div class="pagination-info text-center small mt-1">
                            <?php echo $this->pagination->getResultsCounter(); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$isDeletedView && $paymentsTab === 'notes') : ?>
        <div class="row mb-2">
            <div class="col-12">
                <?php if (!$this->hasMismatchNotesFeature) : ?>
                    <div class="alert alert-warning py-2 small mb-0">
                        <?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_NOTES_NO_SCHEMA')); ?>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info py-2 small mb-0">
                        <?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_NOTES_INTRO')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($this->hasMismatchNotesFeature && empty($this->mismatchNotesItems)) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-secondary py-2 text-center small mb-0">
                        <?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_NOTES_EMPTY')); ?>
                    </div>
                </div>
            </div>
        <?php elseif ($this->hasMismatchNotesFeature) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover com-ordenproduccion-payments-mismatch-table">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_ID')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_DATE')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_CLIENT')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_ORDER')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_AMOUNT')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_DIFFERENCE')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_NOTE')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_SALES_AGENT')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_RECORDED_BY')); ?></th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->mismatchNotesItems as $mItem) : ?>
                                    <?php
                                    $mPid = (int) ($mItem->id ?? 0);
                                    $mOid = (int) ($mItem->order_id ?? 0);
                                    $payFmt = 'PA-' . str_pad((string) $mPid, 6, '0', STR_PAD_LEFT);
                                    $diffRaw = isset($mItem->mismatch_difference) ? trim((string) $mItem->mismatch_difference) : '';
                                    ?>
                                    <tr>
                                        <td><?php echo $mOid > 0
                                            ? '<a href="' . htmlspecialchars($this->getPaymentProofRoute($mOid, $mPid)) . '" class="text-primary text-decoration-none">' . htmlspecialchars($payFmt) . '</a>'
                                            : htmlspecialchars($payFmt); ?></td>
                                        <td><?php echo $this->formatDate($mItem->created ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($mItem->client_name ?? '-'); ?></td>
                                        <td><?php if ($mOid > 0) : ?>
                                            <a href="<?php echo htmlspecialchars($this->getOrderRoute($mOid)); ?>" class="text-primary">
                                                <?php echo htmlspecialchars($mItem->order_number ?? $mItem->orden_de_trabajo ?? '#' . $mOid); ?>
                                            </a>
                                        <?php else : ?>-<?php endif; ?></td>
                                        <td><?php echo number_format((float) ($mItem->payment_amount ?? 0), 2); ?></td>
                                        <td><?php echo $diffRaw !== '' ? htmlspecialchars($diffRaw) : '—'; ?></td>
                                        <td class="small text-break" style="max-width: 280px;"><?php
                                            $note = isset($mItem->mismatch_note) ? trim((string) $mItem->mismatch_note) : '';
                                            echo $note !== '' ? nl2br(htmlspecialchars($note)) : '—';
                                        ?></td>
                                        <td><?php echo htmlspecialchars($mItem->sales_agent ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($mItem->created_by_name ?? '-'); ?></td>
                                        <td><?php if ($mOid > 0) : ?>
                                            <a href="<?php echo htmlspecialchars($this->getPaymentProofRoute($mOid, $mPid)); ?>"
                                               class="btn btn-sm btn-outline-primary py-0 px-1" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTRATION')); ?>">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                        <?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php if ($this->mismatchNotesPagination && $this->mismatchNotesPagination->pagesTotal > 1) : ?>
                <div class="row mt-2">
                    <div class="col-12">
                        <nav aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAGINATION')); ?>" class="small">
                            <?php echo $this->mismatchNotesPagination->getPagesLinks(); ?>
                        </nav>
                        <div class="pagination-info text-center small mt-1">
                            <?php echo $this->mismatchNotesPagination->getResultsCounter(); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal: Confirm delete payment -->
    <div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-labelledby="deletePaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePaymentModalLabel"><?php echo htmlspecialchars($this->modalDeleteTitle ?? 'Confirmar eliminación de pago'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small"><?php echo htmlspecialchars($this->modalDeleteDesc ?? 'Revise los datos del pago antes de eliminar. Se generará un PDF como comprobante.'); ?></p>
                    <div id="deletePaymentLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
                    </div>
                    <div id="deletePaymentContent" class="d-none">
                        <table class="table table-sm table-bordered mb-3">
                            <tr><th class="bg-light" style="width:35%">Cliente</th><td id="modalClient"></td></tr>
                            <tr><th class="bg-light">Fecha</th><td id="modalDate"></td></tr>
                            <tr><th class="bg-light">Tipo</th><td id="modalType"></td></tr>
                            <tr><th class="bg-light">Banco</th><td id="modalBank"></td></tr>
                            <tr><th class="bg-light">No. Documento</th><td id="modalDoc"></td></tr>
                            <tr><th class="bg-light">Monto total</th><td id="modalAmount" class="fw-bold"></td></tr>
                            <tr><th class="bg-light">Registrado por</th><td id="modalCreatedBy"></td></tr>
                        </table>
                        <h6 class="small fw-bold">Líneas de pago</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-striped" id="modalLinesTable">
                                <thead><tr><th>Tipo</th><th>Banco</th><th>No. Doc.</th><th class="text-end">Monto</th></tr></thead>
                                <tbody id="modalLinesBody"></tbody>
                            </table>
                        </div>
                        <h6 class="small fw-bold">Órdenes asociadas</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="modalOrdersTable">
                                <thead><tr><th>Orden</th><th>Cliente</th><th class="text-end">Monto aplicado</th></tr></thead>
                                <tbody id="modalOrdersBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars($this->modalCancel ?? 'Cancelar'); ?></button>
                    <form id="deletePaymentForm" method="post" style="display:inline">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="payment_id" id="modalPaymentId" value="">
                        <button type="submit" class="btn btn-danger" id="modalConfirmDelete"><?php echo htmlspecialchars($this->modalConfirmDelete ?? 'Confirmar eliminación'); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.com-ordenproduccion-payments .card-compact .card-body { padding: 0.5rem 1rem; }
.com-ordenproduccion-payments .table-responsive .table { font-size: 0.8rem; }
.com-ordenproduccion-payments .table th, .com-ordenproduccion-payments .table td { vertical-align: middle; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var deleteModal = document.getElementById('deletePaymentModal');
    var modalPaymentId = document.getElementById('modalPaymentId');
    var deleteForm = document.getElementById('deletePaymentForm');
    var deleteUrl = <?php echo json_encode(Route::_('index.php?option=com_ordenproduccion&task=payments.delete', false)); ?>;
    var paymentsUrl = <?php echo json_encode(Route::_('index.php?option=com_ordenproduccion&view=payments', false)); ?>;
    var getDetailsBase = <?php echo json_encode(Route::_('index.php?option=com_ordenproduccion&task=payments.getPaymentDetails&format=json', false)); ?>;
    var tokenParam = <?php echo json_encode(Session::getFormToken() . '=1'); ?>;

    document.querySelectorAll('.btn-delete-payment').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-payment-id');
            if (!id) return;
            modalPaymentId.value = id;
            document.getElementById('deletePaymentLoading').classList.remove('d-none');
            document.getElementById('deletePaymentContent').classList.add('d-none');
            var modal = new bootstrap.Modal(deleteModal);
            modal.show();
            var url = getDetailsBase + (getDetailsBase.indexOf('?') >= 0 ? '&' : '?') + 'payment_id=' + encodeURIComponent(id) + '&' + tokenParam;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onload = function() {
                document.getElementById('deletePaymentLoading').classList.add('d-none');
                try {
                    var d = JSON.parse(xhr.responseText);
                    if (d.error) {
                        alert(d.message || 'Error al cargar');
                        return;
                    }
                    var p = d.proof;
                    document.getElementById('modalClient').textContent = p.client_name || '-';
                    document.getElementById('modalDate').textContent = p.created ? new Date(p.created).toLocaleString('es-GT') : '-';
                    document.getElementById('modalType').textContent = p.payment_type_label || '-';
                    document.getElementById('modalBank').textContent = p.bank_label || p.bank || '-';
                    document.getElementById('modalDoc').textContent = p.document_number || '-';
                    document.getElementById('modalAmount').textContent = 'Q ' + (p.payment_amount || 0).toLocaleString('es-GT', {minimumFractionDigits: 2});
                    document.getElementById('modalCreatedBy').textContent = p.created_by_name || '-';
                    var tbody = document.getElementById('modalLinesBody');
                    tbody.innerHTML = '';
                    (d.lines || []).forEach(function(l) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td>' + (l.payment_type_label || '-') + '</td><td>' + (l.bank_label || l.bank || '-') + '</td><td>' + (l.document_number || '-') + '</td><td class="text-end">Q ' + (l.amount || 0).toLocaleString('es-GT', {minimumFractionDigits: 2}) + '</td>';
                        tbody.appendChild(tr);
                    });
                    if (!d.lines || d.lines.length === 0) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td colspan="4" class="text-muted">Sin líneas detalladas</td>';
                        tbody.appendChild(tr);
                    }
                    var obody = document.getElementById('modalOrdersBody');
                    obody.innerHTML = '';
                    (d.orders || []).forEach(function(o) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td>' + (o.order_number || '-') + '</td><td>' + (o.client_name || '-') + '</td><td class="text-end">Q ' + (o.amount_applied || 0).toLocaleString('es-GT', {minimumFractionDigits: 2}) + '</td>';
                        obody.appendChild(tr);
                    });
                    if (!d.orders || d.orders.length === 0) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td colspan="3" class="text-muted">Sin órdenes asociadas</td>';
                        obody.appendChild(tr);
                    }
                    document.getElementById('deletePaymentContent').classList.remove('d-none');
                } catch (e) {
                    alert('Error al cargar los datos');
                }
            };
            xhr.onerror = function() { document.getElementById('deletePaymentLoading').classList.add('d-none'); alert('Error de red'); };
            xhr.send();
        });
    });

    deleteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(deleteForm);
        var btn = document.getElementById('modalConfirmDelete');
        btn.disabled = true;
        fetch(deleteUrl, { method: 'POST', body: formData })
            .then(function(r) {
                var redirect = r.headers.get('X-Redirect');
                var ct = r.headers.get('Content-Type') || '';
                return r.blob().then(function(blob) {
                    if (ct.indexOf('application/pdf') >= 0 && blob.type === 'application/pdf' && redirect) {
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = 'comprobante-eliminacion-pago-' + modalPaymentId.value + '.pdf';
                        a.click();
                        URL.revokeObjectURL(a.href);
                        window.location.href = redirect;
                    } else if (redirect) {
                        window.location.href = redirect;
                    } else {
                        btn.disabled = false;
                        bootstrap.Modal.getInstance(deleteModal).hide();
                        window.location.href = paymentsUrl;
                    }
                });
            })
            .catch(function() { btn.disabled = false; alert('Error de red'); });
    });
});
</script>
