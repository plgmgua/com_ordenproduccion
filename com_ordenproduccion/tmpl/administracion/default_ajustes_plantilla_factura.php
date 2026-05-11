<?php
/**
 * Ajustes > Plantilla de Factura: optional HTML header/footer for the Grimpsa invoice PDF (WYSIWYG + placeholders),
 * logo and mm offsets (same idea as Ajustes de Cotización PDF). Not used on the browser invoice page.
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
use Joomla\CMS\Uri\Uri;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceFacturaTemplateHelper;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
HTMLHelper::_('form.csrf');

$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$settings = isset($this->invoiceFacturaPlantillaSettings) && is_array($this->invoiceFacturaPlantillaSettings)
    ? $this->invoiceFacturaPlantillaSettings
    : [
        'header_izq_html'   => '',
        'header_der_html'   => '',
        'footer_html'       => '',
        'logo_path'         => '',
        'logo_x'            => 15,
        'logo_y'            => 15,
        'logo_width'        => 50,
        'encabezado_izq_x'  => 15,
        'encabezado_izq_y'  => 15,
        'encabezado_der_x'  => 115,
        'encabezado_der_y'  => 15,
        'qr_x'              => 150,
        'qr_y'              => 18,
        'qr_size_mm'        => 0,
        'pie_x'             => 0,
        'pie_y'             => 0,
    ];
$headerIzqHtml = isset($settings['header_izq_html']) ? $settings['header_izq_html'] : '';
$headerDerHtml = isset($settings['header_der_html']) ? $settings['header_der_html'] : '';
$footerHtml   = isset($settings['footer_html']) ? $settings['footer_html'] : '';
$logo_path    = isset($settings['logo_path']) ? $settings['logo_path'] : '';
$logo_x       = isset($settings['logo_x']) ? (float) $settings['logo_x'] : 15;
$logo_y       = isset($settings['logo_y']) ? (float) $settings['logo_y'] : 15;
$logo_width   = isset($settings['logo_width']) ? (float) $settings['logo_width'] : 50;
$encabezado_izq_x = isset($settings['encabezado_izq_x']) ? (float) $settings['encabezado_izq_x'] : 15;
$encabezado_izq_y = isset($settings['encabezado_izq_y']) ? (float) $settings['encabezado_izq_y'] : 15;
$encabezado_der_x = isset($settings['encabezado_der_x']) ? (float) $settings['encabezado_der_x'] : 115;
$encabezado_der_y = isset($settings['encabezado_der_y']) ? (float) $settings['encabezado_der_y'] : 15;
$qr_x             = isset($settings['qr_x']) ? (float) $settings['qr_x'] : 150;
$qr_y             = isset($settings['qr_y']) ? (float) $settings['qr_y'] : 18;
$qr_size_mm       = isset($settings['qr_size_mm']) ? (float) $settings['qr_size_mm'] : 0;
$pie_x        = isset($settings['pie_x']) ? (float) $settings['pie_x'] : 0;
$pie_y        = isset($settings['pie_y']) ? (float) $settings['pie_y'] : 0;

$logo_preview_url = '';
if ($logo_path !== '') {
    if (preg_match('/^https?:\/\//i', $logo_path) || strpos($logo_path, '//') === 0) {
        $logo_preview_url = $logo_path;
    } else {
        $logo_preview_url = Uri::root() . ltrim($logo_path, '/');
    }
}

try {
    $editorName = $app->get('editor', 'tinymce');
    $editor = Editor::getInstance($editorName);
} catch (\Exception $e) {
    $editor = null;
}
$editorWidth   = '100%';
$editorHeight  = '220';
$editorCols    = 75;
$editorRows    = 16;
$editorButtons = true;
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-file-invoice"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_DESC'); ?>
        </p>

        <div class="alert alert-info mb-4">
            <h3 class="alert-heading h6 mb-2">
                <i class="fas fa-code"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VARS_HEADING'); ?>
            </h3>
            <p class="mb-2 small"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VARS_DESC'); ?></p>
            <ul class="list-unstyled mb-0 small">
                <?php foreach (InvoiceFacturaTemplateHelper::getPlaceholdersForUi() as $placeholder => $labelKey) : ?>
                    <li><code><?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?></code> — <?php echo Text::_($labelKey); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.saveInvoiceFacturaPlantilla'); ?>"
              method="post" name="adminForm" id="ajustes-invoice-factura-plantilla-form" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>

            <!-- Logo (same fields as cotización PDF) -->
            <div class="mb-4 p-3 border rounded bg-light">
                <label class="form-label fw-bold">
                    <i class="fas fa-image me-1"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_LOGO'); ?>
                </label>
                <p class="text-muted small mb-2">
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_LOGO_VIEW_HINT'); ?>
                </p>

                <div class="mb-2">
                    <label for="inv_plant_logo_path" class="form-label small mb-1">
                        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_LOGO_PATH'); ?>
                    </label>
                    <input type="text"
                           name="jform[logo_path]"
                           id="inv_plant_logo_path"
                           class="form-control"
                           value="<?php echo htmlspecialchars($logo_path, ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="images/mi-logo.png" />
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_LOGO_PATH_HINT'); ?></div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="inv_plant_logo_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[logo_x]" id="inv_plant_logo_x"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $logo_x, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_logo_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[logo_y]" id="inv_plant_logo_y"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $logo_y, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_logo_width" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_LOGO_WIDTH'); ?></label>
                        <input type="number" step="0.1" min="1" name="jform[logo_width]" id="inv_plant_logo_width"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $logo_width, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                </div>

                <?php if ($logo_preview_url !== '') : ?>
                <div class="mt-2">
                    <p class="small mb-1 text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_LOGO_PREVIEW'); ?></p>
                    <img src="<?php echo htmlspecialchars($logo_preview_url, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="Logo preview" style="max-height:80px; max-width:200px; border:1px solid #dee2e6; padding:4px; background:#fff;" />
                </div>
                <?php endif; ?>
            </div>

            <!-- Verification QR (from certified XML / Digifact JSON when available) -->
            <div class="mb-4 p-3 border rounded bg-white">
                <label class="form-label fw-bold">
                    <i class="fas fa-qrcode me-1"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_QR_HEADING'); ?>
                </label>
                <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_QR_DESC'); ?></p>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="inv_plant_qr_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[qr_x]" id="inv_plant_qr_x"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $qr_x, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_qr_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[qr_y]" id="inv_plant_qr_y"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $qr_y, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_qr_size_mm" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_QR_SIZE_MM'); ?></label>
                        <input type="number" step="0.5" min="0" name="jform[qr_size_mm]" id="inv_plant_qr_size_mm"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $qr_size_mm, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_QR_SIZE_MM_DESC'); ?>" />
                    </div>
                </div>
                <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_QR_SIZE_MM_DESC'); ?></div>
            </div>

            <!-- Encabezado izquierdo -->
            <div class="mb-4 p-3 border rounded">
                <label for="jform_invoice_factura_header_izq_html" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_HEADER_LEFT'); ?>
                </label>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="inv_plant_enc_izq_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[encabezado_izq_x]" id="inv_plant_enc_izq_x"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $encabezado_izq_x, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_enc_izq_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[encabezado_izq_y]" id="inv_plant_enc_izq_y"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $encabezado_izq_y, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                </div>
                <?php if ($editor) : ?>
                    <?php echo $editor->display(
                        'jform[invoice_factura_header_izq_html]',
                        $headerIzqHtml,
                        $editorWidth,
                        $editorHeight,
                        $editorCols,
                        $editorRows,
                        $editorButtons,
                        'jform_invoice_factura_header_izq_html',
                        null,
                        null,
                        []
                    ); ?>
                <?php else : ?>
                    <textarea name="jform[invoice_factura_header_izq_html]" id="jform_invoice_factura_header_izq_html"
                              class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($headerIzqHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <!-- Encabezado derecho -->
            <div class="mb-4 p-3 border rounded">
                <label for="jform_invoice_factura_header_der_html" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_HEADER_RIGHT'); ?>
                </label>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="inv_plant_enc_der_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[encabezado_der_x]" id="inv_plant_enc_der_x"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $encabezado_der_x, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_enc_der_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[encabezado_der_y]" id="inv_plant_enc_der_y"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $encabezado_der_y, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                </div>
                <?php if ($editor) : ?>
                    <?php echo $editor->display(
                        'jform[invoice_factura_header_der_html]',
                        $headerDerHtml,
                        $editorWidth,
                        $editorHeight,
                        $editorCols,
                        $editorRows,
                        $editorButtons,
                        'jform_invoice_factura_header_der_html',
                        null,
                        null,
                        []
                    ); ?>
                <?php else : ?>
                    <textarea name="jform[invoice_factura_header_der_html]" id="jform_invoice_factura_header_der_html"
                              class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($headerDerHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <!-- Pie de página -->
            <div class="mb-4">
                <label for="jform_invoice_factura_footer_html" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_FOOTER'); ?>
                </label>
                <div class="row g-2 mb-2">
                    <div class="col-auto">
                        <label for="inv_plant_pie_x" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_X'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[pie_x]" id="inv_plant_pie_x"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $pie_x, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                    <div class="col-auto">
                        <label for="inv_plant_pie_y" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_COTIZACION_PDF_POS_Y'); ?></label>
                        <input type="number" step="0.1" min="0" name="jform[pie_y]" id="inv_plant_pie_y"
                               class="form-control form-control-sm" style="width:5rem;"
                               value="<?php echo htmlspecialchars((string) $pie_y, ENT_QUOTES, 'UTF-8'); ?>"
                               title="<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_POS_MM_VIEW'); ?>" />
                    </div>
                </div>
                <?php if ($editor) : ?>
                    <?php echo $editor->display(
                        'jform[invoice_factura_footer_html]',
                        $footerHtml,
                        $editorWidth,
                        $editorHeight,
                        $editorCols,
                        $editorRows,
                        $editorButtons,
                        'jform_invoice_factura_footer_html',
                        null,
                        null,
                        []
                    ); ?>
                <?php else : ?>
                    <textarea name="jform[invoice_factura_footer_html]" id="jform_invoice_factura_footer_html"
                              class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($footerHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo Text::_('JSAVE'); ?>
            </button>
        </form>
    </div>
</div>
