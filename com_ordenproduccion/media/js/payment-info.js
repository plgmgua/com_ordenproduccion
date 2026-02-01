/**
 * Payment Info Popup for Com Orden Produccion
 * Fetches and displays payment information for work orders
 * @since 3.54.0
 */
(function() {
    'use strict';

    const paymentTypeLabels = {
        efectivo: 'Efectivo',
        cheque: 'Cheque',
        transferencia: 'Transferencia',
        deposito: 'Depósito'
    };

    window.showPaymentInfoPopup = function(orderId, baseUrl, token) {
        const modal = document.getElementById('paymentInfoModal');
        const body = document.getElementById('paymentInfoBody');
        const loader = document.getElementById('paymentInfoLoader');
        const content = document.getElementById('paymentInfoContent');
        const errorDiv = document.getElementById('paymentInfoError');

        if (!modal || !body) return;

        body.innerHTML = '';
        loader.style.display = 'block';
        content.style.display = 'none';
        if (errorDiv) errorDiv.style.display = 'none';

        const url = baseUrl + '&order_id=' + orderId + '&' + token + '=1';
        fetch(url)
            .then(r => r.json())
            .then(data => {
                loader.style.display = 'none';
                if (data.success) {
                    content.style.display = 'block';
                    content.innerHTML = renderPaymentInfo(data);
                } else {
                    if (errorDiv) {
                        errorDiv.textContent = data.message || 'Error loading payment info';
                        errorDiv.style.display = 'block';
                    }
                }
            })
            .catch(err => {
                loader.style.display = 'none';
                if (errorDiv) {
                    errorDiv.textContent = 'Error loading payment info';
                    errorDiv.style.display = 'block';
                }
            });

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    };

    function renderPaymentInfo(data) {
        let html = '<div class="payment-info-summary mb-4">';
        html += '<table class="table table-sm"><tbody>';
        html += '<tr><th>Valor a facturar:</th><td>Q ' + formatNum(data.invoice_value) + '</td></tr>';
        html += '<tr><th>Total pagado:</th><td>Q ' + formatNum(data.total_paid) + '</td></tr>';
        html += '<tr><th>Saldo pendiente:</th><td>Q ' + formatNum(data.remaining_balance) + '</td></tr>';
        html += '</tbody></table></div>';

        if (data.payment_proofs && data.payment_proofs.length > 0) {
            html += '<h6>Comprobantes de pago</h6>';
            html += '<table class="table table-sm table-bordered"><thead><tr>';
            html += '<th>Documento</th><th>Tipo</th><th>Monto doc.</th><th>Aplicado</th><th>Archivo</th></tr></thead><tbody>';
            data.payment_proofs.forEach(function(p) {
                const typeLabel = paymentTypeLabels[p.payment_type] || p.payment_type;
                const fileUrl = p.file_path ? (p.file_path.startsWith('http') ? p.file_path : (window.location.origin + '/' + p.file_path.replace(/^\//, ''))) : '';
                const fileLink = fileUrl ? '<a href="' + escapeHtml(fileUrl) + '" target="_blank"><i class="fas fa-file-pdf"></i></a>' : '-';
                html += '<tr>';
                html += '<td>' + escapeHtml(p.document_number || '') + '</td>';
                html += '<td>' + escapeHtml(typeLabel) + '</td>';
                html += '<td>Q ' + formatNum(p.payment_amount) + '</td>';
                html += '<td>Q ' + formatNum(p.amount_applied) + '</td>';
                html += '<td>' + fileLink + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<p class="text-muted">No hay comprobantes de pago registrados.</p>';
        }
        return html;
    }

    function formatNum(n) {
        return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }
})();
