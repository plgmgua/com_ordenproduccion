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
?>

<div class="cotizacion-container">
    <div class="cotizacion-header">
        <h2>
            <i class="fas fa-file-invoice"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_NEW_QUOTATION_TITLE'); ?>
        </h2>
        <p class="form-note">
            <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_FORM_NOTE'); ?>
        </p>
    </div>

    <form id="quotationForm" onsubmit="submitQuotationForm(event)">
        <input type="hidden" name="client_id" id="client_id" value="<?php echo htmlspecialchars($this->clientId ?? ''); ?>">

        <!-- Client Information Section -->
        <div class="client-info-section">
            <h4 class="section-title">
                <i class="fas fa-user"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_INFORMATION'); ?>
            </h4>
            
            <table class="client-table">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_NAME'); ?></th>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?></th>
                        <th style="width: 30%;"><?php echo Text::_('COM_ORDENPRODUCCION_ADDRESS'); ?></th>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_SALES_AGENT'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" 
                                   id="client_name" 
                                   name="client_name" 
                                   value="<?php echo htmlspecialchars($this->clientName); ?>" 
                                   required 
                                   placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_NAME'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="client_nit" 
                                   name="client_nit" 
                                   value="<?php echo htmlspecialchars($this->clientNit); ?>" 
                                   required 
                                   placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="client_address" 
                                   name="client_address" 
                                   value="<?php echo htmlspecialchars($this->clientAddress); ?>" 
                                   required 
                                   placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ADDRESS'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="sales_agent" 
                                   name="sales_agent" 
                                   value="<?php echo htmlspecialchars($this->salesAgent ?? ''); ?>" 
                                   placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_SALES_AGENT'); ?>">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Contact Information Section -->
        <div class="contact-info-section">
            <h4 class="section-title">
                <i class="fas fa-address-book"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_CONTACT_INFORMATION'); ?>
            </h4>
            
            <table class="contact-table">
                <thead>
                    <tr>
                        <th style="width: 50%;"><?php echo Text::_('COM_ORDENPRODUCCION_CONTACT_NAME'); ?></th>
                        <th style="width: 30%;"><?php echo Text::_('COM_ORDENPRODUCCION_CONTACT_PHONE'); ?></th>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_DATE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" 
                                   id="contact_name" 
                                   name="contact_name" 
                                   placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_CONTACT_NAME'); ?>">
                        </td>
                        <td>
                            <input type="text" 
                                   id="contact_phone" 
                                   name="contact_phone" 
                                   placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_CONTACT_PHONE'); ?>">
                        </td>
                        <td>
                            <input type="date" 
                                   id="quote_date" 
                                   name="quote_date" 
                                   value="<?php echo $today; ?>" 
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
                <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_ITEMS'); ?>
            </h4>
            <p class="form-note"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_LINES_PRECOTIZACION_NOTE'); ?></p>
            <?php if (empty($this->preCotizacionesList)) : ?>
                <p class="alert alert-info"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_NO_PRE_COTIZACIONES'); ?></p>
            <?php endif; ?>
            <div class="mb-2">
                <label class="me-2"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SELECT'); ?></label>
                <select id="precotizacionSelect" class="form-select d-inline-block" style="width: auto;">
                    <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_PRE_COTIZACION'); ?></option>
                    <?php foreach ($this->preCotizacionesList ?? [] as $pre) : ?>
                        <option value="<?php echo (int) $pre->id; ?>" data-total="<?php echo number_format($pre->total, 2, '.', ''); ?>" data-number="<?php echo htmlspecialchars($pre->number); ?>">
                            <?php echo htmlspecialchars($pre->number); ?> — Q <?php echo number_format($pre->total, 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="precotizacionDescription" class="form-control d-inline-block ms-2" style="width: 280px;" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_LINE_DESCRIPTION_PLACEHOLDER'); ?>">
                <button type="button" class="btn btn-primary ms-2" id="btnAddPrecotizacionLine">
                    <i class="fas fa-plus"></i> <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_ADD_LINE'); ?>
                </button>
            </div>
            <table class="items-table table table-bordered" id="quotationItemsTable">
                <thead>
                    <tr>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION'); ?></th>
                        <th style="width: 45%;"><?php echo Text::_('COM_ORDENPRODUCCION_DESCRIPCION'); ?></th>
                        <th style="width: 20%;" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_SUBTOTAL'); ?></th>
                        <th style="width: 15%;"><?php echo Text::_('COM_ORDENPRODUCCION_ACTION'); ?></th>
                    </tr>
                </thead>
                <tbody id="quotationItemsBody">
                    <!-- Lines added via JS: pre_cotizacion_id + description + value -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL'); ?>:</td>
                        <td class="text-end"><input type="text" id="totalAmount" name="total_amount" value="0.00" readonly class="form-control form-control-sm d-inline-block text-end fw-bold" style="width: 100px; background: #f8f9fa;"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="window.location.href='index.php?option=com_ordenproduccion&view=cotizaciones'">
                <i class="fas fa-times"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_CANCEL'); ?>
            </button>
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_SAVE_QUOTATION'); ?>
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const token = '<?php echo Session::getFormToken(); ?>';
    const selectEl = document.getElementById('precotizacionSelect');
    const descEl = document.getElementById('precotizacionDescription');
    const btnAdd = document.getElementById('btnAddPrecotizacionLine');
    const tbody = document.getElementById('quotationItemsBody');
    let lineIndex = 0;

    function updateTotal() {
        let total = 0;
        tbody.querySelectorAll('tr').forEach(function(tr) {
            const val = parseFloat(tr.querySelector('input[name*="[value]"]').value) || 0;
            total += val;
        });
        document.getElementById('totalAmount').value = total.toFixed(2);
    }

    function removeLine(btn) {
        btn.closest('tr').remove();
        updateTotal();
    }

    if (btnAdd && selectEl) {
        btnAdd.addEventListener('click', function() {
            const opt = selectEl.options[selectEl.selectedIndex];
            if (!opt || !opt.value) return;
            const preId = opt.value;
            const total = opt.getAttribute('data-total') || '0';
            const number = opt.getAttribute('data-number') || ('PRE-' + preId);
            const desc = (descEl && descEl.value) ? descEl.value.trim() : number;
            lineIndex++;
            const tr = document.createElement('tr');
            tr.className = 'quotation-item-row';
            tr.innerHTML = '<td>' + number + '</td>' +
                '<td><input type="text" name="lines[' + lineIndex + '][descripcion]" class="form-control form-control-sm" value="' + (desc.replace(/"/g, '&quot;').replace(/'/g, '&#39;')) + '" placeholder="Custom description"></td>' +
                '<td class="text-end">Q <input type="hidden" name="lines[' + lineIndex + '][pre_cotizacion_id]" value="' + preId + '"><input type="number" step="0.01" name="lines[' + lineIndex + '][value]" class="line-value-input form-control form-control-sm d-inline-block text-end" style="width:90px;" value="' + total + '" readonly></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger btn-delete-row" onclick="window.removeQuotationLine(this)"><i class="fas fa-trash"></i></button></td>';
            tbody.appendChild(tr);
            if (descEl) descEl.value = '';
            selectEl.selectedIndex = 0;
            updateTotal();
        });
    }
    window.removeQuotationLine = removeLine;
    window.updateQuotationTotal = updateTotal;
})();

function submitQuotationForm(event) {
    event.preventDefault();
    const tbody = document.getElementById('quotationItemsBody');
    if (!tbody || tbody.querySelectorAll('tr').length === 0) {
        alert('<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_QUOTATION_ADD_AT_LEAST_ONE_LINE')); ?>');
        return;
    }
    const submitButton = event.target.querySelector('.btn-submit');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo addslashes(Text::_('COM_ORDENPRODUCCION_PROCESSING')); ?>...';
    
    const formData = new FormData(document.getElementById('quotationForm'));
    formData.set('<?php echo Session::getFormToken(); ?>', '1');
    formData.set('total_amount', document.getElementById('totalAmount').value);
    
    fetch('<?php echo Uri::root(); ?>index.php?option=com_ordenproduccion&task=ajax.createQuotation', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Server error: ' + response.status);
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            alert('<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_QUOTATION_CREATED_SUCCESS')); ?>: ' + data.quotation_number);
            window.location.href = 'index.php?option=com_ordenproduccion&view=cotizaciones';
        } else {
            throw new Error(data.message || 'Error creating quotation');
        }
    })
    .catch(function(error) {
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-save"></i> <?php echo addslashes(Text::_('COM_ORDENPRODUCCION_SAVE_QUOTATION')); ?>';
        alert('<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_ERROR_CREATING_QUOTATION')); ?>: ' + error.message);
        console.error('Error:', error);
    });
}
</script>


