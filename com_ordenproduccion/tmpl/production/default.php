<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Production\HtmlView $this */

$stats = $this->statistics;
?>

<div class="com-ordenproduccion-production">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTION_ACTIONS_TITLE'); ?>
                        </h1>
                        <p class="page-description">
                            <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTION_ACTIONS_DESC'); ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BACK_TO_ORDERS'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTION_STATISTICS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-clipboard-list text-primary"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats['total_orders'] ?? 0; ?></h3>
                                        <p><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_ORDERS'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle text-success"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats['completed_orders'] ?? 0; ?></h3>
                                        <p><?php echo Text::_('COM_ORDENPRODUCCION_COMPLETED_ORDERS'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-clock text-warning"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo $stats['in_progress_orders'] ?? 0; ?></h3>
                                        <p><?php echo Text::_('COM_ORDENPRODUCCION_IN_PROGRESS_ORDERS'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-dollar-sign text-info"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3>Q. <?php echo number_format($stats['total_value'] ?? 0, 2); ?></h3>
                                        <p><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_VALUE'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Actions -->
        <div class="row">
            <!-- PDF Generation -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-pdf"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_PDF_GENERATION'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><?php echo Text::_('COM_ORDENPRODUCCION_PDF_GENERATION_DESC'); ?></p>
                        
                        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=production.generatePDF'); ?>" 
                              method="post" class="form-inline">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <div class="form-group mr-3">
                                <label for="order_id" class="sr-only"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_ID'); ?></label>
                                <input type="number" 
                                       class="form-control" 
                                       id="order_id" 
                                       name="id" 
                                       placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ORDER_ID_PLACEHOLDER'); ?>" 
                                       required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_GENERATE_PDF'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Excel Export -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-excel"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_EXCEL_EXPORT'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><?php echo Text::_('COM_ORDENPRODUCCION_EXCEL_EXPORT_DESC'); ?></p>
                        
                        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=production.exportExcel'); ?>" 
                              method="post">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date"><?php echo Text::_('COM_ORDENPRODUCCION_START_DATE'); ?></label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="start_date" 
                                               name="start_date" 
                                               value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date"><?php echo Text::_('COM_ORDENPRODUCCION_END_DATE'); ?></label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="end_date" 
                                               name="end_date" 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="status"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_FILTER'); ?></label>
                                <select class="form-control" id="status" name="status">
                                    <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ALL_STATUSES'); ?></option>
                                    <option value="Nuevo"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_NEW'); ?></option>
                                    <option value="En Proceso"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_IN_PROGRESS'); ?></option>
                                    <option value="Terminada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_COMPLETED'); ?></option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-file-excel"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_EXCEL'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_QUICK_ACTIONS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" 
                                   class="btn btn-outline-primary btn-block">
                                    <i class="fas fa-list"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_ORDERS'); ?>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=production&layout=statistics'); ?>" 
                                   class="btn btn-outline-info btn-block">
                                    <i class="fas fa-chart-line"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_DETAILED_STATISTICS'); ?>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=settings'); ?>" 
                                   class="btn btn-outline-secondary btn-block">
                                    <i class="fas fa-cog"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_SETTINGS'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    display: flex;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.stat-icon {
    font-size: 2.5rem;
    margin-right: 20px;
}

.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: #333;
}

.stat-content p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: none;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.btn-block {
    margin-bottom: 10px;
}
</style>
