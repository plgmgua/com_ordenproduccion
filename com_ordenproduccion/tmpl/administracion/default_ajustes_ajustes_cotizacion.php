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
    : ['encabezado' => '', 'terminos_condiciones' => '', 'pie_pagina' => ''];
$encabezado = isset($settings['encabezado']) ? $settings['encabezado'] : '';
$terminos = isset($settings['terminos_condiciones']) ? $settings['terminos_condiciones'] : '';
$pie = isset($settings['pie_pagina']) ? $settings['pie_pagina'] : '';

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
                <?php if ($editor): ?>
                    <?php echo $editor->display('jform[encabezado]', $encabezado, $editorWidth, $editorHeight, $editorCols, $editorRows, $editorButtons, 'jform_encabezado', null, null, []); ?>
                <?php else: ?>
                    <textarea name="jform[encabezado]" id="jform_encabezado" class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($encabezado, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="jform_terminos_condiciones" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_TERMINOS'); ?>
                </label>
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
