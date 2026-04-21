<?php
/**
 * Pre-cotización document: external vendor quotation request (lines only, no pliego modals).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Cotizador\HtmlView $this */

$item  = $this->item ?? null;
$lines = $this->lines ?? [];
if (!$item) {
    return;
}

$listUrl           = Route::_('index.php?option=com_ordenproduccion&view=cotizador');
$preCotizacionId   = (int) $item->id;
$precotizacionLocked = !empty($this->precotizacionLocked);
$canEditDocument   = isset($this->precotizacionDocumentEditable)
    ? (bool) $this->precotizacionDocumentEditable
    : !$precotizacionLocked;
$ofertaViewOnly    = !empty($item->oferta) && !$canEditDocument && !$precotizacionLocked;

$vendorLines = [];
foreach ($lines as $ln) {
    $lt = isset($ln->line_type) ? (string) $ln->line_type : 'pliego';
    if ($lt === 'proveedor_externo') {
        $vendorLines[] = $ln;
    }
}

$hasStoredTotals = isset($item->lines_subtotal) && $item->lines_subtotal !== null && $item->lines_subtotal !== '';
if ($hasStoredTotals) {
    $linesSubtotal = (float) $item->lines_subtotal;
    $margenAmount  = (float) ($item->margen_amount ?? 0);
    $ivaAmount     = (float) ($item->iva_amount ?? 0);
    $isrAmount     = (float) ($item->isr_amount ?? 0);
    $comisionAmount = (float) ($item->comision_amount ?? 0);
    $linesTotal    = (float) ($item->total ?? 0);
    $linesTotalFinal = isset($item->total_final) && $item->total_final !== null && $item->total_final !== ''
        ? (float) $item->total_final : $linesTotal;
} else {
    $linesSubtotal = 0.0;
    foreach ($lines as $l) {
        $lineType = isset($l->line_type) ? (string) $l->line_type : 'pliego';
        if ($lineType !== 'envio') {
            $linesSubtotal += (float) ($l->total ?? 0);
        }
    }
    $paramMargen   = isset($this->paramMargen) ? (float) $this->paramMargen : 0;
    $paramIva      = isset($this->paramIva) ? (float) $this->paramIva : 0;
    $paramIsr      = isset($this->paramIsr) ? (float) $this->paramIsr : 0;
    $paramComision = isset($this->paramComision) ? (float) $this->paramComision : 0;
    $margenAmount  = $linesSubtotal * ($paramMargen / 100);
    $facturarCalc  = !empty($item->facturar);
    $ivaAmount     = $facturarCalc ? ($linesSubtotal * ($paramIva / 100)) : 0;
    $isrAmount     = $facturarCalc ? ($linesSubtotal * ($paramIsr / 100)) : 0;
    $comisionAmount = $linesSubtotal * ($paramComision / 100);
    $linesTotal    = $linesSubtotal + $margenAmount + $ivaAmount + $isrAmount + $comisionAmount;
    $linesTotalFinal = $linesTotal;
}

$facturar = !empty($item->facturar);
$paramMargen = isset($this->paramMargen) ? (float) $this->paramMargen : 0;
$paramIva = isset($this->paramIva) ? (float) $this->paramIva : 0;
$paramIsr = isset($this->paramIsr) ? (float) $this->paramIsr : 0;
$paramComision = isset($this->paramComision) ? (float) $this->paramComision : 0;
$paramComisionMargenAdicional = isset($this->paramComisionMargenAdicional) ? (float) $this->paramComisionMargenAdicional : 0;
$margenAdicional = ($item && isset($item->margen_adicional) && $item->margen_adicional !== null && $item->margen_adicional !== '')
    ? (float) $item->margen_adicional : 0;
$comisionMargenAdicionalAmount = ($item && isset($item->comision_margen_adicional) && $item->comision_margen_adicional !== null && $item->comision_margen_adicional !== '')
    ? (float) $item->comision_margen_adicional : 0;
$displayTotal = $linesTotalFinal + $margenAdicional;
$canSeePrecotInternalTax = AccessHelper::canSeePrecotizacionInternalTaxBreakdown();

$labelFacturar = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FACTURAR');
if (strpos($labelFacturar, 'COM_ORDENPRODUCCION_') === 0) {
    $labelFacturar = 'Facturar';
}
$facturarChecked = !empty($item->facturar);
$saveFacturarUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.saveFacturar');
$labelDescripcion = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION');
if (strpos($labelDescripcion, 'COM_ORDENPRODUCCION_') === 0) {
    $labelDescripcion = 'Descripción';
}
$descripcionValue = isset($item->descripcion) ? (string) $item->descripcion : '';
$saveDescripcionUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.saveDescripcion');
$labelMedidas = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MEDIDAS');
if (strpos($labelMedidas, 'COM_ORDENPRODUCCION_') === 0) {
    $labelMedidas = 'Medidas';
}
$placeholderMedidas = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MEDIDAS_PLACEHOLDER');
if (strpos($placeholderMedidas, 'COM_ORDENPRODUCCION_') === 0) {
    $placeholderMedidas = 'ingrese medidas en pulgadas';
}
$medidasValue = isset($item->medidas) ? (string) $item->medidas : '';

$saveVendorLinesUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.saveProveedorExternoLines');
$badgeVendor        = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_BADGE');
if (strpos($badgeVendor, 'COM_ORDENPRODUCCION_') === 0) {
    $badgeVendor = 'Proveedor externo';
}
$tfootLabelSpan = 5;
?>

<div class="com-ordenproduccion-precotizacion-document com-ordenproduccion-precotizacion-proveedor-externo container py-4">
    <nav class="mb-3">
        <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BACK'); ?></a>
    </nav>

    <?php if ($canEditDocument) : ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="page-title mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE'); ?> <?php echo htmlspecialchars($item->number); ?>
            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($badgeVendor); ?></span>
        </h1>
        <button type="submit" form="precotizacion-desc-medidas-form" class="btn btn-secondary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_DOCUMENT_SAVE_BTN'); ?></button>
    </div>
    <?php else : ?>
    <h1 class="page-title">
        <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE'); ?> <?php echo htmlspecialchars($item->number); ?>
        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($badgeVendor); ?></span>
    </h1>
    <?php endif; ?>

    <?php
    $associatedQuotations = $this->associatedQuotations ?? [];
    if (!empty($associatedQuotations)) :
        $labelAssociated = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ASSOCIATED_QUOTATION');
        if (strpos($labelAssociated, 'COM_ORDENPRODUCCION_') === 0) {
            $labelAssociated = 'Associated quotation(s)';
        }
    ?>
    <div class="precotizacion-associated-quotations mb-3 p-3 bg-light rounded">
        <strong><?php echo htmlspecialchars($labelAssociated); ?>:</strong>
        <?php
        $links = [];
        foreach ($associatedQuotations as $q) {
            $url = Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $q->id);
            $links[] = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($q->quotation_number ?? ('COT-' . $q->id)) . '</a>';
        }
        echo implode(', ', $links);
        ?>
    </div>
    <?php endif; ?>

    <?php if ($precotizacionLocked) :
        $msgLocked = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_MODIFY');
        if ($msgLocked === 'COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_MODIFY'
            || (is_string($msgLocked) && strpos($msgLocked, 'COM_ORDENPRODUCCION_') === 0)) {
            $msgLocked = 'Esta pre-cotización ya forma parte de una cotización formal, por eso no se puede editar aquí. Si necesita cambios, cree una nueva pre-cotización o revise la cotización vinculada.';
        }
    ?>
    <div class="alert alert-info mb-3"><?php echo htmlspecialchars($msgLocked); ?></div>
    <?php endif; ?>

    <?php if ($ofertaViewOnly) :
        $msgOfertaRo = Text::_('COM_ORDENPRODUCCION_PRE_OFERTA_VIEW_ONLY');
        if ($msgOfertaRo === 'COM_ORDENPRODUCCION_PRE_OFERTA_VIEW_ONLY' || (is_string($msgOfertaRo) && strpos($msgOfertaRo, 'COM_ORDENPRODUCCION_') === 0)) {
            $msgOfertaRo = 'Esta pre-cotización es una oferta plantilla. Solo el autor puede editarla; usted puede verla.';
        }
    ?>
    <div class="alert alert-warning mb-3"><?php echo htmlspecialchars($msgOfertaRo); ?></div>
    <?php endif; ?>

    <div class="precotizacion-descripcion mb-3">
        <label class="form-label fw-bold"><?php echo htmlspecialchars($labelDescripcion); ?></label>
        <?php if ($precotizacionLocked || !$canEditDocument) : ?>
            <div class="form-control-plaintext bg-light px-2 py-1 rounded"><?php echo $descripcionValue !== '' ? htmlspecialchars($descripcionValue) : '<span class="text-muted">—</span>'; ?></div>
            <div class="mt-2">
                <span class="form-label fw-bold mb-0 d-block"><?php echo htmlspecialchars($labelMedidas); ?></span>
                <div class="form-control-plaintext bg-light px-2 py-1 rounded mt-1"><?php echo $medidasValue !== '' ? htmlspecialchars($medidasValue) : '<span class="text-muted">—</span>'; ?></div>
            </div>
        <?php else : ?>
        <form action="<?php echo htmlspecialchars($saveDescripcionUrl); ?>" method="post" id="precotizacion-desc-medidas-form" class="d-flex flex-wrap gap-2 align-items-stretch mb-0" style="min-width: 0;">
            <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <div class="flex-grow-1 d-flex" style="min-width: 200px;">
                <textarea id="precotizacion-descripcion" name="descripcion" class="form-control flex-grow-1" rows="3" placeholder="<?php echo htmlspecialchars($labelDescripcion); ?>" style="min-height: 5.5rem; resize:vertical;"><?php echo htmlspecialchars($descripcionValue); ?></textarea>
            </div>
            <div class="d-flex flex-column flex-grow-1" style="min-width: 200px; max-width: 360px;">
                <label class="form-label fw-bold mb-1" for="precotizacion-medidas"><?php echo htmlspecialchars($labelMedidas); ?></label>
                <textarea name="medidas" id="precotizacion-medidas" class="form-control flex-grow-1" rows="3" autocomplete="off" maxlength="512"
                          placeholder="<?php echo htmlspecialchars($placeholderMedidas); ?>"
                          style="min-height: 5.5rem; resize:vertical;"><?php echo htmlspecialchars($medidasValue); ?></textarea>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="precotizacion-oferta-facturar mb-3 d-flex flex-wrap align-items-center gap-4">
        <?php if ($precotizacionLocked || !$canEditDocument) : ?>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="precotizacion-facturar-display" disabled <?php echo $facturarChecked ? ' checked' : ''; ?>>
            <label class="form-check-label" for="precotizacion-facturar-display"><?php echo htmlspecialchars($labelFacturar); ?></label>
        </div>
        <?php else : ?>
        <form action="<?php echo htmlspecialchars($saveFacturarUrl); ?>" method="post" class="d-inline mb-0" id="form-facturar">
            <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" name="facturar" id="precotizacion-facturar" value="1" <?php echo $facturarChecked ? ' checked' : ''; ?> onchange="this.form.submit();">
                <label class="form-check-label" for="precotizacion-facturar"><?php echo htmlspecialchars($labelFacturar); ?></label>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <h2 class="h5 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINES_TITLE'); ?></h2>

    <?php if ($precotizacionLocked || !$canEditDocument) : ?>
        <?php if (empty($vendorLines)) : ?>
            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <?php else : ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_QTY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_DESC'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_PRICE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_LEAD_TIME'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendorLines as $line) :
                        $unit = round((float) ($line->price_per_sheet ?? 0), 2);
                        ?>
                    <tr>
                        <td><?php echo (int) $line->quantity; ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string) ($line->vendor_descripcion ?? ''))); ?></td>
                        <td class="text-end">Q <?php echo number_format($unit, 2); ?></td>
                        <td><?php echo htmlspecialchars((string) ($line->vendor_tiempo_entrega ?? '')); ?></td>
                        <td class="text-end">Q <?php echo number_format((float) ($line->total ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php else :
    if ($vendorLines === []) {
        $vendorLines = [
            (object) [
                'id'                  => 0,
                'quantity'            => 1,
                'vendor_descripcion'  => '',
                'vendor_tiempo_entrega' => '',
                'price_per_sheet'     => 0,
                'total'               => 0,
                'line_type'           => 'proveedor_externo',
            ],
        ];
    }
    ?>
    <form method="post" action="<?php echo htmlspecialchars($saveVendorLinesUrl); ?>" id="proveedor-externo-lines-form" class="mb-3">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
        <div class="table-responsive">
            <table class="table table-bordered" id="proveedor-externo-lines-table">
                <thead>
                    <tr>
                        <th style="width:7rem;"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_QTY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_DESC'); ?></th>
                        <th style="width:8rem;" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_PRICE'); ?></th>
                        <th style="width:14rem;"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_LEAD_TIME'); ?></th>
                        <th style="width:8rem;" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL'); ?></th>
                        <th style="width:6rem;" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody id="proveedor-externo-lines-tbody">
                    <?php foreach ($vendorLines as $i => $line) :
                        $lid = (int) $line->id;
                        $unit = round((float) ($line->price_per_sheet ?? 0), 2);
                        $tot  = round((float) ($line->total ?? 0), 2);
                        ?>
                    <tr class="proveedor-externo-line-row">
                        <td>
                            <input type="hidden" name="lines[<?php echo $i; ?>][id]" value="<?php echo $lid; ?>">
                            <input type="number" class="form-control form-control-sm js-vendor-qty" name="lines[<?php echo $i; ?>][quantity]" min="1" step="1" value="<?php echo (int) $line->quantity; ?>" required>
                        </td>
                        <td>
                            <textarea class="form-control form-control-sm" name="lines[<?php echo $i; ?>][vendor_descripcion]" rows="3" style="min-height:4rem;"><?php echo htmlspecialchars((string) ($line->vendor_descripcion ?? '')); ?></textarea>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm text-end js-vendor-price" name="lines[<?php echo $i; ?>][price_per_sheet]" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format($unit, 2, '.', '')); ?>">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="lines[<?php echo $i; ?>][vendor_tiempo_entrega]" maxlength="512" value="<?php echo htmlspecialchars((string) ($line->vendor_tiempo_entrega ?? '')); ?>">
                        </td>
                        <td class="text-end align-middle">
                            <span class="js-vendor-line-total">Q <?php echo number_format($tot, 2); ?></span>
                        </td>
                        <td class="text-end align-middle">
                            <?php if ($lid > 0) :
                                $deleteLineUrl = 'index.php?option=com_ordenproduccion&task=precotizacion.deleteLine&line_id=' . $lid . '&id=' . $preCotizacionId;
                                ?>
                            <form action="<?php echo Route::_($deleteLineUrl); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE_LINE')); ?>');">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('JACTION_DELETE'); ?></button>
                            </form>
                            <?php else : ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary js-remove-vendor-row" title="<?php echo Text::_('JACTION_DELETE'); ?>">×</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-outline-primary" id="proveedor-externo-add-line" aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ADD_LINE'); ?>">+</button>
            <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_SAVE_LINES'); ?></button>
        </div>
    </form>
    <template id="proveedor-externo-line-template">
        <tr class="proveedor-externo-line-row">
            <td>
                <input type="hidden" name="lines[__I__][id]" value="0">
                <input type="number" class="form-control form-control-sm js-vendor-qty" name="lines[__I__][quantity]" min="1" step="1" value="1" required>
            </td>
            <td>
                <textarea class="form-control form-control-sm" name="lines[__I__][vendor_descripcion]" rows="3" style="min-height:4rem;"></textarea>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm text-end js-vendor-price" name="lines[__I__][price_per_sheet]" min="0" step="0.01" value="0.00">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="lines[__I__][vendor_tiempo_entrega]" maxlength="512" value="">
            </td>
            <td class="text-end align-middle">
                <span class="js-vendor-line-total">Q 0.00</span>
            </td>
            <td class="text-end align-middle">
                <button type="button" class="btn btn-sm btn-outline-secondary js-remove-vendor-row" title="<?php echo Text::_('JACTION_DELETE'); ?>">×</button>
            </td>
        </tr>
    </template>
    <script>
    (function() {
        var tbody = document.getElementById('proveedor-externo-lines-tbody');
        var tpl = document.getElementById('proveedor-externo-line-template');
        var btnAdd = document.getElementById('proveedor-externo-add-line');
        if (!tbody || !tpl || !btnAdd) return;
        var nextIdx = tbody.querySelectorAll('tr.proveedor-externo-line-row').length;

        function parseNum(el) {
            if (!el) return 0;
            var v = parseFloat(String(el.value).replace(',', '.'));
            return isFinite(v) ? v : 0;
        }

        function updateRowTotal(row) {
            var q = row.querySelector('.js-vendor-qty');
            var p = row.querySelector('.js-vendor-price');
            var out = row.querySelector('.js-vendor-line-total');
            if (!out) return;
            var qty = Math.max(1, Math.floor(parseNum(q) || 1));
            if (q && String(q.value) !== String(qty)) q.value = qty;
            var price = Math.round(parseNum(p) * 100) / 100;
            var t = Math.round(qty * price * 100) / 100;
            out.textContent = 'Q ' + t.toFixed(2);
        }

        function bindRow(row) {
            row.querySelectorAll('.js-vendor-qty, .js-vendor-price').forEach(function(el) {
                el.addEventListener('input', function() { updateRowTotal(row); });
            });
            var rm = row.querySelector('.js-remove-vendor-row');
            if (rm) {
                rm.addEventListener('click', function() {
                    if (tbody.querySelectorAll('tr.proveedor-externo-line-row').length <= 1) {
                        var q = row.querySelector('.js-vendor-qty');
                        var pr = row.querySelector('.js-vendor-price');
                        var ta = row.querySelector('textarea[name*="[vendor_descripcion]"]');
                        var tt = row.querySelector('input[name*="[vendor_tiempo_entrega]"]');
                        if (q) q.value = '1';
                        if (pr) pr.value = '0.00';
                        if (ta) ta.value = '';
                        if (tt) tt.value = '';
                        updateRowTotal(row);
                        return;
                    }
                    row.parentNode.removeChild(row);
                });
            }
            updateRowTotal(row);
        }

        tbody.querySelectorAll('tr.proveedor-externo-line-row').forEach(bindRow);

        btnAdd.addEventListener('click', function() {
            var html = tpl.innerHTML.replace(/__I__/g, String(nextIdx));
            var wrap = document.createElement('tbody');
            wrap.innerHTML = html.trim();
            var row = wrap.firstElementChild;
            if (!row) return;
            tbody.appendChild(row);
            nextIdx++;
            bindRow(row);
        });
    })();
    </script>
    <?php endif; ?>

    <div class="table-responsive mt-4">
        <table class="table table-sm w-auto ms-auto">
            <tbody>
                <tr>
                    <td class="text-end pe-3"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?></td>
                    <td class="text-end fw-bold">Q <?php echo number_format($linesSubtotal, 2); ?></td>
                </tr>
                <?php if ($canSeePrecotInternalTax && $paramMargen != 0) : ?>
                <?php $margenTotal = $margenAmount + $margenAdicional; ?>
                <tr>
                    <td class="text-end pe-3">(<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_TOTAL'); ?> Q <?php echo number_format($margenTotal, 2); ?>) <?php echo Text::_('COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA'); ?> (<?php echo number_format($paramMargen, 1); ?>%)</td>
                    <td class="text-end">Q <?php echo number_format($margenAmount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($canSeePrecotInternalTax && $facturar && $paramIva != 0) : ?>
                <tr>
                    <td class="text-end pe-3"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_IVA'); ?> (<?php echo number_format($paramIva, 1); ?>%)</td>
                    <td class="text-end">Q <?php echo number_format($ivaAmount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($canSeePrecotInternalTax && $facturar && $paramIsr != 0) : ?>
                <tr>
                    <td class="text-end pe-3"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_ISR'); ?> (<?php echo number_format($paramIsr, 1); ?>%)</td>
                    <td class="text-end">Q <?php echo number_format($isrAmount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($paramComision != 0) : ?>
                <tr>
                    <td class="text-end pe-3"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA'); ?> (<?php echo number_format($paramComision, 1); ?>%)</td>
                    <td class="text-end">Q <?php echo number_format($comisionAmount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($margenAdicional > 0) : ?>
                <tr>
                    <td class="text-end pe-3"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_ADICIONAL'); ?></td>
                    <td class="text-end">Q <?php echo number_format($margenAdicional, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="table-secondary">
                    <td class="text-end pe-3 fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?></td>
                    <td class="text-end fw-bold">Q <?php echo number_format($displayTotal, 2); ?></td>
                </tr>
                <?php if ($comisionMargenAdicionalAmount > 0) : ?>
                <?php $totalComision = $comisionAmount + $comisionMargenAdicionalAmount; ?>
                <tr>
                    <td class="text-end pe-3">(<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL_COMISION'); ?> Q <?php echo number_format($totalComision, 2); ?>) <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COMISION_MARGEN_ADICIONAL'); ?> (<?php echo number_format($paramComisionMargenAdicional, 1); ?>%)</td>
                    <td class="text-end">Q <?php echo number_format($comisionMargenAdicionalAmount, 2); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
