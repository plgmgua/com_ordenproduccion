<?php
/**
 * Ajustes — Solicitud de cotización a proveedor externo (message templates).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$schemaOk = !empty($this->vendorQuoteTemplatesSchemaOk);
$tpl      = $this->vendorQuoteTemplates ?? [];
$email    = $tpl['email'] ?? null;
$cell     = $tpl['cellphone'] ?? null;
$pdf      = $tpl['pdf'] ?? null;

$saveUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveVendorQuoteTemplates', false);
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_SOLICITUD_COTIZACION_HEADING'); ?></h2>
        <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_SOLICITUD_COTIZACION_INTRO'); ?></p>

        <?php if (!$schemaOk) : ?>
            <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_SCHEMA_MISSING'); ?></div>
        <?php else : ?>
        <div class="alert alert-light border small mb-4">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_PLACEHOLDERS_TITLE'); ?></strong>
            <code class="d-block mt-2" style="white-space: pre-wrap; font-size: 0.8rem;">{PROVEEDOR_NOMBRE} {PROVEEDOR_NIT} {PROVEEDOR_DIRECCION} {PROVEEDOR_TELEFONO}
{PROVEEDOR_CONTACTO_NOMBRE} {PROVEEDOR_CELULAR} {PROVEEDOR_EMAIL}
{PRECOT_NUMERO} {PRECOT_DESCRIPCION} {PRECOT_MEDIDAS}
{LINEAS_TEXTO} {LINEAS_TEXTO_CORTO}
{USUARIO_NOMBRE} {USUARIO_EMAIL} {USUARIO_CELULAR} {USUARIO_CELULAR_WA_URL} {USUARIO_CELULAR_HTML}</code>
        </div>

        <form method="post" action="<?php echo htmlspecialchars($saveUrl); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>

            <h3 class="h6 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TPL_EMAIL'); ?></h3>
            <div class="mb-3">
                <label class="form-label" for="vqt-email-subject"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_EMAIL_SUBJECT'); ?></label>
                <input type="text" class="form-control" name="email_subject" id="vqt-email-subject" maxlength="512"
                       value="<?php echo htmlspecialchars((string) ($email->subject ?? '')); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="vqt-email-body"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_EMAIL_BODY'); ?></label>
                <textarea class="form-control font-monospace" name="email_body" id="vqt-email-body" rows="10"><?php echo htmlspecialchars((string) ($email->body ?? '')); ?></textarea>
            </div>

            <h3 class="h6 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TPL_CELLPHONE'); ?></h3>
            <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TPL_CELLPHONE_DESC'); ?></p>
            <div class="mb-3">
                <label class="form-label" for="vqt-cell-body"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_SMS_BODY'); ?></label>
                <textarea class="form-control font-monospace" name="cellphone_body" id="vqt-cell-body" rows="6"><?php echo htmlspecialchars((string) ($cell->body ?? '')); ?></textarea>
            </div>

            <h3 class="h6 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TPL_PDF'); ?></h3>
            <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TPL_PDF_DESC'); ?></p>
            <div class="mb-3">
                <label class="form-label" for="vqt-pdf-body"><?php echo Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_BODY'); ?></label>
                <textarea class="form-control font-monospace" name="pdf_body" id="vqt-pdf-body" rows="12"><?php echo htmlspecialchars((string) ($pdf->body ?? '')); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
