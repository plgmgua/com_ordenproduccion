<?php
/**
 * Manual FEL invoice modal (cotización display).
 *
 * @var callable $l
 * @var int $quotationId
 * @var string $manualFelIssueUrl
 * @var string $digifactVerifyCuiUrl
 * @var bool $manualFelBillingIsCf
 * @var string $manualBuyerNameInitial
 * @var string $manualBuyerNitInitial
 * @var array<int, array{descripcion: string, cantidad: float, precio_unitario: float}> $manualFelLinePresets
 * @var array<int, array{id: int, label: string, valor: float}> $manualFelOrdensForClient
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$manualFelLinePresets = is_array($manualFelLinePresets ?? null) ? $manualFelLinePresets : [];
$manualFelOrdensForClient = is_array($manualFelOrdensForClient ?? null) ? $manualFelOrdensForClient : [];
?>
<div class="modal fade" id="manual-fel-invoice-modal" tabindex="-1" aria-labelledby="manualFelInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualFelInvoiceModalLabel"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_MODAL_TITLE', 'Manual invoice', 'Factura manual')); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE')); ?>"></button>
            </div>
            <div class="modal-body">
                <div id="manual-fel-modal-alert" class="alert alert-danger py-2 small mb-3 d-none" role="alert"></div>
                <p class="small text-muted"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_MODAL_INTRO', 'Edit buyer data and lines, associate work orders, then generate the FEL via Digifact.', 'Edite datos del cliente y líneas, asocie órdenes de trabajo y genere la factura FEL por Digifact.')); ?></p>
                <form id="manual-fel-token-form" class="d-none"><?php echo HTMLHelper::_('form.token'); ?></form>
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label small mb-1" for="manual-fel-buyer-name"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_NAME', 'Client name', 'Nombre del cliente')); ?></label>
                        <input type="text" class="form-control form-control-sm" id="manual-fel-buyer-name" maxlength="500"
                            value="<?php echo htmlspecialchars($manualBuyerNameInitial ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-initial="<?php echo htmlspecialchars($manualBuyerNameInitial ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="manual-fel-buyer-nit"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_NIT', 'NIT / ID', 'NIT / ID')); ?></label>
                        <input type="text" class="form-control form-control-sm" id="manual-fel-buyer-nit" maxlength="50"
                            value="<?php echo htmlspecialchars($manualBuyerNitInitial ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-initial="<?php echo htmlspecialchars($manualBuyerNitInitial ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="manual-fel-buyer-address"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_ADDRESS', 'Address', 'Dirección')); ?></label>
                        <input type="text" class="form-control form-control-sm" id="manual-fel-buyer-address" maxlength="255" value="Ciudad" />
                    </div>
                </div>
                <?php if (!empty($manualFelBillingIsCf)) : ?>
                <div id="manual-fel-cf-cui-wrap" class="border rounded p-3 mb-3 bg-light">
                    <p class="small mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_DIGIFACT_CF_CUI_BLOCK_INTRO', 'Billing ID is consumidor final (CF). Enter the buyer\'s CUI and validate with Digifact before generating.', 'El NIT de facturación es consumidor final (CF). Ingrese el CUI del comprador y valídelo con Digifact antes de generar.')); ?></p>
                    <label class="form-label small mb-1" for="manual-fel-cui-input"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_DIGIFACT_CF_CUI_LABEL', 'CUI', 'CUI')); ?></label>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <input type="text" class="form-control" id="manual-fel-cui-input" inputmode="numeric" maxlength="32" autocomplete="off" style="max-width: 16rem;" />
                        <button type="button" class="btn btn-outline-primary btn-sm" id="manual-fel-cui-validate-btn">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_DIGIFACT_CF_CUI_VALIDATE_BTN', 'Validate', 'Validar')); ?>
                        </button>
                    </div>
                    <div id="manual-fel-cui-msg" class="small mt-2" role="status"></div>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-uppercase text-muted small"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_LINES_HEADING', 'Invoice lines', 'Líneas de factura')); ?></h6>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="manual-fel-add-line-btn">
                        <i class="fas fa-plus" aria-hidden="true"></i> <?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_ADD_LINE', 'Add line', 'Agregar línea')); ?>
                    </button>
                </div>
                <div class="table-responsive mb-3" style="max-height: 40vh;">
                    <table class="table table-sm table-bordered align-middle mb-0 w-100" id="manual-fel-lines-table" style="table-layout: fixed;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:6rem;"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_QUOTATION_TH_CANT', 'Qty', 'Cant.')); ?></th>
                                <th style="width: auto;"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_ITEM_DESCRIPTION', 'Description', 'Descripción')); ?></th>
                                <th style="width:8rem;"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_UNIT_PRICE', 'Unit price', 'Precio unit.')); ?></th>
                                <th style="width:8rem;" class="text-end"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_LINE_TOTAL', 'Subtotal', 'Subtotal')); ?></th>
                                <th style="width:3rem;"></th>
                            </tr>
                        </thead>
                        <tbody id="manual-fel-lines-tbody">
                            <?php if ($manualFelLinePresets === []) :
                                $manualFelLinePresets = [['descripcion' => '', 'cantidad' => 1.0, 'precio_unitario' => 0.0]];
                            endif;
                            foreach ($manualFelLinePresets as $idx => $preset) :
                                $pq = (float) ($preset['cantidad'] ?? 1);
                                $pu = (float) ($preset['precio_unitario'] ?? 0);
                                $ps = round($pq * $pu, 2);
                                ?>
                            <tr class="manual-fel-line-row">
                                <td><input type="number" class="form-control form-control-sm manual-fel-qty" step="0.001" min="0.001" value="<?php echo htmlspecialchars((string) $pq, ENT_QUOTES, 'UTF-8'); ?>" /></td>
                                <td class="p-1"><input type="text" class="form-control form-control-sm manual-fel-desc w-100" style="width: 100%; min-width: 0;" value="<?php echo htmlspecialchars((string) ($preset['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></td>
                                <td><input type="number" class="form-control form-control-sm manual-fel-unit" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $pu, ENT_QUOTES, 'UTF-8'); ?>" /></td>
                                <td class="text-end text-nowrap manual-fel-subtotal"><?php echo number_format($ps, 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger manual-fel-remove-line py-0 px-1" title="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_REMOVE_LINE', 'Remove line', 'Quitar línea')); ?>">&times;</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($manualFelOrdensForClient !== []) : ?>
                <h6 class="text-uppercase text-muted small mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_ORDENES_HEADING', 'Work orders (same client)', 'Órdenes de trabajo (mismo cliente)')); ?></h6>
                <p class="small text-muted mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_ORDENES_HELP', 'Select orders to link to this invoice for tracking.', 'Seleccione las órdenes a vincular a esta factura para seguimiento.')); ?></p>
                <div class="border rounded p-2 mb-2" style="max-height: 220px; overflow-y: auto;">
                    <?php foreach ($manualFelOrdensForClient as $ordenRow) :
                        $oid = (int) ($ordenRow['id'] ?? 0);
                        $olab = (string) ($ordenRow['label'] ?? '');
                        $oval = (float) ($ordenRow['valor'] ?? 0);
                        if ($oid < 1) {
                            continue;
                        }
                        ?>
                    <div class="form-check">
                        <input class="form-check-input manual-fel-orden-cb" type="checkbox" value="<?php echo $oid; ?>" id="manual-fel-orden-<?php echo $oid; ?>" />
                        <label class="form-check-label small" for="manual-fel-orden-<?php echo $oid; ?>">
                            <?php echo htmlspecialchars($olab, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="text-muted">— Q <?php echo number_format($oval, 2); ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p class="small text-muted"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_ORDENES_EMPTY', 'No work orders found for this client.', 'No se encontraron órdenes de trabajo para este cliente.')); ?></p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(Text::_('JCANCEL')); ?></button>
                <button type="button" class="btn btn-success" id="manual-fel-generar-btn">
                    <i class="fas fa-file-invoice" aria-hidden="true"></i> <?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_GENERAR_BTN', 'Generate invoice', 'Generar factura')); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var openBtn = document.getElementById('manual-fel-open-btn');
    var modalEl = document.getElementById('manual-fel-invoice-modal');
    var tokenForm = document.getElementById('manual-fel-token-form');
    var generarBtn = document.getElementById('manual-fel-generar-btn');
    var alertEl = document.getElementById('manual-fel-modal-alert');
    var tbody = document.getElementById('manual-fel-lines-tbody');
    var addLineBtn = document.getElementById('manual-fel-add-line-btn');
    var issueUrl = <?php echo json_encode($manualFelIssueUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var verifyCuiUrl = <?php echo json_encode($digifactVerifyCuiUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var qid = <?php echo (int) ($quotationId ?? 0); ?>;
    var needsCfCui = <?php echo !empty($manualFelBillingIsCf) ? 'true' : 'false'; ?>;
    var cuiInput = document.getElementById('manual-fel-cui-input');
    var cuiValidateBtn = document.getElementById('manual-fel-cui-validate-btn');
    var cuiMsg = document.getElementById('manual-fel-cui-msg');
    var cuiValidated = false;
    var nameInput = document.getElementById('manual-fel-buyer-name');
    var nitInput = document.getElementById('manual-fel-buyer-nit');
    var addrInput = document.getElementById('manual-fel-buyer-address');
    var msgNet = <?php echo json_encode($l('COM_ORDENPRODUCCION_INSTRUCCIONES_MODAL_NETWORK_ERROR', 'Network error. Try again.', 'Error de red. Intente de nuevo.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgBuyerRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_REQUIRED', 'Client name and NIT are required.', 'Nombre del cliente y NIT son obligatorios.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgLinesRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_LINES_REQUIRED', 'Add at least one valid line.', 'Agregue al menos una línea válida.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgCuiRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_DIGIFACT_DIRECT_CUI_REQUIRED', 'Enter and validate the buyer CUI before issuing (consumidor final).', 'Ingrese y valide el CUI del comprador antes de timbrar (consumidor final).'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgCuiNotValidated = <?php echo json_encode($l('COM_ORDENPRODUCCION_DIGIFACT_DIRECT_CUI_NOT_VALIDATED', 'Click «Validate» and wait for Digifact to confirm the CUI before generating.', 'Pulse «Validar» y espere la confirmación de Digifact antes de generar.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    if (!openBtn || !modalEl || !generarBtn || !tbody) return;

    function showModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    function hideAlert() {
        if (alertEl) {
            alertEl.classList.add('d-none');
            alertEl.textContent = '';
        }
    }
    function showAlert(msg) {
        if (alertEl) {
            alertEl.classList.remove('d-none');
            alertEl.textContent = String(msg || '');
        }
    }
    function parseNum(v) {
        var n = parseFloat(String(v).replace(',', '.'));
        return isFinite(n) ? n : 0;
    }
    function updateRowSubtotal(tr) {
        var qty = parseNum(tr.querySelector('.manual-fel-qty') && tr.querySelector('.manual-fel-qty').value);
        var unit = parseNum(tr.querySelector('.manual-fel-unit') && tr.querySelector('.manual-fel-unit').value);
        var cell = tr.querySelector('.manual-fel-subtotal');
        if (cell) {
            cell.textContent = (qty * unit).toFixed(2);
        }
    }
    function bindRow(tr) {
        ['manual-fel-qty', 'manual-fel-unit'].forEach(function(cls) {
            var inp = tr.querySelector('.' + cls);
            if (inp) {
                inp.addEventListener('input', function() { updateRowSubtotal(tr); });
            }
        });
        var rm = tr.querySelector('.manual-fel-remove-line');
        if (rm) {
            rm.addEventListener('click', function() {
                if (tbody.querySelectorAll('.manual-fel-line-row').length <= 1) {
                    return;
                }
                tr.remove();
            });
        }
    }
    tbody.querySelectorAll('.manual-fel-line-row').forEach(bindRow);

    function addEmptyRow() {
        var tr = document.createElement('tr');
        tr.className = 'manual-fel-line-row';
        tr.innerHTML = '<td><input type="number" class="form-control form-control-sm manual-fel-qty" step="0.001" min="0.001" value="1" /></td>'
            + '<td class="p-1"><input type="text" class="form-control form-control-sm manual-fel-desc w-100" style="width: 100%; min-width: 0;" value="" /></td>'
            + '<td><input type="number" class="form-control form-control-sm manual-fel-unit" step="0.01" min="0" value="0" /></td>'
            + '<td class="text-end text-nowrap manual-fel-subtotal">0.00</td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger manual-fel-remove-line py-0 px-1">&times;</button></td>';
        tbody.appendChild(tr);
        bindRow(tr);
    }
    if (addLineBtn) {
        addLineBtn.addEventListener('click', addEmptyRow);
    }

    function resetCuiGate() {
        cuiValidated = false;
        if (cuiMsg) {
            cuiMsg.textContent = '';
            cuiMsg.className = 'small mt-2';
        }
        if (needsCfCui) {
            generarBtn.disabled = true;
        }
    }

    openBtn.addEventListener('click', function() {
        hideAlert();
        if (nameInput && nameInput.getAttribute('data-initial') !== null) {
            nameInput.value = nameInput.getAttribute('data-initial') || '';
        }
        if (nitInput && nitInput.getAttribute('data-initial') !== null) {
            nitInput.value = nitInput.getAttribute('data-initial') || '';
        }
        if (addrInput && !addrInput.value) {
            addrInput.value = 'Ciudad';
        }
        if (needsCfCui && cuiInput) {
            cuiInput.value = '';
        }
        modalEl.querySelectorAll('.manual-fel-orden-cb').forEach(function(cb) {
            cb.checked = false;
        });
        resetCuiGate();
        if (!needsCfCui) {
            generarBtn.disabled = false;
        }
        showModal();
    });

    if (cuiValidateBtn && tokenForm && needsCfCui) {
        cuiValidateBtn.addEventListener('click', function() {
            var raw = cuiInput ? String(cuiInput.value || '').replace(/\D/g, '') : '';
            if (!raw) {
                if (cuiMsg) {
                    cuiMsg.textContent = msgCuiRequired;
                    cuiMsg.className = 'small mt-2 text-danger';
                }
                return;
            }
            var fd = new FormData(tokenForm);
            fd.append('cui', raw);
            cuiValidateBtn.disabled = true;
            fetch(verifyCuiUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    cuiValidateBtn.disabled = false;
                    if (j && j.success) {
                        cuiValidated = true;
                        generarBtn.disabled = false;
                        if (nameInput && j.data && j.data.name) {
                            nameInput.value = String(j.data.name).trim();
                        }
                        if (cuiMsg) {
                            cuiMsg.textContent = (j.message || 'OK');
                            cuiMsg.className = 'small mt-2 text-success';
                        }
                    } else {
                        cuiValidated = false;
                        generarBtn.disabled = true;
                        if (cuiMsg) {
                            cuiMsg.textContent = (j && j.message) ? j.message : 'Error';
                            cuiMsg.className = 'small mt-2 text-danger';
                        }
                    }
                })
                .catch(function() {
                    cuiValidateBtn.disabled = false;
                    cuiValidated = false;
                    generarBtn.disabled = true;
                    if (cuiMsg) {
                        cuiMsg.textContent = msgNet;
                        cuiMsg.className = 'small mt-2 text-danger';
                    }
                });
        });
        if (cuiInput) {
            cuiInput.addEventListener('input', function() {
                if (cuiValidated) {
                    resetCuiGate();
                }
            });
        }
        generarBtn.disabled = true;
    }

    function collectLines() {
        var rows = [];
        tbody.querySelectorAll('.manual-fel-line-row').forEach(function(tr) {
            var desc = tr.querySelector('.manual-fel-desc');
            var qty = tr.querySelector('.manual-fel-qty');
            var unit = tr.querySelector('.manual-fel-unit');
            var d = desc ? String(desc.value || '').trim() : '';
            var q = qty ? parseNum(qty.value) : 0;
            var u = unit ? parseNum(unit.value) : 0;
            if (d === '' || q < 0.001) {
                return;
            }
            rows.push({ descripcion: d, cantidad: q, precio_unitario: u });
        });
        return rows;
    }
    function collectOrdenIds() {
        var ids = [];
        modalEl.querySelectorAll('.manual-fel-orden-cb:checked').forEach(function(cb) {
            var v = parseInt(cb.value, 10);
            if (v > 0) {
                ids.push(v);
            }
        });
        return ids;
    }

    generarBtn.addEventListener('click', function() {
        hideAlert();
        var buyerName = nameInput ? String(nameInput.value || '').trim() : '';
        var buyerNit = nitInput ? String(nitInput.value || '').trim() : '';
        if (!buyerName || !buyerNit) {
            showAlert(msgBuyerRequired);
            return;
        }
        var lines = collectLines();
        if (!lines.length) {
            showAlert(msgLinesRequired);
            return;
        }
        if (needsCfCui && !cuiValidated) {
            showAlert(msgCuiNotValidated);
            return;
        }
        if (!tokenForm) {
            return;
        }
        var fd = new FormData(tokenForm);
        fd.append('quotation_id', String(qid));
        fd.append('manual_buyer_name', buyerName);
        fd.append('manual_buyer_nit', buyerNit);
        fd.append('manual_buyer_address', addrInput ? String(addrInput.value || 'Ciudad').trim() : 'Ciudad');
        fd.append('manual_lines_json', JSON.stringify(lines));
        fd.append('manual_orden_ids_json', JSON.stringify(collectOrdenIds()));
        if (needsCfCui && cuiInput) {
            fd.append('digifact_buyer_cui', String(cuiInput.value || '').replace(/\D/g, ''));
        }
        generarBtn.disabled = true;
        openBtn.disabled = true;
        fetch(issueUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text().then(function(t) { try { return { ok: r.ok, data: JSON.parse(t) }; } catch (e) { return { ok: false, data: null, text: t }; } }); })
            .then(function(res) {
                generarBtn.disabled = false;
                openBtn.disabled = false;
                var j = res.data;
                if (!j) {
                    showAlert(msgNet);
                    return;
                }
                if (!j.success) {
                    showAlert(j.message || 'Error');
                    return;
                }
                if (j.invoice_url) {
                    window.location.href = j.invoice_url;
                } else {
                    window.location.reload();
                }
            })
            .catch(function() {
                generarBtn.disabled = false;
                openBtn.disabled = false;
                showAlert(msgNet);
            });
    });
})();
</script>
