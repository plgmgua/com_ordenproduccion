<?php
/**
 * Ajustes > Solicitud de Orden: URL to notify when finishing confirmar steps (with next order number).
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

$solicitudOrdenUrl = isset($this->solicitudOrdenUrl) ? trim((string) $this->solicitudOrdenUrl) : '';
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-link"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SOLICITUD_ORDEN_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SOLICITUD_ORDEN_DESC'); ?>
        </p>

        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.saveSolicitudOrden'); ?>" method="post" name="adminForm" id="ajustes-solicitud-orden-form" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>
            <?php if (!empty($this->returnUrlAjustesCotizacion)): ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($this->returnUrlAjustesCotizacion, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>

            <div class="mb-4">
                <label for="jform_solicitud_orden_url" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SOLICITUD_ORDEN_URL_LABEL'); ?>
                </label>
                <input type="url" name="jform[solicitud_orden_url]" id="jform_solicitud_orden_url" class="form-control" style="max-width: 36rem;"
                       value="<?php echo htmlspecialchars($solicitudOrdenUrl, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="https://...">
                <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_SOLICITUD_ORDEN_URL_DESC'); ?></div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo Text::_('JSAVE'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
