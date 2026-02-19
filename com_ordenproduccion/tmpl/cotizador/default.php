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
                            <option value="<?php echo (int) $s->id; ?>"><?php echo htmlspecialchars($s->name ?? ''); ?></option>
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
                            <option value="<?php echo (int) $l->id; ?>"><?php echo htmlspecialchars($l->name ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                <p class="mb-0"><strong><?php echo Text::_('COM_ORDENPRODUCCION_QUOTE_TOTAL'); ?>:</strong> <span id="pliego_total_price">-</span></p>
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
        var laminationType = document.getElementById('pliego_lamination_type');
        var laminationWrap = document.getElementById('pliego_lamination_type_wrap');
        var baseUrl = <?php echo json_encode($baseUrl); ?>;
        var token = <?php echo json_encode($token); ?>;

        function updateLaminationVisibility() {
            laminationWrap.style.display = lamination && lamination.checked ? 'block' : 'none';
        }
        if (lamination) lamination.addEventListener('change', updateLaminationVisibility);

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
                return;
            }
            var tiroRetiro = (retiro && retiro.checked) ? 'retiro' : 'tiro';
            var lamId = (lamination && lamination.checked && laminationType && laminationType.value) ? laminationType.value : '';
            var processIds = [];
            document.querySelectorAll('.pliego-process-cb:checked').forEach(function(cb) { processIds.push(cb.value); });

            var url = baseUrl + '&' + token + '=1&quantity=' + encodeURIComponent(quantity) + '&paper_type_id=' + encodeURIComponent(paperId) + '&size_id=' + encodeURIComponent(sizeId) + '&tiro_retiro=' + encodeURIComponent(tiroRetiro) + '&lamination_type_id=' + encodeURIComponent(lamId);
            processIds.forEach(function(id) { url += '&process_ids[]=' + encodeURIComponent(id); });

            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        document.getElementById('pliego_price_per_sheet').textContent = 'Q ' + (data.price_per_sheet != null ? Number(data.price_per_sheet).toFixed(2) : '-');
                        document.getElementById('pliego_total_price').textContent = 'Q ' + (data.total != null ? Number(data.total).toFixed(2) : '-');
                    } else {
                        document.getElementById('pliego_price_per_sheet').textContent = '-';
                        document.getElementById('pliego_total_price').textContent = data.message || '-';
                    }
                })
                .catch(function() {
                    document.getElementById('pliego_price_per_sheet').textContent = '-';
                    document.getElementById('pliego_total_price').textContent = '-';
                });
        }

        [qty, paper, size, retiro, lamination, laminationType].forEach(function(el) {
            if (el) el.addEventListener('change', recalc);
        });
        if (qty) qty.addEventListener('input', recalc);
        document.querySelectorAll('.pliego-process-cb').forEach(function(cb) { cb.addEventListener('change', recalc); });
        updateLaminationVisibility();
    })();
    </script>

    <?php endif; ?>
</div>
