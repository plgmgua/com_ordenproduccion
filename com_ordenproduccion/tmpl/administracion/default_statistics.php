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

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$stats = $this->stats;
$currentMonth = $this->currentMonth;
$currentYear = $this->currentYear;

// Month names in Spanish
$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>

<style>
.admin-dashboard {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.dashboard-header h1 {
    margin: 0 0 10px 0;
    font-size: 32px;
    font-weight: bold;
}

.month-filter {
    background: rgba(255,255,255,0.2);
    padding: 15px;
    border-radius: 8px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.month-filter label {
    color: white;
    font-weight: bold;
    margin: 0;
}

.month-filter select {
    padding: 8px 12px;
    border-radius: 5px;
    border: none;
    font-size: 14px;
}

.month-filter button {
    padding: 8px 20px;
    background: white;
    color: #667eea;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.2s;
}

.month-filter button:hover {
    transform: scale(1.05);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.stat-card.primary { border-left-color: #667eea; }
.stat-card.success { border-left-color: #28a745; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.info { border-left-color: #17a2b8; }

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-card .value {
    font-size: 36px;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.stat-card .subtitle {
    font-size: 14px;
    color: #999;
    margin-top: 5px;
}

.top-orders-table {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.top-orders-table h2 {
    margin: 0 0 20px 0;
    font-size: 24px;
    color: #333;
}

.top-orders-table table {
    width: 100%;
    border-collapse: collapse;
}

.top-orders-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: bold;
    color: #666;
    border-bottom: 2px solid #dee2e6;
}

.top-orders-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.top-orders-table tr:hover {
    background: #f8f9fa;
}

.order-number {
    font-weight: bold;
    color: #667eea;
    text-decoration: none;
}

.order-number:hover {
    text-decoration: underline;
}

.invoice-value {
    font-size: 18px;
    font-weight: bold;
    color: #28a745;
}

.status-badge {
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
</style>

<div class="admin-dashboard">
    <!-- Header with Month/Year Filter -->
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TITLE'); ?>
        </h1>
        
        <form method="get" action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=statistics'); ?>">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="administracion" />
            <input type="hidden" name="tab" value="statistics" />
            
            <div class="month-filter">
                <label for="month-select">
                    <i class="fas fa-calendar"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SELECT_PERIOD'); ?>:
                </label>
                
                <select name="month" id="month-select">
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $num == $currentMonth ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="year" id="year-select">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <button type="submit">
                    <i class="fas fa-search"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_FILTER'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <!-- Total Orders -->
        <div class="stat-card primary">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_ORDERS'); ?></h3>
            <div class="value"><?php echo number_format($stats->totalOrders); ?></div>
            <div class="subtitle">
                <?php echo $monthNames[$currentMonth] . ' ' . $currentYear; ?>
            </div>
        </div>

        <!-- Total Invoice Value -->
        <div class="stat-card success">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_VALUE'); ?></h3>
            <div class="value">Q <?php echo number_format($stats->totalInvoiceValue, 2); ?></div>
            <div class="subtitle">
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_INVOICE_VALUE'); ?>
            </div>
        </div>

        <!-- Average Invoice Value -->
        <div class="stat-card warning">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_AVERAGE_VALUE'); ?></h3>
            <div class="value">Q <?php echo number_format($stats->averageInvoiceValue, 2); ?></div>
            <div class="subtitle">
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_PER_ORDER'); ?>
            </div>
        </div>

        <!-- Orders by Status -->
        <div class="stat-card info">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_BY_STATUS'); ?></h3>
            <div style="margin-top: 10px;">
                <?php foreach ($stats->ordersByStatus as $statusData): ?>
                    <div style="margin-bottom: 5px; font-size: 14px;">
                        <strong><?php echo $statusData->status; ?>:</strong> <?php echo $statusData->count; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sales by Sales Agent Table -->
    <div class="top-orders-table" style="margin-bottom: 30px;">
        <h2>
            <i class="fas fa-user-tie"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SALES_BY_AGENT'); ?>
        </h2>
        
        <?php if (!empty($stats->salesByAgent)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SALES_AGENT'); ?></th>
                        <th style="text-align: center;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_ORDER_COUNT'); ?></th>
                        <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_SALES'); ?></th>
                        <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_AVERAGE_PER_ORDER'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats->salesByAgent as $index => $agentData): ?>
                        <?php $avgPerOrder = $agentData->order_count > 0 ? ($agentData->total_sales / $agentData->order_count) : 0; ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($agentData->sales_agent); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 20px; font-weight: bold;">
                                    <?php echo number_format($agentData->order_count); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span class="invoice-value">Q <?php echo number_format($agentData->total_sales, 2); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <span style="color: #666;">Q <?php echo number_format($avgPerOrder, 2); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_NO_SALES_DATA'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top 5 Clients Table -->
    <div class="top-clients-table">
        <h2>
            <i class="fas fa-users"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOP_CLIENTS'); ?>
        </h2>
        
        <?php if (!empty($stats->topClients)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_CLIENT_NAME'); ?></th>
                        <th style="text-align: center;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_ORDERS'); ?></th>
                        <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_VALUE'); ?></th>
                        <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_AVERAGE_ORDER'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats->topClients as $index => $clientData): ?>
                        <?php $avgPerOrder = $clientData->order_count > 0 ? ($clientData->total_value / $clientData->order_count) : 0; ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($clientData->client_name); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #e8f5e8; color: #2e7d32; padding: 4px 12px; border-radius: 20px; font-weight: bold;">
                                    <?php echo number_format($clientData->order_count); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span class="invoice-value">Q <?php echo number_format($clientData->total_value, 2); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <span style="color: #666;">Q <?php echo number_format($avgPerOrder, 2); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_NO_CLIENTS_DATA'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top 10 Orders Table -->
    <div class="top-orders-table">
        <h2>
            <i class="fas fa-trophy"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOP_ORDERS'); ?>
        </h2>
        
        <?php if (!empty($stats->topOrders)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_ORDER_NUMBER'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_CLIENT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_DESCRIPTION'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SALES_AGENT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_STATUS'); ?></th>
                        <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_VALUE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats->topOrders as $index => $order): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . $order->id); ?>" 
                                   class="order-number">
                                    <?php echo htmlspecialchars($order->orden_de_trabajo); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($order->client_name); ?></td>
                            <td><?php echo htmlspecialchars(substr($order->work_description, 0, 50)) . (strlen($order->work_description) > 50 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars($order->sales_agent); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $order->status)); ?>">
                                    <?php echo htmlspecialchars($order->status); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span class="invoice-value">Q <?php echo number_format($order->invoice_value, 2); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_NO_ORDERS'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

