/**
 * Payment Proof JavaScript for Com Orden Produccion
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof
 * @subpackage  PaymentProof
 * @since       3.1.5
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    let rowIndex = 1;
    let unpaidOrders = [];

    // Load unpaid orders data
    try {
        const dataElement = document.getElementById('unpaid-orders-data');
        if (dataElement) {
            unpaidOrders = JSON.parse(dataElement.textContent);
        }
    } catch (e) {
        console.error('Error loading unpaid orders data:', e);
    }

    // Initialize payment proof functionality
    initPaymentProof();

    function initPaymentProof() {
        setupDynamicOrderRows();
        setupPaymentAmountValidation();
        setupFormValidation();
        setupPaymentTypeChange();
    }

    function setupDynamicOrderRows() {
        const table = document.getElementById('payment-orders-table');
        const tbody = document.getElementById('payment-orders-body');
        
        if (!table || !tbody) {
            console.error('Table or tbody not found', {table: !!table, tbody: !!tbody});
            return;
        }

        console.log('Setting up dynamic order rows, unpaid orders:', unpaidOrders.length);

        // Add row button click handler (delegated on table, not tbody)
        table.addEventListener('click', function(e) {
            console.log('Click detected on table', e.target, e.target.className);
            
            // Check if clicked element or its parent is the add/remove button
            const addBtn = e.target.classList.contains('add-row-btn') ? e.target : e.target.closest('.add-row-btn');
            const removeBtn = e.target.classList.contains('remove-row-btn') ? e.target : e.target.closest('.remove-row-btn');

            if (addBtn) {
                console.log('Add button clicked!');
                e.preventDefault();
                e.stopPropagation();
                addOrderRow();
            } else if (removeBtn) {
                console.log('Remove button clicked!');
                e.preventDefault();
                e.stopPropagation();
                removeOrderRow(removeBtn);
            }
        });

        // Listen for value changes to update totals
        tbody.addEventListener('input', function(e) {
            if (e.target.classList.contains('payment-value-input')) {
                updateTotalAndValidate();
            }
        });

        // Listen for order selection changes
        tbody.addEventListener('change', function(e) {
            if (e.target.classList.contains('order-select')) {
                handleOrderSelection(e.target);
            }
        });

        // Initial total calculation
        updateTotalAndValidate();
    }

    function addOrderRow() {
        if (unpaidOrders.length === 0) {
            showAlert('warning', 'No hay órdenes con saldo pendiente para este cliente');
            return;
        }

        const tbody = document.getElementById('payment-orders-body');
        const newRow = document.createElement('tr');
        newRow.className = 'payment-order-row';
        newRow.setAttribute('data-row-index', rowIndex);

        // Create dropdown options (use remaining_balance for suggested amount)
        let options = '<option value="">Seleccionar orden...</option>';
        unpaidOrders.forEach(order => {
            const remaining = order.remaining_balance ?? order.invoice_value ?? 0;
            options += `<option value="${order.id}" data-invoice-value="${order.invoice_value}" data-remaining-balance="${remaining}">
                ${order.order_number} (Saldo: Q.${parseFloat(remaining).toFixed(2)})
            </option>`;
        });

        newRow.innerHTML = `
            <td>
                <input type="hidden" name="payment_orders[${rowIndex}][order_id]" class="order-id-input" value="">
                <select class="form-control order-select" required>
                    ${options}
                </select>
            </td>
            <td>
                <div class="input-group">
                    <span class="input-group-text">Q.</span>
                    <input type="number" 
                           name="payment_orders[${rowIndex}][value]" 
                           class="form-control payment-value-input" 
                           min="0.01" 
                           step="0.01" 
                           placeholder="0.00"
                           required>
                </div>
            </td>
            <td class="text-center">
                <button type="button" 
                        class="btn btn-sm btn-danger remove-row-btn" 
                        title="Eliminar orden">
                    <i class="fas fa-minus"></i>
                </button>
            </td>
        `;

        tbody.appendChild(newRow);
        rowIndex++;
    }

    function removeOrderRow(button) {
        const row = button.closest('tr');
        if (row) {
            row.remove();
            updateTotalAndValidate();
        }
    }

    function handleOrderSelection(selectElement) {
        const row = selectElement.closest('tr');
        const hiddenInput = row.querySelector('.order-id-input');
        const valueInput = row.querySelector('.payment-value-input');
        const selectedOption = selectElement.selectedOptions[0];

        if (selectedOption && selectedOption.value) {
            hiddenInput.value = selectedOption.value;
            
            // Pre-fill with remaining balance (or full invoice value if no partial payments)
            const remainingBalance = selectedOption.getAttribute('data-remaining-balance');
            const fallback = selectedOption.getAttribute('data-invoice-value');
            const defaultVal = remainingBalance || fallback;
            if (defaultVal && (!valueInput.value || valueInput.value === '0')) {
                valueInput.value = parseFloat(defaultVal).toFixed(2);
                updateTotalAndValidate();
            }
        } else {
            hiddenInput.value = '';
        }
    }

    function updateTotalAndValidate() {
        const valueInputs = document.querySelectorAll('.payment-value-input');
        const paymentAmountInput = document.getElementById('payment_amount');
        const totalInput = document.getElementById('payment-total');
        const totalRow = totalInput.closest('tr');

        let total = 0;
        valueInputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });

        // Update total display
        totalInput.value = total.toFixed(2);

        // Visual feedback: compare with payment amount
        if (paymentAmountInput && paymentAmountInput.value) {
            const paymentAmount = parseFloat(paymentAmountInput.value) || 0;
            const difference = Math.abs(total - paymentAmount);

            // Remove existing classes
            totalRow.classList.remove('table-success', 'table-warning');

            if (difference < 0.01) {
                // Match - green background
                totalRow.classList.add('table-success');
            } else if (total > 0) {
                // No match - yellow background
                totalRow.classList.add('table-warning');
            }
        }
    }

    function setupPaymentAmountValidation() {
        // Payment amount comes from payment lines total (hidden field updated by inline script)
        const paymentAmountInput = document.getElementById('payment_amount');
        if (paymentAmountInput) {
            paymentAmountInput.addEventListener('input', updateTotalAndValidate);
        }
    }

    function setupPaymentTypeChange() {
        // Handled by inline script for payment lines
    }

    function setupFormValidation() {
        const form = document.getElementById('payment-proof-form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });
    }

    function validateForm() {
        const paymentAmountInput = document.getElementById('payment_amount');
        const valueInputs = document.querySelectorAll('.payment-value-input');
        const lineAmounts = document.querySelectorAll('.payment-line-amount');
        const lineTypes = document.querySelectorAll('.payment-line-type');
        const lineDocs = document.querySelectorAll('.payment-line-row input[name*="document_number"], .payment-line-row input[name*="[document_number]"]');
        
        let isValid = true;
        let errorMessage = '';

        // Validate at least one payment line with amount and type
        let linesTotal = 0;
        for (let i = 0; i < lineAmounts.length; i++) {
            const amt = parseFloat(lineAmounts[i].value) || 0;
            if (amt > 0) {
                const row = lineAmounts[i].closest('tr');
                const typeSel = row && row.querySelector('.payment-line-type');
                const docInp = row && row.querySelector('input[name*="document_number"]');
                if (!typeSel || !typeSel.value) {
                    isValid = false;
                    errorMessage += '• Tipo de pago es requerido en cada línea\n';
                    break;
                }
                if (typeSel.value !== 'efectivo') {
                    const bankSel = row && row.querySelector('.payment-line-bank');
                    if (!bankSel || !bankSel.value) {
                        isValid = false;
                        errorMessage += '• Banco es requerido para pagos no efectivo\n';
                        break;
                    }
                }
                if (!docInp || !docInp.value.trim()) {
                    isValid = false;
                    errorMessage += '• Número de documento es requerido en cada línea\n';
                    break;
                }
                linesTotal += amt;
            }
        }
        if (linesTotal <= 0) {
            isValid = false;
            errorMessage += '• Agregue al menos una línea de pago con monto mayor a cero\n';
        }

        // Validate at least one order with value
        let hasValues = false;
        valueInputs.forEach(input => {
            if (parseFloat(input.value) > 0) {
                hasValues = true;
            }
        });

        if (!hasValues) {
            isValid = false;
            errorMessage += '• Debe ingresar al menos un valor para aplicar\n';
        }

        if (!isValid) {
            showAlert('error', 'Por favor corrija los siguientes errores:\n\n' + errorMessage);
        }

        return isValid;
    }

    function showAlert(type, message) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.payment-proof-alert');
        existingAlerts.forEach(alert => alert.remove());

        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} payment-proof-alert`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
            ${message.replace(/\n/g, '<br>')}
        `;

        document.body.appendChild(alertDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // File validation function (kept from original)
    window.validateFile = function(fileInput) {
        const file = fileInput.files[0];
        if (!file) return true;

        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            showAlert('error', 'Tipo de archivo no válido. Solo se permiten archivos JPG, PNG y PDF.');
            fileInput.value = '';
            return false;
        }
        
        if (file.size > maxSize) {
            showAlert('error', 'El archivo es demasiado grande. El tamaño máximo permitido es 5MB.');
            fileInput.value = '';
            return false;
        }

        return true;
    };
});
