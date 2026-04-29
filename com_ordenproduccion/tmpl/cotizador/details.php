<?php
/**
 * Pre-Cotización details (read-only) for popup: lines table and total.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\Language\Text;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Cotizador\HtmlView $this */

$item  = $this->item ?? null;
$lines = $this->lines ?? [];

$paperNames = [];
$sizeNames = [];
foreach ($this->pliegoPaperTypes ?? [] as $p) {
    $paperNames[(int) $p->id] = $p->name ?? '';
}
foreach ($this->pliegoSizes ?? [] as $s) {
    $sizeNames[(int) $s->id] = $s->name ?? '';
}
$elementosById = [];
foreach ($this->elementos ?? [] as $el) {
    $elementosById[(int) $el->id] = $el;
}
$facturar = !empty($item->facturar);
$hasStoredTotals = isset($item->lines_subtotal) && $item->lines_subtotal !== null && $item->lines_subtotal !== '';
if ($hasStoredTotals) {
    $linesSubtotal = (float) $item->lines_subtotal;
    $margenAmount = (float) ($item->margen_amount ?? 0);
    $ivaAmount = (float) ($item->iva_amount ?? 0);
    $isrAmount = (float) ($item->isr_amount ?? 0);
    $comisionAmount = (float) ($item->comision_amount ?? 0);
    $linesTotal = (float) ($item->total ?? 0);
    $linesTotalFinal = isset($item->total_final) && $item->total_final !== null && $item->total_final !== '' ? (float) $item->total_final : $linesTotal;
} else {
    $linesSubtotal = 0;
    if (!empty($lines)) {
        foreach ($lines as $l) {
            $linesSubtotal += (float) ($l->total ?? 0);
        }
    }
    $paramMargen = isset($this->paramMargen) ? (float) $this->paramMargen : 0;
    $paramIva = isset($this->paramIva) ? (float) $this->paramIva : 0;
    $paramIsr = isset($this->paramIsr) ? (float) $this->paramIsr : 0;
    $paramComision = isset($this->paramComision) ? (float) $this->paramComision : 0;
    $margenAmount = $linesSubtotal * ($paramMargen / 100);
    $ivaAmount = $facturar ? ($linesSubtotal * ($paramIva / 100)) : 0;
    $isrAmount = $facturar ? ($linesSubtotal * ($paramIsr / 100)) : 0;
    $comisionAmount = $linesSubtotal * ($paramComision / 100);
    $linesTotal = $linesSubtotal + $margenAmount + $ivaAmount + $isrAmount + $comisionAmount;
    $linesTotalFinal = $linesTotal;
}
$paramMargen = isset($this->paramMargen) ? (float) $this->paramMargen : 0;
$paramIva = isset($this->paramIva) ? (float) $this->paramIva : 0;
$paramIsr = isset($this->paramIsr) ? (float) $this->paramIsr : 0;
$paramComision = isset($this->paramComision) ? (float) $this->paramComision : 0;
$paramComisionMargenAdicional = isset($this->paramComisionMargenAdicional) ? (float) $this->paramComisionMargenAdicional : 0;
$margenAdicional = ($item && isset($item->margen_adicional) && $item->margen_adicional !== null && $item->margen_adicional !== '') ? (float) $item->margen_adicional : 0;
$comisionMargenAdicionalAmount = ($item && isset($item->comision_margen_adicional) && $item->comision_margen_adicional !== null && $item->comision_margen_adicional !== '') ? (float) $item->comision_margen_adicional : 0;
$displayTotal = $linesTotalFinal + $margenAdicional;
$tcCuotasSel = 0;
$tcMonto = 0.0;
$tcTasa = 0.0;
$totalConTarjeta = null;
if ($item) {
    $tcCuotasSel = isset($item->tarjeta_credito_cuotas) && $item->tarjeta_credito_cuotas !== null && $item->tarjeta_credito_cuotas !== ''
        ? (int) $item->tarjeta_credito_cuotas
        : 0;
    $tcMonto = isset($item->tarjeta_credito_monto) && $item->tarjeta_credito_monto !== null && $item->tarjeta_credito_monto !== ''
        ? (float) $item->tarjeta_credito_monto
        : 0.0;
    $tcTasa = isset($item->tarjeta_credito_tasa) && $item->tarjeta_credito_tasa !== null && $item->tarjeta_credito_tasa !== ''
        ? (float) $item->tarjeta_credito_tasa
        : 0.0;
    $totalConTarjeta = isset($item->total_con_tarjeta) && $item->total_con_tarjeta !== null && $item->total_con_tarjeta !== ''
        ? (float) $item->total_con_tarjeta
        : null;
}
$canSeePrecotInternalTax = AccessHelper::canSeePrecotizacionInternalTaxBreakdown();

$precotFooterShowIva = $canSeePrecotInternalTax && $facturar
    && (($paramIva != 0) || abs((float) $ivaAmount) >= 0.005);
$precotFooterShowIsr = $canSeePrecotInternalTax && $facturar
    && (($paramIsr != 0) || abs((float) $isrAmount) >= 0.005);

$docMode = isset($item->document_mode) ? (string) $item->document_mode : 'pliego';
$isProveedorExternoDoc = ($docMode === 'proveedor_externo');
$vendorLines           = [];
if ($isProveedorExternoDoc) {
    foreach ($lines as $ln) {
        $lt = isset($ln->line_type) ? (string) $ln->line_type : 'pliego';
        if ($lt === 'proveedor_externo') {
            $vendorLines[] = $ln;
        }
    }
}

$colQtyPe   = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_QTY');
$colDescPe  = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_DESC');
$colPricePe = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_PRICE_SHORT');
if (strpos($colPricePe, 'COM_ORDENPRODUCCION_') === 0) {
    $colPricePe = 'Precio unidad';
}
$colPupPe = Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_COL_P_UNIT_PROVEEDOR');
if (strpos($colPupPe, 'COM_ORDENPRODUCCION_') === 0) {
    $colPupPe = 'P.Unit Proveedor';
}
?>
<div class="com-ordenproduccion-precotizacion-details p-3">
    <?php if (!$item) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'); ?></p>
    <?php elseif ($isProveedorExternoDoc && $vendorLines === []) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <p class="mb-0 text-end"><strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?>:</strong> Q 0.00 &rarr; <strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?>:</strong> Q 0.00</p>
    <?php elseif ($isProveedorExternoDoc) :
        /** Five columns (Cant., Desc., Precio unidad, P.Unit Prov., Total) — matches document proveedor_externo. */
        $tfootLabelSpanPe = 4;
        ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm com-ordenproduccion-precot-details-vendor">
                <thead>
                    <tr>
                        <th class="text-nowrap"><?php echo htmlspecialchars($colQtyPe); ?></th>
                        <th><?php echo htmlspecialchars($colDescPe); ?></th>
                        <th class="text-end text-nowrap"><?php echo htmlspecialchars($colPricePe); ?></th>
                        <th class="text-end text-nowrap"><?php echo htmlspecialchars($colPupPe); ?></th>
                        <th class="text-end text-nowrap"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendorLines as $line) :
                        $qty  = (int) ($line->quantity ?? 1);
                        $desc = (string) ($line->vendor_descripcion ?? '');
                        $unit = round((float) ($line->price_per_sheet ?? 0), 2);
                        $pup  = round((float) ($line->vendor_precio_unit_proveedor ?? 0), 2);
                        $tot  = round((float) ($line->total ?? 0), 2);
                        ?>
                    <tr>
                        <td class="text-nowrap"><?php echo $qty; ?></td>
                        <td><?php echo nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')); ?></td>
                        <td class="text-end text-nowrap">Q <?php echo number_format($unit, 2); ?></td>
                        <td class="text-end text-nowrap">Q <?php echo number_format($pup, 2); ?></td>
                        <td class="text-end text-nowrap">Q <?php echo number_format($tot, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($linesSubtotal, 2); ?></td>
                    </tr>
                    <?php if ($canSeePrecotInternalTax && $paramMargen != 0) : ?>
                    <?php $margenTotal = $margenAmount + $margenAdicional; ?>
                    <tr class="margen-total-row">
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end">(<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_TOTAL'); ?> Q <?php echo number_format($margenTotal, 2); ?>) <?php echo Text::_('COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA'); ?> (<?php echo number_format($paramMargen, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($margenAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($precotFooterShowIva) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_IVA'); ?><?php echo $paramIva != 0 ? ' (' . number_format($paramIva, 1) . '%)' : ''; ?></td>
                        <td class="text-end">Q <?php echo number_format($ivaAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($precotFooterShowIsr) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_ISR'); ?><?php echo $paramIsr != 0 ? ' (' . number_format($paramIsr, 1) . '%)' : ''; ?></td>
                        <td class="text-end">Q <?php echo number_format($isrAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($paramComision != 0) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA'); ?> (<?php echo number_format($paramComision, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($comisionAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($margenAdicional > 0) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_ADICIONAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($margenAdicional, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-secondary fw-bold">
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($displayTotal, 2); ?></td>
                    </tr>
                    <?php if ($tcCuotasSel > 0 && $totalConTarjeta !== null && $totalConTarjeta > 0) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end">
                            <?php echo Text::sprintf('COM_ORDENPRODUCCION_PRE_COTIZACION_TARJETA_CARGO_ROW', $tcCuotasSel, number_format($tcTasa, 2)); ?>
                        </td>
                        <td class="text-end">Q <?php echo number_format($tcMonto, 2); ?></td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL_CON_TARJETA'); ?></td>
                        <td class="text-end">Q <?php echo number_format($totalConTarjeta, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($comisionMargenAdicionalAmount > 0) : ?>
                    <?php $totalComision = $comisionAmount + $comisionMargenAdicionalAmount; ?>
                    <tr class="comision-margen-adicional-row">
                        <td colspan="<?php echo $tfootLabelSpanPe; ?>" class="text-end">(<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL_COMISION'); ?> Q <?php echo number_format($totalComision, 2); ?>) <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COMISION_MARGEN_ADICIONAL'); ?> (<?php echo number_format($paramComisionMargenAdicional, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($comisionMargenAdicionalAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
        <style>
        .comision-margen-adicional-row td { background-color: #e7f1ff !important; color: #004085; font-weight: 500; }
        .margen-total-row td { background-color: #d4edda !important; color: #155724; font-weight: 500; }
        </style>
    <?php elseif (empty($lines)) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <p class="mb-0 text-end"><strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?>:</strong> Q 0.00 &rarr; <strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?>:</strong> Q 0.00</p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TIPO_ELEMENTO'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_QUANTITY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COL_ELEMENTO'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_SIZE'); ?></th>
                        <th>Tiro/Retiro</th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line) :
                        $isElemento = isset($line->line_type) && $line->line_type === 'elementos' && !empty($line->elemento_id);
                        if ($isElemento && isset($elementosById[(int) $line->elemento_id])) {
                            $el = $elementosById[(int) $line->elemento_id];
                            $paperName = $el->name ?? '';
                            $sizeName = $el->size ?? '—';
                        } else {
                            $paperName = $paperNames[$line->paper_type_id ?? 0] ?? ('ID ' . (int) ($line->paper_type_id ?? 0));
                            $sizeName = $sizeNames[$line->size_id ?? 0] ?? ('ID ' . (int) ($line->size_id ?? 0));
                        }
                    $tipoElementoDetail = isset($line->tipo_elemento) && trim((string) $line->tipo_elemento) !== '' ? trim((string) $line->tipo_elemento) : '—';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tipoElementoDetail); ?></td>
                            <td><?php echo (int) $line->quantity; ?></td>
                            <td><?php echo $isElemento ? htmlspecialchars($paperName) : htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FOLIOS_PREFIX') . ' ' . $paperName); ?></td>
                            <td><?php echo htmlspecialchars($sizeName); ?></td>
                            <td><?php echo $isElemento ? '—' : (($line->tiro_retiro ?? '') === 'retiro' ? 'Tiro/Retiro' : 'Tiro'); ?></td>
                            <td class="text-end">Q <?php echo number_format((float) $line->total, 2); ?></td>
                        </tr>
                        <?php if (!$isElemento) :
                            $breakdown = $line->breakdown ?? [];
                        ?>
                        <tr class="line-detail-row">
                            <td colspan="6" class="p-0 bg-light align-top">
                                <div class="p-2">
                                    <table class="table table-sm table-bordered mb-0" style="max-width: 700px;">
                                        <?php if ($canSeePrecotInternalTax) : ?>
                                        <thead>
                                            <tr>
                                                <th><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_ITEM'); ?></th>
                                                <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_DETAIL'); ?></th>
                                                <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_SUBTOTAL'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($breakdown as $row) :
                                                $label = isset($row['label']) ? htmlspecialchars($row['label']) : '';
                                                $detail = isset($row['detail']) ? htmlspecialchars($row['detail']) : '';
                                                $subtotal = isset($row['subtotal']) ? number_format((float) $row['subtotal'], 2) : '0.00';
                                            ?>
                                                <tr>
                                                    <td><?php echo $label; ?></td>
                                                    <td class="text-end"><?php echo $detail; ?></td>
                                                    <td class="text-end">Q <?php echo $subtotal; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-secondary fw-bold">
                                                <td colspan="2"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_TOTAL'); ?></td>
                                                <td class="text-end">Q <?php echo number_format((float) $line->total, 2); ?></td>
                                            </tr>
                                        </tfoot>
                                        <?php else : ?>
                                        <thead>
                                            <tr>
                                                <th><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_ITEM'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($breakdown as $row) :
                                                $label = isset($row['label']) ? htmlspecialchars($row['label']) : '';
                                            ?>
                                                <tr>
                                                    <td><?php echo $label; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-secondary fw-bold">
                                                <td><?php echo Text::_('COM_ORDENPRODUCCION_CALC_TOTAL'); ?></td>
                                            </tr>
                                        </tfoot>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php
                    /** Six columns: label cells span the first five, amount in the last. */
                    $tfootLabelSpan = 5;
                    ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($linesSubtotal, 2); ?></td>
                    </tr>
                    <?php if ($canSeePrecotInternalTax && $paramMargen != 0) : ?>
                    <?php $margenTotal = $margenAmount + $margenAdicional; ?>
                    <tr class="margen-total-row">
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end">(<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_TOTAL'); ?> Q <?php echo number_format($margenTotal, 2); ?>) <?php echo Text::_('COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA'); ?> (<?php echo number_format($paramMargen, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($margenAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($precotFooterShowIva) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_IVA'); ?><?php echo $paramIva != 0 ? ' (' . number_format($paramIva, 1) . '%)' : ''; ?></td>
                        <td class="text-end">Q <?php echo number_format($ivaAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($precotFooterShowIsr) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_ISR'); ?><?php echo $paramIsr != 0 ? ' (' . number_format($paramIsr, 1) . '%)' : ''; ?></td>
                        <td class="text-end">Q <?php echo number_format($isrAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($paramComision != 0) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA'); ?> (<?php echo number_format($paramComision, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($comisionAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($margenAdicional > 0) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_ADICIONAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($margenAdicional, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-secondary fw-bold">
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($displayTotal, 2); ?></td>
                    </tr>
                    <?php if ($tcCuotasSel > 0 && $totalConTarjeta !== null && $totalConTarjeta > 0) : ?>
                    <tr>
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end">
                            <?php echo Text::sprintf('COM_ORDENPRODUCCION_PRE_COTIZACION_TARJETA_CARGO_ROW', $tcCuotasSel, number_format($tcTasa, 2)); ?>
                        </td>
                        <td class="text-end">Q <?php echo number_format($tcMonto, 2); ?></td>
                    </tr>
                    <tr class="table-primary fw-bold">
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL_CON_TARJETA'); ?></td>
                        <td class="text-end">Q <?php echo number_format($totalConTarjeta, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($comisionMargenAdicionalAmount > 0) : ?>
                    <?php $totalComision = $comisionAmount + $comisionMargenAdicionalAmount; ?>
                    <tr class="comision-margen-adicional-row">
                        <td colspan="<?php echo $tfootLabelSpan; ?>" class="text-end">(<?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL_COMISION'); ?> Q <?php echo number_format($totalComision, 2); ?>) <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COMISION_MARGEN_ADICIONAL'); ?> (<?php echo number_format($paramComisionMargenAdicional, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($comisionMargenAdicionalAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
        <style>
        .comision-margen-adicional-row td { background-color: #e7f1ff !important; color: #004085; font-weight: 500; }
        .margen-total-row td { background-color: #d4edda !important; color: #155724; font-weight: 500; }
        </style>
    <?php endif; ?>
</div>
