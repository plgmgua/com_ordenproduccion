<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// Safe helper function
function safeEscape($value, $default = '') {
    if (is_string($value) && !empty($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$isNew = empty($this->item->id);
?>

<div class="com-ordenproduccion-asistenciaentry-edit">
    <div class="page-header">
        <h1>
            <?php echo $isNew ? Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NEW_ENTRY') : Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EDIT_ENTRY'); ?>
        </h1>
    </div>

    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistenciaentry&layout=edit&id=' . (int) $this->item->id); ?>" 
          method="post" name="adminForm" id="adminForm" class="form-validate">

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_ENTRY_DETAILS'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Employee Selection -->
                    <div class="col-md-12">
                        <label for="jform_cardno" class="form-label">
                            <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EMPLOYEE'); ?> *
                        </label>
                        <select name="jform[cardno]" id="jform_cardno" class="form-select required" required onchange="updatePersonName()">
                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SELECT_EMPLOYEE'); ?></option>
                            <?php foreach ($this->employees as $employee): ?>
                                <option value="<?php echo safeEscape($employee->cardno); ?>" 
                                        data-name="<?php echo safeEscape($employee->personname); ?>"
                                        <?php echo (isset($this->item->cardno) && $this->item->cardno == $employee->cardno) ? 'selected' : ''; ?>>
                                    <?php echo safeEscape($employee->personname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Hidden field for person name (auto-filled from dropdown) -->
                        <input type="hidden" name="jform[personname]" id="jform_personname" 
                               value="<?php echo safeEscape($this->item->personname ?? ''); ?>">
                    </div>

                    <!-- Date -->
                    <div class="col-md-6">
                        <label for="jform_authdate" class="form-label">
                            <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DATE'); ?> *
                        </label>
                        <input type="date" name="jform[authdate]" id="jform_authdate" 
                               class="form-control required" 
                               value="<?php echo safeEscape($this->item->authdate ?? date('Y-m-d')); ?>" 
                               required>
                    </div>

                    <!-- Time -->
                    <div class="col-md-6">
                        <label for="jform_authtime" class="form-label">
                            <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TIME'); ?> *
                        </label>
                        <input type="time" name="jform[authtime]" id="jform_authtime" 
                               class="form-control required" 
                               value="<?php echo safeEscape($this->item->authtime ?? date('H:i:s')); ?>" 
                               required>
                    </div>

                    <!-- Notes -->
                    <div class="col-12">
                        <label for="jform_notes" class="form-label">
                            <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NOTES'); ?>
                        </label>
                        <textarea name="jform[notes]" id="jform_notes" 
                                  class="form-control" 
                                  rows="3"
                                  placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NOTES_PLACEHOLDER'); ?>"><?php echo safeEscape($this->item->notes ?? ''); ?></textarea>
                    </div>

                    <!-- Hidden fields for automatic values -->
                    <input type="hidden" name="jform[direction]" value="Puerta">
                    <input type="hidden" name="jform[devicename]" value="Manual Entry">
                    <input type="hidden" name="jform[deviceserialno]" value="">
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-3">
            <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('asistenciaentry.save')">
                <span class="icon-save"></span> <?php echo Text::_('JSAVE'); ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('asistenciaentry.cancel')">
                <span class="icon-cancel"></span> <?php echo Text::_('JCANCEL'); ?>
            </button>
            <?php if (!$isNew): ?>
            <button type="button" class="btn btn-danger" onclick="deleteEntry()">
                <span class="icon-delete"></span> <?php echo Text::_('JACTION_DELETE'); ?>
            </button>
            <?php endif; ?>
        </div>

        <input type="hidden" name="task" value="" />
        <input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>" />
        <input type="hidden" name="jform[id]" value="<?php echo (int) $this->item->id; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<script>
Joomla = window.Joomla || {};

Joomla.submitbutton = function(task) {
    if (task == 'asistenciaentry.cancel') {
        Joomla.submitform(task, document.getElementById('adminForm'));
    } else {
        const form = document.getElementById('adminForm');
        if (document.formvalidator.isValid(form)) {
            Joomla.submitform(task, form);
        } else {
            alert('<?php echo Text::_('JGLOBAL_VALIDATION_FORM_FAILED'); ?>');
        }
    }
}

function updatePersonName() {
    const select = document.getElementById('jform_cardno');
    const nameInput = document.getElementById('jform_personname');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.dataset.name) {
        nameInput.value = selectedOption.dataset.name;
    }
}

function deleteEntry() {
    if (confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DELETE_CONFIRM'); ?>')) {
        window.location.href = '<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistenciaentry.delete&id=' . (int) $this->item->id . '&' . Session::getFormToken() . '=1', false); ?>';
    }
}
</script>

