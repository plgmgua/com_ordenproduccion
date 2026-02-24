<?php
/**
 * Pre-Cotización document: header, lines table, "Cálculo de Folios" and "Otros Elementos" modals, Total.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Cotizador\HtmlView $this */

$item  = $this->item ?? null;
$lines = $this->lines ?? [];
$listUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizador');
$addLineTask = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.addLine');
$editLineTask = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.editLine');
$token = Session::getFormToken();

if (!$item) {
    return;
}

$preCotizacionId = (int) $item->id;
$precotizacionLocked = !empty($this->precotizacionLocked);
$paperNames = [];
$sizeNames = [];
foreach ($this->pliegoPaperTypes ?? [] as $p) {
    $paperNames[(int) $p->id] = $p->name ?? '';
}
foreach ($this->pliegoSizes ?? [] as $s) {
    $sizeNames[(int) $s->id] = $s->name ?? '';
}

$baseUrl = Route::_('index.php?option=com_ordenproduccion&task=cotizacion.calculatePliegoPrice&format=raw');
$sizes = $this->pliegoSizes ?? [];
$paperTypes = $this->pliegoPaperTypes ?? [];
$sizeIdsByPaperType = $this->pliegoSizeIdsByPaperType ?? [];
$laminationTypeIdsBySizeTiro = $this->pliegoLaminationTypeIdsBySizeTiro ?? [];
$laminationTypeIdsBySizeRetiro = $this->pliegoLaminationTypeIdsBySizeRetiro ?? [];
$laminationTypes = $this->pliegoLaminationTypes ?? [];
$processes = $this->pliegoProcesses ?? [];
$tablesExist = $this->pliegoTablesExist ?? false;
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
// Labels for add-line buttons (fallback if lang key missing or old "Nueva Línea" override)
$labelCalculoFolios = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CALCULO_FOLIOS');
if ($labelCalculoFolios === 'COM_ORDENPRODUCCION_PRE_COTIZACION_CALCULO_FOLIOS' || $labelCalculoFolios === 'COM_ORDENPRODUCCION_PRE_COTIZACION_NUEVA_LINEA' || $labelCalculoFolios === 'New Line' || $labelCalculoFolios === 'Nueva Línea') {
    $labelCalculoFolios = 'Cálculo de Folios';
}
$labelOtrosElementos = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OTROS_ELEMENTOS');
if ($labelOtrosElementos === 'COM_ORDENPRODUCCION_PRE_COTIZACION_OTROS_ELEMENTOS' || $labelOtrosElementos === 'Other elements') {
    $labelOtrosElementos = 'Otros Elementos';
}
?>

<div class="com-ordenproduccion-precotizacion-document container py-4">
    <nav class="mb-3">
        <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BACK'); ?></a>
    </nav>

    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE'); ?> <?php echo htmlspecialchars($item->number); ?></h1>

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
        if (strpos($msgLocked, 'COM_ORDENPRODUCCION_') === 0) {
            $msgLocked = 'This pre-quote is linked to a quotation and cannot be modified.';
        }
    ?>
    <div class="alert alert-info mb-3"><?php echo htmlspecialchars($msgLocked); ?></div>
    <?php endif; ?>

    <?php
    $labelDescripcion = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION');
    if (strpos($labelDescripcion, 'COM_ORDENPRODUCCION_') === 0) {
        $labelDescripcion = 'Descripción';
    }
    $descripcionValue = isset($item->descripcion) ? (string) $item->descripcion : '';
    $saveDescripcionUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.saveDescripcion');
    ?>
    <div class="precotizacion-descripcion mb-4">
        <label class="form-label fw-bold"><?php echo htmlspecialchars($labelDescripcion); ?></label>
        <?php if ($precotizacionLocked) : ?>
            <div class="form-control-plaintext bg-light p-3 rounded"><?php echo $descripcionValue !== '' ? nl2br(htmlspecialchars($descripcionValue)) : '<span class="text-muted">—</span>'; ?></div>
        <?php else : ?>
        <form action="<?php echo htmlspecialchars($saveDescripcionUrl); ?>" method="post" class="mb-2">
            <input type="hidden" name="id" value="<?php echo (int) $preCotizacionId; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <textarea id="precotizacion-descripcion" name="descripcion" class="form-control" rows="4" placeholder="<?php echo htmlspecialchars($labelDescripcion); ?>"><?php echo htmlspecialchars($descripcionValue); ?></textarea>
            <button type="submit" class="btn btn-secondary mt-2"><?php echo Text::_('JSAVE'); ?></button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!$precotizacionLocked) : ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pliegoLineModal">
            <?php echo htmlspecialchars($labelCalculoFolios); ?>
        </button>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#elementosLineModal">
            <?php echo htmlspecialchars($labelOtrosElementos); ?>
        </button>
    </div>
    <?php endif; ?>

    <h2 class="h5 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINES'); ?></h2>

    <?php if (empty($lines)) : ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
        <p class="mb-0 text-end"><strong><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?>:</strong> Q <?php echo number_format(0, 2); ?></p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_QUANTITY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COL_ELEMENTO'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_SIZE'); ?></th>
                        <th>Tiro/Retiro</th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line) :
                        $deleteLineUrl = 'index.php?option=com_ordenproduccion&task=precotizacion.deleteLine&line_id=' . (int) $line->id . '&id=' . $preCotizacionId;
                        $isElemento = isset($line->line_type) && $line->line_type === 'elementos' && !empty($line->elemento_id);
                        if ($isElemento && isset($elementosById[(int) $line->elemento_id])) {
                            $el = $elementosById[(int) $line->elemento_id];
                            $paperName = $el->name ?? '';
                            $sizeName = $el->size ?? '—';
                        } else {
                            $paperName = $paperNames[$line->paper_type_id ?? 0] ?? ('ID ' . (int) $line->paper_type_id);
                            $sizeName = $sizeNames[$line->size_id ?? 0] ?? ('ID ' . (int) $line->size_id);
                        }
                        $lineJson = htmlspecialchars(json_encode([
                            'id' => (int) $line->id,
                            'quantity' => (int) $line->quantity,
                            'paper_type_id' => (int) $line->paper_type_id,
                            'size_id' => (int) $line->size_id,
                            'tiro_retiro' => $line->tiro_retiro ?? 'tiro',
                            'lamination_type_id' => $line->lamination_type_id ? (int) $line->lamination_type_id : null,
                            'lamination_tiro_retiro' => $line->lamination_tiro_retiro ?? 'tiro',
                            'process_ids' => $line->process_ids_array ?? [],
                            'price_per_sheet' => (float) $line->price_per_sheet,
                            'total' => (float) $line->total,
                            'breakdown' => $line->breakdown ?? [],
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="line-data-row">
                            <td><?php echo (int) $line->quantity; ?></td>
                            <td><?php echo $isElemento ? htmlspecialchars($paperName) : htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FOLIOS_PREFIX') . ' ' . $paperName); ?></td>
                            <td><?php echo htmlspecialchars($sizeName); ?></td>
                            <td><?php echo $isElemento ? '—' : (($line->tiro_retiro ?? '') === 'retiro' ? 'Tiro/Retiro' : 'Tiro'); ?></td>
                            <td class="text-end">Q <?php echo number_format((float) $line->total, 2); ?></td>
                            <td class="text-end">
                                <?php if (!$isElemento) : ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary toggle-line-detail" data-detail-id="line-detail-<?php echo (int) $line->id; ?>" aria-expanded="false">
                                    <span class="toggle-detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_VER_DETALLE'); ?></span>
                                </button>
                                <?php endif; ?>
                                <?php if (!$precotizacionLocked) : ?>
                                    <?php if (!$isElemento) : ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary pliego-edit-line-btn" data-line="<?php echo $lineJson; ?>">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_EDIT_LINE'); ?>
                                    </button>
                                    <?php endif; ?>
                                    <form action="<?php echo Route::_($deleteLineUrl); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE_LINE')); ?>');">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('JACTION_DELETE'); ?></button>
                                    </form>
                                <?php elseif ($isElemento) : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!$isElemento) : ?>
                        <tr id="line-detail-<?php echo (int) $line->id; ?>" class="line-detail-row" style="display:none;">
                            <td colspan="6" class="p-0 bg-light align-top">
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
                                            <?php
                                            $breakdown = $line->breakdown ?? [];
                                            foreach ($breakdown as $row) :
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
                    <tr class="table-secondary fw-bold">
                        <td colspan="4" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TOTAL'); ?></td>
                        <td class="text-end">Q <?php echo number_format($linesTotal, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($lines)) : ?>
<script>
(function() {
    var verDetalle = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_VER_DETALLE')); ?>;
    var ocultarDetalle = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OCULTAR_DETALLE')); ?>;
    document.addEventListener('click', function(e) {
        var btn = e.target && e.target.closest && e.target.closest('.toggle-line-detail');
        if (!btn) return;
        var id = btn.getAttribute('data-detail-id');
        if (!id) return;
        var row = document.getElementById(id);
        var label = btn.querySelector('.toggle-detail-label');
        if (!row || !label) return;
        var isHidden = row.style.display === 'none';
        row.style.display = isHidden ? 'table-row' : 'none';
        btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        label.textContent = isHidden ? ocultarDetalle : verDetalle;
    });
})();
</script>
<?php endif; ?>

<?php if ($tablesExist) : ?>
<!-- Modal: Cálculo de Folios (Pliego form) -->
<div class="modal fade" id="pliegoLineModal" tabindex="-1" aria-labelledby="pliegoLineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pliegoLineModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_NUEVA_COTIZACION_PLIEGO_TITLE'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="pliego-line-form" method="post" action="<?php echo $addLineTask; ?>" data-add-action="<?php echo htmlspecialchars($addLineTask); ?>" data-edit-action="<?php echo htmlspecialchars($editLineTask); ?>">
                    <input type="hidden" name="<?php echo $token; ?>" value="1">
                    <input type="hidden" name="pre_cotizacion_id" value="<?php echo $preCotizacionId; ?>">
                    <input type="hidden" name="line_id" id="pliego_modal_line_id" value="">
                    <input type="hidden" name="price_per_sheet" id="pliego_modal_price_per_sheet" value="">
                    <input type="hidden" name="total" id="pliego_modal_total" value="">
                    <input type="hidden" name="calculation_breakdown" id="pliego_modal_breakdown" value="">

                    <div class="row mb-2">
                        <div class="col-md-4">
                            <label for="pliego_modal_quantity" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_QUANTITY'); ?></label>
                            <input type="number" id="pliego_modal_quantity" name="quantity" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="pliego_modal_paper_type" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_PAPER_TYPE'); ?></label>
                            <select id="pliego_modal_paper_type" name="paper_type_id" class="form-select" required>
                                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_PAPER_TYPE'); ?></option>
                                <?php foreach ($paperTypes as $p) : ?>
                                    <option value="<?php echo (int) $p->id; ?>"><?php echo htmlspecialchars($p->name ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="pliego_modal_size" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_SIZE'); ?></label>
                            <select id="pliego_modal_size" name="size_id" class="form-select" required>
                                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_SIZE'); ?></option>
                                <?php foreach ($sizes as $s) : ?>
                                    <option value="<?php echo (int) $s->id; ?>"><?php echo htmlspecialchars($s->name ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" id="pliego_modal_retiro" name="tiro_retiro" value="retiro" class="form-check-input">
                                <label class="form-check-label" for="pliego_modal_retiro"><?php echo Text::_('COM_ORDENPRODUCCION_TIRO_RETIRO'); ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" id="pliego_modal_needs_lamination" name="needs_lamination" value="1" class="form-check-input">
                                <label class="form-check-label" for="pliego_modal_needs_lamination"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_NEEDS_LAMINATION'); ?></label>
                            </div>
                        </div>
                        <div class="col-md-6" id="pliego_modal_lamination_wrap" style="display:none;">
                            <label for="pliego_modal_lamination_type" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_LAMINATION_TYPE'); ?></label>
                            <select id="pliego_modal_lamination_type" name="lamination_type_id" class="form-select">
                                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_LAMINATION'); ?></option>
                                <?php foreach ($laminationTypes as $l) : ?>
                                    <option value="<?php echo (int) $l->id; ?>"><?php echo htmlspecialchars($l->name ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-check mt-1">
                                <input type="checkbox" id="pliego_modal_lamination_retiro" name="lamination_tiro_retiro" value="retiro" class="form-check-input">
                                <label class="form-check-label" for="pliego_modal_lamination_retiro"><?php echo Text::_('COM_ORDENPRODUCCION_LAMINATION_TIRO_RETIRO'); ?></label>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($processes)) : ?>
                    <div class="mb-2">
                        <label class="form-label mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_ADDITIONAL_PROCESSES'); ?></label>
                        <div class="row g-1">
                            <?php foreach ($processes as $pr) : ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="process_ids[]" value="<?php echo (int) $pr->id; ?>" id="pliego_modal_proc_<?php echo (int) $pr->id; ?>" class="form-check-input pliego-modal-process-cb">
                                        <label class="form-check-label" for="pliego_modal_proc_<?php echo (int) $pr->id; ?>"><?php echo htmlspecialchars($pr->name ?? ''); ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="border-top pt-2 mt-2">
                        <p class="mb-1"><strong><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_PRICE_PER_PLIEGO'); ?>:</strong> <span id="pliego_modal_price_display">-</span></p>
                        <p class="mb-1"><strong><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_TOTAL'); ?>:</strong> <span id="pliego_modal_total_display">-</span></p>
                        <div id="pliego_modal_calc_detail" class="mt-2 small" style="display:none;">
                            <strong><?php echo Text::_('COM_ORDENPRODUCCION_CALC_DETAIL'); ?></strong>
                            <div class="table-responsive mt-1">
                                <table class="table table-sm table-bordered mb-1" id="pliego_modal_calc_table">
                                    <thead><tr><th><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_ITEM'); ?></th><th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_DETAIL'); ?></th><th class="text-end" style="min-width:6em;"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_SUBTOTAL'); ?></th></tr></thead>
                                    <tbody id="pliego_modal_calc_body"></tbody>
                                    <tfoot><tr class="table-secondary fw-bold"><td colspan="2"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_TOTAL'); ?></td><td class="text-end" id="pliego_modal_calc_total_cell">—</td></tr></tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-primary" id="pliego_modal_submit_btn" disabled><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ADD_LINE_BTN'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Otros Elementos (always shown so "Otros Elementos" button always works) -->
<div class="modal fade" id="elementosLineModal" tabindex="-1" aria-labelledby="elementosLineModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="elementosLineModalLabel"><?php echo htmlspecialchars($labelOtrosElementos); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=precotizacion.addLineElemento'); ?>" method="post" id="elementos-line-form">
                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="pre_cotizacion_id" value="<?php echo $preCotizacionId; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="elementos_modal_elemento_id" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ELEMENTO_LINE'); ?></label>
                        <select name="elemento_id" id="elementos_modal_elemento_id" class="form-select" required>
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SELECT_ELEMENTO'); ?></option>
                            <?php foreach ($this->elementos ?? [] as $el) : ?>
                                <option value="<?php echo (int) $el->id; ?>"><?php echo htmlspecialchars($el->name ?? ''); ?><?php echo ($el->size ?? '') !== '' ? ' (' . htmlspecialchars($el->size) . ')' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="elementos_modal_quantity" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_QUANTITY'); ?></label>
                        <input type="number" name="quantity" id="elementos_modal_quantity" class="form-control" min="1" value="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ADD_ELEMENTO_LINE'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($tablesExist) : ?>
<script>
(function() {
    var baseUrl = <?php echo json_encode($baseUrl); ?>;
    var token = <?php echo json_encode($token); ?>;
    var sizeIdsByPaperType = <?php echo json_encode($sizeIdsByPaperType); ?>;
    var laminationTypeIdsBySizeTiro = <?php echo json_encode($laminationTypeIdsBySizeTiro); ?>;
    var laminationTypeIdsBySizeRetiro = <?php echo json_encode($laminationTypeIdsBySizeRetiro); ?>;
    var addLineBtnLabel = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ADD_LINE_BTN')); ?>;
    var saveLineBtnLabel = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SAVE_LINE_BTN')); ?>;
    var modalTitleNew = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_NUEVA_COTIZACION_PLIEGO_TITLE')); ?>;
    var modalTitleEdit = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_EDIT_LINE')); ?>;

    var form = document.getElementById('pliego-line-form');
    var qty = document.getElementById('pliego_modal_quantity');
    var paper = document.getElementById('pliego_modal_paper_type');
    var size = document.getElementById('pliego_modal_size');
    var retiro = document.getElementById('pliego_modal_retiro');
    var lamination = document.getElementById('pliego_modal_needs_lamination');
    var laminationRetiro = document.getElementById('pliego_modal_lamination_retiro');
    var laminationType = document.getElementById('pliego_modal_lamination_type');
    var laminationWrap = document.getElementById('pliego_modal_lamination_wrap');
    var submitBtn = document.getElementById('pliego_modal_submit_btn');
    var lineIdInput = document.getElementById('pliego_modal_line_id');
    var calcDetail = document.getElementById('pliego_modal_calc_detail');
    var calcBody = document.getElementById('pliego_modal_calc_body');
    var calcTotalCell = document.getElementById('pliego_modal_calc_total_cell');
    var modalTitle = document.getElementById('pliegoLineModalLabel');
    var pliegoModal = document.getElementById('pliegoLineModal');

    var lastCalc = null;

    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }
    function renderBreakdownTable(rows, totalVal) {
        if (!calcDetail || !calcBody || !calcTotalCell) return;
        if (!rows || rows.length === 0) {
            calcDetail.style.display = 'none';
            return;
        }
        calcBody.innerHTML = rows.map(function(row) {
            return '<tr><td>' + escapeHtml(row.label) + '</td><td class="text-end">' + escapeHtml(row.detail) + '</td><td class="text-end">Q ' + Number(row.subtotal).toFixed(2) + '</td></tr>';
        }).join('');
        calcTotalCell.textContent = totalVal != null ? 'Q ' + Number(totalVal).toFixed(2) : '—';
        calcDetail.style.display = 'block';
    }

    function filterSizeDropdown() {
        var paperId = paper && paper.value ? parseInt(paper.value, 10) : 0;
        var allowedIds = (paperId && sizeIdsByPaperType[paperId]) ? sizeIdsByPaperType[paperId] : [];
        var options = size ? size.querySelectorAll('option') : [];
        var currentVal = size ? size.value : '';
        for (var i = 0; i < options.length; i++) {
            var opt = options[i];
            var vid = opt.value ? parseInt(opt.value, 10) : 0;
            if (vid === 0) { opt.disabled = false; opt.style.display = ''; continue; }
            var show = paperId && allowedIds.indexOf(vid) !== -1;
            opt.style.display = show ? '' : 'none';
            opt.disabled = !show;
            if (show && opt.value === currentVal) ; else if (currentVal && !show) size.value = '';
        }
        recalc();
    }
    if (paper) paper.addEventListener('change', filterSizeDropdown);

    function filterLaminationBySize() {
        var sizeId = size && size.value ? parseInt(size.value, 10) : 0;
        var lamRetiro = laminationRetiro && laminationRetiro.checked;
        var map = lamRetiro ? laminationTypeIdsBySizeRetiro : laminationTypeIdsBySizeTiro;
        var allowedLamIds = (sizeId && map[sizeId]) ? map[sizeId] : [];
        if (lamination) {
            lamination.disabled = allowedLamIds.length === 0;
            if (allowedLamIds.length === 0 && lamination.checked) { lamination.checked = false; if (laminationType) laminationType.value = ''; laminationWrap.style.display = 'none'; }
        }
        var options = laminationType ? laminationType.querySelectorAll('option') : [];
        for (var j = 0; j < options.length; j++) {
            var opt = options[j];
            var lid = opt.value ? parseInt(opt.value, 10) : 0;
            if (lid === 0) { opt.disabled = false; opt.style.display = ''; continue; }
            var show = allowedLamIds.indexOf(lid) !== -1;
            opt.style.display = show ? '' : 'none';
            opt.disabled = !show;
        }
        recalc();
    }
    function updateLaminationVisibility() {
        laminationWrap.style.display = lamination && lamination.checked ? 'block' : 'none';
    }
    if (lamination) lamination.addEventListener('change', updateLaminationVisibility);
    if (size) size.addEventListener('change', filterLaminationBySize);
    if (laminationRetiro) laminationRetiro.addEventListener('change', function() { filterLaminationBySize(); recalc(); });

    function recalc() {
        var quantity = parseInt(qty.value, 10) || 0;
        var paperId = paper && paper.value ? paper.value : '';
        var sizeId = size && size.value ? size.value : '';
        if (!quantity || !paperId || !sizeId) {
            document.getElementById('pliego_modal_price_display').textContent = '-';
            document.getElementById('pliego_modal_total_display').textContent = '-';
            lastCalc = null;
            if (calcDetail) calcDetail.style.display = 'none';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }
        var tiroRetiro = (retiro && retiro.checked) ? 'retiro' : 'tiro';
        var lamTiroRetiro = (laminationRetiro && laminationRetiro.checked) ? 'retiro' : 'tiro';
        var lamId = (lamination && lamination.checked && laminationType && laminationType.value) ? laminationType.value : '';
        var processIds = [];
        document.querySelectorAll('.pliego-modal-process-cb:checked').forEach(function(cb) { processIds.push(cb.value); });

        var url = baseUrl + '&' + token + '=1&quantity=' + encodeURIComponent(quantity) + '&paper_type_id=' + encodeURIComponent(paperId) + '&size_id=' + encodeURIComponent(sizeId) + '&tiro_retiro=' + encodeURIComponent(tiroRetiro) + '&lamination_tiro_retiro=' + encodeURIComponent(lamTiroRetiro) + '&lamination_type_id=' + encodeURIComponent(lamId);
        processIds.forEach(function(id) { url += '&process_ids[]=' + encodeURIComponent(id); });

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('pliego_modal_price_display').textContent = 'Q ' + (data.price_per_sheet != null ? Number(data.price_per_sheet).toFixed(2) : '-');
                    document.getElementById('pliego_modal_total_display').textContent = 'Q ' + (data.total != null ? Number(data.total).toFixed(2) : '-');
                    lastCalc = { price_per_sheet: data.price_per_sheet, total: data.total, rows: data.rows || [] };
                    renderBreakdownTable(lastCalc.rows, data.total);
                    if (submitBtn) submitBtn.disabled = false;
                } else {
                    document.getElementById('pliego_modal_price_display').textContent = '-';
                    document.getElementById('pliego_modal_total_display').textContent = data.message || '-';
                    lastCalc = null;
                    if (calcDetail) calcDetail.style.display = 'none';
                    if (submitBtn) submitBtn.disabled = true;
                }
            })
            .catch(function() {
                document.getElementById('pliego_modal_price_display').textContent = '-';
                document.getElementById('pliego_modal_total_display').textContent = '-';
                lastCalc = null;
                if (calcDetail) calcDetail.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
            });
    }

    [qty, paper, size, retiro, lamination, laminationType, laminationRetiro].forEach(function(el) {
        if (el) el.addEventListener('change', recalc);
    });
    if (qty) qty.addEventListener('input', recalc);
    document.querySelectorAll('.pliego-modal-process-cb').forEach(function(cb) { cb.addEventListener('change', recalc); });

    if (submitBtn && form) {
        submitBtn.addEventListener('click', function() {
            if (!lastCalc) return;
            document.getElementById('pliego_modal_price_per_sheet').value = lastCalc.price_per_sheet;
            document.getElementById('pliego_modal_total').value = lastCalc.total;
            document.getElementById('pliego_modal_breakdown').value = JSON.stringify(lastCalc.rows || []);
            if (lineIdInput && lineIdInput.value) {
                form.action = form.getAttribute('data-edit-action') || form.action;
            }
            form.submit();
        });
    }

    function setAddMode() {
        if (lineIdInput) lineIdInput.value = '';
        if (form) form.action = form.getAttribute('data-add-action') || form.action;
        if (modalTitle) modalTitle.textContent = modalTitleNew;
        if (submitBtn) submitBtn.textContent = addLineBtnLabel;
        if (qty) qty.value = '1';
        if (paper) paper.value = '';
        if (size) size.value = '';
        if (retiro) retiro.checked = false;
        if (lamination) { lamination.checked = false; }
        if (laminationType) laminationType.value = '';
        if (laminationRetiro) laminationRetiro.checked = false;
        document.querySelectorAll('.pliego-modal-process-cb').forEach(function(cb) { cb.checked = false; });
        lastCalc = null;
        if (calcDetail) calcDetail.style.display = 'none';
        document.getElementById('pliego_modal_price_display').textContent = '-';
        document.getElementById('pliego_modal_total_display').textContent = '-';
        if (submitBtn) submitBtn.disabled = true;
        filterSizeDropdown();
        filterLaminationBySize();
        updateLaminationVisibility();
    }

    var nuevaLineaBtn = document.querySelector('[data-bs-target="#pliegoLineModal"]');
    if (nuevaLineaBtn) {
        nuevaLineaBtn.addEventListener('click', function() { setAddMode(); });
    }

    document.addEventListener('click', function(e) {
        var editBtn = e.target && e.target.closest && e.target.closest('.pliego-edit-line-btn');
        if (!editBtn) return;
        var dataStr = editBtn.getAttribute('data-line');
        if (!dataStr) return;
        var line;
        try { line = JSON.parse(dataStr); } catch (err) { return; }
        if (!line) return;
        if (lineIdInput) lineIdInput.value = line.id || '';
        if (form) form.action = form.getAttribute('data-edit-action') || form.action;
        if (modalTitle) modalTitle.textContent = modalTitleEdit;
        if (submitBtn) submitBtn.textContent = saveLineBtnLabel;
        if (qty) qty.value = line.quantity || 1;
        if (paper) paper.value = (line.paper_type_id || '').toString();
        if (size) size.value = (line.size_id || '').toString();
        if (retiro) retiro.checked = (line.tiro_retiro || '') === 'retiro';
        if (lamination) lamination.checked = !!(line.lamination_type_id);
        if (laminationType) laminationType.value = (line.lamination_type_id || '').toString();
        if (laminationRetiro) laminationRetiro.checked = (line.lamination_tiro_retiro || '') === 'retiro';
        var procIds = (line.process_ids || []).map(function(x) { return parseInt(x, 10); });
        document.querySelectorAll('.pliego-modal-process-cb').forEach(function(cb) {
            cb.checked = procIds.indexOf(parseInt(cb.value, 10)) !== -1;
        });
        lastCalc = {
            price_per_sheet: line.price_per_sheet,
            total: line.total,
            rows: line.breakdown || []
        };
        renderBreakdownTable(lastCalc.rows, line.total);
        document.getElementById('pliego_modal_price_display').textContent = line.price_per_sheet != null ? 'Q ' + Number(line.price_per_sheet).toFixed(2) : '-';
        document.getElementById('pliego_modal_total_display').textContent = line.total != null ? 'Q ' + Number(line.total).toFixed(2) : '-';
        if (submitBtn) submitBtn.disabled = false;
        filterSizeDropdown();
        filterLaminationBySize();
        updateLaminationVisibility();
        if (pliegoModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = bootstrap.Modal.getOrCreateInstance(pliegoModal);
            modal.show();
        }
    });

    filterSizeDropdown();
    filterLaminationBySize();
    updateLaminationVisibility();
})();
</script>
<?php endif; ?>
