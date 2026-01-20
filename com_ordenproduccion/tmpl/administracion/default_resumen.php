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

.agent-header:hover {
    opacity: 0.95;
}

.agent-orders-list {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 2000px;
    }
}

.orders-detail-table tr:hover {
    background-color: #f8f9fa;
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
        
        <!-- Overall Summary Table -->
        <table class="resumen-table" style="margin-bottom: 30px;">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDERS_CREATED'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONEY_GENERATED'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_STATUS_CHANGES'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PAYMENT_PROOFS'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONEY_COLLECTED'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="stat-value"><?php echo number_format($currentStats->workOrdersCreated ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value">Q. <?php echo number_format($currentStats->moneyGenerated ?? 0, 2); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($currentStats->statusChanges ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($currentStats->paymentProofsRecorded ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value">Q. <?php echo number_format($currentStats->moneyCollected ?? 0, 2); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Grouped by Sales Agent -->
        <?php 
        $agentsStats = $this->activityStatsByAgent ?? [];
        if (!empty($agentsStats)): 
        ?>
        <h4 style="color: #495057; margin-top: 30px; margin-bottom: 20px;">
            <i class="fas fa-users"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_BY_SALES_AGENT'); ?>
        </h4>

        <?php foreach ($agentsStats as $agentStats): ?>
            <div class="agent-group" style="margin-bottom: 25px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                <!-- Agent Header (Clickable to toggle orders list) -->
                <div class="agent-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;" onclick="toggleAgentOrders('agent-<?php echo htmlspecialchars(md5($agentStats->salesAgent ?? 'no-agent')); ?>')">
                    <div>
                        <strong style="font-size: 16px;">
                            <i class="fas fa-user-tie"></i>
                            <?php echo htmlspecialchars($agentStats->salesAgent ?? Text::_('COM_ORDENPRODUCCION_RESUMEN_NO_AGENT')); ?>
                        </strong>
                    </div>
                    <div style="display: flex; gap: 30px; align-items: center;">
                        <span><strong><?php echo number_format($agentStats->workOrdersCreated ?? 0); ?></strong> <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDERS'); ?></span>
                        <span><strong>Q. <?php echo number_format($agentStats->moneyGenerated ?? 0, 2); ?></strong> <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_VENTAS'); ?></span>
                        <span><strong>Q. <?php echo number_format($agentStats->moneyCollected ?? 0, 2); ?></strong> <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_COBROS'); ?></span>
                        <i class="fas fa-chevron-down" id="icon-agent-<?php echo htmlspecialchars(md5($agentStats->salesAgent ?? 'no-agent')); ?>" style="transition: transform 0.3s;"></i>
                    </div>
                </div>

                <!-- Agent Summary Table -->
                <div style="padding: 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                    <table class="resumen-table" style="margin: 0; box-shadow: none;">
                        <thead>
                            <tr>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDERS_CREATED'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONEY_GENERATED'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_STATUS_CHANGES'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PAYMENT_PROOFS'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONEY_COLLECTED'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo number_format($agentStats->workOrdersCreated ?? 0); ?></td>
                                <td>Q. <?php echo number_format($agentStats->moneyGenerated ?? 0, 2); ?></td>
                                <td><?php echo number_format($agentStats->statusChanges ?? 0); ?></td>
                                <td><?php echo number_format($agentStats->paymentProofsRecorded ?? 0); ?></td>
                                <td>Q. <?php echo number_format($agentStats->moneyCollected ?? 0, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Orders List (Collapsed by default) -->
                <div id="agent-<?php echo htmlspecialchars(md5($agentStats->salesAgent ?? 'no-agent')); ?>" class="agent-orders-list" style="display: none; padding: 15px; background: white;">
                    <h5 style="color: #495057; margin-bottom: 15px;">
                        <i class="fas fa-list"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDERS_DETAIL'); ?>
                    </h5>
                    <table class="orders-detail-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDER_NUMBER'); ?></th>
                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDER_DESCRIPTION'); ?></th>
                                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6; font-weight: 600; color: #495057;"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDER_VALUE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $orders = $agentStats->orders ?? [];
                            if (empty($orders)): 
                            ?>
                                <tr>
                                    <td colspan="3" style="padding: 15px; text-align: center; color: #6c757d;">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_NO_ORDERS'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr style="border-bottom: 1px solid #e9ecef;">
                                        <td style="padding: 10px;">
                                            <strong><?php echo htmlspecialchars($order->orden_de_trabajo ?? $order->order_number ?? 'ORD-' . $order->id); ?></strong>
                                        </td>
                                        <td style="padding: 10px; color: #495057;">
                                            <?php echo htmlspecialchars($order->work_description ?? '-'); ?>
                                        </td>
                                        <td style="padding: 10px; text-align: right; font-weight: 600; color: #28a745;">
                                            Q. <?php echo number_format((float)($order->invoice_value ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

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
    const ordersList = document.getElementById(agentId);
    const icon = document.getElementById('icon-' + agentId);
    
    if (ordersList.style.display === 'none' || ordersList.style.display === '') {
        ordersList.style.display = 'block';
        if (icon) {
            icon.style.transform = 'rotate(180deg)';
        }
    } else {
        ordersList.style.display = 'none';
        if (icon) {
            icon.style.transform = 'rotate(0deg)';
        }
    }
}
</script>
