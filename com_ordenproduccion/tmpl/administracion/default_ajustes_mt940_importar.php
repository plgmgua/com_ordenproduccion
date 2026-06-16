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
$mt940DateUrl    = Route::_('index.php?option=com_ordenproduccion&task=administracion.runMt940MailboxImportByDate&format=json', false);
$mt940ClearUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.clearMt940ImportedData&format=json', false);
$mt940CronSaveUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveMt940CronSettings', false);
$mt940Token      = Session::getFormToken();
$mt940CronLine   = isset($this->mt940CronCrontabLine) ? (string) $this->mt940CronCrontabLine : '';
$mt940CronKeyOk  = !empty($this->mt940CronKeyConfigured);
?>
<p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_IMPORT_SUBTAB_INTRO'); ?></p>

<div class="card mb-3 border-secondary">
    <div class="card-body py-3">
        <h3 class="h6 mb-2"><i class="fas fa-clock"></i> <?php echo Text::_('COM_ORDENPRODUCCION_MT940_CRON_TITLE'); ?></h3>
        <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_CRON_HELP'); ?></p>
        <form action="<?php echo htmlspecialchars($mt940CronSaveUrl, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="mb-3">
            <?php echo HTMLHelper::_('form.token'); ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small mb-0" for="mt940_cron_key"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_CRON_KEY_LABEL'); ?></label>
                    <input type="password" class="form-control form-control-sm" name="mt940_cron_key" id="mt940_cron_key" value=""
                           autocomplete="new-password"
                           placeholder="<?php echo htmlspecialchars($mt940CronKeyOk ? Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE_PLACEHOLDER_KEEP') : Text::_('COM_ORDENPRODUCCION_MT940_CRON_KEY_PLACEHOLDER_NEW'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($mt940CronKeyOk) : ?>
                        <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE_KEEP_HINT'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-save"></i> <?php echo Text::_('COM_ORDENPRODUCCION_MT940_CRON_SAVE_BTN'); ?>
                    </button>
                </div>
            </div>
        </form>
        <?php if ($mt940CronLine !== '') : ?>
            <p class="small text-muted mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_CRON_LINE_HELP'); ?></p>
            <pre class="small mb-0 font-monospace bg-light p-2 rounded border" style="white-space: pre-wrap; word-break: break-all;"><?php echo htmlspecialchars($mt940CronLine, ENT_QUOTES, 'UTF-8'); ?></pre>
            <?php if (!$mt940CronKeyOk) : ?>
                <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_CRON_SAVE_KEY_FIRST'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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

    <div class="card mb-3 border-info">
        <div class="card-body py-3">
            <h3 class="h6 mb-2"><i class="fas fa-calendar-day"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_MAILBOX_DATE_TITLE'); ?></h3>
            <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_MAILBOX_DATE_DESC'); ?></p>
            <p class="small text-muted mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_DEDUP_NOTICE'); ?></p>
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="form-label small mb-0" for="mt940-mailbox-date"><?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_MAILBOX_DATE_LABEL'); ?></label>
                    <input type="date" class="form-control form-control-sm" id="mt940-mailbox-date" name="mt940_mailbox_date"
                           value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 180px;">
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-mt940-date-import"
                        data-url="<?php echo htmlspecialchars($mt940DateUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-envelope-open-text"></i> <?php echo Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_MAILBOX_DATE_BTN'); ?>
                </button>
            </div>
            <div id="mt940-date-result" class="d-none mt-2">
                <div id="mt940-date-alert" class="alert mb-0" role="alert"></div>
                <pre id="mt940-date-json" class="bg-light border rounded p-2 small mb-0 mt-2" style="max-height: 180px; overflow: auto;"></pre>
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
    var runningDateImport = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_MAILBOX_DATE_RUNNING')); ?>;
    var clearingLabel = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_FINANCIERO_MT940_CLEAR_RUNNING')); ?>;
    var initialBtn = document.getElementById('btn-mt940-initial-import');
    var dateBtn = document.getElementById('btn-mt940-date-import');
    var dateInput = document.getElementById('mt940-mailbox-date');
    var clearBtn = document.getElementById('btn-mt940-clear-imported');
    var initialResult = document.getElementById('mt940-initial-result');
    var initialAlert = document.getElementById('mt940-initial-alert');
    var initialJson = document.getElementById('mt940-initial-json');
    var dateResult = document.getElementById('mt940-date-result');
    var dateAlert = document.getElementById('mt940-date-alert');
    var dateJson = document.getElementById('mt940-date-json');

    if (dateBtn && dateInput) {
        dateBtn.addEventListener('click', function () {
            var url = dateBtn.getAttribute('data-url');
            var dateVal = (dateInput.value || '').trim();
            if (!url || !dateVal) {
                return;
            }
            var orig = dateBtn.innerHTML;
            dateBtn.disabled = true;
            dateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningDateImport;
            dateResult.classList.remove('d-none');
            dateAlert.className = 'alert alert-info mb-0';
            dateAlert.textContent = runningDateImport;
            dateJson.textContent = '';

            var body = new URLSearchParams();
            body.append(token, '1');
            body.append('mt940_mailbox_date', dateVal);

            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var ok = !!data.success;
                    dateAlert.className = 'alert mb-0 ' + (ok ? 'alert-success' : 'alert-danger');
                    dateAlert.textContent = data.message || (ok ? 'OK' : 'Error');
                    dateJson.textContent = JSON.stringify(data, null, 2);
                    if (ok && (data.data?.files_imported > 0 || data.data?.transactions_imported > 0)) {
                        window.setTimeout(function () { window.location.reload(); }, 1500);
                    }
                })
                .catch(function (err) {
                    dateAlert.className = 'alert alert-danger mb-0';
                    dateAlert.textContent = err && err.message ? err.message : 'Error';
                })
                .finally(function () {
                    dateBtn.disabled = false;
                    dateBtn.innerHTML = orig;
                });
        });
    }

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
