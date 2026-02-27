<?php
/**
 * Pre-Cotización details (read-only) for popup: lines table and total.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

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
$linesSubtotal = 0;
if (!empty($lines)) {
    foreach ($lines as $l) {
        $lineType = isset($l->line_type) ? (string) $l->line_type : 'pliego';
        if ($lineType !== 'envio') {
            $linesSubtotal += (float) ($l->total ?? 0);
        }
    }
}
$facturar = !empty($item->facturar);
$paramMargen = isset($this->paramMargen) ? (float) $this->paramMargen : 0;
$paramIva = isset($this->paramIva) ? (float) $this->paramIva : 0;
$paramIsr = isset($this->paramIsr) ? (float) $this->paramIsr : 0;
$paramComision = isset($this->paramComision) ? (float) $this->paramComision : 0;
$margenAmount = $linesSubtotal * ($paramMargen / 100);
$ivaAmount = $facturar ? ($linesSubtotal * ($paramIva / 100)) : 0;
$isrAmount = $facturar ? ($linesSubtotal * ($paramIsr / 100)) : 0;
$comisionAmount = $linesSubtotal * ($paramComision / 100);
$linesTotal = $linesSubtotal + $margenAmount + $ivaAmount + $isrAmount + $comisionAmount;
?>
<div class="com-ordenproduccion-precotizacion-details p-3">
    <?php if (!$item) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'); ?></p>
    <?php elseif (empty($lines)) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <p class="mb-0 text-end"><strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?>:</strong> Q 0.00 &rarr; <strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?>:</strong> Q 0.00</p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
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
                    ?>
                        <tr>
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
                            <td colspan="5" class="p-0 bg-light align-top">
                                <div class="p-2">
                                    <table class="table table-sm table-bordered mb-0" style="max-width: 600px;">
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
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SUBTOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($linesSubtotal, 2); ?></td>
                    </tr>
                    <?php if ($paramMargen != 0) : ?>
                    <tr>
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA'); ?> (<?php echo number_format($paramMargen, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($margenAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($facturar && $paramIva != 0) : ?>
                    <tr>
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_IVA'); ?> (<?php echo number_format($paramIva, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($ivaAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($facturar && $paramIsr != 0) : ?>
                    <tr>
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_ISR'); ?> (<?php echo number_format($paramIsr, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($isrAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($paramComision != 0) : ?>
                    <tr>
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_COMISION_VENTA'); ?> (<?php echo number_format($paramComision, 1); ?>%)</td>
                        <td class="text-end">Q <?php echo number_format($comisionAmount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-secondary fw-bold">
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($linesTotal, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
