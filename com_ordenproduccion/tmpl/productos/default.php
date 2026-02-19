<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

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
            <div class="card">
                <div class="card-header"><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_TYPES', 'Tipos de Laminación'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->laminationTypes)) : ?>
                        <p class="text-muted"><?php echo $l('COM_ORDENPRODUCCION_NO_LAMINATION_TYPES', 'No hay tipos de laminación definidos.'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_NAME', 'Nombre'); ?></th><th><?php echo $l('COM_ORDENPRODUCCION_LAMINATION_CODE', 'Código'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($this->laminationTypes as $l) : ?>
                                    <tr>
                                        <td><?php echo (int) $l->id; ?></td>
                                        <td><?php echo htmlspecialchars($l->name ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($l->code ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeTab === 'processes') : ?>
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
