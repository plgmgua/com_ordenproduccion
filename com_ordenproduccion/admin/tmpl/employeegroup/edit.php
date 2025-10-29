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

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=employeegroup&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-header">
                    <h4><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_BASIC_INFO'); ?></h4>
                </div>
                <div class="card-body">
                    <?php echo $this->form->renderField('name'); ?>
                    <?php echo $this->form->renderField('description'); ?>
                    <?php echo $this->form->renderField('color'); ?>
                    <?php echo $this->form->renderField('grace_period_minutes'); ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h4><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_DEFAULT_SCHEDULE'); ?></h4>
                    <small class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_DEFAULT_SCHEDULE_DESC'); ?></small>
                </div>
                <div class="card-body">
                    <?php echo $this->form->renderField('work_start_time'); ?>
                    <?php echo $this->form->renderField('work_end_time'); ?>
                    <?php echo $this->form->renderField('expected_hours'); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_WEEKLY_SCHEDULE'); ?></h4>
                    <small><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_WEEKLY_SCHEDULE_HELP'); ?></small>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="applyToAllDays()">
                            <i class="icon-copy"></i> <?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_APPLY_TO_ALL'); ?>
                        </button>
                    </div>

                    <?php echo $this->form->renderField('weekly_schedule'); ?>

                    <table class="table table-striped table-hover" id="weekly-schedule-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;"><?php echo Text::_('COM_ORDENPRODUCCION_DAY'); ?></th>
                                <th style="width: 10%;" class="text-center"><?php echo Text::_('COM_ORDENPRODUCCION_ENABLED'); ?></th>
                                <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_START_TIME'); ?></th>
                                <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_END_TIME'); ?></th>
                                <th style="width: 15%;"><?php echo Text::_('COM_ORDENPRODUCCION_EXPECTED_HOURS'); ?></th>
                                <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_NOTES'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="schedule-days">
                            <!-- Days will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h4><?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_PUBLISHING'); ?></h4>
                    <?php echo $this->form->renderField('state'); ?>
                    <?php echo $this->form->renderField('ordering'); ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const days = [
        {key: 'monday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_MONDAY'); ?>'},
        {key: 'tuesday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_TUESDAY'); ?>'},
        {key: 'wednesday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_WEDNESDAY'); ?>'},
        {key: 'thursday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_THURSDAY'); ?>'},
        {key: 'friday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_FRIDAY'); ?>'},
        {key: 'saturday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_SATURDAY'); ?>'},
        {key: 'sunday', label: '<?php echo Text::_('COM_ORDENPRODUCCION_SUNDAY'); ?>'}
    ];

    const defaultStartTime = document.getElementById('jform_work_start_time').value || '08:00:00';
    const defaultEndTime = document.getElementById('jform_work_end_time').value || '17:00:00';
    const defaultHours = document.getElementById('jform_expected_hours').value || '8.00';

    // Load existing schedule or create default
    let schedule = {};
    const scheduleField = document.getElementById('jform_weekly_schedule');
    
    try {
        if (scheduleField.value) {
            schedule = JSON.parse(scheduleField.value);
        }
    } catch(e) {
        console.error('Error parsing weekly schedule:', e);
    }

    // Initialize schedule for each day if not exists
    days.forEach(day => {
        if (!schedule[day.key]) {
            schedule[day.key] = {
                enabled: true,
                start_time: defaultStartTime,
                end_time: defaultEndTime,
                expected_hours: parseFloat(defaultHours),
                notes: ''
            };
        }
    });

    // Render the schedule table
    renderSchedule();

    function renderSchedule() {
        const tbody = document.getElementById('schedule-days');
        tbody.innerHTML = '';

        days.forEach(day => {
            const data = schedule[day.key];
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${day.label}</strong></td>
                <td class="text-center">
                    <input type="checkbox" 
                           class="form-check-input day-enabled" 
                           data-day="${day.key}" 
                           ${data.enabled ? 'checked' : ''}>
                </td>
                <td>
                    <input type="time" 
                           class="form-control form-control-sm day-start" 
                           data-day="${day.key}" 
                           value="${data.start_time ? data.start_time.substring(0,5) : '08:00'}"
                           ${!data.enabled ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="time" 
                           class="form-control form-control-sm day-end" 
                           data-day="${day.key}" 
                           value="${data.end_time ? data.end_time.substring(0,5) : '17:00'}"
                           ${!data.enabled ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="number" 
                           class="form-control form-control-sm day-hours" 
                           data-day="${day.key}" 
                           value="${data.expected_hours || 8.00}" 
                           step="0.25" 
                           min="0" 
                           max="24"
                           ${!data.enabled ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="text" 
                           class="form-control form-control-sm day-notes" 
                           data-day="${day.key}" 
                           value="${data.notes || ''}" 
                           placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_OPTIONAL'); ?>"
                           ${!data.enabled ? 'disabled' : ''}>
                </td>
            `;
            tbody.appendChild(row);
        });

        // Add event listeners
        document.querySelectorAll('.day-enabled').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const day = this.dataset.day;
                schedule[day].enabled = this.checked;
                
                // Enable/disable inputs for this day
                const row = this.closest('tr');
                row.querySelectorAll('input:not(.day-enabled)').forEach(input => {
                    input.disabled = !this.checked;
                });
                
                updateScheduleField();
            });
        });

        document.querySelectorAll('.day-start, .day-end, .day-hours, .day-notes').forEach(input => {
            input.addEventListener('change', function() {
                const day = this.dataset.day;
                const field = this.classList.contains('day-start') ? 'start_time' :
                             this.classList.contains('day-end') ? 'end_time' :
                             this.classList.contains('day-hours') ? 'expected_hours' :
                             'notes';
                
                let value = this.value;
                if (field === 'start_time' || field === 'end_time') {
                    value = value + ':00';
                } else if (field === 'expected_hours') {
                    value = parseFloat(value);
                }
                
                schedule[day][field] = value;
                updateScheduleField();
            });
        });
    }

    function updateScheduleField() {
        const scheduleField = document.getElementById('jform_weekly_schedule');
        scheduleField.value = JSON.stringify(schedule);
    }

    // Initial update
    updateScheduleField();
});

function applyToAllDays() {
    const startTime = document.getElementById('jform_work_start_time').value;
    const endTime = document.getElementById('jform_work_end_time').value;
    const expectedHours = document.getElementById('jform_expected_hours').value;

    if (!startTime || !endTime || !expectedHours) {
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUP_FILL_DEFAULT_FIRST'); ?>');
        return;
    }

    // Update all day inputs
    document.querySelectorAll('.day-start').forEach(input => {
        if (!input.disabled) {
            input.value = startTime.substring(0,5);
            input.dispatchEvent(new Event('change'));
        }
    });

    document.querySelectorAll('.day-end').forEach(input => {
        if (!input.disabled) {
            input.value = endTime.substring(0,5);
            input.dispatchEvent(new Event('change'));
        }
    });

    document.querySelectorAll('.day-hours').forEach(input => {
        if (!input.disabled) {
            input.value = expectedHours;
            input.dispatchEvent(new Event('change'));
        }
    });
}
</script>

<style>
#weekly-schedule-table input[type="time"],
#weekly-schedule-table input[type="number"],
#weekly-schedule-table input[type="text"] {
    font-size: 0.9rem;
}

#weekly-schedule-table tbody tr:hover {
    background-color: #f8f9fa;
}

#weekly-schedule-table input:disabled {
    background-color: #e9ecef;
    opacity: 0.6;
}

.card-header.bg-primary {
    background-color: #007bff !important;
}
</style>

