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
?>

<div class="com-ordenproduccion-productos">
    <div class="container-fluid">
        <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTOS_TITLE'); ?></h1>

        <?php if (!$this->tablesExist) : ?>
            <div class="alert alert-warning">
                <?php echo Text::_('COM_ORDENPRODUCCION_PLIEGO_TABLES_MISSING'); ?>
                <br>
                <small><?php echo Text::_('COM_ORDENPRODUCCION_PLIEGO_RUN_UPDATE'); ?></small>
            </div>
        <?php else : ?>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'sizes' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=sizes'); ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTOS_TAB_SIZES'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'papers' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=papers'); ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTOS_TAB_PAPERS'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'lamination' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=lamination'); ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTOS_TAB_LAMINATION'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'processes' ? 'active' : ''; ?>"
                   href="<?php echo Route::_($baseUrl . '&tab=processes'); ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTOS_TAB_PROCESSES'); ?>
                </a>
            </li>
        </ul>

        <?php if ($activeTab === 'sizes') : ?>
            <div class="card">
                <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_PLIEGO_SIZES'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->sizes)) : ?>
                        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_NO_SIZES'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo Text::_('COM_ORDENPRODUCCION_SIZE_NAME'); ?></th><th><?php echo Text::_('COM_ORDENPRODUCCION_SIZE_DIMENSIONS'); ?></th></tr></thead>
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
                <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_PAPER_TYPES'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->paperTypes)) : ?>
                        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_NO_PAPER_TYPES'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo Text::_('COM_ORDENPRODUCCION_PAPER_NAME'); ?></th><th><?php echo Text::_('COM_ORDENPRODUCCION_PAPER_CODE'); ?></th></tr></thead>
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
                <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_LAMINATION_TYPES'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->laminationTypes)) : ?>
                        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_NO_LAMINATION_TYPES'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo Text::_('COM_ORDENPRODUCCION_LAMINATION_NAME'); ?></th><th><?php echo Text::_('COM_ORDENPRODUCCION_LAMINATION_CODE'); ?></th></tr></thead>
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
                <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_PLIEGO_PROCESSES'); ?></div>
                <div class="card-body">
                    <?php if (empty($this->processes)) : ?>
                        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_NO_PROCESSES'); ?></p>
                    <?php else : ?>
                        <table class="table table-sm">
                            <thead><tr><th>#</th><th><?php echo Text::_('COM_ORDENPRODUCCION_PROCESS_NAME'); ?></th><th><?php echo Text::_('COM_ORDENPRODUCCION_PROCESS_PRICE'); ?></th></tr></thead>
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
