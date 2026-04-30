<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

// Get today's date for default quote_date
$today = Factory::getDate()->format('Y-m-d');
// Fallback for labels so we never show raw language keys
$l = function($key, $fallbackEn, $fallbackEs = null) {
    $t = Text::_($key);
    if ($t === $key || (is_string($t) && strpos($t, 'COM_ORDENPRODUCCION_') === 0)) {
        $lang = Factory::getApplication()->getLanguage()->getTag();
        return (strpos($lang, 'es') !== false && $fallbackEs !== null) ? $fallbackEs : $fallbackEn;
    }
    return $t;
};
?>

<?php
$isEdit = !empty($this->quotation);
$quotationId = $isEdit ? (int) $this->quotation->id : 0;
?>
<div class="cotizacion-container">
    <div class="cotizacion-header">
        <h2>
            <i class="fas fa-file-invoice"></i>
            <?php echo $isEdit ? $l('COM_ORDENPRODUCCION_EDIT_QUOTATION_TITLE', 'Edit Quotation', 'Editar cotización') : $l('COM_ORDENPRODUCCION_NEW_QUOTATION_TITLE', 'Create New Quotation', 'Crear nueva cotización'); ?>
        </h2>
        <p class="form-note">
            <?php echo $l('COM_ORDENPRODUCCION_QUOTATION_FORM_NOTE', 'Fill in client information and quotation details.', 'Complete la información del cliente y los detalles de la cotización.'); ?>
        </p>
    </div>

    <form id="quotationForm" onsubmit="submitQuotationForm(event)">
        <?php if ($quotationId) : ?>
        <input type="hidden" name="quotation_id" id="quotation_id" value="<?php echo $quotationId; ?>">
        <?php endif; ?>
        <!-- Client ID is in the client table below (visible field for API) -->

        <!-- Client Information Section -->
        <div class="client-info-section">
            <h4 class="section-title">
                <i class="fas fa-user"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CLIENT_INFORMATION', 'Client Information', 'Información del cliente'); ?>
            </h4>
            
            <table class="client-table">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php echo $l('COM_ORDENPRODUCCION_CLIENT_NAME', 'Client Name', 'Nombre del cliente'); ?></th>
                        <th style="width: 15%;"><?php echo $l('COM_ORDENPRODUCCION_CLIENT_ID_API', 'Client ID (API)', 'Client ID (API)'); ?></th>
                        <th style="width: 15%;"><?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?></th>
                        <th style="width: 25%;"><?php echo $l('COM_ORDENPRODUCCION_ADDRESS', 'Address', 'Dirección'); ?></th>
                        <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_SALES_AGENT', 'Sales Agent', 'Agente de ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        $clientNamePrepop = (string) ($this->clientName ?? '');
                        $clientIdPrepop   = (string) ($this->clientId ?? '');
                        $clientNitPrepop  = (string) ($this->clientNit ?? '');
                        $salesAgentPrepop = (string) ($this->salesAgent ?? '');
                        $readonlyClientName = $clientNamePrepop !== '';
                        $readonlyClientId   = $clientIdPrepop !== '';
                        $readonlyClientNit  = $clientNitPrepop !== '';
                        $readonlySalesAgent = $salesAgentPrepop !== '';
                        ?>
                        <td>
                            <input type="text" 
                                   id="client_name" 
                                   name="client_name" 
                                   value="<?php echo htmlspecialchars($clientNamePrepop); ?>" 
                                   <?php if ($readonlyClientName) : ?>readonly class="readonly-prepop"<?php endif; ?>
                                   required 
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_CLIENT_NAME', 'Client Name', 'Nombre del cliente'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="client_id" 
                                   name="client_id" 
                                   value="<?php echo htmlspecialchars($clientIdPrepop); ?>" 
                                   <?php if ($readonlyClientId) : ?>readonly class="readonly-prepop"<?php endif; ?>
                                   placeholder="e.g. 7"
                                   title="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_CLIENT_ID_API_DESC', 'External client ID for API (e.g. Odoo partner id). Required for pending pre-cotizaciones API.', 'ID de cliente externo para API (ej. id partner Odoo). Requerido para la API de pre-cotizaciones pendientes.')); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="client_nit" 
                                   name="client_nit" 
                                   value="<?php echo htmlspecialchars($clientNitPrepop); ?>" 
                                   <?php if ($readonlyClientNit) : ?>readonly class="readonly-prepop"<?php endif; ?>
                                   required 
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?>">
                        </td>
                        <td>
                            <?php $addressValue = ($this->clientAddress ?? '') !== '' ? $this->clientAddress : 'Ciudad'; ?>
                            <input type="text" 
                                   id="client_address" 
                                   name="client_address" 
                                   value="<?php echo htmlspecialchars($addressValue); ?>" 
                                   required 
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_ADDRESS', 'Address', 'Dirección'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="sales_agent" 
                                   name="sales_agent" 
                                   value="<?php echo htmlspecialchars($salesAgentPrepop); ?>" 
                                   <?php if ($readonlySalesAgent) : ?>readonly class="readonly-prepop"<?php endif; ?>
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_SALES_AGENT', 'Sales Agent', 'Agente de ventas'); ?>">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Contact Information Section -->
        <div class="contact-info-section">
            <h4 class="section-title">
                <i class="fas fa-address-book"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CONTACT_INFORMATION', 'Contact Information', 'Información de contacto'); ?>
            </h4>
            
            <table class="contact-table">
                <thead>
                    <tr>
                        <th style="width: 50%;"><?php echo $l('COM_ORDENPRODUCCION_CONTACT_NAME', 'Contact Name', 'Nombre de contacto'); ?></th>
                        <th style="width: 30%;"><?php echo $l('COM_ORDENPRODUCCION_CONTACT_PHONE', 'Contact Phone', 'Teléfono de contacto'); ?></th>
                        <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_QUOTE_DATE', 'Quotation Date', 'Fecha de cotización'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php $contactNameValue = $isEdit ? ($this->quotation->contact_name ?? '') : ($this->contactPersonName ?? ''); ?>
                            <input type="text" 
                                   id="contact_name" 
                                   name="contact_name" 
                                   value="<?php echo htmlspecialchars($contactNameValue); ?>"
                                   readonly class="readonly-prepop"
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_CONTACT_NAME', 'Contact Name', 'Nombre de contacto'); ?>">
                        </td>
                        <td>
                            <?php $contactPhoneValue = $isEdit ? ($this->quotation->contact_phone ?? '') : ($this->contactPersonPhone ?? ''); ?>
                            <input type="text" 
                                   id="contact_phone" 
                                   name="contact_phone" 
                                   value="<?php echo htmlspecialchars($contactPhoneValue); ?>"
                                   readonly class="readonly-prepop"
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_CONTACT_PHONE', 'Contact Phone', 'Teléfono de contacto'); ?>">
                        </td>
                        <td>
                            <input type="date" 
                                   id="quote_date" 
                                   name="quote_date" 
                                   value="<?php echo $isEdit && !empty($this->quotation->quote_date) ? htmlspecialchars(CotizacionHelper::formatQuoteDateYmd($this->quotation->quote_date)) : $today; ?>" 
                                   required>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Quotation Lines: Pre-Cotizaciones + custom description -->
        <div class="items-table-section">
            <h4 class="items-table-title">
                <i class="fas fa-list"></i>
                <?php echo $l('COM_ORDENPRODUCCION_QUOTATION_ITEMS', 'Quotation Details', 'Detalles de la cotización'); ?>
            </h4>
            <p class="form-note"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_LINES_PRECOTIZACION_NOTE', 'Add lines by selecting a Pre-Quotation (description is filled automatically). Set quantity on each line (must be greater than zero before saving). Total is the sum of all line values.', 'Agregue líneas eligiendo una Pre-Cotización (la descripción se copia sola). Indique la cantidad en cada línea (debe ser mayor que cero para poder guardar). El total es la suma de todos los valores.'); ?></p>
            <?php if (empty($this->preCotizacionesList)) : ?>
                <p class="alert alert-info"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_NO_PRE_COTIZACIONES', 'You have no Pre-Quotations yet. Create one from Pre-Cotizaciones first.', 'Aún no tiene Pre-Cotizaciones. Cree una en Pre-Cotizaciones primero.'); ?></p>
            <?php endif; ?>
            <div class="cotizacion-add-line-block mb-2">
                <div class="cotizacion-add-line-row-first">
                    <label class="me-2"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION_SELECT', 'Pre-Quotation', 'Pre-Cotización'); ?></label>
                    <select id="precotizacionSelect" class="form-select form-select-sm cotizacion-precotizacion-select">
                        <option value=""><?php echo $l('COM_ORDENPRODUCCION_SELECT_PRE_COTIZACION', 'Select Pre-Quotation...', 'Seleccionar Pre-Cotización...'); ?></option>
                        <?php
                        $precotizacionDescriptions = [];
                        $initialWarmupPreId        = !$isEdit ? (int) ($this->initialPrecotizacionId ?? 0) : 0;
                        foreach ($this->preCotizacionesList ?? [] as $pre) :
                            $desc = isset($pre->descripcion) ? trim((string) $pre->descripcion) : '';
                            $precotizacionDescriptions[(int) $pre->id] = $desc;
                            $label = $pre->number . ($desc !== '' ? ' — ' . $desc : '');
                            if (mb_strlen($label) > 120) {
                                $label = mb_substr($label, 0, 117) . '...';
                            }
                            $precotIdLoop = (int) $pre->id;
                            $warmSelected = $initialWarmupPreId > 0 && $initialWarmupPreId === $precotIdLoop;
                            $tcAttr = '';
                            if (isset($pre->total_con_tarjeta) && $pre->total_con_tarjeta !== null && $pre->total_con_tarjeta !== '') {
                                $tcAttr = ' data-total-con-tarjeta="' . htmlspecialchars(number_format((float) $pre->total_con_tarjeta, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '"';
                            }
                        ?>
                            <?php /* option value = pre_cotizacion.id (PK). Label uses $pre->number (PRE-xxxxx), never mix them. */ ?>
                            <option value="<?php echo (int) $pre->id; ?>" <?php echo $warmSelected ? ' selected' : ''; ?> data-total="<?php echo number_format($pre->total, 2, '.', ''); ?>" data-number="<?php echo htmlspecialchars($pre->number); ?>" data-descripcion="<?php echo htmlspecialchars($desc); ?>"<?php echo $tcAttr; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cotizacion-add-line-row-second d-flex flex-wrap align-items-end gap-2">
                    <div id="cotizacion-add-line-cantidad-wrap" class="cotizacion-add-line-cantidad-wrap d-none">
                    <div class="cotizacion-add-cantidad">
                        <label class="me-1"><?php echo $l('COM_ORDENPRODUCCION_CANTIDAD', 'Qty', 'Cantidad'); ?> <span class="text-danger">*</span></label>
                        <input type="number" id="precotizacionCantidad" class="form-control form-control-sm text-end" style="width: 70px;" min="1" step="1" value="0" aria-label="Cantidad" disabled>
                    </div>
                    </div>
                    <div class="cotizacion-add-descripcion">
                        <label class="me-1"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_LINE_DESCRIPTION_LABEL', 'Custom description', 'Descripción personalizada'); ?> <span class="text-danger">*</span></label>
                        <textarea id="precotizacionDescription" class="form-control form-control-sm" rows="2" style="min-width: 200px; resize: vertical;" placeholder="<?php echo $l('COM_ORDENPRODUCCION_QUOTATION_LINE_DESCRIPTION_PLACEHOLDER', 'Enter description...', 'Ingrese descripción...'); ?>" aria-label="Descripción personalizada"></textarea>
                    </div>
                    <div class="cotizacion-add-btn align-self-end">
                        <button type="button" class="btn btn-primary btn-sm" id="btnAddPrecotizacionLine" title="<?php echo $l('COM_ORDENPRODUCCION_QUOTATION_ADD_LINE', 'Add line', 'Agregar línea'); ?>">
                            <i class="fas fa-plus"></i> <?php echo $l('COM_ORDENPRODUCCION_QUOTATION_ADD_LINE', 'Add line', 'Agregar línea'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <table class="items-table table table-bordered table-sm" id="quotationItemsTable">
                <thead>
                    <tr>
                        <th class="col-precotizacion" style="width: 9%;"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION', 'Pre-Quotation', 'Pre-Cotización'); ?></th>
                        <th class="col-cotizacion-items-qty-th" style="width: 5%;"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_TH_CANT', 'Qty', 'Cant.'); ?></th>
                        <th style="width: 33%;"><?php echo $l('COM_ORDENPRODUCCION_DESCRIPCION', 'Description', 'Descripción'); ?></th>
                        <th style="width: 9%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_PRECIO_UNIDAD', 'Unit price', 'Precio unidad.'); ?></th>
                        <th style="width: 11%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_SUBTOTAL', 'Subtotal', 'Subtotal'); ?></th>
                        <th style="width: 11%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_VALOR_FINAL', 'Final value', 'Valor final'); ?></th>
                        <th style="width: 11%;" class="text-nowrap"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_LINE_IMAGES', 'Images', 'Imágenes'); ?></th>
                        <th style="width: 11%;"><?php echo $l('COM_ORDENPRODUCCION_ACTION', 'Action', 'Acción'); ?></th>
                    </tr>
                </thead>
                <tbody id="quotationItemsBody">
                    <?php
                    $lineIndex = 0;
                    foreach (isset($this->quotationItems) ? $this->quotationItems : [] as $item) :
                        $lineIndex++;
                        $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
                        $preNum = '-';
                        if ($preId > 0) {
                            $preNum = !empty($item->pre_cotizacion_number) ? trim((string) $item->pre_cotizacion_number) : ('PRE-' . $preId);
                        }
                        $qty = isset($item->cantidad) ? (int) $item->cantidad : 0;
                        if ($qty < 0) {
                            $qty = 0;
                        }
                        $preTotal = isset($item->pre_cotizacion_total) ? (float) $item->pre_cotizacion_total : null;
                        $preTc = (isset($item->pre_cotizacion_total_con_tarjeta) && $item->pre_cotizacion_total_con_tarjeta !== null && $item->pre_cotizacion_total_con_tarjeta !== '')
                            ? (float) $item->pre_cotizacion_total_con_tarjeta
                            : null;
                        $subtotalRef = ($preId > 0 && $preTotal !== null) ? $preTotal : (isset($item->subtotal) ? (float) $item->subtotal : 0);
                        $minValor = ($preId > 0 && $preTc !== null) ? $preTc : $subtotalRef;
                        $storedVf = (isset($item->valor_final) && $item->valor_final !== null && $item->valor_final !== '') ? (float) $item->valor_final : null;
                        if ($storedVf === null) {
                            $valorFinal = $minValor;
                        } else {
                            $valorFinal = ($storedVf < $minValor - 0.005) ? $minValor : $storedVf;
                        }
                        $lineTotal = $valorFinal;
                        $unitPriceDisplay = $qty > 0 ? ($lineTotal / $qty) : 0;
                        $desc = isset($item->descripcion) ? $item->descripcion : '';
                        $lineImagesJsonForRow = '[]';
                        if (!empty($item->line_images_json)) {
                            $lineImagesJsonForRow = (string) $item->line_images_json;
                        }
                    ?>
                    <tr class="quotation-item-row" data-pre-id="<?php echo $preId; ?>" data-unit="<?php echo number_format($subtotalRef, 2, '.', ''); ?>" data-subtotal-ref="<?php echo number_format($subtotalRef, 2, '.', ''); ?>" data-min-valor="<?php echo number_format($minValor, 2, '.', ''); ?>">
                        <td><?php if ($preId > 0) : ?><a href="#" class="precotizacion-detail-link" data-pre-id="<?php echo $preId; ?>" data-pre-number="<?php echo htmlspecialchars($preNum); ?>"><?php echo htmlspecialchars($preNum); ?></a><?php else : ?><?php echo htmlspecialchars($preNum); ?><?php endif; ?></td>
                        <td><input type="number" name="lines[<?php echo $lineIndex; ?>][cantidad]" class="form-control form-control-sm line-cantidad-input text-end" style="width:70px;" min="0" step="1" value="<?php echo $qty; ?>"></td>
                        <td><textarea name="lines[<?php echo $lineIndex; ?>][descripcion]" class="form-control form-control-sm" rows="2" style="resize:vertical;"><?php echo htmlspecialchars($desc); ?></textarea></td>
                        <td class="text-end line-precio-unidad-cell">Q <span class="line-precio-unidad"><?php echo number_format($unitPriceDisplay, 4); ?></span></td>
                        <td class="text-end">Q <span class="line-subtotal-ref"><?php echo number_format($subtotalRef, 2); ?></span></td>
                        <td class="text-end align-middle">Q
                            <input type="hidden" name="lines[<?php echo $lineIndex; ?>][pre_cotizacion_id]" value="<?php echo $preId; ?>">
                            <div class="d-inline-block ms-1">
                                <input type="text" inputmode="decimal" name="lines[<?php echo $lineIndex; ?>][value]" class="line-value-input form-control form-control-sm text-end" style="width:90px;" value="<?php echo number_format($lineTotal, 2, '.', ''); ?>" data-min="<?php echo number_format($minValor, 2, '.', ''); ?>" placeholder="<?php echo number_format($minValor, 2, '.', ''); ?>" title="<?php echo $l('COM_ORDENPRODUCCION_VALOR_FINAL_MIN_HINT', 'Must be at least the subtotal', 'Debe ser al menos el subtotal'); ?>">
                                <div class="line-valor-final-error small text-danger mt-1" role="alert" style="display:none;"></div>
                            </div>
                        </td>
                        <td class="align-middle cotizacion-line-images-cell">
                            <input type="hidden" class="line-images-json-input" name="lines[<?php echo $lineIndex; ?>][line_images_json]" value="<?php echo htmlspecialchars($lineImagesJsonForRow, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="file" class="line-image-file-input" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff,.tif,.tiff" multiple aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
                            <div class="line-images-preview d-flex flex-wrap gap-1 align-items-center mb-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-line-attach" aria-label="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_QUOTATION_LINE_ATTACH', 'Attach images', 'Adjuntar imágenes')); ?>">
                                <i class="fas fa-paperclip" aria-hidden="true"></i>
                            </button>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-save-line me-1" onclick="window.saveQuotationLine(this)" title="<?php echo $l('COM_ORDENPRODUCCION_SAVE_LINE', 'Save line', 'Guardar línea'); ?>"><i class="fas fa-save"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" onclick="window.removeQuotationLine(this)" title="<?php echo $l('COM_ORDENPRODUCCION_DELETE', 'Delete', 'Eliminar'); ?>"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-end fw-bold"><?php echo $l('COM_ORDENPRODUCCION_TOTAL', 'Total', 'Total'); ?>:</td>
                        <td class="text-end"><input type="text" id="totalAmount" name="total_amount" value="0.00" readonly class="form-control form-control-sm d-inline-block text-end fw-bold" style="width: 90px; background: #f8f9fa;"></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn-cancel me-2" onclick="window.location.href='index.php?option=com_ordenproduccion&view=cotizaciones'">
                <i class="fas fa-times"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CANCEL', 'Cancel', 'Cancelar'); ?>
            </button>
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i>
                <?php echo $l('COM_ORDENPRODUCCION_SAVE_QUOTATION', 'Save Quotation', 'Guardar cotización'); ?>
            </button>
        </div>
    </form>
</div>

<!-- Modal: Pre-Cotización details -->
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
    const selectEl = document.getElementById('precotizacionSelect');
    const descEl = document.getElementById('precotizacionDescription');
    const cantidadWrapEl = document.getElementById('cotizacion-add-line-cantidad-wrap');
    const cantidadEl = document.getElementById('precotizacionCantidad');
    const btnAdd = document.getElementById('btnAddPrecotizacionLine');
    const tbody = document.getElementById('quotationItemsBody');
    let lineIndex = <?php echo isset($lineIndex) ? (int)$lineIndex : 0; ?>;
    var isEditMode = <?php echo $isEdit ? 'true' : 'false'; ?>;
    var initialPrecotizacionId = <?php echo !$isEdit ? (int) ($this->initialPrecotizacionId ?? 0) : 0; ?>;
    var initialPrecotizacionFirstQty = <?php echo !$isEdit ? (int) ($this->initialPrecotizacionFirstLineQty ?? 0) : 0; ?>;
    var msgLineAttach = <?php echo json_encode($l('COM_ORDENPRODUCCION_QUOTATION_LINE_ATTACH', 'Attach images', 'Adjuntar imágenes')); ?>;

    (function mergePreFromUrl() {
        try {
            var sp = new URLSearchParams(window.location.search || '');
            var u = parseInt(sp.get('precotizacion_id') || sp.get('pre_cotizacion_id') || '0', 10);
            if (!isNaN(u) && u > 0 && (!initialPrecotizacionId || initialPrecotizacionId < 1)) {
                initialPrecotizacionId = u;
            }
        } catch (e) {}
    })();

    // Pre-cotización descriptions from server (reliable for long/special chars), fallback to data-descripcion
    var precotizacionDescriptions = <?php echo json_encode($precotizacionDescriptions ?? []); ?>;

    function deriveDescFromPrecotOption(opt) {
        if (!opt || !opt.value) {
            return '';
        }
        var pid = String(opt.value);
        if (precotizacionDescriptions[pid] !== undefined && precotizacionDescriptions[pid] !== null && String(precotizacionDescriptions[pid]).trim() !== '') {
            return String(precotizacionDescriptions[pid]).trim();
        }
        var da = opt.getAttribute('data-descripcion');
        if (da && String(da).trim() !== '') {
            return String(da).trim();
        }
        var num = opt.getAttribute('data-number');
        return num ? String(num).trim() : '';
    }

    function syncPrecotCantidadWrapVisibility() {
        if (!cantidadWrapEl || !selectEl) {
            return;
        }
        var opt = selectEl.options[selectEl.selectedIndex];
        var hasPre = !!(opt && opt.value && parseInt(opt.value, 10) > 0);
        if (hasPre) {
            cantidadWrapEl.classList.remove('d-none');
            if (cantidadEl) {
                cantidadEl.disabled = false;
            }
        } else {
            cantidadWrapEl.classList.add('d-none');
            if (cantidadEl) {
                cantidadEl.value = '0';
                cantidadEl.disabled = true;
            }
        }
    }

    function fillDescriptionFromPrecotizacion() {
        if (!selectEl || !descEl) return;
        var opt = selectEl.options[selectEl.selectedIndex];
        if (opt && opt.value) {
            var preId = String(opt.value);
            var preDesc = (precotizacionDescriptions[preId] !== undefined && precotizacionDescriptions[preId] !== null)
                ? String(precotizacionDescriptions[preId]).trim()
                : '';
            if (preDesc === '') {
                preDesc = (opt.getAttribute('data-descripcion') || '').trim();
            }
            if (preDesc === '') {
                preDesc = (opt.getAttribute('data-number') || '').trim();
            }
            descEl.value = preDesc;
        } else {
            descEl.value = '';
        }
    }

    // Auto-fill description when a pre-cotización is selected (and on load if one is already selected)
    if (selectEl && descEl) {
        selectEl.addEventListener('change', function() {
            fillDescriptionFromPrecotizacion();
            syncPrecotCantidadWrapVisibility();
        });
        fillDescriptionFromPrecotizacion();
        syncPrecotCantidadWrapVisibility();
    }

    function escapeAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function rowMinValor(row) {
        var m = row.getAttribute('data-min-valor');
        if (m !== null && m !== '') {
            var mv = parseFloat(String(m).trim().replace(',', '.'), 10);
            if (!isNaN(mv)) {
                return mv;
            }
        }
        return parseFloat(row.getAttribute('data-subtotal-ref') || row.getAttribute('data-unit') || 0, 10) || 0;
    }

    function updateTotal() {
        var total = 0;
        tbody.querySelectorAll('tr.quotation-item-row').forEach(function(tr) {
            var v = tr.querySelector('input[name*="[value]"]');
            if (v) {
                var n = parseFloat(String(v.value).trim().replace(',', '.'), 10);
                total += isNaN(n) ? 0 : n;
            }
        });
        var totalInp = document.getElementById('totalAmount');
        if (totalInp) totalInp.value = total.toFixed(2);
    }

    function updateUnitPriceDisplay(row) {
        var qtyInp = row.querySelector('input[name*="[cantidad]"]');
        var valueInp = row.querySelector('input[name*="[value]"]');
        var span = row.querySelector('.line-precio-unidad');
        if (!span || !qtyInp || !valueInp) return;
        var q = parseFloat(String(qtyInp.value).trim().replace(',', '.'), 10);
        if (isNaN(q) || q < 0) q = 0;
        var raw = String(valueInp.value).trim().replace(',', '.');
        var sub = parseFloat(raw, 10);
        if (isNaN(sub)) sub = 0;
        span.textContent = q > 0 ? (sub / q).toFixed(4) : '0.0000';
    }

    var msgValorFinalMin = '<?php echo addslashes($l('COM_ORDENPRODUCCION_VALOR_FINAL_CANNOT_LOWER', 'The value cannot be lower than the subtotal.', 'El valor no puede ser menor al subtotal.')); ?>';

    function onValorFinalChange(row) {
        var valueInp = row.querySelector('input[name*="[value]"]');
        var errEl = row.querySelector('.line-valor-final-error');
        var minVal = rowMinValor(row);
        if (!valueInp || minVal < 0) return;
        var raw = String(valueInp.value).trim().replace(',', '.');
        var v = parseFloat(raw, 10);
        if (errEl) {
            if (raw !== '' && !isNaN(v) && v < minVal) {
                errEl.textContent = msgValorFinalMin;
                errEl.style.display = 'block';
            } else {
                errEl.textContent = '';
                errEl.style.display = 'none';
            }
        }
        if (!isNaN(v) && v >= minVal) {
            valueInp.value = v.toFixed(2);
        }
        updateUnitPriceDisplay(row);
        updateTotal();
    }

    function onValorFinalBlur(row) {
        var valueInp = row.querySelector('input[name*="[value]"]');
        var errEl = row.querySelector('.line-valor-final-error');
        var minVal = rowMinValor(row);
        if (!valueInp || minVal < 0) return;
        var raw = String(valueInp.value).trim().replace(',', '.');
        var v = parseFloat(raw, 10);
        if (isNaN(v) || v < minVal) {
            valueInp.value = minVal.toFixed(2);
            if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
        }
        updateUnitPriceDisplay(row);
        updateTotal();
    }

    function onRowCantidadChange(row) {
        var qtyInp = row.querySelector('input[name*="[cantidad]"]');
        if (qtyInp) {
            var q = parseInt(qtyInp.value, 10);
            if (isNaN(q) || q < 0) {
                qtyInp.value = '0';
            }
            updateUnitPriceDisplay(row);
            updateTotal();
        }
    }

    var msgCantidadRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_QUOTATION_LINE_CANTIDAD_REQUIRED', 'Enter a quantity greater than zero for each line.', 'Indique una cantidad mayor que cero en cada línea.')); ?>;

    function saveLine(btn) {
        var tr = btn.closest('tr');
        if (!tr) return;
        var qtyInpCheck = tr.querySelector('input[name*="[cantidad]"]');
        if (qtyInpCheck) {
            var qc = parseInt(qtyInpCheck.value, 10);
            if (isNaN(qc) || qc < 1) {
                alert(msgCantidadRequired);
                qtyInpCheck.focus();
                return;
            }
        }
        onValorFinalBlur(tr);
        onRowCantidadChange(tr);
        var saveBtn = tr.querySelector('.btn-save-line');
        if (saveBtn) {
            var origHtml = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-check"></i>';
            saveBtn.classList.add('btn-success');
            saveBtn.classList.remove('btn-outline-primary');
            setTimeout(function() {
                saveBtn.innerHTML = origHtml;
                saveBtn.classList.remove('btn-success');
                saveBtn.classList.add('btn-outline-primary');
            }, 800);
        }
    }

    function removeLine(btn) {
        var tr = btn.closest('tr');
        if (!tr) return;
        var preId = tr.getAttribute('data-pre-id') || '';
        // Only restore option in dropdown when this row had a real pre-cotización (not legacy/manual line with id 0)
        if (preId && preId !== '0' && selectEl) {
            var opt = selectEl.querySelector('option[value="' + escapeAttr(preId) + '"]');
            if (opt) opt.remove();
        }
        tr.remove();
        updateTotal();
    }

    if (btnAdd && selectEl) {
        btnAdd.addEventListener('click', function() {
            var opt = selectEl.options[selectEl.selectedIndex];
            if (!opt || !opt.value) return;
            var desc = (descEl && descEl.value) ? String(descEl.value).trim() : '';
            if (!desc) {
                desc = deriveDescFromPrecotOption(opt);
                if (descEl && desc) {
                    descEl.value = desc;
                }
            }
            if (!desc) {
                alert('<?php echo addslashes($l('COM_ORDENPRODUCCION_QUOTATION_DESCRIPTION_REQUIRED', 'Custom description is required.', 'La descripción personalizada es obligatoria.')); ?>');
                if (descEl) descEl.focus();
                return;
            }
            var qtyForNewRowPre = cantidadEl ? parseInt(String(cantidadEl.value).trim(), 10) : 0;
            if (isNaN(qtyForNewRowPre) || qtyForNewRowPre < 1) {
                alert(msgCantidadRequired);
                if (cantidadEl) {
                    cantidadEl.focus();
                }
                return;
            }
            var preId = opt.value;
            var baseTotal = parseFloat(opt.getAttribute('data-total') || '0');
            var tcRaw = opt.getAttribute('data-total-con-tarjeta');
            var minValorLine = (tcRaw !== null && tcRaw !== '' && !isNaN(parseFloat(tcRaw))) ? parseFloat(tcRaw) : baseTotal;
            var number = opt.getAttribute('data-number') || ('PRE-' + preId);
            var value = minValorLine.toFixed(2);
            var qtyForNewRow = qtyForNewRowPre;
            lineIndex++;
            var tr = document.createElement('tr');
            tr.className = 'quotation-item-row';
            tr.setAttribute('data-pre-id', preId);
            tr.setAttribute('data-unit', String(baseTotal));
            tr.setAttribute('data-subtotal-ref', String(baseTotal));
            tr.setAttribute('data-min-valor', String(minValorLine));
            var unitPrice = '0.0000';
            var firstCell = preId > 0 ? '<a href="#" class="precotizacion-detail-link" data-pre-id="' + escapeAttr(String(preId)) + '" data-pre-number="' + escapeAttr(number) + '">' + escapeAttr(number) + '</a>' : escapeAttr(number);
            tr.innerHTML = '<td>' + firstCell + '</td>' +
                '<td><input type="number" name="lines[' + lineIndex + '][cantidad]" class="form-control form-control-sm line-cantidad-input text-end" style="width:70px;" min="0" step="1" value="' + String(qtyForNewRow) + '"></td>' +
                '<td><textarea name="lines[' + lineIndex + '][descripcion]" class="form-control form-control-sm" rows="2" style="resize:vertical;">' + escapeAttr(desc) + '</textarea></td>' +
                '<td class="text-end line-precio-unidad-cell">Q <span class="line-precio-unidad">' + unitPrice + '</span></td>' +
                '<td class="text-end">Q <span class="line-subtotal-ref">' + baseTotal.toFixed(2) + '</span></td>' +
                '<td class="text-end align-middle">Q <input type="hidden" name="lines[' + lineIndex + '][pre_cotizacion_id]" value="' + escapeAttr(preId) + '"><div class="d-inline-block"><input type="text" inputmode="decimal" name="lines[' + lineIndex + '][value]" class="line-value-input form-control form-control-sm text-end" style="width:90px;" value="' + value + '" data-min="' + minValorLine + '" placeholder="' + minValorLine + '"><div class="line-valor-final-error small text-danger mt-1" role="alert" style="display:none;"></div></div></td>' +
                '<td class="align-middle cotizacion-line-images-cell">' +
                '<input type="hidden" class="line-images-json-input" name="lines[' + lineIndex + '][line_images_json]" value="[]">' +
                '<input type="file" class="line-image-file-input" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff,.tif,.tiff" multiple aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">' +
                '<div class="line-images-preview d-flex flex-wrap gap-1 align-items-center mb-1"></div>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary btn-line-attach" aria-label="' + escapeAttr(msgLineAttach) + '"><i class="fas fa-paperclip" aria-hidden="true"></i></button>' +
                '</td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-primary btn-save-line me-1" onclick="window.saveQuotationLine(this)"><i class="fas fa-save"></i></button><button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" onclick="window.removeQuotationLine(this)"><i class="fas fa-trash"></i></button></td>';
            tbody.appendChild(tr);
            if (typeof window.initLineImagesRow === 'function') {
                window.initLineImagesRow(tr);
            }
            var qtyInput = tr.querySelector('.line-cantidad-input');
            if (qtyInput) qtyInput.addEventListener('input', function() { onRowCantidadChange(tr); });
            var valueInput = tr.querySelector('.line-value-input');
            if (valueInput) {
                valueInput.addEventListener('input', function() { onValorFinalChange(tr); });
                valueInput.addEventListener('blur', function() { onValorFinalBlur(tr); });
            }
            if (qtyInput && qtyForNewRow > 0) {
                onRowCantidadChange(tr);
            }
            if (descEl) descEl.value = '';
            if (cantidadEl) cantidadEl.value = '0';
            selectEl.selectedIndex = 0;
            opt.remove();
            syncPrecotCantidadWrapVisibility();
            updateTotal();
        });
    }

    if (!isEditMode && typeof initialPrecotizacionId === 'number' && initialPrecotizacionId > 0 && selectEl && btnAdd) {
        var warmOpt = selectEl.querySelector('option[value="' + String(initialPrecotizacionId) + '"]');
        if (warmOpt && warmOpt.value) {
            selectEl.value = String(initialPrecotizacionId);
            fillDescriptionFromPrecotizacion();
            syncPrecotCantidadWrapVisibility();
            if (descEl && !String(descEl.value).trim()) {
                var bf = deriveDescFromPrecotOption(warmOpt);
                if (bf) descEl.value = bf;
            }
            if (cantidadEl) {
                if (initialPrecotizacionFirstQty > 0) {
                    cantidadEl.value = String(initialPrecotizacionFirstQty);
                } else {
                    cantidadEl.value = '1';
                }
            }
            btnAdd.click();
        }
    }
    // Bind cantidad and valor final change/blur on existing rows (edit mode)
    tbody.querySelectorAll('tr.quotation-item-row').forEach(function(tr) {
        var qtyInp = tr.querySelector('.line-cantidad-input');
        if (qtyInp) qtyInp.addEventListener('input', function() { onRowCantidadChange(tr); });
        var valueInp = tr.querySelector('.line-value-input');
        if (valueInp) {
            valueInp.addEventListener('input', function() { onValorFinalChange(tr); });
            valueInp.addEventListener('blur', function() { onValorFinalBlur(tr); });
        }
    });
    updateTotal();
    tbody.querySelectorAll('tr.quotation-item-row').forEach(function(tr) { updateUnitPriceDisplay(tr); });

    var opUploadToken = <?php echo json_encode(Session::getFormToken()); ?>;
    var opSiteRoot = <?php echo json_encode(Uri::root()); ?>;

    function syncLineImagesHidden(tr) {
        var inp = tr.querySelector('.line-images-json-input');
        if (inp) {
            inp.value = JSON.stringify(tr.lineImagePaths || []);
        }
    }

    function createLineImageThumb(tr, path) {
        var wrap = document.createElement('span');
        wrap.className = 'position-relative d-inline-block';
        var img = document.createElement('img');
        img.src = opSiteRoot.replace(/\/$/, '') + '/' + String(path).replace(/^\//, '');
        img.alt = '';
        img.style.cssText = 'max-height:44px;width:auto;border-radius:2px;border:1px solid #dee2e6;';
        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'btn btn-sm btn-link text-danger p-0 ms-1 align-top';
        x.innerHTML = '&times;';
        x.setAttribute('aria-label', 'Remove');
        x.addEventListener('click', function() {
            var i = (tr.lineImagePaths || []).indexOf(path);
            if (i >= 0) {
                tr.lineImagePaths.splice(i, 1);
            }
            syncLineImagesHidden(tr);
            wrap.remove();
        });
        wrap.appendChild(img);
        wrap.appendChild(x);
        return wrap;
    }

    function bindQuotationLineImages(tr) {
        var attach = tr.querySelector('.btn-line-attach');
        var fileInp = tr.querySelector('.line-image-file-input');
        if (!attach || !fileInp) {
            return;
        }
        attach.addEventListener('click', function() {
            fileInp.click();
        });
        fileInp.addEventListener('change', function() {
            if (!this.files || !this.files.length) {
                return;
            }
            var qidEl = document.getElementById('quotation_id');
            var qidVal = qidEl && qidEl.value ? parseInt(qidEl.value, 10) : 0;
            var files = this.files;
            var chain = Promise.resolve();
            for (var i = 0; i < files.length; i++) {
                (function(file) {
                    chain = chain.then(function() {
                        var fd = new FormData();
                        fd.append(opUploadToken, '1');
                        fd.append('quotation_id', String(qidVal));
                        fd.append('image', file);
                        return fetch(opSiteRoot + 'index.php?option=com_ordenproduccion&task=ajax.uploadQuotationLineImage', {
                            method: 'POST',
                            body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(function(r) {
                            return r.json();
                        }).then(function(data) {
                            if (!data || !data.success || !data.path) {
                                alert((data && data.message) ? data.message : 'Upload failed');
                                return;
                            }
                            if (!tr.lineImagePaths) {
                                tr.lineImagePaths = [];
                            }
                            tr.lineImagePaths.push(data.path);
                            syncLineImagesHidden(tr);
                            var prev = tr.querySelector('.line-images-preview');
                            if (prev) {
                                prev.appendChild(createLineImageThumb(tr, data.path));
                            }
                        });
                    });
                })(files[i]);
            }
            chain.then(function() {
                fileInp.value = '';
            });
        });
    }

    function initLineImagesRow(tr) {
        tr.lineImagePaths = [];
        var inp = tr.querySelector('.line-images-json-input');
        if (inp && inp.value) {
            try {
                tr.lineImagePaths = JSON.parse(inp.value);
            } catch (e1) {
                tr.lineImagePaths = [];
            }
            if (!Array.isArray(tr.lineImagePaths)) {
                tr.lineImagePaths = [];
            }
        }
        var prev = tr.querySelector('.line-images-preview');
        if (prev) {
            prev.innerHTML = '';
            (tr.lineImagePaths || []).forEach(function(p) {
                if (typeof p === 'string' && p !== '') {
                    prev.appendChild(createLineImageThumb(tr, p));
                }
            });
        }
        bindQuotationLineImages(tr);
    }

    tbody.querySelectorAll('tr.quotation-item-row').forEach(initLineImagesRow);
    window.initLineImagesRow = initLineImagesRow;

    window.removeQuotationLine = removeLine;
    window.saveQuotationLine = saveLine;
    window.updateQuotationTotal = updateTotal;

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

function submitQuotationForm(event) {
    event.preventDefault();
    const tbody = document.getElementById('quotationItemsBody');
    if (!tbody || tbody.querySelectorAll('tr.quotation-item-row').length === 0) {
        alert('<?php echo addslashes($l('COM_ORDENPRODUCCION_QUOTATION_ADD_AT_LEAST_ONE_LINE', 'Please add at least one line.', 'Agregue al menos una línea.')); ?>');
        return;
    }
    var msgCantidadReq = <?php echo json_encode($l('COM_ORDENPRODUCCION_QUOTATION_LINE_CANTIDAD_REQUIRED', 'Enter a quantity greater than zero for each line.', 'Indique una cantidad mayor que cero en cada línea.')); ?>;
    var rowsCant = tbody.querySelectorAll('tr.quotation-item-row');
    for (var ri = 0; ri < rowsCant.length; ri++) {
        var qinp = rowsCant[ri].querySelector('input.line-cantidad-input');
        if (qinp) {
            var qv = parseInt(qinp.value, 10);
            if (isNaN(qv) || qv < 1) {
                alert(msgCantidadReq);
                qinp.focus();
                return;
            }
        }
    }
    const submitButton = event.target.querySelector('.btn-submit');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo addslashes($l('COM_ORDENPRODUCCION_PROCESSING', 'Processing', 'Procesando')); ?>...';
    
    const formData = new FormData(document.getElementById('quotationForm'));
    formData.set('<?php echo Session::getFormToken(); ?>', '1');
    formData.set('total_amount', document.getElementById('totalAmount').value);
    
    const quotationId = document.getElementById('quotation_id');
    const task = (quotationId && quotationId.value) ? 'ajax.updateQuotation' : 'ajax.createQuotation';
    
    fetch('<?php echo Uri::root(); ?>index.php?option=com_ordenproduccion&task=' + task, {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Server error: ' + response.status);
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            alert(data.message || ('<?php echo addslashes($l('COM_ORDENPRODUCCION_QUOTATION_CREATED_SUCCESS', 'Quotation saved successfully.', 'Cotización guardada correctamente.')); ?>: ' + (data.quotation_number || '')));
            var savedId = data.quotation_id ? String(data.quotation_id) : (quotationId && quotationId.value ? String(quotationId.value) : '');
            if (savedId) {
                window.location.href = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' + encodeURIComponent(savedId);
            } else {
                window.location.href = 'index.php?option=com_ordenproduccion&view=cotizaciones';
            }
        } else {
            throw new Error(data.message || 'Error saving quotation');
        }
    })
    .catch(function(error) {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-save"></i> <?php echo addslashes($l('COM_ORDENPRODUCCION_SAVE_QUOTATION', 'Save Quotation', 'Guardar cotización')); ?>';
        alert('<?php echo addslashes($l('COM_ORDENPRODUCCION_ERROR_CREATING_QUOTATION', 'Error saving quotation.', 'Error al guardar la cotización.')); ?>: ' + error.message);
        console.error('Error:', error);
    });
}
</script>


