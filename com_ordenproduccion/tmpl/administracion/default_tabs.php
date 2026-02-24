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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

$app = Factory::getApplication();
$input = $app->input;

// Ventas: can access Resumen, Estadísticas, Reportes, Estado de cuenta (own data only)
// Administracion/Admon: can access all tabs and see all data
$isVentas = AccessHelper::isInVentasGroup();
$isAdministracionOrAdmon = AccessHelper::isInAdministracionOrAdmonGroup();
$canSeeVentasTabs = $isVentas || $isAdministracionOrAdmon;
$canSeeAdminTabs = $isAdministracionOrAdmon;

// Default tab: Ventas see resumen; Admin can use requested or resumen
$activeTab = $input->get('tab', 'resumen', 'string');
if (!$canSeeAdminTabs && in_array($activeTab, ['workorders', 'invoices', 'herramientas'], true)) {
    $activeTab = 'resumen';
}

// Ensure language is loaded for tabs
$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');
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
    <?php if ($canSeeVentasTabs) : ?>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen'); ?>"
       class="admin-tab <?php echo $activeTab === 'resumen' ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_RESUMEN'); ?>
    </a>
    <?php endif; ?>

    <?php if ($canSeeAdminTabs) : ?>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'); ?>"
       class="admin-tab <?php echo $activeTab === 'workorders' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_WORK_ORDERS'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>"
       class="admin-tab <?php echo $activeTab === 'invoices' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_INVOICES'); ?>
    </a>
    <?php endif; ?>

    <?php if ($canSeeVentasTabs) : ?>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=statistics'); ?>"
       class="admin-tab <?php echo $activeTab === 'statistics' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_STATISTICS'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=reportes'); ?>"
       class="admin-tab <?php echo $activeTab === 'reportes' ? 'active' : ''; ?>">
        <i class="fas fa-file-alt"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_REPORTES'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=clientes'); ?>"
       class="admin-tab <?php echo $activeTab === 'clientes' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_ESTADO_DE_CUENTA'); ?>
    </a>
    <?php endif; ?>

    <?php if ($canSeeAdminTabs) : ?>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=herramientas'); ?>"
       class="admin-tab <?php echo $activeTab === 'herramientas' ? 'active' : ''; ?>">
        <i class="fas fa-tools"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_HERRAMIENTAS'); ?>
    </a>
    <?php endif; ?>
</div>

<div class="tab-content">
    <?php if ($activeTab === 'resumen'): ?>
        <?php echo $this->loadTemplate('resumen'); ?>
    <?php elseif ($activeTab === 'workorders'): ?>
        <?php
        $workOrders = $this->workOrders ?? [];
        $pagination = $this->workOrdersPagination ?? null;
        $state = $this->state ?? null;
        $templatePath = JPATH_ROOT . '/components/com_ordenproduccion/tmpl/administracion/default_workorders.php';
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo '<div style="padding: 20px; color: red;">Work orders template not found at: ' . $templatePath . '</div>';
        }
        ?>
    <?php elseif ($activeTab === 'invoices'): ?>
        <?php echo $this->loadTemplate('invoices'); ?>
    <?php elseif ($activeTab === 'statistics'): ?>
        <?php echo $this->loadTemplate('statistics'); ?>
    <?php elseif ($activeTab === 'reportes'): ?>
        <?php echo $this->loadTemplate('reportes'); ?>
    <?php elseif ($activeTab === 'clientes'): ?>
        <?php echo $this->loadTemplate('clientes'); ?>
    <?php elseif ($activeTab === 'herramientas'): ?>
        <?php echo $this->loadTemplate('herramientas'); ?>
    <?php else: ?>
        <?php echo $this->loadTemplate('resumen'); ?>
    <?php endif; ?>
</div>
