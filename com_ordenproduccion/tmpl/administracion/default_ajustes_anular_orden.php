<?php
/**
 * Ajustes > Anular orden: set work order status to Anulada by order number (ord-0000 format).
 * Anulada orders are excluded from Estado de cuenta, Comprobantes de pago, and Rango de días.
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
            <i class="fas fa-ban"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_DESC'); ?>
        </p>

        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.anularOrden'); ?>" method="post" name="adminForm" id="ajustes-anular-orden-form" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>

            <div class="mb-4">
                <label for="jform_orden_de_trabajo" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_LABEL'); ?>
                </label>
                <input type="text" name="jform[orden_de_trabajo]" id="jform_orden_de_trabajo" class="form-control" style="max-width: 16rem;"
                       value=""
                       placeholder="ORD-006448"
                       title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_DESC_FIELD'); ?></div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-ban"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_ANULAR_ORDEN_BTN'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
