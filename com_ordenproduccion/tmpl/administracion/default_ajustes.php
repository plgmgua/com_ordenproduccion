<?php
/**
 * Ajustes tab (Administracion only): subtabs for quotation settings, etc.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$activeSubTab = isset($this->activeSubTab) ? $this->activeSubTab : 'ajustes_cotizacion';
$app->input->set('subtab', $activeSubTab);

$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');
?>
<style>
.ajustes-subtabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 24px;
}
.ajustes-subtab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    transition: all 0.2s;
}
.ajustes-subtab:hover {
    color: #667eea;
    text-decoration: none;
}
.ajustes-subtab.subtab-active {
    color: #667eea;
    border-bottom-color: #667eea;
}
.subtab-content { margin-top: 0; }
</style>
<div class="ajustes-subtabs">
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=ajustes_cotizacion'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'ajustes_cotizacion' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_AJUSTES_COTIZACION'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=solicitud_orden'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'solicitud_orden' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-link"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_SOLICITUD_ORDEN'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=numeracion_ordenes'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'numeracion_ordenes' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-sort-numeric-up"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_NUMERACION_ORDENES'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=creacion_logs'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'creacion_logs' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-notes-medical"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_CREACION_LOGS'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=solicitud_cotizacion'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'solicitud_cotizacion' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-paper-plane"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_SOLICITUD_COTIZACION'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'flujos_aprobaciones' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-sitemap"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_FLUJOS_APROBACIONES'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'grupos_aprobaciones' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-users-cog"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_GRUPOS_APROBACIONES'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=anular_orden'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'anular_orden' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-ban"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_ANULAR_ORDEN'); ?>
    </a>
</div>
<div class="subtab-content">
    <?php if ($activeSubTab === 'ajustes_cotizacion'): ?>
        <?php include __DIR__ . '/default_ajustes_ajustes_cotizacion.php'; ?>
    <?php elseif ($activeSubTab === 'solicitud_orden'): ?>
        <?php include __DIR__ . '/default_ajustes_solicitud_orden.php'; ?>
    <?php elseif ($activeSubTab === 'numeracion_ordenes'): ?>
        <?php include __DIR__ . '/default_ajustes_numeracion_ordenes.php'; ?>
    <?php elseif ($activeSubTab === 'creacion_logs'): ?>
        <?php include __DIR__ . '/default_ajustes_creacion_logs.php'; ?>
    <?php elseif ($activeSubTab === 'solicitud_cotizacion'): ?>
        <?php echo $this->loadTemplate('solicitud_cotizacion'); ?>
    <?php elseif ($activeSubTab === 'flujos_aprobaciones'): ?>
        <?php include __DIR__ . '/default_ajustes_flujos_aprobaciones.php'; ?>
    <?php elseif ($activeSubTab === 'grupos_aprobaciones'): ?>
        <?php include __DIR__ . '/default_ajustes_grupos_aprobaciones.php'; ?>
    <?php elseif ($activeSubTab === 'anular_orden'): ?>
        <?php include __DIR__ . '/default_ajustes_anular_orden.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/default_ajustes_ajustes_cotizacion.php'; ?>
    <?php endif; ?>
</div>
