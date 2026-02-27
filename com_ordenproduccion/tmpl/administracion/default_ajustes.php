<?php
/**
 * Ajustes tab (Administracion only): subtabs for Cotizaciones, etc.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$activeSubTab = isset($this->activeSubTab) ? $this->activeSubTab : 'cotizaciones';
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
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=cotizaciones'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'cotizaciones' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-file-invoice"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_COTIZACIONES'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=ajustes_cotizacion'); ?>"
       class="ajustes-subtab <?php echo $activeSubTab === 'ajustes_cotizacion' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SUBTAB_AJUSTES_COTIZACION'); ?>
    </a>
</div>
<div class="subtab-content">
    <?php if ($activeSubTab === 'ajustes_cotizacion'): ?>
        <?php include __DIR__ . '/default_ajustes_ajustes_cotizacion.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/default_ajustes_cotizaciones.php'; ?>
    <?php endif; ?>
</div>
