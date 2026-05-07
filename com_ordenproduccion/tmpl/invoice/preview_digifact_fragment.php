<?php
/**
 * Digifact NUC — invoice layout preview (not saved). Included from CotizacionController; variables: $previewItem, $previewQuotationRef.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @since       3.118.42
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var object $previewItem */
/** @var string $previewQuotationRef */

$item      = $previewItem;
$lineItems = \is_array($item->line_items ?? null) ? $item->line_items : [];
$felExtra  = [];
if (!empty($item->fel_extra) && \is_string($item->fel_extra)) {
    $felExtra = json_decode($item->fel_extra, true) ?: [];
}
$moneda      = htmlspecialchars($item->currency ?? 'Q', ENT_QUOTES, 'UTF-8');
$quotationRef = trim((string) ($previewQuotationRef ?? ''));
$isFel        = true;
?>
<div class="com-ordenproduccion-invoice-detail invoice-pdf-style invoice-digifact-preview-fragment">
    <div class="alert alert-info py-2 mb-3 small" role="status">
        <strong><?php echo Text::_('COM_ORDENPRODUCCION_DIGIFACT_INVOICE_PREVIEW_BANNER'); ?></strong>
        <?php if ($quotationRef !== '') : ?>
            <span class="ms-1"><?php echo Text::_('COM_ORDENPRODUCCION_DIGIFACT_INVOICE_PREVIEW_COT_REF'); ?> <?php echo htmlspecialchars($quotationRef, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
    <div class="invoice-pdf-layout border rounded p-3 bg-white">
        <div class="row mb-3">
            <div class="col-md-6">
                <?php if ($isFel && !empty($item->fel_emisor_nombre)) : ?>
                <div class="fw-bold"><?php echo htmlspecialchars($item->fel_emisor_nombre, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($item->fel_emisor_nit)) : ?>
                <div class="small">NIT Emisor: <?php echo htmlspecialchars((string) $item->fel_emisor_nit, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php
                $ed = $felExtra['emisor_direccion'] ?? null;
                if (!empty($ed) && \is_array($ed)) :
                    $addrParts = array_filter([
                        $ed['direccion'] ?? '',
                        $ed['codigo_postal'] ?? '',
                        ($ed['municipio'] ?? '') . (isset($ed['departamento']) && $ed['departamento'] !== '' ? ', ' . $ed['departamento'] : ''),
                        $ed['pais'] ?? '',
                    ]);
                    if (!empty($addrParts)) :
                ?>
                <div class="small text-break"><?php echo htmlspecialchars(implode(' ', $addrParts), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; endif; ?>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end small">
                <div class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_DIGIFACT_INVOICE_PREVIEW_PENDING_CERT'); ?></div>
                <div class="mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_DIGIFACT_INVOICE_PREVIEW_NUMBER_LABEL'); ?> <span class="text-muted">—</span></div>
                <div class="mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_DIGIFACT_INVOICE_PREVIEW_AUTH_LABEL'); ?> <span class="text-muted">—</span></div>
                <div class="mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_DIGIFACT_INVOICE_PREVIEW_DATE_LABEL'); ?> <?php echo HTMLHelper::_('date', 'now', 'd-m-Y H:i:s'); ?></div>
                <div>Moneda: <?php echo $moneda; ?></div>
            </div>
        </div>

        <div class="row mb-3 small">
            <div class="col-12">
                <?php if ($item->client_nit ?? '') : ?>
                <div><strong>NIT:</strong> <?php echo htmlspecialchars((string) $item->client_nit, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <div><strong>Cliente:</strong> <?php echo htmlspecialchars((string) ($item->client_name ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($item->client_address)) : ?>
                <div><strong>Direccion:</strong> <?php echo htmlspecialchars((string) $item->client_address, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($lineItems)) : ?>
        <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm mb-0 invoice-items-table">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Cantidad</th>
                        <th>Descripcion</th>
                        <th class="text-end">Precio Unitario (<?php echo $moneda; ?>)</th>
                        <th class="text-end">Total Factura (<?php echo $moneda; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $idx => $row) : ?>
                    <?php
                    $qtyRow = (float) ($row['cantidad'] ?? 0);
                    $subRow = (float) ($row['subtotal'] ?? 0);
                    $puRow  = (float) ($row['precio_unitario'] ?? $row['valor_unitario'] ?? 0);
                    if ($puRow <= 0.00001 && $qtyRow > 0 && $subRow > 0) {
                        $puRow = $subRow / $qtyRow;
                    }
                    ?>
                    <tr>
                        <td><?php echo (int) ($row['numero_linea'] ?? $idx + 1); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['cantidad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo number_format($puRow, 2); ?></td>
                        <td class="text-end"><?php echo number_format($subRow, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">TOTALES:</td>
                        <td class="text-end"><?php echo number_format((float) ($item->invoice_amount ?? 0), 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<style>
.invoice-digifact-preview-fragment .invoice-pdf-layout { max-width: 900px; margin-left: auto; margin-right: auto; }
.invoice-digifact-preview-fragment .invoice-items-table { font-size: 0.9rem; }
.invoice-digifact-preview-fragment .invoice-items-table th { white-space: nowrap; }
</style>
