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
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.sync'); ?>" 
           class="btn btn-primary" 
           onclick="return confirm('<?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC_CONFIRM'); ?>');">
            <span class="icon-refresh"></span> <?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SYNC'); ?>
        </a>
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
</script>


