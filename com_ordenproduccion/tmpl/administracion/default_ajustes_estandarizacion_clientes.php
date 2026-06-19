<?php
/**
 * Ajustes > Estandarización de Clientes: analyze and unify duplicate client names.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('form.csrf');

$app = Factory::getApplication();
$user = Factory::getUser();
$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$canApply = !$user->guest && $user->authorise('core.admin');
$analyzeUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.analyzeClientStandardization&format=json', false);
$applyUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.applyClientStandardization', false);
$token = Session::getFormToken();
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-user-check"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_DESC'); ?></p>

        <?php if (!$canApply) : ?>
            <div class="alert alert-warning mb-0">
                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_SUPER_USER_ONLY'); ?>
            </div>
        <?php else : ?>
            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-6">
                    <label for="client-std-search" class="form-label fw-bold">
                        <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_SEARCH_LABEL'); ?>
                    </label>
                    <input type="text" id="client-std-search" class="form-control"
                           placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_CLIENT_STD_SEARCH_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"
                           value="">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_SEARCH_HELP'); ?></div>
                </div>
                <div class="col-md-auto">
                    <button type="button" class="btn btn-primary" id="client-std-analyze-btn">
                        <i class="fas fa-search"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_ANALYZE_BTN'); ?>
                    </button>
                </div>
            </div>

            <div id="client-std-loading" class="alert alert-info d-none">
                <i class="fas fa-spinner fa-spin"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_ANALYZING'); ?>
            </div>
            <div id="client-std-error" class="alert alert-danger d-none"></div>
            <div id="client-std-empty" class="alert alert-secondary d-none">
                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_NO_VARIANTS'); ?>
            </div>

            <div id="client-std-results" class="d-none">
                <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_VARIANTS_TITLE'); ?></h3>
                <p class="small text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_VARIANTS_HELP'); ?></p>

                <div class="table-responsive mb-4">
                    <table class="table table-sm table-striped table-bordered" id="client-std-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:3rem;"></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_NAME'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_NIT'); ?></th>
                                <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_ORDENES'); ?></th>
                                <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_INVOICES'); ?></th>
                                <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_PAYMENTS'); ?></th>
                                <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_OTHER'); ?></th>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_COL_SAMPLES'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <form action="<?php echo htmlspecialchars($applyUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" id="client-std-apply-form">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <div id="client-std-form-fields"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="std_target_name" class="form-label fw-bold">
                                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_TARGET_NAME'); ?>
                            </label>
                            <input type="text" name="std_target_name" id="std_target_name" class="form-control" required>
                            <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_TARGET_NAME_HELP'); ?></div>
                        </div>
                        <div class="col-md-4">
                            <label for="std_target_nit" class="form-label">
                                <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_TARGET_NIT'); ?>
                            </label>
                            <input type="text" name="std_target_nit" id="std_target_nit" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success" id="client-std-apply-btn" disabled>
                            <i class="fas fa-check"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_APPLY_BTN'); ?>
                        </button>
                    </div>
                    <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_STD_APPLY_WARNING'); ?></p>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canApply) : ?>
<script>
(function () {
    var analyzeUrl = <?php echo json_encode($analyzeUrl); ?>;
    var token = <?php echo json_encode($token); ?>;
    var variantsData = [];

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function show(el) { el.classList.remove('d-none'); }
    function hide(el) { el.classList.add('d-none'); }

    function otherCount(v) {
        return (v.quotations || 0) + (v.opening_balance || 0) + (v.client_balance || 0)
            + (v.pliego_quotes || 0) + (v.fel_receptor || 0);
    }

    function rebuildFormFields() {
        var container = document.getElementById('client-std-form-fields');
        container.innerHTML = '';
        document.querySelectorAll('.client-std-source-cb:checked').forEach(function (cb) {
            var idx = parseInt(cb.getAttribute('data-idx'), 10);
            var v = variantsData[idx];
            if (!v) return;
            var nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'std_sources[' + idx + '][client_name]';
            nameInput.value = v.client_name;
            container.appendChild(nameInput);
        });
    }

    function updateApplyState() {
        var checked = document.querySelectorAll('.client-std-source-cb:checked').length;
        var target = document.getElementById('std_target_name').value.trim();
        document.getElementById('client-std-apply-btn').disabled = checked < 1 || target === '';
        rebuildFormFields();
    }

    function renderVariants(data) {
        variantsData = data.variants || [];
        var tbody = document.querySelector('#client-std-table tbody');
        tbody.innerHTML = '';

        if (!variantsData.length) {
            hide(document.getElementById('client-std-results'));
            show(document.getElementById('client-std-empty'));
            return;
        }

        hide(document.getElementById('client-std-empty'));
        show(document.getElementById('client-std-results'));

        variantsData.forEach(function (v, idx) {
            var tr = document.createElement('tr');
            var nits = (v.nit_values || []).join(', ') || '—';
            var samples = (v.sample_ordenes || []).join(', ') || '—';
            var other = otherCount(v);
            tr.innerHTML =
                '<td class="text-center"><input type="checkbox" class="form-check-input client-std-source-cb" data-idx="' + idx + '" checked></td>' +
                '<td><code>' + esc(v.client_name) + '</code></td>' +
                '<td>' + esc(nits) + '</td>' +
                '<td class="text-end">' + (v.ordenes || 0) + '</td>' +
                '<td class="text-end">' + (v.invoices || 0) + '</td>' +
                '<td class="text-end">' + (v.payment_proofs || 0) + '</td>' +
                '<td class="text-end">' + other + '</td>' +
                '<td class="small">' + esc(samples) + '</td>';
            tbody.appendChild(tr);
        });

        document.querySelectorAll('.client-std-source-cb').forEach(function (cb) {
            cb.addEventListener('change', updateApplyState);
        });

        var longest = variantsData.reduce(function (best, v) {
            return (v.ordenes || 0) > (best.ordenes || 0) ? v : best;
        }, variantsData[0]);
        document.getElementById('std_target_name').value = longest.client_name || '';
        if ((longest.nit_values || []).length === 1) {
            document.getElementById('std_target_nit').value = longest.nit_values[0];
        }
        updateApplyState();
    }

    document.getElementById('client-std-analyze-btn').addEventListener('click', function () {
        var search = document.getElementById('client-std-search').value.trim();
        hide(document.getElementById('client-std-error'));
        hide(document.getElementById('client-std-empty'));
        hide(document.getElementById('client-std-results'));

        if (search.length < 2) {
            var err = document.getElementById('client-std-error');
            err.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CLIENT_STD_SEARCH_MIN')); ?>;
            show(err);
            return;
        }

        show(document.getElementById('client-std-loading'));
        fetch(analyzeUrl + '&' + encodeURIComponent(token) + '=1&search=' + encodeURIComponent(search), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                hide(document.getElementById('client-std-loading'));
                if (!json.success) {
                    var errBox = document.getElementById('client-std-error');
                    errBox.textContent = json.message || 'Error';
                    show(errBox);
                    return;
                }
                renderVariants(json.data || {});
            })
            .catch(function (e) {
                hide(document.getElementById('client-std-loading'));
                var errBox = document.getElementById('client-std-error');
                errBox.textContent = e.message || 'Error';
                show(errBox);
            });
    });

    document.getElementById('std_target_name').addEventListener('input', updateApplyState);

    document.getElementById('client-std-apply-form').addEventListener('submit', function (e) {
        var target = document.getElementById('std_target_name').value.trim();
        var checked = document.querySelectorAll('.client-std-source-cb:checked').length;
        if (!target || checked < 1) {
            e.preventDefault();
            return;
        }
        if (!window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CLIENT_STD_CONFIRM')); ?>)) {
            e.preventDefault();
        }
    });

    document.getElementById('client-std-search').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('client-std-analyze-btn').click();
        }
    });
})();
</script>
<?php endif; ?>
