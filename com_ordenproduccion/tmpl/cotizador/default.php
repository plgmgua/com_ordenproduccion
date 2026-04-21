<?php
/**
 * Pre-Cotización list (current user). "Nueva Pre-Cotización" opens modal to choose template or create blank.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\PrecotizacionModel;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Cotizador\HtmlView $this */

$items      = $this->items ?? [];
$pagination = $this->pagination ?? null;
$templates  = $this->templates ?? [];
$showOfertaColumn   = !empty($this->hasOfertaColumn);
$showFacturarColumn = !empty($this->hasFacturarColumn);
$state = $this->state;
$fv = static function (string $key, string $default = '') use ($state) : string {
    $v = $state->get('filter.' . $key);
    if ($v === null || $v === '') {
        return $default;
    }

    return is_scalar($v) ? (string) $v : $default;
};
$fInt = static function (string $key) use ($state) : int {
    return (int) $state->get('filter.' . $key, 0);
};
$dateIn = static function (string $key) use ($fv) : string {
    $s = trim($fv($key));

    return strlen($s) >= 10 ? substr($s, 0, 10) : '';
};
$colCount = 6 + (!empty($this->showSalesAgentColumn) ? 1 : 0) + ($showOfertaColumn ? 1 : 0) + ($showFacturarColumn ? 1 : 0);
$listLimit = (int) $state->get('list.limit', 20);
$filterFormAction = Route::_('index.php?option=com_ordenproduccion&view=cotizador', false);
$filterClearUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizador&filter_reset=1', false);
$addFromTemplateUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.addFromTemplate', false);
$labelNewBlank = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NEW_BLANK');
if (strpos($labelNewBlank, 'COM_ORDENPRODUCCION_') === 0) {
    $labelNewBlank = 'Crear en blanco';
}
$salesAgentOpts = $this->salesAgentFilterOptions ?? [];
?>

<div class="com-ordenproduccion-precotizacion-list container py-4">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_TITLE'); ?></h1>

    <p class="lead"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_DESC'); ?></p>

    <div class="mb-3">
        <form action="<?php echo htmlspecialchars($addFromTemplateUrl); ?>" method="post" class="d-flex flex-nowrap align-items-center gap-2">
            <?php echo HTMLHelper::_('form.token'); ?>
            <select name="template_id" id="new-precotizacion-template" class="form-select" style="width: auto; max-width: 360px;">
                <option value="0"><?php echo htmlspecialchars($labelNewBlank); ?></option>
                <option value="-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COT_PROVEEDOR_EXTERNO_OPTION'); ?></option>
                <?php
                $labelNoExpiry = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OFERTA_NO_EXPIRY');
                if (strpos($labelNoExpiry, 'COM_') === 0) {
                    $labelNoExpiry = 'Sin vencimiento';
                }
                foreach ($templates as $tpl) :
                    $parts = [$tpl->number ?? ''];
                    if (strlen((string) ($tpl->descripcion ?? '')) > 0) {
                        $parts[] = (string) $tpl->descripcion;
                    }
                    if (!empty($tpl->oferta_expires)) {
                        $parts[] = (new \DateTime($tpl->oferta_expires))->format('d/m/Y');
                    } else {
                        $parts[] = $labelNoExpiry;
                    }
                    $optLabel = implode(' — ', $parts);
                ?>
                <option value="<?php echo (int) $tpl->id; ?>"><?php echo htmlspecialchars($optLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NEW'); ?></button>
        </form>
    </div>

    <div class="card border-0 shadow-sm mb-3 precotizacion-list-filters-card">
        <div class="card-body">
            <h2 class="h6 text-muted text-uppercase mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTERS_TITLE'); ?></h2>
            <form method="get" action="<?php echo htmlspecialchars($filterFormAction); ?>" class="precotizacion-list-filter-form">
                <input type="hidden" name="option" value="com_ordenproduccion">
                <input type="hidden" name="view" value="cotizador">
                <input type="hidden" name="limit" value="<?php echo (int) $listLimit; ?>">
                <input type="hidden" name="limitstart" value="0">

                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="filter_number" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NUMBER'); ?></label>
                        <input type="text" name="filter_number" id="filter_number" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fv('number')); ?>" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_PLACEHOLDER_NUMBER')); ?>">
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="filter_descripcion" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION'); ?></label>
                        <input type="text" name="filter_descripcion" id="filter_descripcion" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fv('descripcion')); ?>" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_PLACEHOLDER_DESC')); ?>">
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="filter_client" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CLIENT'); ?></label>
                        <input type="text" name="filter_client" id="filter_client" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fv('client')); ?>" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_PLACEHOLDER_CLIENT')); ?>">
                    </div>

                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="filter_created_from" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_DATE_FROM'); ?></label>
                        <input type="date" name="filter_created_from" id="filter_created_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateIn('created_from')); ?>">
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="filter_created_to" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_DATE_TO'); ?></label>
                        <input type="date" name="filter_created_to" id="filter_created_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateIn('created_to')); ?>">
                    </div>

                    <?php if (!empty($this->showSalesAgentColumn)) : ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="filter_created_by" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SALES_AGENT'); ?></label>
                        <select name="filter_created_by" id="filter_created_by" class="form-select form-select-sm">
                            <option value="0"><?php echo htmlspecialchars(Text::_('JALL')); ?></option>
                            <?php foreach ($salesAgentOpts as $aid => $aname) : ?>
                            <option value="<?php echo (int) $aid; ?>"<?php echo $fInt('created_by') === (int) $aid ? ' selected' : ''; ?>><?php echo htmlspecialchars($aname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="filter_quotation" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ASSOCIATED_QUOTATION'); ?></label>
                        <input type="text" name="filter_quotation" id="filter_quotation" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fv('quotation')); ?>" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_PLACEHOLDER_QUOTATION')); ?>">
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="filter_has_cotizacion" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_HAS_QUOTATION'); ?></label>
                        <select name="filter_has_cotizacion" id="filter_has_cotizacion" class="form-select form-select-sm">
                            <option value=""<?php echo $fv('has_cotizacion') === '' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JALL')); ?></option>
                            <option value="1"<?php echo $fv('has_cotizacion') === '1' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_HAS_QUOTATION_WITH')); ?></option>
                            <option value="0"<?php echo $fv('has_cotizacion') === '0' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_HAS_QUOTATION_WITHOUT')); ?></option>
                        </select>
                    </div>

                    <?php if ($showOfertaColumn) : ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label for="filter_oferta" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OFERTA'); ?></label>
                        <select name="filter_oferta" id="filter_oferta" class="form-select form-select-sm">
                            <option value=""<?php echo $fv('oferta') === '' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JALL')); ?></option>
                            <option value="1"<?php echo $fv('oferta') === '1' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JYES')); ?></option>
                            <option value="0"<?php echo $fv('oferta') === '0' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JNO')); ?></option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($showFacturarColumn) : ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label for="filter_facturar" class="form-label small mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FACTURAR'); ?></label>
                        <select name="filter_facturar" id="filter_facturar" class="form-select form-select-sm">
                            <option value=""<?php echo $fv('facturar') === '' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JALL')); ?></option>
                            <option value="1"<?php echo $fv('facturar') === '1' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JYES')); ?></option>
                            <option value="0"<?php echo $fv('facturar') === '0' ? ' selected' : ''; ?>><?php echo htmlspecialchars(Text::_('JNO')); ?></option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-12 d-flex flex-wrap align-items-end gap-2 pt-1">
                        <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_APPLY'); ?></button>
                        <a href="<?php echo htmlspecialchars($filterClearUrl); ?>" class="btn btn-outline-secondary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FILTER_CLEAR'); ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive precotizacion-list-table-wrap">
        <table class="table table-striped table-hover precotizacion-list-table">
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NUMBER'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CREATED'); ?></th>
                    <?php if (!empty($this->showSalesAgentColumn)) : ?>
                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_SALES_AGENT'); ?></th>
                    <?php endif; ?>
                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ASSOCIATED_QUOTATION'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CLIENT'); ?></th>
                    <?php if ($showOfertaColumn) : ?>
                    <th scope="col" class="text-center"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OFERTA'); ?></th>
                    <?php endif; ?>
                    <?php if ($showFacturarColumn) : ?>
                    <th scope="col" class="text-center"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FACTURAR'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $associatedMap = $this->associatedQuotationNumbersByPreId ?? [];
                if (empty($items)) :
                ?>
                <tr>
                    <td colspan="<?php echo (int) $colCount; ?>" class="text-center text-muted py-4">
                        <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_ITEMS'); ?>
                    </td>
                </tr>
                <?php else :
                    foreach ($items as $item) :
                        $docUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . (int) $item->id);
                        $deleteAction = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.delete', false);
                        $created = $item->created ? (new \DateTime($item->created))->format('d/m/Y H:i') : '-';
                        $quotationNumbers = $associatedMap[(int) $item->id] ?? [];
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo htmlspecialchars($docUrl); ?>"><?php echo htmlspecialchars($item->number ?? ''); ?></a>
                        </td>
                        <td><?php echo htmlspecialchars($created); ?></td>
                        <?php if (!empty($this->showSalesAgentColumn)) : ?>
                        <td><?php echo htmlspecialchars($item->created_by_name ?? '—'); ?></td>
                        <?php endif; ?>
                        <td class="col-descripcion"><?php echo htmlspecialchars($item->descripcion ?? ''); ?></td>
                        <td>
                            <?php
                            if (empty($quotationNumbers)) {
                                echo '<span class="text-muted">—</span>';
                            } else {
                                $parts = [];
                                foreach ($quotationNumbers as $q) {
                                    $qid = is_array($q) ? (int) $q['id'] : 0;
                                    $qnum = is_array($q) ? ($q['quotation_number'] ?? '') : (string) $q;
                                    if ($qid) {
                                        $parts[] = '<a href="' . htmlspecialchars(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $qid)) . '">' . htmlspecialchars($qnum) . '</a>';
                                    } else {
                                        $parts[] = htmlspecialchars($qnum);
                                    }
                                }
                                echo implode(', ', $parts);
                            }
                            ?>
                        </td>
                        <td class="col-client">
                            <?php
                            $clientNames = [];
                            foreach ($quotationNumbers as $q) {
                                $name = is_array($q) ? trim((string) ($q['client_name'] ?? '')) : '';
                                if ($name !== '' && !in_array($name, $clientNames, true)) {
                                    $clientNames[] = $name;
                                }
                            }
                            echo $clientNames !== [] ? htmlspecialchars(implode(', ', $clientNames)) : '—';
                            ?>
                        </td>
                        <?php if ($showOfertaColumn) : ?>
                        <td class="text-center">
                            <?php
                            $ofertaOn = !empty($item->oferta);
                            echo $ofertaOn
                                ? '<span class="text-success">' . htmlspecialchars(Text::_('JYES')) . '</span>'
                                : '<span class="text-muted">' . htmlspecialchars(Text::_('JNO')) . '</span>';
                            if ($ofertaOn && isset($item->oferta_expires) && PrecotizacionModel::isOfertaExpired($item)) {
                                echo ' <span class="badge bg-secondary">' . htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_OFERTA_EXPIRED_BADGE')) . '</span>';
                            }
                            ?>
                        </td>
                        <?php endif; ?>
                        <?php if ($showFacturarColumn) : ?>
                        <td class="text-center">
                            <?php
                            $facturarOn = !empty($item->facturar);
                            echo $facturarOn
                                ? '<span class="text-success">' . htmlspecialchars(Text::_('JYES')) . '</span>'
                                : '<span class="text-muted">' . htmlspecialchars(Text::_('JNO')) . '</span>';
                            ?>
                        </td>
                        <?php endif; ?>
                        <td class="text-end">
                            <?php
                            $currentUid = (int) Factory::getUser()->id;
                            $createdBy = (int) ($item->created_by ?? 0);
                            $isOwnerRow = $createdBy === $currentUid;
                            $ofertaRow = !empty($item->oferta);
                            $canAdminRow = AccessHelper::isInAdministracionOrAdmonGroup() || Factory::getUser()->authorise('core.admin');
                            $mayDeleteRow = empty($quotationNumbers) && ($ofertaRow ? $isOwnerRow : ($isOwnerRow || $canAdminRow));
                            ?>
                            <?php if ($mayDeleteRow) : ?>
                            <form action="<?php echo htmlspecialchars($deleteAction); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE')); ?>');">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>">
                                    <span class="icon-trash" aria-hidden="true"></span>
                                </button>
                            </form>
                            <?php else : ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                    endforeach;
                endif;
                ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($items) && $pagination) : ?>
        <div class="com-ordenproduccion-pagination mt-3">
            <?php echo $pagination->getListFooter(); ?>
        </div>
    <?php endif; ?>
</div>
<style>
.precotizacion-list-filters-card .form-label { font-weight: 500; color: #495057; }
.precotizacion-list-table-wrap .precotizacion-list-table { font-size: 0.8rem; }
.precotizacion-list-table-wrap .precotizacion-list-table th,
.precotizacion-list-table-wrap .precotizacion-list-table td { padding: 0.3rem 0.4rem; vertical-align: middle; }
.precotizacion-list-table-wrap .precotizacion-list-table .col-descripcion { max-width: 260px; }
.precotizacion-list-table-wrap .precotizacion-list-table .col-client { max-width: 200px; }
.precotizacion-list-table-wrap .precotizacion-list-table .btn .icon-trash { font-size: 1rem; }
</style>
