<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.framework');

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('com_ordenproduccion.dashboard', 'media/com_ordenproduccion/css/dashboard.css', [], ['version' => 'auto']);
?>

<div class="com-ordenproduccion-timeattendance">
    <!-- Dashboard Header -->
    <div class="dashboard-header mb-4">
        <div class="row">
            <div class="col-md-12">
                <h1 class="dashboard-title">
                    <i class="icon-clock"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIANS_TITLE'); ?>
                </h1>
                <p class="dashboard-subtitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_TECHNICIANS_SUBTITLE'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Quick Access Cards -->
    <div class="row mb-4">
        <!-- Employee Groups Card -->
        <div class="col-lg-6 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="icon-users" style="font-size: 48px; color: #007bff;"></i>
                    </div>
                    <h3 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUPS_TITLE'); ?></h3>
                    <p class="card-text text-muted">
                        <?php echo Text::_('Manage work schedules, shifts, and employee groups'); ?>
                    </p>
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=employeegroups'); ?>" 
                       class="btn btn-primary btn-lg">
                        <i class="icon-briefcase"></i>
                        <?php echo Text::_('Manage Groups'); ?>
                    </a>
                </div>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted"><?php echo Text::_('Set work hours'); ?></small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted"><?php echo Text::_('Grace periods'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employees Card -->
        <div class="col-lg-6 col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="icon-user" style="font-size: 48px; color: #28a745;"></i>
                    </div>
                    <h3 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEES_TITLE'); ?></h3>
                    <p class="card-text text-muted">
                        <?php echo Text::_('Manage employees, departments, and group assignments'); ?>
                    </p>
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=employees'); ?>" 
                       class="btn btn-success btn-lg">
                        <i class="icon-users"></i>
                        <?php echo Text::_('Manage Employees'); ?>
                    </a>
                </div>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted"><?php echo Text::_('Assign groups'); ?></small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted"><?php echo Text::_('Track info'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Reports Card -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="icon-calendar" style="font-size: 48px; color: #17a2b8;"></i>
                    </div>
                    <h3 class="card-title"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TITLE'); ?></h3>
                    <p class="card-text text-muted">
                        <?php echo Text::_('View attendance records, daily summaries, and reports'); ?>
                    </p>
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia'); ?>" 
                       class="btn btn-info btn-lg">
                        <i class="icon-calendar-2"></i>
                        <?php echo Text::_('View Attendance Records'); ?>
                    </a>
                </div>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted"><?php echo Text::_('Daily summaries'); ?></small>
                        </div>
                        <div class="col-4">
                            <small class="text-muted"><?php echo Text::_('Manual entry'); ?></small>
                        </div>
                        <div class="col-4">
                            <small class="text-muted"><?php echo Text::_('Export data'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h4><i class="icon-info"></i> <?php echo Text::_('Quick Guide'); ?></h4>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <h5><?php echo Text::_('1. Set Up Groups'); ?></h5>
                            <p class="text-muted">Create work schedule groups (morning shift, office staff, etc.) with specific start times, end times, and expected hours.</p>
                        </div>
                        <div class="col-md-4">
                            <h5><?php echo Text::_('2. Assign Employees'); ?></h5>
                            <p class="text-muted">Add employees to the system and assign them to appropriate work schedule groups.</p>
                        </div>
                        <div class="col-md-4">
                            <h5><?php echo Text::_('3. Monitor Attendance'); ?></h5>
                            <p class="text-muted">View real-time attendance data, check who's late or left early, and export reports.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.com-ordenproduccion-timeattendance .card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.com-ordenproduccion-timeattendance .card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.com-ordenproduccion-timeattendance .dashboard-title {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 10px;
}

.com-ordenproduccion-timeattendance .dashboard-subtitle {
    color: #7f8c8d;
    font-size: 16px;
}

.com-ordenproduccion-timeattendance .card-title {
    color: #2c3e50;
    font-weight: 600;
    margin-top: 15px;
}

.com-ordenproduccion-timeattendance .btn-lg {
    padding: 12px 32px;
    font-size: 16px;
    border-radius: 6px;
}

.com-ordenproduccion-timeattendance .card-footer {
    border-top: 1px solid #dee2e6;
    padding: 12px;
}
</style>
