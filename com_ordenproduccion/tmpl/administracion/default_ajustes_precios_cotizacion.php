<?php
/**
 * Ajustes → Ajustes Precios de Cotización.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

$loadUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.loadAjustesPreciosCotizacion&format=json', false);
$saveUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveAjustesPreciosCotizacionLine&format=json', false);
$otUrl     = Route::_('index.php?option=com_ordenproduccion&task=administracion.applyAjustesPreciosOrdenTrabajo&format=json', false);
$token     = Session::getFormToken();
$initialCot = trim((string) $app->input->getString('cot_number', ''));
?>
<div class="card" id="ajustes-precios-cotizacion">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-tags"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_COT_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_COT_DESC'); ?></p>

        <div class="row g-3 align-items-end mb-4">
            <div class="col-md-4">
                <label for="apc-cot-number" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_COT_NUMBER'); ?></label>
                <input type="text" id="apc-cot-number" class="form-control" placeholder="COT-000001"
                       value="<?php echo htmlspecialchars($initialCot, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-primary w-100" id="apc-btn-load">
                    <i class="fas fa-search"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_LOAD'); ?>
                </button>
            </div>
        </div>

        <div id="apc-alert" class="alert d-none" role="alert"></div>

        <div id="apc-context" class="d-none">
            <div class="border rounded bg-light p-3 mb-3">
                <div class="row g-2">
                    <div class="col-md-3"><strong><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACION'); ?>:</strong> <span id="apc-meta-number"></span></div>
                    <div class="col-md-4"><strong><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?>:</strong> <span id="apc-meta-client"></span></div>
                    <div class="col-md-2"><strong><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_DATE'); ?>:</strong> <span id="apc-meta-date"></span></div>
                    <div class="col-md-3"><strong><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL'); ?>:</strong> <span id="apc-meta-total"></span></div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="apc-display-currency" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_CURRENCY'); ?></label>
                    <select id="apc-display-currency" class="form-select">
                        <option value="GTQ">GTQ (Q)</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="apc-exchange-rate" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_EXCHANGE_RATE'); ?></label>
                    <input type="text" inputmode="decimal" id="apc-exchange-rate" class="form-control" placeholder="7.75000">
                    <div class="form-text" id="apc-rate-hint"></div>
                </div>
            </div>

            <input type="hidden" id="apc-quotation-id" value="0">

            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle" id="apc-lines-table">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_DESCRIPCION'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_TH_CANT'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_CURRENT'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_NEW'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="apc-lines-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var loadUrl = <?php echo json_encode($loadUrl); ?>;
    var saveUrl = <?php echo json_encode($saveUrl); ?>;
    var otUrl = <?php echo json_encode($otUrl); ?>;
    var token = <?php echo json_encode($token); ?>;
    var i18n = {
        loadError: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_LOAD_ERROR')); ?>,
        saved: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_LINE_SAVED')); ?>,
        otConfirm: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_OT_CONFIRM')); ?>,
        otUpdated: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_OT_UPDATED')); ?>,
        rateStored: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_RATE_STORED')); ?>,
        rateBanguat: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_RATE_BANGUAT')); ?>,
        impuestoNote: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_PRECIOS_IMPRENTA_READONLY')); ?>
    };

    var state = { quotationId: 0, exchangeRate: null, displayCurrency: 'GTQ' };

    function el(id) { return document.getElementById(id); }
    function showAlert(type, msg) {
        var box = el('apc-alert');
        if (!box) return;
        box.className = 'alert alert-' + type;
        box.textContent = msg;
        box.classList.remove('d-none');
    }
    function hideAlert() {
        var box = el('apc-alert');
        if (box) box.classList.add('d-none');
    }
    function fmtAmt(gtq, rate, cur, dec) {
        cur = (cur || 'GTQ').toUpperCase();
        dec = dec == null ? 2 : dec;
        if (cur === 'USD' && rate > 0) {
            return 'USD ' + (gtq / rate).toFixed(dec);
        }
        return 'Q ' + Number(gtq).toFixed(dec);
    }
    function parseNum(v) {
        var n = parseFloat(String(v || '').replace(/,/g, ''));
        return isNaN(n) ? 0 : n;
    }
    function getRate() {
        return parseNum(el('apc-exchange-rate') && el('apc-exchange-rate').value);
    }
    function renderLines(lines) {
        var tbody = el('apc-lines-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        var rate = getRate();
        var cur = el('apc-display-currency') ? el('apc-display-currency').value : 'GTQ';
        (lines || []).forEach(function (line) {
            var tr = document.createElement('tr');
            if (line.is_impuesto_line) {
                tr.className = 'table-light';
            }
            var pre = line.pre_cotizacion_number || '—';
            var desc = line.descripcion || '';
            var qty = line.cantidad != null ? line.cantidad : '';
            var curAmt = fmtAmt(line.line_total_gtq, rate, cur, 2);
            var editCell = '';
            if (line.can_edit_price) {
                var dispVal = cur === 'USD' && rate > 0 ? (line.line_total_gtq / rate).toFixed(2) : Number(line.line_total_gtq).toFixed(2);
                editCell = '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end apc-new-amount" '
                    + 'data-item-id="' + line.id + '" value="' + dispVal + '">'
                    + '<button type="button" class="btn btn-sm btn-success mt-1 apc-btn-save-line" data-item-id="' + line.id + '">'
                    + '<i class="fas fa-save"></i></button>';
            } else {
                editCell = '<span class="text-muted small">' + i18n.impuestoNote + '</span>';
            }
            tr.innerHTML = '<td>' + pre + '</td>'
                + '<td>' + desc + '</td>'
                + '<td class="text-end">' + qty + '</td>'
                + '<td class="text-end apc-cur-amt" data-gtq="' + line.line_total_gtq + '">' + curAmt + '</td>'
                + '<td class="text-end">' + editCell + '</td>'
                + '<td></td>';
            tbody.appendChild(tr);
        });
    }
    function applyContext(data) {
        state.quotationId = data.quotation_id || 0;
        el('apc-quotation-id').value = state.quotationId;
        el('apc-meta-number').textContent = data.quotation_number || '';
        el('apc-meta-client').textContent = data.client_name || '';
        el('apc-meta-date').textContent = data.quote_date || '';
        el('apc-meta-total').textContent = fmtAmt(data.total_amount_gtq, getRate(), el('apc-display-currency').value, 2);
        if (data.exchange_rate != null && data.exchange_rate > 0) {
            el('apc-exchange-rate').value = Number(data.exchange_rate).toFixed(5);
            state.exchangeRate = data.exchange_rate;
        }
        var hint = data.exchange_rate_source === 'banguat' ? i18n.rateBanguat : i18n.rateStored;
        if (data.exchange_rate_date) {
            hint += ' (' + data.exchange_rate_date + ')';
        }
        el('apc-rate-hint').textContent = hint;
        renderLines(data.lines || []);
        el('apc-context').classList.remove('d-none');
    }
    function loadCotizacion() {
        hideAlert();
        var num = (el('apc-cot-number').value || '').trim();
        if (!num) return;
        var url = loadUrl + (loadUrl.indexOf('?') >= 0 ? '&' : '?') + 'cot_number=' + encodeURIComponent(num);
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.success) {
                    showAlert('danger', (j && j.message) ? j.message : i18n.loadError);
                    el('apc-context').classList.add('d-none');
                    return;
                }
                applyContext(j.data || {});
            })
            .catch(function () { showAlert('danger', i18n.loadError); });
    }
    function saveLine(itemId, btn) {
        hideAlert();
        var rowInput = document.querySelector('.apc-new-amount[data-item-id="' + itemId + '"]');
        if (!rowInput) return;
        var fd = new FormData();
        fd.append(token, '1');
        fd.append('quotation_id', String(state.quotationId));
        fd.append('item_id', String(itemId));
        fd.append('new_amount', rowInput.value);
        fd.append('display_currency', el('apc-display-currency').value);
        fd.append('exchange_rate', el('apc-exchange-rate').value);
        if (btn) btn.disabled = true;
        fetch(saveUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (btn) btn.disabled = false;
                if (!j || !j.success) {
                    showAlert('danger', (j && j.message) ? j.message : i18n.loadError);
                    return;
                }
                showAlert('success', j.message || i18n.saved);
                var d = j.data || {};
                if (d.total_amount_gtq != null) {
                    el('apc-meta-total').textContent = fmtAmt(d.total_amount_gtq, getRate(), el('apc-display-currency').value, 2);
                }
                if (d.lines) {
                    renderLines(d.lines);
                }
                var ot = d.orden_trabajo;
                if (ot && ot.pre_cotizacion_id) {
                    var msg = i18n.otConfirm
                        .replace('{ORDER}', ot.order_number || '')
                        .replace('{CURRENT}', fmtAmt(ot.current_invoice_value, getRate(), 'GTQ', 2))
                        .replace('{NEW}', fmtAmt(ot.suggested_value, getRate(), 'GTQ', 2));
                    if (window.confirm(msg)) {
                        var fd2 = new FormData();
                        fd2.append(token, '1');
                        fd2.append('pre_cotizacion_id', String(ot.pre_cotizacion_id));
                        fd2.append('invoice_value_gtq', String(ot.suggested_value));
                        fetch(otUrl, { method: 'POST', body: fd2, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r2) { return r2.json(); })
                            .then(function (j2) {
                                if (j2 && j2.success) {
                                    showAlert('success', j2.message || i18n.otUpdated);
                                } else {
                                    showAlert('warning', (j2 && j2.message) ? j2.message : i18n.loadError);
                                }
                            });
                    }
                }
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                showAlert('danger', i18n.loadError);
            });
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (t && t.closest && t.closest('#apc-btn-load')) {
            ev.preventDefault();
            loadCotizacion();
        }
        var saveBtn = t && t.closest ? t.closest('.apc-btn-save-line') : null;
        if (saveBtn) {
            ev.preventDefault();
            saveLine(parseInt(saveBtn.getAttribute('data-item-id') || '0', 10), saveBtn);
        }
    });
    if (el('apc-display-currency')) {
        el('apc-display-currency').addEventListener('change', function () {
            loadCotizacion();
        });
    }
    if (el('apc-cot-number') && el('apc-cot-number').value.trim() !== '') {
        loadCotizacion();
    }
})();
</script>
