<?php
/**
 * Ajustes → Numeración de órdenes de trabajo (siguiente secuencia, prefijo, formato).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
HTMLHelper::_('form.csrf');

$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView|\Grimpsa\Component\Ordenproduccion\Site\View\Productos\HtmlView $this */

$row = $this->workOrderNumbering;
if (!\is_object($row)) {
    $sm  = new SettingsModel();
    $row = $sm->getWorkOrderNumberingRow();
}

$nextNum  = (int) ($row->next_order_number ?? 1000);
$prefix   = (string) ($row->order_prefix ?? 'ORD');
$format   = (string) ($row->order_format ?? 'PREFIX-NUMBER');
$preview  = (new SettingsModel())->getNextOrderNumberPreview();
$resyncUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.resyncWorkOrderNumbering', false);
$saveUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveWorkOrderNumbering', false);

$ocRow = $this->ordenCompraNumbering ?? null;
if (!\is_object($ocRow)) {
    $ocRow = (new SettingsModel())->getOrdenCompraNumberingRow();
}
$ocNext   = (int) ($ocRow->next_orden_compra_number ?? 1);
$ocPrefix = (string) ($ocRow->orden_compra_prefix ?? 'ORC');
$ocWidth  = (int) ($ocRow->orden_compra_number_width ?? 5);
$ocPreview = (new SettingsModel())->getNextOrdenCompraNumberPreview();
$ocSaveUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveOrdenCompraNumbering', false);
$ocResyncUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.resyncOrdenCompraNumbering', false);
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-sort-numeric-up"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_DESC'); ?>
        </p>

        <form action="<?php echo htmlspecialchars($saveUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="ajustes-numeracion-ordenes-form" class="mb-4">
            <?php echo HTMLHelper::_('form.token'); ?>
            <?php if (!empty($this->returnUrlAjustesCotizacion)) : ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($this->returnUrlAjustesCotizacion, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="jform_next_order_number" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER_LABEL'); ?></label>
                    <input type="number" name="jform[next_order_number]" id="jform_next_order_number" class="form-control" min="1" max="999999" required
                           value="<?php echo (int) $nextNum; ?>">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER_DESC'); ?></div>
                </div>
                <div class="col-md-4">
                    <label for="jform_order_prefix" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_PREFIX_LABEL'); ?></label>
                    <input type="text" name="jform[order_prefix]" id="jform_order_prefix" class="form-control" maxlength="10" required
                           value="<?php echo htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_PREFIX_DESC'); ?></div>
                </div>
                <div class="col-md-4">
                    <label for="jform_order_format" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_FORMAT_LABEL'); ?></label>
                    <select name="jform[order_format]" id="jform_order_format" class="form-select" required>
                        <option value="PREFIX-NUMBER" <?php echo $format === 'PREFIX-NUMBER' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_FORMAT_PREFIX_NUMBER'); ?></option>
                        <option value="NUMBER" <?php echo $format === 'NUMBER' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_FORMAT_NUMBER_ONLY'); ?></option>
                        <option value="PREFIX-NUMBER-YEAR" <?php echo $format === 'PREFIX-NUMBER-YEAR' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_FORMAT_PREFIX_NUMBER_YEAR'); ?></option>
                        <option value="NUMBER-YEAR" <?php echo $format === 'NUMBER-YEAR' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_FORMAT_NUMBER_YEAR'); ?></option>
                    </select>
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_FORMAT_DESC'); ?></div>
                </div>
            </div>

            <p class="mt-3 mb-2 small">
                <strong><?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER_WILL_BE'); ?>:</strong>
                <span id="numeracion-ordenes-preview" class="text-primary"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></span>
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo Text::_('JSAVE'); ?>
                </button>
            </div>
        </form>

        <hr class="my-4">

        <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SYNC_TITLE'); ?></h3>
        <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SYNC_DESC'); ?></p>
        <form action="<?php echo htmlspecialchars($resyncUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post"
              onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SYNC_CONFIRM')); ?>);">
            <?php echo HTMLHelper::_('form.token'); ?>
            <?php if (!empty($this->returnUrlAjustesCotizacion)) : ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($this->returnUrlAjustesCotizacion, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_ORDEN_SYNC_BTN'); ?>
            </button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_DESC'); ?>
        </p>

        <form action="<?php echo htmlspecialchars($ocSaveUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="ajustes-numeracion-oc-form" class="mb-4">
            <?php echo HTMLHelper::_('form.token'); ?>
            <?php if (!empty($this->returnUrlAjustesCotizacion)) : ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($this->returnUrlAjustesCotizacion, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="jform_oc_next_orden_compra_number" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_NEXT_LABEL'); ?></label>
                    <input type="number" name="jform_oc[next_orden_compra_number]" id="jform_oc_next_orden_compra_number" class="form-control" min="1" max="999999" required
                           value="<?php echo (int) $ocNext; ?>">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_NEXT_DESC'); ?></div>
                </div>
                <div class="col-md-4">
                    <label for="jform_oc_orden_compra_prefix" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_PREFIX_LABEL'); ?></label>
                    <input type="text" name="jform_oc[orden_compra_prefix]" id="jform_oc_orden_compra_prefix" class="form-control" maxlength="10" required
                           value="<?php echo htmlspecialchars($ocPrefix, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_PREFIX_DESC'); ?></div>
                </div>
                <div class="col-md-4">
                    <label for="jform_oc_orden_compra_number_width" class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_WIDTH_LABEL'); ?></label>
                    <input type="number" name="jform_oc[orden_compra_number_width]" id="jform_oc_orden_compra_number_width" class="form-control" min="3" max="8" required
                           value="<?php echo (int) $ocWidth; ?>">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_WIDTH_DESC'); ?></div>
                </div>
            </div>

            <p class="mt-3 mb-2 small">
                <strong><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_PREVIEW_LABEL'); ?>:</strong>
                <span id="numeracion-oc-preview" class="text-primary"><?php echo htmlspecialchars($ocPreview, ENT_QUOTES, 'UTF-8'); ?></span>
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo Text::_('JSAVE'); ?>
                </button>
            </div>
        </form>

        <hr class="my-4">

        <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SYNC_TITLE'); ?></h3>
        <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SYNC_DESC'); ?></p>
        <form action="<?php echo htmlspecialchars($ocResyncUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post"
              onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SYNC_CONFIRM')); ?>);">
            <?php echo HTMLHelper::_('form.token'); ?>
            <?php if (!empty($this->returnUrlAjustesCotizacion)) : ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($this->returnUrlAjustesCotizacion, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sync-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_NUMERACION_OC_SYNC_BTN'); ?>
            </button>
        </form>
    </div>
</div>
<script>
(function () {
    function padNum(n) {
        var s = String(Math.max(0, parseInt(n, 10) || 0));
        while (s.length < 6) { s = '0' + s; }
        return s;
    }
    function buildPreview(prefix, format, num) {
        var n = padNum(num);
        var y = String(new Date().getFullYear());
        switch (format) {
            case 'NUMBER':
                return n;
            case 'NUMBER-YEAR':
                return n + '-' + y;
            case 'PREFIX-NUMBER-YEAR':
                return prefix + '-' + n + '-' + y;
            case 'PREFIX-NUMBER':
            default:
                return prefix + '-' + n;
        }
    }
    var p = document.getElementById('jform_order_prefix');
    var f = document.getElementById('jform_order_format');
    var o = document.getElementById('jform_next_order_number');
    var out = document.getElementById('numeracion-ordenes-preview');
    function refresh() {
        if (!out || !p || !f || !o) return;
        out.textContent = buildPreview(p.value || 'ORD', f.value || 'PREFIX-NUMBER', o.value || '0');
    }
    if (p) p.addEventListener('input', refresh);
    if (f) f.addEventListener('change', refresh);
    if (o) o.addEventListener('input', refresh);

    function padWidth(num, w) {
        var n = Math.max(0, parseInt(num, 10) || 0);
        var width = Math.max(3, Math.min(8, parseInt(w, 10) || 5));
        var s = String(n);
        while (s.length < width) { s = '0' + s; }
        return s;
    }
    var ocp = document.getElementById('jform_oc_orden_compra_prefix');
    var ocw = document.getElementById('jform_oc_orden_compra_number_width');
    var ocn = document.getElementById('jform_oc_next_orden_compra_number');
    var ocOut = document.getElementById('numeracion-oc-preview');
    function refreshOc() {
        if (!ocOut || !ocp || !ocw || !ocn) return;
        var pref = (ocp.value || 'ORC').trim() || 'ORC';
        ocOut.textContent = pref + '-' + padWidth(ocn.value, ocw.value);
    }
    if (ocp) ocp.addEventListener('input', refreshOc);
    if (ocw) ocw.addEventListener('input', refreshOc);
    if (ocn) ocn.addEventListener('input', refreshOc);
})();
</script>
