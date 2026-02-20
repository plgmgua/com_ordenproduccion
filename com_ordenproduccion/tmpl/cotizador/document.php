<?php
/**
 * Pre-Cotización document: header, lines table, "Nueva Línea" opens pliego form in modal.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

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
?>

<div class="com-ordenproduccion-precotizacion-document container py-4">
    <nav class="mb-3">
        <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BACK'); ?></a>
    </nav>

    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE'); ?> <?php echo htmlspecialchars($item->number); ?></h1>

    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pliegoLineModal">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NUEVA_LINEA'); ?>
        </button>
    </div>

    <h2 class="h5 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINES'); ?></h2>

    <?php if (empty($lines)) : ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_LINES'); ?></p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_QUANTITY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_PAPER_TYPE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_SIZE'); ?></th>
                        <th>Tiro/Retiro</th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_TOTAL'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line) :
                        $deleteLineUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.deleteLine&line_id=' . (int) $line->id . '&id=' . $preCotizacionId . '&' . $token . '=1');
                        $paperName = $paperNames[$line->paper_type_id ?? 0] ?? ('ID ' . (int) $line->paper_type_id);
                        $sizeName = $sizeNames[$line->size_id ?? 0] ?? ('ID ' . (int) $line->size_id);
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
                        <tr>
                            <td><?php echo (int) $line->quantity; ?></td>
                            <td><?php echo htmlspecialchars($paperName); ?></td>
                            <td><?php echo htmlspecialchars($sizeName); ?></td>
                            <td><?php echo ($line->tiro_retiro ?? '') === 'retiro' ? 'Tiro/Retiro' : 'Tiro'; ?></td>
                            <td>Q <?php echo number_format((float) $line->total, 2); ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary pliego-edit-line-btn" data-line="<?php echo $lineJson; ?>">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_EDIT_LINE'); ?>
                                </button>
                                <a href="<?php echo htmlspecialchars($deleteLineUrl); ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE_LINE')); ?>');">
                                    <?php echo Text::_('JACTION_DELETE'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($tablesExist) : ?>
<!-- Modal: Nueva Línea (Pliego form) -->
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
                                    <thead><tr><th><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_ITEM'); ?></th><th><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_DETAIL'); ?></th><th class="text-end" style="min-width:6em;"><?php echo Text::_('COM_ORDENPRODUCCION_CALC_COL_SUBTOTAL'); ?></th></tr></thead>
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
            return '<tr><td>' + escapeHtml(row.label) + '</td><td>' + escapeHtml(row.detail) + '</td><td class="text-end">Q ' + Number(row.subtotal).toFixed(2) + '</td></tr>';
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
