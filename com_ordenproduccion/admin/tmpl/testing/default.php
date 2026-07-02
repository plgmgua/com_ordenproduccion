<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/** @var \Grimpsa\Component\Ordenproduccion\Administrator\View\Testing\HtmlView $this */

HTMLHelper::_('bootstrap.framework');
$token = Session::getFormToken();
?>

<div class="com-ordenproduccion-testing">
    <div class="row mb-4">
        <div class="col-12">
            <p class="lead"><?php echo Text::_('COM_ORDENPRODUCCION_MENU_TESTING'); ?></p>
            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_DESC'); ?></p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_TITLE'); ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_DESC'); ?></p>

                    <dl class="row mb-3">
                        <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_ENABLED_LABEL'); ?></dt>
                        <dd class="col-sm-8">
                            <?php if ($this->blinkEnabled) : ?>
                                <span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-secondary"><?php echo Text::_('JNO'); ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_BASE_URL_LABEL'); ?></dt>
                        <dd class="col-sm-8"><code><?php echo htmlspecialchars($this->blinkBaseUrl ?: '—'); ?></code></dd>

                        <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_API_KEY_LABEL'); ?></dt>
                        <dd class="col-sm-8">
                            <?php if ($this->blinkHasApiKey) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_SET'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-danger"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_PAYBI_USUARIO_LABEL'); ?></dt>
                        <dd class="col-sm-8">
                            <?php if ($this->blinkUsuario !== '') : ?>
                                <code><?php echo htmlspecialchars($this->blinkUsuario); ?></code>
                            <?php else : ?>
                                <span class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_PAYBI_CLAVE_LABEL'); ?></dt>
                        <dd class="col-sm-8">
                            <?php if ($this->blinkHasClave) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_SET'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-danger"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                            <?php endif; ?>
                        </dd>
                    </dl>

                    <?php if (!$this->blinkCredentialsConfigured) : ?>
                        <div class="alert alert-warning">
                            <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED'); ?>
                            <a href="<?php echo $this->blinkConfigUrl; ?>" class="alert-link">
                                <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_OPEN_CONFIG'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary" id="btn-blink-health" data-url="<?php echo htmlspecialchars($this->blinkHealthUrl); ?>">
                            <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_HEALTH_BTN'); ?>
                        </button>
                        <button type="button" class="btn btn-primary" id="btn-blink-test-login" data-url="<?php echo htmlspecialchars($this->blinkTestLoginUrl); ?>" <?php echo $this->blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                            <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_TEST_LOGIN_BTN'); ?>
                        </button>
                        <a href="<?php echo $this->blinkConfigUrl; ?>" class="btn btn-outline-secondary">
                            <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_OPEN_CONFIG'); ?>
                        </a>
                    </div>

                    <div id="blink-test-result" class="d-none">
                        <div id="blink-test-alert" class="alert" role="alert"></div>
                        <pre id="blink-test-json" class="bg-light border rounded p-3 small mb-0" style="max-height: 320px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const token = <?php echo json_encode($token); ?>;
    const resultBox = document.getElementById('blink-test-result');
    const alertBox = document.getElementById('blink-test-alert');
    const jsonBox = document.getElementById('blink-test-json');

    function runBlinkTest(url, button) {
        if (!url) {
            return;
        }
        button.disabled = true;
        resultBox.classList.remove('d-none');
        alertBox.className = 'alert alert-info';
        alertBox.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_RUNNING')); ?>;
        jsonBox.textContent = '';

        const sep = url.indexOf('?') >= 0 ? '&' : '?';
        fetch(url + sep + token + '=1', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                const ok = !!data.success;
                alertBox.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
                alertBox.textContent = data.message || (ok ? 'OK' : 'Error');
                jsonBox.textContent = JSON.stringify(data, null, 2);
            })
            .catch(function (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = err && err.message ? err.message : 'Request failed';
            })
            .finally(function () {
                button.disabled = false;
            });
    }

    const healthBtn = document.getElementById('btn-blink-health');
    if (healthBtn) {
        healthBtn.addEventListener('click', function () {
            runBlinkTest(healthBtn.getAttribute('data-url'), healthBtn);
        });
    }

    const loginBtn = document.getElementById('btn-blink-test-login');
    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            runBlinkTest(loginBtn.getAttribute('data-url'), loginBtn);
        });
    }
})();
</script>
