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

        // Order search: type-to-search (delegated)
        table.addEventListener('focusin', function(e) {
            if (e.target.classList.contains('order-search-input')) {
                filterAndShowOrderResults(e.target);
            }
        });
        table.addEventListener('input', function(e) {
            if (e.target.classList.contains('order-search-input')) {
                filterAndShowOrderResults(e.target);
            }
        });
        table.addEventListener('click', function(e) {
            const item = e.target.closest('.order-search-result-item');
            if (item) {
                e.preventDefault();
                handleOrderSearchSelect(item);
            }
        });
        table.addEventListener('focusout', function(e) {
            if (e.target.classList.contains('order-search-input')) {
                const wrap = e.target.closest('.order-search-wrap');
                setTimeout(function() {
                    if (wrap && wrap.querySelector('.order-search-results')) {
                        wrap.querySelector('.order-search-results').style.display = 'none';
                    }
                }, 200);
            }
        });

        // Initial total calculation
        updateTotalAndValidate();
    }

    function filterAndShowOrderResults(inputEl) {
        const wrap = inputEl.closest('.order-search-wrap');
        const listEl = wrap && wrap.querySelector('.order-search-results');
        if (!listEl) return;
        const q = (inputEl.value || '').trim().toLowerCase();
        const row = inputEl.closest('tr');
        const hiddenInput = row && row.querySelector('.order-id-input');
        const alreadySelected = hiddenInput ? (hiddenInput.value || '').trim() : '';
        const maxResults = 15;
        let matches = unpaidOrders.filter(function(o) {
            const num = (o.order_number || '').toString().toLowerCase();
            const idStr = (o.id || '').toString();
            return (num.indexOf(q) !== -1 || idStr.indexOf(q) !== -1) && (q.length >= 1 || alreadySelected === idStr);
        }).slice(0, maxResults);
        listEl.innerHTML = '';
        if (q.length === 0) {
            matches = unpaidOrders.slice(0, maxResults);
        }
        matches.forEach(function(order) {
            const remaining = order.remaining_balance ?? order.invoice_value ?? 0;
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action order-search-result-item';
            li.setAttribute('data-order-id', order.id);
            li.setAttribute('data-order-number', order.order_number || '');
            li.setAttribute('data-amount', (remaining || order.invoice_value || 0));
            li.textContent = (order.order_number || '') + ' — Saldo: Q.' + parseFloat(remaining || 0).toFixed(2);
            listEl.appendChild(li);
        });
        listEl.style.display = matches.length ? 'block' : 'none';
    }

    function handleOrderSearchSelect(itemEl) {
        const row = itemEl.closest('tr');
        if (!row) return;
        const wrap = itemEl.closest('.order-search-wrap');
        const hiddenInput = row.querySelector('.order-id-input');
        const searchInput = row.querySelector('.order-search-input');
        const valueInput = row.querySelector('.payment-value-input');
        const orderId = itemEl.getAttribute('data-order-id');
        const orderNumber = itemEl.getAttribute('data-order-number');
        const amount = itemEl.getAttribute('data-amount');
        if (hiddenInput) hiddenInput.value = orderId || '';
        if (searchInput) searchInput.value = orderNumber || '';
        if (valueInput && amount !== null && amount !== '') {
            valueInput.value = parseFloat(amount).toFixed(2);
        }
        if (wrap && wrap.querySelector('.order-search-results')) {
            wrap.querySelector('.order-search-results').style.display = 'none';
        }
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

        newRow.innerHTML = `
            <td>
                <input type="hidden" name="payment_orders[${rowIndex}][order_id]" class="order-id-input" value="">
                <div class="order-search-wrap position-relative">
                    <input type="text" class="form-control order-search-input" placeholder="Escriba número de orden para buscar..." autocomplete="off" required>
                    <ul class="order-search-results list-group position-absolute" style="display:none; z-index: 1000; max-height: 200px; overflow-y: auto; min-width: 100%;"></ul>
                </div>
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

        const mismatchModalEl = document.getElementById('payment-mismatch-modal');
        const mismatchModal = mismatchModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(mismatchModalEl, { backdrop: 'static' }) : null;
        const mismatchDifferenceText = document.getElementById('payment-mismatch-difference-text');
        const mismatchNoteTa = document.getElementById('payment-mismatch-note-ta');
        const mismatchNoteHidden = document.getElementById('payment_mismatch_note');
        const mismatchDiffHidden = document.getElementById('payment_mismatch_difference');
        const proceedBtn = document.getElementById('payment-mismatch-proceed-btn');

        function getLinesTotal() {
            let sum = 0;
            document.querySelectorAll('.payment-line-amount').forEach(function(inp) {
                sum += parseFloat(inp.value) || 0;
            });
            return sum;
        }

        function getOrdersTotal() {
            let sum = 0;
            document.querySelectorAll('.payment-value-input').forEach(function(inp) {
                sum += parseFloat(inp.value) || 0;
            });
            return sum;
        }

        function submitFormWithMismatch() {
            if (mismatchNoteTa && mismatchNoteHidden) {
                mismatchNoteHidden.value = (mismatchNoteTa.value || '').trim();
            }
            if (mismatchDiffHidden && form._pendingMismatchDiff != null) {
                mismatchDiffHidden.value = String(form._pendingMismatchDiff);
            }
            form._mismatchConfirmed = true;
            if (mismatchModal) mismatchModal.hide();
            form.submit();
        }

        if (proceedBtn) {
            proceedBtn.addEventListener('click', function() {
                submitFormWithMismatch();
            });
        }

        form.addEventListener('submit', function(e) {
            if (form._mismatchConfirmed) {
                return;
            }
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }

            const linesTotal = getLinesTotal();
            const ordersTotal = getOrdersTotal();
            const difference = Math.abs(linesTotal - ordersTotal);

            if (difference >= 0.01 && mismatchModal && mismatchDifferenceText) {
                e.preventDefault();
                form._pendingMismatchDiff = (linesTotal - ordersTotal);
                const diffStr = (form._pendingMismatchDiff >= 0 ? 'Q. ' : '-Q. ') + Math.abs(form._pendingMismatchDiff).toFixed(2);
                mismatchDifferenceText.textContent = diffStr;
                if (mismatchNoteTa) {
                    mismatchNoteTa.value = '';
                }
                if (mismatchNoteHidden) mismatchNoteHidden.value = '';
                if (mismatchDiffHidden) mismatchDiffHidden.value = '';
                mismatchModal.show();
                setTimeout(function() {
                    if (mismatchNoteTa) mismatchNoteTa.focus();
                }, 300);
                return false;
            }

            var submitBtn = form.querySelector('button[type="submit"]');
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

        // Validate at least one order with value and that each row with value has an order selected
        let hasValues = false;
        let needOrderSelection = false;
        valueInputs.forEach(input => {
            const val = parseFloat(input.value) || 0;
            if (val > 0) {
                hasValues = true;
                const row = input.closest('tr');
                const orderIdInput = row && row.querySelector('.order-id-input');
                if (orderIdInput && !orderIdInput.value.trim()) {
                    needOrderSelection = true;
                }
            }
        });
        if (needOrderSelection) {
            isValid = false;
            errorMessage += '• Escriba el número de orden y seleccione una orden de la lista para cada fila con monto\n';
        }

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
