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

    <div class="card mb-3">
        <div class="card-header">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_FILTERS'); ?></strong>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_WEEK_START'); ?></label>
                    <input type="date" class="form-control" />
                </div>
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_GROUP'); ?></label>
                    <select class="form-select"><option>—</option></select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_EMPLOYEE'); ?></label>
                    <input type="text" class="form-control" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_SEARCH'); ?>" />
                </div>
                <div class="col-sm-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_WEEKLY_SUMMARY'); ?></strong>
            <div>
                <button class="btn btn-success btn-sm" disabled><?php echo Text::_('COM_ORDENPRODUCCION_APPROVE_SELECTED'); ?></button>
                <button class="btn btn-danger btn-sm" disabled><?php echo Text::_('COM_ORDENPRODUCCION_REJECT_SELECTED'); ?></button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($this->items)) : ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_EMPLOYEE'); ?></th>
                            <th style="width:120px;">Grupo</th>
                            <th style="width:140px;">Semana</th>
                            <th style="width:120px;">Horas (semana)</th>
                            <th style="width:120px;">Aprobadas</th>
                            <th style="width:120px;">Estatus</th>
                            <th style="width:160px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->items as $row) : ?>
                        <tr>
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
                            <td>
                                <?php
                                $weekStartParam = htmlspecialchars($row->week_start, ENT_QUOTES, 'UTF-8');
                                $cardParam = htmlspecialchars($row->cardno, ENT_QUOTES, 'UTF-8');
                                $approveUrl = JRoute::_('index.php?option=com_ordenproduccion&task=timesheets.approve&cardno=' . $cardParam . '&week_start=' . $weekStartParam . '&' . JSession::getFormToken() . '=1');
                                $rejectUrl = JRoute::_('index.php?option=com_ordenproduccion&task=timesheets.reject&cardno=' . $cardParam . '&week_start=' . $weekStartParam . '&' . JSession::getFormToken() . '=1');
                                ?>
                                <a href="<?php echo $approveUrl; ?>" class="btn btn-success btn-sm" <?php echo ($row->week_approval_status === 'approved') ? 'disabled' : ''; ?>>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_APPROVE_SELECTED'); ?>
                                </a>
                                <a href="<?php echo $rejectUrl; ?>" class="btn btn-danger btn-sm" <?php echo ($row->week_approval_status !== 'approved') ? 'disabled' : ''; ?>>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_REJECT_SELECTED'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
            <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_PLACEHOLDER'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>


