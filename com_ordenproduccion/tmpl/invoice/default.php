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
?>
<div class="com-ordenproduccion-invoice-detail container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="h4 mb-0">
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICE'); ?>: <?php echo htmlspecialchars($item->invoice_number ?? ''); ?>
        </h1>
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> <?php echo Text::_('COM_ORDENPRODUCCION_BACK_TO_INVOICES'); ?>
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_DETAILS'); ?></strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?>:</strong> <?php echo htmlspecialchars($item->client_name ?? '-'); ?></p>
                    <?php if (!empty($item->client_nit)) : ?>
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?>:</strong> <?php echo htmlspecialchars($item->client_nit); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item->client_address)) : ?>
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_ADDRESS'); ?>:</strong> <?php echo htmlspecialchars($item->client_address); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item->orden_de_trabajo) && ($item->orden_de_trabajo ?? '') !== '') : ?>
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?>:</strong> <?php echo htmlspecialchars($item->orden_de_trabajo); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_DATE'); ?>:</strong> <?php echo $item->invoice_date ? HTMLHelper::_('date', $item->invoice_date, Text::_('DATE_FORMAT_LC3')) : '-'; ?></p>
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_AMOUNT'); ?>:</strong> <?php echo htmlspecialchars($item->currency ?? 'Q'); ?> <?php echo number_format((float) ($item->invoice_amount ?? 0), 2); ?></p>
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?>:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($item->status ?? 'sent'); ?></span></p>
                    <?php if (!empty($item->sales_agent)) : ?>
                    <p><strong><?php echo Text::_('COM_ORDENPRODUCCION_SALES_AGENT'); ?>:</strong> <?php echo htmlspecialchars($item->sales_agent); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isFel && (!empty($item->fel_emisor_nombre) || !empty($item->fel_autorizacion_uuid))) : ?>
            <hr>
            <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_FEL_INFO'); ?></h6>
            <div class="row small">
                <?php if (!empty($item->fel_emisor_nombre)) : ?>
                <div class="col-md-6"><strong><?php echo Text::_('COM_ORDENPRODUCCION_FEL_EMISOR'); ?>:</strong> <?php echo htmlspecialchars($item->fel_emisor_nombre); ?></div>
                <?php endif; ?>
                <?php if (!empty($item->fel_tipo_dte)) : ?>
                <div class="col-md-6"><strong><?php echo Text::_('COM_ORDENPRODUCCION_FEL_TIPO'); ?>:</strong> <?php echo htmlspecialchars($item->fel_tipo_dte); ?></div>
                <?php endif; ?>
                <?php if (!empty($item->fel_autorizacion_uuid)) : ?>
                <div class="col-12"><strong><?php echo Text::_('COM_ORDENPRODUCCION_FEL_UUID'); ?>:</strong> <code><?php echo htmlspecialchars($item->fel_autorizacion_uuid); ?></code></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($lineItems)) : ?>
    <div class="card">
        <div class="card-header"><strong><?php echo Text::_('COM_ORDENPRODUCCION_LINE_ITEMS'); ?></strong></div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ITEM_QTY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ITEM_DESCRIPTION'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ITEM_UNIT_PRICE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ITEM_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $row) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['cantidad'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['descripcion'] ?? ''); ?></td>
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
