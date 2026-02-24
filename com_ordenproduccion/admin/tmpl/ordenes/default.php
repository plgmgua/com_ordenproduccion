<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Administrator\View\Ordenes\HtmlView $this */

$app       = Factory::getApplication();
$user      = $app->getIdentity();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_ordenproduccion&task=ordenes.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="ordenesList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDENES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_HEADING_ORDER_NUMBER', 'a.orden_de_trabajo', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_HEADING_CLIENT', 'a.nombre_del_cliente', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-20">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_HEADING_WORK_DESCRIPTION'); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_HEADING_DELIVERY_DATE', 'a.fecha_de_entrega', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_HEADING_STATUS', 'a.status', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-8">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_HEADING_TYPE', 'a.type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_HEADING_CREATED', 'a.created', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-3 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody<?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                            <?php
                            $n = is_array($this->items) ? count($this->items) : 0;
                            if (is_array($this->items)) {
                                foreach ($this->items as $i => $item) :
                                $publishUp   = $item->publish_up ?? null;
                                $publishDown = $item->publish_down ?? null;
                                $checkedOut  = $item->checked_out ?? null;
                                $editor      = $item->editor ?? '';
                                $checkedOutTime = $item->checked_out_time ?? null;
                                $canEdit    = $user->authorise('core.edit', 'com_ordenproduccion.orden.' . $item->id);
                                $canCheckin = $user->authorise('core.manage', 'com_checkin') || $checkedOut == $userId || $checkedOut === null || $checkedOut === 0;
                                $canEditOwn = $user->authorise('core.edit.own', 'com_ordenproduccion.orden.' . $item->id) && ($item->created_by ?? 0) == $userId;
                                $canChange  = $user->authorise('core.edit.state', 'com_ordenproduccion.orden.' . $item->id) && $canCheckin;
                                ?>
                                <tr class="row<?php echo $i % 2; ?>" data-draggable-group="0" data-item-id="<?php echo $item->id; ?>" data-parents="" data-level="0">
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->orden_de_trabajo); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'ordenes.', $canChange, 'cb', $publishUp, $publishDown); ?>
                                    </td>
                                    <th scope="row" class="has-context">
                                        <div>
                                            <?php if ($canEdit || $canEditOwn) : ?>
                                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=orden.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->orden_de_trabajo)); ?>">
                                                    <?php echo $this->escape($item->orden_de_trabajo); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo $this->escape($item->orden_de_trabajo); ?>
                                            <?php endif; ?>
                                            <?php if ($checkedOut) : ?>
                                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $editor, $checkedOutTime, 'ordenes.', $canCheckin); ?>
                                            <?php endif; ?>
                                        </div>
                                    </th>
                                    <td>
                                        <?php echo $this->escape(isset($item->nombre_del_cliente) ? $item->nombre_del_cliente : (isset($item->client_name) ? $item->client_name : '')); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $description = $item->descripcion_de_trabajo ?? '';
                                        if (strlen($description) > 50) {
                                            echo $this->escape(substr($description, 0, 50)) . '...';
                                        } else {
                                            echo $this->escape($description);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $this->formatDate($item->fecha_de_entrega); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $this->getStatusColor($item->status); ?>">
                                            <?php echo $this->getStatusText($item->status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $this->getTypeColor($item->type); ?>">
                                            <?php echo $this->getTypeText($item->type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $this->formatDateTime($item->created); ?>
                                        </small>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo (int) $item->id; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php } ?>
                        </tbody>
                    </table>

                    <?php // load the pagination. ?>
                    <?php echo $this->pagination->getListFooter(); ?>

                <?php endif; ?>
                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<!-- Batch Status Modal -->
<div class="modal fade" id="batchStatusModal" tabindex="-1" aria-labelledby="batchStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchStatusModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_BATCH_STATUS'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body">
                <form id="batchStatusForm">
                    <div class="mb-3">
                        <label for="batch_status" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_STATUS'); ?></label>
                        <select class="form-select" id="batch_status" name="batch_status" required>
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_STATUS'); ?></option>
                            <option value="nueva"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_NUEVA'); ?></option>
                            <option value="en_proceso"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_EN_PROCESO'); ?></option>
                            <option value="terminada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_TERMINADA'); ?></option>
                            <option value="cerrada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_CERRADA'); ?></option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-primary" id="batchStatusSubmit"><?php echo Text::_('COM_ORDENPRODUCCION_UPDATE_STATUS'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Batch status functionality
    const batchButton = document.querySelector('[data-task="ordenes.batchStatus"]');
    const batchModal = new bootstrap.Modal(document.getElementById('batchStatusModal'));
    const batchForm = document.getElementById('batchStatusForm');
    const batchSubmit = document.getElementById('batchStatusSubmit');
    
    if (batchButton) {
        batchButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if any items are selected
            const checkedBoxes = document.querySelectorAll('input[name="cid[]"]:checked');
            if (checkedBoxes.length === 0) {
                alert('<?php echo Text::_('COM_ORDENPRODUCCION_NO_ITEMS_SELECTED'); ?>');
                return;
            }
            
            batchModal.show();
        });
    }
    
    if (batchSubmit) {
        batchSubmit.addEventListener('click', function() {
            const status = document.getElementById('batch_status').value;
            if (!status) {
                alert('<?php echo Text::_('COM_ORDENPRODUCCION_PLEASE_SELECT_STATUS'); ?>');
                return;
            }
            
            // Add status to form and submit
            const form = document.getElementById('adminForm');
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'batch_status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            form.task.value = 'ordenes.batchStatus';
            form.submit();
        });
    }
});
</script>
