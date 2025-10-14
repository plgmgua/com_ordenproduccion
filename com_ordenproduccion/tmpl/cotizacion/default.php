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
                        <th style="width: 50%;"><?php echo Text::_('COM_ORDENPRODUCCION_ADDRESS'); ?></th>
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

        <!-- Quotation Items Section -->
        <div class="items-table-section">
            <h4 class="items-table-title">
                <i class="fas fa-list"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_ITEMS'); ?>
            </h4>
            <table class="items-table" id="quotationItemsTable">
                <thead>
                    <tr>
                        <th style="width: 15%;"><?php echo Text::_('COM_ORDENPRODUCCION_CANTIDAD'); ?></th>
                        <th style="width: 40%;"><?php echo Text::_('COM_ORDENPRODUCCION_DESCRIPCION'); ?></th>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_VALOR_UNITARIO'); ?></th>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_SUBTOTAL'); ?></th>
                        <th style="width: 5%;"><?php echo Text::_('COM_ORDENPRODUCCION_ACTION'); ?></th>
                    </tr>
                </thead>
                <tbody id="quotationItemsBody">
                    <tr class="quotation-item-row">
                        <td><input type="number" name="items[1][cantidad]" class="cantidad-input" placeholder="1" min="1" step="1" oninput="calculateSubtotal(this)" required></td>
                        <td><input type="text" name="items[1][descripcion]" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ITEM_DESCRIPTION'); ?>" required></td>
                        <td><input type="number" name="items[1][valor_unitario]" class="valor-unitario-input" placeholder="0.00" min="0" step="0.01" oninput="calculateSubtotal(this)" required></td>
                        <td><input type="number" name="items[1][subtotal]" class="subtotal-input" placeholder="0.00" readonly></td>
                        <td><button type="button" class="btn-delete-row" onclick="deleteRow(this)" style="background: #dc3545; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;"><i class="fas fa-trash"></i></button></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: bold; padding: 10px;"><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL'); ?>:</td>
                        <td><input type="number" id="totalAmount" name="total_amount" placeholder="0.00" readonly style="font-weight: bold; background: #f8f9fa;"></td>
                        <td><button type="button" class="btn-add-row" onclick="addRow()" style="background: #28a745; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;"><i class="fas fa-plus"></i></button></td>
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
let rowCounter = 1; // Keep track of row numbers

function addRow() {
    rowCounter++;
    const tbody = document.getElementById('quotationItemsBody');
    const newRow = document.createElement('tr');
    newRow.className = 'quotation-item-row';
    newRow.innerHTML = `
        <td><input type="number" name="items[${rowCounter}][cantidad]" class="cantidad-input" placeholder="1" min="1" step="1" oninput="calculateSubtotal(this)" required></td>
        <td><input type="text" name="items[${rowCounter}][descripcion]" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ITEM_DESCRIPTION'); ?>" required></td>
        <td><input type="number" name="items[${rowCounter}][valor_unitario]" class="valor-unitario-input" placeholder="0.00" min="0" step="0.01" oninput="calculateSubtotal(this)" required></td>
        <td><input type="number" name="items[${rowCounter}][subtotal]" class="subtotal-input" placeholder="0.00" readonly></td>
        <td><button type="button" class="btn-delete-row" onclick="deleteRow(this)" style="background: #dc3545; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;"><i class="fas fa-trash"></i></button></td>
    `;
    tbody.appendChild(newRow);
}

function deleteRow(button) {
    const row = button.closest('tr');
    row.remove();
    calculateTotal();
}

function calculateSubtotal(input) {
    const row = input.closest('tr');
    const cantidad = parseFloat(row.querySelector('.cantidad-input').value) || 0;
    const valorUnitario = parseFloat(row.querySelector('.valor-unitario-input').value) || 0;
    const subtotal = cantidad * valorUnitario;

    const subtotalInput = row.querySelector('.subtotal-input');
    subtotalInput.value = subtotal.toFixed(2);

    calculateTotal();
}

function calculateTotal() {
    const subtotalInputs = document.querySelectorAll('.subtotal-input');
    let total = 0;

    subtotalInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });

    const totalInput = document.getElementById('totalAmount');
    totalInput.value = total.toFixed(2);
}

function submitQuotationForm(event) {
    event.preventDefault();
    
    const submitButton = event.target.querySelector('.btn-submit');
    const originalText = submitButton.innerHTML;
    
    // Disable button and show loading
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROCESSING'); ?>...';
    
    // Get form data
    const formData = new FormData();
    formData.append('<?php echo Session::getFormToken(); ?>', '1');  // CSRF token
    formData.append('client_name', document.getElementById('client_name').value);
    formData.append('client_nit', document.getElementById('client_nit').value);
    formData.append('client_address', document.getElementById('client_address').value);
    formData.append('contact_name', document.getElementById('contact_name').value);
    formData.append('contact_phone', document.getElementById('contact_phone').value);
    formData.append('quote_date', document.getElementById('quote_date').value);
    
    // Get quotation items data
    const rows = document.querySelectorAll('.quotation-item-row');
    rows.forEach((row, index) => {
        const cantidad = row.querySelector('.cantidad-input').value;
        const descripcion = row.querySelector('input[name*="[descripcion]"]').value;
        const valorUnitario = row.querySelector('.valor-unitario-input').value;
        const subtotal = row.querySelector('.subtotal-input').value;
        
        if (cantidad && valorUnitario) {
            formData.append(`items[${index + 1}][cantidad]`, cantidad);
            formData.append(`items[${index + 1}][descripcion]`, descripcion);
            formData.append(`items[${index + 1}][valor_unitario]`, valorUnitario);
            formData.append(`items[${index + 1}][subtotal]`, subtotal);
        }
    });
    
    // Submit to AJAX endpoint (following working pattern)
    fetch('<?php echo Uri::root(); ?>index.php?option=com_ordenproduccion&task=ajax.createQuotation', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Success - show message and redirect
            alert('<?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_CREATED_SUCCESS'); ?>: ' + data.quotation_number);
            window.location.href = 'index.php?option=com_ordenproduccion&view=cotizaciones';
        } else {
            throw new Error(data.message || 'Error creating quotation');
        }
    })
    .catch(error => {
        // Error - re-enable button and show error
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_ERROR_CREATING_QUOTATION'); ?>: ' + error.message);
        console.error('Error:', error);
    });
}
</script>


