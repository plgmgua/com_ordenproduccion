<?php
/**
 * Pre-Cotización list (current user). "Nueva Pre-Cotización" opens modal to choose template or create blank.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Cotizador\HtmlView $this */

$items      = $this->items ?? [];
$pagination = $this->pagination ?? null;
$templates  = $this->templates ?? [];
$createUrl  = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.create&' . Session::getFormToken() . '=1', false);
$addFromTemplateUrl = Route::_('index.php?option=com_ordenproduccion&task=precotizacion.addFromTemplate', false);
$labelNewBlank = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NEW_BLANK');
if (strpos($labelNewBlank, 'COM_ORDENPRODUCCION_') === 0) {
    $labelNewBlank = 'Crear en blanco';
}
$labelChooseTemplate = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CHOOSE_TEMPLATE');
if (strpos($labelChooseTemplate, 'COM_ORDENPRODUCCION_') === 0) {
    $labelChooseTemplate = 'Crear desde plantilla';
}
?>

<div class="com-ordenproduccion-precotizacion-list container py-4">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_TITLE'); ?></h1>

    <p class="lead"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_DESC'); ?></p>

    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPrecotizacionModal">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NEW'); ?>
        </button>
    </div>

    <!-- Modal: Nueva Pre-Cotización - choose template or blank -->
    <div class="modal fade" id="newPrecotizacionModal" tabindex="-1" aria-labelledby="newPrecotizacionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newPrecotizacionModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NEW'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted"><?php echo htmlspecialchars($labelChooseTemplate); ?></p>
                    <?php if (!empty($templates)) : ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($templates as $tpl) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($tpl->number . (\strlen((string) ($tpl->descripcion ?? '')) ? ' — ' . $tpl->descripcion : '')); ?></span>
                            <form action="<?php echo htmlspecialchars($addFromTemplateUrl); ?>" method="post" class="d-inline">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="template_id" value="<?php echo (int) $tpl->id; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo Text::_('COM_ORDENPRODUCCION_USE_TEMPLATE'); ?></button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <form action="<?php echo htmlspecialchars($addFromTemplateUrl); ?>" method="post">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="template_id" value="0">
                        <button type="submit" class="btn btn-secondary"><?php echo htmlspecialchars($labelNewBlank); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($items)) : ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_NO_ITEMS'); ?>
        </div>
    <?php else : ?>
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
                        <th scope="col" class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $associatedMap = $this->associatedQuotationNumbersByPreId ?? [];
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
                            <td class="text-end">
                                <a href="<?php echo htmlspecialchars($docUrl); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_VIEW')); ?>">
                                    <span class="icon-eye" aria-hidden="true"></span>
                                </a>
                                <?php if (empty($quotationNumbers)) : ?>
                                <form action="<?php echo htmlspecialchars($deleteAction); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CONFIRM_DELETE')); ?>');">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $item->id; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo htmlspecialchars(Text::_('JACTION_DELETE')); ?>">
                                        <span class="icon-trash" aria-hidden="true"></span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination && $pagination->total > $pagination->limit) : ?>
            <div class="com-ordenproduccion-pagination">
                <?php echo $pagination->getListFooter(); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<style>
.precotizacion-list-table-wrap .precotizacion-list-table { font-size: 0.8rem; }
.precotizacion-list-table-wrap .precotizacion-list-table th,
.precotizacion-list-table-wrap .precotizacion-list-table td { padding: 0.3rem 0.4rem; vertical-align: middle; }
.precotizacion-list-table-wrap .precotizacion-list-table .col-descripcion { max-width: 260px; }
.precotizacion-list-table-wrap .precotizacion-list-table .col-client { max-width: 200px; }
.precotizacion-list-table-wrap .precotizacion-list-table .btn .icon-eye,
.precotizacion-list-table-wrap .precotizacion-list-table .btn .icon-trash { font-size: 1rem; }
</style>
