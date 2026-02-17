<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('form.validate');
?>

<div class="com-ordenproduccion-asistencia">
    <div class="page-header">
        <h1><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TITLE'); ?></h1>
    </div>

    <?php echo $this->loadTemplate('tabs'); ?>
</div>
