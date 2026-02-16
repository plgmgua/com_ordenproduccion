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
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$reportWorkOrders = $this->reportWorkOrders ?? [];
$reportDateFrom = $this->reportDateFrom ?? '';
$reportDateTo = $this->reportDateTo ?? '';
$reportClient = $this->reportClient ?? '';
$reportNit = $this->reportNit ?? '';

$tokenParam = Session::getFormToken() . '=1';
$suggestClientsUrl = Route::_(
    'index.php?option=com_ordenproduccion&task=ajax.suggestReportClients&format=json&' . $tokenParam,
    false
);
$suggestNitsUrl = Route::_(
    'index.php?option=com_ordenproduccion&task=ajax.suggestReportNits&format=json&' . $tokenParam,
    false
);

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

.reportes-client-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.reportes-client-wrap input {
    min-width: 260px;
}

.reportes-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin-top: 2px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    max-height: 220px;
    overflow-y: auto;
    z-index: 100;
    list-style: none;
    padding: 0;
    margin: 0;
}

.reportes-suggestions li {
    padding: 10px 12px;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 1px solid #eee;
}

.reportes-suggestions li:last-child {
    border-bottom: none;
}

.reportes-suggestions li:hover,
.reportes-suggestions li.reportes-suggestion-active {
    background: #e7f3ff;
}

.reportes-suggestions-empty {
    padding: 12px;
    color: #6c757d;
    font-size: 13px;
}

.reportes-client-hint,
.reportes-nit-hint {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}

.reportes-nit-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.reportes-nit-wrap input {
    min-width: 200px;
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
            <label class="reportes-client-wrap">
                <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_CLIENT'); ?>
                <input type="text" 
                       name="filter_report_client" 
                       id="filter_report_client" 
                       value="<?php echo safeEscape($reportClient); ?>" 
                       autocomplete="off"
                       placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_ALL_CLIENTS'); ?>"
                       aria-describedby="reportes-client-hint" />
                <span id="reportes-client-hint" class="reportes-client-hint"><?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_CLIENT_HINT'); ?></span>
                <ul id="reportes-suggestions" class="reportes-suggestions" role="listbox" aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_CLIENT'); ?>" hidden></ul>
            </label>
            <label class="reportes-nit-wrap">
                <?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_NIT'); ?>
                <input type="text" 
                       name="filter_report_nit" 
                       id="filter_report_nit" 
                       value="<?php echo safeEscape($reportNit); ?>" 
                       autocomplete="off"
                       placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_ALL_NITS'); ?>"
                       aria-describedby="reportes-nit-hint" />
                <span id="reportes-nit-hint" class="reportes-nit-hint"><?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_NIT_HINT'); ?></span>
                <ul id="reportes-nit-suggestions" class="reportes-suggestions" role="listbox" aria-label="<?php echo Text::_('COM_ORDENPRODUCCION_REPORTES_NIT'); ?>" hidden></ul>
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

<script>
(function() {
    var dateFrom = document.querySelector('input[name="filter_report_date_from"]');
    var dateTo = document.querySelector('input[name="filter_report_date_to"]');
    var suggestClientsUrl = <?php echo json_encode($suggestClientsUrl); ?>;
    var suggestNitsUrl = <?php echo json_encode($suggestNitsUrl); ?>;

    var msgSelectDates = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_REPORTES_SELECT_DATES_FIRST')); ?>;
    var msgNoClientsInRange = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_REPORTES_NO_CLIENTS_IN_RANGE')); ?>;
    var msgNoClientsMatch = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_REPORTES_NO_CLIENTS_MATCH')); ?>;
    var msgSuggestionsError = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_REPORTES_SUGGESTIONS_ERROR')); ?>;
    var msgNoNitsInRange = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_REPORTES_NO_NITS_IN_RANGE')); ?>;
    var msgNoNitsMatch = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_REPORTES_NO_NITS_MATCH')); ?>;

    function setupSuggest(input, list, suggestUrl, isEmptyMsg, emptyInRangeMsg, noMatchMsg, responseKey) {
        if (!input || !list) return;
        var debounceTimer = null;
        var activeIndex = -1;

        function showSuggestions(items, isEmpty, isError) {
            list.innerHTML = '';
            list.hidden = true;
            if (isEmpty && items.length === 0) {
                var li = document.createElement('li');
                li.className = 'reportes-suggestions-empty';
                li.textContent = msgSelectDates;
                list.appendChild(li);
                list.hidden = false;
                return;
            }
            if (isError) {
                var errLi = document.createElement('li');
                errLi.className = 'reportes-suggestions-empty';
                errLi.textContent = msgSuggestionsError;
                list.appendChild(errLi);
                list.hidden = false;
                return;
            }
            if (items.length === 0) {
                var emptyLi = document.createElement('li');
                emptyLi.className = 'reportes-suggestions-empty';
                emptyLi.textContent = emptyInRangeMsg;
                list.appendChild(emptyLi);
                list.hidden = false;
                return;
            }
            items.forEach(function(name, i) {
                var li = document.createElement('li');
                li.textContent = name;
                li.setAttribute('role', 'option');
                li.addEventListener('click', function() {
                    input.value = name;
                    list.hidden = true;
                    activeIndex = -1;
                });
                list.appendChild(li);
            });
            list.hidden = false;
            activeIndex = -1;
        }

        function fetchSuggestions() {
            var from = (dateFrom && dateFrom.value) ? dateFrom.value.trim() : '';
            var to = (dateTo && dateTo.value) ? dateTo.value.trim() : '';
            var q = input.value.trim();
            if (!from || !to) {
                showSuggestions([], true, false);
                return;
            }
            var url = suggestUrl + '&date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to) + '&q=' + encodeURIComponent(q);
            fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && Array.isArray(data[responseKey])) {
                        showSuggestions(data[responseKey], false, false);
                    } else {
                        showSuggestions([], false, true);
                    }
                })
                .catch(function() {
                    showSuggestions([], false, true);
                });
        }

        function onInput() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchSuggestions, 220);
        }

        input.addEventListener('focus', function() {
            if (dateFrom && dateFrom.value && dateTo && dateTo.value) {
                fetchSuggestions();
            } else {
                showSuggestions([], true, false);
            }
        });
        input.addEventListener('input', onInput);
        input.addEventListener('keydown', function(e) {
            var items = list.querySelectorAll('li:not(.reportes-suggestions-empty)');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                items.forEach(function(li, i) {
                    li.classList.toggle('reportes-suggestion-active', i === activeIndex);
                    if (i === activeIndex) li.scrollIntoView({ block: 'nearest' });
                });
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, -1);
                items.forEach(function(li, i) {
                    li.classList.toggle('reportes-suggestion-active', i === activeIndex);
                    if (i === activeIndex) li.scrollIntoView({ block: 'nearest' });
                });
                return;
            }
            if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                input.value = items[activeIndex].textContent;
                list.hidden = true;
                activeIndex = -1;
                return;
            }
            if (e.key === 'Escape') {
                list.hidden = true;
                activeIndex = -1;
            }
        });

        document.addEventListener('click', function(e) {
            if (list && !list.hidden && input && !input.contains(e.target) && !list.contains(e.target)) {
                list.hidden = true;
            }
        });
    }

    setupSuggest(
        document.getElementById('filter_report_client'),
        document.getElementById('reportes-suggestions'),
        suggestClientsUrl,
        msgSelectDates,
        msgNoClientsInRange,
        msgNoClientsMatch,
        'clients'
    );
    setupSuggest(
        document.getElementById('filter_report_nit'),
        document.getElementById('reportes-nit-suggestions'),
        suggestNitsUrl,
        msgSelectDates,
        msgNoNitsInRange,
        msgNoNitsMatch,
        'nits'
    );
})();
</script>
