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
                            <label for="size_width" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_WIDTH_CM', 'Ancho (cm)'); ?></label>
                            <input type="number" name="width_cm" id="size_width" class="form-control" step="0.01" placeholder="60">
                        </div>
                        <div class="form-group mb-2 me-2">
                            <label for="size_height" class="me-1"><?php echo $l('COM_ORDENPRODUCCION_HEIGHT_CM', 'Alto (cm)'); ?></label>
                            <input type="number" name="height_cm" id="size_height" class="form-control" step="0.01" placeholder="90">
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
                            <thead><tr><th>#</th><th><?php echo $l('COM_ORDENPRODUCCION_SIZE_NAME', 'Nombre'); ?></th><th><?php echo $l('COM_ORDENPRODUCCION_SIZE_DIMENSIONS', 'Dimensiones (cm)'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($this->sizes as $s) : ?>
                                    <tr>
                                        <td><?php echo (int) $s->id; ?></td>
                                        <td><?php echo htmlspecialchars($s->name ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(($s->width_cm ?? '') . ' x ' . ($s->height_cm ?? '')); ?></td>
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

        <?php endif; ?>
    </div>
</div>
