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

$clients = $this->clients ?? [];
$canMerge = !empty($this->canMergeClients);
$canInitialize = !empty($this->canInitializeOpeningBalances);
$clientesOrdering = $this->clientesOrdering ?? 'name';
$clientesDirection = $this->clientesDirection ?? 'asc';
$clientesHideZero = !empty($this->clientesHideZero);

function clientesSortUrl($col, $currentOrdering, $currentDirection, $currentHideZero) {
    $dir = ($col === $currentOrdering && $currentDirection === 'asc') ? 'desc' : 'asc';
    $params = ['option' => 'com_ordenproduccion', 'view' => 'administracion', 'tab' => 'clientes'];
    $params['filter_clientes_ordering'] = $col;
    $params['filter_clientes_direction'] = $dir;
    if ($currentHideZero) {
        $params['filter_clientes_hide_zero'] = 1;
    }
    return \Joomla\CMS\Router\Route::_('index.php?' . http_build_query($params));
}

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

.clientes-table .col-total-value,
.clientes-table .col-compras,
.clientes-table .col-saldo {
    min-width: 120px;
    text-align: right;
}

.clientes-table .col-saldo {
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

.clientes-merge-actions {
    margin-bottom: 20px;
}

.clientes-table .col-select {
    width: 40px;
    text-align: center;
}

#mergeModal .merge-target-option {
    cursor: pointer;
    padding: 12px 16px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: border-color 0.2s, background 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
}

#mergeModal .merge-target-option::before {
    content: '';
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border: 2px solid #adb5bd;
    border-radius: 50%;
    background: #fff;
    transition: border-color 0.2s, background 0.2s;
}

#mergeModal .merge-target-option:hover {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.06);
}

#mergeModal .merge-target-option:hover::before {
    border-color: #667eea;
}

#mergeModal .merge-target-option.selected {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.12);
}

#mergeModal .merge-target-option.selected::before {
    border-color: #667eea;
    background: #667eea;
    box-shadow: inset 0 0 0 3px #fff;
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
        <div class="clientes-filter-bar mb-3 d-flex flex-wrap align-items-center gap-3">
            <?php
            $baseParams = ['option' => 'com_ordenproduccion', 'view' => 'administracion', 'tab' => 'clientes', 'filter_clientes_ordering' => $clientesOrdering, 'filter_clientes_direction' => $clientesDirection];
            $hideZeroUrl = Route::_('index.php?' . http_build_query($baseParams + ['filter_clientes_hide_zero' => 1]));
            $showAllUrl = Route::_('index.php?' . http_build_query($baseParams));
            ?>
            <a href="<?php echo $clientesHideZero ? $showAllUrl : $hideZeroUrl; ?>"
               class="btn btn-sm <?php echo $clientesHideZero ? 'btn-primary' : 'btn-outline-secondary'; ?>"
               title="<?php echo $clientesHideZero ? Text::_('COM_ORDENPRODUCCION_CLIENTES_SHOW_ALL_TIP') : Text::_('COM_ORDENPRODUCCION_CLIENTES_HIDE_ZERO_TIP'); ?>">
                <i class="fas fa-filter"></i>
                <?php echo $clientesHideZero ? Text::_('COM_ORDENPRODUCCION_CLIENTES_SHOW_ALL') : Text::_('COM_ORDENPRODUCCION_CLIENTES_HIDE_ZERO'); ?>
            </a>
        </div>
        <?php if (($canMerge || $canInitialize) && !empty($clients)) : ?>
        <div class="clientes-merge-actions mt-3 d-flex flex-wrap gap-2">
            <?php if ($canMerge) : ?>
            <button type="button" class="btn btn-primary" id="btn-merge-clients" disabled title="<?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_SELECT_TIP'); ?>">
                <i class="fas fa-compress-arrows-alt"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_BTN'); ?>
            </button>
            <?php endif; ?>
            <?php if ($canInitialize) : ?>
            <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.initializeOpeningBalances'); ?>" class="d-inline">
                <?php echo HTMLHelper::_('form.token'); ?>
                <button type="submit" class="btn btn-outline-primary" title="<?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_INITIALIZE_TIP'); ?>">
                    <i class="fas fa-calculator"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_INITIALIZE_BTN'); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($clients)) : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.mergeClients'); ?>" id="clientes-merge-form" style="display:none;">
            <?php echo HTMLHelper::_('form.token'); ?>
            <div id="merge-form-fields"></div>
        </form>
        <div class="table-responsive">
                <table class="clientes-table">
                    <thead>
                        <tr>
                            <?php if ($canMerge) : ?>
                            <th class="col-select"><input type="checkbox" id="select-all-clients" title="<?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_SELECT_ALL'); ?>"></th>
                            <?php endif; ?>
                            <th class="col-client-name"><a href="<?php echo clientesSortUrl('name', $clientesOrdering, $clientesDirection, $clientesHideZero); ?>" class="text-decoration-none<?php echo $clientesOrdering === 'name' ? ' fw-bold' : ''; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_CLIENT_NAME'); ?><?php if ($clientesOrdering === 'name') : ?><i class="fas fa-sort-<?php echo $clientesDirection === 'asc' ? 'down' : 'up'; ?> ms-1"></i><?php endif; ?></a></th>
                            <th class="col-nit"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_NIT'); ?></th>
                            <th class="col-order-count"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_ORDER_COUNT'); ?></th>
                            <th class="col-compras"><a href="<?php echo clientesSortUrl('compras', $clientesOrdering, $clientesDirection, $clientesHideZero); ?>" class="text-decoration-none<?php echo $clientesOrdering === 'compras' ? ' fw-bold' : ''; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_COMPRAS'); ?><?php if ($clientesOrdering === 'compras') : ?><i class="fas fa-sort-<?php echo $clientesDirection === 'asc' ? 'down' : 'up'; ?> ms-1"></i><?php endif; ?></a></th>
                            <th class="col-saldo"><a href="<?php echo clientesSortUrl('saldo', $clientesOrdering, $clientesDirection, $clientesHideZero); ?>" class="text-decoration-none<?php echo $clientesOrdering === 'saldo' ? ' fw-bold' : ''; ?>"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_COL_SALDO'); ?><?php if ($clientesOrdering === 'saldo') : ?><i class="fas fa-sort-<?php echo $clientesDirection === 'asc' ? 'down' : 'up'; ?> ms-1"></i><?php endif; ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalSaldo = 0;
                        $totalOrders = 0;
                        $totalCompras = 0;
                        foreach ($clients as $idx => $client) :
                            $orderCount = (int) ($client->order_count ?? 0);
                            $saldo = (float) ($client->saldo ?? 0);
                            $compras = (float) ($client->compras ?? 0);
                            $totalSaldo += $saldo;
                            $totalOrders += $orderCount;
                            $totalCompras += $compras;
                            $cn = $client->client_name ?? '';
                            $nit = $client->nit ?? '';
                        ?>
                            <tr>
                                <?php if ($canMerge) : ?>
                                <td class="col-select">
                                    <input type="checkbox" class="client-merge-cb" value=""
                                        data-client-name="<?php echo safeEscape($cn); ?>"
                                        data-nit="<?php echo safeEscape($nit); ?>">
                                </td>
                                <?php endif; ?>
                                <td class="col-client-name"><?php echo safeEscape($cn); ?></td>
                                <td class="col-nit"><?php echo safeEscape($nit ?: '—'); ?></td>
                                <td class="col-order-count"><?php echo $orderCount; ?></td>
                                <td class="col-compras">Q.<?php echo number_format($compras, 2); ?></td>
                                <td class="col-saldo">Q.<?php echo number_format($saldo, 2); ?></td>
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
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_TOTAL_COMPRAS'); ?>: Q.<?php echo number_format($totalCompras, 2); ?>
            &nbsp;|&nbsp;
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_TOTAL_SALDO'); ?>: Q.<?php echo number_format($totalSaldo, 2); ?>
        </div>
    <?php else : ?>
        <div class="clientes-empty">
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_NO_CLIENTS'); ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($canMerge && !empty($clients)) : ?>
<!-- Merge modal -->
<div class="modal fade" id="mergeModal" tabindex="-1" role="dialog" aria-labelledby="mergeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mergeModalLabel"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_MODAL_TITLE'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_CHOOSE_TARGET'); ?></p>
                <div id="merge-target-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-primary" id="merge-confirm-btn" disabled><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTES_MERGE_CONFIRM'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var selectAll = document.getElementById('select-all-clients');
    var checkboxes = document.querySelectorAll('.client-merge-cb');
    var mergeBtn = document.getElementById('btn-merge-clients');
    var form = document.getElementById('clientes-merge-form');
    var formFields = document.getElementById('merge-form-fields');
    var mergeModal = document.getElementById('mergeModal');
    var mergeTargetList = document.getElementById('merge-target-list');
    var mergeConfirmBtn = document.getElementById('merge-confirm-btn');
    var selectedTarget = null;

    function updateMergeBtn() {
        var checked = document.querySelectorAll('.client-merge-cb:checked');
        mergeBtn.disabled = checked.length < 2;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateMergeBtn();
        });
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
            updateMergeBtn();
        });
    });

    if (mergeBtn) {
        mergeBtn.addEventListener('click', function() {
            var checked = document.querySelectorAll('.client-merge-cb:checked');
            if (checked.length < 2) return;
            mergeTargetList.innerHTML = '';
            selectedTarget = null;
            var selected = [];
            checked.forEach(function(cb) {
                selected.push({
                    name: cb.getAttribute('data-client-name') || '',
                    nit: cb.getAttribute('data-nit') || ''
                });
            });
            selected.forEach(function(s, i) {
                var div = document.createElement('div');
                div.className = 'merge-target-option';
                div.dataset.name = s.name;
                div.dataset.nit = s.nit;
                div.innerHTML = '<strong>' + (s.name || '—') + '</strong>' + (s.nit ? ' <span class="text-muted">(' + s.nit + ')</span>' : '');
                div.addEventListener('click', function() {
                    document.querySelectorAll('#merge-target-list .merge-target-option').forEach(function(o) { o.classList.remove('selected'); });
                    div.classList.add('selected');
                    selectedTarget = { name: div.dataset.name, nit: div.dataset.nit || '' };
                    mergeConfirmBtn.disabled = false;
                });
                mergeTargetList.appendChild(div);
            });
            mergeConfirmBtn.disabled = true;
            var bsModal = typeof bootstrap !== 'undefined' ? new bootstrap.Modal(mergeModal) : null;
            if (bsModal) bsModal.show(); else if (typeof jQuery !== 'undefined') jQuery(mergeModal).modal('show');
        });
    }

    if (mergeConfirmBtn && form && formFields) {
        mergeConfirmBtn.addEventListener('click', function() {
            if (!selectedTarget || !selectedTarget.name) return;
            mergeConfirmBtn.disabled = true;
            mergeConfirmBtn.textContent = '<?php echo addslashes(Text::_('JPROCESSING')); ?>';
            var checked = document.querySelectorAll('.client-merge-cb:checked');
            formFields.innerHTML = '';
            var idx = 0;
            checked.forEach(function(cb) {
                var name = cb.getAttribute('data-client-name') || '';
                var nit = cb.getAttribute('data-nit') || '';
                var in1 = document.createElement('input');
                in1.type = 'hidden';
                in1.name = 'merge_sources[' + idx + '][client_name]';
                in1.value = name;
                formFields.appendChild(in1);
                var in2 = document.createElement('input');
                in2.type = 'hidden';
                in2.name = 'merge_sources[' + idx + '][nit]';
                in2.value = nit;
                formFields.appendChild(in2);
                idx++;
            });
            var t1 = document.createElement('input');
            t1.type = 'hidden';
            t1.name = 'merge_target_name';
            t1.value = selectedTarget.name;
            formFields.appendChild(t1);
            var t2 = document.createElement('input');
            t2.type = 'hidden';
            t2.name = 'merge_target_nit';
            t2.value = selectedTarget.nit;
            formFields.appendChild(t2);
            form.submit();
        });
    }
})();
</script>
<?php endif; ?>
