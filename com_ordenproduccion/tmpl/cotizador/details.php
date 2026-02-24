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
$linesTotal = 0;
if (!empty($lines)) {
    foreach ($lines as $l) {
        $linesTotal += (float) ($l->total ?? 0);
    }
}
?>
<div class="com-ordenproduccion-precotizacion-details p-3">
    <?php if (!$item) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'); ?></p>
    <?php elseif (empty($lines)) : ?>
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <p class="mb-0 text-end"><strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?>:</strong> Q <?php echo number_format(0, 2); ?></p>
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
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary fw-bold">
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($linesTotal, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
