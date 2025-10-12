<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Variables passed from default_tabs.php (since we use include instead of loadTemplate)
// $workOrders, $pagination, and $state are already defined in the including template
?>

<style>
.workorders-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.workorders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.workorders-title {
    color: #495057;
    font-size: 24px;
    font-weight: 600;
    margin: 0;
}

.workorders-count {
    background: #007cba;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.workorders-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.workorders-filters input,
.workorders-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.workorders-filters input[type="text"] {
    min-width: 200px;
}

.workorders-filters select {
    min-width: 150px;
}

.filter-btn {
    background: #007cba;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.filter-btn:hover {
    background: #005a8b;
}

.workorders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.workorders-table th {
    background: #007cba;
    color: white;
    padding: 15px 10px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
}

.workorders-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #e9ecef;
    font-size: 14px;
}

.workorders-table tr:hover {
    background: #f8f9fa;
}

.order-number {
    font-weight: 600;
    color: #007cba;
}

.order-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-nueva {
    background: #e3f2fd;
    color: #1976d2;
}

.status-en-proceso {
    background: #fff3e0;
    color: #f57c00;
}

.status-terminada {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-entregada {
    background: #f3e5f5;
    color: #7b1fa2;
}

.status-cerrada {
    background: #fafafa;
    color: #616161;
}

.invoice-number {
    font-weight: 500;
}

.invoice-number.empty {
    color: #999;
    font-style: italic;
}

.pagination-wrapper {
    margin-top: 20px;
    text-align: center;
}

.pagination-wrapper .pagination {
    display: inline-block;
    margin: 0;
}

.pagination-wrapper .pagination li {
    display: inline-block;
    margin: 0 2px;
}

.pagination-wrapper .pagination li a {
    padding: 8px 12px;
    border: 1px solid #ddd;
    color: #007cba;
    text-decoration: none;
    border-radius: 4px;
}

.pagination-wrapper .pagination li a:hover {
    background: #007cba;
    color: white;
}

.pagination-wrapper .pagination li.active a {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.empty-state i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 15px;
}

.empty-state h3 {
    color: #999;
    margin-bottom: 10px;
}

.empty-state p {
    color: #aaa;
    margin: 0;
}
</style>

<div class="workorders-section">
    <div class="workorders-header">
        <h2 class="workorders-title">
            <i class="fas fa-clipboard-list"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_WORK_ORDERS'); ?>
        </h2>
        <div class="workorders-count">
            <?php if ($pagination): ?>
                <?php echo $pagination->getPagesCounter(); ?>
            <?php else: ?>
                <?php echo count($workOrders); ?> <?php echo Text::_('COM_ORDENPRODUCCION_ORDERS'); ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search and Filter Form -->
    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'); ?>" class="workorders-filters">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="workorders" />
        
        <input type="text" 
               name="filter_search" 
               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_SEARCH_WORK_ORDER'); ?>"
               value="<?php echo isset($state) ? htmlspecialchars($state->get('filter.search', '')) : ''; ?>" />
        
        <select name="filter_status">
            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ALL_STATUSES'); ?></option>
            <option value="Nueva"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_NUEVA'); ?></option>
            <option value="En Proceso"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_EN_PROCESO'); ?></option>
            <option value="Terminada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_TERMINADA'); ?></option>
            <option value="Entregada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_ENTREGADA'); ?></option>
            <option value="Cerrada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_CERRADA'); ?></option>
        </select>
        
        <button type="submit" class="filter-btn">
            <i class="fas fa-search"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER'); ?>
        </button>
        
        <?php if (!empty($state) && ($state->get('filter.search') || $state->get('filter.status'))): ?>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'); ?>" 
               class="filter-btn" style="background: #6c757d; text-decoration: none;">
                <i class="fas fa-times"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_CLEAR_FILTERS'); ?>
            </a>
        <?php endif; ?>
    </form>

    <?php if (empty($workOrders)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_NO_WORK_ORDERS'); ?></h3>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_NO_WORK_ORDERS_DESC'); ?></p>
        </div>
    <?php else: ?>
        <!-- DEBUG: About to render table with <?php echo count($workOrders); ?> orders -->
        <table class="workorders-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_DATE'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_DELIVERY_DATE'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_SALES_AGENT'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_NUMBER'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workOrders as $order): ?>
                    <tr>
                        <td>
                            <span class="order-number"><?php echo htmlspecialchars($order->orden_de_trabajo); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($order->client_name); ?></td>
                        <td><?php echo !empty($order->request_date) ? HTMLHelper::_('date', $order->request_date, 'Y-m-d') : '-'; ?></td>
                        <td><?php echo !empty($order->delivery_date) ? HTMLHelper::_('date', $order->delivery_date, 'Y-m-d') : '-'; ?></td>
                        <td>
                            <span class="order-status status-<?php echo strtolower(str_replace(' ', '-', $order->status)); ?>">
                                <?php echo htmlspecialchars($order->status); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($order->sales_agent); ?></td>
                        <td>
                            <?php if (!empty($order->invoice_number)): ?>
                                <span class="invoice-number"><?php echo htmlspecialchars($order->invoice_number); ?></span>
                            <?php else: ?>
                                <span class="invoice-number empty"><?php echo Text::_('COM_ORDENPRODUCCION_NOT_ASSIGNED'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pagination): ?>
            <div class="pagination-wrapper">
                <?php echo $pagination->getListFooter(); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>