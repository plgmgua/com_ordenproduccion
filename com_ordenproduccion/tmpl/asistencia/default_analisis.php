<?php
/**
 * Asistencia Análisis tab - on-time % by quincena, grouped by employee group
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 */
defined('_JEXEC') or die;

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
$msgLoadError = AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_LOAD_ERROR', 'Error loading data', 'Error al cargar los datos');
$msgYes = AsistenciaHelper::safeText('JYES', 'Yes', 'Sí');
$msgNo = AsistenciaHelper::safeText('JNO', 'No', 'No');
?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ANALISIS_TITLE', 'Punctuality Analysis', 'Análisis de Puntualidad'); ?></h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=analisis'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="asistencia" />
            <input type="hidden" name="tab" value="analisis" />
            <div class="col-md-6">
                <label for="quincena" class="form-label"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_QUINCENA', 'Quincena', 'Quincena'); ?></label>
                <select name="quincena" id="quincena" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($quincenas as $q): ?>
                    <option value="<?php echo safeEscapeAnalisis($q->value); ?>" <?php echo $q->value === $selectedQuincena ? 'selected' : ''; ?>>
                        <?php echo safeEscapeAnalisis($q->label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php
        $thresholdDesc = AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ANALISIS_THRESHOLD_DESC',
            'Employees with on-time arrival % >= %d%% (configurable in Configuration)',
            'Empleados con % de llegadas a tiempo >= %d%% (configurable en Configuración)');
        $thresholdDesc = str_replace(['%d', '%%'], [(string) (int) $config->on_time_threshold, '%'], $thresholdDesc);
        ?>
        <p class="text-muted"><?php echo htmlspecialchars($thresholdDesc, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if (empty($analysisData)): ?>
        <p class="text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS', 'No attendance records found', 'No se encontraron registros de asistencia'); ?></p>
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
                            <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE', 'Employee', 'Empleado'); ?></th>
                            <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_DAYS', 'Days worked', 'Días trabajados'); ?></th>
                            <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ATTENDANCE_PCT', 'Attendance %', 'Asistencia %'); ?></th>
                            <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ON_TIME_DAYS', 'On-time arrivals', 'Llegadas a tiempo'); ?></th>
                            <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_PUNCTUALITY_PCT', 'Punctuality', 'Puntualidad'); ?> %</th>
                            <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_STATUS', 'Status', 'Estado'); ?></th>
                            <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ACTIONS', 'Actions', 'Acciones'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group->employees as $emp): ?>
                        <tr class="<?php echo $emp->meets_threshold ? '' : 'table-warning'; ?>">
                            <td><strong><?php echo safeEscapeAnalisis($emp->personname); ?></strong></td>
                            <td class="text-center"><?php echo (int) $emp->total_days; ?><?php echo isset($emp->work_days_in_quincena) && $emp->work_days_in_quincena > 0 ? ' / ' . (int) $emp->work_days_in_quincena : ''; ?></td>
                            <td class="text-center"><?php echo number_format($emp->attendance_pct ?? 0, 1); ?>%</td>
                            <td class="text-center"><?php echo (int) $emp->on_time_days; ?></td>
                            <td class="text-center"><strong><?php echo number_format($emp->on_time_pct, 1); ?>%</strong></td>
                            <td class="text-center">
                                <?php if ($emp->meets_threshold): ?>
                                <span class="badge bg-success"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ON_TIME_OK', 'Complies', 'Cumple'); ?></span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ON_TIME_BELOW', 'Below', 'Debajo'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-analisis-detalle"
                                    data-personname="<?php echo safeEscapeAnalisis($emp->personname); ?>"
                                    title="<?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_VIEW_DETAILS', 'View details', 'Ver detalle'); ?>">
                                    <span class="icon-eye"></span> <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_VIEW_DETAILS', 'View details', 'Ver detalle'); ?>
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
                    <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_VIEW_DETAILS', 'View details', 'Ver detalle'); ?>: <span id="modalAnalisisEmployee"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAnalisisLoading" class="text-center py-4">
                    <span class="spinner-border text-primary"></span>
                    <p class="mt-2 text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_LOADING', 'Loading...', 'Cargando...'); ?></p>
                </div>
                <div id="modalAnalisisContent" class="d-none">
                    <p class="mb-3">
                        <strong><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_DAYS', 'Days worked', 'Días trabajados'); ?>:</strong>
                        <span id="modalTotalDays"></span> / <span id="modalWorkDaysInQuincena"></span> |
                        <strong><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ATTENDANCE_PCT', 'Attendance %', 'Asistencia %'); ?>:</strong>
                        <span id="modalAttendancePct"></span>% |
                        <strong><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ON_TIME_DAYS', 'On-time arrivals', 'Llegadas a tiempo'); ?>:</strong>
                        <span id="modalOnTimeDays"></span> |
                        <strong><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_PUNCTUALITY_PCT', 'Punctuality', 'Puntualidad'); ?>:</strong>
                        <span id="modalOnTimePct"></span>%
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_WORK_DATE', 'Work Date', 'Fecha de trabajo'); ?></th>
                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_FIRST_ENTRY', 'First Entry', 'Primera entrada'); ?></th>
                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_LAST_EXIT', 'Last Exit', 'Última salida'); ?></th>
                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_HOURS', 'Total Hours', 'Horas totales'); ?></th>
                                    <th class="text-center"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_PUNTUAL', 'On-time', 'Puntual'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="modalAnalisisTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="modalAnalisisError" class="alert alert-danger d-none"></div>
                <div id="modalAnalisisEmpty" class="alert alert-info d-none"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS', 'No attendance records found', 'No se encontraron registros de asistencia'); ?></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var detailsUrl = <?php echo json_encode($detailsUrl); ?>;
    var msgLoadError = <?php echo json_encode($msgLoadError); ?>;
    var msgYes = <?php echo json_encode($msgYes); ?>;
    var msgNo = <?php echo json_encode($msgNo); ?>;
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
    var modalWorkDaysInQuincena = document.getElementById('modalWorkDaysInQuincena');
    var modalAttendancePct = document.getElementById('modalAttendancePct');
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
                    modalWorkDaysInQuincena.textContent = data.work_days_in_quincena || 0;
                    modalAttendancePct.textContent = data.attendance_pct != null ? data.attendance_pct : 0;
                    modalOnTimeDays.textContent = data.on_time_days;
                    modalOnTimePct.textContent = data.on_time_pct;
                    tbody.innerHTML = '';

                    if (!data.records || data.records.length === 0) {
                        showModal('empty');
                        return;
                    }

                    data.records.forEach(function(r) {
                        var isOnTime = r.is_late != 1;
                        var tr = document.createElement('tr');
                        tr.className = !isOnTime ? 'table-warning' : '';
                        tr.innerHTML =
                            '<td>' + r.work_date + ' (' + getDayName(r.work_date) + ')</td>' +
                            '<td>' + formatTime(r.first_entry) + '</td>' +
                            '<td>' + formatTime(r.last_exit) + '</td>' +
                            '<td>' + (r.total_hours != null ? parseFloat(r.total_hours).toFixed(2) : '-') + '</td>' +
                            '<td class="text-center">' + (isOnTime ?
                                '<span class="badge bg-success">' + msgYes + '</span>' :
                                '<span class="badge bg-warning text-dark">' + msgNo + '</span>') + '</td>';
                        tbody.appendChild(tr);
                    });
                    showModal('content');
                })
                .catch(function(err) {
                    modalError.textContent = err.message || msgLoadError;
                    showModal('error');
                });
        });
    });
});
</script>
