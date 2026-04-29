<?php
/**
 * Control de Ventas tab Financiero (core.admin): pre-cotización financial overview.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$lang = Joomla\CMS\Factory::getApplication()->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$fst = isset($this->financieroSubtab) ? (string) $this->financieroSubtab : 'listado';

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
?>
<style>
.financiero-subtabs { display: flex; flex-wrap: wrap; gap: 0; border-bottom: 2px solid #dee2e6; margin-bottom: 18px; }
.financiero-subtab { padding: 10px 20px; background: transparent; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; font-size: 14px; font-weight: 600; color: #666; text-decoration: none; }
.financiero-subtab:hover { color: #667eea; text-decoration: none; }
.financiero-subtab.subtab-active { color: #667eea; border-bottom-color: #667eea; }
.table-financiero { font-size: 12px; }
.table-financiero th { white-space: nowrap; }
.bg-margen-total-row { background: rgba(144, 238, 144, 0.25); }
.bg-bono-ma-row { background: rgba(173, 216, 230, 0.35); }
</style>

<div class="financiero-subtabs">
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=listado'); ?>"
       class="financiero-subtab <?php echo $fst === 'listado' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-list"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_SUBTAB_LISTADO'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero&financiero_subtab=bonos'); ?>"
       class="financiero-subtab <?php echo $fst === 'bonos' ? 'subtab-active' : ''; ?>">
        <i class="fas fa-gift"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_SUBTAB_BONOS'); ?>
    </a>
</div>

<?php if ($fst === 'listado') : ?>
    <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_INTRO_LISTADO'); ?></p>
    <?php
    $fdFrom   = isset($this->financieroFilterDateFrom) ? (string) $this->financieroFilterDateFrom : '';
    $fdTo     = isset($this->financieroFilterDateTo) ? (string) $this->financieroFilterDateTo : '';
    $fAgent   = isset($this->financieroFilterAgent) ? (string) $this->financieroFilterAgent : '';
    $fFactur  = isset($this->financieroFilterFacturar) ? (string) $this->financieroFilterFacturar : '';
    $agentsOp = isset($this->financieroAgentFilterOptions) && is_array($this->financieroAgentFilterOptions) ? $this->financieroAgentFilterOptions : [];
    $fLim     = isset($this->financieroListLimit) ? max(5, min(200, (int) $this->financieroListLimit)) : 50;
    ?>
    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=financiero'); ?>"
          class="d-flex flex-wrap gap-2 align-items-end mb-3 search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="financiero" />
        <input type="hidden" name="financiero_subtab" value="listado" />
        <input type="hidden" name="financiero_limit" value="<?php echo (int) $fLim; ?>" />
        <input type="hidden" name="financiero_limitstart" value="0" />
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
            <label class="form-label small mb-0">&nbsp;</label>
            <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_FILTER_APPLY'); ?></button>
        </div>
    </form>
    <?php
        $exportParams = [
            'financiero_filter_date_from' => $fdFrom,
            'financiero_filter_date_to' => $fdTo,
            'financiero_filter_agent' => $fAgent,
            'financiero_filter_facturar' => $fFactur,
        ];
        $baseExport = 'index.php?option=com_ordenproduccion&task=administracion.exportFinancieroExcel&format=raw';
        foreach ($exportParams as $pk => $pv) {
            $baseExport .= '&' . $pk . '=' . rawurlencode((string) $pv);
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
                        <td colspan="3"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_TOTAL_ROW_FILTERED'); ?></td>
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
<?php endif; ?>
