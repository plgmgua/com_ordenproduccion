<?php
/**
 * Ajustes > Cotizaciones: reset pre-cotizaciones and cotizaciones (delete all records).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-file-invoice"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACIONES_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACIONES_DESC'); ?>
        </p>
        <p>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reset-cotizaciones-modal">
                <i class="fas fa-trash-alt"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_COTIZACIONES_BTN'); ?>
            </button>
        </p>
    </div>
</div>

<!-- Confirmation modal -->
<div class="modal fade" id="reset-cotizaciones-modal" tabindex="-1" aria-labelledby="reset-cotizaciones-modal-label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="reset-cotizaciones-modal-label">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_COTIZACIONES_CONFIRM_TITLE'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_COTIZACIONES_CONFIRM_MSG'); ?>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.resetCotizacionesPrecotizaciones'); ?>" method="post" class="d-inline">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="confirm" value="1" />
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_RESET_COTIZACIONES_CONFIRM_BTN'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
