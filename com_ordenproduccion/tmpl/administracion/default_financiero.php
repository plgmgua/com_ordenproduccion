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
       class="financiero-subtab <?php echo \in_array($fst, ['cuentas_bancarias', 'cuentas_bancarias_importar'], true) ? 'subtab-active' : ''; ?>">
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
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_FACTURAR'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_AGENTE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_COL_INVOICE_NUMBER'); ?></th>
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
                        $ppDoc             = isset($r->financiero_payment_proof_number) ? trim((string) $r->financiero_payment_proof_number) : '';
                        ?>
                    <tr>
                        <td>
                            <a href="<?php echo htmlspecialchars($urlPrecot($pid)); ?>"><?php echo htmlspecialchars($rowPrecotLabel($r)); ?></a>
                        </td>
                        <td><?php echo htmlspecialchars($facturarSiNo($r)); ?></td>
                        <td><?php
                            $ag = isset($r->financiero_agent_label) ? trim((string) $r->financiero_agent_label) : '';
                            echo $ag !== '' ? htmlspecialchars($ag) : '—';
?></td>
                        <td><?php echo $invDisplay !== '' ? htmlspecialchars($invDisplay) : '—'; ?></td>
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
                        <td colspan="7"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_TOTAL_ROW_FILTERED'); ?></td>
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
<?php elseif (\in_array($fst, ['cuentas_bancarias', 'cuentas_bancarias_importar'], true)) : ?>
    <?php
    HTMLHelper::_('bootstrap.framework');
    HTMLHelper::_('form.csrf');
    $mt940SchemaOk     = !empty($this->financieroMt940SchemaOk);
    $mt940Accounts     = isset($this->financieroMt940BankAccountOptions) && \is_array($this->financieroMt940BankAccountOptions)
        ? $this->financieroMt940BankAccountOptions : [];
    $mt940BankFilter   = (int) ($this->financieroMt940FilterBankAccountId ?? 0);
    $mt940FilterMonth  = max(1, min(12, (int) ($this->financieroMt940FilterMonth ?? (int) \date('n'))));
    $mt940FilterYear   = max(2000, min(2100, (int) ($this->financieroMt940FilterYear ?? (int) \date('Y'))));
    $mt940Rows         = isset($this->financieroMt940Rows) && \is_array($this->financieroMt940Rows) ? $this->financieroMt940Rows : [];
    $mt940ImportRows   = isset($this->financieroMt940ImportRows) && \is_array($this->financieroMt940ImportRows) ? $this->financieroMt940ImportRows : [];
    $mt940ImportUrl    = Route::_('index.php?option=com_ordenproduccion&task=administracion.importMt940File&format=json', false);
    $mt940InitialUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.runMt940InitialImport&format=json', false);
    $mt940ClearUrl     = Route::_('index.php?option=com_ordenproduccion&task=administracion.clearMt940ImportedData&format=json', false);
    $mt940DatosQs      = 'index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=cuentas_bancarias'
        . '&mt940_filter_month=' . $mt940FilterMonth . '&mt940_filter_year=' . $mt940FilterYear . $finItemSuffix;
    $mt940ImportQs     = 'index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=cuentas_bancarias_importar' . $finItemSuffix;
    $mt940FormAction   = Route::_($mt940DatosQs, false);
    $mt940Token        = \Joomla\CMS\Session\Session::getFormToken();
    $mt940YearMin      = (int) \date('Y') - 5;
    $mt940YearMax      = (int) \date('Y') + 1;

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
    ?>

    <div class="financiero-subtabs mb-3" style="border-bottom-width: 1px;">
        <a href="<?php echo Route::_($mt940DatosQs, false); ?>"
           class="financiero-subtab <?php echo $fst === 'cuentas_bancarias' ? 'subtab-active' : ''; ?>">
            <i class="fas fa-table"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_SUBTAB_DATOS'); ?>
        </a>
        <a href="<?php echo Route::_($mt940ImportQs, false); ?>"
           class="financiero-subtab <?php echo $fst === 'cuentas_bancarias_importar' ? 'subtab-active' : ''; ?>">
            <i class="fas fa-file-import"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_SUBTAB_IMPORTAR'); ?>
        </a>
    </div>

    <?php if ($fst === 'cuentas_bancarias') : ?>
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
                <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_MONTH'); ?></label>
                <select class="form-select form-select-sm" name="mt940_filter_month" style="min-width: 9rem;">
                    <?php for ($m = 1; $m <= 12; $m++) :
                        $monthLabel = HTMLHelper::_('date', \sprintf('2020-%02d-01', $m), 'F'); ?>
                        <option value="<?php echo $m; ?>"<?php echo $mt940FilterMonth === $m ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_FILTER_YEAR'); ?></label>
                <select class="form-select form-select-sm" name="mt940_filter_year" style="min-width: 6rem;">
                    <?php for ($y = $mt940YearMax; $y >= $mt940YearMin; $y--) : ?>
                        <option value="<?php echo $y; ?>"<?php echo $mt940FilterYear === $y ? ' selected' : ''; ?>><?php echo $y; ?></option>
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
        </form>

        <h3 class="h6 mt-2 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_STATEMENTS_TITLE'); ?></h3>
        <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_STATEMENTS_INTRO'); ?></p>
        <?php if ($mt940ImportRows === []) : ?>
            <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_STATEMENTS_EMPTY'); ?></p>
        <?php else : ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_STATEMENT_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_ACCOUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_STATEMENT_REF'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_SEQUENCE'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_OPENING'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_CLOSING'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_AVAILABLE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_TX_COUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_SOURCE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_IMPORTED'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mt940ImportRows as $imp) :
                            $impCur   = (string) ($imp->currency ?? 'GTQ');
                            $impAcct  = trim((string) ($imp->bank_account_name ?? ''));
                            $impNo    = trim((string) ($imp->account_number ?? ''));
                            if ($impAcct === '' && $impNo !== '') {
                                $impAcct = $impNo;
                            } elseif ($impNo !== '' && $impAcct !== '') {
                                $impAcct .= ' (' . $impNo . ')';
                            }
                            ?>
                        <tr>
                            <td><?php echo !empty($imp->statement_date) ? htmlspecialchars((string) $imp->statement_date) : '—'; ?></td>
                            <td><?php echo $impAcct !== '' ? htmlspecialchars($impAcct) : '—'; ?></td>
                            <td><?php echo !empty($imp->statement_reference) ? htmlspecialchars((string) $imp->statement_reference) : '—'; ?></td>
                            <td><?php echo !empty($imp->statement_sequence) ? htmlspecialchars((string) $imp->statement_sequence) : '—'; ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($fmtMt940Balance($imp->opening_balance ?? null, $impCur)); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($fmtMt940Balance($imp->closing_balance ?? null, $impCur)); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($fmtMt940Balance($imp->closing_available_balance ?? null, $impCur)); ?></td>
                            <td><?php echo (int) ($imp->transactions_count ?? 0); ?></td>
                            <td><code class="small"><?php echo !empty($imp->filename) ? htmlspecialchars((string) $imp->filename) : '—'; ?></code></td>
                            <td><?php echo !empty($imp->imported_at) ? htmlspecialchars(HTMLHelper::_('date', $imp->imported_at, Text::_('COM_ORDENPRODUCCION_FINANCIERO_VERIFIED_DATETIME_FMT'))) : '—'; ?></td>
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
                <table class="table table-sm table-striped table-financiero align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_VALUE_DATE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_ACCOUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_CODE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_REFERENCE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_DESCRIPTION'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_TYPE'); ?></th>
                            <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_AMOUNT'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_SOURCE'); ?></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_COL_IMPORTED'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mt940Rows as $row) :
                            $dc       = (string) ($row->debit_credit ?? '');
                            $currency = (string) ($row->currency ?? 'GTQ');
                            if (!\in_array($currency, ['GTQ', 'USD'], true)) {
                                $stmtCur = (string) ($row->statement_currency ?? '');
                                $currency = \in_array($stmtCur, ['GTQ', 'USD'], true) ? $stmtCur : 'GTQ';
                            }
                            $acctLbl  = trim((string) ($row->bank_account_name ?? ''));
                            $acctNo   = trim((string) ($row->account_number ?? ''));
                            if ($acctLbl === '' && $acctNo !== '') {
                                $acctLbl = $acctNo;
                            } elseif ($acctNo !== '' && $acctLbl !== '') {
                                $acctLbl .= ' (' . $acctNo . ')';
                            }
                            $typeLbl = $dc === 'D'
                                ? Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_TYPE_DEBIT')
                                : ($dc === 'C' ? Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_TYPE_CREDIT') : '—');
                            ?>
                        <tr>
                            <td><?php echo !empty($row->transaction_date) ? htmlspecialchars((string) $row->transaction_date) : '—'; ?></td>
                            <td><?php echo !empty($row->value_date) ? htmlspecialchars((string) $row->value_date) : '—'; ?></td>
                            <td><?php echo $acctLbl !== '' ? htmlspecialchars($acctLbl) : '—'; ?></td>
                            <td><?php echo !empty($row->transaction_code) ? htmlspecialchars((string) $row->transaction_code) : '—'; ?></td>
                            <td><?php echo !empty($row->reference) ? htmlspecialchars((string) $row->reference) : '—'; ?></td>
                            <td><?php echo !empty($row->description) ? htmlspecialchars((string) $row->description) : '—'; ?></td>
                            <td><?php echo htmlspecialchars($typeLbl); ?></td>
                            <td class="text-end <?php echo $dc === 'D' ? 'text-danger' : 'text-success'; ?>">
                                <?php echo htmlspecialchars($fmtMt940Amount($row->amount ?? 0, $dc, $currency)); ?>
                            </td>
                            <td><code class="small"><?php echo !empty($row->source_filename) ? htmlspecialchars((string) $row->source_filename) : '—'; ?></code></td>
                            <td><?php echo !empty($row->imported_at) ? htmlspecialchars(HTMLHelper::_('date', $row->imported_at, Text::_('COM_ORDENPRODUCCION_FINANCIERO_VERIFIED_DATETIME_FMT'))) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $mt940Pag = $this->financieroMt940Pagination ?? null; ?>
            <?php if ($mt940Pag && ((int) ($this->financieroMt940Total ?? 0) > 0 || (int) ($this->financieroMt940ImportTotal ?? 0) > 0)) : ?>
                <div class="com-content-pagination mt-3 small"><?php echo $mt940Pag->getListFooter(); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php elseif ($fst === 'cuentas_bancarias_importar') : ?>
    <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_SUBTAB_INTRO'); ?></p>

    <?php if (!$mt940SchemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'); ?></div>
    <?php elseif ($mt940Accounts === []) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_NO_ACCOUNTS'); ?></div>
    <?php else : ?>
        <div class="card mb-3 border-primary">
            <div class="card-body py-3">
                <h3 class="h6 mb-2"><i class="fas fa-inbox"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_TITLE'); ?></h3>
                <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_DESC'); ?></p>
                <p class="small text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_DEDUP_NOTICE'); ?></p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-mt940-initial-import" data-url="<?php echo htmlspecialchars($mt940InitialUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-cloud-download-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_BTN'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-mt940-clear-imported"
                            data-url="<?php echo htmlspecialchars($mt940ClearUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            data-confirm="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-trash-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_BTN'); ?>
                    </button>
                </div>
                <div id="mt940-initial-result" class="d-none mt-2">
                    <div id="mt940-initial-alert" class="alert mb-0" role="alert"></div>
                    <pre id="mt940-initial-json" class="bg-light border rounded p-2 small mb-0 mt-2" style="max-height: 180px; overflow: auto;"></pre>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body py-3">
                <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_TITLE'); ?></h3>
                <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_DESC'); ?></p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="file" class="form-control form-control-sm" id="mt940-import-file" accept=".txt,.TXT,text/plain" style="max-width: 320px;">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-mt940-import" data-url="<?php echo htmlspecialchars($mt940ImportUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-file-import"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_BTN'); ?>
                    </button>
                </div>
                <div id="mt940-import-result" class="d-none mt-2">
                    <div id="mt940-import-alert" class="alert mb-0" role="alert"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php endif; ?>

    <?php if ($fst === 'cuentas_bancarias_importar') : ?>
    <script>
    (function () {
        var token = <?php echo json_encode($mt940Token); ?>;
        var runningInitial = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_RUNNING')); ?>;
        var clearingLabel = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_RUNNING')); ?>;
        var initialBtn = document.getElementById('btn-mt940-initial-import');
        var clearBtn = document.getElementById('btn-mt940-clear-imported');
        var initialResult = document.getElementById('mt940-initial-result');
        var initialAlert = document.getElementById('mt940-initial-alert');
        var initialJson = document.getElementById('mt940-initial-json');

        if (initialBtn) {
            initialBtn.addEventListener('click', function () {
                var url = initialBtn.getAttribute('data-url');
                if (!url) {
                    return;
                }
                var orig = initialBtn.innerHTML;
                initialBtn.disabled = true;
                initialBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningInitial;
                initialResult.classList.remove('d-none');
                initialAlert.className = 'alert alert-info mb-0';
                initialAlert.textContent = runningInitial;
                initialJson.textContent = '';

                var body = new URLSearchParams();
                body.append(token, '1');

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: body.toString()
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var ok = !!data.success;
                        initialAlert.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                        initialAlert.textContent = data.message || (ok ? 'OK' : 'Error');
                        initialJson.textContent = JSON.stringify(data, null, 2);
                        if (ok && (data.data?.files_imported > 0 || data.data?.transactions_imported > 0)) {
                            window.setTimeout(function () { window.location.reload(); }, 1500);
                        }
                    })
                    .catch(function (err) {
                        initialAlert.className = 'alert alert-danger mb-0';
                        initialAlert.textContent = err && err.message ? err.message : 'Error';
                    })
                    .finally(function () {
                        initialBtn.disabled = false;
                        initialBtn.innerHTML = orig;
                    });
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                var url = clearBtn.getAttribute('data-url');
                var confirmMsg = clearBtn.getAttribute('data-confirm') || 'Clear all imported MT-940 data?';
                if (!url || !window.confirm(confirmMsg)) {
                    return;
                }
                var orig = clearBtn.innerHTML;
                clearBtn.disabled = true;
                if (initialBtn) {
                    initialBtn.disabled = true;
                }
                clearBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + clearingLabel;
                initialResult.classList.remove('d-none');
                initialAlert.className = 'alert alert-info mb-0';
                initialAlert.textContent = clearingLabel;
                initialJson.textContent = '';

                var body = new URLSearchParams();
                body.append(token, '1');

                fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: body.toString()
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var ok = !!data.success;
                        initialAlert.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                        initialAlert.textContent = data.message || (ok ? 'OK' : 'Error');
                        initialJson.textContent = JSON.stringify(data, null, 2);
                        if (ok) {
                            window.setTimeout(function () { window.location.reload(); }, 1200);
                        }
                    })
                    .catch(function (err) {
                        initialAlert.className = 'alert alert-danger mb-0';
                        initialAlert.textContent = err && err.message ? err.message : 'Error';
                    })
                    .finally(function () {
                        clearBtn.disabled = false;
                        clearBtn.innerHTML = orig;
                        if (initialBtn) {
                            initialBtn.disabled = false;
                        }
                    });
            });
        }
    })();
    (function () {
        var token = <?php echo json_encode($mt940Token); ?>;
        var btn = document.getElementById('btn-mt940-import');
        var fileInput = document.getElementById('mt940-import-file');
        var resultBox = document.getElementById('mt940-import-result');
        var alertBox = document.getElementById('mt940-import-alert');
        if (!btn || !fileInput) {
            return;
        }
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url');
            if (!url || !fileInput.files || !fileInput.files[0]) {
                return;
            }
            var fd = new FormData();
            fd.append('mt940_file', fileInput.files[0]);
            fd.append(token, '1');
            btn.disabled = true;
            resultBox.classList.remove('d-none');
            alertBox.className = 'alert alert-info mb-0';
            alertBox.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_RUNNING')); ?>;
            fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var ok = !!data.success;
                    alertBox.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                    alertBox.textContent = data.message || (ok ? 'OK' : 'Error');
                    if (ok && !data.data?.skipped) {
                        window.setTimeout(function () { window.location.reload(); }, 1200);
                    }
                })
                .catch(function (err) {
                    alertBox.className = 'alert alert-danger mb-0';
                    alertBox.textContent = err && err.message ? err.message : 'Error';
                })
                .finally(function () { btn.disabled = false; });
        });
    })();
    </script>
    <?php endif; ?>
<?php endif; ?>
