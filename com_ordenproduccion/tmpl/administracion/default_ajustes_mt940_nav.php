<?php
/**
 * Ajustes > MT-940 inner navigation (settings | import).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$mt940ActiveSubTab = isset($this->activeSubTab) ? (string) $this->activeSubTab : 'mt940';
?>
<div class="ajustes-subtabs mb-3" style="border-bottom-width: 1px;">
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=mt940'); ?>"
       class="ajustes-subtab <?php echo $mt940ActiveSubTab === 'mt940' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_MT940_SUBTAB_CONFIG'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=mt940_importar'); ?>"
       class="ajustes-subtab <?php echo $mt940ActiveSubTab === 'mt940_importar' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-file-import"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_SUBTAB_IMPORTAR'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=mt940_registro'); ?>"
       class="ajustes-subtab <?php echo $mt940ActiveSubTab === 'mt940_registro' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_MT940_SUBTAB_REGISTRO'); ?>
    </a>
</div>
