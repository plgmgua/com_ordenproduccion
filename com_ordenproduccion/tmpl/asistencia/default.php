<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

// Safe helper functions
function safeEscape($value, $default = '') {
    if (is_string($value) && !empty($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('form.validate');

$listOrder = $this->escape($this->state->get('list.ordering', 'a.work_date'));
$listDirn = $this->escape($this->state->get('list.direction', 'DESC'));

// Get filter values
$filterSearch = $this->state->get('filter.search');
$filterDateFrom = $this->state->get('filter.date_from');
$filterDateTo = $this->state->get('filter.date_to');
$filterCardno = $this->state->get('filter.cardno');
$filterGroupId = $this->state->get('filter.group_id');
$filterIsComplete = $this->state->get('filter.is_complete');
$filterIsLate = $this->state->get('filter.is_late');
?>

<div class="com-ordenproduccion-asistencia">
    <div class="page-header">
        <h1><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TITLE'); ?></h1>
    </div>

    <?php if (!empty($this->stats)): ?>
    <!-- Statistics Cards -->
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

    <!-- Filters -->
    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia'); ?>" method="post" name="adminForm" id="adminForm">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_FILTERS'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label for="filter_search" class="form-label"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
                        <input type="text" name="filter_search" id="filter_search" 
                               class="form-control" 
                               value="<?php echo safeEscape($filterSearch); ?>"
                               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SEARCH_PLACEHOLDER'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_date_from" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DATE_FROM'); ?></label>
                        <input type="date" name="filter_date_from" id="filter_date_from" 
                               class="form-control" 
                               value="<?php echo safeEscape($filterDateFrom); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_date_to" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DATE_TO'); ?></label>
                        <input type="date" name="filter_date_to" id="filter_date_to" 
                               class="form-control" 
                               value="<?php echo safeEscape($filterDateTo); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filter_cardno" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE'); ?></label>
                        <select name="filter_cardno" id="filter_cardno" class="form-select">
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_EMPLOYEES'); ?></option>
                            <?php foreach ($this->employees as $employee): ?>
                                <option value="<?php echo safeEscape($employee->cardno); ?>" 
                                        <?php echo ($filterCardno == $employee->cardno) ? 'selected' : ''; ?>>
                                    <?php echo safeEscape($employee->personname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter_group_id" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_GROUP'); ?></label>
                        <select name="filter_group_id" id="filter_group_id" class="form-select">
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_GROUPS'); ?></option>
                            <?php foreach ($this->groups as $group): ?>
                                <option value="<?php echo (int) $group->id; ?>" 
                                        <?php echo ($filterGroupId == $group->id) ? 'selected' : ''; ?>>
                                    <?php echo safeEscape($group->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="filter_is_complete" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_STATUS'); ?></label>
                        <select name="filter_is_complete" id="filter_is_complete" class="form-select">
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ALL_STATUS'); ?></option>
                            <option value="1" <?php echo ($filterIsComplete === '1') ? 'selected' : ''; ?>>
                                <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_COMPLETE'); ?>
                            </option>
                            <option value="0" <?php echo ($filterIsComplete === '0') ? 'selected' : ''; ?>>
                                <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_INCOMPLETE'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" style="font-size: 0.8rem; padding: 0.375rem 0.5rem;">
                            <span class="icon-search"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-3">
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistenciaentry&layout=edit'); ?>" 
               class="btn btn-success">
                <span class="icon-plus"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NEW_ENTRY'); ?>
            </a>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.sync'); ?>" 
               class="btn btn-primary" 
               onclick="return confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_CONFIRM'); ?>');">
                <span class="icon-refresh"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC'); ?>
            </a>
            <button type="button" class="btn btn-success" onclick="document.getElementById('exportExcelModal').style.display='block';">
                <span class="icon-download"></span> <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_TO_EXCEL'); ?>
            </button>
            <?php if ($filterDateFrom && $filterDateTo): ?>
            <button type="button" class="btn btn-secondary" onclick="recalculateSummaries()">
                <span class="icon-refresh"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_RECALCULATE'); ?>
            </button>
            <?php endif; ?>
        </div>

        <!-- Data Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm compact-table">
                <thead class="table-dark">
                    <tr>
                        <th style="min-width: 120px;">Empleado</th>
                        <th style="width: 100px;">Grupo</th>
                        <th style="width: 90px;">Fecha</th>
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
                            <td>
                                <strong style="font-size: 0.85rem;"><?php echo safeEscape($item->personname); ?></strong>
                                <?php if ($item->is_late): ?>
                                    <span class="badge bg-warning text-dark" style="font-size: 0.6rem; padding: 2px 5px;">Tarde</span>
                                <?php endif; ?>
                                <?php if ($item->is_early_exit): ?>
                                    <span class="badge bg-info" style="font-size: 0.6rem; padding: 2px 5px;">Salida T.</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.75rem;">
                                <?php if (!empty($item->group_name)): ?>
                                    <span class="badge" style="font-size: 0.7rem; padding: 3px 6px; background-color: <?php echo safeEscape($item->group_color, '#6c757d'); ?>; color: white;">
                                        <?php echo safeEscape($item->group_name); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" style="font-size: 0.7rem; padding: 3px 6px;">
                                        Sin Grupo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem;"><?php echo date('d/m/y', strtotime($item->work_date)); ?></td>
                            <td style="font-size: 0.8rem;"><?php echo substr(safeEscape($item->first_entry, '-'), 0, 5); ?></td>
                            <td style="font-size: 0.8rem;"><?php echo substr(safeEscape($item->last_exit, '-'), 0, 5); ?></td>
                            <td style="font-size: 0.8rem;">
                                <strong><?php echo AsistenciaHelper::formatHours($item->total_hours ?? 0); ?></strong>
                                <span class="text-muted" style="font-size: 0.7rem;"> /<?php echo number_format($item->expected_hours ?? 8, 0); ?>h</span>
                            </td>
                            <td>
                                <span class="<?php echo AsistenciaHelper::getStatusBadgeClass($item->is_complete); ?>" style="font-size: 0.65rem; padding: 3px 6px;">
                                    <?php echo $item->is_complete ? 'Completo' : 'Incompleto'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (($item->approval_status ?? 'pending') === 'approved'): ?>
                                    <span class="badge bg-success" style="font-size: 0.65rem; padding: 3px 6px;">Aprobado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark" style="font-size: 0.65rem; padding: 3px 6px;">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($item->manual_entries)) : ?>
                            <?php foreach ($item->manual_entries as $manual) : ?>
                            <tr class="manual-entry-row" style="background-color: #f8f9fa; font-size: 0.85em;">
                                <td colspan="3" style="padding-left: 40px;">
                                    <i class="fas fa-hand-paper text-info"></i>
                                    <span class="text-muted">Manual:</span>
                                    <strong><?php echo $manual->authtime ? substr($manual->authtime, 0, 5) : '-'; ?></strong>
                                    <span class="badge bg-info" style="font-size: 0.7rem; padding: 2px 5px;"><?php echo safeEscape($manual->direction ?: 'N/A'); ?></span>
                                    <?php if (!empty($manual->creator_name)) : ?>
                                        <span class="text-muted" style="margin-left: 8px;"><i class="fas fa-user"></i> <?php echo safeEscape($manual->creator_name); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td colspan="5">
                                    <?php if (!empty($manual->notes)) : ?>
                                        <em style="font-size: 0.8rem;"><?php echo safeEscape($manual->notes); ?></em>
                                    <?php else : ?>
                                        <span class="text-muted" style="font-size: 0.8rem;">(Sin notas)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">
                                <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($this->pagination && $this->pagination->pagesTotal > 1): ?>
        <div class="pagination">
            <?php echo $this->pagination->getListFooter(); ?>
        </div>
        <?php endif; ?>

        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<script>
function exportData() {
    const form = document.getElementById('adminForm');
    const originalAction = form.action;
    form.action = '<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.export'); ?>';
    form.submit();
    form.action = originalAction;
}

function recalculateSummaries() {
    const dateFrom = document.getElementById('filter_date_from').value;
    const dateTo = document.getElementById('filter_date_to').value;
    
    if (!dateFrom || !dateTo) {
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ERROR_DATE_RANGE_REQUIRED'); ?>');
        return;
    }
    
    if (confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_RECALCULATE_CONFIRM'); ?>')) {
        window.location.href = '<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.recalculate'); ?>&date_from=' + dateFrom + '&date_to=' + dateTo;
    }
}

// Get current week dates (Monday to Sunday)
function getCurrentWeekDates() {
    const today = new Date();
    const day = today.getDay();
    const diff = today.getDate() - day + (day === 0 ? -6 : 1); // adjust when day is sunday
    const monday = new Date(today.setDate(diff));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    
    return {
        from: monday.toISOString().split('T')[0],
        to: sunday.toISOString().split('T')[0]
    };
}

// Get month dates (First to Last day)
function getMonthDates(year, month) {
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);
    
    return {
        from: firstDay.toISOString().split('T')[0],
        to: lastDay.toISOString().split('T')[0]
    };
}

// Show/hide month selector
function showExportOption(option) {
    document.getElementById('weekOption').style.display = option === 'week' ? 'block' : 'none';
    document.getElementById('monthOption').style.display = option === 'month' ? 'block' : 'none';
}

// Export to Excel
function exportToExcel(type) {
    let dates;
    
    if (type === 'week') {
        dates = getCurrentWeekDates();
    } else if (type === 'month') {
        const year = parseInt(document.getElementById('exportYear').value);
        const month = parseInt(document.getElementById('exportMonth').value);
        dates = getMonthDates(year, month);
    }
    
    window.location.href = '<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.exportExcel'); ?>&date_from=' + dates.from + '&date_to=' + dates.to;
    document.getElementById('exportExcelModal').style.display = 'none';
}
</script>

<!-- Export to Excel Modal -->
<div id="exportExcelModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #4CAF50;">
                <span class="icon-download"></span> <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_TO_EXCEL'); ?>
            </h3>
            <button onclick="document.getElementById('exportExcelModal').style.display='none'" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #666;">&times;</button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <p class="text-muted" style="margin-bottom: 15px;"><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_EXPORT_RANGE'); ?></p>
            
            <!-- Export Type Selection -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 10px;">
                    <input type="radio" name="exportType" value="week" checked onclick="showExportOption('week')" style="margin-right: 8px;">
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_CURRENT_WEEK'); ?></strong>
                    <small class="text-muted"> (<?php echo Text::_('COM_ORDENPRODUCCION_MONDAY_TO_SUNDAY'); ?>)</small>
                </label>
                <label style="display: block;">
                    <input type="radio" name="exportType" value="month" onclick="showExportOption('month')" style="margin-right: 8px;">
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_SELECT_MONTH'); ?></strong>
                    <small class="text-muted"> (<?php echo Text::_('COM_ORDENPRODUCCION_ANY_MONTH'); ?>)</small>
                </label>
            </div>
            
            <!-- Week Option (shown by default) -->
            <div id="weekOption" style="display: block; padding: 15px; background-color: #f5f5f5; border-radius: 5px; margin-bottom: 15px;">
                <p style="margin: 0;">
                    <span class="icon-calendar"></span> 
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_CURRENT_WEEK_WILL_EXPORT'); ?></strong>
                </p>
            </div>
            
            <!-- Month Option (hidden by default) -->
            <div id="monthOption" style="display: none; padding: 15px; background-color: #f5f5f5; border-radius: 5px; margin-bottom: 15px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php echo Text::_('COM_ORDENPRODUCCION_SELECT_YEAR'); ?>:
                    </label>
                    <select id="exportYear" class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php 
                        $currentYear = date('Y');
                        $previousYear = $currentYear - 1;
                        ?>
                        <option value="<?php echo $currentYear; ?>" selected><?php echo $currentYear; ?> (<?php echo Text::_('COM_ORDENPRODUCCION_CURRENT_YEAR'); ?>)</option>
                        <option value="<?php echo $previousYear; ?>"><?php echo $previousYear; ?> (<?php echo Text::_('COM_ORDENPRODUCCION_PREVIOUS_YEAR'); ?>)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                        <?php echo Text::_('COM_ORDENPRODUCCION_SELECT_MONTH'); ?>:
                    </label>
                    <select id="exportMonth" class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        <?php
                        $currentMonth = date('n');
                        $months = [
                            1 => Text::_('JANUARY'),
                            2 => Text::_('FEBRUARY'),
                            3 => Text::_('MARCH'),
                            4 => Text::_('APRIL'),
                            5 => Text::_('MAY'),
                            6 => Text::_('JUNE'),
                            7 => Text::_('JULY'),
                            8 => Text::_('AUGUST'),
                            9 => Text::_('SEPTEMBER'),
                            10 => Text::_('OCTOBER'),
                            11 => Text::_('NOVEMBER'),
                            12 => Text::_('DECEMBER')
                        ];
                        foreach ($months as $num => $name) {
                            $selected = ($num == $currentMonth) ? 'selected' : '';
                            echo '<option value="' . $num . '" ' . $selected . '>' . $name . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="document.getElementById('exportExcelModal').style.display='none'" class="btn btn-secondary">
                <span class="icon-cancel"></span> <?php echo Text::_('JCANCEL'); ?>
            </button>
            <button type="button" onclick="exportToExcel(document.querySelector('input[name=exportType]:checked').value)" class="btn btn-success btn-lg">
                <span class="icon-download"></span> <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_NOW'); ?>
            </button>
        </div>
    </div>
</div>

