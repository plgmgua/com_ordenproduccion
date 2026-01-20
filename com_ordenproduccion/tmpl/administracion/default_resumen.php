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
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$app = Factory::getApplication();
$input = $app->input;
$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

// Get selected period from view, default to 'day'
$selectedPeriod = isset($this->selectedPeriod) ? $this->selectedPeriod : $input->get('period', 'day', 'string');

// Get activity statistics from view
$activityStats = $this->activityStats ?? (object) [
    'daily' => (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0],
    'weekly' => (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0],
    'monthly' => (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0]
];

// Get stats for selected period only
$currentStats = null;
switch ($selectedPeriod) {
    case 'week':
        $currentStats = $activityStats->weekly ?? (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'moneyGenerated' => 0, 'moneyCollected' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0];
        $periodLabel = Text::_('COM_ORDENPRODUCCION_RESUMEN_WEEKLY');
        break;
    case 'month':
        $currentStats = $activityStats->monthly ?? (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'moneyGenerated' => 0, 'moneyCollected' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0];
        $periodLabel = Text::_('COM_ORDENPRODUCCION_RESUMEN_MONTHLY');
        break;
    case 'day':
    default:
        $currentStats = $activityStats->daily ?? (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'moneyGenerated' => 0, 'moneyCollected' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0];
        $periodLabel = Text::_('COM_ORDENPRODUCCION_RESUMEN_DAILY');
        break;
}
?>

<style>
.resumen-container {
    padding: 20px 0;
}

.resumen-section {
    margin-bottom: 40px;
}

.resumen-section h3 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
}

.resumen-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}

.resumen-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.resumen-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

.resumen-table td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
}

.resumen-table tbody tr:hover {
    background-color: #f8f9fa;
}

.resumen-table tbody tr:last-child td {
    border-bottom: none;
}

.stat-value {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.stat-label {
    color: #666;
    font-size: 14px;
    margin-right: 5px;
}

.period-header {
    background: #f8f9fa !important;
    color: #333 !important;
    font-weight: 700;
    font-size: 14px;
    text-transform: none;
    letter-spacing: 0;
}

.period-cell {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

#period-select {
    border: 2px solid #667eea;
    border-radius: 5px;
    transition: all 0.3s;
}

#period-select:hover {
    border-color: #764ba2;
}

#period-select:focus {
    outline: none;
    border-color: #764ba2;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Expandable Table Styles (matching estadisticas) */
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

.order-row {
    background: #f8f9fa;
    transition: background-color 0.2s;
}

.order-row:hover {
    background: #e9ecef;
}

.order-row td {
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
}

.badge-orders {
    display: inline-block;
    background: #e3f2fd;
    color: #1976d2;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
}

.invoice-value {
    font-size: 18px;
    font-weight: bold;
    color: #28a745;
}
</style>

<div class="resumen-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="margin: 0;">
                <i class="fas fa-chart-bar"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_TITLE'); ?>
            </h2>
            <p class="text-muted" style="margin: 5px 0 0 0;">
                <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_DESCRIPTION'); ?>
            </p>
        </div>
        <div>
            <form method="get" action="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen'); ?>" id="period-filter-form" style="display: inline-block;">
                <input type="hidden" name="option" value="com_ordenproduccion">
                <input type="hidden" name="view" value="administracion">
                <input type="hidden" name="tab" value="resumen">
                <label for="period-select" style="margin-right: 10px; font-weight: 600; color: #333;">
                    <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SELECT_PERIOD'); ?>:
                </label>
                <select name="period" id="period-select" class="form-control" style="display: inline-block; width: auto; min-width: 150px; padding: 8px 12px; font-size: 14px;" onchange="this.form.submit();">
                    <option value="day" <?php echo $selectedPeriod === 'day' ? 'selected' : ''; ?>>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_CURRENT_DAY'); ?>
                    </option>
                    <option value="week" <?php echo $selectedPeriod === 'week' ? 'selected' : ''; ?>>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_CURRENT_WEEK'); ?>
                    </option>
                    <option value="month" <?php echo $selectedPeriod === 'month' ? 'selected' : ''; ?>>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_CURRENT_MONTH'); ?>
                    </option>
                </select>
            </form>
        </div>
    </div>

    <!-- Work Orders Activities Section -->
    <div class="resumen-section">
        <h3>
            <i class="fas fa-clipboard-list"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_WORK_ORDERS_ACTIVITIES'); ?>
            <small style="color: #666; font-weight: normal; margin-left: 10px;">(<?php echo htmlspecialchars($periodLabel); ?>)</small>
        </h3>
        
        <!-- Single compact table with expandable rows -->
        <table class="expandable-table">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_AGENT'); ?></th>
                    <th style="text-align: center;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDERS_CREATED'); ?></th>
                    <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONEY_GENERATED'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Overall Summary Row -->
                <tr class="agent-row" style="font-weight: bold; background: #f8f9fa;">
                    <td></td>
                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_TOTAL'); ?></strong></td>
                    <td style="text-align: center;">
                        <span class="badge-orders"><?php echo number_format($currentStats->workOrdersCreated ?? 0); ?></span>
                    </td>
                    <td style="text-align: right;">
                        <span class="invoice-value">Q <?php echo number_format($currentStats->moneyGenerated ?? 0, 2); ?></span>
                    </td>
                </tr>

                <!-- Agent Rows -->
                <?php 
                $agentsStats = $this->activityStatsByAgent ?? [];
                if (!empty($agentsStats)): 
                    foreach ($agentsStats as $index => $agentStats): 
                        $agentId = md5($agentStats->salesAgent ?? 'no-agent');
                        $agentName = htmlspecialchars($agentStats->salesAgent ?? Text::_('COM_ORDENPRODUCCION_RESUMEN_NO_AGENT'));
                        $hasOrders = !empty($agentStats->orders);
                ?>
                    <!-- Agent Summary Row -->
                    <tr class="agent-row" data-agent-id="<?php echo $agentId; ?>">
                        <td class="expand-cell">
                            <?php if ($hasOrders): ?>
                                <button class="expand-btn" onclick="toggleAgentOrders('<?php echo $agentId; ?>')">
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $agentName; ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge-orders">
                                <?php echo number_format($agentStats->workOrdersCreated ?? 0); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <span class="invoice-value">Q <?php echo number_format($agentStats->moneyGenerated ?? 0, 2); ?></span>
                        </td>
                    </tr>

                    <!-- Orders Detail Rows (Initially Hidden) -->
                    <?php if ($hasOrders): ?>
                        <?php foreach ($agentStats->orders as $order): ?>
                            <tr class="order-row" data-agent-id="<?php echo $agentId; ?>" style="display: none;">
                                <td></td>
                                <td style="padding-left: 40px;">
                                    <i class="fas fa-minus" style="color: #999; margin-right: 8px;"></i>
                                    <i class="fas fa-file-alt" style="color: #667eea; margin-right: 8px;"></i>
                                    <strong><?php echo htmlspecialchars($order->orden_de_trabajo ?? $order->order_number ?? 'ORD-' . $order->id); ?></strong>
                                    <?php if (!empty($order->client_name)): ?>
                                        <span style="color: #666; margin-left: 12px; font-size: 13px;">
                                            | <?php echo htmlspecialchars($order->client_name); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($order->created)): ?>
                                        <span style="color: #999; margin-left: 12px; font-size: 12px;">
                                            | <?php echo Factory::getDate($order->created)->format('d/m/Y'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <br>
                                    <span style="color: #666; font-size: 13px; margin-left: 32px;">
                                        <?php echo htmlspecialchars($order->work_description ?? '-'); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;"></td>
                                <td style="text-align: right;">
                                    <span class="invoice-value">Q <?php echo number_format((float)($order->invoice_value ?? 0), 2); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php 
                    endforeach;
                endif; 
                ?>
            </tbody>
        </table>
    </div>

    <!-- Payment Proofs Section -->
    <?php 
    $paymentProofsByAgent = $this->paymentProofsByAgent ?? [];
    // Calculate totals
    $totalProofs = 0;
    $totalCollected = 0;
    foreach ($paymentProofsByAgent as $agentStat) {
        $totalProofs += $agentStat->paymentProofsCount ?? 0;
        $totalCollected += $agentStat->moneyCollected ?? 0;
    }
    ?>
    <div class="resumen-section" style="margin-top: 40px;">
        <h3>
            <i class="fas fa-money-check-alt"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PAYMENT_PROOFS_TITLE'); ?>
            <small style="color: #666; font-weight: normal; margin-left: 10px;">(<?php echo htmlspecialchars($periodLabel); ?>)</small>
        </h3>
        
        <table class="expandable-table">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_AGENT'); ?></th>
                    <th style="text-align: center;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PAYMENT_PROOFS'); ?></th>
                    <th style="text-align: right;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONEY_COLLECTED'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Overall Summary Row -->
                <tr class="agent-row" style="font-weight: bold; background: #f8f9fa;">
                    <td></td>
                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_TOTAL'); ?></strong></td>
                    <td style="text-align: center;">
                        <span class="badge-orders"><?php echo number_format($totalProofs); ?></span>
                    </td>
                    <td style="text-align: right;">
                        <span class="invoice-value">Q <?php echo number_format($totalCollected, 2); ?></span>
                    </td>
                </tr>

                <!-- Agent Rows -->
                <?php if (!empty($paymentProofsByAgent)): ?>
                    <?php foreach ($paymentProofsByAgent as $index => $agentStat): 
                        $agentId = md5($agentStat->salesAgent ?? 'no-agent-' . $index);
                        $agentName = htmlspecialchars($agentStat->salesAgent ?? Text::_('COM_ORDENPRODUCCION_RESUMEN_NO_AGENT'));
                        $hasProofs = !empty($agentStat->paymentProofs);
                    ?>
                        <tr class="agent-row" data-agent-id="<?php echo $agentId; ?>">
                            <td class="expand-cell">
                                <?php if ($hasProofs): ?>
                                    <button class="expand-btn" onclick="togglePaymentProofs('<?php echo $agentId; ?>')">
                                        <i class="fas fa-plus-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $agentName; ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge-orders">
                                    <?php echo number_format($agentStat->paymentProofsCount ?? 0); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <span class="invoice-value">Q <?php echo number_format($agentStat->moneyCollected ?? 0, 2); ?></span>
                            </td>
                        </tr>

                        <!-- Payment Proof Details (Initially Hidden) -->
                        <?php if ($hasProofs): ?>
                            <?php foreach ($agentStat->paymentProofs as $proof): ?>
                                <tr class="payment-proof-row" data-agent-id="<?php echo $agentId; ?>" style="display: none;">
                                    <td></td>
                                    <td style="padding-left: 40px; color: #999;">
                                        <i class="fas fa-minus"></i>
                                    </td>
                                    <td style="padding-left: 20px;">
                                        <i class="fas fa-file-invoice-dollar" style="color: #28a745; margin-right: 8px;"></i>
                                        <strong><?php echo htmlspecialchars($proof->orden_de_trabajo ?? $proof->order_number ?? 'ORD-' . $proof->order_id); ?></strong>
                                        <br>
                                        <span style="color: #666; font-size: 13px; margin-left: 32px;">
                                            <?php echo htmlspecialchars($proof->work_description ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <span class="invoice-value">Q <?php echo number_format((float)($proof->payment_amount ?? 0), 2); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #6c757d;">
                            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_NO_PAYMENT_PROOFS'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Status Changes Section -->
    <?php 
    $statusChangesData = $this->statusChangesByAgent ?? null;
    if ($statusChangesData && !empty($statusChangesData->agents)):
        $allStatuses = $statusChangesData->allStatuses ?? [];
        $statusAgents = $statusChangesData->agents ?? [];
    ?>
    <div class="resumen-section" style="margin-top: 40px;">
        <h3>
            <i class="fas fa-exchange-alt"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_STATUS_CHANGES_TITLE'); ?>
            <small style="color: #666; font-weight: normal; margin-left: 10px;">(<?php echo htmlspecialchars($periodLabel); ?>)</small>
        </h3>
        
        <table class="expandable-table">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_AGENT'); ?></th>
                    <?php foreach ($allStatuses as $status): ?>
                        <th style="text-align: center;"><?php echo htmlspecialchars($status); ?></th>
                    <?php endforeach; ?>
                    <th style="text-align: center;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Overall Summary Row -->
                <?php
                $totalByStatus = [];
                $grandTotal = 0;
                foreach ($allStatuses as $status) {
                    $totalByStatus[$status] = 0;
                }
                foreach ($statusAgents as $agentStat) {
                    foreach ($allStatuses as $status) {
                        $totalByStatus[$status] += $agentStat->statusCounts[$status] ?? 0;
                    }
                    $grandTotal += $agentStat->totalStatusChanges ?? 0;
                }
                ?>
                <tr class="agent-row" style="font-weight: bold; background: #f8f9fa;">
                    <td></td>
                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_TOTAL'); ?></strong></td>
                    <?php foreach ($allStatuses as $status): ?>
                        <td style="text-align: center;">
                            <span class="badge-orders"><?php echo number_format($totalByStatus[$status] ?? 0); ?></span>
                        </td>
                    <?php endforeach; ?>
                    <td style="text-align: center;">
                        <span class="badge-orders"><?php echo number_format($grandTotal); ?></span>
                    </td>
                </tr>

                <!-- Agent Rows -->
                <?php foreach ($statusAgents as $index => $agentStat): 
                    $agentId = md5($agentStat->salesAgent ?? 'no-agent-' . $index);
                    $agentName = htmlspecialchars($agentStat->salesAgent ?? Text::_('COM_ORDENPRODUCCION_RESUMEN_NO_AGENT'));
                ?>
                    <tr class="agent-row" data-agent-id="<?php echo $agentId; ?>">
                        <td class="expand-cell">
                            <!-- No expand button for status changes as there are no details to show -->
                        </td>
                        <td>
                            <strong><?php echo $agentName; ?></strong>
                        </td>
                        <?php foreach ($allStatuses as $status): ?>
                            <td style="text-align: center;">
                                <span class="badge-orders">
                                    <?php echo number_format($agentStat->statusCounts[$status] ?? 0); ?>
                                </span>
                            </td>
                        <?php endforeach; ?>
                        <td style="text-align: center;">
                            <span class="badge-orders">
                                <?php echo number_format($agentStat->totalStatusChanges ?? 0); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Shipping Slips Section -->
    <div class="resumen-section">
        <h3>
            <i class="fas fa-shipping-fast"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_SLIPS'); ?>
            <small style="color: #666; font-weight: normal; margin-left: 10px;">(<?php echo htmlspecialchars($periodLabel); ?>)</small>
        </h3>
        
        <table class="resumen-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_FULL'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_PARTIAL'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="stat-value"><?php echo number_format($currentStats->shippingSlipsFull ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($currentStats->shippingSlipsPartial ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format(($currentStats->shippingSlipsFull ?? 0) + ($currentStats->shippingSlipsPartial ?? 0)); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleAgentOrders(agentId) {
    const orderRows = document.querySelectorAll('tr.order-row[data-agent-id="' + agentId + '"]');
    const expandBtn = document.querySelector('tr.agent-row[data-agent-id="' + agentId + '"] .expand-btn');
    
    if (!expandBtn || orderRows.length === 0) return;
    
    const icon = expandBtn.querySelector('i');
    const isExpanded = orderRows[0].style.display !== 'none';
    
    orderRows.forEach(row => {
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


function togglePaymentProofs(agentId) {
    const proofRows = document.querySelectorAll('tr.payment-proof-row[data-agent-id="' + agentId + '"]');
    const expandBtn = document.querySelector('tr.agent-row[data-agent-id="' + agentId + '"] .expand-btn');
    
    if (!expandBtn || proofRows.length === 0) return;
    
    const icon = expandBtn.querySelector('i');
    const isExpanded = proofRows[0].style.display !== 'none';
    
    proofRows.forEach(row => {
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
