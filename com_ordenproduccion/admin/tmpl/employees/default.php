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

