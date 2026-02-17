<?php
/**
 * Asistencia Análisis tab - on-time % by quincena, grouped by employee group
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

$quincenas = $this->quincenas ?? [];
$selectedQuincena = $this->selectedQuincena ?? '';
$analysisData = $this->analysisData ?? [];
$config = $this->asistenciaConfig ?? (object) ['on_time_threshold' => 90];

function safeEscapeAnalisis($v, $d = '') {
    return is_string($v) && $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $d;
}
?>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">Análisis de Puntualidad</h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=analisis'); ?>" class="row g-3 mb-4">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="asistencia" />
            <input type="hidden" name="tab" value="analisis" />
            <div class="col-md-6">
                <label for="quincena" class="form-label">Quincena</label>
                <select name="quincena" id="quincena" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($quincenas as $q): ?>
                    <option value="<?php echo safeEscapeAnalisis($q->value); ?>" <?php echo $q->value === $selectedQuincena ? 'selected' : ''; ?>>
                        <?php echo safeEscapeAnalisis($q->label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <p class="text-muted">Empleados con % de llegadas a tiempo >= <?php echo (int) $config->on_time_threshold; ?>% (configurable en Configuración)</p>

        <?php if (empty($analysisData)): ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NO_RECORDS'); ?></p>
        <?php else: ?>
        <?php foreach ($analysisData as $group): ?>
        <div class="mb-4">
            <h6 class="border-bottom pb-2" style="border-color: <?php echo safeEscapeAnalisis($group->group_color, '#6c757d'); ?> !important;">
                <span class="badge" style="background-color: <?php echo safeEscapeAnalisis($group->group_color, '#6c757d'); ?>; color: white; font-size: 0.9rem;">
                    <?php echo safeEscapeAnalisis($group->group_name); ?>
                </span>
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Empleado</th>
                            <th class="text-center">Días trabajados</th>
                            <th class="text-center">Llegadas a tiempo</th>
                            <th class="text-center">% Puntualidad</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group->employees as $emp): ?>
                        <tr class="<?php echo $emp->meets_threshold ? '' : 'table-warning'; ?>">
                            <td><strong><?php echo safeEscapeAnalisis($emp->personname); ?></strong></td>
                            <td class="text-center"><?php echo (int) $emp->total_days; ?></td>
                            <td class="text-center"><?php echo (int) $emp->on_time_days; ?></td>
                            <td class="text-center"><strong><?php echo number_format($emp->on_time_pct, 1); ?>%</strong></td>
                            <td class="text-center">
                                <?php if ($emp->meets_threshold): ?>
                                <span class="badge bg-success">Cumple</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Debajo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
