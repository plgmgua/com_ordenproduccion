<?php
/**
 * Asistencia Festivos tab - company holidays and justified absences
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
$absences = $this->justifiedAbsences ?? [];
$employees = $this->employees ?? [];
$filterYear = $this->festivosFilterYear ?? (int) date('Y');
$filterMonth = $this->festivosFilterMonth ?? 0;
$filterPerson = $this->festivosFilterPerson ?? '';

function safeE($v, $d = '') {
    return is_string($v) && $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $d;
}

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<div class="row">
    <!-- Company Holidays -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAYS_TITLE', 'Company Holidays', 'Días festivos (todos)'); ?></h5>
            </div>
            <div class="card-body">
                <p class="text-muted small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAYS_DESC', 'Holidays that apply to everyone. They reduce expected work days for attendance %.', 'Afectan a todos. Reducen los días laborables esperados para el % de asistencia.'); ?></p>
                <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=festivos'); ?>" class="row g-2 mb-3">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="view" value="asistencia" />
                    <input type="hidden" name="tab" value="festivos" />
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
                <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.saveHoliday'); ?>" method="post" class="mb-3">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_DATE', 'Date', 'Fecha'); ?></label>
                            <input type="date" name="holiday_date" class="form-control form-control-sm" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_NAME', 'Name', 'Nombre'); ?></label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="<?php echo safeE(AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_NAME_PLACEHOLDER', 'e.g. Christmas', 'ej. Navidad')); ?>" />
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('JSAVE'); ?></button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_DATE', 'Date', 'Fecha'); ?></th>
                                <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAY_NAME', 'Name', 'Nombre'); ?></th>
                                <th class="text-end"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ACTIONS', 'Actions', 'Acciones'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($holidays)): ?>
                            <tr><td colspan="3" class="text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_NO_HOLIDAYS', 'No holidays defined', 'No hay festivos definidos'); ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($holidays as $h): ?>
                            <tr>
                                <td><?php echo safeE($h->holiday_date); ?></td>
                                <td><?php echo safeE($h->name); ?></td>
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
    </div>

    <!-- Justified Absences -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_JUSTIFIED_ABSENCES_TITLE', 'Justified Days Off', 'Días justificados por empleado'); ?></h5>
            </div>
            <div class="card-body">
                <p class="text-muted small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_JUSTIFIED_ABSENCES_DESC', 'Per-employee excused absences (vacation, medical, etc.) that count as present for attendance %.', 'Ausencias excusadas por empleado (vacaciones, médico, etc.) que cuentan como presentes.'); ?></p>
                <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=festivos'); ?>" class="row g-2 mb-3 align-items-end">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="view" value="asistencia" />
                    <input type="hidden" name="tab" value="festivos" />
                    <input type="hidden" name="filter_year" value="<?php echo $filterYear; ?>" />
                    <input type="hidden" name="filter_month" value="<?php echo $filterMonth; ?>" />
                    <div class="col-auto">
                        <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE', 'Employee', 'Empleado'); ?></label>
                        <select name="filter_person" class="form-select form-select-sm">
                            <option value=""><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ALL_EMPLOYEES', 'All employees', 'Todos los empleados'); ?></option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo safeE($emp->personname ?? $emp->name ?? ''); ?>" <?php echo $filterPerson === ($emp->personname ?? $emp->name ?? '') ? 'selected' : ''; ?>><?php echo safeE($emp->personname ?? $emp->name ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small">&nbsp;</label>
                        <button type="submit" class="btn btn-sm btn-outline-secondary d-block"><?php echo Text::_('JFILTER'); ?></button>
                    </div>
                </form>
                <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.saveJustifiedAbsence'); ?>" method="post" class="mb-3">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE', 'Employee', 'Empleado'); ?></label>
                            <select name="personname" class="form-select form-select-sm" required>
                                <option value="">--</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo safeE($emp->personname ?? $emp->name ?? ''); ?>"><?php echo safeE($emp->personname ?? $emp->name ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ABSENCE_DATE', 'Date', 'Fecha'); ?></label>
                            <input type="date" name="absence_date" class="form-control form-control-sm" required />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ABSENCE_REASON', 'Reason', 'Motivo'); ?></label>
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="<?php echo safeE(AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ABSENCE_REASON_PLACEHOLDER', 'e.g. Vacation', 'ej. Vacaciones')); ?>" />
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('JSAVE'); ?></button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE', 'Employee', 'Empleado'); ?></th>
                                <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ABSENCE_DATE', 'Date', 'Fecha'); ?></th>
                                <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ABSENCE_REASON', 'Reason', 'Motivo'); ?></th>
                                <th class="text-end"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_ACTIONS', 'Actions', 'Acciones'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($absences)): ?>
                            <tr><td colspan="4" class="text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_NO_JUSTIFIED_ABSENCES', 'No justified absences', 'No hay ausencias justificadas'); ?></td></tr>
                            <?php else: ?>
                            <?php foreach ($absences as $a): ?>
                            <tr>
                                <td><?php echo safeE($a->personname); ?></td>
                                <td><?php echo safeE($a->absence_date); ?></td>
                                <td><?php echo safeE($a->reason); ?></td>
                                <td class="text-end">
                                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.deleteJustifiedAbsence'); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo addslashes(AsistenciaHelper::safeText('COM_ORDENPRODUCCION_DELETE_CONFIRM', 'Are you sure you want to delete?', '¿Está seguro de que desea eliminar?')); ?>');">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $a->id; ?>" />
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
    </div>
</div>
