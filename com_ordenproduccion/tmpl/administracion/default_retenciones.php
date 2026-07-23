<?php
/**
 * Administración → Retenciones (Constancia de Exención de IVA)
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 * @since       3.119.257
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\RetencionPdfHelper;

$retenciones = $this->get('retenciones');
if (!is_array($retenciones)) {
    $retenciones = isset($this->retenciones) && is_array($this->retenciones) ? $this->retenciones : [];
}
$pagination = $this->get('retencionesPagination');
if ($pagination === null && isset($this->retencionesPagination)) {
    $pagination = $this->retencionesPagination;
}
$tableAvailable = (bool) $this->get('retencionesTableAvailable');
$state = $this->state ?? new \Joomla\Registry\Registry();

$importReport = Factory::getApplication()->getSession()->get('com_ordenproduccion.retenciones_import_report', null);
if ($importReport !== null) {
    Factory::getApplication()->getSession()->set('com_ordenproduccion.retenciones_import_report', null);
}

$listLimit = (int) $state->get('list.limit', 20) ?: 20;
$filterSearch = (string) $state->get('filter.search', '');
$filterFechaFrom = (string) $state->get('filter.fecha_from', '');
$filterFechaTo = (string) $state->get('filter.fecha_to', '');

$esc = static function ($value, $default = '—') {
    if (is_string($value) && $value !== '') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    if (is_numeric($value)) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    return $default;
};
?>
<style>
.retenciones-section { margin-top: 0.5rem; }
.retenciones-header h2 { font-size: 1.35rem; margin-bottom: 1rem; }
.retenciones-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.retenciones-table th, .retenciones-table td { border: 1px solid #dee2e6; padding: 0.45rem 0.55rem; vertical-align: top; }
.retenciones-table th { background: #f8f9fa; white-space: nowrap; }
.retenciones-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.retenciones-table .uuid { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.78rem; word-break: break-all; }
.import-pdf-bar { margin: 0.75rem 0 1rem; }
.search-filter-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem; }
.search-filter-bar input { max-width: 14rem; }
</style>

<div class="retenciones-section">
    <div class="retenciones-header">
        <h2>
            <i class="fas fa-file-invoice"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_TITLE'); ?>
        </h2>
        <p class="text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_INTRO'); ?></p>
    </div>

    <?php if (!$tableAvailable): ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_TABLE_MISSING'); ?>
        </div>
    <?php else: ?>

    <form id="adminForm" method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=retenciones'); ?>"
          class="search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="retenciones" />
        <input type="hidden" name="limit" value="<?php echo (int) $listLimit; ?>" />
        <input type="hidden" name="limitstart" value="0" />
        <input type="text" name="filter_search" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_FILTER_SEARCH'); ?>"
               value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="date" name="filter_fecha_from" value="<?php echo htmlspecialchars($filterFechaFrom, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_FILTER_FECHA_FROM'); ?>" />
        <input type="date" name="filter_fecha_to" value="<?php echo htmlspecialchars($filterFechaTo, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_FILTER_FECHA_TO'); ?>" />
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-search"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER'); ?>
        </button>
    </form>

    <div class="import-pdf-bar d-flex flex-wrap gap-2 align-items-center">
        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.importRetencionesPdf'); ?>"
              method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="file" name="retencion_pdf[]" accept=".pdf,application/pdf" multiple="multiple"
                   class="form-control form-control-sm" style="max-width: 320px;"
                   title="<?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_IMPORT_HINT'); ?>" />
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-import"></i> <?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_IMPORT_PDF'); ?>
            </button>
        </form>
        <?php
        $exportUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.exportRetencionesExcel&format=raw');
        $exportUrl .= '&export_all=1';
        $exportUrl .= '&filter_search=' . rawurlencode($filterSearch);
        $exportUrl .= '&filter_fecha_from=' . rawurlencode($filterFechaFrom);
        $exportUrl .= '&filter_fecha_to=' . rawurlencode($filterFechaTo);
        ?>
        <a href="<?php echo $exportUrl; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-file-excel"></i> <?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_EXPORT_EXCEL'); ?>
        </a>
    </div>

    <?php if (!empty($importReport) && is_array($importReport)): ?>
    <div class="import-report-box mb-3">
        <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_IMPORT_REPORT'); ?></h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_FILE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_RESULT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_DETAIL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($importReport as $r):
                        $file = $r['file'] ?? '';
                        $status = $r['status'] ?? '';
                        $msg = $r['message'] ?? '';
                        $statusLabel = $status === 'imported'
                            ? Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_IMPORTED')
                            : ($status === 'skipped'
                                ? Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_SKIPPED')
                                : Text::_('COM_ORDENPRODUCCION_IMPORT_STATUS_ERROR'));
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

    <?php if (!empty($retenciones)): ?>
        <div class="table-responsive">
            <table class="retenciones-table">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_TIPO_DOCUMENTO'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_AUTORIZACION'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_SERIE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_NUMERO'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_FACT_AUTORIZACION'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_FACT_SERIE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_FACT_NUMERO'); ?></th>
                        <th style="text-align:right;"><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_FACT_IVA_EXENTO'); ?></th>
                        <th style="text-align:right;"><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_MONTO_RETENCION'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_FECHA'); ?></th>
                        <th style="text-align:right;"><?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_COL_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($retenciones as $row):
                        $fecha = !empty($row->fecha_emision) ? $row->fecha_emision : ($row->created ?? null);
                        $fechaStr = $fecha ? HTMLHelper::_('date', $fecha, 'd-m-Y H:i') : '—';
                        $iva = isset($row->fact_iva_exento) ? number_format((float) $row->fact_iva_exento, 2, '.', ',') : '—';
                        $ret = isset($row->monto_retencion) ? number_format((float) $row->monto_retencion, 2, '.', ',') : '—';
                        $totalNum = RetencionPdfHelper::resolveMontoTotal($row);
                        $total = number_format($totalNum, 2, '.', ',');
                    ?>
                    <tr>
                        <td><?php echo $esc($row->tipo_documento ?? ''); ?></td>
                        <td class="uuid"><?php echo $esc($row->autorizacion ?? ''); ?></td>
                        <td><?php echo $esc($row->serie ?? ''); ?></td>
                        <td><?php echo $esc($row->numero ?? ''); ?></td>
                        <td class="uuid"><?php echo $esc($row->fact_autorizacion ?? ''); ?></td>
                        <td><?php echo $esc($row->fact_serie ?? ''); ?></td>
                        <td><?php echo $esc($row->fact_numero ?? ''); ?></td>
                        <td class="num"><?php echo htmlspecialchars($iva, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><?php echo htmlspecialchars($ret, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($fechaStr, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num"><strong><?php echo htmlspecialchars($total, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination): ?>
            <div class="pagination-wrap mt-3">
                <?php echo $pagination->getPagesLinks(); ?>
                <div class="text-muted small mt-1">
                    <?php echo $pagination->getResultsCounter(); ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_ORDENPRODUCCION_RETENCIONES_EMPTY'); ?>
        </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
