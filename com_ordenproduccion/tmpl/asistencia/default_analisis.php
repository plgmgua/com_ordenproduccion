<?php
/**
 * Asistencia Análisis tab - on-time % by quincena, grouped by employee group
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

$quincenas = $this->quincenas ?? [];
$selectedQuincena = $this->selectedQuincena ?? '';
$analysisData = $this->analysisData ?? [];
$config = $this->asistenciaConfig ?? (object) ['on_time_threshold' => 90];

function safeEscapeAnalisis($v, $d = '') {
    return is_string($v) && $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $d;
}

$detailsUrl = Route::_('index.php?option=com_ordenproduccion&task=asistencia.getAnalysisDetails&format=json', false);
?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">Análisis de Puntualidad</h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=analisis'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="asistencia" />
            <input type="hidden" name="tab" value="analisis" />
            <div class="col-md-6">
                <label for="quincena" class="form-label">Quincena</label>
                <select name="quincena" id="quincena" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($quincenas as $q): ?>
                    <option value="<?php echo safeEscapeAnalisis($q->value); ?>" <?php echo $q->value === $selectedQuincena ? 'selected' : ''; ?>>
                        <?php echo safeEscapeAnalisis($q->label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <p class="text-muted">Empleados con % de llegadas a tiempo >= <?php echo (int) $config->on_time_threshold; ?>% (configurable en Configuración)</p>

        <?php if (empty($analysisData)): ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS'); ?></p>
        <?php else: ?>
        <?php foreach ($analysisData as $group): ?>
        <div class="mb-4">
            <h6 class="border-bottom pb-2" style="border-color: <?php echo safeEscapeAnalisis($group->group_color, '#6c757d'); ?> !important;">
                <span class="badge" style="background-color: <?php echo safeEscapeAnalisis($group->group_color, '#6c757d'); ?>; color: white; font-size: 0.9rem;">
                    <?php echo safeEscapeAnalisis($group->group_name); ?>
                </span>
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Empleado</th>
                            <th class="text-center">Días trabajados</th>
                            <th class="text-center">Llegadas a tiempo</th>
                            <th class="text-center">% Puntualidad</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group->employees as $emp): ?>
                        <tr class="<?php echo $emp->meets_threshold ? '' : 'table-warning'; ?>">
                            <td><strong><?php echo safeEscapeAnalisis($emp->personname); ?></strong></td>
                            <td class="text-center"><?php echo (int) $emp->total_days; ?></td>
                            <td class="text-center"><?php echo (int) $emp->on_time_days; ?></td>
                            <td class="text-center"><strong><?php echo number_format($emp->on_time_pct, 1); ?>%</strong></td>
                            <td class="text-center">
                                <?php if ($emp->meets_threshold): ?>
                                <span class="badge bg-success">Cumple</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Debajo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-analisis-detalle"
                                    data-personname="<?php echo safeEscapeAnalisis($emp->personname); ?>"
                                    title="<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_VIEW_DETAILS'); ?>">
                                    <span class="icon-eye"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_VIEW_DETAILS'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Detalle de puntualidad -->
<div class="modal fade" id="modalAnalisisDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAnalisisDetalleTitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_VIEW_DETAILS'); ?>: <span id="modalAnalisisEmployee"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAnalisisLoading" class="text-center py-4">
                    <span class="spinner-border text-primary"></span>
                    <p class="mt-2 text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_LOADING'); ?></p>
                </div>
                <div id="modalAnalisisContent" class="d-none">
                    <p class="mb-3">
                        <strong><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_DAYS'); ?>:</strong>
                        <span id="modalTotalDays"></span> |
                        <strong><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ON_TIME_DAYS'); ?>:</strong>
                        <span id="modalOnTimeDays"></span> |
                        <strong><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_PUNCTUALITY_PCT'); ?>:</strong>
                        <span id="modalOnTimePct"></span>%
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_WORK_DATE'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_FIRST_ENTRY'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LAST_EXIT'); ?></th>
                                    <th><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_HOURS'); ?></th>
                                    <th class="text-center"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LATE'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="modalAnalisisTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="modalAnalisisError" class="alert alert-danger d-none"></div>
                <div id="modalAnalisisEmpty" class="alert alert-info d-none"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS'); ?></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var detailsUrl = <?php echo json_encode($detailsUrl); ?>;
    var selectedQuincena = <?php echo json_encode($selectedQuincena); ?>;
    var btnDetails = document.querySelectorAll('.btn-analisis-detalle');
    var modal = document.getElementById('modalAnalisisDetalle');
    var modalEmployee = document.getElementById('modalAnalisisEmployee');
    var modalLoading = document.getElementById('modalAnalisisLoading');
    var modalContent = document.getElementById('modalAnalisisContent');
    var modalError = document.getElementById('modalAnalisisError');
    var modalEmpty = document.getElementById('modalAnalisisEmpty');
    var tbody = document.getElementById('modalAnalisisTableBody');
    var modalTotalDays = document.getElementById('modalTotalDays');
    var modalOnTimeDays = document.getElementById('modalOnTimeDays');
    var modalOnTimePct = document.getElementById('modalOnTimePct');

    var dayLabels = {0: 'Dom', 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb'};
    function getDayName(dateStr) {
        var d = new Date(dateStr + 'T12:00:00');
        return dayLabels[d.getDay()] || '';
    }

    function formatTime(t) {
        if (!t) return '-';
        var p = String(t).split(':');
        return (p[0] || '00') + ':' + (p[1] || '00');
    }

    function showModal(state) {
        modalLoading.classList.add('d-none');
        modalContent.classList.add('d-none');
        modalError.classList.add('d-none');
        modalEmpty.classList.add('d-none');
        if (state === 'loading') modalLoading.classList.remove('d-none');
        else if (state === 'content') modalContent.classList.remove('d-none');
        else if (state === 'error') modalError.classList.remove('d-none');
        else if (state === 'empty') modalEmpty.classList.remove('d-none');
    }

    btnDetails.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var personname = this.getAttribute('data-personname');
            if (!personname || !selectedQuincena) return;

            modalEmployee.textContent = personname;
            showModal('loading');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                new bootstrap.Modal(modal).show();
            } else {
                modal.classList.add('show');
                modal.style.display = 'block';
            }

            var url = detailsUrl + (detailsUrl.indexOf('?') >= 0 ? '&' : '?') +
                'personname=' + encodeURIComponent(personname) + '&quincena=' + encodeURIComponent(selectedQuincena);

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        modalError.textContent = data.message || 'Error';
                        showModal('error');
                        return;
                    }
                    modalTotalDays.textContent = data.total_days;
                    modalOnTimeDays.textContent = data.on_time_days;
                    modalOnTimePct.textContent = data.on_time_pct;
                    tbody.innerHTML = '';

                    if (!data.records || data.records.length === 0) {
                        showModal('empty');
                        return;
                    }

                    data.records.forEach(function(r) {
                        var tr = document.createElement('tr');
                        tr.className = r.is_late == 1 ? 'table-warning' : '';
                        tr.innerHTML =
                            '<td>' + r.work_date + ' (' + getDayName(r.work_date) + ')</td>' +
                            '<td>' + formatTime(r.first_entry) + '</td>' +
                            '<td>' + formatTime(r.last_exit) + '</td>' +
                            '<td>' + (r.total_hours != null ? parseFloat(r.total_hours).toFixed(2) : '-') + '</td>' +
                            '<td class="text-center">' + (r.is_late == 1 ?
                                '<span class="badge bg-warning text-dark">Sí</span>' :
                                '<span class="badge bg-success">No</span>') + '</td>';
                        tbody.appendChild(tr);
                    });
                    showModal('content');
                })
                .catch(function(err) {
                    modalError.textContent = err.message || 'Error al cargar los datos';
                    showModal('error');
                });
        });
    });
});
</script>
