<?php
/**
 * Ajustes > Ajustes de Cotización: Encabezado, Términos y Condiciones, Pie de página (WYSIWYG) for PDF cotización.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
HTMLHelper::_('form.csrf');

// Ensure component language is loaded so labels translate (e.g. when included from Productos view)
$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$settings = isset($this->cotizacionPdfSettings) && is_array($this->cotizacionPdfSettings)
    ? $this->cotizacionPdfSettings
    : ['encabezado' => '', 'terminos_condiciones' => '', 'pie_pagina' => '', 'encabezado_x' => 15, 'encabezado_y' => 15, 'table_x' => 0, 'table_y' => 0, 'terminos_x' => 0, 'terminos_y' => 0, 'pie_x' => 0, 'pie_y' => 0];
$encabezado = isset($settings['encabezado']) ? $settings['encabezado'] : '';
$terminos = isset($settings['terminos_condiciones']) ? $settings['terminos_condiciones'] : '';
$pie = isset($settings['pie_pagina']) ? $settings['pie_pagina'] : '';
$encabezado_x = isset($settings['encabezado_x']) ? (float) $settings['encabezado_x'] : 15;
$encabezado_y = isset($settings['encabezado_y']) ? (float) $settings['encabezado_y'] : 15;
$table_x = isset($settings['table_x']) ? (float) $settings['table_x'] : 0;
$table_y = isset($settings['table_y']) ? (float) $settings['table_y'] : 0;
$terminos_x = isset($settings['terminos_x']) ? (float) $settings['terminos_x'] : 0;
$terminos_y = isset($settings['terminos_y']) ? (float) $settings['terminos_y'] : 0;
$pie_x = isset($settings['pie_x']) ? (float) $settings['pie_x'] : 0;
$pie_y = isset($settings['pie_y']) ? (float) $settings['pie_y'] : 0;

try {
    $editorName = $app->get('editor', 'tinymce');
    $editor = Editor::getInstance($editorName);
} catch (\Exception $e) {
    $editor = null;
}
$editorWidth = '100%';
$editorHeight = '280';
$editorCols = 75;
$editorRows = 20;
$editorButtons = true;
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-file-pdf"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_DESC'); ?>
        </p>

        <div class="alert alert-info mb-4">
            <h3 class="alert-heading h6 mb-2">
                <i class="fas fa-code"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_VARS_HEADING'); ?>
            </h3>
            <p class="mb-2 small"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_VARS_DESC'); ?></p>
            <ul class="list-unstyled mb-0 small">
                <?php foreach (CotizacionPdfHelper::getPlaceholdersForUi() as $placeholder => $labelKey): ?>
                    <li><code><?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?></code> — <?php echo Text::_($labelKey); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.saveAjustesCotizacionPdf'); ?>" method="post" name="adminForm" id="ajustes-cotizacion-pdf-form" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>
            <?php if (!empty($this->returnUrlAjustesCotizacion)): ?>
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($this->returnUrlAjustesCotizacion, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>

            <div class="mb-4">
                <label for="jform_encabezado" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_ENCABEZADO'); ?>
                </label>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="jform_encabezado_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[encabezado_x]" id="jform_encabezado_x" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($encabezado_x, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_MM'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="jform_encabezado_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[encabezado_y]" id="jform_encabezado_y" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($encabezado_y, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_MM'); ?>" />
                    </div>
                </div>
                <?php if ($editor): ?>
                    <?php echo $editor->display('jform[encabezado]', $encabezado, $editorWidth, $editorHeight, $editorCols, $editorRows, $editorButtons, 'jform_encabezado', null, null, []); ?>
                <?php else: ?>
                    <textarea name="jform[encabezado]" id="jform_encabezado" class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($encabezado, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_TABLE_POS'); ?></label>
                <div class="row g-2">
                    <div class="col-auto">
                        <label for="jform_table_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[table_x]" id="jform_table_x" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($table_x, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_0_FLOW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="jform_table_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[table_y]" id="jform_table_y" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($table_y, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_0_FLOW'); ?>" />
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label for="jform_terminos_condiciones" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_TERMINOS'); ?>
                </label>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="jform_terminos_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[terminos_x]" id="jform_terminos_x" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($terminos_x, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_0_FLOW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="jform_terminos_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[terminos_y]" id="jform_terminos_y" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($terminos_y, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_0_FLOW'); ?>" />
                    </div>
                </div>
                <?php if ($editor): ?>
                    <?php echo $editor->display('jform[terminos_condiciones]', $terminos, $editorWidth, $editorHeight, $editorCols, $editorRows, $editorButtons, 'jform_terminos_condiciones', null, null, []); ?>
                <?php else: ?>
                    <textarea name="jform[terminos_condiciones]" id="jform_terminos_condiciones" class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($terminos, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="jform_pie_pagina" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_PIE'); ?>
                </label>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="jform_pie_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[pie_x]" id="jform_pie_x" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($pie_x, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_0_FLOW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="jform_pie_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[pie_y]" id="jform_pie_y" class="form-control form-control-sm" style="width:5rem;" value="<?php echo htmlspecialchars($pie_y, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_0_FLOW'); ?>" />
                    </div>
                </div>
                <?php if ($editor): ?>
                    <?php echo $editor->display('jform[pie_pagina]', $pie, $editorWidth, $editorHeight, $editorCols, $editorRows, $editorButtons, 'jform_pie_pagina', null, null, []); ?>
                <?php else: ?>
                    <textarea name="jform[pie_pagina]" id="jform_pie_pagina" class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($pie, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_SAVE'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
