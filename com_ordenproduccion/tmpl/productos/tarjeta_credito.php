<?php
/**
 * Administración de Imprenta – Tarjeta de crédito (tasas por plazo).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
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
$baseOfertas = $baseUrl . '&section=ofertas';
$baseEnvios = $baseUrl . '&section=envios';
$baseAjustes = $baseUrl . '&section=ajustes&tab=cotizaciones';
$baseTarjeta = $baseUrl . '&section=tarjeta_credito';

$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};

$tableOk = !empty($this->tarjetaCreditoTableExists);
$rates = $this->tarjetaCreditoRates ?? [];
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
                <a class="nav-link" href="<?php echo Route::_($baseParametros); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_PARAMETROS', 'Parámetros'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseOfertas); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_OFERTAS', 'Ofertas'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseEnvios); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_ENVIOS', 'Envíos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseAjustes); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_TAB_AJUSTES', 'Ajustes'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_($baseTarjeta); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_TARJETA_CREDITO', 'Tarjeta de Crédito'); ?>
                </a>
            </li>
        </ul>

        <?php if (!$tableOk) : ?>
            <div class="alert alert-warning">
                <?php echo $l('COM_ORDENPRODUCCION_TARJETA_CREDITO_TABLE_MISSING', 'Tabla no instalada. Ejecute admin/sql/updates/mysql/3.101.0_tarjeta_credito.sql (reemplace #__ con su prefijo).'); ?>
            </div>
        <?php else : ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0"><?php echo $l('COM_ORDENPRODUCCION_TARJETA_CREDITO_TITLE', 'Comisión por cuotas'); ?></h2>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_TARJETA_CREDITO_INTRO', 'Defina la tasa (%) aplicada al total de la pre-cotización (incluye impuestos y comisiones) según el plazo en meses.'); ?></p>
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveTarjetaCredito'); ?>" method="post" class="mb-0">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo $l('COM_ORDENPRODUCCION_TARJETA_CREDITO_COL_PLAZO', 'Plazo (meses)'); ?></th>
                                        <th><?php echo $l('COM_ORDENPRODUCCION_TARJETA_CREDITO_COL_TASA', 'Tasa (%)'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rates as $row) :
                                        $c = (int) ($row->cuotas ?? 0);
                                        $t = isset($row->tasa_percent) ? (float) $row->tasa_percent : 0.0;
                                        if ($c < 1) {
                                            continue;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $c; ?></td>
                                            <td>
                                                <input type="number"
                                                       name="tasas[<?php echo $c; ?>]"
                                                       class="form-control form-control-sm"
                                                       min="0"
                                                       max="100"
                                                       step="0.01"
                                                       value="<?php echo htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); ?>"
                                                       style="max-width: 160px;" />
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
