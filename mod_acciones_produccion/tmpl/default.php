<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_acciones_produccion
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

$app = Factory::getApplication();
$user = Factory::getUser();
$currentUrl = Uri::current();
?>

<div class="mod-acciones-produccion">
    <?php if (!$hasProductionAccess): ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock"></i>
            <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ACCESS_DENIED'); ?>
        </div>
    <?php else: ?>
        
        <!-- Production Statistics -->
        <?php if ($showStatistics && $statistics): ?>
        <div class="production-stats mb-3">
            <h5 class="stats-title">
                <i class="fas fa-chart-bar"></i>
                <?php echo Text::_('MOD_ACCIONES_PRODUCCION_STATISTICS'); ?>
            </h5>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $statistics->total_orders; ?></span>
                    <span class="stat-label"><?php echo Text::_('MOD_ACCIONES_PRODUCCION_TOTAL_ORDERS'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $statistics->completed_orders; ?></span>
                    <span class="stat-label"><?php echo Text::_('MOD_ACCIONES_PRODUCCION_COMPLETED'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $statistics->in_progress_orders; ?></span>
                    <span class="stat-label"><?php echo Text::_('MOD_ACCIONES_PRODUCCION_IN_PROGRESS'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">Q. <?php echo number_format($statistics->total_value, 2); ?></span>
                    <span class="stat-label"><?php echo Text::_('MOD_ACCIONES_PRODUCCION_TOTAL_VALUE'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Production Actions -->
        <div class="production-actions">
            <h5 class="actions-title">
                <i class="fas fa-tools"></i>
                <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ACTIONS'); ?>
            </h5>
            
            <!-- PDF Generation Form -->
            <?php if ($showPdfButton): ?>
            <div class="action-item mb-3">
                <form action="<?php echo $currentUrl; ?>" method="post" class="pdf-form">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="task" value="generate_pdf">
                    <div class="form-group">
                        <label for="order_id" class="form-label">
                            <i class="fas fa-file-pdf"></i>
                            <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ORDER_ID'); ?>
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="order_id" 
                               name="order_id" 
                               value="<?php echo htmlspecialchars($orderId); ?>"
                               placeholder="<?php echo Text::_('MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER'); ?>"
                               required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-file-pdf"></i>
                        <?php echo Text::_('MOD_ACCIONES_PRODUCCION_GENERATE_PDF'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Excel Export Button -->
            <?php if ($showExcelButton): ?>
            <div class="action-item mb-3">
                <form action="<?php echo $currentUrl; ?>" method="post" class="excel-form">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="task" value="export_excel">
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-file-excel"></i>
                        <?php echo Text::_('MOD_ACCIONES_PRODUCCION_EXPORT_EXCEL'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Quick Links -->
            <div class="quick-links">
                <h6 class="links-title">
                    <i class="fas fa-link"></i>
                    <?php echo Text::_('MOD_ACCIONES_PRODUCCION_QUICK_LINKS'); ?>
                </h6>
                <div class="links-grid">
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i>
                        <?php echo Text::_('MOD_ACCIONES_PRODUCCION_VIEW_ORDERS'); ?>
                    </a>
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=production'); ?>" 
                       class="btn btn-outline-info btn-sm">
                        <i class="fas fa-cogs"></i>
                        <?php echo Text::_('MOD_ACCIONES_PRODUCCION_PRODUCTION_PANEL'); ?>
                    </a>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.mod-acciones-produccion {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.production-stats {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.stats-title {
    color: #495057;
    font-size: 14px;
    margin-bottom: 10px;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.stat-item {
    text-align: center;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.stat-number {
    display: block;
    font-size: 18px;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    display: block;
    font-size: 11px;
    color: #6c757d;
    margin-top: 2px;
}

.production-actions {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.actions-title, .links-title {
    color: #495057;
    font-size: 14px;
    margin-bottom: 10px;
    font-weight: 600;
}

.action-item {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 15px;
}

.action-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.form-label {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.form-control {
    font-size: 12px;
    padding: 6px 8px;
}

.btn {
    font-size: 12px;
    padding: 6px 12px;
}

.quick-links {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.links-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.alert {
    font-size: 12px;
    padding: 10px;
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .links-grid {
        grid-template-columns: 1fr;
    }
}
</style>
