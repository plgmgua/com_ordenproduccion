<?php
/**
 * Ajustes > Blink payment test: health check and Pay Bi login via Blink gateway.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('form.csrf');

$app = Factory::getApplication();
$user = Factory::getUser();
$snap = BlinkGatewayConfigHelper::getSnapshot();
$blinkEnabled              = (bool) ($snap['enabled'] ?? false);
$blinkCredentialsConfigured = (bool) ($snap['credentials_configured'] ?? false);
$blinkBaseUrl              = (string) ($snap['base_url'] ?? '');
$blinkUsuario              = (string) ($snap['usuario'] ?? '');
$blinkHasApiKey            = (bool) ($snap['api_key_set'] ?? false);
$blinkHasClave             = (bool) ($snap['clave_set'] ?? false);
$blinkHealthUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkHealth&format=json', false);
$blinkLoginUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkTestLogin&format=json', false);
$blinkConfigUrl  = $user->authorise('core.admin')
    ? Route::_('index.php?option=com_config&view=component&component=com_ordenproduccion')
    : '';
$token = Session::getFormToken();
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-credit-card"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_DESC'); ?></p>

        <div class="row">
            <div class="col-lg-8">
                <dl class="row mb-3">
                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_ENABLED_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($blinkEnabled) : ?>
                            <span class="badge bg-success"><?php echo Text::_('JYES'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-secondary"><?php echo Text::_('JNO'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_BASE_URL_LABEL'); ?></dt>
                    <dd class="col-sm-8"><code><?php echo htmlspecialchars($blinkBaseUrl ?: '—'); ?></code></dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_API_KEY_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($blinkHasApiKey) : ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_SET'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-danger"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_PAYBI_USUARIO_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($blinkUsuario !== '') : ?>
                            <code><?php echo htmlspecialchars($blinkUsuario); ?></code>
                        <?php else : ?>
                            <span class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_PAYBI_CLAVE_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($blinkHasClave) : ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_SET'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-danger"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                        <?php endif; ?>
                    </dd>
                </dl>

                <?php if (!$blinkCredentialsConfigured) : ?>
                    <div class="alert alert-warning">
                        <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED'); ?>
                        <?php if ($blinkConfigUrl !== '') : ?>
                            <a href="<?php echo $blinkConfigUrl; ?>" class="alert-link">
                                <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_OPEN_CONFIG'); ?>
                            </a>
                        <?php else : ?>
                            <span class="d-block mt-1 small"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_CONFIG_HINT'); ?></span>
                        <?php endif; ?>
                        <span class="d-block mt-1 small"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_PASSWORD_HINT'); ?></span>
                    </div>
                <?php elseif (!$blinkEnabled) : ?>
                    <div class="alert alert-info">
                        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_DISABLED_NOTICE'); ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-blink-health" data-url="<?php echo htmlspecialchars($blinkHealthUrl); ?>">
                        <i class="fas fa-heartbeat"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_HEALTH_BTN'); ?>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-blink-test-login" data-url="<?php echo htmlspecialchars($blinkLoginUrl); ?>" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                        <i class="fas fa-sign-in-alt"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_TEST_LOGIN_BTN'); ?>
                    </button>
                </div>

                <div id="blink-test-result" class="d-none">
                    <div id="blink-test-alert" class="alert" role="alert"></div>
                    <pre id="blink-test-json" class="bg-light border rounded p-3 small mb-0" style="max-height: 320px; overflow: auto;"></pre>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="border rounded p-3 bg-light">
                    <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_CURL_TITLE'); ?></h3>
                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_CURL_DESC'); ?></p>
                    <pre class="small mb-0" style="white-space: pre-wrap;">curl -X POST <?php echo htmlspecialchars($blinkBaseUrl ?: 'http://localhost:3000'); ?>/api/v1/gateway/test-login \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_GATEWAY_API_KEY" \
  -d '{
    "credentials": {
      "usuario": "merchant@example.com",
      "clave": "your-paybi-password"
    }
  }'</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var token = <?php echo json_encode($token); ?>;
    var runningText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_RUNNING')); ?>;
    var networkErr = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_NETWORK')); ?>;
    var resultBox = document.getElementById('blink-test-result');
    var alertBox = document.getElementById('blink-test-alert');
    var jsonBox = document.getElementById('blink-test-json');

    function runBlinkTest(url, button) {
        if (!url || !button) {
            return;
        }
        var origHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
        resultBox.classList.remove('d-none');
        alertBox.className = 'alert alert-info';
        alertBox.textContent = runningText;
        jsonBox.textContent = '';

        var sep = url.indexOf('?') >= 0 ? '&' : '?';
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
                button.disabled = false;
                button.innerHTML = origHtml;
            });
    }

    var healthBtn = document.getElementById('btn-blink-health');
    if (healthBtn) {
        healthBtn.addEventListener('click', function () {
            runBlinkTest(healthBtn.getAttribute('data-url'), healthBtn);
        });
    }

    var loginBtn = document.getElementById('btn-blink-test-login');
    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            runBlinkTest(loginBtn.getAttribute('data-url'), loginBtn);
        });
    }
})();
</script>
