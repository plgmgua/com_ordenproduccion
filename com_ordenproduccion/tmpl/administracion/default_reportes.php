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
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$reportWorkOrders = $this->reportWorkOrders ?? [];
$reportClients = $this->reportClients ?? [];
$reportDateFrom = $this->reportDateFrom ?? '';
$reportDateTo = $this->reportDateTo ?? '';
$reportClient = $this->reportClient ?? '';

function safeEscape($value, $default = '')
{
    if (is_string($value) && $value !== '') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}
?>

<style>
.reportes-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.reportes-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.reportes-title {
    color: #495057;
    font-size: 24px;
    font-weight: 600;
    margin: 0;
}

.reportes-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.reportes-filters label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-weight: 500;
    color: #495057;
}

.reportes-filters input,
.reportes-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.reportes-filters input[type="date"] {
    min-width: 160px;
}

.reportes-filters select {
    min-width: 220px;
}

.reportes-filters .filter-btn {
    background: #007cba;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: background 0.3s;
}

.reportes-filters .filter-btn:hover {
    background: #005a8b;
}

.reportes-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.reportes-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
}

.reportes-table td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
    font-size: 14px;
}

.reportes-table tbody tr:hover {
    background: #f8f9fa;
}

.reportes-table .col-work-order {
    font-weight: 600;
    color: #007cba;
}

.reportes-table .col-invoice-value {
    text-align: right;
    font-weight: 500;
    white-space: nowrap;
}

.reportes-empty {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
    font-size: 16px;
}

.reportes-summary {
    margin-top: 15px;
    padding: 12px 15px;
    background: #e7f3ff;
    border-radius: 6px;
    font-weight: 600;
    color: #004085;
}
</style>

<div class="reportes-section">
    <div class="reportes-header">
        <h2 class="reportes-title">
            <i class="fas fa-file-alt"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_TITLE'); ?>
        </h2>
    </div>

    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=reportes'); ?>" 
          method="get" class="reportes-filters-form">
        <input type="hidden" name="option" value="com_ordenproduccion" />
        <input type="hidden" name="view" value="administracion" />
        <input type="hidden" name="tab" value="reportes" />
        <div class="reportes-filters">
            <label>
                <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_DATE_FROM'); ?>
                <input type="date" name="filter_report_date_from" value="<?php echo safeEscape($reportDateFrom); ?>" />
            </label>
            <label>
                <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_DATE_TO'); ?>
                <input type="date" name="filter_report_date_to" value="<?php echo safeEscape($reportDateTo); ?>" />
            </label>
            <label>
                <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_CLIENT'); ?>
                <select name="filter_report_client">
                    <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_ALL_CLIENTS'); ?></option>
                    <?php foreach ($reportClients as $client) : ?>
                        <option value="<?php echo safeEscape($client); ?>" <?php echo $reportClient === $client ? 'selected="selected"' : ''; ?>>
                            <?php echo safeEscape($client); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="filter-btn">
                <i class="fas fa-search"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_GENERATE'); ?>
            </button>
        </div>
    </form>

    <?php if (!empty($reportWorkOrders)) : ?>
        <table class="reportes-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_COL_WORK_ORDER'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_COL_WORK_DESCRIPTION'); ?></th>
                    <th class="col-invoice-value"><?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_COL_INVOICE_VALUE'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalValue = 0;
                foreach ($reportWorkOrders as $row) :
                    $invoiceVal = isset($row->invoice_value) ? (float) $row->invoice_value : 0;
                    $totalValue += $invoiceVal;
                ?>
                    <tr>
                        <td class="col-work-order"><?php echo safeEscape($row->orden_de_trabajo ?? ''); ?></td>
                        <td><?php echo safeEscape($row->work_description ?? ''); ?></td>
                        <td class="col-invoice-value"><?php echo number_format($invoiceVal, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="reportes-summary">
            <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_TOTAL_ORDERS'); ?>: <?php echo count($reportWorkOrders); ?>
            &nbsp;|&nbsp;
            <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_TOTAL_VALUE'); ?>: <?php echo number_format($totalValue, 2); ?>
        </div>
    <?php else : ?>
        <div class="reportes-empty">
            <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_NO_RESULTS'); ?>
        </div>
    <?php endif; ?>
</div>
