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

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Payments\HtmlView $this */
?>
<div class="com-ordenproduccion-payments">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <h1 class="page-title h4 mb-0">Control de Pagos</h1>
            </div>
        </div>

        <!-- Filters - compact -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="card card-compact">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0 small">
                            <i class="fas fa-filter"></i> Filtros
                        </h5>
                    </div>
                    <div class="card-body py-2">
                        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments'); ?>">
                            <input type="hidden" name="option" value="com_ordenproduccion">
                            <input type="hidden" name="view" value="payments">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label for="filter_client" class="form-label small mb-0">Cliente</label>
                                    <input type="text" name="filter_client" id="filter_client" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.client')); ?>"
                                           placeholder="Filtrar por cliente...">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_from" class="form-label small mb-0">Fecha Desde</label>
                                    <input type="date" name="filter_date_from" id="filter_date_from" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.date_from')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_to" class="form-label small mb-0">Fecha Hasta</label>
                                    <input type="date" name="filter_date_to" id="filter_date_to" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.date_to')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_sales_agent" class="form-label small mb-0">Agente de Ventas</label>
                                    <select name="filter_sales_agent" id="filter_sales_agent" class="form-select form-select-sm">
                                        <?php foreach ($this->getModel()->getSalesAgentOptions() as $value => $text) : ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo $this->state->get('filter.sales_agent') === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($text); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i> Aplicar
                                    </button>
                                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments'); ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Summary and Export -->
        <div class="row mb-2">
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="alert alert-info py-2 mb-0 small flex-grow-1">
                    <i class="fas fa-info-circle"></i> Se encontraron <?php echo count($this->items); ?> pagos
                </div>
                <?php
                $exportUrl = Route::_('index.php?option=com_ordenproduccion&task=payments.export&format=raw');
                $exportUrl .= '&filter_client=' . rawurlencode($this->state->get('filter.client', ''));
                $exportUrl .= '&filter_date_from=' . rawurlencode($this->state->get('filter.date_from', ''));
                $exportUrl .= '&filter_date_to=' . rawurlencode($this->state->get('filter.date_to', ''));
                $exportUrl .= '&filter_sales_agent=' . rawurlencode($this->state->get('filter.sales_agent', ''));
                ?>
                <a href="<?php echo $exportUrl; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener">
                    <i class="fas fa-file-excel"></i> Exportar a Excel
                </a>
            </div>
        </div>

        <!-- Payments List -->
        <?php if (empty($this->items)) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning py-2 text-center small mb-0">
                        <i class="fas fa-exclamation-triangle"></i> No se encontraron pagos
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col" class="small">Fecha</th>
                                    <th scope="col" class="small">Cliente</th>
                                    <th scope="col" class="small">Orden</th>
                                    <th scope="col" class="small">Tipo</th>
                                    <th scope="col" class="small">Nº Doc.</th>
                                    <th scope="col" class="small">Monto</th>
                                    <th scope="col" class="small">Agente</th>
                                    <th scope="col" class="small"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->items as $item) : ?>
                                    <tr>
                                        <td class="small"><?php echo $this->formatDate($item->created); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($item->client_name ?? '-'); ?></td>
                                        <td class="small">
                                            <?php if (!empty($item->order_id)) : ?>
                                                <a href="<?php echo $this->getOrderRoute($item->order_id); ?>" class="text-primary">
                                                    <?php echo htmlspecialchars($item->order_number ?? $item->orden_de_trabajo ?? '#' . $item->order_id); ?>
                                                </a>
                                            <?php else : ?>-<?php endif; ?>
                                        </td>
                                        <td class="small"><?php echo $this->translatePaymentType($item->payment_type ?? ''); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($item->document_number ?? '-'); ?></td>
                                        <td class="small"><?php echo number_format((float) ($item->payment_amount ?? 0), 2); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($item->sales_agent ?? '-'); ?></td>
                                        <td class="small">
                                            <?php if (!empty($item->order_id)) : ?>
                                                <a href="<?php echo $this->getPaymentProofRoute($item->order_id); ?>"
                                                   class="btn btn-sm btn-outline-primary py-0 px-1" title="Ver comprobante">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($this->pagination->pagesTotal > 1) : ?>
                <div class="row mt-2">
                    <div class="col-12">
                        <nav aria-label="Paginación" class="small">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </nav>
                        <div class="pagination-info text-center small mt-1">
                            <?php echo $this->pagination->getResultsCounter(); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<style>
.com-ordenproduccion-payments .card-compact .card-body { padding: 0.5rem 1rem; }
.com-ordenproduccion-payments .table th, .com-ordenproduccion-payments .table td { vertical-align: middle; }
</style>
