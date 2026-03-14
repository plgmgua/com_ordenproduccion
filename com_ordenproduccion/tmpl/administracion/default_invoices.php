<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Get invoices data from view (get() ensures value when layout data is used)
$invoices = $this->get('invoices');
if (!is_array($invoices)) {
    $invoices = isset($this->invoices) && is_array($this->invoices) ? $this->invoices : [];
}
$pagination = $this->get('invoicesPagination');
if ($pagination === null && isset($this->invoicesPagination)) {
    $pagination = $this->invoicesPagination;
}
$importReport = Factory::getApplication()->getSession()->get('com_ordenproduccion.import_report', null);
if ($importReport !== null) {
    Factory::getApplication()->getSession()->set('com_ordenproduccion.import_report', null);
}
?>

<style>
.invoices-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.invoices-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.invoices-header h2 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.btn-create-invoice {
    padding: 10px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.3s;
}

.btn-create-invoice:hover {
    background: #5568d3;
    color: white;
    text-decoration: none;
}

.search-filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-filter-bar input,
.search-filter-bar select {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    font-size: 14px;
}

.search-filter-bar button {
    padding: 8px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

.search-filter-bar button:hover {
    background: #5568d3;
}

.import-xml-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.import-xml-bar .form-control {
    padding: 8px 12px;
    font-size: 14px;
}

.invoices-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.invoices-table thead {
    background: #f8f9fa;
}

.invoices-table th {
    padding: 6px 8px;
    text-align: left;
    font-weight: bold;
    font-size: 0.75rem;
    color: #666;
    border-bottom: 2px solid #dee2e6;
}

.invoices-table td {
    padding: 6px 8px;
    font-size: 0.8125rem;
    border-bottom: 1px solid #dee2e6;
}

.invoices-table tbody tr {
    cursor: pointer;
    transition: background 0.2s;
}

.invoices-table tbody tr:hover {
    background: #f8f9fa;
}

.invoice-number,
.invoice-serie-numero {
    font-weight: bold;
    color: #667eea;
}

.invoice-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.status-draft { background: #e3f2fd; color: #1976d2; }
.status-sent { background: #fff3e0; color: #f57c00; }
.status-paid { background: #e8f5e9; color: #388e3c; }
.status-cancelled { background: #ffebee; color: #c62828; }

.invoice-amount {
    font-size: 0.8125rem;
    font-weight: bold;
    color: #28a745;
    text-align: right;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state p {
    font-size: 18px;
    margin-bottom: 20px;
}
</style>

<div class="invoices-section">
    <div class="invoices-header">
        <h2>
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_TITLE'); ?>
        </h2>
    </div>

    <!-- Filters: NIT, Cliente, Fecha, Total -->
    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" 
          class="search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="invoices" />
        <?php
        $state = $this->state ?? new \Joomla\Registry\Registry();
        $filterNit     = $state->get('filter.nit', '');
        $filterCliente = $state->get('filter.cliente', '');
        $filterFechaFrom = $state->get('filter.fecha_from', '');
        $filterFechaTo   = $state->get('filter.fecha_to', '');
        $filterTotalMin  = $state->get('filter.total_min', '');
        $filterTotalMax  = $state->get('filter.total_max', '');
        ?>
        <input type="text" name="filter_nit" placeholder="NIT" value="<?php echo htmlspecialchars($filterNit); ?>" />
        <input type="text" name="filter_cliente" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?> (Cliente)" value="<?php echo htmlspecialchars($filterCliente); ?>" />
        <input type="date" name="filter_fecha_from" placeholder="Fecha desde" value="<?php echo htmlspecialchars($filterFechaFrom); ?>" title="Fecha desde" />
        <input type="date" name="filter_fecha_to" placeholder="Fecha hasta" value="<?php echo htmlspecialchars($filterFechaTo); ?>" title="Fecha hasta" />
        <input type="number" name="filter_total_min" placeholder="Total min" value="<?php echo htmlspecialchars($filterTotalMin); ?>" step="0.01" min="0" title="Total mínimo (Q)" />
        <input type="number" name="filter_total_max" placeholder="Total max" value="<?php echo htmlspecialchars($filterTotalMax); ?>" step="0.01" min="0" title="Total máximo (Q)" />
        <button type="submit">
            <i class="fas fa-search"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER'); ?>
        </button>
    </form>

    <!-- Import XML and Export Excel -->
    <div class="import-xml-bar d-flex flex-wrap gap-2 align-items-center">
        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.importInvoicesXml'); ?>" 
              method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="file" name="invoice_xml[]" accept=".xml,.zip" multiple="multiple" class="form-control form-control-sm" style="max-width: 320px;" title="<?php echo Text::_('COM_ORDENPRODUCCION_IMPORT_XML_MULTIPLE_HINT'); ?>" />
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-import"></i> <?php echo Text::_('COM_ORDENPRODUCCION_IMPORT_XML'); ?>
            </button>
        </form>
        <?php
        $state = $this->state ?? new \Joomla\Registry\Registry();
        $exportUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.exportInvoicesExcel&format=raw');
        $exportUrl .= '&filter_nit=' . rawurlencode($state->get('filter.nit', ''));
        $exportUrl .= '&filter_cliente=' . rawurlencode($state->get('filter.cliente', ''));
        $exportUrl .= '&filter_fecha_from=' . rawurlencode($state->get('filter.fecha_from', ''));
        $exportUrl .= '&filter_fecha_to=' . rawurlencode($state->get('filter.fecha_to', ''));
        $exportUrl .= '&filter_total_min=' . rawurlencode($state->get('filter.total_min', ''));
        $exportUrl .= '&filter_total_max=' . rawurlencode($state->get('filter.total_max', ''));
        ?>
        <a href="<?php echo $exportUrl; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-file-excel"></i> <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_EXPORT_EXCEL'); ?>
        </a>
    </div>

    <?php if (!empty($importReport) && is_array($importReport)): ?>
    <div class="import-report-box mb-3">
        <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_IMPORT_REPORT'); ?></h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>Archivo</th>
                        <th>Resultado</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($importReport as $r):
                        $file = isset($r['file']) ? $r['file'] : '';
                        $status = isset($r['status']) ? $r['status'] : '';
                        $msg = isset($r['message']) ? $r['message'] : '';
                        $statusLabel = $status === 'imported' ? Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_IMPORTED') : ($status === 'skipped' ? Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_SKIPPED') : Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_ERROR'));
                        $rowClass = $status === 'imported' ? 'table-success' : ($status === 'skipped' ? 'table-warning' : 'table-danger');
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $statusLabel; ?></td>
                        <td><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Invoices Table: Serie|Numero, Fecha de Emision, NIT, Cliente, Total Factura (Q) -->
    <?php if (!empty($invoices)): ?>
        <table class="invoices-table">
            <thead>
                <tr>
                    <th>Serie | Número</th>
                    <th>Fecha de Emisión</th>
                    <th>NIT</th>
                    <th>Cliente</th>
                    <th style="text-align: right;">Total Factura (Q)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice):
                    $felExtra = [];
                    if (!empty($invoice->fel_extra) && is_string($invoice->fel_extra)) {
                        $felExtra = json_decode($invoice->fel_extra, true) ?: [];
                    }
                    $serie  = $felExtra['autorizacion_serie'] ?? '';
                    $numero = $felExtra['autorizacion_numero_dte'] ?? '';
                    $fechaEmision = !empty($invoice->fel_fecha_emision) ? $invoice->fel_fecha_emision : ($invoice->invoice_date ?? null);
                    if ($fechaEmision) {
                        $fechaEmision = HTMLHelper::_('date', $fechaEmision, 'd-m-Y H:i:s');
                    } else {
                        $fechaEmision = '—';
                    }
                    $nit = trim($invoice->client_nit ?? $invoice->fel_receptor_id ?? '');
                    if ($nit === '') {
                        $nit = '—';
                    }
                    $moneda = $invoice->currency ?? 'Q';
                ?>
                    <tr onclick="window.location.href='<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . (int) $invoice->id); ?>'">
                        <td>
                            <span class="invoice-serie-numero"><?php echo htmlspecialchars($serie ?: '—'); ?> | <?php echo htmlspecialchars($numero ?: '—'); ?></span>
                        </td>
                        <td><?php echo $fechaEmision; ?></td>
                        <td><?php echo htmlspecialchars($nit); ?></td>
                        <td><?php echo htmlspecialchars($invoice->client_name ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="invoice-amount"><?php echo number_format((float) ($invoice->invoice_amount ?? 0), 2); ?> <?php echo htmlspecialchars($moneda); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pagination): ?>
            <div class="pagination-wrapper" style="margin-top: 20px; text-align: center;">
                <?php echo $pagination->getListFooter(); ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_NO_INVOICES_FOUND'); ?></p>
            <p>
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&layout=create'); ?>" 
                   class="btn-create-invoice">
                    <i class="fas fa-plus"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_CREATE_FIRST_INVOICE'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

