/**
 * Cotización GTQ/USD display toggle (stored amounts remain GTQ).
 *
 * @since 3.119.225
 */
(function () {
    'use strict';

    function parseNum(v) {
        var n = parseFloat(String(v).replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function formatGtq(n, dec) {
        return 'Q ' + n.toFixed(dec);
    }

    function formatUsd(n, dec) {
        return 'USD ' + n.toFixed(dec);
    }

    function getStorageKey(qId) {
        return 'com_ordenproduccion_cotizacion_currency_' + qId;
    }

    window.CotizacionCurrency = {
        refresh: function () {
            var root = document.getElementById('cotizacion-currency-toggle');
            if (!root) {
                return;
            }
            var rate = parseNum(root.getAttribute('data-exchange-rate'));
            var cur = root.getAttribute('data-active-currency') || 'GTQ';
            var dec;
            var gtq;
            var val;

            document.querySelectorAll('.cotizacion-amt').forEach(function (el) {
                gtq = parseNum(el.getAttribute('data-gtq'));
                dec = parseInt(el.getAttribute('data-decimals') || '2', 10);
                if (cur === 'USD' && rate > 0) {
                    val = gtq / rate;
                    el.textContent = formatUsd(val, dec);
                } else {
                    el.textContent = formatGtq(gtq, dec);
                }
            });

            var pdfId = root.getAttribute('data-pdf-link-id');
            if (pdfId) {
                var pdfLink = document.getElementById(pdfId);
                if (pdfLink) {
                    var base = pdfLink.getAttribute('data-base-href') || pdfLink.href;
                    var qIdx = base.indexOf('?');
                    var path = qIdx >= 0 ? base.substring(0, qIdx) : base;
                    var params = new URLSearchParams(qIdx >= 0 ? base.substring(qIdx + 1) : '');
                    if (cur === 'USD' && rate > 0) {
                        params.set('display_currency', 'USD');
                    } else {
                        params.delete('display_currency');
                    }
                    var qs = params.toString();
                    pdfLink.href = qs ? path + '?' + qs : path;
                }
            }
        },

        setCurrency: function (cur) {
            var root = document.getElementById('cotizacion-currency-toggle');
            if (!root) {
                return;
            }
            cur = cur === 'USD' ? 'USD' : 'GTQ';
            root.setAttribute('data-active-currency', cur);
            var qId = root.getAttribute('data-quotation-id');
            if (qId) {
                try {
                    localStorage.setItem(getStorageKey(qId), cur);
                } catch (e) {
                }
            }
            root.querySelectorAll('[data-currency-choice]').forEach(function (btn) {
                var choice = btn.getAttribute('data-currency-choice');
                var active = choice === cur;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            window.CotizacionCurrency.refresh();
        },

        updateAmt: function (el, gtq, decimals) {
            if (!el) {
                return;
            }
            el.setAttribute('data-gtq', String(gtq));
            if (decimals !== undefined) {
                el.setAttribute('data-decimals', String(decimals));
            }
            window.CotizacionCurrency.refresh();
        },

        init: function () {
            var root = document.getElementById('cotizacion-currency-toggle');
            if (!root) {
                return;
            }
            var qId = root.getAttribute('data-quotation-id');
            var stored = 'GTQ';
            if (qId) {
                try {
                    stored = localStorage.getItem(getStorageKey(qId)) || 'GTQ';
                } catch (e) {
                    stored = 'GTQ';
                }
            }
            if (stored !== 'USD') {
                stored = 'GTQ';
            }
            root.querySelectorAll('[data-currency-choice]').forEach(function (btn) {
                btn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    window.CotizacionCurrency.setCurrency(btn.getAttribute('data-currency-choice'));
                });
            });
            window.CotizacionCurrency.setCurrency(stored);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.CotizacionCurrency.init);
    } else {
        window.CotizacionCurrency.init();
    }
})();
