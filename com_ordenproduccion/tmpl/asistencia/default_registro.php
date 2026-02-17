<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 * Asistencia Registro tab - attendance list
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

if (!function_exists('safeEscape')) {
    function safeEscape($value, $default = '') {
        return is_string($value) && $value !== '' ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $default;
    }
}
if (!function_exists('safeTranslate')) {
    function safeTranslate($key, $enFallback, $esFallback = null) {
        $s = Text::_($key);
        if ($s === $key) {
            $tag = Factory::getApplication()->getLanguage()->getTag();
            return $tag === 'es-ES' ? ($esFallback ?? $enFallback) : $enFallback;
        }
        return $s;
    }
}

$user = Factory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering', 'a.work_date'));
$listDirn = $this->escape($this->state->get('list.direction', 'DESC'));
$filterSearch = $this->state->get('filter.search');
$filterDateFrom = $this->state->get('filter.date_from');
$filterDateTo = $this->state->get('filter.date_to');
$filterCardno = (array) $this->state->get('filter.cardno', []);
$filterGroupId = (array) $this->state->get('filter.group_id', []);
$filterIsComplete = $this->state->get('filter.is_complete');
?>

<?php if (!empty($this->stats)): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TOTAL_EMPLOYEES'); ?></h5>
                <p class="card-text display-4"><?php echo (int) $this->stats->total_employees; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_COMPLETE_DAYS'); ?></h5>
                <p class="card-text display-4"><?php echo (int) $this->stats->complete_days; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_LATE_DAYS'); ?></h5>
                <p class="card-text display-4"><?php echo (int) $this->stats->late_days; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_AVG_HOURS'); ?></h5>
                <p class="card-text display-4"><?php echo number_format($this->stats->avg_hours ?? 0, 2); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=registro'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_FILTERS'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="filter_search" class="form-label"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
                    <input type="text" name="filter_search" id="filter_search" class="form-control" value="<?php echo safeEscape($filterSearch); ?>" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SEARCH_PLACEHOLDER'); ?>">
                </div>
                <div class="col-md-2">
                    <label for="filter_date_from" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DATE_FROM'); ?></label>
                    <input type="date" name="filter_date_from" id="filter_date_from" class="form-control" value="<?php echo safeEscape($filterDateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label for="filter_date_to" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DATE_TO'); ?></label>
                    <input type="date" name="filter_date_to" id="filter_date_to" class="form-control" value="<?php echo safeEscape($filterDateTo); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE'); ?></label>
                    <div class="checkbox-dropdown" id="filter_cardno_dropdown" data-all-label="<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_EMPLOYEES'); ?>" data-selected-label="<?php echo safeTranslate('COM_ORDENPRODUCCION_ASISTENCIA_SELECTED', '%d selected', '%d seleccionados'); ?>">
                        <button type="button" class="checkbox-dropdown-toggle" aria-haspopup="listbox" aria-expanded="false">
                            <span class="checkbox-dropdown-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_EMPLOYEES'); ?></span>
                            <span class="dropdown-caret" aria-hidden="true">&#9662;</span>
                        </button>
                        <div class="checkbox-dropdown-panel" role="listbox">
                            <?php foreach ($this->employees as $employee): ?>
                                <?php $val = $employee->personname ?: $employee->cardno; ?>
                                <label class="checkbox-dropdown-option">
                                    <input type="checkbox" name="filter_cardno[]" value="<?php echo $this->escape($val); ?>" <?php echo in_array($val, $filterCardno, true) ? 'checked' : ''; ?>>
                                    <?php echo safeEscape($employee->personname); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_GROUP'); ?></label>
                    <div class="checkbox-dropdown" id="filter_group_id_dropdown" data-all-label="<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_GROUPS'); ?>" data-selected-label="<?php echo safeTranslate('COM_ORDENPRODUCCION_ASISTENCIA_SELECTED', '%d selected', '%d seleccionados'); ?>">
                        <button type="button" class="checkbox-dropdown-toggle" aria-haspopup="listbox" aria-expanded="false">
                            <span class="checkbox-dropdown-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_GROUPS'); ?></span>
                            <span class="dropdown-caret" aria-hidden="true">&#9662;</span>
                        </button>
                        <div class="checkbox-dropdown-panel" role="listbox">
                            <?php foreach ($this->groups as $group): ?>
                                <label class="checkbox-dropdown-option">
                                    <input type="checkbox" name="filter_group_id[]" value="<?php echo (int) $group->id; ?>" <?php echo in_array((int) $group->id, $filterGroupId, true) ? 'checked' : ''; ?>>
                                    <?php echo safeEscape($group->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <label for="filter_is_complete" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_STATUS'); ?></label>
                    <select name="filter_is_complete" id="filter_is_complete" class="form-select">
                        <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_STATUS'); ?></option>
                        <option value="1" <?php echo ($filterIsComplete === '1') ? 'selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_COMPLETE'); ?></option>
                        <option value="0" <?php echo ($filterIsComplete === '0') ? 'selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_INCOMPLETE'); ?></option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" style="font-size: 0.8rem; padding: 0.375rem 0.5rem;"><span class="icon-search"></span></button>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.sync'); ?>" class="btn btn-primary"><span class="icon-refresh"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC'); ?></a>
        <button type="button" class="btn btn-success" onclick="document.getElementById('exportExcelModal').style.display='block';"><span class="icon-download"></span> <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_TO_EXCEL'); ?></button>
        <?php if ($filterDateFrom && $filterDateTo): ?>
        <button type="button" class="btn btn-secondary" onclick="recalculateSummaries()"><span class="icon-refresh"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_RECALCULATE'); ?></button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm compact-table">
            <thead class="table-dark">
                <tr>
                    <th style="min-width: 120px;">Empleado</th>
                    <th style="width: 110px;"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALERTS'); ?></th>
                    <th style="width: 100px;">Grupo</th>
                    <th style="width: 90px;">Fecha</th>
                    <th style="width: 90px;"><?php echo safeTranslate('COM_ORDENPRODUCCION_DAY', 'Day', 'Día'); ?></th>
                    <th style="width: 70px;">Entrada</th>
                    <th style="width: 70px;">Salida</th>
                    <th style="width: 80px;">Horas</th>
                    <th style="width: 80px;">Estado</th>
                    <th style="width: 100px;">Aprobación</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($this->items)): ?>
                    <?php foreach ($this->items as $item): ?>
                    <tr class="summary-row <?php echo $item->is_late ? 'table-warning' : ''; ?>">
                        <td><strong style="font-size: 0.85rem;"><?php echo safeEscape($item->personname); ?></strong></td>
                        <td style="font-size: 0.7rem;">
                            <?php if ($item->is_late || $item->is_early_exit): ?>
                                <?php if ($item->is_late): ?><span class="badge bg-warning text-dark" style="font-size: 0.6rem;">Tarde</span><?php endif; ?>
                                <?php if ($item->is_early_exit): ?><span class="badge bg-info" style="font-size: 0.6rem;">Salida T.</span><?php endif; ?>
                            <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($item->group_name)): ?>
                                <span class="badge" style="font-size: 0.7rem; background-color: <?php echo safeEscape($item->group_color, '#6c757d'); ?>; color: white;"><?php echo safeEscape($item->group_name); ?></span>
                            <?php else: ?><span class="badge bg-secondary">Sin Grupo</span><?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/y', strtotime($item->work_date)); ?></td>
                        <td><?php echo AsistenciaHelper::getDayName($item->work_date); ?></td>
                        <td><?php echo substr(safeEscape($item->first_entry, '-'), 0, 5); ?></td>
                        <td><?php echo substr(safeEscape($item->last_exit, '-'), 0, 5); ?></td>
                        <td><strong><?php echo AsistenciaHelper::formatHours($item->total_hours ?? 0); ?></strong> <span class="text-muted">/<?php echo number_format($item->expected_hours ?? 8, 0); ?>h</span></td>
                        <td><span class="<?php echo AsistenciaHelper::getStatusBadgeClass($item->is_complete); ?>"><?php echo $item->is_complete ? 'Completo' : 'Incompleto'; ?></span></td>
                        <td><span class="badge <?php echo (($item->approval_status ?? 'pending') === 'approved') ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo (($item->approval_status ?? 'pending') === 'approved') ? 'Aprobado' : 'Pendiente'; ?></span></td>
                    </tr>
                    <?php if (!empty($item->manual_entries)): foreach ($item->manual_entries as $manual): ?>
                    <tr class="manual-entry-row" style="background-color: #f8f9fa;">
                        <td colspan="5" style="padding-left: 40px;">
                            <span class="text-muted">Manual:</span> <strong><?php echo $manual->authtime ? substr($manual->authtime, 0, 5) : '-'; ?></strong>
                            <span class="badge bg-info"><?php echo safeEscape($manual->direction ?: 'N/A'); ?></span>
                        </td>
                        <td colspan="4"><?php echo !empty($manual->notes) ? safeEscape($manual->notes) : '(Sin notas)'; ?></td>
                        <td><?php if (!$user->guest): ?><a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistenciaentry.delete&id=' . (int)$manual->id . '&' . Session::getFormToken() . '=1'); ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DELETE_CONFIRM'); ?>');"><i class="icon-delete"></i></a><?php endif; ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($this->pagination && $this->pagination->pagesTotal > 1): ?>
    <div class="pagination"><?php echo $this->pagination->getListFooter(); ?></div>
    <?php endif; ?>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
function recalculateSummaries() {
    const dateFrom = document.getElementById('filter_date_from').value;
    const dateTo = document.getElementById('filter_date_to').value;
    if (!dateFrom || !dateTo) { alert('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ERROR_DATE_RANGE_REQUIRED'); ?>'); return; }
    if (confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_RECALCULATE_CONFIRM'); ?>')) {
        window.location.href = '<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.recalculate'); ?>&date_from=' + dateFrom + '&date_to=' + dateTo;
    }
}
function getCurrentWeekDates() { const d = new Date(); const day = d.getDay(); const diff = d.getDate() - day + (day === 0 ? -6 : 1); const mon = new Date(d); mon.setDate(diff); const sun = new Date(mon); sun.setDate(mon.getDate() + 6); return { from: mon.toISOString().split('T')[0], to: sun.toISOString().split('T')[0] }; }
function getMonthDates(y, m) { const f = new Date(y, m - 1, 1), l = new Date(y, m, 0); return { from: f.toISOString().split('T')[0], to: l.toISOString().split('T')[0] }; }
function showExportOption(o) { document.getElementById('weekOption').style.display = o === 'week' ? 'block' : 'none'; document.getElementById('monthOption').style.display = o === 'month' ? 'block' : 'none'; }
function exportToExcel(type) {
    const dates = type === 'week' ? getCurrentWeekDates() : getMonthDates(parseInt(document.getElementById('exportYear').value), parseInt(document.getElementById('exportMonth').value));
    window.location.href = '<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.exportExcel'); ?>&date_from=' + dates.from + '&date_to=' + dates.to;
    document.getElementById('exportExcelModal').style.display = 'none';
}
</script>

<div id="exportExcelModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4);">
    <div style="background: #fff; margin: 10% auto; padding: 25px; width: 500px; border-radius: 8px;">
        <h3><?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_TO_EXCEL'); ?></h3>
        <p><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_EXPORT_RANGE'); ?></p>
        <label><input type="radio" name="exportType" value="week" checked onclick="showExportOption('week')"> <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_CURRENT_WEEK'); ?></label>
        <label><input type="radio" name="exportType" value="month" onclick="showExportOption('month')"> <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_SELECT_MONTH'); ?></label>
        <div id="weekOption"><?php echo Text::_('COM_ORDENPRODUCCION_CURRENT_WEEK_WILL_EXPORT'); ?></div>
        <div id="monthOption" style="display: none;">
            <label><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_YEAR'); ?>: <select id="exportYear"><?php $y = date('Y'); echo '<option value="' . $y . '">' . $y . '</option><option value="' . ($y-1) . '">' . ($y-1) . '</option>'; ?></select></label>
            <label><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_MONTH'); ?>: <select id="exportMonth"><?php
$months = [1=>'JANUARY',2=>'FEBRUARY',3=>'MARCH',4=>'APRIL',5=>'MAY',6=>'JUNE',7=>'JULY',8=>'AUGUST',9=>'SEPTEMBER',10=>'OCTOBER',11=>'NOVEMBER',12=>'DECEMBER'];
foreach ($months as $m => $k) { $sel = $m == date('n') ? ' selected' : ''; echo '<option value="' . $m . '"' . $sel . '>' . Text::_($k) . '</option>'; }
?></select></label>
        </div>
        <button onclick="document.getElementById('exportExcelModal').style.display='none'"><?php echo Text::_('JCANCEL'); ?></button>
        <button class="btn btn-success" onclick="exportToExcel(document.querySelector('input[name=exportType]:checked').value)"><?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_NOW'); ?></button>
    </div>
</div>
