<?php
/**
 * Single Invoice (detail) template - layout matches SAT FEL PDF
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
$felExtra = [];
if (!empty($item->fel_extra) && is_string($item->fel_extra)) {
    $felExtra = json_decode($item->fel_extra, true) ?: [];
}

$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t !== '' && $t !== $key) ? $t : $fallback;
};

$moneda = htmlspecialchars($item->currency ?? 'Q', ENT_QUOTES, 'UTF-8');
?>
<div class="com-ordenproduccion-invoice-detail invoice-pdf-style container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0"><?php echo $l('COM_ORDENPRODUCCION_INVOICE', 'Factura'); ?> <?php echo htmlspecialchars($item->invoice_number ?? ''); ?></h1>
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> <?php echo $l('COM_ORDENPRODUCCION_BACK_TO_INVOICES', 'Volver a Facturas'); ?>
        </a>
    </div>

    <div class="invoice-pdf-layout border rounded p-3 bg-white">
        <!-- Row: Emisor (left) | Autorización / Fechas / Moneda (right) -->
        <div class="row mb-3">
            <div class="col-md-6">
                <?php if ($isFel && !empty($item->fel_emisor_nombre)) : ?>
                <div class="fw-bold"><?php echo htmlspecialchars($item->fel_emisor_nombre, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($item->fel_emisor_nit)) : ?>
                <div class="small">NIT Emisor: <?php echo htmlspecialchars($item->fel_emisor_nit); ?></div>
                <?php endif; ?>
                <?php if (!empty($felExtra['emisor_nombre_comercial'])) : ?>
                <div class="small"><?php echo htmlspecialchars($felExtra['emisor_nombre_comercial'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php
                $ed = $felExtra['emisor_direccion'] ?? null;
                if (!empty($ed) && is_array($ed)) :
                    $addrParts = array_filter([$ed['direccion'] ?? '', $ed['codigo_postal'] ?? '', ($ed['municipio'] ?? '') . (isset($ed['departamento']) && $ed['departamento'] !== '' ? ', ' . $ed['departamento'] : ''), $ed['pais'] ?? '']);
                    if (!empty($addrParts)) :
                ?>
                <div class="small text-break"><?php echo htmlspecialchars(implode(' ', $addrParts), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; endif; ?>
                <?php elseif (!empty($item->client_name)) : ?>
                <div class="fw-bold"><?php echo htmlspecialchars($item->client_name, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end small">
                <?php if ($isFel && !empty($item->fel_autorizacion_uuid)) : ?>
                <div><strong>Número de autorización:</strong><br><?php echo htmlspecialchars($item->fel_autorizacion_uuid); ?></div>
                <?php if (!empty($felExtra['autorizacion_serie']) || !empty($felExtra['autorizacion_numero_dte'])) : ?>
                <div class="mt-1">Serie: <?php echo htmlspecialchars($felExtra['autorizacion_serie'] ?? '-'); ?> | Numero: <?php echo htmlspecialchars($felExtra['autorizacion_numero_dte'] ?? '-'); ?></div>
                <?php endif; ?>
                <?php if (!empty($item->fel_fecha_emision)) : ?>
                <div class="mt-1">Fecha de Emision: <?php echo $item->fel_fecha_emision ? HTMLHelper::_('date', $item->fel_fecha_emision, 'd-m-Y H:i:s') : '-'; ?></div>
                <?php endif; ?>
                <?php
                $cert = $felExtra['certificacion'] ?? null;
                if (!empty($cert['fecha_hora_certificacion'])) :
                    $certDate = is_numeric(strtotime($cert['fecha_hora_certificacion'])) ? date('d-m-Y H:i:s', strtotime($cert['fecha_hora_certificacion'])) : $cert['fecha_hora_certificacion'];
                ?>
                <div>Fecha y hora de certificación: <?php echo htmlspecialchars($certDate); ?></div>
                <?php endif; ?>
                <?php endif; ?>
                <div>Moneda: <?php echo $moneda; ?></div>
            </div>
        </div>

        <!-- Receptor (header: NIT, Cliente, Direccion) -->
        <div class="row mb-3 small">
            <div class="col-12">
                <?php if ($item->client_nit ?? $item->fel_receptor_id ?? '') : ?>
                <div><strong>NIT:</strong> <?php echo htmlspecialchars($item->client_nit ?? $item->fel_receptor_id ?? ''); ?></div>
                <?php endif; ?>
                <div><strong>Cliente:</strong> <?php echo htmlspecialchars($item->client_name ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($item->client_address) || !empty($item->fel_receptor_direccion)) : ?>
                <div><strong>Direccion:</strong> <?php echo htmlspecialchars($item->client_address ?? $item->fel_receptor_direccion ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items table (PDF columns) -->
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
                    <tr>
                        <td><?php echo (int) ($row['numero_linea'] ?? $idx + 1); ?></td>
                        <td><?php echo htmlspecialchars($row['cantidad'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo number_format((float) ($row['precio_unitario'] ?? 0), 2); ?></td>
                        <td class="text-end"><?php echo number_format((float) ($row['subtotal'] ?? 0), 2); ?></td>
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

        <!-- Frases (notes) -->
        <?php
        $frases = $felExtra['frases'] ?? [];
        if (!empty($frases) && is_array($frases)) :
            $frasesText = [
                '1' => ['1' => '', '2' => '', '3' => '', '4' => '* Exportaciones. Exenta del IVA (art. 7 núm. 2 Ley del IVA)'],
                '2' => ['1' => '* Sujeto a retención definitiva ISR'],
            ];
            $toShow = [];
            foreach ($frases as $f) {
                $esc = $f['codigo_escenario'] ?? '';
                $tipo = $f['tipo_frase'] ?? '';
                if (isset($frasesText[$esc][$tipo]) && $frasesText[$esc][$tipo] !== '') {
                    $toShow[] = $frasesText[$esc][$tipo];
                }
            }
            if (empty($toShow) && ($felExtra['complemento_exportacion'] ?? null)) {
                $toShow[] = '* Exportaciones. Exenta del IVA (art. 7 núm. 2 Ley del IVA)';
            }
            if (!empty($toShow)) :
        ?>
        <div class="small text-muted mb-3">
            <?php foreach ($toShow as $txt) : ?>
            <div><?php echo $txt; ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; endif; ?>

        <!-- Complemento Exportación -->
        <?php
        $exp = $felExtra['complemento_exportacion'] ?? null;
        if (!empty($exp) && is_array($exp)) :
        ?>
        <div class="border rounded p-2 mb-3 small">
            <div class="fw-bold mb-2">COMPLEMENTO EXPORTACIÓN</div>
            <?php if (!empty($exp['lugar_expedicion'])) : ?>
            <div>Lugar de expedición: <?php echo htmlspecialchars($exp['lugar_expedicion'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($exp['nombre_consignatario'])) : ?>
            <div>Nombre consignatario: <?php echo htmlspecialchars($exp['nombre_consignatario'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($exp['direccion_consignatario'])) : ?>
            <div>Dirección consignatario: <?php echo htmlspecialchars($exp['direccion_consignatario'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($exp['pais_consignatario'])) : ?>
            <div>País del consignatario: <?php echo htmlspecialchars($exp['pais_consignatario'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (isset($exp['incoterm']) && $exp['incoterm'] !== '') : ?>
            <div>Términos (INCOTERM): <?php echo htmlspecialchars($exp['incoterm'], ENT_QUOTES, 'UTF-8'); ?> <?php if ($exp['incoterm'] === 'ZZZ') : ?>- Otros<?php endif; ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Complemento Abonos (FCAM) -->
        <?php
        $abonos = $felExtra['complemento_abonos'] ?? [];
        if (!empty($abonos) && is_array($abonos)) :
        ?>
        <div class="border rounded p-2 mb-3 small">
            <div class="fw-bold mb-2">Abonos / Fechas de vencimiento</div>
            <ul class="mb-0 ps-3">
            <?php foreach ($abonos as $ab) : ?>
                <li>Abono <?php echo (int) ($ab['numero_abono'] ?? 0); ?>: Vence <?php echo htmlspecialchars($ab['fecha_vencimiento'] ?? ''); ?> — <?php echo $moneda; ?> <?php echo number_format((float) ($ab['monto_abono'] ?? 0), 2); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Datos del certificador (footer) -->
        <?php
        $cert = $felExtra['certificacion'] ?? null;
        if (!empty($cert) && is_array($cert) && (!empty($cert['nombre_certificador']) || !empty($cert['nit_certificador']))) :
        ?>
        <div class="pt-2 mt-2 border-top small text-muted">
            <strong>Datos del certificador</strong><br>
            <?php if (!empty($cert['nombre_certificador'])) : ?>
            <?php echo htmlspecialchars($cert['nombre_certificador'], ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
            <?php if (!empty($cert['nit_certificador'])) : ?>
            <?php if (!empty($cert['nombre_certificador'])) : ?> NIT: <?php endif; ?><?php echo htmlspecialchars($cert['nit_certificador']); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        $assocLinks = is_array($this->associatedOrdenLinks ?? null) ? $this->associatedOrdenLinks : [];
        $detailDropdown = is_array($this->invoiceDetailOrdenDropdown ?? null) ? $this->invoiceDetailOrdenDropdown : [];
        $matchTbl = (bool) ($this->invoiceOrdenMatchTableAvailable ?? false);
        ?>
        <div class="pt-3 mt-3 border-top invoice-work-orders-footer">
            <div class="fw-bold mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_WORK_ORDERS_SECTION'); ?></div>
            <?php if (!empty($assocLinks)) : ?>
                <ul class="mb-2 ps-3">
                    <?php foreach ($assocLinks as $lnk) :
                        $oid = (int) ($lnk['orden_id'] ?? 0);
                        $onum = htmlspecialchars((string) ($lnk['orden_num'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $oUrl = Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . $oid);
                        ?>
                    <li><a href="<?php echo $oUrl; ?>" target="_blank" rel="noopener noreferrer"><?php echo $onum !== '' ? $onum : ('#' . $oid); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_NO_ORDEN_LINKED'); ?></p>
            <?php endif; ?>

            <?php if ($isFel && $matchTbl) : ?>
                <?php if (!empty($detailDropdown)) : ?>
                <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=invoice.associateOrden'); ?>" class="d-flex flex-wrap align-items-end gap-2">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="invoice_id" value="<?php echo (int) ($item->id ?? 0); ?>" />
                    <div>
                        <label for="invoice-detail-orden-id" class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_LABEL'); ?></label>
                        <select name="orden_id" id="invoice-detail-orden-id" class="form-select form-select-sm" style="min-width: 280px;">
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_SELECT_ORDEN'); ?></option>
                            <?php foreach ($detailDropdown as $opt) :
                                $oid = (int) ($opt['id'] ?? 0);
                                $lab = (string) ($opt['label'] ?? '');
                                if ($oid <= 0) {
                                    continue;
                                }
                                ?>
                            <option value="<?php echo $oid; ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_BUTTON'); ?></button>
                </form>
                <?php else : ?>
                <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ASSOCIATE_ORDEN_NONE_AVAILABLE'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isFel) : ?>
    <div class="mt-3 small">
        <strong><?php echo $l('COM_ORDENPRODUCCION_INVOICE_DATE', 'Fecha'); ?>:</strong> <?php echo $item->invoice_date ? HTMLHelper::_('date', $item->invoice_date, Text::_('DATE_FORMAT_LC3')) : '-'; ?>
        | <strong><?php echo $l('COM_ORDENPRODUCCION_STATUS', 'Estado'); ?>:</strong>
        <?php
        $statusKey = 'COM_ORDENPRODUCCION_STATUS_' . strtoupper((string) ($item->status ?? 'sent'));
        $statusLabel = Text::_($statusKey);
        echo ($statusLabel !== $statusKey) ? $statusLabel : htmlspecialchars($item->status ?? 'sent');
        ?>
    </div>
    <?php endif; ?>
</div>

<style>
.invoice-pdf-style .invoice-pdf-layout { max-width: 900px; }
.invoice-pdf-style .invoice-items-table { font-size: 0.9rem; }
.invoice-pdf-style .invoice-items-table th { white-space: nowrap; }
</style>
