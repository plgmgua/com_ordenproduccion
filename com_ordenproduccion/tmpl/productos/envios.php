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
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Productos\HtmlView $this */

$baseUrl = 'index.php?option=com_ordenproduccion&view=productos';
$basePliegos = $baseUrl . '&section=pliegos';
$baseElementos = $baseUrl . '&section=elementos';
$baseParametros = $baseUrl . '&section=parametros';
$baseEnvios = $baseUrl . '&section=envios';

$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};

$enviosTableExists = !empty($this->enviosTableExists);
$envios = $this->envios ?? [];
$envio = $this->envio ?? null;
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
                <a class="nav-link active" href="<?php echo Route::_($baseEnvios); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_ENVIOS', 'Envíos'); ?>
                </a>
            </li>
        </ul>

        <?php if (!$enviosTableExists) : ?>
            <div class="alert alert-warning">
                <?php echo $l('COM_ORDENPRODUCCION_ENVIOS_TABLE_MISSING', 'La tabla de envíos no está instalada. Ejecute el script 3.77.0_envios.sql'); ?>
            </div>
        <?php else : ?>
            <div class="card mb-3">
                <div class="card-header">
                    <?php echo $envio
                        ? $l('COM_ORDENPRODUCCION_ENVIO_EDIT', 'Editar envío')
                        : $l('COM_ORDENPRODUCCION_ENVIO_ADD', 'Añadir envío'); ?>
                </div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveEnvio'); ?>"
                          method="post" name="adminForm" id="adminFormEnvios" class="form-horizontal">
                        <input type="hidden" name="task" value="productos.saveEnvio" />
                        <input type="hidden" name="id" value="<?php echo $envio ? (int) $envio->id : 0; ?>" />
                        <?php echo HTMLHelper::_('form.token'); ?>

                        <div class="row mb-2">
                            <div class="col-md-4">
                                <label for="envio_name" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_ENVIO_NAME', 'Nombre'); ?> *</label>
                                <input type="text" name="name" id="envio_name" class="form-control" required maxlength="255"
                                       value="<?php echo $envio ? htmlspecialchars($envio->name ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" />
                            </div>
                            <div class="col-md-3">
                                <label for="envio_tipo" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_ENVIO_TIPO', 'Tipo'); ?></label>
                                <select name="tipo" id="envio_tipo" class="form-select">
                                    <option value="fixed" <?php echo ($envio && ($envio->tipo ?? '') === 'custom') ? '' : 'selected'; ?>>
                                        <?php echo $l('COM_ORDENPRODUCCION_ENVIO_TIPO_FIXED', 'Precio fijo'); ?>
                                    </option>
                                    <option value="custom" <?php echo ($envio && ($envio->tipo ?? '') === 'custom') ? 'selected' : ''; ?>>
                                        <?php echo $l('COM_ORDENPRODUCCION_ENVIO_TIPO_CUSTOM', 'Precio personalizado'); ?>
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3" id="envio_valor_wrap">
                                <label for="envio_valor" class="form-label"><?php echo $l('COM_ORDENPRODUCCION_ENVIO_VALOR', 'Precio'); ?></label>
                                <input type="number" name="valor" id="envio_valor" class="form-control" step="0.01" min="0"
                                       value="<?php echo ($envio && ($envio->tipo ?? '') !== 'custom' && isset($envio->valor)) ? (float) $envio->valor : ''; ?>"
                                       placeholder="0.00" />
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary"><?php echo $envio ? $l('JSAVE', 'Guardar') : $l('COM_ORDENPRODUCCION_ADD', 'Añadir'); ?></button>
                                <?php if ($envio) : ?>
                                    <a href="<?php echo Route::_($baseEnvios); ?>" class="btn btn-secondary ms-1"><?php echo $l('JCANCEL', 'Cancelar'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">
                            <?php echo $l('COM_ORDENPRODUCCION_ENVIO_TIPO_FIXED_DESC', 'Precio fijo: se usa el valor definido aquí.'); ?>
                            <?php echo $l('COM_ORDENPRODUCCION_ENVIO_TIPO_CUSTOM_DESC', 'Precio personalizado: el monto se solicita al añadir a la pre-cotización.'); ?>
                        </p>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_ENVIOS_LIST', 'Tipos de envío'); ?></div>
                <div class="card-body">
                    <?php if (empty($envios)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_ENVIOS', 'No hay tipos de envío. Añada uno arriba.'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $l('COM_ORDENPRODUCCION_ENVIO_NAME', 'Nombre'); ?></th>
                                    <th><?php echo $l('COM_ORDENPRODUCCION_ENVIO_TIPO', 'Tipo'); ?></th>
                                    <th><?php echo $l('COM_ORDENPRODUCCION_ENVIO_VALOR', 'Precio'); ?></th>
                                    <th class="text-end"><?php echo $l('COM_ORDENPRODUCCION_ACTIONS', 'Acciones'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($envios as $e) :
                                    $isCustom = isset($e->tipo) && $e->tipo === 'custom';
                                    $editUrl = Route::_($baseEnvios . '&edit_id=' . (int) $e->id);
                                    $deleteUrl = Route::_('index.php?option=com_ordenproduccion&task=productos.deleteEnvio&id=' . (int) $e->id . '&' . Session::getFormToken() . '=1');
                                ?>
                                    <tr>
                                        <td><?php echo (int) $e->id; ?></td>
                                        <td><?php echo htmlspecialchars($e->name ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php echo $isCustom
                                                ? $l('COM_ORDENPRODUCCION_ENVIO_TIPO_CUSTOM', 'Precio personalizado')
                                                : $l('COM_ORDENPRODUCCION_ENVIO_TIPO_FIXED', 'Precio fijo'); ?>
                                        </td>
                                        <td>
                                            <?php if ($isCustom) : ?>
                                                <em><?php echo $l('COM_ORDENPRODUCCION_ENVIO_AMOUNT_ON_USE', 'Se solicita al usar'); ?></em>
                                            <?php else : ?>
                                                Q <?php echo number_format((float) ($e->valor ?? 0), 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo $editUrl; ?>" class="btn btn-sm btn-outline-primary"><?php echo $l('JEDIT', 'Editar'); ?></a>
                                            <?php if (!$isCustom) : ?>
                                                <a href="<?php echo $deleteUrl; ?>" class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_ENVIO_CONFIRM_DELETE', '¿Eliminar este envío?')); ?>');">
                                                    <?php echo $l('JACTION_DELETE', 'Eliminar'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var sel = document.getElementById('envio_tipo');
                var wrap = document.getElementById('envio_valor_wrap');
                var inp = document.getElementById('envio_valor');
                function toggle() {
                    if (sel.value === 'custom') {
                        wrap.style.display = 'none';
                        inp.removeAttribute('required');
                        inp.value = '';
                    } else {
                        wrap.style.display = '';
                        inp.setAttribute('required', 'required');
                    }
                }
                if (sel) sel.addEventListener('change', toggle);
                toggle();
            });
            </script>
        <?php endif; ?>
    </div>
</div>
