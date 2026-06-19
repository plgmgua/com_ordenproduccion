<?php
/**
 * Manual FEL invoice modal (cotización display).
 *
 * @var callable $l
 * @var int $quotationId
 * @var string $manualFelIssueUrl
 * @var string $manualFelPreviewUrl
 * @var string $manualFelQuotationRef
 * @var string $digifactVerifyCuiUrl
 * @var bool $manualFelBillingIsCf
 * @var string $manualBuyerNameInitial
 * @var string $manualBuyerNitInitial
 * @var array<int, array{descripcion: string, cantidad: float, precio_unitario: float}> $manualFelLinePresets
 * @var array<int, array{id: int, label: string, valor: float}> $manualFelOrdensForClient
 * @var array<int, array{id: int, label: string, total: float, quote_date: string}> $manualFelOtherQuotations
 * @var string $manualFelLinesUrl
 * @var string $manualFelExchangeRateUrl
 * @var string $manualFelIssueDateDefault
 * @var array<string, mixed>|null $manualFelSeedFromInvoice
 * @var int $manualFelSourceInvoiceId
 * @var bool $manualFelInvoiceDuplicateMode
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$manualFelLinePresets = is_array($manualFelLinePresets ?? null) ? $manualFelLinePresets : [];
$manualFelOrdensForClient = is_array($manualFelOrdensForClient ?? null) ? $manualFelOrdensForClient : [];
$manualFelOtherQuotations = is_array($manualFelOtherQuotations ?? null) ? $manualFelOtherQuotations : [];
$manualFelSourceInvoiceId = (int) ($manualFelSourceInvoiceId ?? 0);
$manualFelInvoiceDuplicateMode = !empty($manualFelInvoiceDuplicateMode);
$manualFelIssueDateDefault = trim((string) ($manualFelIssueDateDefault ?? ''));
if ($manualFelIssueDateDefault === '') {
    $manualFelIssueDateDefault = date('Y-m-d');
}
$manualFelQuotationRef = trim((string) ($manualFelQuotationRef ?? ''));
if ($manualFelQuotationRef === '') {
    $manualFelQuotationRef = 'COT-' . (int) ($quotationId ?? 0);
}
$manualFelSeedFromInvoice = (isset($manualFelSeedFromInvoice) && \is_array($manualFelSeedFromInvoice)) ? $manualFelSeedFromInvoice : null;
$manualFelBuyerAddressInitial = 'Ciudad';
if ($manualFelSeedFromInvoice !== null && trim((string) ($manualFelSeedFromInvoice['buyer_address'] ?? '')) !== '') {
    $manualFelBuyerAddressInitial = trim((string) $manualFelSeedFromInvoice['buyer_address']);
}
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
                        <input type="text" class="form-control form-control-sm" id="manual-fel-buyer-address" maxlength="255" value="<?php echo htmlspecialchars($manualFelBuyerAddressInitial, ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="manual-fel-issue-date"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_ISSUE_DATE', 'Issue date', 'Fecha de emisión')); ?></label>
                        <input type="date" class="form-control form-control-sm" id="manual-fel-issue-date" value="<?php echo htmlspecialchars($manualFelIssueDateDefault, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($manualFelIssueDateDefault, ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="manual-fel-doc-type"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_DOC_TYPE', 'Document type', 'Tipo de documento')); ?></label>
                        <select class="form-select form-select-sm" id="manual-fel-doc-type">
                            <option value="FACT"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_DOC_TYPE_FACT', 'FACT — Invoice', 'FACT — Factura')); ?></option>
                            <option value="FCAM"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_DOC_TYPE_FCAM', 'FCAM — Exchange invoice', 'FCAM — Factura cambiaria')); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1" for="manual-fel-currency"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_CURRENCY', 'Currency', 'Moneda')); ?></label>
                        <select class="form-select form-select-sm" id="manual-fel-currency">
                            <option value="GTQ"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_CURRENCY_GTQ', 'GTQ — Quetzales', 'GTQ — Quetzales')); ?></option>
                            <option value="USD"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_CURRENCY_USD', 'USD — US dollars', 'USD — Dólares')); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3 d-none" id="manual-fel-exchange-wrap">
                        <label class="form-label small mb-1" for="manual-fel-exchange-rate"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_EXCHANGE_RATE', 'Exchange rate (BANGUAT)', 'Tipo de cambio (BANGUAT)')); ?></label>
                        <input type="text" class="form-control form-control-sm text-end" id="manual-fel-exchange-rate" readonly />
                        <div id="manual-fel-exchange-msg" class="form-text small text-muted"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="manual-fel-observaciones"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_OBSERVACIONES', 'Observations', 'Observaciones')); ?></label>
                        <textarea class="form-control form-control-sm" id="manual-fel-observaciones" rows="2" maxlength="500"><?php echo htmlspecialchars($manualFelQuotationRef, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div class="form-text small"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_OBSERVACIONES_HELP', 'Shown on the invoice PDF and sent to Digifact as OBSERVACIONES. Default is the quotation reference.', 'Se muestra en el PDF y se envía a Digifact como OBSERVACIONES. Por defecto es la referencia de cotización.')); ?></div>
                    </div>
                </div>
                <div id="manual-fel-fcam-panel" class="border rounded p-3 mb-3 bg-light d-none">
                    <h6 class="text-uppercase text-muted small mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_HEADING', 'Exchange invoice — payment schedule', 'Factura cambiaria — abonos')); ?></h6>
                    <p class="small text-muted mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_HELP', 'Enter the due date and amount for the payment (amount must equal invoice total).', 'Ingrese la fecha de vencimiento y el monto del abono (debe coincidir con el total de la factura).')); ?></p>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="manual-fel-fcam-due-date"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_DUE_DATE', 'Due date', 'Fecha de vencimiento')); ?></label>
                            <input type="date" class="form-control form-control-sm" id="manual-fel-fcam-due-date" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="manual-fel-fcam-amount"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_AMOUNT', 'Payment amount', 'Monto del abono')); ?></label>
                            <input type="number" class="form-control form-control-sm text-end" id="manual-fel-fcam-amount" step="0.01" min="0" readonly />
                        </div>
                    </div>
                </div>
                <?php if ($manualFelOtherQuotations !== [] && !$manualFelInvoiceDuplicateMode) : ?>
                <div class="border rounded p-3 mb-3 bg-light">
                    <h6 class="text-uppercase text-muted small mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_OTHER_QUOTATIONS_HEADING', 'Additional quotations (same client)', 'Cotizaciones adicionales (mismo cliente)')); ?></h6>
                    <p class="small text-muted mb-2"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_OTHER_QUOTATIONS_HELP', 'Select other quotations to merge their lines into this invoice.', 'Seleccione otras cotizaciones para combinar sus líneas en esta factura.')); ?></p>
                    <div class="row g-2">
                        <?php foreach ($manualFelOtherQuotations as $otherQuot) :
                            $oqid = (int) ($otherQuot['id'] ?? 0);
                            $olab = (string) ($otherQuot['label'] ?? '');
                            $otot = (float) ($otherQuot['total'] ?? 0);
                            $odate = (string) ($otherQuot['quote_date'] ?? '');
                            if ($oqid < 1) {
                                continue;
                            }
                            ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input manual-fel-quotation-cb" type="checkbox" value="<?php echo $oqid; ?>" id="manual-fel-quotation-<?php echo $oqid; ?>" data-quotation-label="<?php echo htmlspecialchars($olab, ENT_QUOTES, 'UTF-8'); ?>" />
                                <label class="form-check-label small" for="manual-fel-quotation-<?php echo $oqid; ?>">
                                    <?php echo htmlspecialchars($olab, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($odate !== '') : ?>
                                        <span class="text-muted">— <?php echo htmlspecialchars($odate); ?></span>
                                    <?php endif; ?>
                                    <span class="text-muted">— Q <?php echo number_format($otot, 2); ?></span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
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
                                $presetQid = (int) ($preset['quotation_id'] ?? ($quotationId ?? 0));
                                ?>
                            <tr class="manual-fel-line-row" data-quotation-id="<?php echo $presetQid; ?>">
                                <td><input type="number" class="form-control form-control-sm manual-fel-qty" step="0.001" min="0.001" value="<?php echo htmlspecialchars((string) $pq, ENT_QUOTES, 'UTF-8'); ?>" /></td>
                                <td class="p-1"><input type="text" class="form-control form-control-sm manual-fel-desc w-100" style="width: 100%; min-width: 0;" value="<?php echo htmlspecialchars((string) ($preset['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" /></td>
                                <td><input type="number" class="form-control form-control-sm manual-fel-unit text-end" step="0.0001" min="0" value="<?php echo htmlspecialchars((string) $pu, ENT_QUOTES, 'UTF-8'); ?>" /></td>
                                <td><input type="number" class="form-control form-control-sm manual-fel-subtotal text-end" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format($ps, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_SUBTOTAL_HINT', 'Edit subtotal; unit price is calculated from quantity.', 'Edite el subtotal; el precio unitario se calcula según la cantidad.'), ENT_QUOTES, 'UTF-8'); ?>" /></td>
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
                <button type="button" class="btn btn-outline-primary" id="manual-fel-preview-btn">
                    <i class="fas fa-eye" aria-hidden="true"></i> <?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_PREVIEW_BTN', 'Preview', 'Vista previa')); ?>
                </button>
                <button type="button" class="btn btn-success" id="manual-fel-generar-btn">
                    <i class="fas fa-file-invoice" aria-hidden="true"></i> <?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_GENERAR_BTN', 'Generate invoice', 'Generar factura')); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="manual-fel-preview-modal" tabindex="-1" aria-labelledby="manualFelPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualFelPreviewModalLabel"><?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_PREVIEW_TITLE', 'Invoice preview', 'Vista previa de factura')); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE')); ?>"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="manual-fel-preview-iframe" title="<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_MANUAL_FEL_PREVIEW_TITLE', 'Invoice preview', 'Vista previa de factura')); ?>" style="width:100%; height:75vh; border:0;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo htmlspecialchars(Text::_('JCLOSE')); ?></button>
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
    var previewBtn = document.getElementById('manual-fel-preview-btn');
    var previewModalEl = document.getElementById('manual-fel-preview-modal');
    var previewIframe = document.getElementById('manual-fel-preview-iframe');
    var alertEl = document.getElementById('manual-fel-modal-alert');
    var tbody = document.getElementById('manual-fel-lines-tbody');
    var addLineBtn = document.getElementById('manual-fel-add-line-btn');
    var issueUrl = <?php echo json_encode($manualFelIssueUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var previewUrl = <?php echo json_encode($manualFelPreviewUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var linesUrl = <?php echo json_encode($manualFelLinesUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var exchangeRateUrl = <?php echo json_encode($manualFelExchangeRateUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var verifyCuiUrl = <?php echo json_encode($digifactVerifyCuiUrl ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var qid = <?php echo (int) ($quotationId ?? 0); ?>;
    var issueDateInput = document.getElementById('manual-fel-issue-date');
    var issueDateDefault = <?php echo json_encode($manualFelIssueDateDefault, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgIssueDateInvalid = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_ISSUE_DATE_INVALID', 'Invalid issue date (cannot be in the future).', 'Fecha de emisión inválida (no puede ser futura).'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgQuotLinesLoadFailed = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_QUOTATION_LINES_LOAD_FAILED', 'Could not load quotation lines.', 'No se pudieron cargar las líneas de la cotización.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var needsCfCui = <?php echo !empty($manualFelBillingIsCf) ? 'true' : 'false'; ?>;
    var cuiInput = document.getElementById('manual-fel-cui-input');
    var cuiValidateBtn = document.getElementById('manual-fel-cui-validate-btn');
    var cuiMsg = document.getElementById('manual-fel-cui-msg');
    var cuiValidated = false;
    var nameInput = document.getElementById('manual-fel-buyer-name');
    var nitInput = document.getElementById('manual-fel-buyer-nit');
    var addrInput = document.getElementById('manual-fel-buyer-address');
    var docTypeSelect = document.getElementById('manual-fel-doc-type');
    var currencySelect = document.getElementById('manual-fel-currency');
    var exchangeWrap = document.getElementById('manual-fel-exchange-wrap');
    var exchangeRateInput = document.getElementById('manual-fel-exchange-rate');
    var exchangeMsg = document.getElementById('manual-fel-exchange-msg');
    var observacionesInput = document.getElementById('manual-fel-observaciones');
    var fcamPanel = document.getElementById('manual-fel-fcam-panel');
    var fcamDueDate = document.getElementById('manual-fel-fcam-due-date');
    var fcamAmount = document.getElementById('manual-fel-fcam-amount');
    var quotationRefDefault = <?php echo json_encode($manualFelQuotationRef, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var manualFelSeed = <?php echo json_encode($manualFelSeedFromInvoice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var sourceInvoiceId = <?php echo (int) $manualFelSourceInvoiceId; ?>;
    var invoiceDuplicateMode = <?php echo $manualFelInvoiceDuplicateMode ? 'true' : 'false'; ?>;
    var msgNet = <?php echo json_encode($l('COM_ORDENPRODUCCION_INSTRUCCIONES_MODAL_NETWORK_ERROR', 'Network error. Try again.', 'Error de red. Intente de nuevo.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgBuyerRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_REQUIRED', 'Client name and NIT are required.', 'Nombre del cliente y NIT son obligatorios.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgLinesRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_LINES_REQUIRED', 'Add at least one valid line.', 'Agregue al menos una línea válida.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgCuiRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_DIGIFACT_DIRECT_CUI_REQUIRED', 'Enter and validate the buyer CUI before issuing (consumidor final).', 'Ingrese y valide el CUI del comprador antes de timbrar (consumidor final).'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgCuiNotValidated = <?php echo json_encode($l('COM_ORDENPRODUCCION_DIGIFACT_DIRECT_CUI_NOT_VALIDATED', 'Click «Validate» and wait for Digifact to confirm the CUI before generating.', 'Pulse «Validar» y espere la confirmación de Digifact antes de generar.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgFcamDateRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_DATE_REQUIRED', 'Due date is required for FCAM.', 'La fecha de vencimiento es obligatoria para FCAM.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgPreviewFailed = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_PREVIEW_FAILED', 'Could not build preview.', 'No se pudo generar la vista previa.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgExchangeRateRequired = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_EXCHANGE_RATE_REQUIRED', 'USD invoices require the BANGUAT exchange rate for the issue date.', 'Las facturas en USD requieren el tipo de cambio BANGUAT de la fecha de emisión.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var msgExchangeRateLoading = <?php echo json_encode($l('COM_ORDENPRODUCCION_MANUAL_FEL_EXCHANGE_RATE_LOADING', 'Loading exchange rate…', 'Cargando tipo de cambio…'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var exchangeRateLoaded = false;
    var exchangeRateLoading = false;
    if (!modalEl || !generarBtn || !tbody) return;

    function showModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    function showPreviewModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal && previewModalEl) {
            bootstrap.Modal.getOrCreateInstance(previewModalEl).show();
        }
    }
    function sumLineTotals() {
        var total = 0;
        tbody.querySelectorAll('.manual-fel-line-row').forEach(function(tr) {
            var sub = tr.querySelector('.manual-fel-subtotal');
            total += sub ? parseNum(sub.value) : 0;
        });
        return round2(total);
    }
    function syncFcamAmountFromLines() {
        if (!fcamAmount) return;
        fcamAmount.value = sumLineTotals().toFixed(2);
    }
    function toggleFcamPanel() {
        var isFcam = docTypeSelect && String(docTypeSelect.value || 'FACT').toUpperCase() === 'FCAM';
        if (fcamPanel) {
            fcamPanel.classList.toggle('d-none', !isFcam);
        }
        syncFcamAmountFromLines();
    }
    if (docTypeSelect) {
        docTypeSelect.addEventListener('change', toggleFcamPanel);
    }
    function isUsdCurrency() {
        return currencySelect && String(currencySelect.value || 'GTQ').toUpperCase() === 'USD';
    }
    function resetExchangeRateState() {
        exchangeRateLoaded = false;
        if (exchangeRateInput) {
            exchangeRateInput.value = '';
        }
        if (exchangeMsg) {
            exchangeMsg.textContent = '';
            exchangeMsg.className = 'form-text small text-muted';
        }
    }
    function toggleCurrencyPanel() {
        var usd = isUsdCurrency();
        if (exchangeWrap) {
            exchangeWrap.classList.toggle('d-none', !usd);
        }
        if (!usd) {
            resetExchangeRateState();
        } else {
            fetchExchangeRate();
        }
    }
    function fetchExchangeRate() {
        if (!isUsdCurrency() || !tokenForm || !exchangeRateUrl || !issueDateInput) {
            return;
        }
        if (!validateIssueDateSilent()) {
            resetExchangeRateState();
            return;
        }
        exchangeRateLoading = true;
        exchangeRateLoaded = false;
        if (exchangeRateInput) {
            exchangeRateInput.value = '';
        }
        if (exchangeMsg) {
            exchangeMsg.textContent = msgExchangeRateLoading;
            exchangeMsg.className = 'form-text small text-muted';
        }
        var fd = new FormData(tokenForm);
        fd.append('manual_issue_date', String(issueDateInput.value || issueDateDefault));
        fetch(exchangeRateUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                exchangeRateLoading = false;
                if (j && j.success && j.exchange_rate != null) {
                    exchangeRateLoaded = true;
                    if (exchangeRateInput) {
                        exchangeRateInput.value = Number(j.exchange_rate).toFixed(5);
                    }
                    if (exchangeMsg) {
                        exchangeMsg.textContent = '';
                        exchangeMsg.className = 'form-text small text-muted';
                    }
                } else {
                    exchangeRateLoaded = false;
                    if (exchangeMsg) {
                        exchangeMsg.textContent = (j && j.message) ? j.message : msgExchangeRateRequired;
                        exchangeMsg.className = 'form-text small text-danger';
                    }
                }
            })
            .catch(function() {
                exchangeRateLoading = false;
                exchangeRateLoaded = false;
                if (exchangeMsg) {
                    exchangeMsg.textContent = msgNet;
                    exchangeMsg.className = 'form-text small text-danger';
                }
            });
    }
    function validateIssueDateSilent() {
        if (!issueDateInput) {
            return true;
        }
        var val = String(issueDateInput.value || '').trim();
        return !!(val && val <= issueDateDefault);
    }
    if (currencySelect) {
        currencySelect.addEventListener('change', toggleCurrencyPanel);
    }
    if (issueDateInput) {
        issueDateInput.addEventListener('change', function() {
            if (isUsdCurrency()) {
                fetchExchangeRate();
            }
        });
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
    function round2(n) {
        return Math.round(n * 100) / 100;
    }
    function round4(n) {
        return Math.round(n * 10000) / 10000;
    }
    /** qty × unit → subtotal */
    function updateRowSubtotalFromUnit(tr) {
        var qtyInp = tr.querySelector('.manual-fel-qty');
        var unitInp = tr.querySelector('.manual-fel-unit');
        var subInp = tr.querySelector('.manual-fel-subtotal');
        if (!qtyInp || !unitInp || !subInp) {
            return;
        }
        var qty = parseNum(qtyInp.value);
        var unit = parseNum(unitInp.value);
        subInp.value = round2(qty * unit).toFixed(2);
        syncFcamAmountFromLines();
    }
    /** subtotal ÷ qty → unit (keeps subtotal when quantity changes) */
    function updateRowUnitFromSubtotal(tr) {
        var qtyInp = tr.querySelector('.manual-fel-qty');
        var unitInp = tr.querySelector('.manual-fel-unit');
        var subInp = tr.querySelector('.manual-fel-subtotal');
        if (!qtyInp || !unitInp || !subInp) {
            return;
        }
        var qty = parseNum(qtyInp.value);
        var sub = parseNum(subInp.value);
        if (qty < 0.001) {
            return;
        }
        unitInp.value = round4(sub / qty).toFixed(4);
        syncFcamAmountFromLines();
    }
    function bindRow(tr) {
        var qtyInp = tr.querySelector('.manual-fel-qty');
        var unitInp = tr.querySelector('.manual-fel-unit');
        var subInp = tr.querySelector('.manual-fel-subtotal');
        if (unitInp) {
            unitInp.addEventListener('input', function() { updateRowSubtotalFromUnit(tr); });
        }
        if (subInp) {
            subInp.addEventListener('input', function() { updateRowUnitFromSubtotal(tr); });
        }
        if (qtyInp) {
            qtyInp.addEventListener('input', function() { updateRowUnitFromSubtotal(tr); });
        }
        var rm = tr.querySelector('.manual-fel-remove-line');
        if (rm) {
            rm.addEventListener('click', function() {
                if (tbody.querySelectorAll('.manual-fel-line-row').length <= 1) {
                    return;
                }
                tr.remove();
                syncFcamAmountFromLines();
            });
        }
    }
    tbody.querySelectorAll('.manual-fel-line-row').forEach(bindRow);
    toggleFcamPanel();

    function applyManualFelSeed(seed) {
        if (!seed || typeof seed !== 'object') {
            return;
        }
        if (nameInput && seed.buyer_name) {
            nameInput.value = String(seed.buyer_name);
        }
        if (nitInput && seed.buyer_nit) {
            nitInput.value = String(seed.buyer_nit);
        }
        if (addrInput && seed.buyer_address) {
            addrInput.value = String(seed.buyer_address);
        }
        if (issueDateInput && seed.issue_date) {
            issueDateInput.value = String(seed.issue_date);
        }
        if (docTypeSelect && seed.doc_type) {
            docTypeSelect.value = String(seed.doc_type).toUpperCase() === 'FCAM' ? 'FCAM' : 'FACT';
        }
        if (currencySelect && seed.currency) {
            currencySelect.value = String(seed.currency).toUpperCase() === 'USD' ? 'USD' : 'GTQ';
        }
        if (observacionesInput && seed.observaciones != null) {
            observacionesInput.value = String(seed.observaciones);
        }
        if (Array.isArray(seed.lines) && seed.lines.length) {
            tbody.innerHTML = '';
            seed.lines.forEach(function(line) {
                addLineFromPreset(line, line.quotation_id || qid);
            });
        }
        toggleFcamPanel();
        if (docTypeSelect && String(docTypeSelect.value || 'FACT').toUpperCase() === 'FCAM' && Array.isArray(seed.fcam_abonos) && seed.fcam_abonos.length) {
            var ab = seed.fcam_abonos[0];
            if (fcamDueDate && ab.fecha) {
                fcamDueDate.value = String(ab.fecha);
            }
            syncFcamAmountFromLines();
        }
        if (Array.isArray(seed.orden_ids) && seed.orden_ids.length) {
            modalEl.querySelectorAll('.manual-fel-orden-cb').forEach(function(cb) {
                var oid = parseInt(cb.value, 10);
                cb.checked = seed.orden_ids.indexOf(oid) !== -1;
            });
        }
        if (needsCfCui && cuiInput && seed.cui_digits) {
            cuiInput.value = String(seed.cui_digits);
            resetCuiGate();
        } else if (!needsCfCui) {
            generarBtn.disabled = false;
        }
        toggleCurrencyPanel();
    }

    function addLineFromPreset(preset, quotationIdForRow) {
        var tr = document.createElement('tr');
        tr.className = 'manual-fel-line-row';
        tr.setAttribute('data-quotation-id', String(quotationIdForRow || qid));
        var pq = preset && preset.cantidad != null ? preset.cantidad : 1;
        var pu = preset && preset.precio_unitario != null ? preset.precio_unitario : 0;
        var ps = Math.round(parseFloat(pq) * parseFloat(pu) * 100) / 100;
        var desc = preset && preset.descripcion != null ? String(preset.descripcion) : '';
        tr.innerHTML = '<td><input type="number" class="form-control form-control-sm manual-fel-qty" step="0.001" min="0.001" value="' + pq + '" /></td>'
            + '<td class="p-1"><input type="text" class="form-control form-control-sm manual-fel-desc w-100" style="width: 100%; min-width: 0;" value="' + desc.replace(/"/g, '&quot;') + '" /></td>'
            + '<td><input type="number" class="form-control form-control-sm manual-fel-unit text-end" step="0.0001" min="0" value="' + pu + '" /></td>'
            + '<td><input type="number" class="form-control form-control-sm manual-fel-subtotal text-end" step="0.01" min="0" value="' + ps.toFixed(2) + '" /></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger manual-fel-remove-line py-0 px-1">&times;</button></td>';
        tbody.appendChild(tr);
        bindRow(tr);
    }

    function removeLinesForQuotation(quotationIdForRow) {
        tbody.querySelectorAll('.manual-fel-line-row[data-quotation-id="' + String(quotationIdForRow) + '"]').forEach(function(tr) {
            if (String(quotationIdForRow) !== String(qid)) {
                tr.remove();
            }
        });
    }

    function loadQuotationLines(quotationIdForRow, done) {
        if (!tokenForm || !linesUrl || quotationIdForRow < 1) {
            if (done) done(false);
            return;
        }
        var fd = new FormData(tokenForm);
        fd.append('primary_quotation_id', String(qid));
        fd.append('quotation_id', String(quotationIdForRow));
        fetch(linesUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (!j || !j.success || !Array.isArray(j.lines)) {
                    if (done) done(false, j && j.message ? j.message : msgQuotLinesLoadFailed);
                    return;
                }
                j.lines.forEach(function(line) {
                    addLineFromPreset(line, quotationIdForRow);
                });
                if (done) done(true);
            })
            .catch(function() {
                if (done) done(false, msgNet);
            });
    }

    function addEmptyRow() {
        addLineFromPreset({ descripcion: '', cantidad: 1, precio_unitario: 0 }, qid);
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

    if (openBtn) {
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
        modalEl.querySelectorAll('.manual-fel-quotation-cb').forEach(function(cb) {
            cb.checked = false;
        });
        if (issueDateInput) {
            issueDateInput.value = issueDateDefault;
            issueDateInput.max = issueDateDefault;
        }
        if (docTypeSelect) {
            docTypeSelect.value = 'FACT';
        }
        if (currencySelect) {
            currencySelect.value = 'GTQ';
        }
        resetExchangeRateState();
        toggleCurrencyPanel();
        if (observacionesInput) {
            observacionesInput.value = quotationRefDefault || '';
        }
        if (fcamDueDate) {
            fcamDueDate.value = '';
        }
        toggleFcamPanel();
        resetCuiGate();
        if (!needsCfCui) {
            generarBtn.disabled = false;
        }
        showModal();
    });
    }

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

    modalEl.querySelectorAll('.manual-fel-quotation-cb').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var targetQid = parseInt(cb.value, 10);
            if (targetQid < 1) {
                return;
            }
            if (cb.checked) {
                cb.disabled = true;
                loadQuotationLines(targetQid, function(ok, errMsg) {
                    cb.disabled = false;
                    if (!ok) {
                        cb.checked = false;
                        showAlert(errMsg || msgQuotLinesLoadFailed);
                    }
                });
            } else {
                removeLinesForQuotation(targetQid);
            }
        });
    });

    function collectQuotationIds() {
        var ids = [qid];
        modalEl.querySelectorAll('.manual-fel-quotation-cb:checked').forEach(function(cb) {
            var v = parseInt(cb.value, 10);
            if (v > 0 && ids.indexOf(v) === -1) {
                ids.push(v);
            }
        });
        return ids;
    }

    function validateIssueDate() {
        if (!issueDateInput) {
            return true;
        }
        var val = String(issueDateInput.value || '').trim();
        if (!val || val > issueDateDefault) {
            showAlert(msgIssueDateInvalid);
            return false;
        }
        return true;
    }

    function collectLines() {
        var rows = [];
        tbody.querySelectorAll('.manual-fel-line-row').forEach(function(tr) {
            var desc = tr.querySelector('.manual-fel-desc');
            var qty = tr.querySelector('.manual-fel-qty');
            var unit = tr.querySelector('.manual-fel-unit');
            var sub = tr.querySelector('.manual-fel-subtotal');
            var d = desc ? String(desc.value || '').trim() : '';
            var q = qty ? parseNum(qty.value) : 0;
            var subVal = sub ? parseNum(sub.value) : 0;
            var u = unit ? parseNum(unit.value) : 0;
            if (q >= 0.001 && subVal > 0) {
                u = round4(subVal / q);
            }
            if (d === '' || q < 0.001) {
                return;
            }
            var lineQid = parseInt(tr.getAttribute('data-quotation-id') || String(qid), 10) || qid;
            rows.push({ descripcion: d, cantidad: q, precio_unitario: u, quotation_id: lineQid });
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

    function buildFcamAbonosJson() {
        if (!docTypeSelect || String(docTypeSelect.value || 'FACT').toUpperCase() !== 'FCAM') {
            return '[]';
        }
        var due = fcamDueDate ? String(fcamDueDate.value || '').trim() : '';
        var amt = fcamAmount ? parseNum(fcamAmount.value) : sumLineTotals();
        if (!due || amt <= 0) {
            return '[]';
        }
        return JSON.stringify([{ numero: 1, fecha: due, monto: round2(amt) }]);
    }

    function buildManualFelFormData(requireCui) {
        var buyerName = nameInput ? String(nameInput.value || '').trim() : '';
        var buyerNit = nitInput ? String(nitInput.value || '').trim() : '';
        var lines = collectLines();
        if (!buyerName || !buyerNit) {
            showAlert(msgBuyerRequired);
            return null;
        }
        if (!lines.length) {
            showAlert(msgLinesRequired);
            return null;
        }
        if (requireCui && needsCfCui && !cuiValidated) {
            showAlert(msgCuiNotValidated);
            return null;
        }
        if (!validateIssueDate()) {
            return null;
        }
        if (isUsdCurrency()) {
            if (exchangeRateLoading) {
                showAlert(msgExchangeRateLoading);
                return null;
            }
            if (!exchangeRateLoaded) {
                showAlert(msgExchangeRateRequired);
                return null;
            }
        }
        if (docTypeSelect && String(docTypeSelect.value || 'FACT').toUpperCase() === 'FCAM') {
            var dueCheck = fcamDueDate ? String(fcamDueDate.value || '').trim() : '';
            if (!dueCheck) {
                showAlert(msgFcamDateRequired);
                return null;
            }
        }
        if (!tokenForm) {
            return null;
        }
        var fd = new FormData(tokenForm);
        if (sourceInvoiceId > 0) {
            fd.append('source_invoice_id', String(sourceInvoiceId));
        } else {
            fd.append('quotation_id', String(qid));
        }
        fd.append('manual_buyer_name', buyerName);
        fd.append('manual_buyer_nit', buyerNit);
        fd.append('manual_buyer_address', addrInput ? String(addrInput.value || 'Ciudad').trim() : 'Ciudad');
        fd.append('manual_lines_json', JSON.stringify(lines));
        fd.append('manual_orden_ids_json', JSON.stringify(collectOrdenIds()));
        fd.append('manual_quotation_ids_json', JSON.stringify(collectQuotationIds()));
        fd.append('manual_doc_type', docTypeSelect ? String(docTypeSelect.value || 'FACT') : 'FACT');
        fd.append('manual_currency', currencySelect ? String(currencySelect.value || 'GTQ') : 'GTQ');
        fd.append('manual_observaciones', observacionesInput ? String(observacionesInput.value || '').trim() : '');
        fd.append('manual_fcam_abonos_json', buildFcamAbonosJson());
        if (issueDateInput) {
            fd.append('manual_issue_date', String(issueDateInput.value || issueDateDefault));
        }
        if (needsCfCui && cuiInput) {
            fd.append('digifact_buyer_cui', String(cuiInput.value || '').replace(/\D/g, ''));
        }
        return fd;
    }

    if (previewBtn && previewUrl) {
        previewBtn.addEventListener('click', function() {
            hideAlert();
            var fd = buildManualFelFormData(false);
            if (!fd) return;
            previewBtn.disabled = true;
            generarBtn.disabled = true;
            if (openBtn) openBtn.disabled = true;
            fetch(previewUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text().then(function(t) { try { return { ok: r.ok, data: JSON.parse(t) }; } catch (e) { return { ok: false, data: null }; } }); })
                .then(function(res) {
                    previewBtn.disabled = false;
                    generarBtn.disabled = needsCfCui && !cuiValidated;
                    if (openBtn) openBtn.disabled = false;
                    var j = res.data;
                    if (!j || !j.success || !j.pdf_base64) {
                        showAlert((j && j.message) ? j.message : msgPreviewFailed);
                        return;
                    }
                    if (previewIframe) {
                        var blob = b64ToBlob(j.pdf_base64, 'application/pdf');
                        var url = URL.createObjectURL(blob);
                        previewIframe.src = url;
                    }
                    showPreviewModal();
                })
                .catch(function() {
                    previewBtn.disabled = false;
                    generarBtn.disabled = needsCfCui && !cuiValidated;
                    if (openBtn) openBtn.disabled = false;
                    showAlert(msgNet);
                });
        });
    }

    function b64ToBlob(b64, mime) {
        var bin = atob(b64);
        var len = bin.length;
        var bytes = new Uint8Array(len);
        for (var i = 0; i < len; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return new Blob([bytes], { type: mime || 'application/octet-stream' });
    }

    generarBtn.addEventListener('click', function() {
        hideAlert();
        var fd = buildManualFelFormData(true);
        if (!fd) return;
        generarBtn.disabled = true;
        if (openBtn) openBtn.disabled = true;
        if (previewBtn) previewBtn.disabled = true;
        fetch(issueUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text().then(function(t) { try { return { ok: r.ok, data: JSON.parse(t) }; } catch (e) { return { ok: false, data: null, text: t }; } }); })
            .then(function(res) {
                generarBtn.disabled = false;
                if (openBtn) openBtn.disabled = false;
                if (previewBtn) previewBtn.disabled = false;
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
                if (openBtn) openBtn.disabled = false;
                if (previewBtn) previewBtn.disabled = false;
                showAlert(msgNet);
            });
    });

    if (manualFelSeed && manualFelSeed.auto_open) {
        applyManualFelSeed(manualFelSeed);
        hideAlert();
        showModal();
        if (invoiceDuplicateMode && window.history && window.history.replaceState) {
            try {
                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('manual_fel_duplicate');
                window.history.replaceState({}, '', cleanUrl.toString());
            } catch (e) {}
        } else if (!invoiceDuplicateMode && window.history && window.history.replaceState) {
            try {
                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('manual_fel_seed_invoice');
                window.history.replaceState({}, '', cleanUrl.toString());
            } catch (e) {}
        }
        setTimeout(function() {
            if (modalEl && typeof modalEl.scrollIntoView === 'function') {
                modalEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 300);
    }
})();
</script>
