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

// Get work orders data (we'll load this from the view)
$workOrders = $this->workOrders ?? [];
$pagination = $this->workOrdersPagination ?? null;
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
    margin-bottom: 20px;
}

.workorders-header h2 {
    margin: 0;
    font-size: 24px;
    color: #333;
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

.workorders-table {
    width: 100%;
    border-collapse: collapse;
}

.workorders-table thead {
    background: #f8f9fa;
}

.workorders-table th {
    padding: 12px;
    text-align: left;
    font-weight: bold;
    color: #666;
    border-bottom: 2px solid #dee2e6;
}

.workorders-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.workorders-table tbody tr {
    cursor: pointer;
    transition: background 0.2s;
}

.workorders-table tbody tr:hover {
    background: #f8f9fa;
}

.order-number {
    font-weight: bold;
    color: #667eea;
}

.invoice-number {
    font-weight: 600;
    color: #333;
}

.invoice-number.empty {
    color: #999;
    font-style: italic;
}

.btn-assign-invoice {
    padding: 4px 8px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

.btn-assign-invoice:hover {
    background: #218838;
}

.order-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.status-nueva { background: #e3f2fd; color: #1976d2; }
.status-en-proceso { background: #fff3e0; color: #f57c00; }
.status-terminada { background: #e8f5e9; color: #388e3c; }
.status-entregada { background: #f3e5f5; color: #7b1fa2; }
.status-cerrada { background: #eceff1; color: #546e7a; }

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

.assign-invoice-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
}

.assign-invoice-modal .modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #555;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 30px;
}

.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}
</style>

<div class="workorders-section">
    <div class="workorders-header">
        <h2>
            <i class="fas fa-clipboard-list"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_WORK_ORDERS_TITLE'); ?>
        </h2>
    </div>

    <!-- Search and Filter -->
    <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'); ?>" 
          class="search-filter-bar">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="workorders" />
        
        <input type="text" 
               name="filter_search" 
               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_SEARCH_WORK_ORDER'); ?>"
               value="<?php echo isset($this->state) ? $this->escape($this->state->get('filter.search', '')) : ''; ?>" />
        
        <select name="filter_status">
            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_ALL_STATUSES'); ?></option>
            <option value="Nueva"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_NUEVA'); ?></option>
            <option value="En Proceso"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_EN_PROCESO'); ?></option>
            <option value="Terminada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_TERMINADA'); ?></option>
            <option value="Entregada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_ENTREGADA'); ?></option>
            <option value="Cerrada"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS_CERRADA'); ?></option>
        </select>
        
        <button type="submit">
            <i class="fas fa-search"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FILTER'); ?>
        </button>
    </form>

    <!-- Work Orders Table -->
    <?php if (!empty($workOrders)): ?>
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
                    <th style="width: 100px;"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
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
                        <td>
                            <button class="btn-assign-invoice" 
                                    onclick="openAssignInvoiceModal(<?php echo $order->id; ?>, '<?php echo htmlspecialchars($order->orden_de_trabajo); ?>', '<?php echo htmlspecialchars($order->invoice_number); ?>')">
                                <?php echo Text::_('COM_ORDENPRODUCCION_ASSIGN_INVOICE'); ?>
                            </button>
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
            <i class="fas fa-clipboard-list"></i>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_NO_WORK_ORDERS_FOUND'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Assign Invoice Modal -->
<div id="assign-invoice-modal" class="assign-invoice-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ASSIGN_INVOICE_TITLE'); ?></h3>
            <button class="close-modal" onclick="closeAssignInvoiceModal()">&times;</button>
        </div>
        
        <form id="assign-invoice-form">
            <input type="hidden" id="order-id" name="order_id" value="" />
            
            <div class="form-group">
                <label for="order-number"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?>:</label>
                <input type="text" id="order-number" readonly style="background: #f5f5f5;" />
            </div>
            
            <div class="form-group">
                <label for="invoice-number"><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_NUMBER'); ?>:</label>
                <input type="text" id="invoice-number" name="invoice_number" required />
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAssignInvoiceModal()">
                    <?php echo Text::_('COM_ORDENPRODUCCION_CANCEL'); ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?php echo Text::_('COM_ORDENPRODUCCION_ASSIGN'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignInvoiceModal(orderId, orderNumber, currentInvoiceNumber) {
    document.getElementById('order-id').value = orderId;
    document.getElementById('order-number').value = orderNumber;
    document.getElementById('invoice-number').value = currentInvoiceNumber || '';
    document.getElementById('assign-invoice-modal').style.display = 'block';
}

function closeAssignInvoiceModal() {
    document.getElementById('assign-invoice-modal').style.display = 'none';
}

document.getElementById('assign-invoice-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const orderId = document.getElementById('order-id').value;
    const invoiceNumber = document.getElementById('invoice-number').value;
    
    if (!invoiceNumber.trim()) {
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_NUMBER_REQUIRED'); ?>');
        return;
    }
    
    // Submit AJAX request to assign invoice number
    fetch('/components/com_ordenproduccion/assign_invoice.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'order_id=' + encodeURIComponent(orderId) + 
              '&invoice_number=' + encodeURIComponent(invoiceNumber) +
              '&' + document.querySelector('input[name="option"]').name + '=' + document.querySelector('input[name="option"]').value +
              '&' + document.querySelector('input[name="task"]').name + '=' + document.querySelector('input[name="task"]').value
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('<?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_ASSIGNED_SUCCESS'); ?>');
            location.reload();
        } else {
            alert('<?php echo Text::_('COM_ORDENPRODUCCION_ERROR'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_ERROR_ASSIGNING_INVOICE'); ?>');
    });
});

// Close modal when clicking outside
document.getElementById('assign-invoice-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAssignInvoiceModal();
    }
});
</script>
