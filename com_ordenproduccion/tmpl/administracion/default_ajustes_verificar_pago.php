<?php
/**
 * Ajustes > Verificar Pago: force a payment proof to Verificado by PA-00000 number.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
HTMLHelper::_('form.csrf');

$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-check-circle"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_VERIFICAR_PAGO_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_VERIFICAR_PAGO_DESC'); ?>
        </p>

        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.forceVerifyPaymentProof'); ?>" method="post" name="adminForm" id="ajustes-verificar-pago-form" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>

            <div class="mb-4">
                <label for="jform_payment_proof_number" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_VERIFICAR_PAGO_LABEL'); ?>
                </label>
                <input type="text" name="jform[payment_proof_number]" id="jform_payment_proof_number" class="form-control" style="max-width: 16rem;"
                       value=""
                       placeholder="PA-00424"
                       title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_AJUSTES_VERIFICAR_PAGO_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_VERIFICAR_PAGO_DESC_FIELD'); ?></div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_VERIFICAR_PAGO_BTN'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
