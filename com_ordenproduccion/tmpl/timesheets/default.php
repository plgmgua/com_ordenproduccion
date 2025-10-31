<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;

?>
<div class="com-ordenproduccion-timesheets">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_TITLE'); ?></h1>

    <div class="alert alert-info">
        <?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_DESC'); ?>
    </div>

    <form action="<?php echo Route::_('index.php'); ?>" method="get" class="card mb-3">
        <input type="hidden" name="option" value="com_ordenproduccion">
        <input type="hidden" name="view" value="timesheets">
        <div class="card-header">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_FILTERS'); ?></strong>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_DATE'); ?></label>
                    <input type="date" name="work_date" value="<?php echo htmlspecialchars($this->state->get('filter.work_date') ?: date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" class="form-control" />
                </div>
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_GROUP'); ?></label>
                    <select name="filter_group_id" class="form-select">
                        <option value="0">—</option>
                        <?php foreach ($this->groups as $g): ?>
                            <option value="<?php echo (int)$g->id; ?>" <?php echo ((int)$this->state->get('filter.group_id') === (int)$g->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g->name, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                </div>
            </div>
        </div>
    </form>

    <!-- Action Buttons -->
    <div class="mb-3">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('manualEntrySection').style.display = document.getElementById('manualEntrySection').style.display === 'none' ? 'block' : 'none';">
            <span class="icon-plus"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NEW_ENTRY'); ?>
        </button>
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.sync'); ?>" 
           class="btn btn-secondary" 
           onclick="return confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_CONFIRM'); ?>');">
            <span class="icon-refresh"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC'); ?>
        </a>
    </div>

    <!-- Manual Entry Form -->
    <div id="manualEntrySection" class="card mb-3" style="display: none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_MANUAL_ENTRY'); ?></strong>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addManualEntryRow()">
                <span class="icon-plus"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ADD_ROW'); ?>
            </button>
        </div>
        <div class="card-body">
            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=timesheets.bulkManualEntry'); ?>" method="post" id="manualEntryForm">
                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="work_date" value="<?php echo htmlspecialchars($this->state->get('filter.work_date') ?: date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="manualEntryTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:120px;"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_PERSONNAME'); ?></th>
                                <th style="width:90px;"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DATE'); ?></th>
                                <th style="width:90px;"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TIME'); ?></th>
                                <th style="width:100px;"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DIRECTION'); ?></th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="manualEntryRows">
                            <!-- Rows will be added by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="document.getElementById('manualEntrySection').style.display='none'; clearManualEntryRows();">
                        <?php echo Text::_('JCANCEL'); ?>
                    </button>
                    <button type="submit" class="btn btn-success">
                        <span class="icon-save"></span> <?php echo Text::_('COM_ORDENPRODUCCION_SAVE_ENTRIES'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_DAILY_SUMMARY'); ?></strong>
        </div>
        <div class="card-body">
            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=timesheets.bulkApprove'); ?>" method="post" id="bulkApproveForm">
            <input type="hidden" name="work_date" value="<?php echo htmlspecialchars($this->state->get('filter.work_date') ?: date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <div class="mb-2 d-flex justify-content-end">
                <button type="submit" class="btn btn-success" id="btnBulkApprove" disabled>
                    <?php echo Text::_('COM_ORDENPRODUCCION_APPROVE_SELECTED'); ?>
                </button>
            </div>
            <?php if (!empty($this->items)) : ?>
            <div class="table-responsive">
                <table class="table table-bordered table-sm" style="white-space: nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:28px;"><input type="checkbox" id="chkAll"></th>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_EMPLOYEE'); ?></th>
                            <th style="width:120px;">Grupo</th>
                            <th style="width:90px;">Fecha</th>
                            <th style="width:80px;">Entrada</th>
                            <th style="width:80px;">Salida</th>
                            <th style="width:100px;">Horas</th>
                            <th style="width:100px;">Aprobadas</th>
                            <th style="width:100px;">Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->items as $row) : ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-check" name="selected[]" value="<?php echo (int)$row->id; ?>"
                                       <?php echo (($row->approval_status ?? 'pending') === 'approved') ? 'checked' : ''; ?>>
                            </td>
                            <td><?php echo htmlspecialchars($row->employee_name, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($row->group_color ?: '#6c757d', ENT_QUOTES, 'UTF-8'); ?>; color: #fff;">
                                    <?php echo htmlspecialchars($row->group_name ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('d/m/y', strtotime($row->work_date)), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $row->first_entry ? substr($row->first_entry, 0, 5) : '-'; ?></td>
                            <td><?php echo $row->last_exit ? substr($row->last_exit, 0, 5) : '-'; ?></td>
                            <td><strong><?php echo number_format((float)($row->total_hours ?? 0), 2); ?>h</strong></td>
                            <td><?php echo number_format((float)($row->approved_hours ?? 0), 2); ?>h</td>
                            <td>
                                <?php if (($row->approval_status ?? 'pending') === 'approved') : ?>
                                    <span class="badge bg-success">Aprobado</span>
                                <?php else : ?>
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
            <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_PLACEHOLDER'); ?></p>
            <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
  const chkAll = document.getElementById('chkAll');
  const checks = document.querySelectorAll('.row-check');
  const btn = document.getElementById('btnBulkApprove');
  function updateBtn(){
    let any = false; checks.forEach(c=>{ if(c.checked) any = true; });
    btn.disabled = !any;
  }
  if (chkAll){
    chkAll.addEventListener('change', ()=>{ checks.forEach(c=>{ c.checked = chkAll.checked; }); updateBtn(); });
  }
  checks.forEach(c=> c.addEventListener('change', updateBtn));
  updateBtn();
})();

// Manual Entry Form JavaScript
const employees = <?php echo json_encode(array_map(function($e) { return ['cardno' => $e->cardno, 'personname' => $e->personname]; }, $this->employees)); ?>;
const workDate = '<?php echo htmlspecialchars($this->state->get('filter.work_date') ?: date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>';
let rowCounter = 0;

function addManualEntryRow() {
    const tbody = document.getElementById('manualEntryRows');
    const row = document.createElement('tr');
    row.id = 'manual_row_' + rowCounter;
    
    // Build employee select options
    let empOptions = '<option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SELECT_EMPLOYEE'); ?></option>';
    employees.forEach(function(emp) {
        empOptions += '<option value="' + escapeHtml(emp.personname) + '" data-cardno="' + escapeHtml(emp.cardno) + '">' + escapeHtml(emp.personname) + '</option>';
    });
    
    // Build row HTML
    row.innerHTML = 
        '<td>' +
            '<select name="entries[' + rowCounter + '][personname]" class="form-select form-select-sm required" required>' + empOptions + '</select>' +
            '<input type="hidden" name="entries[' + rowCounter + '][cardno]" id="cardno_' + rowCounter + '">' +
        '</td>' +
        '<td><input type="date" name="entries[' + rowCounter + '][authdate]" class="form-control form-control-sm required" value="' + workDate + '" required></td>' +
        '<td><input type="time" name="entries[' + rowCounter + '][authtime]" class="form-control form-control-sm required" value="08:00" required></td>' +
        '<td><select name="entries[' + rowCounter + '][direction]" class="form-select form-select-sm"><option value="Puerta">Puerta</option><option value="Entrada">Entrada</option><option value="Salida">Salida</option></select></td>' +
        '<td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'tr\').remove();"><span class="icon-delete"></span></button></td>';
    
    tbody.appendChild(row);
    
    // Attach event listener for cardno auto-fill
    const select = row.querySelector('select[name*="[personname]"]');
    const cardnoInput = row.querySelector('input[name*="[cardno]"]');
    if (select && cardnoInput) {
        select.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected && selected.hasAttribute('data-cardno')) {
                cardnoInput.value = selected.getAttribute('data-cardno');
            } else {
                cardnoInput.value = '';
            }
        });
    }
    
    rowCounter++;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function clearManualEntryRows() {
    document.getElementById('manualEntryRows').innerHTML = '';
    rowCounter = 0;
}

// Add first row when form is shown
document.addEventListener('DOMContentLoaded', function() {
    const manualBtn = document.querySelector('button[onclick*="manualEntrySection"]');
    if (manualBtn) {
        manualBtn.addEventListener('click', function() {
            setTimeout(function() {
                const tbody = document.getElementById('manualEntryRows');
                if (tbody && tbody.children.length === 0) {
                    addManualEntryRow();
                }
            }, 100);
        });
    }
});
</script>


