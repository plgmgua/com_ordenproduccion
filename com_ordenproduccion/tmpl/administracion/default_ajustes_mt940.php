<?php
/**
 * Ajustes > MT-940: mailbox IMAP settings and bank account association.
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
use Grimpsa\Component\Ordenproduccion\Site\Helper\Mt940ImapHelper;

HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('form.csrf');

$app = Factory::getApplication();
$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

/** @var array<string, string|int> $settings */
$settings = isset($this->mt940Settings) && \is_array($this->mt940Settings) ? $this->mt940Settings : [];
$passwordSet = !empty($this->mt940PasswordSet);
$bankAccounts = isset($this->bankAccounts) && \is_array($this->bankAccounts) ? $this->bankAccounts : [];

$enabled         = ($settings['enabled'] ?? '0') === '1';
$mailboxEmail    = (string) ($settings['mailbox_email'] ?? '');
$imapHost        = (string) ($settings['imap_host'] ?? '');
$imapPort        = (string) ($settings['imap_port'] ?? '993');
$imapEncryption  = (string) ($settings['imap_encryption'] ?? 'ssl');
$imapUsername    = (string) ($settings['imap_username'] ?? '');
$senderEmail     = (string) ($settings['sender_email'] ?? Mt940ImapHelper::DEFAULT_SENDER_EMAIL);
$selectedBankAccountIds = [];
if (isset($settings['bank_account_ids']) && \is_array($settings['bank_account_ids'])) {
    foreach ($settings['bank_account_ids'] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $selectedBankAccountIds[$id] = true;
        }
    }
}

$saveUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveMt940Settings', false);
$testUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.testMt940Imap&format=json', false);
$token    = Session::getFormToken();
?>
<div class="card com-ordenproduccion-mt940-settings">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-university"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_MT940_SETTINGS_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_SETTINGS_DESC'); ?></p>

        <form action="<?php echo $saveUrl; ?>" method="post" name="adminForm" id="ajustes-mt940-form" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>

            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="jform[mt940][enabled]" id="mt940_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mt940_enabled"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_ENABLED_LABEL'); ?></label>
                    </div>
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_ENABLED_DESC'); ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mt940_mailbox_email"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_MAILBOX_EMAIL_LABEL'); ?></label>
                    <input type="email" class="form-control" name="jform[mt940][mailbox_email]" id="mt940_mailbox_email"
                           value="<?php echo htmlspecialchars($mailboxEmail, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_MAILBOX_EMAIL_DESC'); ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mt940_sender_email"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_SENDER_EMAIL_LABEL'); ?></label>
                    <input type="email" class="form-control" name="jform[mt940][sender_email]" id="mt940_sender_email"
                           value="<?php echo htmlspecialchars($senderEmail, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_SENDER_EMAIL_DESC'); ?></div>
                </div>

                <div class="col-12">
                    <hr class="my-1">
                    <h3 class="h6 text-secondary mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_SECTION'); ?></h3>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mt940_imap_host"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_HOST_LABEL'); ?></label>
                    <input type="text" class="form-control" name="jform[mt940][imap_host]" id="mt940_imap_host"
                           value="<?php echo htmlspecialchars($imapHost, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" placeholder="imap.example.com">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="mt940_imap_port"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_PORT_LABEL'); ?></label>
                    <input type="number" class="form-control" name="jform[mt940][imap_port]" id="mt940_imap_port"
                           value="<?php echo htmlspecialchars($imapPort, ENT_QUOTES, 'UTF-8'); ?>" min="1" max="65535" autocomplete="off">
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="mt940_imap_encryption"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_ENCRYPTION_LABEL'); ?></label>
                    <select class="form-select" name="jform[mt940][imap_encryption]" id="mt940_imap_encryption">
                        <option value="ssl" <?php echo $imapEncryption === 'ssl' ? 'selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_ENCRYPTION_SSL'); ?></option>
                        <option value="tls" <?php echo $imapEncryption === 'tls' ? 'selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_ENCRYPTION_TLS'); ?></option>
                        <option value="none" <?php echo $imapEncryption === 'none' ? 'selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_ENCRYPTION_NONE'); ?></option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mt940_imap_username"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_USERNAME_LABEL'); ?></label>
                    <input type="text" class="form-control" name="jform[mt940][imap_username]" id="mt940_imap_username"
                           value="<?php echo htmlspecialchars($imapUsername, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mt940_imap_password"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_IMAP_PASSWORD_LABEL'); ?></label>
                    <input type="password" class="form-control" name="jform[mt940][imap_password]" id="mt940_imap_password"
                           value="" autocomplete="new-password"
                           placeholder="<?php echo htmlspecialchars($passwordSet ? Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE_PLACEHOLDER_KEEP') : '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($passwordSet) : ?>
                        <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE_KEEP_HINT'); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_BANK_ACCOUNT_LABEL'); ?></label>
                    <?php
                    $activeBankAccounts = [];
                    foreach ($bankAccounts as $account) {
                        $accId = (int) ($account->id ?? 0);
                        if ($accId < 1 || (int) ($account->state ?? 1) !== 1) {
                            continue;
                        }
                        $activeBankAccounts[] = $account;
                    }
                    ?>
                    <?php if ($activeBankAccounts === []) : ?>
                        <div class="alert alert-warning mb-0 py-2">
                            <?php echo Text::_('COM_ORDENPRODUCCION_MT940_BANK_ACCOUNT_EMPTY'); ?>
                        </div>
                    <?php else : ?>
                        <div class="border rounded p-3 bg-light">
                            <?php foreach ($activeBankAccounts as $account) :
                                $accId   = (int) ($account->id ?? 0);
                                $accName = trim((string) ($account->name ?? ''));
                                $accNo   = trim((string) ($account->account_number ?? ''));
                                $checked = !empty($selectedBankAccountIds[$accId]);
                                $label   = $accName !== '' ? $accName : ('#' . $accId);
                                if ($accNo !== '') {
                                    $label .= ' — ' . $accNo;
                                }
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="jform[mt940][bank_account_ids][]"
                                           id="mt940_bank_account_<?php echo $accId; ?>"
                                           value="<?php echo $accId; ?>"
                                           <?php echo $checked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mt940_bank_account_<?php echo $accId; ?>">
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_BANK_ACCOUNT_DESC'); ?></div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo Text::_('JSAVE'); ?>
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-mt940-test-imap" data-url="<?php echo htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-plug"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_MT940_TEST_IMAP_BTN'); ?>
                </button>
            </div>
        </form>

        <div id="mt940-test-result" class="d-none mt-3">
            <div id="mt940-test-alert" class="alert" role="alert"></div>
            <pre id="mt940-test-json" class="bg-light border rounded p-3 small mb-0" style="max-height: 240px; overflow: auto;"></pre>
        </div>
    </div>
</div>

<script>
(function () {
    var token = <?php echo json_encode($token); ?>;
    var runningText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_MT940_TEST_IMAP_RUNNING')); ?>;
    var networkErr = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_NETWORK')); ?>;
    var form = document.getElementById('ajustes-mt940-form');
    var resultBox = document.getElementById('mt940-test-result');
    var alertBox = document.getElementById('mt940-test-alert');
    var jsonBox = document.getElementById('mt940-test-json');
    var testBtn = document.getElementById('btn-mt940-test-imap');

    if (!form || !testBtn) {
        return;
    }

    testBtn.addEventListener('click', function () {
        var url = testBtn.getAttribute('data-url');
        if (!url) {
            return;
        }

        var origHtml = testBtn.innerHTML;
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
        resultBox.classList.remove('d-none');
        alertBox.className = 'alert alert-info';
        alertBox.textContent = runningText;
        jsonBox.textContent = '';

        var body = new URLSearchParams(new FormData(form));
        body.append(token, '1');

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                var ok = !!data.success;
                alertBox.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
                alertBox.textContent = data.message || (ok ? 'OK' : 'Error');
                jsonBox.textContent = JSON.stringify(data, null, 2);
            })
            .catch(function (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = (err && err.message) ? err.message : networkErr;
            })
            .finally(function () {
                testBtn.disabled = false;
                testBtn.innerHTML = origHtml;
            });
    });
})();
</script>
