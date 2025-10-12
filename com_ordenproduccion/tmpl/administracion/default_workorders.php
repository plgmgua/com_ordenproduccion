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

.btn-create-invoice {
    background: #17a2b8;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: background 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-create-invoice:hover {
    background: #138496;
}

.btn-create-invoice i {
    font-size: 11px;
}

.no-quotation {
    color: #999;
    font-size: 12px;
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
                    <th style="width: 120px;"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
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
                            <?php 
                            $hasQuotation = false;
                            $quotationFiles = '';
                            
                            // Check if quotation_files is not empty and not just empty JSON array
                            if (!empty($order->quotation_files) && $order->quotation_files !== '[]' && $order->quotation_files !== '""') {
                                // Handle JSON array format
                                if (strpos($order->quotation_files, '[') === 0) {
                                    $decoded = json_decode($order->quotation_files, true);
                                    if (is_array($decoded) && !empty($decoded[0])) {
                                        $hasQuotation = true;
                                        $quotationFiles = $order->quotation_files;
                                    }
                                } else {
                                    // Handle simple string format
                                    $hasQuotation = true;
                                    $quotationFiles = $order->quotation_files;
                                }
                            }
                            
                            if ($hasQuotation): ?>
                                <button class="btn-create-invoice" 
                                        onclick="openQuotationView(<?php echo $order->id; ?>, '<?php echo htmlspecialchars($order->orden_de_trabajo); ?>', '<?php echo htmlspecialchars($quotationFiles); ?>')">
                                    <i class="fas fa-file-invoice"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_CREATE_INVOICE'); ?>
                                </button>
                            <?php else: ?>
                                <span class="no-quotation"><?php echo Text::_('COM_ORDENPRODUCCION_NO_QUOTATION'); ?></span>
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

<!-- Floating Modal for Quotation Form -->
<div id="quotationModal" class="quotation-modal" style="display: none;">
    <div class="quotation-modal-overlay" onclick="closeQuotationModal()"></div>
    <div class="quotation-modal-content">
        <button class="quotation-modal-close" onclick="closeQuotationModal()">
            <i class="fas fa-times"></i>
        </button>
        <div id="quotationModalBody" class="quotation-modal-body">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p><?php echo Text::_('COM_ORDENPRODUCCION_LOADING'); ?>...</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Floating Modal Styles */
.quotation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.quotation-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
}

.quotation-modal-content {
    position: relative;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: slideUp 0.3s ease;
    z-index: 1;
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.quotation-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #333;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.quotation-modal-close:hover {
    background: #dc3545;
    color: white;
    transform: rotate(90deg);
}

.quotation-modal-body {
    max-height: 90vh;
    overflow-y: auto;
    padding: 20px;
}

.loading-spinner {
    text-align: center;
    padding: 60px 20px;
    color: #007cba;
}

.loading-spinner i {
    font-size: 48px;
    margin-bottom: 20px;
}

.loading-spinner p {
    font-size: 16px;
    color: #666;
}

/* Responsive Design */
@media (max-width: 768px) {
    .quotation-modal-content {
        width: 95%;
        max-width: none;
        max-height: 95vh;
        border-radius: 8px;
    }
    
    .quotation-modal-body {
        padding: 15px;
    }
}
</style>

<script>
function openQuotationView(orderId, orderNumber, quotationFiles) {
    const modal = document.getElementById('quotationModal');
    const modalBody = document.getElementById('quotationModalBody');
    
    // Show modal with loading spinner
    modal.style.display = 'flex';
    modalBody.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p><?php echo Text::_('COM_ORDENPRODUCCION_LOADING'); ?>...</p>
        </div>
    `;
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    
    // Build URL with parameters (try without format=raw first)
    const url = `?option=com_ordenproduccion&view=quotation&layout=display&order_id=${orderId}&order_number=${encodeURIComponent(orderNumber)}&quotation_files=${encodeURIComponent(quotationFiles)}`;
    
    // Fetch content via AJAX
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
            }
            return response.text();
        })
        .then(html => {
            // Extract body content from full HTML response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const body = doc.querySelector('body');
            
            if (body) {
                modalBody.innerHTML = body.innerHTML;
                
                // Execute any scripts in the loaded content
                const scripts = modalBody.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                });
                
                // Load any stylesheets
                const styles = doc.querySelectorAll('style, link[rel="stylesheet"]');
                styles.forEach(style => {
                    if (!document.querySelector(`[href="${style.href}"]`) && style.href) {
                        const newStyle = document.createElement('link');
                        newStyle.rel = 'stylesheet';
                        newStyle.href = style.href;
                        document.head.appendChild(newStyle);
                    } else if (style.tagName === 'STYLE') {
                        const newStyle = document.createElement('style');
                        newStyle.textContent = style.textContent;
                        document.head.appendChild(newStyle);
                    }
                });
            } else {
                modalBody.innerHTML = html;
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 60px 20px; color: #dc3545;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <p style="font-size: 16px;"><?php echo Text::_('COM_ORDENPRODUCCION_ERROR_LOADING_QUOTATION'); ?></p>
                    <button onclick="closeQuotationModal()" style="margin-top: 20px; padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <?php echo Text::_('COM_ORDENPRODUCCION_CLOSE'); ?>
                    </button>
                </div>
            `;
        });
}

function closeQuotationModal() {
    const modal = document.getElementById('quotationModal');
    modal.style.display = 'none';
    
    // Restore body scroll
    document.body.style.overflow = '';
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeQuotationModal();
    }
});
</script>