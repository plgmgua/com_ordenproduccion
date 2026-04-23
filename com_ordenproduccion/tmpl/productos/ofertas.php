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
$baseOfertas = $baseUrl . '&section=ofertas';
$baseEnvios = $baseUrl . '&section=envios';
$baseAjustes = $baseUrl . '&section=ajustes&tab=ajustes_cotizacion';
$baseTarjetaCredito = $baseUrl . '&section=tarjeta_credito';

$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};

$ofertasUserIds = $this->ofertasUserIds ?? [];
$usersList = $this->ofertasUsersList ?? [];
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
                <a class="nav-link active" href="<?php echo Route::_($baseOfertas); ?>">
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
                <a class="nav-link" href="<?php echo Route::_($baseTarjetaCredito); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_TARJETA_CREDITO', 'Tarjeta de Crédito'); ?>
                </a>
            </li>
        </ul>

        <div class="card">
            <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_OFERTAS_TITLE', 'Usuarios con permiso para plantillas'); ?></div>
            <div class="card-body">
                <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_OFERTAS_DESC', 'Seleccione los usuarios que podrán marcar una Pre-Cotización como plantilla (oferta). Esas plantillas estarán disponibles para todos los agentes al crear una nueva Pre-Cotización.'); ?></p>
                <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&section=ofertas'); ?>"
                      method="post" name="adminForm" id="adminForm">
                    <input type="hidden" name="task" value="productos.saveOfertas" />
                    <?php echo HTMLHelper::_('form.token'); ?>

                    <div class="mb-3">
                        <label for="ofertas_user_ids" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_OFERTAS_SELECT_USERS', 'Usuarios'); ?></label>
                        <select name="ofertas_user_ids[]" id="ofertas_user_ids" class="form-select" multiple="multiple" size="15">
                            <?php foreach ($usersList as $u) : ?>
                                <option value="<?php echo (int) $u->id; ?>"
                                    <?php echo in_array((int) $u->id, $ofertasUserIds, true) ? ' selected="selected"' : ''; ?>>
                                    <?php echo htmlspecialchars($u->name . ' (' . $u->username . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php echo $l('COM_ORDENPRODUCCION_OFERTAS_SELECT_HINT', 'Mantenga Ctrl (o Cmd) para seleccionar varios.'); ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
