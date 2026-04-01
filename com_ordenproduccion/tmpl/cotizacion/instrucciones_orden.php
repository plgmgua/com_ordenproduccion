<?php
/**
 * Form to collect "Detalles" (instructions) per line and per concept before generating Orden de Trabajo.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$l = function ($key, $fallbackEn, $fallbackEs = null) {
    $t = Text::_($key);
    if ($t === $key || (is_string($t) && strpos($t, 'COM_ORDENPRODUCCION_') === 0)) {
        $lang = Factory::getApplication()->getLanguage()->getTag();
        return (strpos($lang, 'es') !== false && $fallbackEs !== null) ? $fallbackEs : $fallbackEn;
    }
    return $t;
};

$linesWithConcepts = $this->instruccionesLines ?? [];
$preCotizacionId   = (int) ($this->instruccionesPreCotizacionId ?? 0);
$quotationId       = (int) ($this->instruccionesQuotationId ?? 0);
$quotation         = $this->instruccionesQuotation ?? null;

if ($preCotizacionId < 1 || empty($linesWithConcepts)) {
    echo '<p class="text-muted p-3">' . htmlspecialchars($l('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID', 'Invalid pre-cotización.', 'Pre-cotización no válida.')) . '</p>';
    return;
}

$saveUrl = Route::_('index.php?option=com_ordenproduccion&task=cotizacion.saveInstruccionesOrden');
$ordenUrl = Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&pre_cotizacion_id=' . $preCotizacionId . '&quotation_id=' . $quotationId);
?>
<div class="cotizacion-instrucciones-orden container py-4">
    <h2 class="mb-3">
        <i class="fas fa-clipboard-list"></i>
        <?php echo $l('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_TITLE', 'Instructions for work order', 'Instrucciones para orden de trabajo'); ?>
    </h2>
    <p class="text-muted mb-4">
        <?php echo $l('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_DESC', 'Enter details/instructions for each element. These will be used when creating the work order.', 'Indique los detalles o instrucciones para cada elemento. Se usarán al crear la orden de trabajo.'); ?>
    </p>

    <form action="<?php echo htmlspecialchars($saveUrl); ?>" method="post" id="instrucciones-orden-form" class="form-horizontal">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="pre_cotizacion_id" value="<?php echo $preCotizacionId; ?>">
        <input type="hidden" name="quotation_id" value="<?php echo $quotationId; ?>">

        <?php
        $paperNames = [];
        $sizeNames = [];
        if (!empty($this->pliegoPaperTypes)) {
            foreach ($this->pliegoPaperTypes as $p) {
                $paperNames[(int) $p->id] = $p->name ?? '';
            }
        }
        if (!empty($this->pliegoSizes)) {
            foreach ($this->pliegoSizes as $s) {
                $sizeNames[(int) $s->id] = $s->name ?? '';
            }
        }
        $elementosById = [];
        if (!empty($this->elementos)) {
            foreach ($this->elementos as $el) {
                $elementosById[(int) $el->id] = $el;
            }
        }
        foreach ($linesWithConcepts as $row) :
            $line = $row->line;
            $concepts = $row->concepts;
            $detalles = $row->detalles;
            $lineId = (int) $line->id;
            $lineType = isset($line->line_type) ? (string) $line->line_type : 'pliego';
            $lineLabel = '—';
            if ($lineType === 'envio') {
                $lineLabel = isset($line->envio_name) ? 'Envío: ' . $line->envio_name : 'Envío';
            } elseif ($lineType === 'elementos' && !empty($line->elemento_id) && isset($elementosById[(int) $line->elemento_id])) {
                $lineLabel = $elementosById[(int) $line->elemento_id]->name ?? ('ID ' . $line->elemento_id);
            } else {
                $paperName = $paperNames[$line->paper_type_id ?? 0] ?? ('ID ' . (int) ($line->paper_type_id ?? 0));
                $sizeName = $sizeNames[$line->size_id ?? 0] ?? ('ID ' . (int) ($line->size_id ?? 0));
                $lineLabel = $paperName . ' · ' . $sizeName . ' · ' . (int) $line->quantity;
            }
            $tipoElemento = isset($line->tipo_elemento) && trim((string) $line->tipo_elemento) !== '' ? trim((string) $line->tipo_elemento) : $lineLabel;
        ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <strong><?php echo htmlspecialchars($tipoElemento); ?></strong>
                <span class="text-muted small ms-2"><?php echo $lineLabel !== $tipoElemento ? htmlspecialchars($lineLabel) : ''; ?></span>
            </div>
            <div class="card-body">
                <?php foreach ($concepts as $conceptoKey => $conceptoLabel) :
                    $value = isset($detalles[$conceptoKey]) ? htmlspecialchars((string) $detalles[$conceptoKey], ENT_QUOTES, 'UTF-8') : '';
                    $name = 'detalle[' . $lineId . '][' . htmlspecialchars($conceptoKey, ENT_QUOTES, 'UTF-8') . ']';
                    $id = 'detalle_' . $lineId . '_' . preg_replace('/[^a-z0-9_]/', '_', $conceptoKey);
                ?>
                <div class="mb-3">
                    <label for="<?php echo $id; ?>" class="form-label"><?php echo htmlspecialchars($conceptoLabel); ?></label>
                    <textarea name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="form-control" rows="2"><?php echo $value; ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <?php echo $l('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVE_AND_CONTINUE', 'Save and continue to work order', 'Guardar y continuar a orden de trabajo'); ?>
            </button>
            <a href="<?php echo htmlspecialchars($ordenUrl); ?>" class="btn btn-outline-secondary" target="_blank">
                <?php echo $l('COM_ORDENPRODUCCION_SKIP_AND_GENERAR', 'Skip and open work order', 'Omitir y abrir orden de trabajo'); ?>
            </a>
            <?php if ($quotationId > 0) : ?>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId); ?>" class="btn btn-outline-secondary">
                <?php echo $l('JCANCEL', 'Cancel', 'Cancelar'); ?>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>
