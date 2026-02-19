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

$activeTab = $this->activeTab ?? 'sizes';
$baseUrl = 'index.php?option=com_ordenproduccion&view=productos';

// Fallback to human-friendly labels when language file is not loaded (e.g. after deploy)
$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};
?>
<div class="com-ordenproduccion-productos">
    <div class="container-fluid">
        <h1 class="page-title"><?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TITLE', 'Productos'); ?></h1>

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
                   href="<?php echo Route::_($baseUrl . '&tab=sizes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_SIZES', 'Tamaños'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'papers' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=papers'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_PAPERS', 'Tipos de Papel'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'lamination' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=lamination'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_LAMINATION', 'Tipos de Laminación'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'processes' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=processes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_TAB_PROCESSES', 'Procesos Adicionales'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'pliego' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=pliego'); ?>">
                    Pliego Papel
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'pliego_laminado' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=pliego_laminado'); ?>">
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
                            <label for="process_price" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_PROCESS_PRICE', 'Precio por pliego'); ?></label>
                            <input type="number" name="price_per_pliego" id="process_price" class="form-control" step="0.01" min="0" value="0">
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
                    <?php if (empty($this->processes)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_PROCESSES', 'No hay procesos adicionales definidos.'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo $l('COM_ORDENPRODUCCION_PROCESS_NAME', 'Nombre'); ?></th><th><?php echo $l('COM_ORDENPRODUCCION_PROCESS_PRICE', 'Precio por pliego'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($this->processes as $pr) : ?>
                                    <tr>
                                        <td><?php echo (int) $pr->id; ?></td>
                                        <td><?php echo htmlspecialchars($pr->name ?? ''); ?></td>
                                        <td>Q <?php echo number_format((float) ($pr->price_per_pliego ?? 0), 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'pliego') : ?>
            <div class="card mb-3">
                <div class="card-header">Pliego Papel – Precio por pliego (papel + tamaño)</div>
                <div class="card-body">
                    <p class="text-muted mb-3">Seleccione un tipo de papel y defina el precio por pliego para cada tamaño. <strong>Tiro</strong> = impresión o laminación en un solo lado; <strong>Tiro/Retiro</strong> = en ambos lados.</p>
                    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&tab=pliego'); ?>" class="mb-4">
                        <input type="hidden" name="option" value="com_ordenproduccion">
                        <input type="hidden" name="view" value="productos">
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
                    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&tab=pliego_laminado'); ?>" class="mb-4">
                        <input type="hidden" name="option" value="com_ordenproduccion">
                        <input type="hidden" name="view" value="productos">
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
    </div>
</div>
