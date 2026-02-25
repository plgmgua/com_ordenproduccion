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
$statisticsMinYear = isset($this->statisticsMinYear) ? (int) $this->statisticsMinYear : 2020;

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

/* Sales Agents with Clients Expandable Table */
.sales-agents-clients-table {
    margin-bottom: 30px;
}

.expandable-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.expandable-table thead {
    background: linear-gradient(135deg, #007cba 0%, #0056b3 100%);
    color: white;
}

.expandable-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
}

.expandable-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
}

/* Agent Row Styles */
.agent-row {
    background: #fff;
    transition: background-color 0.2s;
}

.agent-row:hover {
    background: #f8f9fa;
}

.expand-cell {
    width: 40px;
    text-align: center;
}

.expand-btn {
    background: none;
    border: none;
    color: #007cba;
    cursor: pointer;
    font-size: 18px;
    padding: 5px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.expand-btn:hover {
    color: #0056b3;
    transform: scale(1.2);
}

.expand-btn.expanded {
    color: #28a745;
}

/* Client Row Styles */
.client-row {
    background: #f8f9fa;
    transition: background-color 0.2s;
}

.client-row:hover {
    background: #e9ecef;
}

.client-row td {
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
}

/* Badge Styles */
.badge-orders {
    display: inline-block;
    background: #e3f2fd;
    color: #1976d2;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
}

.badge-client-orders {
    display: inline-block;
    background: #e8f5e8;
    color: #2e7d32;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .expandable-table {
        font-size: 13px;
    }
    
    .expandable-table th,
    .expandable-table td {
        padding: 10px 8px;
    }
    
    .expand-btn {
        font-size: 16px;
    }
}

/* Compact Statistics Cards */
.stats-grid-compact {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card-compact {
    background: white;
    border-radius: 6px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #007cba;
    transition: transform 0.2s;
}

.stat-card-compact:hover {
    transform: translateY(-2px);
}

.stat-card-compact.primary { border-left-color: #007cba; }
.stat-card-compact.success { border-left-color: #28a745; }
.stat-card-compact.warning { border-left-color: #ffc107; }
.stat-card-compact.info { border-left-color: #17a2b8; }

.stat-card-compact h3 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.value-compact {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 4px;
}

.subtitle-compact {
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
}

.status-details-compact {
    margin-top: 8px;
}

.status-item-compact {
    font-size: 11px;
    margin-bottom: 3px;
    color: #495057;
}

.status-item-compact strong {
    font-weight: 600;
}

@media (max-width: 768px) {
    .stats-grid-compact {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .stat-card-compact {
        padding: 12px;
    }
    
    .value-compact {
        font-size: 18px;
    }
}

/* Sales Agents Annual Trend Chart */
.agent-trend-section {
    margin: 30px 0;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 25px;
}

/* Client Annual Trend Chart */
.client-trend-section {
    margin: 30px 0;
    background: #f0f8ff;
    border-radius: 8px;
    padding: 25px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.chart-header h2 {
    color: #007cba;
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.year-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.year-selector label {
    font-weight: 600;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 5px;
}

.year-selector select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.3s;
}

.year-selector select:hover {
    border-color: #007cba;
}

.year-selector select:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 0.2rem rgba(0, 124, 186, 0.25);
}

.chart-container-single {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.chart-subtitle {
    color: #495057;
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.chart-wrapper {
    position: relative;
    height: 500px;
    width: 100%;
}

@media (max-width: 768px) {
    .agent-trend-section {
        padding: 15px;
    }
    
    .chart-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .chart-container-single {
        padding: 15px;
    }
    
    .chart-wrapper {
        height: 400px;
    }
}
</style>

<div class="admin-dashboard">
    <!-- Statistics Cards (Compact) -->
    <div class="stats-grid-compact">
        <!-- Total Orders -->
        <div class="stat-card-compact primary">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_ORDERS'); ?></h3>
            <div class="value-compact"><?php echo number_format($stats->totalOrders); ?></div>
            <div class="subtitle-compact">
                <?php echo ($currentMonth == 0) ? 'Todo el Año ' . $currentYear : $monthNames[$currentMonth] . ' ' . $currentYear; ?>
            </div>
        </div>

        <!-- Total Invoice Value -->
        <div class="stat-card-compact success">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_VALUE'); ?></h3>
            <div class="value-compact">Q <?php echo number_format($stats->totalInvoiceValue, 2); ?></div>
            <div class="subtitle-compact">
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_INVOICE_VALUE'); ?>
            </div>
        </div>

        <!-- Average Invoice Value -->
        <div class="stat-card-compact warning">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_AVERAGE_VALUE'); ?></h3>
            <div class="value-compact">Q <?php echo number_format($stats->averageInvoiceValue, 2); ?></div>
            <div class="subtitle-compact">
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_PER_ORDER'); ?>
            </div>
        </div>

        <!-- Orders by Status -->
        <div class="stat-card-compact info">
            <h3><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_BY_STATUS'); ?></h3>
            <div class="status-details-compact">
                <?php foreach ($stats->ordersByStatus as $statusData): ?>
                    <div class="status-item-compact">
                        <strong><?php echo $statusData->status; ?>:</strong> <?php echo $statusData->count; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

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
            <input type="hidden" name="_t" value="<?php echo time(); ?>" />
            
            <div class="month-filter">
                <label for="month-select">
                    <i class="fas fa-calendar"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SELECT_PERIOD'); ?>:
                </label>
                
                <select name="month" id="month-select">
                    <option value="0" <?php echo $currentMonth == 0 ? 'selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_ALL_YEAR'); ?></option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $num == $currentMonth ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="year" id="year-select">
                    <?php for ($y = date('Y'); $y >= $statisticsMinYear; $y--): ?>
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

    <!-- Sales Agents with Top 5 Clients - Expandable Table -->
    <div class="sales-agents-clients-table">
        <h2>
            <i class="fas fa-user-tie"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SALES_AGENTS_AND_CLIENTS'); ?>
        </h2>
        
        <?php if (!empty($stats->salesAgentsWithClients)): ?>
            <table class="expandable-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>#</th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SALES_AGENT'); ?></th>
                        <th style="text-align: center;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_ORDERS'); ?></th>
                        <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TOTAL_SALES'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats->salesAgentsWithClients as $agentIndex => $agentData): ?>
                        <!-- Sales Agent Row -->
                        <tr class="agent-row" data-agent-id="<?php echo $agentIndex; ?>">
                            <td class="expand-cell">
                                <?php if (!empty($agentData->topClients)): ?>
                                    <button class="expand-btn" onclick="toggleClients(<?php echo $agentIndex; ?>)">
                                        <i class="fas fa-plus-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo $agentIndex + 1; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($agentData->sales_agent); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge-orders">
                                    <?php echo number_format($agentData->total_orders); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span class="invoice-value">Q <?php echo number_format($agentData->total_sales, 2); ?></span>
                            </td>
                        </tr>
                        
                        <!-- Top 5 Clients Rows (Initially Hidden) -->
                        <?php if (!empty($agentData->topClients)): ?>
                            <?php foreach ($agentData->topClients as $clientIndex => $clientData): ?>
                                <tr class="client-row" data-agent-id="<?php echo $agentIndex; ?>" style="display: none;">
                                    <td></td>
                                    <td style="text-align: center; color: #999;">
                                        <i class="fas fa-minus"></i>
                                    </td>
                                    <td style="padding-left: 40px;">
                                        <i class="fas fa-user" style="color: #2e7d32; margin-right: 8px;"></i>
                                        <?php echo htmlspecialchars($clientData->client_name); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge-client-orders">
                                            <?php echo number_format($clientData->order_count); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <span style="color: #666;">Q <?php echo number_format($clientData->total_value, 2); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <script>
            function toggleClients(agentId) {
                const clientRows = document.querySelectorAll('tr.client-row[data-agent-id="' + agentId + '"]');
                const expandBtn = document.querySelector('tr.agent-row[data-agent-id="' + agentId + '"] .expand-btn');
                const icon = expandBtn.querySelector('i');
                
                const isExpanded = clientRows[0].style.display !== 'none';
                
                clientRows.forEach(row => {
                    row.style.display = isExpanded ? 'none' : 'table-row';
                });
                
                if (isExpanded) {
                    icon.className = 'fas fa-plus-circle';
                    expandBtn.classList.remove('expanded');
                } else {
                    icon.className = 'fas fa-minus-circle';
                    expandBtn.classList.add('expanded');
                }
            }
            </script>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_NO_SALES_DATA'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sales Agents Annual Trend Charts -->
    <div class="agent-trend-section">
        <div class="chart-header">
            <h2>
                <i class="fas fa-chart-line"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_AGENT_ANNUAL_TREND'); ?>
            </h2>
            <div class="year-selector">
                <label for="agent-year-select">
                    <i class="fas fa-calendar"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SELECT_YEAR'); ?>:
                </label>
                <select id="agent-year-select" onchange="updateAgentChart()">
                    <?php for ($y = date('Y'); $y >= $statisticsMinYear; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <!-- Line Chart -->
        <div class="chart-container-single">
            <div class="chart-subtitle">
                <i class="fas fa-chart-line"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_LINE_CHART'); ?>
            </div>
            <div class="chart-wrapper">
                <canvas id="agentAnnualChart"></canvas>
            </div>
        </div>
        
        <!-- Bar Chart -->
        <div class="chart-container-single">
            <div class="chart-subtitle">
                <i class="fas fa-chart-bar"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_BAR_CHART'); ?>
            </div>
            <div class="chart-wrapper">
                <canvas id="agentAnnualBarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Client Annual Trend Charts -->
    <div class="client-trend-section">
        <div class="chart-header">
            <h2>
                <i class="fas fa-users"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_CLIENT_ANNUAL_TREND'); ?>
            </h2>
            <div class="year-selector">
                <label for="client-year-select">
                    <i class="fas fa-calendar"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_SELECT_YEAR'); ?>:
                </label>
                <select id="client-year-select" onchange="updateClientChart()">
                    <?php for ($y = date('Y'); $y >= $statisticsMinYear; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <!-- Line Chart -->
        <div class="chart-container-single">
            <div class="chart-subtitle">
                <i class="fas fa-chart-line"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_LINE_CHART'); ?>
            </div>
            <div class="chart-wrapper">
                <canvas id="clientAnnualChart"></canvas>
            </div>
        </div>
        
        <!-- Bar Chart -->
        <div class="chart-container-single">
            <div class="chart-subtitle">
                <i class="fas fa-chart-bar"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_BAR_CHART'); ?>
            </div>
            <div class="chart-wrapper">
                <canvas id="clientAnnualBarChart"></canvas>
            </div>
        </div>
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

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Helper function to generate colors for chart lines
function getColor(index, alpha = 1) {
    const colors = [
        `rgba(0, 124, 186, ${alpha})`,   // Blue
        `rgba(76, 175, 80, ${alpha})`,   // Green
        `rgba(255, 152, 0, ${alpha})`,   // Orange
        `rgba(156, 39, 176, ${alpha})`,  // Purple
        `rgba(244, 67, 54, ${alpha})`,   // Red
        `rgba(0, 188, 212, ${alpha})`,   // Cyan
        `rgba(255, 193, 7, ${alpha})`,   // Amber
        `rgba(96, 125, 139, ${alpha})`,  // Blue Grey
        `rgba(233, 30, 99, ${alpha})`,   // Pink
        `rgba(139, 195, 74, ${alpha})`   // Light Green
    ];
    return colors[index % colors.length];
}

// Store chart instances globally
let agentAnnualChart = null;
let agentAnnualBarChart = null;
let clientAnnualChart = null;
let clientAnnualBarChart = null;

// Store trend data (yearly data)
const agentAnnualData = <?php echo json_encode($stats->agentTrend ?? []); ?>;
const clientAnnualData = <?php echo json_encode($stats->clientTrend ?? []); ?>;

// Initialize agent annual charts (line and bar)
function initializeAgentChart() {
    <?php if (!empty($stats->agentTrend) && !empty($stats->agentTrend['agents'])): ?>
    
    // Destroy existing charts completely
    if (agentAnnualChart) {
        agentAnnualChart.destroy();
        agentAnnualChart = null;
    }
    if (agentAnnualBarChart) {
        agentAnnualBarChart.destroy();
        agentAnnualBarChart = null;
    }
    
    // Ensure we have fresh data
    console.log('Initializing charts with fresh data:', agentAnnualData);
    
    // Validate and prepare chart data
    if (!agentAnnualData || !agentAnnualData.agents || !Array.isArray(agentAnnualData.agents)) {
        console.error('Invalid chart data:', agentAnnualData);
        return;
    }
    
    // Process data to ensure no duplication and proper values
    const processedAgents = agentAnnualData.agents.map((agent, index) => {
        // Ensure data is a proper array of numbers
        const cleanData = Array.isArray(agent.data) ? agent.data.map(val => parseFloat(val) || 0) : [];
        console.log(`Agent ${agent.agent_name} data:`, cleanData);
        
        return {
            label: agent.agent_name,
            data: cleanData,
            borderColor: getColor(index),
            backgroundColor: getColor(index, 0.2),
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 8
        };
    });
    
    // Common data for both charts
    const chartData = {
        labels: agentAnnualData.labels || ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        datasets: processedAgents
    };

    // Common options for both charts
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 20,
                    font: { size: 12 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': Q ' + context.parsed.y.toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Q ' + value.toLocaleString('es-GT');
                    }
                }
            }
        }
    };

    // Line Chart Configuration
    const lineConfig = {
        type: 'line',
        data: chartData,
        options: commonOptions
    };

    // Bar Chart Configuration with processed data
    const barConfig = {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: processedAgents.map((agent, index) => ({
                label: agent.label,
                data: agent.data,
                backgroundColor: getColor(index, 0.8),
                borderColor: getColor(index),
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false
            }))
        },
        options: commonOptions
    };

    // Initialize both charts
    agentAnnualChart = new Chart(document.getElementById('agentAnnualChart'), lineConfig);
    agentAnnualBarChart = new Chart(document.getElementById('agentAnnualBarChart'), barConfig);
    
    <?php endif; ?>
}

// Initialize client annual charts (line and bar)
function initializeClientChart() {
    <?php if (!empty($stats->clientTrend) && !empty($stats->clientTrend['clients'])): ?>
    
    // Destroy existing charts completely
    if (clientAnnualChart) {
        clientAnnualChart.destroy();
        clientAnnualChart = null;
    }
    if (clientAnnualBarChart) {
        clientAnnualBarChart.destroy();
        clientAnnualBarChart = null;
    }
    
    // Ensure we have fresh data
    console.log('Initializing client charts with fresh data:', clientAnnualData);
    
    // Validate and prepare chart data
    if (!clientAnnualData || !clientAnnualData.clients || !Array.isArray(clientAnnualData.clients)) {
        console.error('Invalid client chart data:', clientAnnualData);
        return;
    }
    
    // Process data to ensure no duplication and proper values
    const processedClients = clientAnnualData.clients.map((client, index) => {
        // Ensure data is a proper array of numbers
        const cleanData = Array.isArray(client.data) ? client.data.map(val => parseFloat(val) || 0) : [];
        console.log(`Client ${client.client_name} data:`, cleanData);
        
        return {
            label: client.client_name,
            data: cleanData,
            borderColor: getColor(index),
            backgroundColor: getColor(index, 0.2),
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 6,
            pointHoverRadius: 8
        };
    });
    
    // Common data for both charts
    const chartData = {
        labels: clientAnnualData.labels || ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        datasets: processedClients
    };

    // Common options for both charts
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 20,
                    font: { size: 12 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': Q ' + context.parsed.y.toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Q ' + value.toLocaleString('es-GT');
                    }
                }
            }
        }
    };

    // Line Chart Configuration
    const lineConfig = {
        type: 'line',
        data: chartData,
        options: commonOptions
    };

    // Bar Chart Configuration with processed data
    const barConfig = {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: processedClients.map((client, index) => ({
                label: client.label,
                data: client.data,
                backgroundColor: getColor(index, 0.8),
                borderColor: getColor(index),
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false
            }))
        },
        options: commonOptions
    };

    // Initialize both charts
    clientAnnualChart = new Chart(document.getElementById('clientAnnualChart'), lineConfig);
    clientAnnualBarChart = new Chart(document.getElementById('clientAnnualBarChart'), barConfig);
    
    <?php endif; ?>
}

// Function to update agent chart when year changes
function updateAgentChart() {
    const selectedYear = parseInt(document.getElementById('agent-year-select').value);
    
    // Clear chart instances and data completely
    if (agentAnnualChart) {
        agentAnnualChart.destroy();
        agentAnnualChart = null;
    }
    if (agentAnnualBarChart) {
        agentAnnualBarChart.destroy();
        agentAnnualBarChart = null;
    }
    
    // Clear any cached data
    if (typeof agentAnnualData !== 'undefined') {
        // Force data refresh by reloading page
        const url = new URL(window.location.href);
        url.searchParams.set('year', selectedYear);
        url.searchParams.set('month', 0); // Always use yearly view for agent chart
        url.searchParams.set('_t', Date.now()); // Add timestamp to prevent caching
        window.location.href = url.toString();
    }
}

// Function to update client chart when year changes
function updateClientChart() {
    const selectedYear = parseInt(document.getElementById('client-year-select').value);
    
    // Clear chart instances and data completely
    if (clientAnnualChart) {
        clientAnnualChart.destroy();
        clientAnnualChart = null;
    }
    if (clientAnnualBarChart) {
        clientAnnualBarChart.destroy();
        clientAnnualBarChart = null;
    }
    
    // Clear any cached data
    if (typeof clientAnnualData !== 'undefined') {
        // Force data refresh by reloading page
        const url = new URL(window.location.href);
        url.searchParams.set('year', selectedYear);
        url.searchParams.set('month', 0); // Always use yearly view for client chart
        url.searchParams.set('_t', Date.now()); // Add timestamp to prevent caching
        window.location.href = url.toString();
    }
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeAgentChart();
    initializeClientChart();
});
</script>

