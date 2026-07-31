/**
 * Payment Proof JavaScript for Com Orden Produccion
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof
 * @subpackage  PaymentProof
 * @since       3.1.5
 */

(function() {
    'use strict';

    function getOpts() {
        if (typeof Joomla !== 'undefined' && typeof Joomla.getOptions === 'function') {
            return Joomla.getOptions('com_ordenproduccion.paymentproof') || {};
        }
        return {};
    }

    function parseNum(v) {
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    window.PaymentProofCurrency = {
        getOpts: getOpts,

        getLineCurrency: function(row) {
            if (!row) return 'GTQ';
            var accSel = row.querySelector('.payment-line-bank-account');
            var accId = accSel ? parseInt(accSel.value, 10) : 0;
            if (!accId) return 'GTQ';
            var map = getOpts().bankAccountCurrencies || {};
            return (map[String(accId)] || map[accId] || 'GTQ') === 'USD' ? 'USD' : 'GTQ';
        },

        updateLinePrefix: function(row) {
            if (!row) return;
            var cur = this.getLineCurrency(row);
            var prefix = row.querySelector('.payment-line-currency-prefix');
            if (prefix) prefix.textContent = cur === 'USD' ? 'USD' : 'Q.';
        },

        getLineRate: function(row) {
            if (!row) return 0;
            var hid = row.querySelector('.payment-line-exchange-rate');
            return hid ? parseNum(hid.value) : 0;
        },

        fetchRateForLine: function(row) {
            var self = this;
            if (!row || self.getLineCurrency(row) !== 'USD') {
                return Promise.resolve(0);
            }
            var dateInp = row.querySelector('.payment-line-document-date');
            var dateVal = dateInp && dateInp.value ? dateInp.value : '';
            if (!dateVal) {
                dateVal = new Date().toISOString().slice(0, 10);
            }
            var url = (getOpts().exchangeRateUrl || '') + (getOpts().exchangeRateUrl.indexOf('?') >= 0 ? '&' : '?') + 'issue_date=' + encodeURIComponent(dateVal);
            return fetch(url, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    var rate = j && j.success && j.exchange_rate != null ? parseNum(j.exchange_rate) : 0;
                    var hid = row.querySelector('.payment-line-exchange-rate');
                    if (hid && rate > 0) hid.value = rate.toFixed(6);
                    self.updateLineHint(row);
                    return rate;
                })
                .catch(function() { return 0; });
        },

        getLineAmountGtq: function(row) {
            if (!row) return 0;
            var amt = parseNum((row.querySelector('.payment-line-amount') || {}).value);
            if (this.getLineCurrency(row) === 'USD') {
                var rate = this.getLineRate(row);
                if (rate > 0) return Math.round(amt * rate * 100) / 100;
                return 0;
            }
            return amt;
        },

        getLinesTotalGtq: function() {
            var self = this;
            var sum = 0;
            document.querySelectorAll('#payment-lines-body tr').forEach(function(row) {
                sum += self.getLineAmountGtq(row);
            });
            return sum;
        },

        updateLineHint: function(row) {
            if (!row) return;
            var hint = row.querySelector('.payment-line-gtq-hint');
            if (!hint) return;
            if (this.getLineCurrency(row) !== 'USD') {
                hint.classList.add('d-none');
                hint.textContent = '';
                return;
            }
            var amt = parseNum((row.querySelector('.payment-line-amount') || {}).value);
            var rate = this.getLineRate(row);
            if (amt > 0 && rate > 0) {
                var gtq = Math.round(amt * rate * 100) / 100;
                hint.textContent = '≈ Q ' + gtq.toFixed(2) + ' @ ' + rate.toFixed(4);
                hint.classList.remove('d-none');
            } else {
                hint.classList.add('d-none');
                hint.textContent = '';
            }
        },

        updateLinesTotalDisplay: function() {
            var self = this;
            var hasUsd = false;
            document.querySelectorAll('#payment-lines-body tr').forEach(function(row) {
                self.updateLinePrefix(row);
                if (self.getLineCurrency(row) === 'USD') hasUsd = true;
                self.updateLineHint(row);
            });
            var gtqTotal = self.getLinesTotalGtq();
            var el = document.getElementById('payment-lines-total');
            if (el) {
                el.textContent = 'Q. ' + gtqTotal.toFixed(2);
            }
            var hintEl = document.getElementById('payment-lines-total-gtq-hint');
            if (hintEl) {
                if (hasUsd) {
                    hintEl.textContent = ' (equivalente GTQ para saldo)';
                    hintEl.classList.remove('d-none');
                } else {
                    hintEl.textContent = '';
                    hintEl.classList.add('d-none');
                }
            }
            var amt = document.getElementById('payment_amount');
            if (amt) amt.value = gtqTotal.toFixed(2);
            return gtqTotal;
        },

        bindLineRow: function(row) {
            var self = this;
            if (!row) return;
            var accSel = row.querySelector('.payment-line-bank-account');
            var dateInp = row.querySelector('.payment-line-document-date');
            var amtInp = row.querySelector('.payment-line-amount');
            function onUsdFieldChange() {
                self.updateLinePrefix(row);
                if (self.getLineCurrency(row) === 'USD') {
                    self.fetchRateForLine(row).then(function() {
                        self.updateLinesTotalDisplay();
                    });
                } else {
                    var hid = row.querySelector('.payment-line-exchange-rate');
                    if (hid) hid.value = '';
                    self.updateLineHint(row);
                    self.updateLinesTotalDisplay();
                }
            }
            if (accSel) accSel.addEventListener('change', onUsdFieldChange);
            if (dateInp) dateInp.addEventListener('change', onUsdFieldChange);
            if (amtInp) amtInp.addEventListener('input', function() {
                self.updateLineHint(row);
                self.updateLinesTotalDisplay();
            });
            self.updateLinePrefix(row);
        },

        init: function() {
            var body = document.getElementById('payment-lines-body');
            if (!body) return;
            var self = this;
            body.querySelectorAll('tr').forEach(function(row) { self.bindLineRow(row); });
            self.updateLinesTotalDisplay();
        }
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    let rowIndex = 1;
    let unpaidOrders = [];

    function getPaymentTypeRequiresBankMap() {
        if (typeof Joomla !== 'undefined' && typeof Joomla.getOptions === 'function') {
            const opts = Joomla.getOptions('com_ordenproduccion.paymentproof') || {};
            return opts.paymentTypeRequiresBank || { efectivo: false };
        }
        return { efectivo: false };
    }

    function paymentTypeNeedsBank(typeCode) {
        if (!typeCode) {
            return true;
        }
        const map = getPaymentTypeRequiresBankMap();
        if (Object.prototype.hasOwnProperty.call(map, typeCode)) {
            return !!map[typeCode];
        }
        return typeCode !== 'efectivo';
    }

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
    if (window.PaymentProofCurrency) {
        window.PaymentProofCurrency.init();
    }

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

        function getSubmitButtons() {
            return form.querySelectorAll('button[type="submit"], input[type="submit"]');
        }

        function lockSubmitButtons() {
            getSubmitButtons().forEach(function(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                submitBtn.setAttribute('aria-disabled', 'true');
            });
        }

        function unlockSubmitButtons() {
            form.dataset.submitting = '0';
            getSubmitButtons().forEach(function(submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.removeAttribute('aria-disabled');
            });
        }

        function isSubmitLocked() {
            return form.dataset.submitting === '1' || form._mismatchConfirmed === true;
        }

        function lockSubmitFlow() {
            form.dataset.submitting = '1';
            lockSubmitButtons();
        }

        getSubmitButtons().forEach(function(submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                if (isSubmitLocked()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
            }, true);
        });

        function getLinesTotal() {
            if (window.PaymentProofCurrency && typeof window.PaymentProofCurrency.getLinesTotalGtq === 'function') {
                return window.PaymentProofCurrency.getLinesTotalGtq();
            }
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
            if (isSubmitLocked()) {
                return;
            }
            if (mismatchNoteTa && mismatchNoteHidden) {
                mismatchNoteHidden.value = (mismatchNoteTa.value || '').trim();
            }
            if (mismatchDiffHidden && form._pendingMismatchDiff != null) {
                mismatchDiffHidden.value = String(form._pendingMismatchDiff);
            }
            form._mismatchConfirmed = true;
            if (proceedBtn) {
                proceedBtn.disabled = true;
            }
            if (mismatchModal) mismatchModal.hide();
            lockSubmitFlow();
            form.submit();
        }

        if (proceedBtn) {
            proceedBtn.addEventListener('click', function() {
                submitFormWithMismatch();
            });
        }

        form.addEventListener('submit', function(e) {
            if (form._mismatchConfirmed) {
                lockSubmitFlow();
                return;
            }

            if (form.dataset.submitting === '1') {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }

            lockSubmitFlow();

            if (!validateForm()) {
                e.preventDefault();
                unlockSubmitButtons();
                return false;
            }

            const linesTotal = getLinesTotal();
            const ordersTotal = getOrdersTotal();
            const difference = Math.abs(linesTotal - ordersTotal);

            if (difference >= 0.01 && mismatchModal && mismatchDifferenceText) {
                e.preventDefault();
                unlockSubmitButtons();
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
                if (paymentTypeNeedsBank(typeSel.value)) {
                    const bankSel = row && row.querySelector('.payment-line-bank');
                    if (!bankSel || !bankSel.value) {
                        isValid = false;
                        errorMessage += '• Banco es requerido para este tipo de pago\n';
                        break;
                    }
                    const accSel = row && row.querySelector('.payment-line-bank-account');
                    if (!accSel || !String(accSel.value || '').trim()) {
                        isValid = false;
                        errorMessage += '• Cuenta bancaria es requerida para este tipo de pago\n';
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
