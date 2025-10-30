<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<div class="com-ordenproduccion-timesheets">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_TITLE'); ?></h1>

    <div class="alert alert-info">
        <?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_DESC'); ?>
    </div>

    <form action="<?php echo JRoute::_('index.php'); ?>" method="get" class="card mb-3">
        <input type="hidden" name="option" value="com_ordenproduccion">
        <input type="hidden" name="view" value="timesheets">
        <div class="card-header">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_FILTERS'); ?></strong>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_WEEK_START'); ?></label>
                    <input type="date" name="week_start" value="<?php echo htmlspecialchars($this->state->get('filter.week_start') ?: date('Y-m-d', strtotime('monday this week')), ENT_QUOTES, 'UTF-8'); ?>" class="form-control" />
                </div>
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text(_('COM_ORDENPRODUCCION_TIMESHEETS_GROUP')); ?></label>
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

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_WEEKLY_SUMMARY'); ?></strong>
            <div>
                <button class="btn btn-success btn-sm" disabled><?php echo Text::_('COM_ORDENPRODUCCION_APPROVE_SELECTED'); ?></button>
                <button class="btn btn-danger btn-sm" disabled><?php echo Text::_('COM_ORDENPRODUCCION_REJECT_SELECTED'); ?></button>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo JRoute::_('index.php?option=com_ordenproduccion&task=timesheets.bulkApprove'); ?>" method="post" id="bulkApproveForm">
            <input type="hidden" name="week_start" value="<?php echo htmlspecialchars($this->state->get('filter.week_start') ?: date('Y-m-d', strtotime('monday this week')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo JHtml::_('form.token'); ?>
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
                            <th style="width:140px;">Semana</th>
                            <th style="width:120px;">Horas (semana)</th>
                            <th style="width:120px;">Aprobadas</th>
                            <th style="width:120px;">Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->items as $row) : ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-check" name="selected[]" value="<?php echo htmlspecialchars($row->cardno, ENT_QUOTES, 'UTF-8'); ?>"
                                       <?php echo ($row->week_approval_status === 'approved') ? 'checked' : ''; ?>>
                            </td>
                            <td><?php echo htmlspecialchars($row->employee_name, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($row->group_color ?: '#6c757d', ENT_QUOTES, 'UTF-8'); ?>; color: #fff;">
                                    <?php echo htmlspecialchars($row->group_name ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(date('d/m/y', strtotime($row->week_start)) . ' - ' . date('d/m/y', strtotime($row->week_end)), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td><strong><?php echo number_format((float)($row->week_total_hours ?? 0), 2); ?></strong></td>
                            <td><?php echo number_format((float)($row->week_approved_hours ?? 0), 2); ?></td>
                            <td>
                                <?php if ($row->week_approval_status === 'approved') : ?>
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


