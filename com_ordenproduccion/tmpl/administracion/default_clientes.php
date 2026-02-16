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

$clients = $this->clients ?? [];

function safeEscape($value, $default = '')
{
    if (is_string($value) && $value !== '') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}
?>

<style>
#com-op-clientes.clientes-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.clientes-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.clientes-title {
    color: #495057;
    font-size: 24px;
    font-weight: 600;
    margin: 0;
}

.clientes-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.clientes-table th,
.clientes-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.clientes-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.clientes-table tbody tr:hover {
    background: #f8f9fa;
}

.clientes-table .col-client-name {
    min-width: 200px;
}

.clientes-table .col-nit {
    min-width: 120px;
}

.clientes-table .col-order-count {
    width: 100px;
    text-align: center;
}

.clientes-table .col-total-value {
    min-width: 140px;
    text-align: right;
    font-weight: 600;
}

.clientes-summary {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    font-weight: 600;
    color: #495057;
}

.clientes-empty {
    padding: 40px;
    text-align: center;
    color: #6c757d;
    font-size: 16px;
}
</style>

<div id="com-op-clientes" class="clientes-section">
    <div class="clientes-header">
        <h2 class="clientes-title">
            <i class="fas fa-users"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_TAB_CLIENTES'); ?>
        </h2>
        <p class="text-muted mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_DESC'); ?>
        </p>
    </div>

    <?php if (!empty($clients)) : ?>
        <div class="table-responsive">
            <table class="clientes-table">
                <thead>
                    <tr>
                        <th class="col-client-name"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_CLIENT_NAME'); ?></th>
                        <th class="col-nit"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_NIT'); ?></th>
                        <th class="col-order-count"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_ORDER_COUNT'); ?></th>
                        <th class="col-total-value"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_TOTAL_VALUE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grandTotal = 0;
                    $totalOrders = 0;
                    foreach ($clients as $client) :
                        $totalVal = (float) ($client->total_invoice_value ?? 0);
                        $orderCount = (int) ($client->order_count ?? 0);
                        $grandTotal += $totalVal;
                        $totalOrders += $orderCount;
                    ?>
                        <tr>
                            <td class="col-client-name"><?php echo safeEscape($client->client_name ?? ''); ?></td>
                            <td class="col-nit"><?php echo safeEscape($client->nit ?? '—'); ?></td>
                            <td class="col-order-count"><?php echo $orderCount; ?></td>
                            <td class="col-total-value">Q.<?php echo number_format($totalVal, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="clientes-summary">
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_TOTAL_CLIENTS'); ?>: <?php echo count($clients); ?>
            &nbsp;|&nbsp;
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_TOTAL_ORDERS'); ?>: <?php echo $totalOrders; ?>
            &nbsp;|&nbsp;
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_GRAND_TOTAL'); ?>: Q.<?php echo number_format($grandTotal, 2); ?>
        </div>
    <?php else : ?>
        <div class="clientes-empty">
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_NO_CLIENTS'); ?>
        </div>
    <?php endif; ?>
</div>
