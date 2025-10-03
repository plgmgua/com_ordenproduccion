<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

?>

<div class="com-ordenproduccion-technicians">
    <!-- Technicians Header -->
    <div class="technicians-header mb-4">
        <div class="row">
            <div class="col-md-8">
                <h1 class="technicians-title">
                    <i class="icon-users"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIANS_TITLE'); ?>
                </h1>
                <p class="technicians-subtitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIANS_SUBTITLE'); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="statistics-summary">
                    <small class="text-muted">
                        <?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_TECHNICIANS'); ?>: 
                        <strong><?php echo count($this->technicians); ?></strong> | 
                        <?php echo Text::_('COM_ORDENPRODUCCION_PRESENT_TODAY'); ?>: 
                        <strong><?php echo count($this->todaysTechnicians); ?></strong>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-primary">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo count($this->technicians); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_TECHNICIANS'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-checkmark"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo count($this->todaysTechnicians); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_PRESENT_TODAY'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-warning">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->statistics['busy_technicians'] ?? 0; ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_BUSY_TECHNICIANS'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-info">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->statistics['total_assignments'] ?? 0; ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_ASSIGNMENTS'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Today's Technicians -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-calendar"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_TODAYS_TECHNICIANS'); ?>
                    </h5>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshTodaysTechnicians">
                            <i class="icon-refresh"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_REFRESH'); ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($this->todaysTechnicians)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIAN_NAME'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_CHECK_IN_TIME'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->todaysTechnicians as $technician): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($technician->personname); ?></strong>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $this->formatTime($technician->auth_time); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo Text::_('COM_ORDENPRODUCCION_PRESENT'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary view-technician-stats" 
                                                        data-technician-id="<?php echo $technician->id; ?>"
                                                        data-technician-name="<?php echo htmlspecialchars($technician->personname); ?>">
                                                    <i class="icon-chart"></i>
                                                    <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_STATS'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="icon-users icon-3x text-muted"></i>
                            <p class="text-muted mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_NO_TECHNICIANS_TODAY'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- All Technicians -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-users"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_ALL_TECHNICIANS'); ?>
                    </h5>
                    <div class="card-tools">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="viewGrid">
                                <i class="icon-grid"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary active" id="viewList">
                                <i class="icon-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($this->technicians)): ?>
                        <div class="technicians-list" id="techniciansList">
                            <?php foreach ($this->technicians as $technician): ?>
                                <div class="technician-item">
                                    <div class="technician-info">
                                        <div class="technician-avatar">
                                            <i class="icon-user"></i>
                                        </div>
                                        <div class="technician-details">
                                            <h6 class="technician-name"><?php echo htmlspecialchars($technician->name); ?></h6>
                                            <p class="technician-specialization">
                                                <?php echo htmlspecialchars($technician->specialization ?? Text::_('COM_ORDENPRODUCCION_NO_SPECIALIZATION')); ?>
                                            </p>
                                            <div class="technician-stats">
                                                <span class="stat-item">
                                                    <i class="icon-list"></i>
                                                    <?php echo $technician->active_orders ?? 0; ?> <?php echo Text::_('COM_ORDENPRODUCCION_ACTIVE_ORDERS'); ?>
                                                </span>
                                                <span class="stat-item">
                                                    <i class="icon-checkmark"></i>
                                                    <?php echo $technician->completed_orders ?? 0; ?> <?php echo Text::_('COM_ORDENPRODUCCION_COMPLETED'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="technician-actions">
                                        <span class="badge bg-<?php echo $this->getStatusColor($technician->status ?? 'active'); ?>">
                                            <?php echo $this->getStatusText($technician->status ?? 'active'); ?>
                                        </span>
                                        <div class="workload-bar">
                                            <div class="progress">
                                                <div class="progress-bar bg-<?php echo $this->getWorkloadColor($this->getWorkloadPercentage($technician)); ?>" 
                                                     style="width: <?php echo $this->getWorkloadPercentage($technician); ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo round($this->getWorkloadPercentage($technician)); ?>% <?php echo Text::_('COM_ORDENPRODUCCION_WORKLOAD'); ?>
                                            </small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary view-technician-stats" 
                                                data-technician-id="<?php echo $technician->id; ?>"
                                                data-technician-name="<?php echo htmlspecialchars($technician->name); ?>">
                                            <i class="icon-chart"></i>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_STATS'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="icon-users icon-3x text-muted"></i>
                            <p class="text-muted mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_NO_TECHNICIANS'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-calendar"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_ATTENDANCE_HISTORY'); ?>
                    </h5>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="syncAttendance">
                            <i class="icon-sync"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_SYNC_ATTENDANCE'); ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($this->attendanceData)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIAN_NAME'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_DATE'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_CHECK_IN_TIME'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_CHECK_OUT_TIME'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_WORKING_HOURS'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->attendanceData as $attendance): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($attendance->personname); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $this->formatDate($attendance->auth_date); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo $this->formatTime($attendance->auth_time); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($attendance->checkout_time): ?>
                                                    <span class="badge bg-warning">
                                                        <?php echo $this->formatTime($attendance->checkout_time); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">
                                                        <?php echo Text::_('COM_ORDENPRODUCCION_STILL_WORKING'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($attendance->working_hours): ?>
                                                    <strong><?php echo $attendance->working_hours; ?>h</strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="icon-calendar icon-3x text-muted"></i>
                            <p class="text-muted mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_NO_ATTENDANCE_DATA'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Technician Stats Modal -->
<div class="modal fade" id="technicianStatsModal" tabindex="-1" aria-labelledby="technicianStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="technicianStatsModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIAN_STATISTICS'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="technicianStatsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_LOADING'); ?>...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCLOSE'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for AJAX requests -->
<form id="techniciansForm" style="display: none;">
    <input type="hidden" name="option" value="com_ordenproduccion">
    <input type="hidden" name="task" value="">
    <input type="hidden" name="format" value="json">
    <input type="hidden" name="<?php echo Factory::getSession()->getFormToken(); ?>" value="1">
</form>
