<?php
/**
 * Single Invoice (detail) template
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\Invoice
 * @since       3.97.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Invoice\HtmlView $this */
$item = $this->item;
$lineItems = is_array($item->line_items ?? null) ? $item->line_items : [];
$isFel = !empty($item->invoice_source) && $item->invoice_source === 'fel_import';

// Fallback labels when language file is not loaded (avoid showing COM_ORDENPRODUCCION_* to user)
$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t !== '' && $t !== $key) ? $t : $fallback;
};
?>
<div class="com-ordenproduccion-invoice-detail container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="h4 mb-0">
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo $l('COM_ORDENPRODUCCION_INVOICE', 'Factura'); ?>: <?php echo htmlspecialchars($item->invoice_number ?? ''); ?>
        </h1>
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> <?php echo $l('COM_ORDENPRODUCCION_BACK_TO_INVOICES', 'Volver a Facturas'); ?>
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong><?php echo $l('COM_ORDENPRODUCCION_INVOICE_DETAILS', 'Detalles de Factura'); ?></strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_CLIENT', 'Cliente'); ?>:</strong> <?php echo htmlspecialchars($item->client_name ?? '-'); ?></p>
                    <?php if (!empty($item->client_nit)) : ?>
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_NIT', 'NIT'); ?>:</strong> <?php echo htmlspecialchars($item->client_nit); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item->client_address)) : ?>
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_ADDRESS', 'Dirección'); ?>:</strong> <?php echo htmlspecialchars($item->client_address); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item->orden_de_trabajo) && ($item->orden_de_trabajo ?? '') !== '') : ?>
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_ORDER_NUMBER', 'Orden #'); ?>:</strong> <?php echo htmlspecialchars($item->orden_de_trabajo); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_INVOICE_DATE', 'Fecha de factura'); ?>:</strong> <?php echo $item->invoice_date ? HTMLHelper::_('date', $item->invoice_date, Text::_('DATE_FORMAT_LC3')) : '-'; ?></p>
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_INVOICE_AMOUNT', 'Valor Factura'); ?>:</strong> <?php echo htmlspecialchars($item->currency ?? 'Q'); ?> <?php echo number_format((float) ($item->invoice_amount ?? 0), 2); ?></p>
                    <?php
$statusKey = 'COM_ORDENPRODUCCION_STATUS_' . strtoupper((string) ($item->status ?? 'sent'));
$statusLabel = Text::_($statusKey);
if ($statusLabel === $statusKey) {
    $statusLabel = htmlspecialchars($item->status ?? 'sent');
}
?>
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_STATUS', 'Estado'); ?>:</strong> <span class="badge bg-secondary"><?php echo $statusLabel; ?></span></p>
                    <?php if (!empty($item->sales_agent)) : ?>
                    <p><strong><?php echo $l('COM_ORDENPRODUCCION_SALES_AGENT', 'Agente de Ventas'); ?>:</strong> <?php echo htmlspecialchars($item->sales_agent); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isFel && (!empty($item->fel_emisor_nombre) || !empty($item->fel_autorizacion_uuid))) : ?>
            <hr>
            <h6 class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_FEL_INFO', 'Datos FEL (SAT)'); ?></h6>
            <div class="row small">
                <?php if (!empty($item->fel_emisor_nombre)) : ?>
                <div class="col-md-6"><strong><?php echo $l('COM_ORDENPRODUCCION_FEL_EMISOR', 'Emisor'); ?>:</strong> <?php echo htmlspecialchars($item->fel_emisor_nombre); ?></div>
                <?php endif; ?>
                <?php if (!empty($item->fel_tipo_dte)) : ?>
                <div class="col-md-6"><strong><?php echo $l('COM_ORDENPRODUCCION_FEL_TIPO', 'Tipo DTE'); ?>:</strong> <?php echo htmlspecialchars($item->fel_tipo_dte); ?></div>
                <?php endif; ?>
                <?php if (!empty($item->fel_autorizacion_uuid)) : ?>
                <div class="col-12"><strong><?php echo $l('COM_ORDENPRODUCCION_FEL_UUID', 'UUID de autorización'); ?>:</strong> <code><?php echo htmlspecialchars($item->fel_autorizacion_uuid); ?></code></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($lineItems)) : ?>
    <div class="card">
        <div class="card-header"><strong><?php echo $l('COM_ORDENPRODUCCION_LINE_ITEMS', 'Líneas de factura'); ?></strong></div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?php echo $l('COM_ORDENPRODUCCION_ITEM_QTY', 'Cant.'); ?></th>
                        <th><?php echo $l('COM_ORDENPRODUCCION_ITEM_DESCRIPTION', 'Descripción'); ?></th>
                        <th class="text-end"><?php echo $l('COM_ORDENPRODUCCION_ITEM_UNIT_PRICE', 'P. unit.'); ?></th>
                        <th class="text-end"><?php echo $l('COM_ORDENPRODUCCION_ITEM_TOTAL', 'Total'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $row) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['cantidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo number_format((float) ($row['precio_unitario'] ?? $row['subtotal'] ?? 0), 2); ?></td>
                        <td class="text-end"><?php echo number_format((float) ($row['subtotal'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
