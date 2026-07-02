<?php
/**
 * Ajustes > Blink payment test: health check and Pay Bi login via Blink gateway.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkWebhookLogHelper;
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
$blinkHasPayBiKey          = (bool) ($snap['paybi_key_set'] ?? false);
$blinkHasWebhookSecret     = (bool) ($snap['webhook_secret_set'] ?? false);
$blinkWebhookPublicUrl     = (string) ($snap['webhook_url'] ?? BlinkGatewayConfigHelper::getLogWebhookPublicUrl());
$blinkSocialNetworkCode    = (string) ($snap['social_network_code'] ?? BlinkGatewayConfigHelper::DEFAULT_SOCIAL_NETWORK_CODE);
$blinkHealthUrl  = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkHealth&format=json', false);
$blinkLoginUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkTestLogin&format=json', false);
$blinkPaymentUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkCreatePaymentLink&format=json', false);
$blinkLogsUrl    = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkGetExchangeLogs&format=json', false);
$blinkWebhookUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkSubscribeWebhook&format=json', false);
$blinkWebhooksListUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkListWebhooks&format=json', false);
$blinkReceivedLogsUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.blinkListReceivedWebhookLogs&format=json', false);
$blinkReceivedLogsTableExists = BlinkWebhookLogHelper::tableExists(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class));
$blinkReceivedLogsInitial = $blinkReceivedLogsTableExists ? BlinkWebhookLogHelper::getRecentEntries(BlinkWebhookLogHelper::PAGE_SIZE) : [];
$blinkReceivedLogsTotal = $blinkReceivedLogsTableExists ? BlinkWebhookLogHelper::countEntries() : 0;
$blinkComponentVersion = '';
foreach ([JPATH_SITE . '/components/com_ordenproduccion/VERSION', JPATH_SITE . '/components/com_ordenproduccion/site/VERSION'] as $versionPath) {
    if (is_file($versionPath)) {
        $blinkComponentVersion = trim((string) file_get_contents($versionPath));
        break;
    }
}
$blinkVersionNumber = preg_replace('/-.*$/', '', $blinkComponentVersion);
$blinkHasWebhookUi = $blinkVersionNumber !== '' && version_compare($blinkVersionNumber, '3.119.209', '>=');
$blinkWebhookPublicBaseParam = trim((string) BlinkGatewayConfigHelper::getParams()->get('blink_webhook_public_base', ''));
$blinkWebhookEnvPublicBase = getenv('BLINK_WEBHOOK_PUBLIC_BASE');
$blinkWebhookEnvSecret = getenv('BLINK_WEBHOOK_SECRET');
$blinkConfigUrl = '';
if ($user->authorise('core.admin')) {
    $blinkConfigUrl = Route::link(
        'administrator',
        'index.php?option=com_config&view=component&component=com_ordenproduccion'
    );
}
$blinkWebhookSaveUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveBlinkWebhookSettings', false);
$token = Session::getFormToken();
$blinkTestReferenceDefault = 'test-manual-' . date('YmdHis');
$blinkInstallmentChoices = [
    'VC00' => Text::_('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_SINGLE'),
    'VC02' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 2),
    'VC03' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 3),
    'VC06' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 6),
    'VC10' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 10),
    'VC12' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 12),
    'VC15' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 15),
    'VC18' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 18),
    'VC24' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 24),
    'VC36' => Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', 36),
];
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-credit-card"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_TITLE'); ?>
            <?php if ($blinkComponentVersion !== '') : ?>
                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($blinkComponentVersion); ?></span>
            <?php endif; ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_DESC'); ?></p>

        <?php if (!$blinkHasWebhookUi) : ?>
            <div class="alert alert-warning">
                <?php echo Text::sprintf('COM_ORDENPRODUCCION_BLINK_WEBHOOK_UI_UPDATE_REQUIRED', $blinkComponentVersion !== '' ? $blinkComponentVersion : '?'); ?>
            </div>
        <?php endif; ?>

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

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_PAYBI_KEY_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($blinkHasPayBiKey) : ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_SET'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_PAYBI_KEY_RECOMMENDED'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_SOCIAL_NETWORK_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <code><?php echo htmlspecialchars($blinkSocialNetworkCode); ?></code>
                        <span class="text-muted small"> (<?php echo htmlspecialchars(BlinkGatewayConfigHelper::DEFAULT_SOCIAL_NETWORK_LABEL); ?>)</span>
                    </dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_WEBHOOK_SECRET_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <?php if ($blinkHasWebhookSecret) : ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_SET'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_MISSING'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_URL_LABEL'); ?></dt>
                    <dd class="col-sm-8">
                        <code class="small user-select-all"><?php echo htmlspecialchars($blinkWebhookPublicUrl); ?></code>
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

                <?php if ($blinkCredentialsConfigured && !$blinkHasPayBiKey) : ?>
                    <div class="alert alert-warning">
                        <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_PAYBI_KEY_HINT'); ?>
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
                    <div id="blink-payment-link-box" class="d-none mb-3">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" id="blink-payment-link-input" readonly>
                            <a href="#" class="btn btn-outline-secondary" id="btn-blink-open-link" target="_blank" rel="noopener noreferrer">
                                <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_OPEN_LINK'); ?>
                            </a>
                            <button type="button" class="btn btn-outline-primary" id="btn-blink-copy-link">
                                <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_COPY_LINK'); ?>
                            </button>
                        </div>
                    </div>
                    <pre id="blink-test-json" class="bg-light border rounded p-3 small mb-0" style="max-height: 320px; overflow: auto;"></pre>
                    <div id="blink-exchange-logs-box" class="d-none mt-3">
                        <h4 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_EXCHANGE_LOGS_TITLE'); ?></h4>
                        <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_EXCHANGE_LOGS_DESC'); ?></p>
                        <pre id="blink-exchange-logs-json" class="bg-light border rounded p-3 small mb-0" style="max-height: 360px; overflow: auto;"></pre>
                    </div>
                </div>

                <hr class="my-4">

                <h3 class="h5 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_CREATE_SECTION'); ?></h3>
                <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_CREATE_DESC'); ?></p>

                <form id="blink-create-payment-form" class="row g-3 mb-3" <?php echo $blinkCredentialsConfigured ? '' : 'data-disabled="1"'; ?>>
                    <div class="col-md-4">
                        <label for="blink-test-amount" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_AMOUNT_LABEL'); ?></label>
                        <input type="number" class="form-control form-control-sm" id="blink-test-amount" name="amount" value="1.00" min="0.01" step="0.01" required <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-md-4">
                        <label for="blink-test-reference" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_COL_REFERENCE'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="blink-test-reference" name="reference_id" value="<?php echo htmlspecialchars($blinkTestReferenceDefault); ?>" maxlength="64" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                        <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_REFERENCE_HINT'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <label for="blink-test-installments" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_LABEL'); ?></label>
                        <select class="form-select form-select-sm" id="blink-test-installments" name="installments" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                            <?php foreach ($blinkInstallmentChoices as $code => $label) : ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"<?php echo $code === 'VC00' ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?> (<?php echo htmlspecialchars($code); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="blink-test-title" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_TITLE_LABEL'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="blink-test-title" name="title" value="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_TITLE_DEFAULT')); ?>" maxlength="100" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-md-6">
                        <label for="blink-test-description" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_DESCRIPTION_LABEL'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="blink-test-description" name="description" value="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_AJUSTES_BLINK_TEST_DESCRIPTION_DEFAULT')); ?>" maxlength="255" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-sm" id="btn-blink-create-payment" data-url="<?php echo htmlspecialchars($blinkPaymentUrl); ?>" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                            <i class="fas fa-link"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_CREATE_BTN'); ?>
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <div id="blink-webhook-section" class="border border-warning rounded p-3 mb-0">
                <h3 class="h5 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECTION'); ?></h3>
                <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECTION_DESC'); ?></p>

                <?php if (!$blinkHasWebhookSecret) : ?>
                    <div class="alert alert-warning mb-3">
                        <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECRET_MISSING'); ?>
                        <?php echo ' ' . Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECRET_FORM_HINT'); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo $blinkWebhookSaveUrl; ?>" method="post" id="blink-webhook-settings-form" class="row g-3 mb-4 pb-3 border-bottom">
                    <div class="col-12">
                        <h4 class="h6 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SETTINGS_SECTION'); ?></h4>
                        <p class="text-muted small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SETTINGS_DESC'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label for="blink-webhook-public-base" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_WEBHOOK_PUBLIC_BASE_LABEL'); ?></label>
                        <input type="url" class="form-control form-control-sm" id="blink-webhook-public-base" name="jform[blink_webhook_public_base]" value="<?php echo htmlspecialchars($blinkWebhookPublicBaseParam); ?>" maxlength="255" placeholder="https://your-public-site.example.com" <?php echo ($blinkWebhookEnvPublicBase !== false && trim((string) $blinkWebhookEnvPublicBase) !== '') ? 'readonly disabled' : ''; ?>>
                        <?php if ($blinkWebhookEnvPublicBase !== false && trim((string) $blinkWebhookEnvPublicBase) !== '') : ?>
                            <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_ENV_PUBLIC_BASE'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="blink-webhook-secret" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_CONFIG_BLINK_WEBHOOK_SECRET_LABEL'); ?></label>
                        <input type="password" class="form-control form-control-sm" id="blink-webhook-secret" name="jform[blink_webhook_secret]" value="" autocomplete="new-password" maxlength="255" placeholder="<?php echo $blinkHasWebhookSecret ? Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECRET_KEEP_PLACEHOLDER') : ''; ?>" <?php echo ($blinkWebhookEnvSecret !== false && trim((string) $blinkWebhookEnvSecret) !== '') ? 'readonly disabled' : ''; ?>>
                        <?php if ($blinkHasWebhookSecret) : ?>
                            <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECRET_KEEP_HINT'); ?></div>
                        <?php endif; ?>
                        <?php if ($blinkWebhookEnvSecret !== false && trim((string) $blinkWebhookEnvSecret) !== '') : ?>
                            <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_ENV_SECRET'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-save"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SAVE_SETTINGS_BTN'); ?>
                        </button>
                        <?php if ($blinkConfigUrl !== '') : ?>
                            <a href="<?php echo htmlspecialchars($blinkConfigUrl); ?>" class="small" target="_blank" rel="noopener noreferrer">
                                <?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_OPEN_CONFIG'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>

                <form id="blink-webhook-form" class="row g-3 mb-3">
                    <div class="col-12">
                        <label for="blink-webhook-url" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_URL_LABEL'); ?></label>
                        <input type="url" class="form-control form-control-sm font-monospace" id="blink-webhook-url" name="webhook_url" value="<?php echo htmlspecialchars($blinkWebhookPublicUrl); ?>" maxlength="2048" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                        <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_URL_HINT'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="blink-webhook-events" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_EVENTS_LABEL'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="blink-webhook-events" value="log.created" readonly disabled>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="blink-webhook-active" checked disabled>
                            <label class="form-check-label" for="blink-webhook-active"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_ACTIVE_LABEL'); ?></label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-warning btn-sm" id="btn-blink-subscribe-webhook" data-url="<?php echo htmlspecialchars($blinkWebhookUrl); ?>" <?php echo ($blinkCredentialsConfigured && $blinkHasWebhookSecret) ? '' : 'disabled'; ?>>
                            <i class="fas fa-bell"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_SUBSCRIBE_WEBHOOK_BTN'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-blink-refresh-webhooks" data-url="<?php echo htmlspecialchars($blinkWebhooksListUrl); ?>" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                            <i class="fas fa-sync-alt"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_REFRESH_WEBHOOKS_BTN'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-blink-refresh-received-logs" data-url="<?php echo htmlspecialchars($blinkReceivedLogsUrl); ?>" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                            <i class="fas fa-sync-alt"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_REFRESH_RECEIVED_LOGS_BTN'); ?>
                        </button>
                    </div>
                </form>

                <h4 class="h6 mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_GATEWAY_WEBHOOKS_TABLE_TITLE'); ?></h4>
                <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_GATEWAY_WEBHOOKS_TABLE_DESC'); ?></p>
                <div id="blink-gateway-webhooks-empty" class="text-muted small mb-3 d-none"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_GATEWAY_WEBHOOKS_EMPTY'); ?></div>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-striped table-bordered align-middle mb-0 bg-white" id="blink-gateway-webhooks-table">
                        <thead class="table-light">
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_COL_URL'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_COL_EVENTS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_COL_ACTIVE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_COL_ID'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="blink-gateway-webhooks-body">
                            <tr id="blink-gateway-webhooks-placeholder">
                                <td colspan="4" class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_GATEWAY_WEBHOOKS_LOAD_HINT'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_TABLE_TITLE'); ?></h4>
                <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_TABLE_DESC'); ?></p>
                <?php if (!$blinkReceivedLogsTableExists) : ?>
                    <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_LOGS_TABLE_MISSING'); ?></div>
                <?php else : ?>
                    <p class="small text-muted mb-2" id="blink-received-logs-count">
                        <?php echo Text::sprintf('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_COUNT', (int) $blinkReceivedLogsTotal); ?>
                    </p>
                    <div id="blink-received-logs-empty" class="text-muted small mb-3<?php echo $blinkReceivedLogsTotal > 0 ? ' d-none' : ''; ?>">
                        <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_EMPTY'); ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered align-middle mb-0 bg-white" id="blink-received-logs-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_COL_DATE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_COL_REFERENCE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_REQUEST_ID_LABEL'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_COL_OPERATION'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_COL_DETAIL'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="blink-received-logs-body">
                                <?php if ($blinkReceivedLogsTotal === 0) : ?>
                                    <tr class="blink-received-log-empty-row">
                                        <td colspan="5" class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_EMPTY'); ?></td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($blinkReceivedLogsInitial as $logRow) : ?>
                                        <?php
                                        $logJson = (string) ($logRow->log_data_json ?? '');
                                        $logPreview = $logJson !== '' ? $logJson : '{}';
                                        if (strlen($logPreview) > 120) {
                                            $logPreview = substr($logPreview, 0, 117) . '...';
                                        }
                                        ?>
                                        <tr>
                                            <td class="text-nowrap small"><?php echo htmlspecialchars((string) ($logRow->received ?? '')); ?></td>
                                            <td class="small"><code><?php echo htmlspecialchars((string) ($logRow->reference_id ?? '—')); ?></code></td>
                                            <td class="small"><code><?php echo htmlspecialchars((string) ($logRow->request_id ?? '—')); ?></code></td>
                                            <td class="small"><?php echo htmlspecialchars((string) ($logRow->gateway_operation ?? '—')); ?></td>
                                            <td class="small"><code class="user-select-all"><?php echo htmlspecialchars($logPreview); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                </div>

                <hr class="my-4">

                <h3 class="h5 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_EXCHANGE_LOGS_QUERY_SECTION'); ?></h3>
                <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_EXCHANGE_LOGS_QUERY_DESC'); ?></p>
                <form id="blink-fetch-logs-form" class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label for="blink-logs-reference" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_COL_REFERENCE'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="blink-logs-reference" name="referenceId" maxlength="100" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-md-5">
                        <label for="blink-logs-request-id" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_REQUEST_ID_LABEL'); ?></label>
                        <input type="text" class="form-control form-control-sm" id="blink-logs-request-id" name="requestId" maxlength="64" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100" id="btn-blink-fetch-logs" data-url="<?php echo htmlspecialchars($blinkLogsUrl); ?>" <?php echo $blinkCredentialsConfigured ? '' : 'disabled'; ?>>
                            <i class="fas fa-list-alt"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BLINK_FETCH_LOGS_BTN'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="border rounded p-3 bg-light mb-3">
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
                <div class="border rounded p-3 bg-light">
                    <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_CURL_PAYMENTS_TITLE'); ?></h3>
                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_TESTING_BLINK_CURL_PAYMENTS_DESC'); ?></p>
                    <pre class="small mb-0" style="white-space: pre-wrap;">curl -X POST <?php echo htmlspecialchars($blinkBaseUrl ?: 'http://localhost:3000'); ?>/api/v1/gateway/payments \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_GATEWAY_API_KEY" \
  -d '{
    "credentials": {
      "usuario": "merchant@example.com",
      "clave": "your-paybi-password"
    },
    "amount": 1.00,
    "installments": "VC00",
    "title": "Manual test",
    "description": "Blink payment test",
    "referenceId": "OP-12345"
  }'</pre>
                </div>
                <div class="border rounded p-3 bg-light mt-3">
                    <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_CURL_LOGS_TITLE'); ?></h3>
                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_CURL_LOGS_DESC'); ?></p>
                    <pre class="small mb-0" style="white-space: pre-wrap;">curl -H "X-API-Key: YOUR_GATEWAY_API_KEY" \
  "<?php echo htmlspecialchars($blinkBaseUrl ?: 'http://localhost:3000'); ?>/api/v1/gateway/logs?referenceId=OP-12345"</pre>
                </div>
                <div class="border rounded p-3 bg-light mt-3">
                    <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_CURL_WEBHOOK_TITLE'); ?></h3>
                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_CURL_WEBHOOK_DESC'); ?></p>
                    <pre class="small mb-0" style="white-space: pre-wrap;">curl -X POST <?php echo htmlspecialchars($blinkBaseUrl ?: 'http://localhost:3000'); ?>/api/v1/gateway/webhooks \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_GATEWAY_API_KEY" \
  -d '{
    "url": "<?php echo htmlspecialchars($blinkWebhookPublicUrl); ?>",
    "secret": "YOUR_BLINK_WEBHOOK_SECRET",
    "events": ["log.created"],
    "active": true
  }'</pre>
                </div>
                <div class="border rounded p-3 bg-light mt-3">
                    <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_CURL_LIST_WEBHOOKS_TITLE'); ?></h3>
                    <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_BLINK_CURL_LIST_WEBHOOKS_DESC'); ?></p>
                    <pre class="small mb-0" style="white-space: pre-wrap;">curl -H "X-API-Key: YOUR_GATEWAY_API_KEY" \
  "<?php echo htmlspecialchars($blinkBaseUrl ?: 'http://localhost:3000'); ?>/api/v1/gateway/webhooks"</pre>
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
    var linkCopiedText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_BLINK_LINK_COPIED')); ?>;
    var webhookConfirmText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SUBSCRIBE_CONFIRM')); ?>;
    var gatewayWebhooksEmptyText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_BLINK_GATEWAY_WEBHOOKS_EMPTY')); ?>;
    var receivedLogsEmptyText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_EMPTY')); ?>;
    var receivedLogsCountTpl = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_BLINK_RECEIVED_LOGS_COUNT')); ?>;
    var yesText = <?php echo json_encode(Text::_('JYES')); ?>;
    var noText = <?php echo json_encode(Text::_('JNO')); ?>;
    var resultBox = document.getElementById('blink-test-result');
    var alertBox = document.getElementById('blink-test-alert');
    var jsonBox = document.getElementById('blink-test-json');
    var paymentLinkBox = document.getElementById('blink-payment-link-box');
    var paymentLinkInput = document.getElementById('blink-payment-link-input');
    var openLinkBtn = document.getElementById('btn-blink-open-link');
    var copyLinkBtn = document.getElementById('btn-blink-copy-link');
    var exchangeLogsBox = document.getElementById('blink-exchange-logs-box');
    var exchangeLogsJson = document.getElementById('blink-exchange-logs-json');
    var logsReferenceInput = document.getElementById('blink-logs-reference');
    var logsRequestIdInput = document.getElementById('blink-logs-request-id');
    var gatewayWebhooksBody = document.getElementById('blink-gateway-webhooks-body');
    var gatewayWebhooksEmpty = document.getElementById('blink-gateway-webhooks-empty');
    var receivedLogsBody = document.getElementById('blink-received-logs-body');
    var receivedLogsEmpty = document.getElementById('blink-received-logs-empty');
    var receivedLogsCount = document.getElementById('blink-received-logs-count');

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderGatewayWebhooks(webhooks) {
        if (!gatewayWebhooksBody) {
            return;
        }
        gatewayWebhooksBody.innerHTML = '';
        if (!webhooks || !webhooks.length) {
            if (gatewayWebhooksEmpty) {
                gatewayWebhooksEmpty.classList.remove('d-none');
            }
            var emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="4" class="text-muted small">' + escapeHtml(gatewayWebhooksEmptyText) + '</td>';
            gatewayWebhooksBody.appendChild(emptyRow);
            return;
        }
        if (gatewayWebhooksEmpty) {
            gatewayWebhooksEmpty.classList.add('d-none');
        }
        webhooks.forEach(function (hook) {
            var events = hook.events;
            if (Array.isArray(events)) {
                events = events.join(', ');
            }
            var active = hook.active === true || hook.active === 1 || hook.active === '1';
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="small"><code class="user-select-all">' + escapeHtml(hook.url || '') + '</code></td>' +
                '<td class="small">' + escapeHtml(events || '—') + '</td>' +
                '<td class="small">' + (active ? escapeHtml(yesText) : escapeHtml(noText)) + '</td>' +
                '<td class="small"><code>' + escapeHtml(hook.id || hook._id || '—') + '</code></td>';
            gatewayWebhooksBody.appendChild(tr);
        });
    }

    function renderReceivedLogs(entries, total) {
        if (!receivedLogsBody) {
            return;
        }
        receivedLogsBody.innerHTML = '';
        if (receivedLogsCount && receivedLogsCountTpl) {
            receivedLogsCount.textContent = receivedLogsCountTpl.replace('%d', String(total || 0));
        }
        if (!entries || !entries.length) {
            if (receivedLogsEmpty) {
                receivedLogsEmpty.classList.remove('d-none');
            }
            var emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="5" class="text-muted small">' + escapeHtml(receivedLogsEmptyText) + '</td>';
            receivedLogsBody.appendChild(emptyRow);
            return;
        }
        if (receivedLogsEmpty) {
            receivedLogsEmpty.classList.add('d-none');
        }
        entries.forEach(function (entry) {
            var preview = '{}';
            if (entry.log_data) {
                preview = JSON.stringify(entry.log_data);
            }
            if (preview.length > 120) {
                preview = preview.substring(0, 117) + '...';
            }
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="text-nowrap small">' + escapeHtml(entry.received || '') + '</td>' +
                '<td class="small"><code>' + escapeHtml(entry.reference_id || '—') + '</code></td>' +
                '<td class="small"><code>' + escapeHtml(entry.request_id || '—') + '</code></td>' +
                '<td class="small">' + escapeHtml(entry.gateway_operation || '—') + '</td>' +
                '<td class="small"><code class="user-select-all">' + escapeHtml(preview) + '</code></td>';
            receivedLogsBody.appendChild(tr);
        });
    }

    function fetchBlinkJson(url, options) {
        options = options || {};
        var method = options.method || 'GET';
        var body = options.body || null;
        var params = new URLSearchParams();
        params.set(token, '1');
        var fetchUrl = url;
        if (method === 'GET') {
            fetchUrl += (url.indexOf('?') >= 0 ? '&' : '?') + params.toString();
        }
        return fetch(fetchUrl, {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: method === 'POST' ? body : null
        }).then(function (response) {
            return response.json();
        });
    }

    function loadGatewayWebhooks(button) {
        var url = button ? button.getAttribute('data-url') : '';
        if (!url) {
            return;
        }
        var origHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
        }
        fetchBlinkJson(url, { method: 'GET' })
            .then(function (data) {
                if (data.success && data.data && data.data.webhooks) {
                    renderGatewayWebhooks(data.data.webhooks);
                } else if (data.success && data.data && data.data.entries) {
                    renderGatewayWebhooks(data.data.entries);
                } else {
                    renderGatewayWebhooks([]);
                    if (!data.success && alertBox && resultBox) {
                        resultBox.classList.remove('d-none');
                        alertBox.className = 'alert alert-warning';
                        alertBox.textContent = data.message || networkErr;
                    }
                }
            })
            .catch(function () {
                renderGatewayWebhooks([]);
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = origHtml;
                }
            });
    }

    function loadReceivedLogs(button) {
        var url = button ? button.getAttribute('data-url') : '';
        if (!url || !receivedLogsBody) {
            return;
        }
        var origHtml = button ? button.innerHTML : '';
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
        }
        fetchBlinkJson(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'limit=25', { method: 'GET' })
            .then(function (data) {
                if (data.success && data.data) {
                    renderReceivedLogs(data.data.entries || [], data.data.total || 0);
                }
            })
            .catch(function () {
            })
            .finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = origHtml;
                }
            });
    }

    function renderExchangeLogs(data) {
        var logs = (data && data.data && data.data.exchange_logs) ? data.data.exchange_logs : null;
        if (!logs && data && data.data && data.data.entries) {
            logs = data.data.entries;
        }
        if (!logs || !logs.length) {
            exchangeLogsBox.classList.add('d-none');
            exchangeLogsJson.textContent = '';
            return;
        }
        exchangeLogsBox.classList.remove('d-none');
        exchangeLogsJson.textContent = JSON.stringify(logs, null, 2);
    }

    function syncLogQueryFields(data) {
        if (!data || !data.data) {
            return;
        }
        if (data.data.reference_id && logsReferenceInput && logsReferenceInput.value === '') {
            logsReferenceInput.value = data.data.reference_id;
        }
        if (data.data.request_id && logsRequestIdInput) {
            logsRequestIdInput.value = data.data.request_id;
        }
    }

    function showBlinkResult(data) {
        var ok = !!data.success;
        alertBox.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        var msg = data.message || (ok ? 'OK' : 'Error');
        if (!ok && data.data && data.data.exchange_total) {
            msg += ' (' + data.data.exchange_total + ' log entries)';
        }
        if (!ok && data.data && data.data.exchange_error) {
            msg += ' — ' + data.data.exchange_error;
        }
        alertBox.textContent = msg;
        jsonBox.textContent = JSON.stringify(data, null, 2);
        syncLogQueryFields(data);
        renderExchangeLogs(data);

        var paymentUrl = data.data && data.data.payment_url ? data.data.payment_url : '';
        if (ok && paymentUrl) {
            paymentLinkBox.classList.remove('d-none');
            paymentLinkInput.value = paymentUrl;
            openLinkBtn.href = paymentUrl;
        } else {
            paymentLinkBox.classList.add('d-none');
            paymentLinkInput.value = '';
            openLinkBtn.href = '#';
        }
    }

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
        paymentLinkBox.classList.add('d-none');
        exchangeLogsBox.classList.add('d-none');
        exchangeLogsJson.textContent = '';

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
                showBlinkResult(data);
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

    var paymentForm = document.getElementById('blink-create-payment-form');
    var paymentBtn = document.getElementById('btn-blink-create-payment');
    if (paymentForm && paymentBtn) {
        paymentForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var url = paymentBtn.getAttribute('data-url');
            if (!url) {
                return;
            }
            var origHtml = paymentBtn.innerHTML;
            paymentBtn.disabled = true;
            paymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
            resultBox.classList.remove('d-none');
            alertBox.className = 'alert alert-info';
            alertBox.textContent = runningText;
            jsonBox.textContent = '';
            paymentLinkBox.classList.add('d-none');
            exchangeLogsBox.classList.add('d-none');
            exchangeLogsJson.textContent = '';

            var body = new URLSearchParams(new FormData(paymentForm));
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
                    showBlinkResult(data);
                })
                .catch(function (err) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = (err && err.message) ? err.message : networkErr;
                })
                .finally(function () {
                    paymentBtn.disabled = false;
                    paymentBtn.innerHTML = origHtml;
                });
        });
    }

    if (copyLinkBtn && paymentLinkInput) {
        copyLinkBtn.addEventListener('click', function () {
            var value = paymentLinkInput.value;
            if (!value) {
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = linkCopiedText;
                });
                return;
            }
            paymentLinkInput.select();
            document.execCommand('copy');
            alertBox.className = 'alert alert-success';
            alertBox.textContent = linkCopiedText;
        });
    }

    var logsForm = document.getElementById('blink-fetch-logs-form');
    var logsBtn = document.getElementById('btn-blink-fetch-logs');
    if (logsForm && logsBtn) {
        logsForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var url = logsBtn.getAttribute('data-url');
            if (!url) {
                return;
            }
            var referenceId = (document.getElementById('blink-logs-reference') || {}).value || '';
            var requestId = (document.getElementById('blink-logs-request-id') || {}).value || '';
            if (referenceId === '' && requestId === '') {
                alertBox.className = 'alert alert-warning';
                alertBox.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_BLINK_LOGS_FILTER_REQUIRED')); ?>;
                resultBox.classList.remove('d-none');
                return;
            }
            var origHtml = logsBtn.innerHTML;
            logsBtn.disabled = true;
            logsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
            resultBox.classList.remove('d-none');
            alertBox.className = 'alert alert-info';
            alertBox.textContent = runningText;

            var params = new URLSearchParams();
            if (referenceId !== '') {
                params.set('referenceId', referenceId);
            }
            if (requestId !== '') {
                params.set('requestId', requestId);
            }
            params.set('gatewayOperation', 'create-payment');
            params.set('limit', '50');
            params.set(token, '1');

            fetch(url + '?' + params.toString(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) { showBlinkResult(data); })
                .catch(function (err) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = (err && err.message) ? err.message : networkErr;
                })
                .finally(function () {
                    logsBtn.disabled = false;
                    logsBtn.innerHTML = origHtml;
                });
        });
    }

    var subscribeBtn = document.getElementById('btn-blink-subscribe-webhook');
    var webhookForm = document.getElementById('blink-webhook-form');
    if (webhookForm && subscribeBtn) {
        webhookForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!window.confirm(webhookConfirmText)) {
                return;
            }
            var url = subscribeBtn.getAttribute('data-url');
            if (!url) {
                return;
            }
            var origHtml = subscribeBtn.innerHTML;
            subscribeBtn.disabled = true;
            subscribeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + runningText;
            resultBox.classList.remove('d-none');
            alertBox.className = 'alert alert-info';
            alertBox.textContent = runningText;

            var body = new URLSearchParams();
            var webhookUrlInput = document.getElementById('blink-webhook-url');
            if (webhookUrlInput && webhookUrlInput.value) {
                body.set('webhook_url', webhookUrlInput.value);
            }
            body.set(token, '1');

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    showBlinkResult(data);
                    if (data.success) {
                        var refreshBtn = document.getElementById('btn-blink-refresh-webhooks');
                        if (refreshBtn) {
                            loadGatewayWebhooks(refreshBtn);
                        }
                    }
                })
                .catch(function (err) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = (err && err.message) ? err.message : networkErr;
                })
                .finally(function () {
                    subscribeBtn.disabled = false;
                    subscribeBtn.innerHTML = origHtml;
                });
        });
    }

    var refreshWebhooksBtn = document.getElementById('btn-blink-refresh-webhooks');
    if (refreshWebhooksBtn) {
        refreshWebhooksBtn.addEventListener('click', function () {
            loadGatewayWebhooks(refreshWebhooksBtn);
        });
        if (<?php echo $blinkCredentialsConfigured ? 'true' : 'false'; ?>) {
            loadGatewayWebhooks(refreshWebhooksBtn);
        }
    }

    var refreshReceivedBtn = document.getElementById('btn-blink-refresh-received-logs');
    if (refreshReceivedBtn) {
        refreshReceivedBtn.addEventListener('click', function () {
            loadReceivedLogs(refreshReceivedBtn);
        });
    }
})();
</script>
