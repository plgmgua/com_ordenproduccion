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
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . '&layout=edit'); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i>
                <?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>
            </a>
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
                    <td><?php echo $quotation->quote_date ? HTMLHelper::_('date', $quotation->quote_date, 'Y-m-d') : '—'; ?></td>
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
                        $subtotal = isset($item->subtotal) ? (float) $item->subtotal : 0;
                        $unit = $qty > 0 ? ($subtotal / $qty) : 0;
                    ?>
                        <tr>
                            <td><?php if ($preId > 0) : ?><a href="#" class="precotizacion-detail-link" data-pre-id="<?php echo $preId; ?>" data-pre-number="<?php echo htmlspecialchars($preNum); ?>"><?php echo htmlspecialchars($preNum); ?></a><?php else : ?><?php echo htmlspecialchars($preNum); ?><?php endif; ?></td>
                            <td><?php echo (int) $qty; ?></td>
                            <td><?php echo htmlspecialchars($item->descripcion ?? ''); ?></td>
                            <td class="text-end"><?php echo $currency . ' ' . number_format($unit, 4); ?></td>
                            <td class="text-end"><?php echo $currency . ' ' . number_format($subtotal, 2); ?></td>
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

    <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex gap-2">
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i>
                <?php echo $l('COM_ORDENPRODUCCION_BACK_TO_LIST', 'Back to list', 'Volver a la lista'); ?>
            </a>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . '&layout=edit'); ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i>
                <?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>
            </a>
        </div>
        <div>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#confirmarCotizacionModal" id="btnConfirmarCotizacion">
                <i class="fas fa-check-circle"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_COTIZACION', 'Confirm Quotation', 'Confirmar Cotización'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Cotización (3 steps) -->
<div class="modal fade" id="confirmarCotizacionModal" tabindex="-1" aria-labelledby="confirmarCotizacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmarCotizacionModalLabel"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_COTIZACION', 'Confirm Quotation', 'Confirmar Cotización'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="confirmar-steps mb-3">
                    <span class="badge bg-secondary me-1 confirmar-step-dot" data-step="1">1</span>
                    <span class="badge bg-secondary me-1 confirmar-step-dot" data-step="2">2</span>
                    <span class="badge bg-secondary confirmar-step-dot" data-step="3">3</span>
                </div>

                <!-- Step 1: Upload signed document -->
                <div class="confirmar-step-pane" id="confirmarStep1" data-step="1">
                    <h6 class="mb-2"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP1_TITLE', 'Proof of acceptance', 'Comprobante de aceptación'); ?></h6>
                    <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP1_DESC', 'Upload the signed quotation as proof of acceptance (PDF or image).', 'Subir la cotización firmada como comprobante de aceptación (PDF o imagen).'); ?></p>
                    <?php $signedPath = isset($quotation->signed_document_path) ? trim((string) $quotation->signed_document_path) : ''; ?>
                    <?php if ($signedPath !== '') : ?>
                        <p class="small text-success mb-2"><i class="fas fa-file"></i> <?php echo htmlspecialchars(basename($signedPath)); ?> <a href="<?php echo htmlspecialchars(Uri::root() . $signedPath); ?>" target="_blank" class="ms-1"><?php echo $l('COM_ORDENPRODUCCION_VIEW', 'View', 'Ver'); ?></a></p>
                    <?php endif; ?>
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=cotizacion.saveConfirmarStep1'); ?>" method="post" enctype="multipart/form-data" id="confirmarFormStep1">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $quotationId; ?>">
                        <div class="mb-3">
                            <label for="signed_document" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP1_TITLE', 'Proof of acceptance', 'Comprobante de aceptación'); ?></label>
                            <input type="file" name="signed_document" id="signed_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-outline-primary"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_SAVE', 'Save', 'Guardar'); ?></button>
                            <button type="button" class="btn btn-primary btn-confirmar-next" data-next="2"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_NEXT', 'Next', 'Siguiente'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Step 2: Instrucciones de Facturación -->
                <div class="confirmar-step-pane" id="confirmarStep2" data-step="2" style="display:none;">
                    <h6 class="mb-2"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP2_TITLE', 'Billing Instructions', 'Instrucciones de Facturación'); ?></h6>
                    <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP2_DESC', 'Enter billing instructions for this quotation.', 'Indique las instrucciones de facturación para esta cotización.'); ?></p>
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=cotizacion.saveConfirmarStep2'); ?>" method="post" id="confirmarFormStep2">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $quotationId; ?>">
                        <div class="mb-3">
                            <label for="instrucciones_facturacion" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP2_TITLE', 'Billing Instructions', 'Instrucciones de Facturación'); ?></label>
                            <textarea name="instrucciones_facturacion" id="instrucciones_facturacion" class="form-control" rows="4" placeholder=""><?php echo htmlspecialchars(isset($quotation->instrucciones_facturacion) ? (string) $quotation->instrucciones_facturacion : ''); ?></textarea>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-outline-primary"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_SAVE', 'Save', 'Guardar'); ?></button>
                            <button type="button" class="btn btn-primary btn-confirmar-next" data-next="3"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_NEXT', 'Next', 'Siguiente'); ?></button>
                        </div>
                    </form>
                </div>

                <!-- Step 3: Pre-cotizaciones + Generar Orden de Trabajo -->
                <div class="confirmar-step-pane" id="confirmarStep3" data-step="3" style="display:none;">
                    <h6 class="mb-2"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP3_TITLE', 'Pre-Quotations', 'Pre-Cotizaciones'); ?></h6>
                    <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP3_DESC', 'Generate work order for each pre-quotation.', 'Generar orden de trabajo para cada pre-cotización.'); ?></p>
                    <?php if (empty($items)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_LINES', 'No lines.', 'Sin líneas.'); ?></p>
                    <?php else : ?>
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
                                        $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
                                        $preNum = $preId > 0 ? (trim((string) ($item->pre_cotizacion_number ?? '')) ?: 'PRE-' . $preId) : '—';
                                        $desc = isset($item->descripcion) ? (string) $item->descripcion : '';
                                        $subtotal = isset($item->subtotal) ? (float) $item->subtotal : 0;
                                        $ordenUrl = $preId > 0 ? Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&pre_cotizacion_id=' . $preId . '&quotation_id=' . $quotationId) : '#';
                                    ?>
                                        <tr>
                                            <td class="align-middle"><strong><?php echo htmlspecialchars($preNum); ?></strong></td>
                                            <td class="align-middle small text-muted"><?php echo htmlspecialchars($desc !== '' ? $desc : '—'); ?></td>
                                            <td class="align-middle text-end"><?php echo $currency . ' ' . number_format($subtotal, 2); ?></td>
                                            <td class="align-middle text-center">
                                                <?php if ($preId > 0) : ?>
                                                    <a href="<?php echo htmlspecialchars($ordenUrl); ?>" class="btn btn-sm btn-primary" target="_blank"><?php echo $l('COM_ORDENPRODUCCION_GENERAR_ORDEN_TRABAJO', 'Generate Work Order', 'Generar Orden de Trabajo'); ?></a>
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('confirmarCotizacionModal');
    if (!modal) return;
    var steps = [1, 2, 3];
    function showStep(step) {
        steps.forEach(function(s) {
            var pane = document.getElementById('confirmarStep' + s);
            var dot = document.querySelector('.confirmar-step-dot[data-step="' + s + '"]');
            if (pane) pane.style.display = (s === step) ? 'block' : 'none';
            if (dot) {
                dot.classList.remove('bg-primary', 'bg-secondary');
                dot.classList.add(s === step ? 'bg-primary' : 'bg-secondary');
            }
        });
    }
    modal.addEventListener('show.bs.modal', function() { showStep(1); });
    modal.addEventListener('hidden.bs.modal', function() { showStep(1); });
    modal.addEventListener('click', function(e) {
        var nextBtn = e.target && e.target.closest && e.target.closest('.btn-confirmar-next');
        if (nextBtn) {
            var next = parseInt(nextBtn.getAttribute('data-next'), 10);
            if (next >= 1 && next <= 3) showStep(next);
        }
    });
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
