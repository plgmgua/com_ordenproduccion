<?php
/**
 * Ajustes > Plantilla de Factura: optional HTML header/footer for invoice detail (WYSIWYG + placeholders).
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
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceFacturaTemplateHelper;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
HTMLHelper::_('form.csrf');

$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$settings = isset($this->invoiceFacturaPlantillaSettings) && is_array($this->invoiceFacturaPlantillaSettings)
    ? $this->invoiceFacturaPlantillaSettings
    : ['header_html' => '', 'footer_html' => ''];
$headerHtml = isset($settings['header_html']) ? $settings['header_html'] : '';
$footerHtml = isset($settings['footer_html']) ? $settings['footer_html'] : '';

try {
    $editorName = $app->get('editor', 'tinymce');
    $editor = Editor::getInstance($editorName);
} catch (\Exception $e) {
    $editor = null;
}
$editorWidth  = '100%';
$editorHeight = '220';
$editorCols   = 75;
$editorRows   = 16;
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

            <div class="mb-4">
                <label for="jform_invoice_factura_header_html" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_HEADER'); ?>
                </label>
                <?php if ($editor) : ?>
                    <?php echo $editor->display(
                        'jform[invoice_factura_header_html]',
                        $headerHtml,
                        $editorWidth,
                        $editorHeight,
                        $editorCols,
                        $editorRows,
                        $editorButtons,
                        'jform_invoice_factura_header_html',
                        null,
                        null,
                        []
                    ); ?>
                <?php else : ?>
                    <textarea name="jform[invoice_factura_header_html]" id="jform_invoice_factura_header_html"
                              class="form-control" rows="<?php echo (int) $editorRows; ?>" cols="<?php echo (int) $editorCols; ?>"><?php echo htmlspecialchars($headerHtml, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="jform_invoice_factura_footer_html" class="form-label fw-bold">
                    <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_TEMPLATE_FOOTER'); ?>
                </label>
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
