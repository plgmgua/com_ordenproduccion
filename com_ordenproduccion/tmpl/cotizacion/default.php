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
        <input type="hidden" name="client_id" id="client_id" value="<?php echo htmlspecialchars($this->clientId ?? ''); ?>">

        <!-- Client Information Section -->
        <div class="client-info-section">
            <h4 class="section-title">
                <i class="fas fa-user"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CLIENT_INFORMATION', 'Client Information', 'Información del cliente'); ?>
            </h4>
            
            <table class="client-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php echo $l('COM_ORDENPRODUCCION_CLIENT_NAME', 'Client Name', 'Nombre del cliente'); ?></th>
                        <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?></th>
                        <th style="width: 30%;"><?php echo $l('COM_ORDENPRODUCCION_ADDRESS', 'Address', 'Dirección'); ?></th>
                        <th style="width: 20%;"><?php echo $l('COM_ORDENPRODUCCION_SALES_AGENT', 'Sales Agent', 'Agente de ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        $clientNamePrepop = (string) ($this->clientName ?? '');
                        $clientNitPrepop  = (string) ($this->clientNit ?? '');
                        $salesAgentPrepop = (string) ($this->salesAgent ?? '');
                        $readonlyClientName = $clientNamePrepop !== '';
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
                                   id="client_nit" 
                                   name="client_nit" 
                                   value="<?php echo htmlspecialchars($clientNitPrepop); ?>" 
                                   <?php if ($readonlyClientNit) : ?>readonly class="readonly-prepop"<?php endif; ?>
                                   required 
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="client_address" 
                                   name="client_address" 
                                   value="<?php echo htmlspecialchars($this->clientAddress ?? ''); ?>" 
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
                            <input type="text" 
                                   id="contact_name" 
                                   name="contact_name" 
                                   value="<?php echo htmlspecialchars($isEdit ? ($this->quotation->contact_name ?? '') : ''); ?>"
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_CONTACT_NAME', 'Contact Name', 'Nombre de contacto'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="contact_phone" 
                                   name="contact_phone" 
                                   value="<?php echo htmlspecialchars($isEdit ? ($this->quotation->contact_phone ?? '') : ''); ?>"
                                   placeholder="<?php echo $l('COM_ORDENPRODUCCION_CONTACT_PHONE', 'Contact Phone', 'Teléfono de contacto'); ?>">
                        </td>
                        <td>
                            <input type="date" 
                                   id="quote_date" 
                                   name="quote_date" 
                                   value="<?php echo $isEdit && !empty($this->quotation->quote_date) ? htmlspecialchars($this->quotation->quote_date) : $today; ?>" 
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
            <p class="form-note"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_LINES_PRECOTIZACION_NOTE', 'Add lines by selecting a Pre-Quotation, quantity and a custom description (required). Total is the sum of all line values.', 'Agregue líneas seleccionando una Pre-Cotización, cantidad y descripción personalizada (obligatoria). El total es la suma de todas las líneas.'); ?></p>
            <?php if (empty($this->preCotizacionesList)) : ?>
                <p class="alert alert-info"><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_NO_PRE_COTIZACIONES', 'You have no Pre-Quotations yet. Create one from Pre-Cotizaciones first.', 'Aún no tiene Pre-Cotizaciones. Cree una en Pre-Cotizaciones primero.'); ?></p>
            <?php endif; ?>
            <div class="cotizacion-add-line-block mb-2">
                <div class="cotizacion-add-line-row-first">
                    <label class="me-2"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION_SELECT', 'Pre-Quotation', 'Pre-Cotización'); ?></label>
                    <select id="precotizacionSelect" class="form-select form-select-sm cotizacion-precotizacion-select">
                        <option value=""><?php echo $l('COM_ORDENPRODUCCION_SELECT_PRE_COTIZACION', 'Select Pre-Quotation...', 'Seleccionar Pre-Cotización...'); ?></option>
                        <?php foreach ($this->preCotizacionesList ?? [] as $pre) :
                            $desc = isset($pre->descripcion) ? trim((string) $pre->descripcion) : '';
                            $label = $pre->number . ($desc !== '' ? ' — ' . $desc : '');
                            if (mb_strlen($label) > 120) {
                                $label = mb_substr($label, 0, 117) . '...';
                            }
                        ?>
                            <option value="<?php echo (int) $pre->id; ?>" data-total="<?php echo number_format($pre->total, 2, '.', ''); ?>" data-number="<?php echo htmlspecialchars($pre->number); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cotizacion-add-line-row-second d-flex flex-wrap align-items-end gap-2">
                    <div class="cotizacion-add-cantidad">
                        <label class="me-1"><?php echo $l('COM_ORDENPRODUCCION_CANTIDAD', 'Qty', 'Cantidad'); ?> <span class="text-danger">*</span></label>
                        <input type="number" id="precotizacionCantidad" class="form-control form-control-sm text-end" style="width: 70px;" min="1" step="1" value="1" aria-label="Cantidad">
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
                        <th class="col-precotizacion"><?php echo $l('COM_ORDENPRODUCCION_PRE_COTIZACION', 'Pre-Quotation', 'Pre-Cotización'); ?></th>
                        <th style="width: 8%;"><?php echo $l('COM_ORDENPRODUCCION_CANTIDAD', 'Qty', 'Cantidad'); ?></th>
                        <th style="width: 35%;"><?php echo $l('COM_ORDENPRODUCCION_DESCRIPCION', 'Description', 'Descripción'); ?></th>
                        <th style="width: 12%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_PRECIO_UNIDAD', 'Unit price', 'Precio unidad.'); ?></th>
                        <th style="width: 14%;" class="text-end"><?php echo $l('COM_ORDENPRODUCCION_SUBTOTAL', 'Subtotal', 'Subtotal'); ?></th>
                        <th style="width: 14%;"><?php echo $l('COM_ORDENPRODUCCION_ACTION', 'Action', 'Acción'); ?></th>
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
                        $qty = isset($item->cantidad) ? (int) $item->cantidad : 1;
                        if ($qty < 1) $qty = 1;
                        $subtotal = isset($item->subtotal) ? (float) $item->subtotal : 0;
                        $unit = $qty > 0 && $subtotal > 0 ? ($subtotal / $qty) : 0;
                        $desc = isset($item->descripcion) ? $item->descripcion : '';
                    ?>
                    <?php $unitPriceDisplay = $qty > 0 && $subtotal > 0 ? ($subtotal / $qty) : 0; ?>
                    <tr class="quotation-item-row" data-pre-id="<?php echo $preId; ?>" data-unit="<?php echo number_format($subtotal, 2, '.', ''); ?>">
                        <td><?php if ($preId > 0) : ?><a href="#" class="precotizacion-detail-link" data-pre-id="<?php echo $preId; ?>" data-pre-number="<?php echo htmlspecialchars($preNum); ?>"><?php echo htmlspecialchars($preNum); ?></a><?php else : ?><?php echo htmlspecialchars($preNum); ?><?php endif; ?></td>
                        <td><input type="number" name="lines[<?php echo $lineIndex; ?>][cantidad]" class="form-control form-control-sm line-cantidad-input text-end" style="width:70px;" min="1" step="1" value="<?php echo $qty; ?>"></td>
                        <td><textarea name="lines[<?php echo $lineIndex; ?>][descripcion]" class="form-control form-control-sm" rows="2" style="resize:vertical;"><?php echo htmlspecialchars($desc); ?></textarea></td>
                        <td class="text-end line-precio-unidad-cell">Q <span class="line-precio-unidad"><?php echo number_format($unitPriceDisplay, 4); ?></span></td>
                        <td class="text-end">Q <input type="hidden" name="lines[<?php echo $lineIndex; ?>][pre_cotizacion_id]" value="<?php echo $preId; ?>"><input type="number" step="0.01" name="lines[<?php echo $lineIndex; ?>][value]" class="line-value-input form-control form-control-sm d-inline-block text-end" style="width:90px;" value="<?php echo number_format($subtotal, 2, '.', ''); ?>" readonly></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-save-line me-1" onclick="window.saveQuotationLine(this)" title="<?php echo $l('COM_ORDENPRODUCCION_SAVE_LINE', 'Save line', 'Guardar línea'); ?>"><i class="fas fa-save"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" onclick="window.removeQuotationLine(this)" title="<?php echo $l('COM_ORDENPRODUCCION_DELETE', 'Delete', 'Eliminar'); ?>"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-bold"><?php echo $l('COM_ORDENPRODUCCION_TOTAL', 'Total', 'Total'); ?>:</td>
                        <td class="text-end"><input type="text" id="totalAmount" name="total_amount" value="0.00" readonly class="form-control form-control-sm d-inline-block text-end fw-bold" style="width: 90px; background: #f8f9fa;"></td>
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
    const cantidadEl = document.getElementById('precotizacionCantidad');
    const btnAdd = document.getElementById('btnAddPrecotizacionLine');
    const tbody = document.getElementById('quotationItemsBody');
    let lineIndex = <?php echo isset($lineIndex) ? (int)$lineIndex : 0; ?>;

    function escapeAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function updateTotal() {
        var total = 0;
        tbody.querySelectorAll('tr.quotation-item-row').forEach(function(tr) {
            var v = tr.querySelector('input[name*="[value]"]');
            if (v) total += parseFloat(v.value) || 0;
        });
        var totalInp = document.getElementById('totalAmount');
        if (totalInp) totalInp.value = total.toFixed(2);
    }

    function updateUnitPriceDisplay(row) {
        var qtyInp = row.querySelector('input[name*="[cantidad]"]');
        var valueInp = row.querySelector('input[name*="[value]"]');
        var span = row.querySelector('.line-precio-unidad');
        if (!span || !qtyInp || !valueInp) return;
        var q = parseFloat(qtyInp.value, 10) || 1;
        var sub = parseFloat(valueInp.value, 10) || 0;
        span.textContent = q > 0 ? (sub / q).toFixed(4) : '0.0000';
    }

    function onRowCantidadChange(row) {
        var qtyInp = row.querySelector('input[name*="[cantidad]"]');
        var valueInp = row.querySelector('input[name*="[value]"]');
        var unitTotal = parseFloat(row.getAttribute('data-unit') || 0);
        if (qtyInp && valueInp && unitTotal >= 0) {
            var q = parseInt(qtyInp.value, 10) || 1;
            if (q < 1) { q = 1; qtyInp.value = 1; }
            valueInp.value = unitTotal.toFixed(2);
            updateUnitPriceDisplay(row);
            updateTotal();
        }
    }

    function saveLine(btn) {
        var tr = btn.closest('tr');
        if (!tr) return;
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
            var qty = parseInt(cantidadEl && cantidadEl.value ? cantidadEl.value : 1, 10) || 1;
            if (qty < 1) qty = 1;
            var desc = (descEl && descEl.value) ? String(descEl.value).trim() : '';
            if (!desc) {
                alert('<?php echo addslashes($l('COM_ORDENPRODUCCION_QUOTATION_DESCRIPTION_REQUIRED', 'Custom description is required.', 'La descripción personalizada es obligatoria.')); ?>');
                if (descEl) descEl.focus();
                return;
            }
            var preId = opt.value;
            var unitTotal = parseFloat(opt.getAttribute('data-total') || '0');
            var number = opt.getAttribute('data-number') || ('PRE-' + preId);
            var value = unitTotal.toFixed(2);
            lineIndex++;
            var tr = document.createElement('tr');
            tr.className = 'quotation-item-row';
            tr.setAttribute('data-pre-id', preId);
            tr.setAttribute('data-unit', unitTotal);
            var unitPrice = (qty > 0 && unitTotal > 0) ? (unitTotal / qty).toFixed(4) : '0.0000';
            var firstCell = preId > 0 ? '<a href="#" class="precotizacion-detail-link" data-pre-id="' + escapeAttr(String(preId)) + '" data-pre-number="' + escapeAttr(number) + '">' + escapeAttr(number) + '</a>' : escapeAttr(number);
            tr.innerHTML = '<td>' + firstCell + '</td>' +
                '<td><input type="number" name="lines[' + lineIndex + '][cantidad]" class="form-control form-control-sm line-cantidad-input text-end" style="width:70px;" min="1" step="1" value="' + qty + '"></td>' +
                '<td><textarea name="lines[' + lineIndex + '][descripcion]" class="form-control form-control-sm" rows="2" style="resize:vertical;">' + escapeAttr(desc) + '</textarea></td>' +
                '<td class="text-end line-precio-unidad-cell">Q <span class="line-precio-unidad">' + unitPrice + '</span></td>' +
                '<td class="text-end">Q <input type="hidden" name="lines[' + lineIndex + '][pre_cotizacion_id]" value="' + escapeAttr(preId) + '"><input type="number" step="0.01" name="lines[' + lineIndex + '][value]" class="line-value-input form-control form-control-sm d-inline-block text-end" style="width:90px;" value="' + value + '" readonly></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-primary btn-save-line me-1" onclick="window.saveQuotationLine(this)"><i class="fas fa-save"></i></button><button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" onclick="window.removeQuotationLine(this)"><i class="fas fa-trash"></i></button></td>';
            tbody.appendChild(tr);
            var qtyInput = tr.querySelector('.line-cantidad-input');
            if (qtyInput) qtyInput.addEventListener('input', function() { onRowCantidadChange(tr); });
            if (descEl) descEl.value = '';
            if (cantidadEl) cantidadEl.value = '1';
            selectEl.selectedIndex = 0;
            opt.remove();
            updateTotal();
        });
    }
    // Bind cantidad change on existing rows (edit mode)
    tbody.querySelectorAll('tr.quotation-item-row .line-cantidad-input').forEach(function(inp) {
        var tr = inp.closest('tr');
        if (tr) inp.addEventListener('input', function() { onRowCantidadChange(tr); });
    });
    updateTotal();
    tbody.querySelectorAll('tr.quotation-item-row').forEach(function(tr) { updateUnitPriceDisplay(tr); });
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
            window.location.href = 'index.php?option=com_ordenproduccion&view=cotizaciones';
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


