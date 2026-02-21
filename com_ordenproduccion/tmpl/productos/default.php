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

$section = $this->section ?? 'pliegos';
$activeTab = $this->activeTab ?? 'sizes';
$baseUrl = 'index.php?option=com_ordenproduccion&view=productos';
$basePliegos = $baseUrl . '&section=pliegos';
$baseElementos = $baseUrl . '&section=elementos';

// Fallback to human-friendly labels when language file is not loaded (e.g. after deploy)
$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};
?>
<div class="com-ordenproduccion-productos">
    <div class="container-fluid">
        <h1 class="page-title"><?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TITLE', 'Productos'); ?></h1>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link <?php echo $section === 'pliegos' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=sizes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_PLIEGOS', 'Pliegos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $section === 'elementos' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseElementos); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_ELEMENTOS', 'Elementos'); ?>
                </a>
            </li>
        </ul>

        <?php if ($section === 'elementos') : ?>
            <?php if (empty($this->elementosTableExists)) : ?>
                <div class="alert alert-warning">
                    <?php echo $l('COM_ORDENPRODUCCION_ELEMENTOS_TABLE_MISSING', 'La tabla de elementos no está instalada. Ejecute el script 3.71.0_elementos.sql'); ?>
                </div>
            <?php else :
                $elementos = $this->elementos ?? [];
                $elemento = $this->elemento ?? null;
            ?>
            <div class="card mb-3">
                <div class="card-header"><?php echo $elemento ? $l('COM_ORDENPRODUCCION_ELEMENTO_EDIT', 'Editar elemento') : $l('COM_ORDENPRODUCCION_ELEMENTO_ADD', 'Añadir elemento'); ?></div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveElemento'); ?>" method="post" class="form-inline flex-wrap gap-2 align-items-end">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="<?php echo $elemento ? (int) $elemento->id : 0; ?>">
                        <div class="form-group mb-2 me-2">
                            <label for="elemento_name" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_NAME', 'Nombre'); ?></label>
                            <input type="text" name="name" id="elemento_name" class="form-control" required maxlength="255" value="<?php echo $elemento ? htmlspecialchars($elemento->name ?? '') : ''; ?>">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="elemento_size" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_SIZE', 'Tamaño'); ?></label>
                            <input type="text" name="size" id="elemento_size" class="form-control" maxlength="100" value="<?php echo $elemento ? htmlspecialchars($elemento->size ?? '') : ''; ?>" placeholder="ej. 60x90">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="elemento_price" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_PRICE_RANGE_1', 'Precio rango 1'); ?></label>
                            <input type="number" name="price" id="elemento_price" class="form-control" step="0.01" min="0" value="<?php echo $elemento ? (float) ($elemento->price_1_to_1000 ?? $elemento->price ?? 0) : ''; ?>" placeholder="0.00" title="<?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_PRICE_PER_UNIT', 'Precio por unidad (rango 1)'); ?>">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="elemento_range_1_ceiling" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_RANGE_1_CEILING', 'Rango 1 hasta (unidades)'); ?></label>
                            <input type="number" name="range_1_ceiling" id="elemento_range_1_ceiling" class="form-control" min="1" value="<?php echo $elemento ? (int) ($elemento->range_1_ceiling ?? 1000) : 1000; ?>" title="<?php echo $l('COM_ORDENPRODUCCION_RANGE_1_CEILING_DESC', 'Límite superior del primer rango'); ?>">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="elemento_price_1001" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_PRICE_RANGE_2', 'Precio rango 2'); ?></label>
                            <input type="number" name="price_1001_plus" id="elemento_price_1001" class="form-control" step="0.01" min="0" value="<?php echo $elemento ? (float) ($elemento->price_1001_plus ?? 0) : ''; ?>" placeholder="0.00" title="<?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_PRICE_PER_UNIT_2', 'Precio por unidad (rango 2)'); ?>">
                        </div>
                        <div class="form-group mb-2">
                            <button type="submit" class="btn btn-primary"><?php echo $elemento ? $l('JSAVE', 'Guardar') : $l('COM_ORDENPRODUCCION_ADD', 'Añadir'); ?></button>
                            <?php if ($elemento) : ?>
                                <a href="<?php echo Route::_($baseElementos); ?>" class="btn btn-secondary"><?php echo $l('JCANCEL', 'Cancelar'); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTOS_LIST', 'Elementos'); ?></div>
                <div class="card-body">
                    <?php if (empty($elementos)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_ELEMENTOS', 'No hay elementos. Añada uno arriba.'); ?></p>
                    <?php else : ?>
                        <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTOS_RANGE_NOTE', 'Cada elemento puede definir dos rangos por cantidad: Rango 1 hasta (unidades) y precios por unidad para cada rango.'); ?></p>
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_NAME', 'Nombre'); ?></th>
                                    <th><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_SIZE', 'Tamaño'); ?></th>
                                    <th class="bg-light"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_RANGE_1_CEILING', 'Rango 1 hasta'); ?></th>
                                    <th class="bg-light"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_PRICE_RANGE_1', 'Precio rango 1'); ?> (Q)</th>
                                    <th class="bg-light"><?php echo $l('COM_ORDENPRODUCCION_ELEMENTO_PRICE_RANGE_2', 'Precio rango 2'); ?> (Q)</th>
                                    <th class="text-end"><?php echo $l('COM_ORDENPRODUCCION_ACTIONS', 'Acciones'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($elementos as $el) :
                                    $deleteUrl = Route::_('index.php?option=com_ordenproduccion&task=productos.deleteElemento&id=' . (int) $el->id . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1');
                                    $editUrl = Route::_($baseElementos . '&edit_id=' . (int) $el->id);
                                    $ceiling = (int) ($el->range_1_ceiling ?? 1000);
                                    $legacy = (float) ($el->price ?? 0);
                                    $r1 = (float) ($el->price_1_to_1000 ?? 0);
                                    $r2 = (float) ($el->price_1001_plus ?? 0);
                                    $p1 = $r1 > 0 ? $r1 : $legacy;
                                    $p2 = $r2 > 0 ? $r2 : $legacy;
                                ?>
                                    <tr>
                                        <td><?php echo (int) $el->id; ?></td>
                                        <td><?php echo htmlspecialchars($el->name ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($el->size ?? ''); ?></td>
                                        <td><?php echo $ceiling; ?></td>
                                        <td>Q <?php echo number_format($p1, 2); ?></td>
                                        <td>Q <?php echo number_format($p2, 2); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo $editUrl; ?>" class="btn btn-sm btn-outline-primary"><?php echo $l('JEDIT', 'Editar'); ?></a>
                                            <a href="<?php echo $deleteUrl; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?php echo htmlspecialchars($l('COM_ORDENPRODUCCION_ELEMENTO_CONFIRM_DELETE', '¿Eliminar este elemento?')); ?>');"><?php echo $l('JACTION_DELETE', 'Eliminar'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($section === 'pliegos') : ?>
            <?php if (!$this->tablesExist) : ?>
            <div class="alert alert-warning">
                <?php echo $l('COM_ORDENPRODUCCION_PLIEGO_TABLES_MISSING', 'Las tablas del sistema de cotización por pliego no están instaladas.'); ?>
                <br>
                <small><?php echo $l('COM_ORDENPRODUCCION_PLIEGO_RUN_UPDATE', 'Ejecute el script SQL de actualización 3.67.0_pliego_quoting.sql'); ?></small>
            </div>
            <?php else : ?>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'sizes' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=sizes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_SIZES', 'Tamaños'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'papers' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=papers'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_PAPERS', 'Tipos de Papel'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'lamination' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=lamination'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_LAMINATION', 'Tipos de Laminación'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'processes' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=processes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_PROCESSES', 'Procesos Adicionales'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'pliego' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=pliego'); ?>">
                    Pliego Papel
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'pliego_laminado' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($basePliegos . '&tab=pliego_laminado'); ?>">
                    Pliego Laminado
                </a>
            </li>
        </ul>

        <?php if ($activeTab === 'sizes') : ?>
            <div class="card mb-3">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_ADD_SIZE', 'Añadir tamaño de pliego'); ?></div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveSize'); ?>" method="post" class="form-inline flex-wrap gap-2 align-items-end">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="0">
                        <div class="form-group mb-2 me-2">
                            <label for="size_name" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_SIZE_NAME', 'Nombre'); ?></label>
                            <input type="text" name="name" id="size_name" class="form-control" required placeholder="ej. 60x90" maxlength="100">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="size_width" class="me-1">Ancho (in)</label>
                            <input type="number" name="width_in" id="size_width" class="form-control" step="0.01" placeholder="23.6">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="size_height" class="me-1">Alto (in)</label>
                            <input type="number" name="height_in" id="size_height" class="form-control" step="0.01" placeholder="35.4">
                        </div>
                        <div class="form-group mb-2">
                            <button type="submit" class="btn btn-primary"><?php echo $l('COM_ORDENPRODUCCION_ADD', 'Añadir'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_PLIEGO_SIZES', 'Tamaños de Pliego'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->sizes)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_SIZES', 'No hay tamaños definidos.'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo $l('COM_ORDENPRODUCCION_SIZE_NAME', 'Nombre'); ?></th><th>Dimensiones (in)</th></tr></thead>
                            <tbody>
                                <?php foreach ($this->sizes as $s) : ?>
                                    <tr>
                                        <td><?php echo (int) $s->id; ?></td>
                                        <td><?php echo htmlspecialchars($s->name ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(($s->width_in ?? $s->width_cm ?? '') . ' x ' . ($s->height_in ?? $s->height_cm ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'papers') : ?>
            <div class="card mb-3">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_ADD_PAPER_TYPE', 'Añadir tipo de papel'); ?></div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.savePaperType'); ?>" method="post" class="form-inline flex-wrap gap-2 align-items-end">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="0">
                        <div class="form-group mb-2 me-2">
                            <label for="paper_name" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_PAPER_NAME', 'Nombre'); ?></label>
                            <input type="text" name="name" id="paper_name" class="form-control" required maxlength="255">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="paper_code" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_PAPER_CODE', 'Código'); ?></label>
                            <input type="text" name="code" id="paper_code" class="form-control" maxlength="50">
                        </div>
                        <div class="form-group mb-2">
                            <button type="submit" class="btn btn-primary"><?php echo $l('COM_ORDENPRODUCCION_ADD', 'Añadir'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_PAPER_TYPES', 'Tipos de Papel'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->paperTypes)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_PAPER_TYPES', 'No hay tipos de papel definidos.'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo $l('COM_ORDENPRODUCCION_PAPER_NAME', 'Nombre'); ?></th><th><?php echo $l('COM_ORDENPRODUCCION_PAPER_CODE', 'Código'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($this->paperTypes as $p) : ?>
                                    <tr>
                                        <td><?php echo (int) $p->id; ?></td>
                                        <td><?php echo htmlspecialchars($p->name ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($p->code ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'lamination') : ?>
            <div class="card mb-3">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_ADD_LAMINATION_TYPE', 'Añadir tipo de laminación'); ?></div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveLaminationType'); ?>" method="post" class="form-inline flex-wrap gap-2 align-items-end">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="0">
                        <div class="form-group mb-2 me-2">
                            <label for="lam_name" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_NAME', 'Nombre'); ?></label>
                            <input type="text" name="name" id="lam_name" class="form-control" required maxlength="255">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="lam_code" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_CODE', 'Código'); ?></label>
                            <input type="text" name="code" id="lam_code" class="form-control" maxlength="50">
                        </div>
                        <div class="form-group mb-2">
                            <button type="submit" class="btn btn-primary"><?php echo $l('COM_ORDENPRODUCCION_ADD', 'Añadir'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_TYPES', 'Tipos de Laminación'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->laminationTypes)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_LAMINATION_TYPES', 'No hay tipos de laminación definidos.'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_NAME', 'Nombre'); ?></th><th><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_CODE', 'Código'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($this->laminationTypes as $lam) : ?>
                                    <tr>
                                        <td><?php echo (int) $lam->id; ?></td>
                                        <td><?php echo htmlspecialchars($lam->name ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($lam->code ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'processes') : ?>
            <div class="card mb-3">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_ADD_PROCESS', 'Añadir proceso adicional'); ?></div>
                <div class="card-body">
                    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveProcess'); ?>" method="post" class="form-inline flex-wrap gap-2 align-items-end">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="id" value="0">
                        <div class="form-group mb-2 me-2">
                            <label for="process_name" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_PROCESS_NAME', 'Nombre'); ?></label>
                            <input type="text" name="name" id="process_name" class="form-control" required placeholder="ej. Corte" maxlength="255">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="process_range_ceiling" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_RANGE_1_CEILING', 'Rango 1 hasta (pliegos)'); ?></label>
                            <input type="number" name="range_1_ceiling" id="process_range_ceiling" class="form-control" min="1" value="1000" title="<?php echo $l('COM_ORDENPRODUCCION_RANGE_1_CEILING_DESC', 'Límite superior del primer rango; el segundo rango es desde aquí +1'); ?>">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="process_price_1_1000" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_PROCESS_PRICE_1_1000', 'Precio rango 1'); ?></label>
                            <input type="number" name="price_1_to_1000" id="process_price_1_1000" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="process_price_1001" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_PROCESS_PRICE_1001_PLUS', 'Precio rango 2'); ?></label>
                            <input type="number" name="price_1001_plus" id="process_price_1001" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group mb-2">
                            <button type="submit" class="btn btn-primary"><?php echo $l('COM_ORDENPRODUCCION_ADD', 'Añadir'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_PLIEGO_PROCESSES', 'Procesos Adicionales (corte, doblez, perforado, etc.)'); ?></div>
                <div class="card-body">
                    <p class="text-muted small mb-3"><?php echo $l('COM_ORDENPRODUCCION_PROCESSES_RANGE_NOTE', 'Cada proceso define su propio rango: "Rango 1 hasta" = tope del primer rango (ej. 500 = 1–500); el segundo rango es desde ese tope +1 en adelante.'); ?></p>
                    <?php if (empty($this->processes)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_PROCESSES', 'No hay procesos adicionales definidos.'); ?></p>
                    <?php else : ?>
                        <p class="text-muted mb-3"><?php echo $l('COM_ORDENPRODUCCION_EDIT_PROCESS_PRICES_DESC', 'Cada valor es el precio total para ese rango (no por pliego). Edite y pulse Guardar precios.'); ?></p>
                        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&section=pliegos&tab=processes'); ?>" method="post" name="adminForm" id="adminForm_process_prices">
                            <input type="hidden" name="task" value="productos.saveProcessPrices">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo $l('COM_ORDENPRODUCCION_PROCESS_NAME', 'Nombre'); ?></th>
                                        <th class="bg-light" style="width:140px;"><?php echo $l('COM_ORDENPRODUCCION_RANGE_1_CEILING', 'Rango 1 hasta'); ?></th>
                                        <th class="bg-light" style="width:140px;"><?php echo $l('COM_ORDENPRODUCCION_PROCESS_PRICE_1_1000', 'Precio rango 1'); ?> (Q)</th>
                                        <th class="bg-light" style="width:140px;"><?php echo $l('COM_ORDENPRODUCCION_PROCESS_PRICE_1001_PLUS', 'Precio rango 2'); ?> (Q)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->processes as $pr) :
                                        $pid = (int) $pr->id;
                                        $ceiling = (int) ($pr->range_1_ceiling ?? 1000);
                                        if ($ceiling < 1) {
                                            $ceiling = 1000;
                                        }
                                        $v1 = (float) ($pr->price_1_to_1000 ?? 0);
                                        $v2 = (float) ($pr->price_1001_plus ?? 0);
                                    ?>
                                        <tr>
                                            <td><?php echo $pid; ?></td>
                                            <td><?php echo htmlspecialchars($pr->name ?? ''); ?></td>
                                            <td>
                                                <input type="number" name="range_1_ceiling[<?php echo $pid; ?>]" class="form-control form-control-sm" min="1" value="<?php echo $ceiling; ?>" placeholder="1000" title="<?php echo $l('COM_ORDENPRODUCCION_RANGE_1_CEILING_DESC', 'Límite superior del primer rango'); ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="price_1_to_1000[<?php echo $pid; ?>]" class="form-control form-control-sm" step="0.01" min="0" value="<?php echo $v1; ?>" placeholder="0.00">
                                            </td>
                                            <td>
                                                <input type="number" name="price_1001_plus[<?php echo $pid; ?>]" class="form-control form-control-sm" step="0.01" min="0" value="<?php echo $v2; ?>" placeholder="0.00">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary mt-2"><?php echo $l('COM_ORDENPRODUCCION_SAVE_PRICES', 'Guardar precios'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'pliego') : ?>
            <div class="card mb-3">
                <div class="card-header">Pliego Papel – Precio por pliego (papel + tamaño)</div>
                <div class="card-body">
                    <p class="text-muted mb-3">Seleccione un tipo de papel y defina el precio por pliego para cada tamaño. <strong>Tiro</strong> = impresión o laminación en un solo lado; <strong>Tiro/Retiro</strong> = en ambos lados.</p>
                    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&section=pliegos&tab=pliego'); ?>" class="mb-4">
                        <input type="hidden" name="option" value="com_ordenproduccion">
                        <input type="hidden" name="view" value="productos">
                        <input type="hidden" name="section" value="pliegos">
                        <input type="hidden" name="tab" value="pliego">
                        <label for="pliego_paper_type" class="me-2">Tipo de papel</label>
                        <select name="paper_type_id" id="pliego_paper_type" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                            <option value="0">— Seleccionar —</option>
                            <?php foreach ($this->paperTypes as $pt) : ?>
                                <option value="<?php echo (int) $pt->id; ?>" <?php echo (int) $pt->id === (int) $this->selectedPaperTypeId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pt->name ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ($this->selectedPaperTypeId > 0 && !empty($this->sizes)) : ?>
                        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.savePliegoPrices'); ?>" method="post">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <input type="hidden" name="paper_type_id" value="<?php echo (int) $this->selectedPaperTypeId; ?>">
                            <!-- Pliego: Tiro + Tiro/Retiro price columns (deploy tmpl/productos/default.php) -->
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Tamaño</th>
                                        <th>Dimensiones (in)</th>
                                        <th class="bg-light" style="width:180px;">Tiro (un lado) – Precio (Q)</th>
                                        <th class="bg-light" style="width:180px;">Tiro/Retiro (ambos lados) – Precio (Q)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $pid = $this->pliegoPrices;
                                    foreach ($this->sizes as $s) :
                                        $sid = (int) $s->id;
                                        $tiroVal = isset($pid[$sid]['tiro']) ? (float) $pid[$sid]['tiro'] : '';
                                        $retiroVal = isset($pid[$sid]['retiro']) ? (float) $pid[$sid]['retiro'] : '';
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s->name ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars(($s->width_in ?? $s->width_cm ?? '') . ' x ' . ($s->height_in ?? $s->height_cm ?? '')); ?></td>
                                            <td>
                                                <input type="number" name="price_tiro[<?php echo $sid; ?>]" class="form-control form-control-sm" step="0.01" min="0" value="<?php echo $tiroVal !== '' ? $tiroVal : ''; ?>" placeholder="0.00">
                                            </td>
                                            <td>
                                                <input type="number" name="price_retiro[<?php echo $sid; ?>]" class="form-control form-control-sm" step="0.01" min="0" value="<?php echo $retiroVal !== '' ? $retiroVal : ''; ?>" placeholder="0.00">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary mt-2">Guardar precios</button>
                        </form>
                    <?php elseif ($this->selectedPaperTypeId > 0 && empty($this->sizes)) : ?>
                        <p class="text-muted">No hay tamaños definidos. Agregue tamaños en la pestaña Tamaños.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'pliego_laminado') : ?>
            <div class="card mb-3">
                <div class="card-header">Pliego Laminado – Precio por pliego (tipo de laminación + tamaño)</div>
                <div class="card-body">
                    <p class="text-muted mb-3">Seleccione un tipo de laminación y defina el precio por pliego para cada tamaño. <strong>Tiro</strong> = un solo lado; <strong>Tiro/Retiro</strong> = ambos lados.</p>
                    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&section=pliegos&tab=pliego_laminado'); ?>" class="mb-4">
                        <input type="hidden" name="option" value="com_ordenproduccion">
                        <input type="hidden" name="view" value="productos">
                        <input type="hidden" name="section" value="pliegos">
                        <input type="hidden" name="tab" value="pliego_laminado">
                        <label for="pliego_lam_type" class="me-2">Tipo de laminación</label>
                        <select name="lamination_type_id" id="pliego_lam_type" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                            <option value="0">— Seleccionar —</option>
                            <?php foreach ($this->laminationTypes as $lt) : ?>
                                <option value="<?php echo (int) $lt->id; ?>" <?php echo (int) $lt->id === (int) $this->selectedLaminationTypeId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lt->name ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if ($this->selectedLaminationTypeId > 0 && !empty($this->sizes)) : ?>
                        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=productos.saveLaminationPrices'); ?>" method="post">
                            <?php echo HTMLHelper::_('form.token'); ?>
                            <input type="hidden" name="lamination_type_id" value="<?php echo (int) $this->selectedLaminationTypeId; ?>">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Tamaño</th>
                                        <th>Dimensiones (in)</th>
                                        <th class="bg-light" style="width:180px;">Tiro (un lado) – Precio (Q)</th>
                                        <th class="bg-light" style="width:180px;">Tiro/Retiro (ambos lados) – Precio (Q)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $lamp = $this->laminationPrices;
                                    foreach ($this->sizes as $s) :
                                        $sid = (int) $s->id;
                                        $tiroVal = isset($lamp[$sid]['tiro']) ? (float) $lamp[$sid]['tiro'] : '';
                                        $retiroVal = isset($lamp[$sid]['retiro']) ? (float) $lamp[$sid]['retiro'] : '';
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s->name ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars(($s->width_in ?? $s->width_cm ?? '') . ' x ' . ($s->height_in ?? $s->height_cm ?? '')); ?></td>
                                            <td>
                                                <input type="number" name="price_tiro[<?php echo $sid; ?>]" class="form-control form-control-sm" step="0.01" min="0" value="<?php echo $tiroVal !== '' ? $tiroVal : ''; ?>" placeholder="0.00">
                                            </td>
                                            <td>
                                                <input type="number" name="price_retiro[<?php echo $sid; ?>]" class="form-control form-control-sm" step="0.01" min="0" value="<?php echo $retiroVal !== '' ? $retiroVal : ''; ?>" placeholder="0.00">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary mt-2">Guardar precios</button>
                        </form>
                    <?php elseif ($this->selectedLaminationTypeId > 0 && empty($this->sizes)) : ?>
                        <p class="text-muted">No hay tamaños definidos. Agregue tamaños en la pestaña Tamaños.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
