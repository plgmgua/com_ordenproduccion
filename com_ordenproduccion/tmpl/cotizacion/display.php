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
$instruccionesFacturacionValue = isset($quotation->instrucciones_facturacion) ? (string) $quotation->instrucciones_facturacion : '';

$itemsWithLineDetalles = $this->itemsWithLineDetalles ?? [];
$pliegoPaperTypesIo = $this->pliegoPaperTypesModal ?? [];
$pliegoSizesIo = $this->pliegoSizesModal ?? [];
$elementosIo = $this->elementosModal ?? [];
$lineDetallesTableOk = true;
$precotMdlInstr = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
    ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
if ($precotMdlInstr) {
    $lineDetallesTableOk = $precotMdlInstr->lineDetallesTableExists();
}
$instruccionesSaveJsonUrl = Route::_('index.php?option=com_ordenproduccion&task=cotizacion.saveInstruccionesOrden&format=json', false);
$instruccionesSavedMsgJs = json_encode(Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVED_FOR_LATER'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$instruccionesJsErrNoForm = json_encode($l('COM_ORDENPRODUCCION_INSTRUCCIONES_MODAL_NO_FORM', 'Could not find the form for this pre-quotation.', 'No se encontró el formulario para esta pre-cotización.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$instruccionesJsErrSave = json_encode($l('COM_ORDENPRODUCCION_INSTRUCCIONES_MODAL_SAVE_ERROR', 'Could not save instructions.', 'No se pudieron guardar las instrucciones.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$instruccionesJsErrNet = json_encode($l('COM_ORDENPRODUCCION_INSTRUCCIONES_MODAL_NETWORK_ERROR', 'Network error. Try again.', 'Error de red. Intente de nuevo.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$paperNamesIo = [];
foreach ($pliegoPaperTypesIo as $p) {
    $paperNamesIo[(int) $p->id] = $p->name ?? '';
}
$sizeNamesIo = [];
foreach ($pliegoSizesIo as $s) {
    $sizeNamesIo[(int) $s->id] = $s->name ?? '';
}
$elementosByIdIo = [];
foreach ($elementosIo as $el) {
    $elementosByIdIo[(int) $el->id] = $el;
}
$instruccionesModalCanSave = $lineDetallesTableOk && !empty($itemsWithLineDetalles);
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
                        <th style="width: 34%;"><?php echo $l('COM_ORDENPRODUCCION_DESCRIPCION', 'Description', 'Descripción'); ?></th>
                        <th style="width: 12%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_PRECIO_UNIDAD', 'Unit price', 'Precio unidad.'); ?></th>
                        <th style="width: 12%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_SUBTOTAL', 'Subtotal', 'Subtotal'); ?></th>
                        <?php if ($quotationConfirmed) : ?>
                        <th style="width: 8%;" class="text-center"><?php echo $l('COM_ORDENPRODUCCION_ACTION', 'Action', 'Acción'); ?></th>
                        <?php endif; ?>
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
                            <?php if ($quotationConfirmed) : ?>
                            <td class="text-center align-middle">
                                <?php if ($preId > 0) : ?>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-success instrucciones-orden-trigger px-2 py-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#instruccionesOrdenModal"
                                        data-pre-cotizacion-id="<?php echo (int) $preId; ?>"
                                        data-quotation-id="<?php echo (int) $quotationId; ?>"
                                        title="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_GENERAR_ORDEN_TRABAJO', 'Generate Work Order', 'Generar Orden de Trabajo')); ?>">
                                        <i class="fas fa-print" aria-hidden="true"></i>
                                        <span class="visually-hidden"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_GENERAR_ORDEN_TRABAJO', 'Generate Work Order', 'Generar Orden de Trabajo')); ?></span>
                                    </button>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary fw-bold">
                        <?php if ($quotationConfirmed) : ?>
                        <td colspan="4" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_TOTAL', 'Total', 'Total'); ?>:</td>
                        <td class="text-end"><?php echo $currency . ' ' . number_format($totalAmount, 2); ?></td>
                        <td></td>
                        <?php else : ?>
                        <td colspan="4" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_TOTAL', 'Total', 'Total'); ?>:</td>
                        <td class="text-end"><?php echo $currency . ' ' . number_format($totalAmount, 2); ?></td>
                        <?php endif; ?>
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
                    <div class="mb-3">
                        <label for="instrucciones_facturacion_confirm" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_STEP2_TITLE', 'Billing Instructions', 'Instrucciones de Facturación'); ?></label>
                        <textarea name="instrucciones_facturacion" id="instrucciones_facturacion_confirm" class="form-control form-control-sm" rows="3" maxlength="65535" autocomplete="off" data-lpignore="true" data-1p-ignore="true"><?php echo htmlspecialchars($instruccionesFacturacionValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
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

<!-- Modal: Instrucciones para orden de trabajo (pre-cotización) — saved via saveInstruccionesOrden + instrucciones_save_only -->
<div class="modal fade" id="instruccionesOrdenModal" tabindex="-1" aria-labelledby="instruccionesOrdenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="instruccionesOrdenModalLabel">
                    <i class="fas fa-clipboard-list me-1"></i>
                    <?php echo $l('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_TITLE', 'Instructions for work order', 'Instrucciones para orden de trabajo'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $l('JCLOSE', 'Close', 'Cerrar'); ?>"></button>
            </div>
            <div class="modal-body">
                <div id="instruccionesOrdenJsAlert" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <?php if (!$lineDetallesTableOk) : ?>
                    <p class="text-warning mb-0"><?php echo $l('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_TABLE_MISSING', 'Detalles table is missing. Run migration 3.91.0.', 'Falta la tabla de detalles. Ejecute la migración 3.91.0.'); ?></p>
                <?php elseif (empty($itemsWithLineDetalles)) : ?>
                    <p class="text-muted mb-0"><?php echo $l('COM_ORDENPRODUCCION_INSTRUCCIONES_MODAL_EMPTY', 'No instruction fields are loaded for this quotation.', 'No hay campos de instrucción cargados para esta cotización.'); ?></p>
                <?php else : ?>
                    <?php foreach ($itemsWithLineDetalles as $blockIo) :
                        $preIdBlock = (int) $blockIo->pre_cotizacion_id;
                        $linesWithConceptsIo = $blockIo->linesWithConcepts ?? [];
                        ?>
                    <div class="instrucciones-orden-block d-none" data-pre-cotizacion-id="<?php echo $preIdBlock; ?>">
                        <form method="post" class="instrucciones-orden-form-inner" id="instrucciones-orden-form-<?php echo $preIdBlock; ?>" action="#">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <input type="hidden" name="quotation_id" value="<?php echo (int) $quotationId; ?>">
                            <input type="hidden" name="pre_cotizacion_id" value="<?php echo $preIdBlock; ?>">
                            <input type="hidden" name="instrucciones_save_only" value="1">
                            <?php
                            $preNumBlock = trim((string) ($blockIo->pre_cotizacion_number ?? ''));
                            if ($preNumBlock === '') {
                                $preNumBlock = 'PRE-' . $preIdBlock;
                            }
                            $preDescBlock = trim((string) ($blockIo->descripcion ?? ''));
                            $preMedidasBlock = trim((string) ($blockIo->medidas ?? ''));
                            ?>
                            <div class="border rounded bg-light p-3 mb-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($preNumBlock); ?></span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="fw-semibold small text-uppercase text-muted mb-1"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION', 'Description', 'Descripción'); ?></div>
                                        <div class="small mb-0 instrucciones-pre-descripcion"><?php echo $preDescBlock !== '' ? nl2br(htmlspecialchars($preDescBlock, ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">—</span>'; ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-semibold small text-uppercase text-muted mb-1"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION_MEDIDAS', 'Dimensions', 'Medidas'); ?></div>
                                        <div class="small mb-0 instrucciones-pre-medidas"><?php echo $preMedidasBlock !== '' ? nl2br(htmlspecialchars($preMedidasBlock, ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">—</span>'; ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php foreach ($linesWithConceptsIo as $rowIo) :
                                $lineIo = $rowIo->line;
                                $conceptsIo = $rowIo->concepts;
                                $detallesIo = $rowIo->detalles;
                                $lineIdIo = (int) $lineIo->id;
                                $lineTypeIo = isset($lineIo->line_type) ? (string) $lineIo->line_type : 'pliego';
                                $lineLabelIo = '—';
                                if ($lineTypeIo === 'envio') {
                                    $lineLabelIo = isset($lineIo->envio_name) ? 'Envío: ' . $lineIo->envio_name : 'Envío';
                                } elseif ($lineTypeIo === 'elementos' && !empty($lineIo->elemento_id) && isset($elementosByIdIo[(int) $lineIo->elemento_id])) {
                                    $lineLabelIo = $elementosByIdIo[(int) $lineIo->elemento_id]->name ?? ('ID ' . $lineIo->elemento_id);
                                } else {
                                    $paperNameIo = $paperNamesIo[$lineIo->paper_type_id ?? 0] ?? ('ID ' . (int) ($lineIo->paper_type_id ?? 0));
                                    $sizeNameIo = $sizeNamesIo[$lineIo->size_id ?? 0] ?? ('ID ' . (int) ($lineIo->size_id ?? 0));
                                    $lineLabelIo = $paperNameIo . ' · ' . $sizeNameIo . ' · ' . (int) $lineIo->quantity;
                                }
                                $tipoElementoIo = isset($lineIo->tipo_elemento) && trim((string) $lineIo->tipo_elemento) !== '' ? trim((string) $lineIo->tipo_elemento) : $lineLabelIo;
                                ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light py-2">
                                    <strong><?php echo htmlspecialchars($tipoElementoIo); ?></strong>
                                    <span class="text-muted small ms-2"><?php echo $lineLabelIo !== $tipoElementoIo ? htmlspecialchars($lineLabelIo) : ''; ?></span>
                                </div>
                                <div class="card-body py-2">
                                    <?php foreach ($conceptsIo as $conceptoKeyIo => $conceptoLabelIo) :
                                        $valueIo = isset($detallesIo[$conceptoKeyIo]) ? htmlspecialchars((string) $detallesIo[$conceptoKeyIo], ENT_QUOTES, 'UTF-8') : '';
                                        $nameIo = 'detalle[' . $lineIdIo . '][' . $conceptoKeyIo . ']';
                                        $idIo = 'detalle_pre' . $preIdBlock . '_' . $lineIdIo . '_' . preg_replace('/[^a-z0-9_]/', '_', (string) $conceptoKeyIo);
                                        ?>
                                    <div class="mb-2">
                                        <label for="<?php echo htmlspecialchars($idIo, ENT_QUOTES, 'UTF-8'); ?>" class="form-label small mb-1"><?php echo htmlspecialchars($conceptoLabelIo); ?></label>
                                        <textarea name="<?php echo htmlspecialchars($nameIo, ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($idIo, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm instrucciones-orden-detalle" rows="2" autocomplete="off" data-lpignore="true" data-1p-ignore="true"><?php echo $valueIo; ?></textarea>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $l('JCANCEL', 'Cancel', 'Cancelar'); ?></button>
                <button type="button" class="btn btn-primary" id="instruccionesOrdenNextBtn"<?php echo $instruccionesModalCanSave ? '' : ' disabled'; ?>><?php echo $l('COM_ORDENPRODUCCION_CONFIRMAR_NEXT', 'Next', 'Siguiente'); ?></button>
            </div>
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
    function stripInputPlaceholders(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('textarea, input').forEach(function(el) {
            el.removeAttribute('placeholder');
        });
    }
    var confirmModalEl = document.getElementById('confirmarCotizacionModal');
    if (confirmModalEl) {
        stripInputPlaceholders(confirmModalEl);
        confirmModalEl.addEventListener('show.bs.modal', function() {
            stripInputPlaceholders(confirmModalEl);
        });
    }
    var instrModal = document.getElementById('instruccionesOrdenModal');
    var instrNext = document.getElementById('instruccionesOrdenNextBtn');
    var saveUrl = <?php echo json_encode($instruccionesSaveJsonUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var savedMsg = <?php echo $instruccionesSavedMsgJs; ?>;
    if (!instrModal || !instrNext) {
        return;
    }
    stripInputPlaceholders(instrModal);
    function syncInstruccionesBlocksVisible() {
        var preId = String(instrModal.dataset.preCotizacionId || '');
        document.querySelectorAll('.instrucciones-orden-block').forEach(function(el) {
            var pid = String(el.getAttribute('data-pre-cotizacion-id') || '');
            el.classList.toggle('d-none', pid !== preId);
        });
    }
    document.querySelectorAll('[data-bs-target="#instruccionesOrdenModal"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            instrModal.dataset.preCotizacionId = this.getAttribute('data-pre-cotizacion-id') || '';
            instrModal.dataset.quotationId = this.getAttribute('data-quotation-id') || '';
        });
    });
    instrModal.addEventListener('show.bs.modal', function(e) {
        stripInputPlaceholders(instrModal);
        var ja = document.getElementById('instruccionesOrdenJsAlert');
        if (ja) {
            ja.classList.add('d-none');
            ja.textContent = '';
        }
        var t = e.relatedTarget;
        if (t && typeof t.closest === 'function') {
            var opener = t.closest('[data-pre-cotizacion-id]');
            if (opener) {
                instrModal.dataset.preCotizacionId = opener.getAttribute('data-pre-cotizacion-id') || '';
                instrModal.dataset.quotationId = opener.getAttribute('data-quotation-id') || '';
            }
        } else if (t && typeof t.getAttribute === 'function' && t.getAttribute('data-pre-cotizacion-id')) {
            instrModal.dataset.preCotizacionId = t.getAttribute('data-pre-cotizacion-id') || '';
            instrModal.dataset.quotationId = t.getAttribute('data-quotation-id') || '';
        }
        syncInstruccionesBlocksVisible();
    });
    instrModal.addEventListener('shown.bs.modal', function() {
        syncInstruccionesBlocksVisible();
    });
    instrNext.addEventListener('click', function() {
        if (instrNext.disabled) {
            return;
        }
        var preId = instrModal.dataset.preCotizacionId || '';
        var form = document.getElementById('instrucciones-orden-form-' + preId);
        var ja = document.getElementById('instruccionesOrdenJsAlert');
        if (!form) {
            if (ja) {
                ja.textContent = <?php echo $instruccionesJsErrNoForm; ?>;
                ja.classList.remove('d-none');
            }
            return;
        }
        instrNext.disabled = true;
        var fd = new FormData(form);
        fetch(saveUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(r) {
                return r.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        return { success: false, message: text ? text.substring(0, 200) : 'Error' };
                    }
                });
            })
            .then(function(data) {
                if (data && data.success) {
                    var bs = typeof bootstrap !== 'undefined' && bootstrap.Modal ? bootstrap.Modal.getInstance(instrModal) : null;
                    if (bs) {
                        bs.hide();
                    }
                    var el = document.createElement('div');
                    el.className = 'alert alert-success alert-dismissible fade show';
                    el.setAttribute('role', 'alert');
                    el.innerHTML = '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                        + (data.message || savedMsg);
                    var container = document.querySelector('.cotizacion-container');
                    if (container) {
                        container.insertBefore(el, container.firstChild);
                    }
                    window.setTimeout(function() {
                        if (el && el.parentNode) {
                            el.remove();
                        }
                    }, 8000);
                } else if (ja) {
                    ja.textContent = (data && data.message) ? data.message : <?php echo $instruccionesJsErrSave; ?>;
                    ja.classList.remove('d-none');
                }
            })
            .catch(function() {
                if (ja) {
                    ja.textContent = <?php echo $instruccionesJsErrNet; ?>;
                    ja.classList.remove('d-none');
                }
            })
            .finally(function() {
                instrNext.disabled = false;
            });
    });
})();
</script>
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
