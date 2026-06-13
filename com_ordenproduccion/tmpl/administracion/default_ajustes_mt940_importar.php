<?php
/**
 * Ajustes > MT-940 > Importar datos: initial IMAP import, clear, manual upload.
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
$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$mt940SchemaOk  = !empty($this->ajustesMt940SchemaOk);
$mt940Accounts  = isset($this->ajustesMt940BankAccountOptions) && \is_array($this->ajustesMt940BankAccountOptions)
    ? $this->ajustesMt940BankAccountOptions : [];
$mt940ImportUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.importMt940File&format=json', false);
$mt940InitialUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.runMt940InitialImport&format=json', false);
$mt940ClearUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.clearMt940ImportedData&format=json', false);
$mt940Token      = Session::getFormToken();
?>
<p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_SUBTAB_INTRO'); ?></p>

<?php if (!$mt940SchemaOk) : ?>
    <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'); ?></div>
<?php elseif ($mt940Accounts === []) : ?>
    <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_NO_ACCOUNTS'); ?></div>
<?php else : ?>
    <div class="card mb-3 border-primary">
        <div class="card-body py-3">
            <h3 class="h6 mb-2"><i class="fas fa-inbox"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_TITLE'); ?></h3>
            <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_DESC'); ?></p>
            <p class="small text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_DEDUP_NOTICE'); ?></p>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-primary btn-sm" id="btn-mt940-initial-import" data-url="<?php echo htmlspecialchars($mt940InitialUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-cloud-download-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_BTN'); ?>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-mt940-clear-imported"
                        data-url="<?php echo htmlspecialchars($mt940ClearUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        data-confirm="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-trash-alt"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_BTN'); ?>
                </button>
            </div>
            <div id="mt940-initial-result" class="d-none mt-2">
                <div id="mt940-initial-alert" class="alert mb-0" role="alert"></div>
                <pre id="mt940-initial-json" class="bg-light border rounded p-2 small mb-0 mt-2" style="max-height: 180px; overflow: auto;"></pre>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <h3 class="h6 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_TITLE'); ?></h3>
            <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_DESC'); ?></p>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="file" class="form-control form-control-sm" id="mt940-import-file" accept=".txt,.TXT,text/plain" style="max-width: 320px;">
                <button type="button" class="btn btn-primary btn-sm" id="btn-mt940-import" data-url="<?php echo htmlspecialchars($mt940ImportUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-file-import"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_BTN'); ?>
                </button>
            </div>
            <div id="mt940-import-result" class="d-none mt-2">
                <div id="mt940-import-alert" class="alert mb-0" role="alert"></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    var token = <?php echo json_encode($mt940Token); ?>;
    var runningInitial = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_INITIAL_IMPORT_RUNNING')); ?>;
    var clearingLabel = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_RUNNING')); ?>;
    var initialBtn = document.getElementById('btn-mt940-initial-import');
    var clearBtn = document.getElementById('btn-mt940-clear-imported');
    var initialResult = document.getElementById('mt940-initial-result');
    var initialAlert = document.getElementById('mt940-initial-alert');
    var initialJson = document.getElementById('mt940-initial-json');

    if (initialBtn) {
        initialBtn.addEventListener('click', function () {
            var url = initialBtn.getAttribute('data-url');
            if (!url) {
                return;
            }
            var orig = initialBtn.innerHTML;
            initialBtn.disabled = true;
            initialBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningInitial;
            initialResult.classList.remove('d-none');
            initialAlert.className = 'alert alert-info mb-0';
            initialAlert.textContent = runningInitial;
            initialJson.textContent = '';

            var body = new URLSearchParams();
            body.append(token, '1');

            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var ok = !!data.success;
                    initialAlert.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                    initialAlert.textContent = data.message || (ok ? 'OK' : 'Error');
                    initialJson.textContent = JSON.stringify(data, null, 2);
                    if (ok && (data.data?.files_imported > 0 || data.data?.transactions_imported > 0)) {
                        window.setTimeout(function () { window.location.reload(); }, 1500);
                    }
                })
                .catch(function (err) {
                    initialAlert.className = 'alert alert-danger mb-0';
                    initialAlert.textContent = err && err.message ? err.message : 'Error';
                })
                .finally(function () {
                    initialBtn.disabled = false;
                    initialBtn.innerHTML = orig;
                });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var url = clearBtn.getAttribute('data-url');
            var confirmMsg = clearBtn.getAttribute('data-confirm') || 'Clear all imported MT-940 data?';
            if (!url || !window.confirm(confirmMsg)) {
                return;
            }
            var orig = clearBtn.innerHTML;
            clearBtn.disabled = true;
            if (initialBtn) {
                initialBtn.disabled = true;
            }
            clearBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + clearingLabel;
            initialResult.classList.remove('d-none');
            initialAlert.className = 'alert alert-info mb-0';
            initialAlert.textContent = clearingLabel;
            initialJson.textContent = '';

            var body = new URLSearchParams();
            body.append(token, '1');

            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var ok = !!data.success;
                    initialAlert.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                    initialAlert.textContent = data.message || (ok ? 'OK' : 'Error');
                    initialJson.textContent = JSON.stringify(data, null, 2);
                    if (ok) {
                        window.setTimeout(function () { window.location.reload(); }, 1200);
                    }
                })
                .catch(function (err) {
                    initialAlert.className = 'alert alert-danger mb-0';
                    initialAlert.textContent = err && err.message ? err.message : 'Error';
                })
                .finally(function () {
                    clearBtn.disabled = false;
                    clearBtn.innerHTML = orig;
                    if (initialBtn) {
                        initialBtn.disabled = false;
                    }
                });
        });
    }
})();
(function () {
    var token = <?php echo json_encode($mt940Token); ?>;
    var btn = document.getElementById('btn-mt940-import');
    var fileInput = document.getElementById('mt940-import-file');
    var resultBox = document.getElementById('mt940-import-result');
    var alertBox = document.getElementById('mt940-import-alert');
    if (!btn || !fileInput) {
        return;
    }
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url');
        if (!url || !fileInput.files || !fileInput.files[0]) {
            return;
        }
        var fd = new FormData();
        fd.append('mt940_file', fileInput.files[0]);
        fd.append(token, '1');
        btn.disabled = true;
        resultBox.classList.remove('d-none');
        alertBox.className = 'alert alert-info mb-0';
        alertBox.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_RUNNING')); ?>;
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ok = !!data.success;
                alertBox.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                alertBox.textContent = data.message || (ok ? 'OK' : 'Error');
                if (ok && !data.data?.skipped) {
                    window.setTimeout(function () { window.location.reload(); }, 1200);
                }
            })
            .catch(function (err) {
                alertBox.className = 'alert alert-danger mb-0';
                alertBox.textContent = err && err.message ? err.message : 'Error';
            })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
