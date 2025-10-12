#!/bin/bash
# Manual fix for workorders template - run this on your server

echo "Fixing workorders template loading..."

# Navigate to the component directory
cd /var/www/grimpsa_webserver/components/com_ordenproduccion

# Backup the current file
cp tmpl/administracion/default_tabs.php tmpl/administracion/default_tabs.php.backup

# Create the fixed version
cat > tmpl/administracion/default_tabs.php << 'EOF'
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
    margin-bottom: 30px;
    border-bottom: 2px solid #dee2e6;
    background: white;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.admin-tab {
    padding: 15px 25px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: bold;
    color: #666;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-tab:hover {
    color: #667eea;
    background: #f8f9fa;
}

.admin-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: #f8f9fa;
}

.tab-content {
    background: white;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-height: 400px;
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
    <?php if ($activeTab === 'statistics'): ?>
        <?php echo $this->loadTemplate('statistics'); ?>
    <?php elseif ($activeTab === 'invoices'): ?>
        <?php echo $this->loadTemplate('invoices'); ?>
    <?php elseif ($activeTab === 'workorders'): ?>
        <?php 
        // Direct include - bypass loadTemplate completely
        $templatePath = JPATH_ROOT . '/components/com_ordenproduccion/tmpl/administracion/default_workorders.php';
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo '<div style="padding: 20px; color: red;">Work orders template not found at: ' . $templatePath . '</div>';
        }
        ?>
    <?php endif; ?>
</div>
EOF

echo "✅ Template fixed!"
echo "Clearing cache..."
rm -rf /var/www/grimpsa_webserver/cache/* 2>/dev/null
echo "✅ Done! Please refresh your Work Orders tab."
