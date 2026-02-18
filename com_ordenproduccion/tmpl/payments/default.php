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
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="page-title">
                    <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_TITLE'); ?>
                </h1>
                <?php if ($this->params->get('show_page_heading')) : ?>
                    <p class="page-description">
                        <?php echo $this->params->get('page_heading'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTERS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments'); ?>">
                            <input type="hidden" name="option" value="com_ordenproduccion">
                            <input type="hidden" name="view" value="payments">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="filter_client"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_CLIENT'); ?></label>
                                    <input type="text"
                                           name="filter_client"
                                           id="filter_client"
                                           class="form-control"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.client')); ?>"
                                           placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_CLIENT_PLACEHOLDER'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_from"><?php echo Text::_('COM_ORDENPRODUCCION_FILTER_DATE_FROM'); ?></label>
                                    <input type="date"
                                           name="filter_date_from"
                                           id="filter_date_from"
                                           class="form-control"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.date_from')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="filter_date_to"><?php echo Text::_('COM_ORDENPRODUCCION_FILTER_DATE_TO'); ?></label>
                                    <input type="date"
                                           name="filter_date_to"
                                           id="filter_date_to"
                                           class="form-control"
                                           value="<?php echo htmlspecialchars($this->state->get('filter.date_to')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="filter_sales_agent"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_FILTER_SALES_AGENT'); ?></label>
                                    <select name="filter_sales_agent" id="filter_sales_agent" class="form-control">
                                        <?php foreach ($this->getModel()->getSalesAgentOptions() as $value => $text) : ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo $this->state->get('filter.sales_agent') === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($text); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label>
                                    <div class="btn-group d-block">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_APPLY'); ?>
                                        </button>
                                        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=payments'); ?>"
                                           class="btn btn-secondary">
                                            <i class="fas fa-times"></i>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_CLEAR'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Summary -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo Text::sprintf('COM_ORDENPRODUCCION_PAYMENTS_FOUND', count($this->items)); ?>
                </div>
            </div>
        </div>

        <!-- Payments List -->
        <?php if (empty($this->items)) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_NO_ITEMS'); ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_DATE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_NAME'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_ORDER'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENTS_COL_DOCUMENT'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_AGENTE_VENTAS'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->items as $item) : ?>
                                    <tr>
                                        <td><?php echo $this->formatDate($item->created); ?></td>
                                        <td><?php echo htmlspecialchars($item->client_name ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($item->order_id)) : ?>
                                                <a href="<?php echo $this->getOrderRoute($item->order_id); ?>" class="text-primary">
                                                    <?php echo htmlspecialchars($item->order_number ?? $item->orden_de_trabajo ?? '#' . $item->order_id); ?>
                                                </a>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $this->translatePaymentType($item->payment_type ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item->document_number ?? '-'); ?></td>
                                        <td><?php echo number_format((float) ($item->payment_amount ?? 0), 2); ?></td>
                                        <td><?php echo htmlspecialchars($item->sales_agent ?? '-'); ?></td>
                                        <td>
                                            <?php if (!empty($item->order_id)) : ?>
                                                <a href="<?php echo $this->getPaymentProofRoute($item->order_id); ?>"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="<?php echo Text::_('COM_ORDENPRODUCCION_VIEW_PAYMENT_PROOF'); ?>">
                                                    <i class="fas fa-credit-card fa-sm"></i>
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
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_PAGINATION'); ?>">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </nav>
                        <div class="pagination-info text-center mt-2">
                            <?php echo $this->pagination->getResultsCounter(); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
