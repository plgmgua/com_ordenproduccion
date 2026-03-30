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
        $formatMismatchDifference = static function ($raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return '—';
            }
            if (is_numeric($raw)) {
                return number_format((float) $raw, 2);
            }
            $stripped = preg_replace('/[^0-9.,\-]/', '', $raw);
            if (strpos($stripped, ',') !== false && strpos($stripped, '.') === false) {
                $stripped = str_replace(',', '.', $stripped);
            } elseif (substr_count($stripped, ',') === 1 && substr_count($stripped, '.') === 1) {
                $stripped = str_replace(',', '', $stripped);
            } else {
                $stripped = str_replace(',', '.', $stripped);
            }
            if ($stripped !== '' && is_numeric($stripped)) {
                return number_format((float) $stripped, 2);
            }
            return htmlspecialchars($raw);
        };
        $mismatchStatusLabel = static function ($code) {
            $code = strtolower(trim((string) $code));
            if ($code === '') {
                $code = 'nuevo';
            }
            $map = [
                'nuevo'               => 'COM_ORDENPRODUCCION_PAYMENT_MISMATCH_STATUS_NUEVO',
                'esperando_respuesta' => 'COM_ORDENPRODUCCION_PAYMENT_MISMATCH_STATUS_ESPERANDO',
                'resuelto'            => 'COM_ORDENPRODUCCION_PAYMENT_MISMATCH_STATUS_RESUELTO',
            ];
            $key = $map[$code] ?? '';
            if ($key !== '') {
                $t = Text::_($key);
                if ($t !== $key) {
                    return $t;
                }
            }
            $fb = [
                'nuevo'               => 'Nuevo',
                'esperando_respuesta' => 'Esperando respuesta',
                'resuelto'            => 'Resuelto',
            ];

            return $fb[$code] ?? $code;
        };
        $mismatchTicketJsonUrl   = Route::_('index.php?option=com_ordenproduccion&task=payments.getMismatchTicket&format=json', false);
        $mismatchCommentPostUrl  = Route::_('index.php?option=com_ordenproduccion&task=payments.addMismatchTicketComment', false);
        $mismatchStatusPostUrl   = Route::_('index.php?option=com_ordenproduccion&task=payments.setMismatchTicketStatus', false);
        $mismatchTicketFormToken = Session::getFormToken();
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
                    <?php echo htmlspecialchars($this->paymentsTabLabelList ?: Text::_('COM_ORDENPRODUCCION_PAYMENTS_TAB_LIST')); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link<?php echo $paymentsTab === 'notes' ? ' active' : ''; ?>"
                   href="<?php echo htmlspecialchars($paymentsNotesTabUrl); ?>"
                   <?php echo $paymentsTab === 'notes' ? ' aria-current="page"' : ''; ?>>
                    <?php echo htmlspecialchars($this->paymentsTabLabelNotes ?: Text::_('COM_ORDENPRODUCCION_PAYMENTS_TAB_MISMATCH_NOTES')); ?>
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
                                    <th scope="col" class="com-ordenproduccion-payments-th-id">Nº Pago</th>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col" class="com-ordenproduccion-payments-th-id">Orden</th>
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
                                        <td class="com-ordenproduccion-payments-cell-id">
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
                                        <td class="com-ordenproduccion-payments-cell-id">
                                            <?php if (!empty($item->order_id)) : ?>
                                                <a href="<?php echo $this->getOrderRoute($item->order_id); ?>" class="text-primary">
                                                    <?php echo htmlspecialchars($item->order_number ?? $item->orden_de_trabajo ?? '#' . $item->order_id); ?>
                                                </a>
                                            <?php else : ?>-<?php endif; ?>
                                        </td>
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
                        <p class="mb-1"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_NOTES_INTRO')); ?></p>
                        <p class="mb-0 text-dark"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_NOTES_HOWTO')); ?></p>
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
                                    <th scope="col" class="com-ordenproduccion-payments-mismatch-col-note"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_NOTE')); ?></th>
                                    <th scope="col" class="com-ordenproduccion-payments-th-id"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_ID')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_DATE')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_CLIENT')); ?></th>
                                    <th scope="col" class="com-ordenproduccion-payments-th-id"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_ORDER')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_AMOUNT')); ?></th>
                                    <th scope="col" class="text-end"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_DIFFERENCE')); ?></th>
                                    <th scope="col"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_RECORDED_BY')); ?></th>
                                    <th scope="col" class="text-center text-nowrap px-2 com-ordenproduccion-payments-mismatch-th-case"
                                        title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_OPEN')); ?>"
                                        aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_OPEN')); ?>">
                                        <i class="fas fa-comments" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="text-nowrap"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_STATUS')); ?></th>
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
                                        <td class="com-ordenproduccion-payments-mismatch-col-note small text-break"><?php
                                            $note = isset($mItem->mismatch_note) ? trim((string) $mItem->mismatch_note) : '';
                                            echo $note !== '' ? nl2br(htmlspecialchars($note)) : '—';
                                        ?></td>
                                        <td class="com-ordenproduccion-payments-cell-id"><?php echo $mOid > 0
                                            ? '<a href="' . htmlspecialchars($this->getPaymentProofRoute($mOid, $mPid)) . '" class="text-primary text-decoration-none">' . htmlspecialchars($payFmt) . '</a>'
                                            : htmlspecialchars($payFmt); ?></td>
                                        <td><?php echo $this->formatDate($mItem->created ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($mItem->client_name ?? '-'); ?></td>
                                        <td class="com-ordenproduccion-payments-cell-id"><?php if ($mOid > 0) : ?>
                                            <a href="<?php echo htmlspecialchars($this->getOrderRoute($mOid)); ?>" class="text-primary">
                                                <?php echo htmlspecialchars($mItem->order_number ?? $mItem->orden_de_trabajo ?? '#' . $mOid); ?>
                                            </a>
                                        <?php else : ?>-<?php endif; ?></td>
                                        <td><?php echo number_format((float) ($mItem->payment_amount ?? 0), 2); ?></td>
                                        <td class="text-end com-ordenproduccion-payments-cell-id"><?php echo $formatMismatchDifference($diffRaw); ?></td>
                                        <td><?php echo htmlspecialchars($mItem->created_by_name ?? '-'); ?></td>
                                        <?php
                                        $mSt = isset($mItem->mismatch_ticket_status) ? trim((string) $mItem->mismatch_ticket_status) : 'nuevo';
                                        if ($mSt === '') {
                                            $mSt = 'nuevo';
                                        }
                                        ?>
                                        <td class="text-center text-nowrap align-middle mismatch-ticket-actions-cell">
                                            <button type="button" class="btn btn-sm btn-primary py-1 px-2 btn-mismatch-ticket"
                                                    data-proof-id="<?php echo (int) $mPid; ?>"
                                                    title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_OPEN')); ?>"
                                                    aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_OPEN')); ?>">
                                                <i class="fas fa-comments" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                        <td class="small mismatch-ticket-status-cell text-nowrap fw-semibold" data-proof-id="<?php echo (int) $mPid; ?>"><?php echo htmlspecialchars($mismatchStatusLabel($mSt)); ?></td>
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

    <!-- Modal: Mismatch ticket (status + threaded comments) -->
    <div class="modal fade" id="mismatchTicketModal" tabindex="-1" aria-labelledby="mismatchTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content mismatch-ticket-modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title small" id="mismatchTicketModalLabel"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_TITLE')); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE')); ?>"></button>
                </div>
                <div class="modal-body py-2 mismatch-ticket-modal-body">
                    <div id="mismatchTicketLoading" class="text-center py-3 small text-muted"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_LOADING')); ?></div>
                    <div id="mismatchTicketBody" class="d-none">
                        <p class="small mb-1"><strong><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_STATUS')); ?></strong></p>
                        <select id="mismatchTicketStatusSelect" class="form-select form-select-sm mb-2" disabled></select>
                        <p id="mismatchTicketStatusHint" class="small text-warning mb-2 d-none"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_STATUS_DISABLED')); ?></p>
                        <p id="mismatchTicketStatusHintAdmin" class="small text-muted mb-2 d-none"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_STATUS_ADMIN_ONLY')); ?></p>
                        <div class="small border rounded p-2 mb-2 bg-light">
                            <div class="mb-1"><strong><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_INITIAL')); ?></strong> <span id="mismatchTicketCreator" class="text-muted"></span></div>
                            <div id="mismatchTicketInitialNote" class="mb-0 text-break"></div>
                            <div id="mismatchTicketDiffRow" class="mt-1 text-muted"></div>
                        </div>
                        <p class="small mb-1"><strong><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_THREAD')); ?></strong></p>
                        <p id="mismatchTicketCommentsDisabled" class="small text-warning d-none mb-2"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_COMMENTS_DISABLED')); ?></p>
                        <div id="mismatchTicketComments" class="mb-2 mismatch-ticket-comments"></div>
                        <label for="mismatchTicketNewComment" class="form-label small mb-0"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_NEW_COMMENT')); ?></label>
                        <textarea id="mismatchTicketNewComment" class="form-control form-control-sm mb-1" rows="2" maxlength="8000" disabled></textarea>
                        <button type="button" class="btn btn-primary btn-sm" id="mismatchTicketBtnSendComment" disabled><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_SEND')); ?></button>
                    </div>
                    <div id="mismatchTicketError" class="alert alert-danger small py-2 mb-0 d-none"></div>
                </div>
            </div>
        </div>
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
.com-ordenproduccion-payments .com-ordenproduccion-payments-th-id,
.com-ordenproduccion-payments .com-ordenproduccion-payments-cell-id {
    white-space: nowrap;
    font-size: 0.72rem;
    max-width: 1%;
}
.com-ordenproduccion-payments .com-ordenproduccion-payments-mismatch-col-note {
    min-width: 16rem;
    width: 28%;
    white-space: normal;
    font-size: 0.85rem;
}
.mismatch-ticket-modal-content { font-size: 0.8rem; }
.mismatch-ticket-modal-body .form-select,
.mismatch-ticket-modal-body .form-control { font-size: 0.8rem; }
.mismatch-ticket-comments { max-height: 220px; overflow-y: auto; font-size: 0.78rem; }
.mismatch-ticket-comment-item {
    border-left: 2px solid #dee2e6;
    padding-left: 0.5rem;
    margin-bottom: 0.5rem;
}
.mismatch-ticket-comment-meta { color: #6c757d; font-size: 0.72rem; }
.com-ordenproduccion-payments-mismatch-th-case { width: 1%; }
.com-ordenproduccion-payments-mismatch-th-case .fa-comments { opacity: 0.95; }
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

    var mismatchModalEl = document.getElementById('mismatchTicketModal');
    var mismatchTicketJsonUrl = <?php echo json_encode($mismatchTicketJsonUrl); ?>;
    var mismatchCommentPostUrl = <?php echo json_encode($mismatchCommentPostUrl); ?>;
    var mismatchStatusPostUrl = <?php echo json_encode($mismatchStatusPostUrl); ?>;
    var mismatchFormToken = <?php echo json_encode($mismatchTicketFormToken); ?>;
    var currentMismatchProofId = 0;
    var skipMismatchStatusChange = false;

    function escapeMismatchHtml(s) {
        if (!s) { return ''; }
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function updateMismatchStatusCells(proofId, label) {
        document.querySelectorAll('.mismatch-ticket-status-cell[data-proof-id="' + proofId + '"]').forEach(function(td) {
            td.textContent = label;
        });
    }

    function renderMismatchComments(comments) {
        var box = document.getElementById('mismatchTicketComments');
        box.innerHTML = '';
        if (!comments || comments.length === 0) {
            var p = document.createElement('p');
            p.className = 'text-muted mb-0';
            p.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_NO_COMMENTS')); ?>;
            box.appendChild(p);
            return;
        }
        comments.forEach(function(c) {
            var div = document.createElement('div');
            div.className = 'mismatch-ticket-comment-item';
            var meta = document.createElement('div');
            meta.className = 'mismatch-ticket-comment-meta';
            meta.textContent = (c.user_name || '') + ' · ' + (c.created ? new Date(c.created).toLocaleString() : '');
            var body = document.createElement('div');
            body.className = 'text-break';
            body.innerHTML = escapeMismatchHtml(c.body || '').replace(/\n/g, '<br>');
            div.appendChild(meta);
            div.appendChild(body);
            box.appendChild(div);
        });
    }

    function loadMismatchTicket(proofId) {
        if (!mismatchModalEl) { return; }
        currentMismatchProofId = proofId;
        document.getElementById('mismatchTicketLoading').classList.remove('d-none');
        document.getElementById('mismatchTicketBody').classList.add('d-none');
        document.getElementById('mismatchTicketError').classList.add('d-none');
        var url = mismatchTicketJsonUrl + (mismatchTicketJsonUrl.indexOf('?') >= 0 ? '&' : '?') +
            'proof_id=' + encodeURIComponent(proofId) + '&' + encodeURIComponent(mismatchFormToken) + '=1';
        fetch(url, { headers: { 'Accept': 'application/json' } }).then(function(r) { return r.json(); }).then(function(d) {
            document.getElementById('mismatchTicketLoading').classList.add('d-none');
            if (d.error) {
                var er = document.getElementById('mismatchTicketError');
                er.textContent = d.message || 'Error';
                er.classList.remove('d-none');
                return;
            }
            document.getElementById('mismatchTicketBody').classList.remove('d-none');
            document.getElementById('mismatchTicketModalLabel').textContent =
                <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_TICKET_TITLE')); ?> + ' — ' + (d.payment_label || '');

            var sel = document.getElementById('mismatchTicketStatusSelect');
            sel.innerHTML = '';
            (d.status_options || []).forEach(function(opt) {
                var o = document.createElement('option');
                o.value = opt.value;
                o.textContent = opt.label;
                sel.appendChild(o);
            });
            skipMismatchStatusChange = true;
            sel.value = d.status || 'nuevo';
            setTimeout(function() { skipMismatchStatusChange = false; }, 0);
            var canSt = !!d.can_change_status;
            sel.disabled = !d.status_enabled || !canSt;
            document.getElementById('mismatchTicketStatusHint').classList.toggle('d-none', d.status_enabled);
            document.getElementById('mismatchTicketStatusHintAdmin').classList.toggle('d-none', !d.status_enabled || canSt);

            document.getElementById('mismatchTicketCreator').textContent = d.created_by_name ? '(' + d.created_by_name + ')' : '';
            var initN = document.getElementById('mismatchTicketInitialNote');
            initN.innerHTML = d.initial_note
                ? escapeMismatchHtml(d.initial_note).replace(/\n/g, '<br>')
                : '—';
            var diffRow = document.getElementById('mismatchTicketDiffRow');
            var diffPart = (d.difference !== '' && d.difference != null)
                ? (<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_DIFFERENCE')); ?> + ': ' + escapeMismatchHtml(String(d.difference)))
                : '';
            var amt = (typeof d.payment_amount === 'number')
                ? d.payment_amount.toLocaleString('es-GT', { minimumFractionDigits: 2 })
                : '';
            diffRow.innerHTML = [diffPart, amt ? (<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PAYMENTS_MISMATCH_COL_AMOUNT')); ?> + ': ' + amt) : ''].filter(Boolean).join(' · ');

            document.getElementById('mismatchTicketCommentsDisabled').classList.toggle('d-none', d.comments_enabled);
            var ta = document.getElementById('mismatchTicketNewComment');
            var btnS = document.getElementById('mismatchTicketBtnSendComment');
            ta.disabled = !d.comments_enabled;
            btnS.disabled = !d.comments_enabled;
            ta.value = '';

            renderMismatchComments(d.comments);
        }).catch(function() {
            document.getElementById('mismatchTicketLoading').classList.add('d-none');
            var er = document.getElementById('mismatchTicketError');
            er.textContent = 'Error de red';
            er.classList.remove('d-none');
        });
    }

    document.querySelectorAll('.btn-mismatch-ticket').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var pid = this.getAttribute('data-proof-id');
            if (!pid || !mismatchModalEl) { return; }
            var mm = bootstrap.Modal.getInstance(mismatchModalEl);
            if (!mm) { mm = new bootstrap.Modal(mismatchModalEl); }
            mm.show();
            loadMismatchTicket(pid);
        });
    });

    var statusSel = document.getElementById('mismatchTicketStatusSelect');
    if (statusSel) {
        statusSel.addEventListener('change', function() {
            if (skipMismatchStatusChange) { return; }
            if (!currentMismatchProofId) { return; }
            var fd = new FormData();
            fd.append(mismatchFormToken, '1');
            fd.append('proof_id', String(currentMismatchProofId));
            fd.append('status', this.value);
            fetch(mismatchStatusPostUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); }).then(function(d) {
                    if (d.error) {
                        alert(d.message || 'Error');
                        return;
                    }
                    updateMismatchStatusCells(currentMismatchProofId, d.status_label || '');
                }).catch(function() { alert('Error de red'); });
        });
    }

    var btnSendMismatch = document.getElementById('mismatchTicketBtnSendComment');
    if (btnSendMismatch) {
        btnSendMismatch.addEventListener('click', function() {
            if (!currentMismatchProofId) { return; }
            var ta = document.getElementById('mismatchTicketNewComment');
            var body = (ta && ta.value) ? ta.value.trim() : '';
            if (!body) { return; }
            var fd = new FormData();
            fd.append(mismatchFormToken, '1');
            fd.append('proof_id', String(currentMismatchProofId));
            fd.append('body', body);
            btnSendMismatch.disabled = true;
            fetch(mismatchCommentPostUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); }).then(function(d) {
                    btnSendMismatch.disabled = false;
                    if (d.error) {
                        alert(d.message || 'Error');
                        return;
                    }
                    ta.value = '';
                    loadMismatchTicket(String(currentMismatchProofId));
                }).catch(function() {
                    btnSendMismatch.disabled = false;
                    alert('Error de red');
                });
        });
    }
});
</script>
