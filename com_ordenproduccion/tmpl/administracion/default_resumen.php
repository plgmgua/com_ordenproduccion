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
$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

// Get activity statistics from view
$activityStats = $this->activityStats ?? (object) [
    'daily' => (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0],
    'weekly' => (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0],
    'monthly' => (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0]
];

// Ensure stats objects exist
$daily = $activityStats->daily ?? (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0];
$weekly = $activityStats->weekly ?? (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0];
$monthly = $activityStats->monthly ?? (object) ['workOrdersCreated' => 0, 'statusChanges' => 0, 'paymentProofsRecorded' => 0, 'shippingSlipsFull' => 0, 'shippingSlipsPartial' => 0];
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
</style>

<div class="resumen-container">
    <h2>
        <i class="fas fa-chart-bar"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_TITLE'); ?>
    </h2>
    <p class="text-muted">
        <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_DESCRIPTION'); ?>
    </p>

    <!-- Work Orders Activities Section -->
    <div class="resumen-section">
        <h3>
            <i class="fas fa-clipboard-list"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_WORK_ORDERS_ACTIVITIES'); ?>
        </h3>
        
        <table class="resumen-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PERIOD'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_ORDERS_CREATED'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_STATUS_CHANGES'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PAYMENT_PROOFS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="period-cell"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_DAILY'); ?></td>
                    <td>
                        <span class="stat-value"><?php echo number_format($daily->workOrdersCreated ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($daily->statusChanges ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($daily->paymentProofsRecorded ?? 0); ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="period-cell"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_WEEKLY'); ?></td>
                    <td>
                        <span class="stat-value"><?php echo number_format($weekly->workOrdersCreated ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($weekly->statusChanges ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($weekly->paymentProofsRecorded ?? 0); ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="period-cell"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONTHLY'); ?></td>
                    <td>
                        <span class="stat-value"><?php echo number_format($monthly->workOrdersCreated ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($monthly->statusChanges ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($monthly->paymentProofsRecorded ?? 0); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Shipping Slips Section -->
    <div class="resumen-section">
        <h3>
            <i class="fas fa-shipping-fast"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_SLIPS'); ?>
        </h3>
        
        <table class="resumen-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_PERIOD'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_FULL'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_PARTIAL'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_SHIPPING_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="period-cell"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_DAILY'); ?></td>
                    <td>
                        <span class="stat-value"><?php echo number_format($daily->shippingSlipsFull ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($daily->shippingSlipsPartial ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format(($daily->shippingSlipsFull ?? 0) + ($daily->shippingSlipsPartial ?? 0)); ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="period-cell"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_WEEKLY'); ?></td>
                    <td>
                        <span class="stat-value"><?php echo number_format($weekly->shippingSlipsFull ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($weekly->shippingSlipsPartial ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format(($weekly->shippingSlipsFull ?? 0) + ($weekly->shippingSlipsPartial ?? 0)); ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="period-cell"><?php echo Text::_('COM_ORDENPRODUCCION_RESUMEN_MONTHLY'); ?></td>
                    <td>
                        <span class="stat-value"><?php echo number_format($monthly->shippingSlipsFull ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format($monthly->shippingSlipsPartial ?? 0); ?></span>
                    </td>
                    <td>
                        <span class="stat-value"><?php echo number_format(($monthly->shippingSlipsFull ?? 0) + ($monthly->shippingSlipsPartial ?? 0)); ?></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
