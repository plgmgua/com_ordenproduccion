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

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=employeegroups'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table table-striped" id="employeegroupList">
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_ORDENPRODUCCION_EMPLOYEEGROUP_NAME', 'a.name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_SCHEDULE'); ?>
                                </th>
                                <th scope="col" class="w-10 text-center">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_EXPECTED_HOURS'); ?>
                                </th>
                                <th scope="col" class="w-10 text-center">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_EMPLOYEES'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ORDERING', 'a.ordering', $listDirn, $listOrder); ?>
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
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'employeegroups.', true); ?>
                                </td>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=employeegroup.edit&id=' . (int) $item->id); ?>">
                                        <span class="badge" style="background-color: <?php echo $this->escape($item->color); ?>; color: white;">
                                            <?php echo $this->escape($item->name); ?>
                                        </span>
                                    </a>
                                    <?php if (!empty($item->description)) : ?>
                                        <br><small class="text-muted"><?php echo $this->escape($item->description); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo substr($item->work_start_time, 0, 5); ?></strong> - 
                                    <strong><?php echo substr($item->work_end_time, 0, 5); ?></strong>
                                    <br><small class="text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_EMPLOYEEGROUP_GRACE_PERIOD_VALUE', $item->grace_period_minutes); ?></small>
                                </td>
                                <td class="text-center">
                                    <strong><?php echo number_format($item->expected_hours, 2); ?>h</strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo (int) $item->employee_count; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php echo $item->ordering; ?>
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

