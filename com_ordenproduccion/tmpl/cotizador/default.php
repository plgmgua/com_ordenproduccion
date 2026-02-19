<?php
/**
 * Cotizador (Pliego-based quote form)
 * View: cotizador — link: index.php?option=com_ordenproduccion&view=cotizador
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

$sizes = $this->pliegoSizes ?? [];
$paperTypes = $this->pliegoPaperTypes ?? [];
$sizeIdsByPaperType = $this->pliegoSizeIdsByPaperType ?? [];
$laminationTypeIdsBySizeTiro = $this->pliegoLaminationTypeIdsBySizeTiro ?? [];
$laminationTypeIdsBySizeRetiro = $this->pliegoLaminationTypeIdsBySizeRetiro ?? [];
$laminationTypes = $this->pliegoLaminationTypes ?? [];
$processes = $this->pliegoProcesses ?? [];
$tablesExist = $this->pliegoTablesExist ?? false;
$baseUrl = Route::_('index.php?option=com_ordenproduccion&task=cotizacion.calculatePliegoPrice&format=raw');
$token = Session::getFormToken();
?>

<div class="com-ordenproduccion-cotizador-pliego container py-4">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_NUEVA_COTIZACION_PLIEGO_TITLE'); ?></h1>

    <?php if (!$tablesExist) : ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_ORDENPRODUCCION_PLIEGO_TABLES_MISSING'); ?>
        </div>
    <?php else : ?>

    <form id="pliego-quote-form" class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="pliego_quantity" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_QUANTITY'); ?></label>
                    <input type="number" id="pliego_quantity" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="col-md-4">
                    <label for="pliego_paper_type" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_PAPER_TYPE'); ?></label>
                    <select id="pliego_paper_type" name="paper_type_id" class="form-select" required>
                        <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_PAPER_TYPE'); ?></option>
                        <?php foreach ($paperTypes as $p) : ?>
                            <option value="<?php echo (int) $p->id; ?>"><?php echo htmlspecialchars($p->name ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="pliego_size" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_SIZE'); ?></label>
                    <select id="pliego_size" name="size_id" class="form-select" required>
                        <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_SIZE'); ?></option>
                        <?php foreach ($sizes as $s) : ?>
                            <option value="<?php echo (int) $s->id; ?>" data-size-id="<?php echo (int) $s->id; ?>"><?php echo htmlspecialchars($s->name ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" id="pliego_retiro" name="tiro_retiro" value="retiro" class="form-check-input">
                        <label class="form-check-label" for="pliego_retiro"><?php echo Text::_('COM_ORDENPRODUCCION_TIRO_RETIRO'); ?> (<?php echo Text::_('COM_ORDENPRODUCCION_RETIRO_DESC'); ?>)</label>
                    </div>
                    <small class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TIRO_DESC'); ?></small>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" id="pliego_needs_lamination" name="needs_lamination" value="1" class="form-check-input">
                        <label class="form-check-label" for="pliego_needs_lamination"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_NEEDS_LAMINATION'); ?></label>
                    </div>
                </div>
                <div class="col-md-6" id="pliego_lamination_type_wrap" style="display:none;">
                    <label for="pliego_lamination_type" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_LAMINATION_TYPE'); ?></label>
                    <select id="pliego_lamination_type" name="lamination_type_id" class="form-select">
                        <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_LAMINATION'); ?></option>
                        <?php foreach ($laminationTypes as $l) : ?>
                            <option value="<?php echo (int) $l->id; ?>" data-lamination-id="<?php echo (int) $l->id; ?>"><?php echo htmlspecialchars($l->name ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-check mt-2">
                        <input type="checkbox" id="pliego_lamination_retiro" name="lamination_tiro_retiro" value="retiro" class="form-check-input">
                        <label class="form-check-label" for="pliego_lamination_retiro"><?php echo Text::_('COM_ORDENPRODUCCION_LAMINATION_TIRO_RETIRO'); ?></label>
                    </div>
                    <small class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TIRO_DESC'); ?></small>
                </div>
            </div>

            <?php if (!empty($processes)) : ?>
            <div class="mb-3">
                <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_ADDITIONAL_PROCESSES'); ?></label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($processes as $pr) : ?>
                        <div class="form-check">
                            <input type="checkbox" name="process_ids[]" value="<?php echo (int) $pr->id; ?>" id="proc_<?php echo (int) $pr->id; ?>" class="form-check-input pliego-process-cb" data-price="<?php echo (float) $pr->price_per_pliego; ?>">
                            <label class="form-check-label" for="proc_<?php echo (int) $pr->id; ?>"><?php echo htmlspecialchars($pr->name ?? ''); ?> (Q <?php echo number_format((float) $pr->price_per_pliego, 2); ?>)</label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="border-top pt-3 mt-3">
                <p class="mb-1"><strong><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_PRICE_PER_PLIEGO'); ?>:</strong> <span id="pliego_price_per_sheet">-</span></p>
                <p class="mb-1"><strong><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_TOTAL'); ?>:</strong> <span id="pliego_total_price">-</span></p>
                <div id="pliego_calc_detail" class="mt-2 small text-muted" style="display:none;">
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_CALC_DETAIL'); ?></strong>
                    <ul class="list-unstyled mb-0 ps-2">
                        <li id="pliego_calc_print">—</li>
                        <li id="pliego_calc_lamination">—</li>
                        <li id="pliego_calc_processes">—</li>
                        <li id="pliego_calc_total">—</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>

    <script>
    (function() {
        var form = document.getElementById('pliego-quote-form');
        var qty = document.getElementById('pliego_quantity');
        var paper = document.getElementById('pliego_paper_type');
        var size = document.getElementById('pliego_size');
        var retiro = document.getElementById('pliego_retiro');
        var lamination = document.getElementById('pliego_needs_lamination');
        var laminationRetiro = document.getElementById('pliego_lamination_retiro');
        var laminationType = document.getElementById('pliego_lamination_type');
        var laminationWrap = document.getElementById('pliego_lamination_type_wrap');
        var baseUrl = <?php echo json_encode($baseUrl); ?>;
        var token = <?php echo json_encode($token); ?>;
        var sizeIdsByPaperType = <?php echo json_encode($sizeIdsByPaperType); ?>;
        var laminationTypeIdsBySizeTiro = <?php echo json_encode($laminationTypeIdsBySizeTiro); ?>;
        var laminationTypeIdsBySizeRetiro = <?php echo json_encode($laminationTypeIdsBySizeRetiro); ?>;
        var pliegoCalcLabelPrint = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CALC_PRINT')); ?>;
        var pliegoCalcLabelLam = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CALC_LAMINATION')); ?>;
        var pliegoCalcLabelProc = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CALC_PROCESSES')); ?>;
        var pliegoCalcLabelTotal = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CALC_TOTAL')); ?>;

        function filterSizeDropdown() {
            var paperId = paper && paper.value ? parseInt(paper.value, 10) : 0;
            var allowedIds = (paperId && sizeIdsByPaperType[paperId]) ? sizeIdsByPaperType[paperId] : [];
            var options = size ? size.querySelectorAll('option') : [];
            var currentVal = size ? size.value : '';
            var hasValidSelection = false;
            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                var vid = opt.value ? parseInt(opt.value, 10) : 0;
                if (vid === 0) {
                    opt.disabled = false;
                    opt.style.display = '';
                    continue;
                }
                var show = paperId === 0 ? false : allowedIds.indexOf(vid) !== -1;
                opt.style.display = show ? '' : 'none';
                opt.disabled = !show;
                if (show && opt.value === currentVal) hasValidSelection = true;
            }
            if (size && currentVal && !hasValidSelection) {
                size.value = '';
                recalc();
            }
        }
        if (paper) paper.addEventListener('change', filterSizeDropdown);

        function filterLaminationBySize() {
            var sizeId = size && size.value ? parseInt(size.value, 10) : 0;
            var lamRetiro = laminationRetiro && laminationRetiro.checked;
            var map = lamRetiro ? laminationTypeIdsBySizeRetiro : laminationTypeIdsBySizeTiro;
            var allowedLamIds = (sizeId && map[sizeId]) ? map[sizeId] : [];
            var hasAnyLamination = allowedLamIds.length > 0;
            if (lamination) {
                lamination.disabled = !hasAnyLamination;
                if (!hasAnyLamination && lamination.checked) {
                    lamination.checked = false;
                    if (laminationType) laminationType.value = '';
                    laminationWrap.style.display = 'none';
                }
            }
            var options = laminationType ? laminationType.querySelectorAll('option') : [];
            var currentLam = laminationType ? laminationType.value : '';
            var hasValidLam = false;
            for (var j = 0; j < options.length; j++) {
                var opt = options[j];
                var lid = opt.value ? parseInt(opt.value, 10) : 0;
                if (lid === 0) {
                    opt.disabled = false;
                    opt.style.display = '';
                    continue;
                }
                var show = hasAnyLamination && allowedLamIds.indexOf(lid) !== -1;
                opt.style.display = show ? '' : 'none';
                opt.disabled = !show;
                if (show && opt.value === currentLam) hasValidLam = true;
            }
            if (laminationType && currentLam && !hasValidLam) {
                laminationType.value = '';
                recalc();
            }
        }
        function updateLaminationVisibility() {
            laminationWrap.style.display = lamination && lamination.checked ? 'block' : 'none';
        }
        if (lamination) lamination.addEventListener('change', updateLaminationVisibility);
        if (size) size.addEventListener('change', filterLaminationBySize);
        if (laminationRetiro) laminationRetiro.addEventListener('change', function() { filterLaminationBySize(); recalc(); });

        function getProcessTotal() {
            var total = 0;
            document.querySelectorAll('.pliego-process-cb:checked').forEach(function(cb) {
                total += parseFloat(cb.getAttribute('data-price') || 0);
            });
            return total;
        }

        function recalc() {
            var quantity = parseInt(qty.value, 10) || 0;
            var paperId = paper && paper.value ? paper.value : '';
            var sizeId = size && size.value ? size.value : '';
            if (!quantity || !paperId || !sizeId) {
                document.getElementById('pliego_price_per_sheet').textContent = '-';
                document.getElementById('pliego_total_price').textContent = '-';
                var d = document.getElementById('pliego_calc_detail');
                if (d) d.style.display = 'none';
                return;
            }
            var tiroRetiro = (retiro && retiro.checked) ? 'retiro' : 'tiro';
            var lamTiroRetiro = (laminationRetiro && laminationRetiro.checked) ? 'retiro' : 'tiro';
            var lamId = (lamination && lamination.checked && laminationType && laminationType.value) ? laminationType.value : '';
            var processIds = [];
            document.querySelectorAll('.pliego-process-cb:checked').forEach(function(cb) { processIds.push(cb.value); });

            var url = baseUrl + '&' + token + '=1&quantity=' + encodeURIComponent(quantity) + '&paper_type_id=' + encodeURIComponent(paperId) + '&size_id=' + encodeURIComponent(sizeId) + '&tiro_retiro=' + encodeURIComponent(tiroRetiro) + '&lamination_tiro_retiro=' + encodeURIComponent(lamTiroRetiro) + '&lamination_type_id=' + encodeURIComponent(lamId);
            processIds.forEach(function(id) { url += '&process_ids[]=' + encodeURIComponent(id); });

            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        document.getElementById('pliego_price_per_sheet').textContent = 'Q ' + (data.price_per_sheet != null ? Number(data.price_per_sheet).toFixed(2) : '-');
                        document.getElementById('pliego_total_price').textContent = 'Q ' + (data.total != null ? Number(data.total).toFixed(2) : '-');
                        var detail = document.getElementById('pliego_calc_detail');
                        if (detail) {
                            detail.style.display = 'block';
                            var printVal = data.print_price != null ? Number(data.print_price).toFixed(2) : '0.00';
                            var lamVal = data.lamination_price != null && data.lamination_price > 0 ? Number(data.lamination_price).toFixed(2) : null;
                            var procVal = data.processes_total != null && data.processes_total > 0 ? Number(data.processes_total).toFixed(2) : null;
                            var totalVal = data.total != null ? Number(data.total).toFixed(2) : '-';
                            document.getElementById('pliego_calc_print').textContent = pliegoCalcLabelPrint + ': Q ' + printVal;
                            document.getElementById('pliego_calc_lamination').textContent = pliegoCalcLabelLam + ': ' + (lamVal ? 'Q ' + lamVal : '—');
                            document.getElementById('pliego_calc_processes').textContent = pliegoCalcLabelProc + ': ' + (procVal ? 'Q ' + procVal : '—');
                            document.getElementById('pliego_calc_total').textContent = pliegoCalcLabelTotal + ': Q ' + totalVal;
                        }
                    } else {
                        document.getElementById('pliego_price_per_sheet').textContent = '-';
                        document.getElementById('pliego_total_price').textContent = data.message || '-';
                        var detail = document.getElementById('pliego_calc_detail');
                        if (detail) detail.style.display = 'none';
                    }
                })
                .catch(function() {
                    document.getElementById('pliego_price_per_sheet').textContent = '-';
                    document.getElementById('pliego_total_price').textContent = '-';
                    var detail = document.getElementById('pliego_calc_detail');
                    if (detail) detail.style.display = 'none';
                });
        }

        [qty, paper, size, retiro, lamination, laminationType, laminationRetiro].forEach(function(el) {
            if (el) el.addEventListener('change', recalc);
        });
        if (qty) qty.addEventListener('input', recalc);
        document.querySelectorAll('.pliego-process-cb').forEach(function(cb) { cb.addEventListener('change', recalc); });
        filterSizeDropdown();
        filterLaminationBySize();
        updateLaminationVisibility();
    })();
    </script>

    <?php endif; ?>
</div>
