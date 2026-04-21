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
$canSeeAprobacionesTab = AccessHelper::canViewApprovalWorkflowTab();
$aprobacionesPendingCount = AccessHelper::getPendingApprovalCountForUser();

// Default tab: Ventas see resumen; Admin can use requested or resumen. workorders tab removed.
$activeTab = $input->get('tab', 'resumen', 'string');
if ($activeTab === 'workorders') {
    $activeTab = 'resumen';
}
if (!$canSeeAdminTabs && in_array($activeTab, ['invoices', 'herramientas', 'proveedores'], true)) {
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
    flex-wrap: wrap;
    gap: 0;
    border-bottom: 3px solid #dee2e6;
    margin-bottom: 30px;
}

.admin-tab {
    padding: 8px 12px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -3px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
    white-space: nowrap;
    text-align: center;
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
    display: block;
    font-size: 14px;
    margin-bottom: 3px;
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

    <?php if ($canSeeAprobacionesTab) : ?>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones'); ?>"
       class="admin-tab <?php echo $activeTab === 'aprobaciones' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-check"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_APROBACIONES'); ?>
        <?php if ($aprobacionesPendingCount > 0) : ?>
            <span class="badge bg-danger ms-1"><?php echo (int) $aprobacionesPendingCount; ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if ($canSeeAdminTabs) : ?>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>"
       class="admin-tab <?php echo $activeTab === 'invoices' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_INVOICES'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=proveedores'); ?>"
       class="admin-tab <?php echo $activeTab === 'proveedores' ? 'active' : ''; ?>">
        <i class="fas fa-truck-loading"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_PROVEEDORES'); ?>
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
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=cotizaciones'); ?>"
       class="admin-tab <?php echo $activeTab === 'ajustes' ? 'active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_TAB_AJUSTES'); ?>
    </a>
    <?php endif; ?>
</div>

<div class="tab-content">
    <?php if ($activeTab === 'resumen'): ?>
        <?php echo $this->loadTemplate('resumen'); ?>
    <?php elseif ($activeTab === 'invoices'): ?>
        <?php echo $this->loadTemplate('invoices'); ?>
    <?php elseif ($activeTab === 'statistics'): ?>
        <?php echo $this->loadTemplate('statistics'); ?>
    <?php elseif ($activeTab === 'reportes'): ?>
        <?php echo $this->loadTemplate('reportes'); ?>
    <?php elseif ($activeTab === 'clientes'): ?>
        <?php echo $this->loadTemplate('clientes'); ?>
    <?php elseif ($activeTab === 'proveedores'): ?>
        <?php echo $this->loadTemplate('proveedores'); ?>
    <?php elseif ($activeTab === 'herramientas'): ?>
        <?php echo $this->loadTemplate('herramientas'); ?>
    <?php elseif ($activeTab === 'ajustes'): ?>
        <?php echo $this->loadTemplate('ajustes'); ?>
    <?php elseif ($activeTab === 'aprobaciones'): ?>
        <?php echo $this->loadTemplate('aprobaciones'); ?>
    <?php else: ?>
        <?php echo $this->loadTemplate('resumen'); ?>
    <?php endif; ?>
</div>
