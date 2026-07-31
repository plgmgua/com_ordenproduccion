<?php
/**
 * Control de Ventas tab Financiero (core.admin): pre-cotización financial overview.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$lang = Factory::getApplication()->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$fst = isset($this->financieroSubtab) ? (string) $this->financieroSubtab : 'listado';
$finItemId = isset($this->financieroResolvedItemId) ? max(0, (int) $this->financieroResolvedItemId) : 0;

if ($finItemId <= 0) {
    $finItemId = (int) Factory::getApplication()->input->getInt('Itemid', 0);
}

$finItemSuffix = $finItemId > 0 ? '&Itemid=' . $finItemId : '';
$finListadoActionQs = 'index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=listado' . $finItemSuffix;
$formActionRaw = Route::_($finListadoActionQs, false);

$fmt = static function ($v): string {
    $n = round((float) $v, 2);

    return 'Q ' . number_format($n, 2, '.', '');
};

$rowGrand = static function (object $r): float {
    $tf = isset($r->total_final) && $r->total_final !== null && $r->total_final !== '' ? (float) $r->total_final : null;
    $t  = isset($r->total) ? (float) $r->total : 0.0;
    $ma = isset($r->margen_adicional) && $r->margen_adicional !== null && $r->margen_adicional !== '' ? (float) $r->margen_adicional : 0.0;
    $base = $tf !== null ? $tf : $t;

    return round($base + $ma, 2);
};

$rowPrecotLabel = static function ($r): string {
    $pid = isset($r->id) ? (int) $r->id : 0;
    $raw = isset($r->number) ? trim((string) $r->number) : '';

    return $raw !== '' ? $raw : ('PRE-' . str_pad((string) max(1, $pid), 5, '0', STR_PAD_LEFT));
};

$urlPrecot = static function ($id): string {
    return Route::_('index.php?option=com_ordenproduccion&view=cotizador&precotizacion_id=' . (int) $id);
};

$urlCot    = static function ($qid): string {
    return Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $qid);
};

$urlOrden = static function ($oid): string {
    return Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . (int) $oid);
};

$fmtOrdenLabel = static function ($raw, $oid = 0): string {
    $raw = trim((string) $raw);
    $oid = (int) $oid;
    if ($raw !== '') {
        if (preg_match('/^\d+$/', $raw)) {
            return 'ORD-' . str_pad($raw, 6, '0', STR_PAD_LEFT);
        }

        return $raw;
    }
    if ($oid > 0) {
        return 'ORD-' . str_pad((string) $oid, 6, '0', STR_PAD_LEFT);
    }

    return '';
};

$confirmBadge = static function ($r): string {
    if (!property_exists($r, 'cotizacion_confirmada') || $r->cotizacion_confirmada === null) {
        return '—';
    }
    $c = (int) $r->cotizacion_confirmada;

    return $c === 1 ? Text::_('JYES') : Text::_('JNO');
};

$facturarSiNo = static function ($r): string {
    if (!property_exists($r, 'facturar') || $r->facturar === null || $r->facturar === '') {
        return '—';
    }
    $v  = $r->facturar;
    $on = ($v === true || $v === 1 || $v === '1' || (string) $v === '1');

    return $on ? Text::_('COM_ORDENPRODUCCION_FINANCIERO_FACTURAR_SI') : Text::_('COM_ORDENPRODUCCION_FINANCIERO_FACTURAR_NO');
};

$fmtProofVerified = static function ($v): string {
    if ($v === null || $v === '' || $v === '0000-00-00 00:00:00') {
        return '—';
    }

    try {
        return HTMLHelper::_('date', $v, Text::_('COM_ORDENPRODUCCION_FINANCIERO_VERIFIED_DATETIME_FMT'));
    } catch (\Throwable $e) {
        return '—';
    }
};

$fmtInvoiceDate = static function ($v): string {
    if ($v === null || $v === '' || $v === '0000-00-00 00:00:00' || $v === '0000-00-00') {
        return '—';
    }

    try {
        return HTMLHelper::_('date', $v, Text::_('DATE_FORMAT_LC4'));
    } catch (\Throwable $e) {
        $ts = strtotime((string) $v);

        return $ts ? date('Y-m-d', $ts) : '—';
    }
};

$fmtOrdenDate = $fmtInvoiceDate;

$pagoConfirmadoBadge = static function ($r): string {
    if (!\property_exists($r, 'financiero_pago_confirmado')) {
        return '—';
    }
    $v = $r->financiero_pago_confirmado;

    return ((int) $v === 1) ? Text::_('JYES') : Text::_('JNO');
};
?>
<style>
.financiero-subtabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 18px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Helvetica, Arial, sans-serif;
}
.financiero-subtab {
    padding: 7px 12px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-size: 10px;
    font-weight: 500;
    color: #666;
    text-decoration: none;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1.25;
    white-space: nowrap;
    transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}
.financiero-subtab i {
    display: block;
    font-size: 11px;
    margin-bottom: 2px;
}
.financiero-subtab:hover { color: #667eea; text-decoration: none; background: rgba(102, 126, 234, 0.05); }
.financiero-subtab.subtab-active { color: #667eea; border-bottom-color: #667eea; font-weight: 600; }
.table-financiero { font-size: 12px; }
.table-financiero th { white-space: nowrap; }
.table-mt940 { font-size: 10px; }
.table-mt940 th { white-space: nowrap; }
.bg-margen-total-row { background: rgba(144, 238, 144, 0.25); }
.bg-bono-ma-row { background: rgba(173, 216, 230, 0.35); }
</style>

<div class="financiero-subtabs">
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=listado' . $finItemSuffix); ?>"
       class="financiero-subtab <?php echo $fst === 'listado' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-list"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_SUBTAB_LISTADO'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=bonos' . $finItemSuffix); ?>"
       class="financiero-subtab <?php echo $fst === 'bonos' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-gift"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_SUBTAB_BONOS'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=cuentas_bancarias' . $finItemSuffix); ?>"
       class="financiero-subtab <?php echo $fst === 'cuentas_bancarias' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-university"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_SUBTAB_CUENTAS_BANCARIAS'); ?>
    </a>
</div>

<?php if ($fst === 'listado') : ?>
    <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_INTRO_LISTADO'); ?></p>
    <?php
    $fdFrom   = isset($this->financieroFilterDateFrom) ? (string) $this->financieroFilterDateFrom : '';
    $fdTo     = isset($this->financieroFilterDateTo) ? (string) $this->financieroFilterDateTo : '';
    $fAgent   = isset($this->financieroFilterAgent) ? (string) $this->financieroFilterAgent : '';
    $fFactur  = isset($this->financieroFilterFacturar) ? (string) $this->financieroFilterFacturar : '';
    $fCotConf = isset($this->financieroFilterCotizConfirmada) ? (string) $this->financieroFilterCotizConfirmada : '';
    $agentsOp = isset($this->financieroAgentFilterOptions) && is_array($this->financieroAgentFilterOptions) ? $this->financieroAgentFilterOptions : [];
    $fLim     = isset($this->financieroListLimit) ? max(5, min(200, (int) $this->financieroListLimit)) : 15;
    ?>
    <form method="get"
          action="<?php echo htmlspecialchars((string) $formActionRaw, ENT_QUOTES, 'UTF-8'); ?>"
          accept-charset="utf-8"
          class="d-flex flex-wrap gap-2 align-items-end mb-3 search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="financiero" />
        <input type="hidden" name="financiero_subtab" value="listado" />
        <input type="hidden" name="financiero_limit" value="<?php echo (int) $fLim; ?>" />
        <input type="hidden" name="financiero_limitstart" value="0" />
        <?php if ($finItemId > 0) : ?>
            <input type="hidden" name="Itemid" value="<?php echo (int) $finItemId; ?>" />
        <?php endif; ?>
        <div>
            <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_DATE_FROM'); ?></label>
            <input type="date" class="form-control form-control-sm" name="financiero_filter_date_from" value="<?php echo htmlspecialchars($fdFrom, ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div>
            <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_DATE_TO'); ?></label>
            <input type="date" class="form-control form-control-sm" name="financiero_filter_date_to" value="<?php echo htmlspecialchars($fdTo, ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div>
            <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_AGENTE'); ?></label>
            <select class="form-select form-select-sm" name="financiero_filter_agent" style="min-width: 12rem;" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_AGENTE'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_ALL'); ?></option>
                <?php foreach ($agentsOp as $al) :
                    $tal = trim((string) $al);
                    if ($tal === '') :
                        continue;
                    endif; ?>
                <option value="<?php echo htmlspecialchars($tal, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($fAgent !== '' && $fAgent === $tal) ? ' selected' : ''; ?>><?php echo htmlspecialchars($tal, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_FACTURAR'); ?></label>
            <select class="form-select form-select-sm" name="financiero_filter_facturar" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_FACTURAR'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_ALL'); ?></option>
                <option value="1"<?php echo $fFactur === '1' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FACTURAR_SI'); ?></option>
                <option value="0"<?php echo $fFactur === '0' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FACTURAR_NO'); ?></option>
            </select>
        </div>
        <div>
            <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_COTIZ_CONFIRMADA'); ?></label>
            <select class="form-select form-select-sm" name="financiero_filter_cotiz_confirmada" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_COTIZ_CONFIRMADA'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_ALL'); ?></option>
                <option value="1"<?php echo $fCotConf === '1' ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                <option value="0"<?php echo $fCotConf === '0' ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
            </select>
        </div>
        <div>
            <label class="form-label small mb-0">&nbsp;</label>
            <button type="submit" name="financiero_filter_submit" value="1" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_APPLY'); ?></button>
        </div>
    </form>
    <?php
        $exportParams = [
            'financiero_filter_date_from' => $fdFrom,
            'financiero_filter_date_to' => $fdTo,
            'financiero_filter_agent' => $fAgent,
            'financiero_filter_facturar' => $fFactur,
            'financiero_filter_cotiz_confirmada' => $fCotConf,
        ];
        $baseExport = 'index.php?option=com_ordenproduccion&task=administracion.exportFinancieroExcel&format=raw';
        foreach ($exportParams as $pk => $pv) {
            $baseExport .= '&' . $pk . '=' . rawurlencode((string) $pv);
        }
        if ($finItemId > 0) {
            $baseExport .= '&Itemid=' . $finItemId;
        }
        $exportFinUrl = Route::_($baseExport);
    ?>
    <p class="mb-3">
        <a href="<?php echo htmlspecialchars($exportFinUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener"><i class="fas fa-file-excel"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_EXPORT_EXCEL'); ?></a>
    </p>
    <?php $rowsFin = isset($this->financieroRows) && is_array($this->financieroRows) ? $this->financieroRows : []; ?>
    <?php if ($rowsFin === []) : ?>
        <p><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_EMPTY'); ?></p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-financiero align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_PRECOT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_ORDEN'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_ORDEN_DATE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_CLIENT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_FACTURAR'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_AGENTE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_INVOICE_NUMBER'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_INVOICE_DATE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_PAYMENT_PROOF_NUMBER'); ?></th>
                        <th class="align-top">
                            <div><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_PAYMENT_PROOF_VERIFIED_DATE'); ?></div>
                            <div class="fw-normal small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_PAYMENT_PROOF_VERIFIED_HINT'); ?></div>
                        </th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_PAGO_CONFIRMADO'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_SUBTOTAL'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_MARGEN'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_MARGEN_TOTAL_REF'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_IVA'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PARAM_ISR'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MARGEN_ADICIONAL'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_TOTAL'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COMISION_MARGEN_ADICIONAL'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_COTIZ'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_CONFIRM'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rowsFin as $r) : ?>
                        <?php
                        $pid = (int) ($r->id ?? 0);
                        $margenAm = isset($r->margen_amount) ? (float) $r->margen_amount : 0.0;
                        $margenAd = isset($r->margen_adicional) && $r->margen_adicional !== null && $r->margen_adicional !== '' ? (float) $r->margen_adicional : 0.0;
                        $margenTotDisplay = round($margenAm + $margenAd, 2);
                        $qid               = isset($r->linked_quotation_id) ? (int) $r->linked_quotation_id : 0;
                        $qnum              = isset($r->linked_quotation_number) ? trim((string) $r->linked_quotation_number) : '';
                        $invDisplay        = isset($r->financiero_invoice_number) ? trim((string) $r->financiero_invoice_number) : '';
                        $invDateDisplay    = $fmtInvoiceDate($r->financiero_invoice_date ?? null);
                        $ppDoc             = isset($r->financiero_payment_proof_number) ? trim((string) $r->financiero_payment_proof_number) : '';
                        $ordenIdFin        = isset($r->financiero_orden_id) ? (int) $r->financiero_orden_id : 0;
                        $ordenLabelFin     = $fmtOrdenLabel($r->financiero_orden_trabajo ?? '', $ordenIdFin);
                        $ordenDateFin      = $fmtOrdenDate($r->financiero_orden_date ?? null);
                        $clientNameFin     = isset($r->financiero_client_name) ? trim((string) $r->financiero_client_name) : '';
                        ?>
                    <tr>
                        <td>
                            <a href="<?php echo htmlspecialchars($urlPrecot($pid)); ?>"><?php echo htmlspecialchars($rowPrecotLabel($r)); ?></a>
                        </td>
                        <td>
                            <?php if ($ordenLabelFin !== '' && $ordenIdFin > 0) : ?>
                                <a href="<?php echo htmlspecialchars($urlOrden($ordenIdFin)); ?>"><?php echo htmlspecialchars($ordenLabelFin); ?></a>
                            <?php elseif ($ordenLabelFin !== '') : ?>
                                <?php echo htmlspecialchars($ordenLabelFin); ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($ordenDateFin); ?></td>
                        <td><?php echo $clientNameFin !== '' ? htmlspecialchars($clientNameFin) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($facturarSiNo($r)); ?></td>
                        <td><?php
                            $ag = isset($r->financiero_agent_label) ? trim((string) $r->financiero_agent_label) : '';
                            echo $ag !== '' ? htmlspecialchars($ag) : '—';
?></td>
                        <td><?php echo $invDisplay !== '' ? htmlspecialchars($invDisplay) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($invDateDisplay); ?></td>
                        <td><?php echo $ppDoc !== '' ? htmlspecialchars($ppDoc) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($fmtProofVerified($r->financiero_payment_proof_verified_date ?? null)); ?></td>
                        <td><?php echo htmlspecialchars($pagoConfirmadoBadge($r)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($r->lines_subtotal ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($margenAm)); ?></td>
                        <td class="text-end bg-margen-total-row"><?php echo htmlspecialchars($fmt($margenTotDisplay)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($r->iva_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($r->isr_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($r->comision_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($margenAd)); ?></td>
                        <td class="text-end fw-bold table-secondary"><?php echo htmlspecialchars($fmt($rowGrand($r))); ?></td>
                        <td class="text-end bg-bono-ma-row"><?php echo htmlspecialchars($fmt($r->comision_margen_adicional ?? 0)); ?></td>
                        <td>
                            <?php if ($qid > 0 && $qnum !== '') : ?>
                                <a href="<?php echo htmlspecialchars($urlCot($qid)); ?>"><?php echo htmlspecialchars($qnum); ?></a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($confirmBadge($r)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php
                $agg = $this->financieroAggregates ?? null;
                if ($agg && isset($agg->cnt) && (int) $agg->cnt > 0) :
                    ?>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td colspan="11"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_TOTAL_ROW_FILTERED'); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_lines_subtotal ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_margen_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt(($agg->sum_margen_amount ?? 0) + ($agg->sum_margen_adicional ?? 0))); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_iva_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_isr_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_comision_amount ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_margen_adicional ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_grand_total ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($agg->sum_comision_margen_adicional ?? 0)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php $fp = $this->financieroPagination ?? null;
        ?>
        <?php if ($fp && (int) ($this->financieroTotal ?? 0) > 0) : ?>
            <div class="com-content-pagination mt-3 small"><?php echo $fp->getListFooter(); ?></div>
        <?php endif; ?>
    <?php endif; ?>
<?php elseif ($fst === 'bonos') : ?>
    <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_INTRO_BONOS'); ?></p>
    <?php
    $brows = isset($this->financieroBonosByAgent) && is_array($this->financieroBonosByAgent) ? $this->financieroBonosByAgent : [];
    ?>
    <?php if ($brows === []) : ?>
        <p><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_EMPTY_BONOS'); ?></p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_AGENTE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA'); ?> (Σ)</th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_COMISION_MARGEN_ADICIONAL'); ?> (Σ)</th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_BONOS_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tbV = 0.0;
                    $tbMa = 0.0;
                    foreach ($brows as $b) :
                        $tbV += (float) ($b->sum_bono_venta ?? 0);
                        $tbMa += (float) ($b->sum_bono_margen_adicional ?? 0);
                        ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($b->agent_label ?? '—')); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($b->sum_bono_venta ?? 0)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($b->sum_bono_margen_adicional ?? 0)); ?></td>
                        <td class="text-end fw-semibold"><?php echo htmlspecialchars($fmt($b->sum_bonos_total ?? 0)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_TOTAL_ROW_ALL'); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($tbV)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($tbMa)); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars($fmt($tbV + $tbMa)); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
<?php elseif ($fst === 'cuentas_bancarias') : ?>
    <?php
    HTMLHelper::_('bootstrap.framework');
    HTMLHelper::_('form.csrf');
    $mt940SchemaOk     = !empty($this->financieroMt940SchemaOk);
    $mt940Accounts     = isset($this->financieroMt940BankAccountOptions) && \is_array($this->financieroMt940BankAccountOptions)
        ? $this->financieroMt940BankAccountOptions : [];
    $mt940BankFilter   = (int) ($this->financieroMt940FilterBankAccountId ?? 0);
    $mt940FilterMonth  = max(0, min(12, (int) ($this->financieroMt940FilterMonth ?? (int) \date('n'))));
    $mt940FilterYear   = max(2000, min(2100, (int) ($this->financieroMt940FilterYear ?? (int) \date('Y'))));
    $mt940Rows         = isset($this->financieroMt940Rows) && \is_array($this->financieroMt940Rows) ? $this->financieroMt940Rows : [];
    $mt940BalanceRows  = isset($this->financieroMt940BalanceRows) && \is_array($this->financieroMt940BalanceRows) ? $this->financieroMt940BalanceRows : [];
    $mt940LinkedByTxId = isset($this->financieroMt940LinkedByTxId) && \is_array($this->financieroMt940LinkedByTxId)
        ? $this->financieroMt940LinkedByTxId : [];
    $mt940LinkEnabled  = !empty($this->financieroMt940LinkEnabled);
    $mt940PaSearchUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.searchUnlinkedPaymentsForMt940&format=json', false);
    $mt940PaLinkAction = Route::_('index.php?option=com_ordenproduccion&task=administracion.linkMt940TransactionToPaymentProof', false);
    $mt940FormBaseQs   = 'index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=cuentas_bancarias' . $finItemSuffix;
    $mt940FormAction   = Route::_($mt940FormBaseQs, false);
    $mt940YearMin      = max(2000, (int) ($this->financieroMt940FilterYearMin ?? ((int) \date('Y') - 5)));
    $mt940YearMax      = min(2100, (int) ($this->financieroMt940FilterYearMax ?? ((int) \date('Y') + 1)));
    if ($mt940FilterYear > 0) {
        $mt940YearMin = min($mt940YearMin, $mt940FilterYear);
        $mt940YearMax = max($mt940YearMax, $mt940FilterYear);
    }
    $mt940MonthLangKeys = [
        1  => 'JANUARY',
        2  => 'FEBRUARY',
        3  => 'MARCH',
        4  => 'APRIL',
        5  => 'MAY',
        6  => 'JUNE',
        7  => 'JULY',
        8  => 'AUGUST',
        9  => 'SEPTEMBER',
        10 => 'OCTOBER',
        11 => 'NOVEMBER',
        12 => 'DECEMBER',
    ];

    $fmtMt940Amount = static function ($amount, string $dc, string $currency): string {
        $n = round((float) $amount, 2);
        $sign = $dc === 'D' ? '-' : '';
        if (!\in_array($currency, ['GTQ', 'USD'], true)) {
            $currency = 'GTQ';
        }
        $sym  = $currency === 'USD' ? 'USD ' : 'Q ';

        return $sign . $sym . number_format(abs($n), 2, '.', ',');
    };

    $fmtMt940Balance = static function ($amount, string $currency): string {
        if ($amount === null || $amount === '') {
            return '—';
        }
        if (!\in_array($currency, ['GTQ', 'USD'], true)) {
            $currency = 'GTQ';
        }
        $sym = $currency === 'USD' ? 'USD ' : 'Q ';

        return $sym . number_format((float) $amount, 2, '.', ',');
    };

    $mt940AccountNumberDisplay = static function (object $row): string {
        $acctNo = \trim((string) ($row->account_number ?? ''));
        if ($acctNo !== '') {
            return $acctNo;
        }
        $name = \trim((string) ($row->bank_account_name ?? ''));
        if ($name !== '' && \preg_match('/\(([^)]+)\)\s*$/', $name, $m)) {
            return \trim((string) ($m[1] ?? ''));
        }

        return $name;
    };
    ?>

    <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INTRO'); ?></p>

    <?php if (!$mt940SchemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'); ?></div>
    <?php elseif ($mt940Accounts === []) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_NO_ACCOUNTS'); ?></div>
    <?php else : ?>
        <form method="get" action="<?php echo htmlspecialchars((string) $mt940FormAction, ENT_QUOTES, 'UTF-8'); ?>" class="d-flex flex-wrap gap-2 align-items-end mb-3 search-filter-bar">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="administracion" />
            <input type="hidden" name="tab" value="financiero" />
            <input type="hidden" name="financiero_subtab" value="cuentas_bancarias" />
            <input type="hidden" name="mt940_limit" value="<?php echo (int) ($this->financieroMt940ListLimit ?? 25); ?>" />
            <input type="hidden" name="mt940_limitstart" value="0" />
            <?php if ($finItemId > 0) : ?>
                <input type="hidden" name="Itemid" value="<?php echo (int) $finItemId; ?>" />
            <?php endif; ?>
            <div>
                <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_YEAR'); ?></label>
                <select class="form-select form-select-sm" name="mt940_filter_year" style="min-width: 6rem;">
                    <?php for ($y = $mt940YearMax; $y >= $mt940YearMin; $y--) : ?>
                        <option value="<?php echo $y; ?>"<?php echo $mt940FilterYear === $y ? ' selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_MONTH'); ?></label>
                <select class="form-select form-select-sm" name="mt940_filter_month" style="min-width: 9rem;">
                    <option value="0"<?php echo $mt940FilterMonth === 0 ? ' selected' : ''; ?>>
                        <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_ALL_MONTHS'); ?>
                    </option>
                    <?php for ($m = 1; $m <= 12; $m++) :
                        $monthKey   = $mt940MonthLangKeys[$m] ?? '';
                        $monthLabel = $monthKey !== '' ? Text::_($monthKey) : (string) $m; ?>
                        <option value="<?php echo $m; ?>"<?php echo $mt940FilterMonth === $m ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_ACCOUNT'); ?></label>
                <select class="form-select form-select-sm" name="mt940_bank_account_id" style="min-width: 14rem;">
                    <option value="0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_ALL_ACCOUNTS'); ?></option>
                    <?php foreach ($mt940Accounts as $accId => $accLabel) : ?>
                        <option value="<?php echo (int) $accId; ?>"<?php echo $mt940BankFilter === (int) $accId ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $accLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-0">&nbsp;</label>
                <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_APPLY'); ?></button>
            </div>
            <?php
            $mt940ExportBase = 'index.php?option=com_ordenproduccion&task=administracion.exportMt940TransactionsExcel&format=raw'
                . '&mt940_filter_month=' . (int) $mt940FilterMonth
                . '&mt940_filter_year=' . (int) $mt940FilterYear
                . '&mt940_bank_account_id=' . (int) $mt940BankFilter;
            if ($finItemId > 0) {
                $mt940ExportBase .= '&Itemid=' . (int) $finItemId;
            }
            $mt940ExportUrl = Route::_($mt940ExportBase);
            ?>
            <div>
                <label class="form-label small mb-0">&nbsp;</label>
                <a href="<?php echo htmlspecialchars((string) $mt940ExportUrl, ENT_QUOTES, 'UTF-8'); ?>"
                   class="btn btn-success btn-sm"
                   target="_blank"
                   rel="noopener"
                   title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_EXPORT_HINT'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-file-excel" aria-hidden="true"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_EXPORT_EXCEL'); ?>
                </a>
            </div>
        </form>

        <?php if ($mt940BalanceRows !== []) : ?>
            <h3 class="h6 mt-2 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_BALANCES_TITLE'); ?></h3>
            <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_BALANCES_INTRO'); ?></p>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped table-mt940 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_ACCOUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_STATEMENT_DATE'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_OPENING'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_CLOSING'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mt940BalanceRows as $bal) :
                            $balCur  = (string) ($bal->currency ?? 'GTQ');
                            $balAcct = \trim((string) ($bal->bank_account_name ?? ''));
                            $balNo   = \trim((string) ($bal->account_number ?? ''));
                            if ($balAcct === '' && $balNo !== '') {
                                $balAcct = $balNo;
                            } elseif ($balNo !== '' && $balAcct !== '' && \strpos($balAcct, $balNo) === false) {
                                $balAcct .= ' (' . $balNo . ')';
                            }
                            ?>
                        <tr>
                            <td><?php echo $balAcct !== '' ? htmlspecialchars($balAcct) : '—'; ?></td>
                            <td><?php echo !empty($bal->statement_date) ? htmlspecialchars((string) $bal->statement_date) : '—'; ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($fmtMt940Balance($bal->opening_balance ?? null, $balCur)); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($fmtMt940Balance($bal->closing_balance ?? null, $balCur)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_TRANSACTIONS_TITLE'); ?></h3>

        <?php if ($mt940Rows === []) : ?>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_EMPTY'); ?></p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-mt940 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_ACCOUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_VALUE_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_REFERENCE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_DESCRIPTION'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_TYPE'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_AMOUNT'); ?></th>
                            <?php if ($mt940LinkEnabled) : ?>
                            <th class="text-nowrap"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_PAYMENT'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mt940Rows as $row) :
                            $txId     = (int) ($row->id ?? 0);
                            $dc       = (string) ($row->debit_credit ?? '');
                            $linked   = $txId > 0 && isset($mt940LinkedByTxId[$txId]) ? $mt940LinkedByTxId[$txId] : null;
                            $canLink  = $mt940LinkEnabled && $dc === 'C' && $linked === null;
                            $currency = \strtoupper(\trim((string) ($row->currency ?? '')));
                            $stmtCur  = \strtoupper(\trim((string) ($row->statement_currency ?? '')));
                            if (!\in_array($currency, ['GTQ', 'USD'], true)) {
                                $currency = \in_array($stmtCur, ['GTQ', 'USD'], true) ? $stmtCur : 'GTQ';
                            } elseif ($currency === 'GTQ' && $stmtCur === 'USD') {
                                $currency = 'USD';
                            }
                            $typeLbl = $dc === 'D'
                                ? Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_TYPE_DEBIT')
                                : ($dc === 'C' ? Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_TYPE_CREDIT') : '—');
                            $acctDisplay = $mt940AccountNumberDisplay($row);
                            ?>
                        <tr>
                            <td><?php echo $acctDisplay !== '' ? htmlspecialchars($acctDisplay) : '—'; ?></td>
                            <td><?php echo !empty($row->transaction_date) ? htmlspecialchars((string) $row->transaction_date) : '—'; ?></td>
                            <td><?php echo !empty($row->value_date) ? htmlspecialchars((string) $row->value_date) : '—'; ?></td>
                            <td><?php echo !empty($row->reference) ? htmlspecialchars((string) $row->reference) : '—'; ?></td>
                            <td><?php echo !empty($row->description) ? htmlspecialchars((string) $row->description) : '—'; ?></td>
                            <td><?php echo htmlspecialchars($typeLbl); ?></td>
                            <td class="text-end <?php echo $dc === 'D' ? 'text-danger' : 'text-success'; ?>">
                                <?php echo htmlspecialchars($fmtMt940Amount($row->amount ?? 0, $dc, $currency)); ?>
                            </td>
                            <?php if ($mt940LinkEnabled) : ?>
                            <td class="text-nowrap">
                                <?php if (\is_array($linked)) :
                                    $paLabel = trim((string) ($linked['pa_label'] ?? ''));
                                    $orderId = (int) ($linked['order_id'] ?? 0);
                                    $proofId = (int) ($linked['payment_proof_id'] ?? 0);
                                    $paUrl   = $orderId > 0
                                        ? Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId . ($proofId > 0 ? '&proof_id=' . $proofId : ''), false)
                                        : '';
                                    ?>
                                    <?php if ($paUrl !== '') : ?>
                                        <a href="<?php echo htmlspecialchars($paUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($paLabel, ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else : ?>
                                        <?php echo htmlspecialchars($paLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                <?php elseif ($canLink) : ?>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm fin-mt940-link-pa-btn"
                                            data-tx-id="<?php echo $txId; ?>"
                                            data-tx-ref="<?php echo htmlspecialchars((string) ($row->reference ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-tx-date="<?php echo htmlspecialchars((string) ($row->transaction_date ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-tx-amount="<?php echo htmlspecialchars(number_format(round((float) ($row->amount ?? 0), 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_BTN'); ?>
                                    </button>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $mt940Pag = $this->financieroMt940Pagination ?? null; ?>
            <?php if ($mt940Pag && (int) ($this->financieroMt940Total ?? 0) > 0) : ?>
                <div class="com-content-pagination mt-3 small"><?php echo $mt940Pag->getListFooter(); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($mt940LinkEnabled && $mt940SchemaOk && $mt940Accounts !== []) : ?>
        <div class="modal fade" id="finMt940LinkPaModal" tabindex="-1" aria-labelledby="finMt940LinkPaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="finMt940LinkPaModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_MODAL_TITLE'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2" id="fin-mt940-link-tx-summary"></p>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" class="form-control" id="fin-mt940-link-pa-search" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_SEARCH_PLACEHOLDER'); ?>" />
                            <button type="button" class="btn btn-outline-secondary" id="fin-mt940-link-pa-search-btn"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_SEARCH_BTN'); ?></button>
                        </div>
                        <div id="fin-mt940-link-pa-results" class="list-group small"></div>
                        <p class="small text-muted mt-2 mb-0 d-none" id="fin-mt940-link-pa-empty"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_EMPTY'); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <form method="post" action="<?php echo htmlspecialchars($mt940PaLinkAction, ENT_QUOTES, 'UTF-8'); ?>" id="fin-mt940-link-pa-form" class="d-none">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="tx_id" id="fin-mt940-link-form-tx-id" value="" />
            <input type="hidden" name="proof_id" id="fin-mt940-link-form-proof-id" value="" />
        </form>
        <script>
        (function () {
          var searchUrl = <?php echo json_encode($mt940PaSearchUrl); ?>;
          var modalEl = document.getElementById('finMt940LinkPaModal');
          var summaryEl = document.getElementById('fin-mt940-link-tx-summary');
          var resultsEl = document.getElementById('fin-mt940-link-pa-results');
          var emptyEl = document.getElementById('fin-mt940-link-pa-empty');
          var searchInput = document.getElementById('fin-mt940-link-pa-search');
          var searchBtn = document.getElementById('fin-mt940-link-pa-search-btn');
          var formEl = document.getElementById('fin-mt940-link-pa-form');
          var formTxId = document.getElementById('fin-mt940-link-form-tx-id');
          var formProofId = document.getElementById('fin-mt940-link-form-proof-id');
          var activeTxId = 0;
          var modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

          function escHtml(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
          }

          function renderRows(rows) {
            if (!resultsEl) return;
            resultsEl.innerHTML = '';
            if (!rows || !rows.length) {
              if (emptyEl) emptyEl.classList.remove('d-none');
              return;
            }
            if (emptyEl) emptyEl.classList.add('d-none');
            rows.forEach(function (row) {
              var btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'list-group-item list-group-item-action text-start fin-mt940-pick-pa-btn';
              btn.setAttribute('data-proof-id', String(row.proof_id || '0'));
              var doc = row.document_number ? escHtml(row.document_number) : '—';
              var dt = row.document_date ? escHtml(row.document_date) : '—';
              var amt = typeof row.amount === 'number' ? row.amount.toFixed(2) : escHtml(row.amount);
              btn.innerHTML = '<div><strong>' + escHtml(row.pa_label || '') + '</strong></div>'
                + '<div class="text-muted">' + <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_DOCUMENT_NUMBER')); ?> + ': ' + doc
                + ' · ' + <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_DOCUMENT_DATE')); ?> + ': ' + dt
                + ' · Q ' + amt + '</div>';
              resultsEl.appendChild(btn);
            });
          }

          function loadCandidates() {
            if (activeTxId < 1) return;
            if (resultsEl) resultsEl.innerHTML = '<div class="text-muted small p-2">…</div>';
            if (emptyEl) emptyEl.classList.add('d-none');
            var q = searchInput ? searchInput.value.trim() : '';
            var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'tx_id=' + encodeURIComponent(String(activeTxId));
            if (q !== '') url += '&search=' + encodeURIComponent(q);
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
              .then(function (r) { return r.json(); })
              .then(function (data) {
                if (!data || !data.success) {
                  renderRows([]);
                  return;
                }
                renderRows((data.data && data.data.rows) ? data.data.rows : []);
              })
              .catch(function () { renderRows([]); });
          }

          document.querySelectorAll('.fin-mt940-link-pa-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
              activeTxId = parseInt(btn.getAttribute('data-tx-id') || '0', 10);
              if (activeTxId < 1 || !modal) return;
              var ref = btn.getAttribute('data-tx-ref') || '';
              var dt = btn.getAttribute('data-tx-date') || '';
              var amt = btn.getAttribute('data-tx-amount') || '';
              if (summaryEl) {
                var summaryTpl = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_TX_SUMMARY')); ?>;
                summaryEl.textContent = summaryTpl
                  .replace('%1$s', ref)
                  .replace('%2$s', dt)
                  .replace('%3$s', amt);
              }
              if (searchInput) searchInput.value = '';
              loadCandidates();
              modal.show();
            });
          });

          if (searchBtn) searchBtn.addEventListener('click', loadCandidates);
          if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
              if (e.key === 'Enter') {
                e.preventDefault();
                loadCandidates();
              }
            });
          }

          document.addEventListener('click', function (e) {
            var pick = e.target.closest('.fin-mt940-pick-pa-btn');
            if (!pick) return;
            var proofId = parseInt(pick.getAttribute('data-proof-id') || '0', 10);
            if (proofId < 1 || activeTxId < 1 || !formEl || !formTxId || !formProofId) return;
            var msg = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_LINK_PA_CONFIRM')); ?>;
            if (!window.confirm(msg)) return;
            formTxId.value = String(activeTxId);
            formProofId.value = String(proofId);
            formEl.submit();
          });
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
