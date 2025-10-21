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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Ordenes\HtmlView $this */

// Get user groups for access control
$userGroups = $this->getUserGroups();
$isVentas = in_array(2, $userGroups); // Adjust group ID as needed
$isProduccion = in_array(3, $userGroups); // Adjust group ID as needed
$isAdministracion = AccessHelper::isInAdministracionGroup();
?>

<div class="com-ordenproduccion-ordenes">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="page-title">
                    <?php echo Text::_('COM_ORDENPRODUCCION_ORDENES_TITLE'); ?>
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
                        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>">
                            <input type="hidden" name="option" value="com_ordenproduccion">
                            <input type="hidden" name="view" value="ordenes">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="filter_search">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_SEARCH'); ?>
                                        </label>
                                        <input type="text" 
                                               name="filter_search" 
                                               id="filter_search" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($this->state->get('filter.search')); ?>"
                                               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_FILTER_SEARCH_PLACEHOLDER'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="filter_status">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_STATUS'); ?>
                                        </label>
                                        <select name="filter_status" id="filter_status" class="form-control">
                                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_STATUS'); ?></option>
                                            <?php foreach ($this->getModel()->getStatusOptions() as $value => $text) : ?>
                                                <?php if ($value !== '') : ?>
                                                    <option value="<?php echo $value; ?>" 
                                                            <?php echo $this->state->get('filter.status') == $value ? 'selected' : ''; ?>>
                                                        <?php echo $this->translateStatus($value); ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="filter_date_from">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_DATE_FROM'); ?>
                                        </label>
                                        <input type="date" 
                                               name="filter_date_from" 
                                               id="filter_date_from" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($this->state->get('filter.date_from')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="filter_date_to">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_DATE_TO'); ?>
                                        </label>
                                        <input type="date" 
                                               name="filter_date_to" 
                                               id="filter_date_to" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($this->state->get('filter.date_to')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group d-block">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                                <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_APPLY'); ?>
                                            </button>
                                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" 
                                               class="btn btn-secondary">
                                                <i class="fas fa-times"></i>
                                                <?php echo Text::_('COM_ORDENPRODUCCION_FILTER_CLEAR'); ?>
                                            </a>
                                        </div>
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
                    <?php echo Text::sprintf('COM_ORDENPRODUCCION_ORDENES_FOUND', count($this->items)); ?>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($this->items)) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_NO_ORDENES_FOUND'); ?>
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
                                    <th scope="col">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_NUMERO'); ?>
                                    </th>
                                    <th scope="col">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CLIENTE'); ?>
                                    </th>
                                    <th scope="col">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_FECHA_SOLICITUD'); ?>
                                    </th>
                                    <th scope="col">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_FECHA_ENTREGA'); ?>
                                    </th>
                                    <th scope="col">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_ESTADO'); ?>
                                    </th>
                                    <?php if ($isVentas || ($isVentas && $isProduccion)) : ?>
                                        <th scope="col">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_AGENTE_VENTAS'); ?>
                                        </th>
                                    <?php endif; ?>
                                    <th scope="col">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->items as $item) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $this->getOrderRoute($item->id); ?>" 
                                               class="font-weight-bold text-primary">
                                                <?php echo htmlspecialchars($item->order_number); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item->client_name); ?>
                                        </td>
                                        <td>
                                            <?php echo $this->formatDate($item->request_date); ?>
                                        </td>
                                        <td>
                                            <?php echo $this->formatDate($item->delivery_date); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $this->getStatusBadgeClass($item->status); ?>" style="color: #333 !important;">
                                                <?php echo $this->translateStatus($item->status); ?>
                                            </span>
                                        </td>
                                        <?php if ($isVentas || ($isVentas && $isProduccion)) : ?>
                                            <td>
                                                <?php echo htmlspecialchars($item->sales_agent); ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="btn-group ordenes-actions" role="group">
                                                <!-- Create Invoice - Only for Administracion group -->
                                                <?php if ($isAdministracion): ?>
                                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&order_id=' . $item->id); ?>"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="<?php echo Text::_('COM_ORDENPRODUCCION_CREATE_INVOICE'); ?>"
                                                   aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_CREATE_INVOICE'); ?>">
                                                    <i class="fas fa-file-invoice fa-sm" aria-hidden="true"></i>
                                                </a>
                                                <?php endif; ?>
                                                <!-- Payment Proof -->
                                                <?php $paymentProofUrl = 'index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . (int) $item->id; ?>
                                                <a href="<?php echo $paymentProofUrl; ?>"
                                                   class="btn btn-sm btn-outline-success"
                                                   title="<?php echo Text::_('COM_ORDENPRODUCCION_REGISTER_PAYMENT_PROOF'); ?>"
                                                   aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_REGISTER_PAYMENT_PROOF'); ?>">
                                                    <i class="fas fa-credit-card fa-sm" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
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
