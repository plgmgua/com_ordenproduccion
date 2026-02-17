<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 * Asistencia Configuración tab - work days, threshold, and Días Festivos subtab
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

$configSubtab = $this->configSubtab ?? 'general';
?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=configuracion&subtab=general'); ?>"
           class="nav-link <?php echo $configSubtab === 'general' ? 'active' : ''; ?>">
            <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_CONFIG_GENERAL', 'General', 'General'); ?>
        </a>
    </li>
    <li class="nav-item">
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=asistencia&tab=configuracion&subtab=festivos'); ?>"
           class="nav-link <?php echo $configSubtab === 'festivos' ? 'active' : ''; ?>">
            <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_HOLIDAYS_TITLE', 'Company Holidays', 'Días Festivos'); ?>
        </a>
    </li>
</ul>

<?php if ($configSubtab === 'festivos'): ?>
    <?php echo $this->loadTemplate('configuracion_festivos'); ?>
<?php else: ?>
<?php
$config = $this->asistenciaConfig ?? (object) ['work_days' => [1, 2, 3, 4, 5], 'on_time_threshold' => 90];
$workDays = (array) ($config->work_days ?? [1, 2, 3, 4, 5]);
$threshold = (int) ($config->on_time_threshold ?? 90);

$dayLabels = [
    0 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_SUNDAY', 'Sunday', 'Domingo'),
    1 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_MONDAY', 'Monday', 'Lunes'),
    2 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_TUESDAY', 'Tuesday', 'Martes'),
    3 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_WEDNESDAY', 'Wednesday', 'Miércoles'),
    4 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_THURSDAY', 'Thursday', 'Jueves'),
    5 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_FRIDAY', 'Friday', 'Viernes'),
    6 => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_SATURDAY', 'Saturday', 'Sábado'),
];
?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_CONFIG_TITLE', 'Configuración de Asistencia', 'Configuración de Asistencia'); ?></h5>
    </div>
    <div class="card-body">
        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=asistencia.saveConfig'); ?>" method="post" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>

            <fieldset class="mb-4">
                <legend><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_CONFIG_WORK_DAYS', 'Días de trabajo', 'Días de trabajo'); ?></legend>
                <p class="text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_CONFIG_WORK_DAYS_DESC', 'Seleccione los días de la semana que cuentan para el cálculo de asistencia.', 'Seleccione los días de la semana que cuentan para el cálculo de asistencia.'); ?></p>
                <div class="row">
                    <?php foreach ($dayLabels as $dow => $label): ?>
                    <div class="col-md-4 col-lg-3 mb-2">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="work_days[]" value="<?php echo $dow; ?>" <?php echo in_array($dow, $workDays, true) ? 'checked' : ''; ?> class="me-2">
                            <?php echo htmlspecialchars($label); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_CONFIG_THRESHOLD', 'Umbral de puntualidad', 'Umbral de puntualidad'); ?></legend>
                <p class="text-muted"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ASISTENCIA_CONFIG_THRESHOLD_DESC', 'Porcentaje mínimo de llegadas a tiempo para considerar que el empleado cumple (ej: 90 = 90%%).', 'Porcentaje mínimo de llegadas a tiempo para considerar que el empleado cumple (ej: 90 = 90%%).'); ?></p>
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <input type="number" name="on_time_threshold" id="on_time_threshold" value="<?php echo $threshold; ?>" min="0" max="100" class="form-control" style="width: 100px;">
                        <span class="ms-2">%</span>
                    </div>
                </div>
            </fieldset>

            <button type="submit" class="btn btn-primary">
                <span class="icon-save"></span> <?php echo Text::_('JSAVE'); ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
