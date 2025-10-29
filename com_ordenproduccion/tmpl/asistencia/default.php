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
                    <div class="col-md-3">
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
                    <div class="col-md-1 d-flex align-items-end">
                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia'); ?>" 
                           class="btn btn-secondary w-100" style="font-size: 0.8rem; padding: 0.375rem 0.5rem;">
                            <span class="icon-delete"></span>
                        </a>
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
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.regenerateAll'); ?>" 
               class="btn btn-warning" 
               onclick="return confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_REGENERATE_ALL_CONFIRM'); ?>');">
                <span class="icon-loop"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_REGENERATE_ALL'); ?>
            </a>
            <button type="button" class="btn btn-info" onclick="exportData()">
                <span class="icon-download"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EXPORT'); ?>
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
                        <th style="width: 90px;">Fecha</th>
                        <th style="width: 70px;">Entrada</th>
                        <th style="width: 70px;">Salida</th>
                        <th style="width: 80px;">Horas</th>
                        <th style="width: 80px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($this->items)): ?>
                        <?php foreach ($this->items as $item): ?>
                        <tr class="<?php echo $item->is_late ? 'table-warning' : ''; ?>">
                            <td>
                                <strong style="font-size: 0.85rem;"><?php echo safeEscape($item->personname); ?></strong>
                                <?php if ($item->is_late): ?>
                                    <span class="badge bg-warning text-dark" style="font-size: 0.6rem; padding: 2px 5px;">Tarde</span>
                                <?php endif; ?>
                                <?php if ($item->is_early_exit): ?>
                                    <span class="badge bg-info" style="font-size: 0.6rem; padding: 2px 5px;">Salida T.</span>
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
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
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
</script>

