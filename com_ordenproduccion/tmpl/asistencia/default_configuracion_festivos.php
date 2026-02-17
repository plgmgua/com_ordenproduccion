<?php
/**
 * Configuración subtab - Días Festivos (company holidays)
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

$holidays = $this->companyHolidays ?? [];
$filterYear = $this->festivosFilterYear ?? (int) date('Y');
$filterMonth = $this->festivosFilterMonth ?? 0;

function safeE($v, $d = '') {
    return is_string($v) && $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $d;
}

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAYS_TITLE', 'Company Holidays', 'Días festivos (todos)'); ?></h5>
    </div>
    <div class="card-body">
        <p class="text-muted small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAYS_DESC', 'Full day off: no work, reduces expected days. Half day: reduced hours (e.g. Easter Wed 7am-12pm), on-time/early-exit use those times.', 'Día completo: no hay trabajo, reduce días esperados. Medio día: horario reducido (ej. Miércoles Semana Santa 7am-12pm), puntualidad usa esos horarios.'); ?></p>
        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=configuracion&subtab=festivos'); ?>" class="row g-2 mb-3">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="asistencia" />
            <input type="hidden" name="tab" value="configuracion" />
            <input type="hidden" name="subtab" value="festivos" />
            <div class="col-auto">
                <select name="filter_year" class="form-select form-select-sm">
                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 2; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="filter_month" class="form-select form-select-sm">
                    <option value="0"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ALL_MONTHS', 'All months', 'Todos los meses'); ?></option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>><?php echo $monthNames[$m] ?? $m; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo Text::_('JFILTER'); ?></button>
            </div>
        </form>
        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.saveHoliday'); ?>" method="post" class="mb-3" id="holidayForm">
            <?php echo HTMLHelper::_('form.token'); ?>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4">
                    <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_DATE', 'Date', 'Fecha'); ?></label>
                    <input type="date" name="holiday_date" class="form-control form-control-sm" required />
                </div>
                <div class="col-md-4">
                    <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_NAME', 'Name', 'Nombre'); ?></label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="<?php echo safeE(AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_NAME_PLACEHOLDER', 'e.g. Christmas', 'ej. Navidad')); ?>" />
                </div>
            </div>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-12">
                    <label class="form-label small d-block"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_TYPE', 'Type', 'Tipo'); ?></label>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="is_half_day" id="holiday_full" value="0" class="form-check-input" checked />
                        <label class="form-check-label" for="holiday_full"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_FULL_DAY', 'Full day off', 'Día completo libre'); ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="is_half_day" id="holiday_half" value="1" class="form-check-input" />
                        <label class="form-check-label" for="holiday_half"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_HALF_DAY', 'Half day', 'Medio día'); ?></label>
                    </div>
                </div>
            </div>
            <div class="row g-2 align-items-end mb-2" id="halfDayTimes" style="display:none;">
                <div class="col-md-3">
                    <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_START', 'Start', 'Inicio'); ?></label>
                    <input type="time" name="start_time" class="form-control form-control-sm" value="07:00" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_END', 'End', 'Fin'); ?></label>
                    <input type="time" name="end_time" class="form-control form-control-sm" value="12:00" />
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('JSAVE'); ?></button>
                </div>
            </div>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var full = document.getElementById('holiday_full');
            var half = document.getElementById('holiday_half');
            var times = document.getElementById('halfDayTimes');
            function toggle() { times.style.display = half && half.checked ? 'block' : 'none'; }
            if (full) full.addEventListener('change', toggle);
            if (half) half.addEventListener('change', toggle);
        });
        </script>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="table-light">
                    <tr>
                        <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_DATE', 'Date', 'Fecha'); ?></th>
                        <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_NAME', 'Name', 'Nombre'); ?></th>
                        <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_TYPE', 'Type', 'Tipo'); ?></th>
                        <th class="text-end"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ACTIONS', 'Actions', 'Acciones'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($holidays)): ?>
                    <tr><td colspan="4" class="text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_NO_HOLIDAYS', 'No holidays defined', 'No hay festivos definidos'); ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($holidays as $h): ?>
                    <?php
                    $isHalf = !empty($h->is_half_day) && !empty($h->start_time) && !empty($h->end_time);
                    $typeLabel = $isHalf ? AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_HALF_DAY', 'Half day', 'Medio día') . ' (' . substr($h->start_time, 0, 5) . '-' . substr($h->end_time, 0, 5) . ')' : AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_FULL_DAY', 'Full day off', 'Día completo libre');
                    ?>
                    <tr>
                        <td><?php echo safeE($h->holiday_date); ?></td>
                        <td><?php echo safeE($h->name); ?></td>
                        <td><?php echo safeE($typeLabel); ?></td>
                        <td class="text-end">
                            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.deleteHoliday'); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo addslashes(AsistenciaHelper::safeText('COM_ORDENPRODUCCION_DELETE_CONFIRM', 'Are you sure you want to delete?', '¿Está seguro de que desea eliminar?')); ?>');">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $h->id; ?>" />
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('JDELETE'); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
