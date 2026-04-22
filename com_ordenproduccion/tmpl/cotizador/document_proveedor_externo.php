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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

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

$saveVendorLinesUrl     = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.saveProveedorExternoLines');
$saveVendorQuoteEventCondicionesUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.saveVendorQuoteEventCondiciones', false, Route::TLS_IGNORE, true);
$uploadVendorQuoteUrl   = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.uploadVendorQuoteAttachment', false, Route::TLS_IGNORE, true);
$vendorQuoteAttachRel   = (isset($item->vendor_quote_attachment) && is_string($item->vendor_quote_attachment))
    ? trim($item->vendor_quote_attachment) : '';
$vendorQuoteAttachHref  = $vendorQuoteAttachRel !== ''
    ? rtrim(Uri::root(), '/') . '/' . str_replace('\\', '/', ltrim($vendorQuoteAttachRel, '/'))
    : '';
$vendorAttachExt        = $vendorQuoteAttachRel !== '' ? strtolower(pathinfo($vendorQuoteAttachRel, PATHINFO_EXTENSION)) : '';
$vendorAttachIsImage    = in_array($vendorAttachExt, ['jpg', 'jpeg', 'png'], true);
$vendorAttachIsPdf      = ($vendorAttachExt === 'pdf');
$badgeVendor        = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_BADGE');
if (strpos($badgeVendor, 'COM_ORDENPRODUCCION_') === 0) {
    $badgeVendor = 'Proveedor externo';
}
$colQty   = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_QTY');
$colDesc  = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_DESC');
$colPrice = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_PRICE_SHORT');
if (strpos($colPrice, 'COM_ORDENPRODUCCION_') === 0) {
    $colPrice = 'Precio';
}
$colTotal = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL');
$colAttach = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_ATTACH');
if (strpos($colAttach, 'COM_ORDENPRODUCCION_') === 0) {
    $colAttach = 'Adjunto';
}
$colEventCondiciones = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_COL_CONDICIONES');
$labelSaveEventCondiciones = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_CONDICIONES_SAVE');
$colActions = Text::_('COM_ORDENPRODUCCION_ACTIONS');
$user  = Factory::getUser();
$canAttachVendorQuoteEvent = !$precotizacionLocked && $canEditDocument && !$user->guest;
$token = Session::getFormToken();
$vendorQuoteProveedoresUrl = Route::_(
    'index.php?option=com_ordenproduccion&task=precotizacion.vendorQuoteProveedoresJson&format=json&' . $token . '=1',
    false,
    Route::TLS_IGNORE,
    true
);
/** Non-SEF index.php base so AJAX can append query params safely (SEF breaks string concat on Route URLs). */
$vendorQuoteAjaxIndex = rtrim(Uri::root(), '/') . '/index.php';
$vendorQuoteSendEmailUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.vendorQuoteSendEmail', false, Route::TLS_IGNORE, true);
?>

<div class="com-ordenproduccion-precotizacion-document com-ordenproduccion-precotizacion-proveedor-externo container py-4">
<style>
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-wrap {
    max-width: 100%;
    overflow-x: visible;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table {
    table-layout: fixed;
    width: 100%;
    margin-bottom: 0;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table th,
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table td {
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
    min-width: 0;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table thead th {
    font-size: 0.8rem;
    line-height: 1.25;
    font-weight: 600;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .col-qty { width: 4.25rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .col-desc { width: 52%; }
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .col-price { width: 5.5rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .col-total { width: 5rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .col-actions { width: 2.75rem; padding-left: 0.2rem; padding-right: 0.2rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log .col-evt-cond { min-width: 10rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log tr.precot-vendor-evt-cond-row td.col-evt-cond { max-width: none; }
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log tr.precot-vendor-evt-cond-row td.col-evt-cond textarea.form-control {
    display: block;
    width: 100%;
    min-width: 0;
    min-height: calc(2 * 1.25em + 0.75rem);
    max-width: 100%;
    max-height: 12rem;
    resize: vertical;
    box-sizing: border-box;
    line-height: 1.25;
}
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log tr.precot-vendor-evt-main-row td.col-evt-spacer { width: 0.01%; }
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log td.col-evt-cond-label {
    max-width: 12rem;
    white-space: normal;
    vertical-align: top;
}
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log .col-evt-attach { width: 5.5rem; padding-left: 0.25rem; padding-right: 0.25rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-quote-event-log .col-evt-save { width: 2.85rem; padding-left: 0.2rem; padding-right: 0.2rem; }
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table textarea.form-control {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    resize: vertical;
    min-height: 3.25rem;
    max-height: 8rem;
    box-sizing: border-box;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table input.form-control {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .btn-vendor-row-action {
    padding: 0.15rem 0.4rem;
    line-height: 1.2;
    min-width: 0;
}
.com-ordenproduccion-precotizacion-proveedor-externo .pre-cot-vendor-lines-table .js-vendor-line-total {
    font-size: 0.9rem;
    white-space: nowrap;
}
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-line-viewer iframe {
    min-height: 480px;
}
@media (min-width: 768px) {
    .com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-line-viewer iframe {
        min-height: min(70vh, 720px);
    }
}
.com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-attachment-viewer iframe {
    min-height: 480px;
}
@media (min-width: 768px) {
    .com-ordenproduccion-precotizacion-proveedor-externo .precot-vendor-attachment-viewer iframe {
        min-height: min(70vh, 720px);
    }
}
</style>
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

    <h2 class="h5 mt-4" id="precot-vendor-lines-anchor"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINES_TITLE'); ?></h2>
    <?php if ($vendorQuoteAttachHref !== '') : ?>
    <p class="small mb-2">
        <span class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_CURRENT'); ?>:</span>
        <a href="<?php echo htmlspecialchars($vendorQuoteAttachHref); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEW'); ?></a>
    </p>
    <?php endif; ?>

    <?php if ($precotizacionLocked || !$canEditDocument) : ?>
        <?php if (empty($vendorLines)) : ?>
            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <?php else : ?>
        <div class="pre-cot-vendor-lines-wrap">
            <table class="table table-sm table-bordered pre-cot-vendor-lines-table">
                <thead>
                    <tr>
                        <th class="col-qty"><?php echo htmlspecialchars($colQty); ?></th>
                        <th class="col-desc"><?php echo htmlspecialchars($colDesc); ?></th>
                        <th class="col-price text-end" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_PRICE')); ?>"><?php echo htmlspecialchars($colPrice); ?></th>
                        <th class="col-total text-end"><?php echo htmlspecialchars($colTotal); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendorLines as $line) :
                        $unit = round((float) ($line->price_per_sheet ?? 0), 2);
                        ?>
                    <tr>
                        <td class="col-qty"><?php echo (int) $line->quantity; ?></td>
                        <td class="col-desc"><?php echo nl2br(htmlspecialchars((string) ($line->vendor_descripcion ?? ''))); ?></td>
                        <td class="col-price text-end">Q <?php echo number_format($unit, 2); ?></td>
                        <td class="col-total text-end">Q <?php echo number_format((float) ($line->total ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php if (!$user->guest) : ?>
        <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorQuoteModal" id="btn-vendor-quote-open">
                <i class="fas fa-paper-plane" aria-hidden="true"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_REQUEST_QUOTE_BTN'); ?>
            </button>
        </div>
        <?php endif; ?>
    <?php else :
    if ($vendorLines === []) {
        $vendorLines = [
            (object) [
                'id'                  => 0,
                'quantity'            => 1,
                'vendor_descripcion'  => '',
                'price_per_sheet'     => 0,
                'total'               => 0,
                'line_type'           => 'proveedor_externo',
            ],
        ];
    }
    ?>
    <?php $saveLinesFormId = 'proveedor-externo-lines-form'; ?>
    <?php /* Token + precot id only: line inputs use form= attribute so row upload/delete forms are not nested (invalid HTML). */ ?>
    <form method="post" action="<?php echo htmlspecialchars($saveVendorLinesUrl); ?>" id="<?php echo htmlspecialchars($saveLinesFormId); ?>" class="d-none" aria-hidden="true">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
    </form>
    <div class="pre-cot-vendor-lines-wrap mb-3">
            <table class="table table-sm table-bordered pre-cot-vendor-lines-table" id="proveedor-externo-lines-table">
                <thead>
                    <tr>
                        <th class="col-qty"><?php echo htmlspecialchars($colQty); ?></th>
                        <th class="col-desc"><?php echo htmlspecialchars($colDesc); ?></th>
                        <th class="col-price text-end" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_PRICE')); ?>"><?php echo htmlspecialchars($colPrice); ?></th>
                        <th class="col-total text-end"><?php echo htmlspecialchars($colTotal); ?></th>
                        <th class="col-actions text-center"><span class="visually-hidden"><?php echo htmlspecialchars($colActions); ?></span></th>
                    </tr>
                </thead>
                <tbody id="proveedor-externo-lines-tbody">
                    <?php foreach ($vendorLines as $i => $line) :
                        $lid = (int) $line->id;
                        $unit = round((float) ($line->price_per_sheet ?? 0), 2);
                        $tot  = round((float) ($line->total ?? 0), 2);
                        ?>
                    <tr class="proveedor-externo-line-row">
                        <td class="col-qty">
                            <input type="hidden" name="lines[<?php echo $i; ?>][id]" value="<?php echo $lid; ?>" form="<?php echo htmlspecialchars($saveLinesFormId); ?>">
                            <input type="number" class="form-control form-control-sm js-vendor-qty" name="lines[<?php echo $i; ?>][quantity]" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" min="1" step="1" value="<?php echo (int) $line->quantity; ?>" required>
                        </td>
                        <td class="col-desc">
                            <textarea class="form-control form-control-sm" name="lines[<?php echo $i; ?>][vendor_descripcion]" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" rows="2" aria-label="<?php echo htmlspecialchars($colDesc); ?>"><?php echo htmlspecialchars((string) ($line->vendor_descripcion ?? '')); ?></textarea>
                        </td>
                        <td class="col-price">
                            <input type="number" class="form-control form-control-sm text-end js-vendor-price" name="lines[<?php echo $i; ?>][price_per_sheet]" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format($unit, 2, '.', '')); ?>">
                        </td>
                        <td class="col-total text-end align-middle">
                            <span class="js-vendor-line-total">Q <?php echo number_format($tot, 2); ?></span>
                        </td>
                        <td class="col-actions text-center align-middle">
                            <?php if ($lid > 0) :
                                $deleteLineUrl = 'index.php?option=com_ordenproduccion&task=precotizacion.deleteLine&line_id=' . $lid . '&id=' . $preCotizacionId;
                                ?>
                            <form action="<?php echo Route::_($deleteLineUrl); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE_LINE')); ?>');">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-vendor-row-action" title="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>" aria-label="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>">×</button>
                            </form>
                            <?php else : ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-vendor-row-action js-remove-vendor-row" title="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>" aria-label="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>">×</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
    </div>
        <div class="d-flex flex-wrap gap-2 mt-2 align-items-center justify-content-between">
            <?php if (!$user->guest) : ?>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorQuoteModal" id="btn-vendor-quote-open">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_REQUEST_QUOTE_BTN'); ?>
                </button>
            </div>
            <?php else : ?>
            <div></div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" class="btn btn-primary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_SAVE_LINES'); ?></button>
                <button type="button" class="btn btn-outline-primary" id="proveedor-externo-add-line" aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ADD_LINE'); ?>">+</button>
            </div>
        </div>
    <template id="proveedor-externo-line-template">
        <tr class="proveedor-externo-line-row">
            <td class="col-qty">
                <input type="hidden" name="lines[__I__][id]" value="0" form="<?php echo htmlspecialchars($saveLinesFormId); ?>">
                <input type="number" class="form-control form-control-sm js-vendor-qty" name="lines[__I__][quantity]" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" min="1" step="1" value="1" required>
            </td>
            <td class="col-desc">
                <textarea class="form-control form-control-sm" name="lines[__I__][vendor_descripcion]" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" rows="2"></textarea>
            </td>
            <td class="col-price">
                <input type="number" class="form-control form-control-sm text-end js-vendor-price" name="lines[__I__][price_per_sheet]" form="<?php echo htmlspecialchars($saveLinesFormId); ?>" min="0" step="0.01" value="0.00">
            </td>
            <td class="col-total text-end align-middle">
                <span class="js-vendor-line-total">Q 0.00</span>
            </td>
            <td class="col-actions text-center align-middle">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-vendor-row-action js-remove-vendor-row" title="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>" aria-label="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>">×</button>
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
                        if (q) q.value = '1';
                        if (pr) pr.value = '0.00';
                        if (ta) ta.value = '';
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

    <?php if (!$user->guest) : ?>
    <form id="vendor-quote-email-form" method="post" action="<?php echo htmlspecialchars($vendorQuoteSendEmailUrl); ?>" class="d-none" aria-hidden="true">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="precot_id" value="<?php echo (int) $preCotizacionId; ?>">
        <input type="hidden" name="proveedor_id" id="vendor-quote-email-proveedor-id" value="">
    </form>

    <div class="modal fade" id="vendorQuoteModal" tabindex="-1" aria-labelledby="vendorQuoteModalTitle" aria-hidden="true"
         data-proveedores-url="<?php echo htmlspecialchars($vendorQuoteProveedoresUrl); ?>"
         data-ajax-index="<?php echo htmlspecialchars($vendorQuoteAjaxIndex); ?>"
         data-token-name="<?php echo htmlspecialchars($token); ?>"
         data-precot-id="<?php echo (int) $preCotizacionId; ?>">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="vendorQuoteModalTitle"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_MODAL_TITLE'); ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(Text::_('JCLOSE')); ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="vendor-quote-status"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_SELECT_VENDOR'); ?></p>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <div class="list-group small" id="vendor-quote-proveedor-list" role="listbox" aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_SELECT_VENDOR')); ?>"></div>
                        </div>
                        <div class="col-md-7">
                            <div id="vendor-quote-method-wrap" class="d-none">
                                <div class="fw-bold mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_METHOD_LABEL'); ?></div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vendor_quote_method" id="vqm-email" value="email" checked>
                                    <label class="form-check-label" for="vqm-email"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_METHOD_EMAIL'); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vendor_quote_method" id="vqm-cell" value="cellphone">
                                    <label class="form-check-label" for="vqm-cell"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_METHOD_CELLPHONE'); ?></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vendor_quote_method" id="vqm-pdf" value="pdf">
                                    <label class="form-check-label" for="vqm-pdf"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_METHOD_PDF'); ?></label>
                                </div>
                            </div>
                            <div id="vendor-quote-cell-panel" class="mt-3 d-none">
                                <label class="form-label fw-bold" for="vendor-quote-cell-text"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_COMPOSE_MESSAGE'); ?></label>
                                <textarea id="vendor-quote-cell-text" class="form-control font-monospace small" rows="6" readonly></textarea>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="vendor-quote-copy-msg"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_COPY_MESSAGE'); ?></button>
                                    <a href="#" class="btn btn-outline-primary btn-sm d-none" id="vendor-quote-tel-link"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_OPEN_TEL'); ?></a>
                                </div>
                                <p class="small text-muted mt-2 mb-0" id="vendor-quote-phone-hint"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                    <button type="button" class="btn btn-primary d-none" id="vendor-quote-btn-primary"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_ACTION_SEND_EMAIL'); ?></button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var modalEl = document.getElementById('vendorQuoteModal');
        if (!modalEl) return;

        var msgs = {
            loading: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_LOADING_PROVEEDORES')); ?>,
            loadError: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_LOAD_ERROR')); ?>,
            selectFirst: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_SELECT_VENDOR_FIRST')); ?>,
            copied: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_COPIED')); ?>,
            noPhone: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_NO_PHONE')); ?>,
            sendEmail: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_ACTION_SEND_EMAIL')); ?>,
            composeCell: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_ACTION_COMPOSE_CELL')); ?>,
            downloadPdf: <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_ACTION_DOWNLOAD_PDF')); ?>
        };

        var listEl = document.getElementById('vendor-quote-proveedor-list');
        var statusEl = document.getElementById('vendor-quote-status');
        var methodWrap = document.getElementById('vendor-quote-method-wrap');
        var cellPanel = document.getElementById('vendor-quote-cell-panel');
        var cellText = document.getElementById('vendor-quote-cell-text');
        var btnPrimary = document.getElementById('vendor-quote-btn-primary');
        var btnCopy = document.getElementById('vendor-quote-copy-msg');
        var telLink = document.getElementById('vendor-quote-tel-link');
        var phoneHint = document.getElementById('vendor-quote-phone-hint');
        var emailProveedorInput = document.getElementById('vendor-quote-email-proveedor-id');
        var emailForm = document.getElementById('vendor-quote-email-form');

        var proveedoresUrl = modalEl.getAttribute('data-proveedores-url') || '';
        var ajaxIndex = modalEl.getAttribute('data-ajax-index') || '';
        var tokenName = modalEl.getAttribute('data-token-name') || '';
        var precotId = modalEl.getAttribute('data-precot-id') || '0';

        var selectedId = 0;
        var cellLoaded = false;

        /**
         * Build index.php?option=com_ordenproduccion&task=... URLs with proper query string (works with SEF on the site).
         *
         * @param {string} task
         * @param {Record<string, string>} extraParams
         * @param {boolean} useFormatJson
         * @return {string}
         */
        function buildComponentTaskUrl(task, extraParams, useFormatJson) {
            var base = ajaxIndex || (window.location.origin + '/index.php');
            var u = new URL(base, window.location.href);
            u.searchParams.set('option', 'com_ordenproduccion');
            u.searchParams.set('task', task);
            if (useFormatJson !== false) {
                u.searchParams.set('format', 'json');
            }
            if (tokenName) {
                u.searchParams.set(tokenName, '1');
            }
            if (extraParams) {
                Object.keys(extraParams).forEach(function(k) {
                    u.searchParams.set(k, extraParams[k]);
                });
            }
            return u.toString();
        }

        function setStatus(t) {
            if (statusEl) statusEl.textContent = t;
        }

        function getMethod() {
            var r = modalEl.querySelector('input[name="vendor_quote_method"]:checked');
            return r ? r.value : 'email';
        }

        function updatePrimaryButton() {
            if (!btnPrimary) return;
            var m = getMethod();
            cellPanel.classList.add('d-none');
            if (m === 'email') {
                btnPrimary.textContent = msgs.sendEmail;
                btnPrimary.classList.remove('d-none');
            } else if (m === 'cellphone') {
                btnPrimary.textContent = msgs.composeCell;
                btnPrimary.classList.remove('d-none');
            } else {
                btnPrimary.textContent = msgs.downloadPdf;
                btnPrimary.classList.remove('d-none');
            }
        }

        function resetModal() {
            selectedId = 0;
            cellLoaded = false;
            listEl.innerHTML = '';
            methodWrap.classList.add('d-none');
            cellPanel.classList.add('d-none');
            cellText.value = '';
            btnPrimary.classList.add('d-none');
            telLink.classList.add('d-none');
            phoneHint.textContent = '';
            if (emailProveedorInput) emailProveedorInput.value = '';
        }

        function renderDetail() {
            methodWrap.classList.remove('d-none');
            updatePrimaryButton();
        }

        function selectProveedor(id) {
            selectedId = id;
            cellLoaded = false;
            cellPanel.classList.add('d-none');
            listEl.querySelectorAll('.list-group-item').forEach(function(el) {
                el.classList.toggle('active', parseInt(el.getAttribute('data-id'), 10) === id);
            });
            if (emailProveedorInput) emailProveedorInput.value = String(id);
            var url = buildComponentTaskUrl('precotizacion.vendorQuoteProveedorJson', { proveedor_id: String(id) }, true);
            setStatus(msgs.loading);
            fetch(url, { credentials: 'same-origin' })
                .then(function(r) {
                    return r.text().then(function(t) {
                        try {
                            return JSON.parse(t);
                        } catch (e) {
                            return null;
                        }
                    });
                })
                .then(function(data) {
                    if (!data || !data.ok || !data.proveedor) {
                        setStatus(msgs.loadError);
                        return;
                    }
                    renderDetail();
                    setStatus('');
                })
                .catch(function() { setStatus(msgs.loadError); });
        }

        function loadProveedores() {
            setStatus(msgs.loading);
            fetch(proveedoresUrl, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    listEl.innerHTML = '';
                    if (!data || !data.ok || !Array.isArray(data.proveedores)) {
                        setStatus(msgs.loadError);
                        return;
                    }
                    if (data.proveedores.length === 0) {
                        setStatus(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_EMPTY')); ?>);
                        return;
                    }
                    data.proveedores.forEach(function(pr) {
                        var a = document.createElement('button');
                        a.type = 'button';
                        a.className = 'list-group-item list-group-item-action';
                        a.setAttribute('data-id', String(pr.id));
                        a.textContent = pr.name || ('#' + pr.id);
                        a.addEventListener('click', function() { selectProveedor(parseInt(pr.id, 10)); });
                        listEl.appendChild(a);
                    });
                    setStatus(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_SELECT_VENDOR')); ?>);
                })
                .catch(function() {
                    listEl.innerHTML = '';
                    setStatus(msgs.loadError);
                });
        }

        modalEl.addEventListener('show.bs.modal', function() {
            resetModal();
            loadProveedores();
        });

        modalEl.querySelectorAll('input[name="vendor_quote_method"]').forEach(function(inp) {
            inp.addEventListener('change', function() {
                cellPanel.classList.add('d-none');
                cellLoaded = false;
                updatePrimaryButton();
            });
        });

        if (btnCopy && cellText) {
            btnCopy.addEventListener('click', function() {
                cellText.select();
                document.execCommand('copy');
                if (phoneHint) {
                    phoneHint.textContent = msgs.copied;
                    setTimeout(function() { if (phoneHint.textContent === msgs.copied) phoneHint.textContent = ''; }, 2000);
                }
            });
        }

        if (btnPrimary) {
            btnPrimary.addEventListener('click', function() {
                if (selectedId < 1) {
                    setStatus(msgs.selectFirst);
                    return;
                }
                var m = getMethod();
                if (m === 'email') {
                    if (emailForm) emailForm.submit();
                    return;
                }
                if (m === 'pdf') {
                    var pdfUrl = buildComponentTaskUrl(
                        'precotizacion.vendorQuoteDownloadPdf',
                        { precot_id: String(precotId), proveedor_id: String(selectedId) },
                        false
                    );
                    window.open(pdfUrl, '_blank', 'noopener,noreferrer');
                    var modalApi = window.bootstrap && window.bootstrap.Modal;
                    if (modalApi) {
                        var inst = modalApi.getInstance(modalEl);
                        if (inst) {
                            inst.hide();
                        }
                    }
                    window.location.reload();
                    return;
                }
                if (m === 'cellphone') {
                    if (cellLoaded) {
                        cellPanel.classList.remove('d-none');
                        return;
                    }
                    var fd = new FormData();
                    fd.append(tokenName, '1');
                    fd.append('precot_id', precotId);
                    fd.append('proveedor_id', String(selectedId));
                    setStatus(msgs.loading);
                    fetch(buildComponentTaskUrl('precotizacion.vendorQuoteCellphoneJson', {}, true), { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            setStatus('');
                            if (!data || !data.ok) {
                                setStatus(msgs.loadError);
                                return;
                            }
                            cellText.value = data.message_text || '';
                            cellLoaded = true;
                            cellPanel.classList.remove('d-none');
                            var ph = (data.phone || '').trim();
                            if (ph) {
                                telLink.href = 'tel:' + ph.replace(/\s+/g, '');
                                telLink.classList.remove('d-none');
                                phoneHint.textContent = ph;
                            } else {
                                telLink.classList.add('d-none');
                                phoneHint.textContent = msgs.noPhone;
                            }
                        })
                        .catch(function() { setStatus(msgs.loadError); });
                }
            });
        }
    })();
    </script>
    <?php endif; ?>

    <?php
    $vendorQuoteEvents = $this->precotVendorQuoteEvents ?? [];
    ?>
    <?php if (!empty($vendorQuoteEvents)) : ?>
    <section class="precot-vendor-quote-event-log mt-4 pt-4 border-top" aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_LOG_TITLE')); ?>">
        <h2 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_LOG_TITLE'); ?></h2>
        <p class="small text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_LOG_ATTACH_HINT'); ?></p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_COL_DATE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_COL_USER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_COL_ACTION'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_COL_VENDOR'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_COL_DETAIL'); ?></th>
                        <th scope="col" class="col-evt-spacer p-1" aria-hidden="true"></th>
                        <th scope="col" class="col-evt-attach text-center" title="<?php echo htmlspecialchars($colAttach); ?>"><span class="visually-hidden"><?php echo htmlspecialchars($colAttach); ?></span><i class="fas fa-paperclip" aria-hidden="true"></i></th>
                        <th scope="col" class="col-evt-save text-center" title="<?php echo htmlspecialchars($labelSaveEventCondiciones); ?>"><span class="visually-hidden"><?php echo htmlspecialchars($labelSaveEventCondiciones); ?></span><i class="fas fa-save" aria-hidden="true"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendorQuoteEvents as $ev) :
                        $meta = [];
                        if (!empty($ev->meta)) {
                            $decoded = json_decode((string) $ev->meta, true);
                            $meta    = is_array($decoded) ? $decoded : [];
                        }
                        $etype = (string) ($ev->event_type ?? '');
                        $actionLabel = $etype;
                        if ($etype === 'email_sent') {
                            $actionLabel = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_EMAIL_SENT');
                        } elseif ($etype === 'pdf_download') {
                            $actionLabel = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_PDF_DOWNLOAD');
                        } elseif ($etype === 'cellphone_compose') {
                            $actionLabel = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_CELLPHONE_COMPOSE');
                        }
                        $detail = '';
                        if ($etype === 'email_sent') {
                            $detail = trim((string) ($meta['to_email'] ?? ''));
                            $subj   = trim((string) ($meta['subject'] ?? ''));
                            if ($subj !== '') {
                                $detail .= ($detail !== '' ? ' — ' : '') . $subj;
                            }
                        } elseif ($etype === 'pdf_download') {
                            $detail = (string) ($meta['filename'] ?? '');
                        } elseif ($etype === 'cellphone_compose') {
                            $detail = (string) ($meta['phone'] ?? '');
                        }
                        $actor = trim((string) ($meta['actor_name'] ?? ''));
                        if ($actor === '' && !empty($ev->created_by)) {
                            $actor = '#' . (int) $ev->created_by;
                        }
                        $provName = trim((string) ($meta['proveedor_name'] ?? ''));
                        if ($provName === '' && !empty($ev->proveedor_id)) {
                            $provName = '#' . (int) $ev->proveedor_id;
                        }
                        $evtId = (int) ($ev->id ?? 0);
                        $evtCondiciones = isset($ev->condiciones_entrega) ? trim((string) $ev->condiciones_entrega) : '';
                        $evtAttachRel = isset($ev->vendor_quote_attachment) ? trim((string) $ev->vendor_quote_attachment) : '';
                        $evtAttachHref = $evtAttachRel !== ''
                            ? rtrim(Uri::root(), '/') . '/' . str_replace('\\', '/', ltrim($evtAttachRel, '/'))
                            : '';
                        $evtAttachExt = $evtAttachRel !== '' ? strtolower(pathinfo($evtAttachRel, PATHINFO_EXTENSION)) : '';
                        ?>
                    <tr class="precot-vendor-evt-main-row">
                        <td class="text-nowrap small"><?php echo htmlspecialchars(HTMLHelper::_('date', $ev->created, Text::_('DATE_FORMAT_LC6'))); ?></td>
                        <td class="small"><?php echo htmlspecialchars($actor); ?></td>
                        <td class="small"><?php echo htmlspecialchars($actionLabel); ?></td>
                        <td class="small"><?php echo htmlspecialchars($provName); ?></td>
                        <td class="small"><?php echo htmlspecialchars($detail); ?></td>
                        <td class="col-evt-spacer small p-1 bg-light bg-opacity-25" aria-hidden="true">&nbsp;</td>
                        <td class="col-evt-attach text-center align-middle" rowspan="2">
                            <div class="d-flex justify-content-center align-items-center gap-1 flex-wrap">
                                <?php if ($evtAttachHref !== '') : ?>
                                <button type="button" class="btn btn-sm btn-outline-primary js-vendor-line-view p-1"
                                        data-url="<?php echo htmlspecialchars($evtAttachHref); ?>"
                                        data-ext="<?php echo htmlspecialchars($evtAttachExt); ?>"
                                        title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_ATTACH_VIEW')); ?>"
                                        aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_ATTACH_VIEW')); ?>">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($canAttachVendorQuoteEvent && $evtId > 0) : ?>
                                <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($uploadVendorQuoteUrl); ?>" class="d-inline mb-0">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
                                    <input type="hidden" name="event_id" value="<?php echo (int) $evtId; ?>">
                                    <input type="file" name="vendor_quote_file" id="vendor-event-file-<?php echo (int) $evtId; ?>"
                                           class="d-none js-vendor-event-file"
                                           accept=".pdf,.jpg,.jpeg,.png,image/jpeg,image/png,application/pdf"
                                           aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_ATTACH_UPLOAD')); ?>">
                                    <label for="vendor-event-file-<?php echo (int) $evtId; ?>"
                                           class="btn btn-sm btn-outline-secondary p-1 mb-0"
                                           title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_ATTACH_UPLOAD')); ?>">
                                        <i class="fas fa-paperclip" aria-hidden="true"></i><span class="visually-hidden"><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_ATTACH_UPLOAD')); ?></span>
                                    </label>
                                </form>
                                <?php elseif ($evtAttachHref === '') : ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-evt-save text-center align-middle" rowspan="2">
                            <?php if ($canAttachVendorQuoteEvent && $evtId > 0) : ?>
                            <form id="vendor-evt-cond-form-<?php echo (int) $evtId; ?>" method="post" action="<?php echo htmlspecialchars($saveVendorQuoteEventCondicionesUrl); ?>" class="mb-0">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
                                <input type="hidden" name="event_id" value="<?php echo (int) $evtId; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary p-1" title="<?php echo htmlspecialchars($labelSaveEventCondiciones); ?>" aria-label="<?php echo htmlspecialchars($labelSaveEventCondiciones); ?>">
                                    <i class="fas fa-save" aria-hidden="true"></i>
                                </button>
                            </form>
                            <?php else : ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr class="precot-vendor-evt-cond-row">
                        <td class="col-evt-cond-label small border-top-0 py-2 pe-2 align-top">
                            <?php if ($canAttachVendorQuoteEvent && $evtId > 0) : ?>
                            <label class="d-block mb-0 fw-semibold" for="vendor-evt-cond-<?php echo (int) $evtId; ?>"><?php echo htmlspecialchars($colEventCondiciones); ?></label>
                            <?php else : ?>
                            <span class="d-block fw-semibold"><?php echo htmlspecialchars($colEventCondiciones); ?></span>
                            <?php endif; ?>
                        </td>
                        <td colspan="5" class="col-evt-cond small p-2 border-top-0 w-100">
                            <?php if ($canAttachVendorQuoteEvent && $evtId > 0) : ?>
                            <textarea name="condiciones_entrega" id="vendor-evt-cond-<?php echo (int) $evtId; ?>" class="form-control" form="vendor-evt-cond-form-<?php echo (int) $evtId; ?>"
                                      rows="2" autocomplete="off"><?php echo htmlspecialchars($evtCondiciones); ?></textarea>
                            <?php else : ?>
                            <div class="form-control-plaintext bg-light px-2 py-2 rounded mb-0 small" style="min-height: calc(2 * 1.25em + 0.75rem); line-height: 1.25;"><?php echo $evtCondiciones !== '' ? nl2br(htmlspecialchars($evtCondiciones, ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">—</span>'; ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="precot-vendor-line-viewer mt-3 pt-3 border-top" id="precot-vendor-line-viewer" aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINE_VIEWER_TITLE')); ?>">
        <h2 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINE_VIEWER_TITLE'); ?></h2>
        <p class="small text-muted mb-2" id="precot-vendor-line-viewer-hint"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINE_VIEWER_HINT'); ?></p>
        <div id="precot-vendor-line-viewer-body"></div>
    </section>
    <script>
    (function() {
        document.addEventListener('change', function(e) {
            var t = e.target;
            if (t && t.classList && t.classList.contains('js-vendor-event-file') && t.files && t.files.length) {
                t.form.submit();
            }
        });
    })();
    </script>
    <script>
    (function() {
        var viewer = document.getElementById('precot-vendor-line-viewer');
        var bodyEl = document.getElementById('precot-vendor-line-viewer-body');
        var hintEl = document.getElementById('precot-vendor-line-viewer-hint');
        if (!viewer || !bodyEl) return;

        var pdfHint = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_PDF_HINT')); ?>;
        var openLabel = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEW')); ?>;
        var fallback = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_FALLBACK')); ?>;
        var viewerTitle = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINE_VIEWER_TITLE')); ?>;

        function escAttr(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;');
        }

        document.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest ? e.target.closest('.js-vendor-line-view') : null;
            if (!btn) return;
            e.preventDefault();
            var url = btn.getAttribute('data-url') || '';
            var ext = (btn.getAttribute('data-ext') || '').toLowerCase();
            if (!url) return;
            if (hintEl) hintEl.classList.add('d-none');
            bodyEl.innerHTML = '';
            var isImg = ext === 'jpg' || ext === 'jpeg' || ext === 'png';
            if (isImg) {
                bodyEl.innerHTML = '<div class="text-center bg-light rounded border p-2">'
                    + '<img src="' + escAttr(url) + '" alt="' + escAttr(viewerTitle) + '" class="img-fluid rounded shadow-sm" style="max-height:85vh;width:auto;">'
                    + '</div>';
                return;
            }
            if (ext === 'pdf') {
                bodyEl.innerHTML = '<div class="border rounded overflow-hidden bg-light shadow-sm">'
                    + '<iframe src="' + escAttr(url) + '#toolbar=1" class="w-100 border-0 d-block" title="' + escAttr(viewerTitle) + '"></iframe>'
                    + '</div>'
                    + '<p class="small text-muted mt-2 mb-0">' + escAttr(pdfHint)
                    + ' <a href="' + escAttr(url) + '" target="_blank" rel="noopener">' + escAttr(openLabel) + '</a></p>';
                return;
            }
            bodyEl.innerHTML = '<p class="small text-muted mb-0">' + escAttr(fallback)
                + ' <a href="' + escAttr(url) + '" target="_blank" rel="noopener">' + escAttr(openLabel) + '</a></p>';
        });
    })();
    </script>
    <?php endif; ?>

    <?php if ($vendorQuoteAttachHref !== '') : ?>
    <section class="precot-vendor-attachment-viewer mt-4 pt-4 border-top" aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_TITLE')); ?>">
        <h2 class="h6 mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_TITLE'); ?></h2>
        <?php if ($vendorAttachIsImage) : ?>
        <div class="text-center bg-light rounded border p-2">
            <img src="<?php echo htmlspecialchars($vendorQuoteAttachHref); ?>"
                 alt="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_TITLE')); ?>"
                 class="img-fluid rounded shadow-sm"
                 style="max-height: 85vh; width: auto;">
        </div>
        <?php elseif ($vendorAttachIsPdf) : ?>
        <div class="border rounded overflow-hidden bg-light shadow-sm">
            <iframe src="<?php echo htmlspecialchars($vendorQuoteAttachHref); ?>#toolbar=1"
                    class="w-100 border-0 d-block"
                    title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_TITLE')); ?>"></iframe>
        </div>
        <p class="small text-muted mt-2 mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_PDF_HINT'); ?>
            <a href="<?php echo htmlspecialchars($vendorQuoteAttachHref); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEW'); ?></a>
        </p>
        <?php else : ?>
        <p class="small text-muted mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEWER_FALLBACK'); ?>
            <a href="<?php echo htmlspecialchars($vendorQuoteAttachHref); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_VIEW'); ?></a>
        </p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
