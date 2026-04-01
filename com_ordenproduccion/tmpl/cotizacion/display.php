<?php
/**
 * Read-only display of a single quotation (view).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$l = function($key, $fallbackEn, $fallbackEs = null) {
    $t = Text::_($key);
    if ($t === $key || (is_string($t) && strpos($t, 'COM_ORDENPRODUCCION_') === 0)) {
        $lang = Factory::getApplication()->getLanguage()->getTag();
        return (strpos($lang, 'es') !== false && $fallbackEs !== null) ? $fallbackEs : $fallbackEn;
    }
    return $t;
};

$quotation = $this->quotation ?? null;
$items = $this->quotationItems ?? [];
$quotationId = $quotation ? (int) $quotation->id : 0;
if (!$quotation) {
    echo '<p class="text-muted p-3">' . htmlspecialchars($l('COM_ORDENPRODUCCION_QUOTATION_NOT_FOUND', 'Quotation not found.', 'Cotización no encontrada.')) . '</p>';
    return;
}

$totalAmount = isset($quotation->total_amount) ? (float) $quotation->total_amount : 0;
$currency = $quotation->currency ?? 'Q';
$quotationConfirmed = isset($quotation->cotizacion_confirmada) && (int) $quotation->cotizacion_confirmada === 1;
$editLockedHint = $l('COM_ORDENPRODUCCION_QUOTATION_LOCKED_EDIT_HINT', 'Cannot edit: quotation is confirmed.', 'No se puede editar: la cotización está confirmada.');
$pathCotAprobada = isset($quotation->cotizacion_aprobada_path) ? trim((string) $quotation->cotizacion_aprobada_path) : '';
$pathOrdenCompra  = isset($quotation->orden_compra_path) ? trim((string) $quotation->orden_compra_path) : '';
?>
<div class="cotizacion-container cotizacion-display">
    <div class="cotizaciones-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">
            <i class="fas fa-file-invoice"></i>
            <?php echo htmlspecialchars($quotation->quotation_number ?? ('COT-' . $quotationId)); ?>
        </h2>
        <div class="d-flex gap-2">
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i>
                <?php echo $l('COM_ORDENPRODUCCION_BACK_TO_LIST', 'Back to list', 'Volver a la lista'); ?>
            </a>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=cotizacion.downloadPdf&id=' . $quotationId); ?>" class="btn btn-success" target="_blank">
                <i class="fas fa-file-pdf"></i>
                <?php echo $l('COM_ORDENPRODUCCION_GENERATE_PDF', 'Generate PDF', 'Generar PDF'); ?>
            </a>
            <?php if ($quotationConfirmed) : ?>
            <span class="btn btn-secondary disabled" tabindex="-1" style="opacity: 0.65; cursor: not-allowed;" title="<?php echo htmlspecialchars($editLockedHint); ?>">
                <i class="fas fa-edit"></i>
                <?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>
            </span>
            <?php else : ?>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . '&layout=edit'); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i>
                <?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Client Information -->
    <div class="client-info-section mb-4">
        <h4 class="section-title">
            <i class="fas fa-user"></i>
            <?php echo $l('COM_ORDENPRODUCCION_CLIENT_INFORMATION', 'Client Information', 'Información del cliente'); ?>
        </h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_CLIENT_NAME', 'Client Name', 'Nombre del cliente'); ?></th>
                    <th style="width: 15%;"><?php echo $l('COM_ORDENPRODUCCION_CLIENT_ID_API', 'Client ID (API)', 'Client ID (API)'); ?></th>
                    <th style="width: 15%;"><?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?></th>
                    <th style="width: 30%;"><?php echo $l('COM_ORDENPRODUCCION_ADDRESS', 'Address', 'Dirección'); ?></th>
                    <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_SALES_AGENT', 'Sales Agent', 'Agente de ventas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($quotation->client_name ?? ''); ?></td>
                    <td><?php
                        $cid = isset($quotation->client_id) ? trim((string) $quotation->client_id) : '';
                        if ($cid !== '') {
                            echo '<code>' . htmlspecialchars($cid) . '</code>';
                        } else {
                            echo '<span class="text-muted">—</span>';
                        }
                    ?></td>
                    <td><?php echo htmlspecialchars($quotation->client_nit ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($quotation->client_address ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($quotation->sales_agent ?? ''); ?></td>
                </tr>
            </tbody>
        </table>
        <?php if (empty($quotation->client_id) || trim((string) $quotation->client_id) === ''): ?>
        <p class="small text-warning mb-0 mt-1">
            <?php echo $l('COM_ORDENPRODUCCION_CLIENT_ID_MISSING_NOTE', 'Client ID is empty. The pending pre-cotizaciones API requires client_id; edit the quotation and set the client ID (e.g. from Odoo) so this cotización appears when querying by client_id.', 'El Client ID está vacío. La API de pre-cotizaciones pendientes requiere client_id; edite la cotización y asigne el ID del cliente (ej. desde Odoo) para que aparezca al consultar por client_id.'); ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Contact Information -->
    <div class="contact-info-section mb-4">
        <h4 class="section-title">
            <i class="fas fa-address-book"></i>
            <?php echo $l('COM_ORDENPRODUCCION_CONTACT_INFORMATION', 'Contact Information', 'Información de contacto'); ?>
        </h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width: 50%;"><?php echo $l('COM_ORDENPRODUCCION_CONTACT_NAME', 'Contact Name', 'Nombre de contacto'); ?></th>
                    <th style="width: 30%;"><?php echo $l('COM_ORDENPRODUCCION_CONTACT_PHONE', 'Contact Phone', 'Teléfono de contacto'); ?></th>
                    <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_QUOTE_DATE', 'Quotation Date', 'Fecha de cotización'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($quotation->contact_name ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($quotation->contact_phone ?? ''); ?></td>
                    <td><?php echo $quotation->quote_date ? htmlspecialchars(CotizacionHelper::formatQuoteDateYmd($quotation->quote_date)) : '—'; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Quotation Lines -->
    <div class="items-table-section">
        <h4 class="section-title">
            <i class="fas fa-list"></i>
            <?php echo $l('COM_ORDENPRODUCCION_QUOTATION_ITEMS', 'Quotation Details', 'Detalles de la cotización'); ?>
        </h4>
        <?php if (empty($items)) : ?>
            <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_LINES', 'No lines.', 'Sin líneas.'); ?></p>
        <?php else : ?>
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION', 'Pre-Quotation', 'Pre-Cotización'); ?></th>
                        <th style="width: 10%;"><?php echo $l('COM_ORDENPRODUCCION_CANTIDAD', 'Qty', 'Cantidad'); ?></th>
                        <th style="width: 40%;"><?php echo $l('COM_ORDENPRODUCCION_DESCRIPCION', 'Description', 'Descripción'); ?></th>
                        <th style="width: 15%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_PRECIO_UNIDAD', 'Unit price', 'Precio unidad.'); ?></th>
                        <th style="width: 15%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_SUBTOTAL', 'Subtotal', 'Subtotal'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) :
                        $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
                        $preNum = $preId > 0 ? (trim((string) ($item->pre_cotizacion_number ?? '')) ?: 'PRE-' . $preId) : '—';
                        $qty = isset($item->cantidad) ? (int) $item->cantidad : 1;
                        $lineTotal = (isset($item->valor_final) && $item->valor_final !== null && $item->valor_final !== '') ? (float) $item->valor_final : (isset($item->subtotal) ? (float) $item->subtotal : 0);
                        $unit = $qty > 0 ? ($lineTotal / $qty) : 0;
                    ?>
                        <tr>
                            <td><?php if ($preId > 0) : ?><a href="#" class="precotizacion-detail-link" data-pre-id="<?php echo $preId; ?>" data-pre-number="<?php echo htmlspecialchars($preNum); ?>"><?php echo htmlspecialchars($preNum); ?></a><?php else : ?><?php echo htmlspecialchars($preNum); ?><?php endif; ?></td>
                            <td><?php echo (int) $qty; ?></td>
                            <td><?php echo htmlspecialchars($item->descripcion ?? ''); ?></td>
                            <td class="text-end"><?php echo $currency . ' ' . number_format($unit, 4); ?></td>
                            <td class="text-end"><?php echo $currency . ' ' . number_format($lineTotal, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary fw-bold">
                        <td colspan="4" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_TOTAL', 'Total', 'Total'); ?>:</td>
                        <td class="text-end"><?php echo $currency . ' ' . number_format($totalAmount, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($quotationConfirmed && !empty($items)) : ?>
    <div class="card mb-4 border-success">
        <div class="card-header py-2 bg-success text-white">
            <h5 class="mb-0 small"><i class="fas fa-tasks"></i> <?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP3_TITLE', 'Pre-Quotations', 'Pre-Cotizaciones'); ?></h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP3_DESC', 'Generate work order for each pre-quotation.', 'Generar orden de trabajo para cada pre-cotización.'); ?></p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 12%;"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION', 'Pre-Quotation', 'Pre-Cotización'); ?></th>
                            <th style="width: 48%;"><?php echo $l('COM_ORDENPRODUCCION_DESCRIPCION', 'Description', 'Descripción'); ?></th>
                            <th style="width: 18%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_SUBTOTAL', 'Subtotal', 'Subtotal'); ?></th>
                            <th style="width: 22%;" class="text-center"><?php echo $l('COM_ORDENPRODUCCION_ACTION', 'Action', 'Acción'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) :
                            $preIdRow = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
                            $preNumRow = $preIdRow > 0 ? (trim((string) ($item->pre_cotizacion_number ?? '')) ?: 'PRE-' . $preIdRow) : '—';
                            $descRow = isset($item->descripcion) ? (string) $item->descripcion : '';
                            $subtotalRow = isset($item->subtotal) ? (float) $item->subtotal : 0;
                            $instrUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $quotationId . '&layout=instrucciones_orden&pre_cotizacion_id=' . (int) $preIdRow);
                            ?>
                        <tr>
                            <td class="align-middle"><strong><?php echo htmlspecialchars($preNumRow); ?></strong></td>
                            <td class="align-middle small text-muted"><?php echo htmlspecialchars($descRow !== '' ? $descRow : '—'); ?></td>
                            <td class="align-middle text-end"><?php echo $currency . ' ' . number_format($subtotalRow, 2); ?></td>
                            <td class="align-middle text-center">
                                <?php if ($preIdRow > 0) : ?>
                                    <a href="<?php echo htmlspecialchars($instrUrl); ?>" class="btn btn-sm btn-primary"><?php echo $l('COM_ORDENPRODUCCION_GENERAR_ORDEN_TRABAJO', 'Generate Work Order', 'Generar Orden de Trabajo'); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex gap-2">
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i>
                <?php echo $l('COM_ORDENPRODUCCION_BACK_TO_LIST', 'Back to list', 'Volver a la lista'); ?>
            </a>
            <?php if ($quotationConfirmed) : ?>
            <span class="btn btn-secondary disabled" tabindex="-1" style="opacity: 0.65; cursor: not-allowed;" title="<?php echo htmlspecialchars($editLockedHint); ?>">
                <i class="fas fa-edit"></i>
                <?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>
            </span>
            <?php else : ?>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . '&layout=edit'); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i>
                <?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>
            </a>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <?php if ($quotationConfirmed) : ?>
                <span class="badge bg-success"><i class="fas fa-check"></i> <?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_YA_FINALIZADA', 'Confirmation completed', 'Confirmación finalizada'); ?></span>
            <?php else : ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmarCotizacionModal" id="btnConfirmarCotizacion">
                <i class="fas fa-check-circle"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_COTIZACION', 'Confirm Quotation', 'Confirmar Cotización'); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: archivos opcionales + Finalizar confirmación -->
<div class="modal fade" id="confirmarCotizacionModal" tabindex="-1" aria-labelledby="confirmarCotizacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmarCotizacionModalLabel"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_COTIZACION', 'Confirm Quotation', 'Confirmar Cotización'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=cotizacion.finalizeConfirmacionCotizacion'); ?>" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $quotationId; ?>">
                    <p class="text-muted small"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_MODAL_FILES_HELP', 'Optional files. You can finish without attaching documents.', 'Archivos opcionales. Puede finalizar sin adjuntar documentos.'); ?></p>
                    <div class="mb-3">
                        <label for="cotizacion_aprobada_file" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_COTIZACION_APROBADA_FILE', 'Approved quotation', 'Cotización aprobada'); ?></label>
                        <input type="file" name="cotizacion_aprobada" id="cotizacion_aprobada_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if ($pathCotAprobada !== '') :
                            $uA = (strpos($pathCotAprobada, 'http') === 0) ? $pathCotAprobada : Uri::root() . ltrim($pathCotAprobada, '/');
                            ?>
                            <div class="small mt-1">
                                <i class="fas fa-paperclip text-success"></i> <?php echo htmlspecialchars(basename($pathCotAprobada)); ?>
                                <button type="button" class="btn btn-link btn-sm py-0 cotizacion-confirm-file-view" data-file-url="<?php echo htmlspecialchars($uA); ?>"><?php echo $l('COM_ORDENPRODUCCION_VIEW', 'View', 'Ver'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="orden_compra_file" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_ORDEN_COMPRA_FILE', 'Purchase order', 'Orden de compra'); ?></label>
                        <input type="file" name="orden_compra" id="orden_compra_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if ($pathOrdenCompra !== '') :
                            $uO = (strpos($pathOrdenCompra, 'http') === 0) ? $pathOrdenCompra : Uri::root() . ltrim($pathOrdenCompra, '/');
                            ?>
                            <div class="small mt-1">
                                <i class="fas fa-paperclip text-success"></i> <?php echo htmlspecialchars(basename($pathOrdenCompra)); ?>
                                <button type="button" class="btn btn-link btn-sm py-0 cotizacion-confirm-file-view" data-file-url="<?php echo htmlspecialchars($uO); ?>"><?php echo $l('COM_ORDENPRODUCCION_VIEW', 'View', 'Ver'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $l('JCANCEL', 'Cancel', 'Cancelar'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_FINALIZAR', 'Finish confirmation', 'Finalizar confirmación'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="cotizacionConfirmFileModal" tabindex="-1" aria-labelledby="cotizacionConfirmFileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cotizacionConfirmFileModalLabel"><?php echo $l('COM_ORDENPRODUCCION_VIEW', 'View', 'Ver'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $l('JCLOSE', 'Close', 'Cerrar'); ?>"></button>
            </div>
            <div class="modal-body p-0" style="min-height: 70vh;">
                <iframe id="cotizacionConfirmFileIframe" style="width:100%; height:70vh; border:0;" title=""></iframe>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var modalFile = document.getElementById('cotizacionConfirmFileModal');
    var iframeFile = document.getElementById('cotizacionConfirmFileIframe');
    if (modalFile && iframeFile) {
        document.querySelectorAll('.cotizacion-confirm-file-view').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var url = this.getAttribute('data-file-url');
                if (url) {
                    iframeFile.src = url;
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalFile).show();
                    }
                }
            });
        });
        modalFile.addEventListener('hidden.bs.modal', function() {
            iframeFile.src = 'about:blank';
        });
    }
})();
</script>

<?php if (!empty($items)) : ?>
<!-- Modal: Pre-Cotización details (same as edit view) -->
<div class="modal fade" id="precotizacionDetailModal" tabindex="-1" aria-labelledby="precotizacionDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="precotizacionDetailModalLabel"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION', 'Pre-Quotation', 'Pre-Cotización'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
        contentEl.innerHTML = '<div class="p-3 text-muted text-center"><span class="spinner-border spinner-border-sm me-2"></span><?php echo addslashes($l('COM_ORDENPRODUCCION_LOADING', 'Loading...', 'Cargando...')); ?></div>';
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();
        } else {
            modal.classList.add('show');
            modal.style.display = 'block';
        }
        var url = precotizacionDetailBase + 'index.php?option=com_ordenproduccion&task=ajax.getPrecotizacionDetails&format=raw&id=' + encodeURIComponent(preId) + '&' + precotizacionDetailToken;
        fetch(url).then(function(r) { return r.text(); }).then(function(html) {
            contentEl.innerHTML = html || '<p class="p-3 text-muted"><?php echo addslashes($l('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND', 'Pre-quotation not found.', 'Pre-cotización no encontrada.')); ?></p>';
        }).catch(function() {
            contentEl.innerHTML = '<p class="p-3 text-danger"><?php echo addslashes($l('COM_ORDENPRODUCCION_ERROR_LOADING', 'Error loading.', 'Error al cargar.')); ?></p>';
        });
    });
})();
</script>
<?php endif; ?>
