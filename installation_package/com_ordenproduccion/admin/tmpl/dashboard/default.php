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
use Joomla\CMS\Uri\Uri;

?>

<div class="com-ordenproduccion-dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header mb-4">
        <div class="row">
            <div class="col-md-8">
                <h1 class="dashboard-title">
                    <i class="icon-dashboard"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_DASHBOARD'); ?>
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_DASHBOARD_SUBTITLE'); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="version-info">
                    <small class="text-muted">
                        <?php echo Text::_('COM_ORDENPRODUCCION_VERSION'); ?>: 
                        <strong><?php echo $this->versionInfo['version']; ?></strong>
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
                        <i class="icon-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['total_orders']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_ORDERS'); ?></p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" class="btn btn-sm btn-primary">
                        <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_ALL'); ?>
                    </a>
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
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['pending_orders']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_PENDING_ORDERS'); ?></p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes&filter[status]=nueva'); ?>" class="btn btn-sm btn-warning">
                        <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_PENDING'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-info">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-checkmark"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['completed_orders']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_COMPLETED_ORDERS'); ?></p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes&filter[status]=terminada'); ?>" class="btn btn-sm btn-info">
                        <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_COMPLETED'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['active_technicians']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIVE_TECHNICIANS'); ?></p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=technicians'); ?>" class="btn btn-sm btn-success">
                        <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_TECHNICIANS'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Statistics -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card stat-card stat-card-danger">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-warning"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['overdue_orders']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_OVERDUE_ORDERS'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card stat-card stat-card-secondary">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['orders_due_today']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_ORDERS_DUE_TODAY'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card stat-card stat-card-dark">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-cog"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->formatNumber($this->statistics['in_process_orders']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_IN_PROCESS_ORDERS'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Recent Orders -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-list"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RECENT_ORDERS'); ?>
                    </h5>
                    <div class="card-tools">
                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" class="btn btn-sm btn-primary">
                            <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_ALL'); ?>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($this->recentOrders)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_DELIVERY_DATE'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_CREATED'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->recentOrders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order->orden_de_trabajo); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($order->nombre_del_cliente); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $this->getStatusColor($order->status); ?>">
                                                    <?php echo $this->getStatusText($order->status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order->fecha_de_entrega): ?>
                                                    <?php echo Factory::getDate($order->fecha_de_entrega)->format('d/m/Y'); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo Factory::getDate($order->created)->format('d/m/Y H:i'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $order->id); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="icon-edit"></i>
                                                    <?php echo Text::_('COM_ORDENPRODUCCION_EDIT'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="icon-list icon-3x text-muted"></i>
                            <p class="text-muted mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_NO_RECENT_ORDERS'); ?></p>
                            <?php if ($this->hasPermission('core.create')): ?>
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit'); ?>" class="btn btn-primary">
                                    <i class="icon-plus"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_CREATE_FIRST_ORDER'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Calendar and Quick Actions -->
        <div class="col-lg-4">
            <!-- Calendar Widget -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-calendar"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDERS_CALENDAR'); ?>
                    </h5>
                    <div class="card-tools">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="prevMonth">
                                <i class="icon-chevron-left"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="nextMonth">
                                <i class="icon-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="calendar-widget">
                        <div class="calendar-header">
                            <h6 id="calendarTitle">
                                <?php echo $this->getMonthName($this->currentMonth) . ' ' . $this->currentYear; ?>
                            </h6>
                        </div>
                        <div class="calendar-grid" id="calendarGrid">
                            <!-- Calendar will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-lightning"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_QUICK_ACTIONS'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($this->hasPermission('core.create')): ?>
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit'); ?>" class="btn btn-primary">
                                <i class="icon-plus"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_NEW_ORDER'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" class="btn btn-outline-primary">
                            <i class="icon-list"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_ORDERS'); ?>
                        </a>
                        
                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=technicians'); ?>" class="btn btn-outline-primary">
                            <i class="icon-users"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_TECHNICIANS'); ?>
                        </a>
                        
                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=webhook'); ?>" class="btn btn-outline-primary">
                            <i class="icon-link"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_CONFIG'); ?>
                        </a>
                        
                        <?php if ($this->hasPermission('core.admin')): ?>
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=debug'); ?>" class="btn btn-outline-secondary">
                                <i class="icon-bug"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_CONSOLE'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for AJAX requests -->
<form id="dashboardForm" style="display: none;">
    <input type="hidden" name="option" value="com_ordenproduccion">
    <input type="hidden" name="task" value="">
    <input type="hidden" name="format" value="json">
    <input type="hidden" name="<?php echo Factory::getSession()->getFormToken(); ?>" value="1">
</form>
