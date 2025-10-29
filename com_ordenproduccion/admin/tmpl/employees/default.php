<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
?>

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=employees'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table table-striped" id="employeeList">
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_EMPLOYEE_ACTIVE', 'a.active', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_EMPLOYEE_PERSONNAME', 'a.personname', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_EMPLOYEE_CARDNO', 'a.cardno', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEE_GROUP'); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_EMPLOYEE_DEPARTMENT', 'a.department', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_EMPLOYEE_POSITION', 'a.position', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($item->active) : ?>
                                        <span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('JNO'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=employee.edit&id=' . (int) $item->id); ?>">
                                        <strong><?php echo $this->escape($item->personname); ?></strong>
                                    </a>
                                    <?php if (!empty($item->employee_number)) : ?>
                                        <br><small class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEE_NUMBER'); ?>: <?php echo $this->escape($item->employee_number); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark"><?php echo $this->escape($item->cardno); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($item->group_name)) : ?>
                                        <span class="badge" style="background-color: <?php echo $this->escape($item->group_color); ?>; color: white;">
                                            <?php echo $this->escape($item->group_name); ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo substr($item->work_start_time, 0, 5); ?> - <?php echo substr($item->work_end_time, 0, 5); ?></small>
                                    <?php else : ?>
                                        <span class="badge bg-warning"><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEE_NO_GROUP'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->department); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->position); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo (int) $item->id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<!-- Change Group Modal -->
<div id="changeGroupModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 5px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;"><?php echo Text::_('COM_ORDENPRODUCCION_CHANGE_GROUP_TITLE'); ?></h3>
            <button onclick="document.getElementById('changeGroupModal').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <form id="changeGroupForm" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=employees.changeGroup'); ?>" method="post">
            <div class="form-group mb-3">
                <label for="group_id" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_GROUP'); ?>:</label>
                <select name="group_id" id="group_id" class="form-select" required>
                    <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_GROUP_OPTION'); ?></option>
                    <?php foreach ($this->groups as $group) : ?>
                        <option value="<?php echo (int) $group->id; ?>">
                            <?php echo $this->escape($group->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('changeGroupModal').style.display='none'" class="btn btn-secondary">
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?php echo Text::_('COM_ORDENPRODUCCION_CHANGE_GROUP'); ?>
                </button>
            </div>
            <input type="hidden" name="boxchecked" value="0">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
</div>

<script>
// Copy selected checkboxes to modal form when submitting
document.getElementById('changeGroupForm').addEventListener('submit', function(e) {
    // Get all checked checkboxes from the main form
    var mainForm = document.getElementById('adminForm');
    var checkboxes = mainForm.querySelectorAll('input[name="cid[]"]:checked');
    
    if (checkboxes.length === 0) {
        e.preventDefault();
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_NO_ITEMS_SELECTED'); ?>');
        return false;
    }
    
    // Add the checked IDs to the modal form
    checkboxes.forEach(function(checkbox) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'cid[]';
        input.value = checkbox.value;
        this.appendChild(input);
    }.bind(this));
});
</script>

