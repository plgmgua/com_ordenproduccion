<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$input = $app->input;
$activeTab = $input->get('tab', 'statistics', 'string');
?>

<style>
.admin-tabs {
    display: flex;
    gap: 0;
    border-bottom: 3px solid #dee2e6;
    margin-bottom: 30px;
}

.admin-tab {
    padding: 15px 30px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -3px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
}

.admin-tab:hover {
    color: #667eea;
    text-decoration: none;
}

.admin-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}

.admin-tab i {
    margin-right: 8px;
}

.tab-content {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="admin-tabs">
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=statistics'); ?>" 
       class="admin-tab <?php echo $activeTab === 'statistics' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_STATISTICS'); ?>
    </a>
    
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" 
       class="admin-tab <?php echo $activeTab === 'invoices' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_INVOICES'); ?>
    </a>
    
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'); ?>" 
       class="admin-tab <?php echo $activeTab === 'workorders' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_WORK_ORDERS'); ?>
    </a>
</div>

<div class="tab-content">
    <!-- DEBUG: Active tab: <?php echo $activeTab; ?> -->
    <?php if ($activeTab === 'statistics'): ?>
        <?php echo $this->loadTemplate('statistics'); ?>
    <?php elseif ($activeTab === 'invoices'): ?>
        <?php echo $this->loadTemplate('invoices'); ?>
    <?php elseif ($activeTab === 'workorders'): ?>
        <?php 
        // Simple direct include - bypass loadTemplate completely
        $templatePath = JPATH_ROOT . '/components/com_ordenproduccion/tmpl/administracion/default_workorders.php';
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo '<div style="padding: 20px; color: red;">Work orders template not found at: ' . $templatePath . '</div>';
        }
        ?>
    <?php else: ?>
        <!-- DEBUG: No matching tab found for: <?php echo $activeTab; ?> -->
    <?php endif; ?>
</div>

