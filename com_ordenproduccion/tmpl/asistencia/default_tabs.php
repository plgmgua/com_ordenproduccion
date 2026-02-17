<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

$activeTab = $this->activeTab ?? 'registro';
?>

<style>
.asistencia-tabs {
    display: flex;
    gap: 0;
    border-bottom: 3px solid #dee2e6;
    margin-bottom: 25px;
}

.asistencia-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -3px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
}

.asistencia-tab:hover {
    color: #007bff;
    text-decoration: none;
}

.asistencia-tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: rgba(0, 123, 255, 0.05);
}

.asistencia-tab i {
    margin-right: 8px;
}

.asistencia-tab-content {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="asistencia-tabs">
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=registro'); ?>"
       class="asistencia-tab <?php echo $activeTab === 'registro' ? 'active' : ''; ?>">
        <i class="icon-list"></i>
        <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TAB_REGISTRO', 'Registro', 'Registro'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=analisis'); ?>"
       class="asistencia-tab <?php echo $activeTab === 'analisis' ? 'active' : ''; ?>">
        <i class="icon-chart"></i>
        <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TAB_ANALISIS', 'Análisis', 'Análisis'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=festivos'); ?>"
       class="asistencia-tab <?php echo $activeTab === 'festivos' ? 'active' : ''; ?>">
        <i class="icon-calendar"></i>
        <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TAB_FESTIVOS', 'Festivos / Ausencias', 'Festivos / Ausencias'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=configuracion'); ?>"
       class="asistencia-tab <?php echo $activeTab === 'configuracion' ? 'active' : ''; ?>">
        <i class="icon-cog"></i>
        <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_TAB_CONFIGURACION', 'Configuración', 'Configuración'); ?>
    </a>
</div>

<div class="asistencia-tab-content">
    <?php if ($activeTab === 'registro'): ?>
        <?php echo $this->loadTemplate('registro'); ?>
    <?php elseif ($activeTab === 'analisis'): ?>
        <?php echo $this->loadTemplate('analisis'); ?>
    <?php elseif ($activeTab === 'festivos'): ?>
        <?php echo $this->loadTemplate('festivos'); ?>
    <?php elseif ($activeTab === 'configuracion'): ?>
        <?php echo $this->loadTemplate('configuracion'); ?>
    <?php else: ?>
        <?php echo $this->loadTemplate('registro'); ?>
    <?php endif; ?>
</div>
