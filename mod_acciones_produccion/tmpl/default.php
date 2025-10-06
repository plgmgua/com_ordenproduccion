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
    <?php elseif ($orderId && $workOrderData): ?>
        
        <!-- Debug Information (remove in production) -->
        <div class="debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 12px; border: 1px solid #ccc;">
            <strong>Debug Info:</strong><br>
            Order ID: <?php echo $orderId; ?><br>
            Numero de Orden: <?php echo htmlspecialchars($workOrderData->numero_de_orden ?? 'NULL'); ?><br>
            Client Name: <?php echo htmlspecialchars($workOrderData->client_name ?? 'NULL'); ?><br>
            Status: <?php echo htmlspecialchars($workOrderData->status ?? 'NULL'); ?><br>
            Has Production Access: <?php echo $hasProductionAccess ? 'Yes' : 'No'; ?>
        </div>
        
        <!-- Work Order Information -->
        <div class="work-order-info mb-3">
            <h6 class="order-title">
                <i class="fas fa-file-alt"></i>
                Orden de Trabajo <?php echo htmlspecialchars($workOrderData->numero_de_orden ?? 'N/A'); ?>
            </h6>
            <div class="order-details">
                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($workOrderData->client_name ?? 'N/A'); ?></p>
                <p><strong>Estado:</strong> <?php echo htmlspecialchars($workOrderData->status ?? 'N/A'); ?></p>
            </div>
        </div>

        <!-- PDF Generation Button -->
        <div class="pdf-action">
            <form action="<?php echo $currentUrl; ?>" method="post" class="pdf-form">
                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="task" value="generate_pdf">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-file-pdf"></i>
                    Generar PDF
                </button>
            </form>
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

.work-order-info {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
    margin-bottom: 15px;
}

.order-title {
    color: #495057;
    font-size: 16px;
    margin-bottom: 10px;
    font-weight: 600;
}

.order-details p {
    margin: 5px 0;
    font-size: 14px;
    color: #6c757d;
}

.pdf-action {
    text-align: center;
}

.btn {
    font-size: 14px;
    padding: 10px 20px;
    font-weight: 600;
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
