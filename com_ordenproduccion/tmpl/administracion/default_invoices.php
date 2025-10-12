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

// Get invoices data (we'll load this from the view)
$invoices = $this->invoices ?? [];
$pagination = $this->invoicesPagination ?? null;
?>

<style>
.invoices-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.invoices-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.invoices-header h2 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.btn-create-invoice {
    padding: 10px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.3s;
}

.btn-create-invoice:hover {
    background: #5568d3;
    color: white;
    text-decoration: none;
}

.search-filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-filter-bar input,
.search-filter-bar select {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    font-size: 14px;
}

.search-filter-bar button {
    padding: 8px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

.search-filter-bar button:hover {
    background: #5568d3;
}

.invoices-table {
    width: 100%;
    border-collapse: collapse;
}

.invoices-table thead {
    background: #f8f9fa;
}

.invoices-table th {
    padding: 12px;
    text-align: left;
    font-weight: bold;
    color: #666;
    border-bottom: 2px solid #dee2e6;
}

.invoices-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.invoices-table tbody tr {
    cursor: pointer;
    transition: background 0.2s;
}

.invoices-table tbody tr:hover {
    background: #f8f9fa;
}

.invoice-number {
    font-weight: bold;
    color: #667eea;
}

.orden-number {
    font-weight: 600;
    color: #333;
}

.invoice-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.status-draft { background: #e3f2fd; color: #1976d2; }
.status-sent { background: #fff3e0; color: #f57c00; }
.status-paid { background: #e8f5e9; color: #388e3c; }
.status-cancelled { background: #ffebee; color: #c62828; }

.invoice-amount {
    font-size: 16px;
    font-weight: bold;
    color: #28a745;
    text-align: right;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state p {
    font-size: 18px;
    margin-bottom: 20px;
}
</style>

<div class="invoices-section">
    <div class="invoices-header">
        <h2>
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_INVOICES_TITLE'); ?>
        </h2>
        
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&layout=create'); ?>" 
           class="btn-create-invoice">
            <i class="fas fa-plus"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_CREATE_INVOICE'); ?>
        </a>
    </div>

    <!-- Search and Filter -->
    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices'); ?>" 
          class="search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="invoices" />
        
        <input type="text" 
               name="filter_search" 
               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_SEARCH_INVOICE'); ?>"
               value="<?php echo $this->escape($this->state->get('filter.search', '')); ?>" />
        
        <select name="filter_status">
            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ALL_STATUSES'); ?></option>
            <option value="draft"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_DRAFT'); ?></option>
            <option value="sent"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_SENT'); ?></option>
            <option value="paid"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_PAID'); ?></option>
            <option value="cancelled"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_CANCELLED'); ?></option>
        </select>
        
        <button type="submit">
            <i class="fas fa-search"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER'); ?>
        </button>
    </form>

    <!-- Invoices Table -->
    <?php if (!empty($invoices)): ?>
        <table class="invoices-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_NUMBER'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_DATE'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_DELIVERY_DATE'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_SALES_AGENT'); ?></th>
                    <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_AMOUNT'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr onclick="window.location.href='<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&id=' . $invoice->id); ?>'">
                        <td>
                            <span class="invoice-number"><?php echo htmlspecialchars($invoice->invoice_number); ?></span>
                        </td>
                        <td>
                            <span class="orden-number"><?php echo htmlspecialchars($invoice->orden_de_trabajo); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($invoice->client_name); ?></td>
                        <td><?php echo !empty($invoice->request_date) ? HTMLHelper::_('date', $invoice->request_date, 'Y-m-d') : '-'; ?></td>
                        <td><?php echo !empty($invoice->delivery_date) ? HTMLHelper::_('date', $invoice->delivery_date, 'Y-m-d') : '-'; ?></td>
                        <td><?php echo htmlspecialchars($invoice->sales_agent); ?></td>
                        <td class="invoice-amount">
                            <?php echo $invoice->currency; ?> <?php echo number_format($invoice->invoice_amount, 2); ?>
                        </td>
                        <td>
                            <span class="invoice-status status-<?php echo htmlspecialchars($invoice->status); ?>">
                                <?php echo ucfirst(htmlspecialchars($invoice->status)); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pagination): ?>
            <div class="pagination-wrapper" style="margin-top: 20px; text-align: center;">
                <?php echo $pagination->getListFooter(); ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_NO_INVOICES_FOUND'); ?></p>
            <p>
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=invoice&layout=create'); ?>" 
                   class="btn-create-invoice">
                    <i class="fas fa-plus"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_CREATE_FIRST_INVOICE'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

