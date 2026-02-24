<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Productos\HtmlView $this */

$baseUrl = 'index.php?option=com_ordenproduccion&view=productos';
$basePliegos = $baseUrl . '&section=pliegos';
$baseElementos = $baseUrl . '&section=elementos';
$baseParametros = $baseUrl . '&section=parametros';

$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};

$margen = isset($this->margenGanancia) ? (float) $this->margenGanancia : 0;
$iva = isset($this->iva) ? (float) $this->iva : 0;
$isr = isset($this->isr) ? (float) $this->isr : 0;
?>
<div class="com-ordenproduccion-productos">
    <div class="container-fluid">
        <h1 class="page-title"><?php echo $l('COM_ORDENPRODUCCION_ADMIN_IMPRENTA_TITLE', 'Administración de Imprenta'); ?></h1>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($basePliegos . '&tab=sizes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_PLIEGOS', 'Pliegos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseElementos); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_ELEMENTOS', 'Elementos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_($baseParametros); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_PARAMETROS', 'Parámetros'); ?>
                </a>
            </li>
        </ul>

        <div class="parametros-form-wrap">
            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&section=parametros'); ?>"
                  method="post" name="adminForm" id="adminForm" class="form-horizontal">
                <input type="hidden" name="task" value="productos.saveParametros" />
                <?php echo HTMLHelper::_('form.token'); ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="param_margen_ganancia" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA', 'Margen de Ganancia'); ?> (%)
                            </label>
                            <input type="number" name="margen_ganancia" id="param_margen_ganancia"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $margen, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_iva" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_IVA', 'IVA'); ?> (%)
                            </label>
                            <input type="number" name="iva" id="param_iva"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $iva, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_isr" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_ISR', 'ISR'); ?> (%)
                            </label>
                            <input type="number" name="isr" id="param_isr"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $isr, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <?php echo Text::_('JSAVE'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
