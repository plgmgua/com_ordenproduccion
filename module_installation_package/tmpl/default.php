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
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$currentUrl = Uri::current();
?>

<div class="mod-acciones-produccion">
    <?php if (!$hasProductionAccess): ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock"></i>
            <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ACCESS_DENIED'); ?>
        </div>
    <?php elseif ($orderId): ?>
        
        <!-- PDF Generation Button -->
        <div class="pdf-action">
            <a href="index.php?option=com_ordenproduccion&task=orden.generatePdf&id=<?php echo $orderId; ?>" 
               target="_blank" 
               class="btn btn-primary btn-block">
                <i class="fas fa-file-pdf"></i>
                Generar PDF
            </a>
        </div>

    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No se encontró información de la orden de trabajo.
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

.pdf-action {
    text-align: center;
}

.btn {
    font-size: 14px;
    padding: 10px 20px;
    font-weight: 600;
    width: 100%;
}

.alert {
    font-size: 14px;
    padding: 15px;
    margin-bottom: 0;
    text-align: center;
}

.alert i {
    margin-right: 8px;
}
</style>
