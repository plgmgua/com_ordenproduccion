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
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=employee&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-header">
                    <h4><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEE_DETAILS'); ?></h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('personname'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('cardno'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('employee_number'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('group_id'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('department'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('position'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('email'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('phone'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('hire_date'); ?>
                        </div>
                    </div>
                    <?php echo $this->form->renderField('notes'); ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h4><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEE_PUBLISHING'); ?></h4>
                    <?php echo $this->form->renderField('active'); ?>
                    <?php echo $this->form->renderField('state'); ?>
                    <?php echo $this->form->renderField('id'); ?>
                </div>
            </div>
            
            <?php if ($this->item->id) : ?>
            <div class="card mt-3">
                <div class="card-body">
                    <h4><?php echo Text::_('JGLOBAL_FIELD_CREATED_LABEL'); ?></h4>
                    <?php echo $this->form->renderField('created'); ?>
                    <?php echo $this->form->renderField('created_by'); ?>
                    <?php if ($this->item->modified) : ?>
                        <?php echo $this->form->renderField('modified'); ?>
                        <?php echo $this->form->renderField('modified_by'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

