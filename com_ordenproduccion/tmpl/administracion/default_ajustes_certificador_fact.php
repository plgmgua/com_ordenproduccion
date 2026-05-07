<?php
/**
 * Ajustes > Certificador de Fact: URLs and credentials (test + production).
 *
 * Layout can use flattened active-modo variables from the view, e.g. $this->certificadorFactActiveNit,
 * $this->certificadorFactActiveUsuario, $this->certificadorFactActiveUrlAutenticacion, or the map
 * $this->certificadorFactActive (clave is never on the view; use certificadorFactActiveClaveConfigured).
 * For API code needing the stored clave, use AdministracionModel::getCertificadorFactSettingsForActiveModo()
 * or FelInvoiceIssuanceService::getActiveCertificadorCredentials() server-side only.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
HTMLHelper::_('behavior.core');
HTMLHelper::_('form.csrf');

$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

/** @var array<string, array<string, string>> $settings */
$settings = isset($this->certificadorFactSettings) && is_array($this->certificadorFactSettings)
    ? $this->certificadorFactSettings
    : ['test' => [], 'prod' => []];
$claveSet = isset($this->certificadorFactClaveSet) && is_array($this->certificadorFactClaveSet)
    ? $this->certificadorFactClaveSet
    : ['test' => false, 'prod' => false];
$felModo = (isset($this->certificadorFactModo) && $this->certificadorFactModo === 'prod') ? 'prod' : 'test';
$felFrontendDebug = !empty($this->certificadorFactFrontendDebug);
$testAuthTaskUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.testCertificadorFactAuth&format=json', false);

$renderSection = static function (string $env, string $headingKey, string $icon) use ($settings, $claveSet) {
    $s = $settings[$env] ?? [];
    $hasClave = !empty($claveSet[$env]);
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="h5 mb-0">
                <i class="fas fa-<?php echo $icon; ?>"></i>
                <?php echo Text::_($headingKey); ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_url_auth"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_URL_AUTENTICACION'); ?></label>
                    <input type="url" class="form-control" name="jform[certificador][<?php echo $env; ?>][url_autenticacion]" id="cf_<?php echo $env; ?>_url_auth"
                           value="<?php echo htmlspecialchars($s['url_autenticacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_url_info"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_URL_INFO'); ?></label>
                    <input type="url" class="form-control" name="jform[certificador][<?php echo $env; ?>][url_info]" id="cf_<?php echo $env; ?>_url_info"
                           value="<?php echo htmlspecialchars($s['url_info'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_url_cf"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_URL_CERT_CF'); ?></label>
                    <input type="url" class="form-control" name="jform[certificador][<?php echo $env; ?>][url_cert_cf]" id="cf_<?php echo $env; ?>_url_cf"
                           value="<?php echo htmlspecialchars($s['url_cert_cf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_url_nit"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_URL_CERT_NIT'); ?></label>
                    <input type="url" class="form-control" name="jform[certificador][<?php echo $env; ?>][url_cert_nit]" id="cf_<?php echo $env; ?>_url_nit"
                           value="<?php echo htmlspecialchars($s['url_cert_nit'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_url_cui"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_URL_CERT_CUI'); ?></label>
                    <input type="url" class="form-control" name="jform[certificador][<?php echo $env; ?>][url_cert_cui]" id="cf_<?php echo $env; ?>_url_cui"
                           value="<?php echo htmlspecialchars($s['url_cert_cui'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_nit"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_NIT'); ?></label>
                    <input type="text" class="form-control" name="jform[certificador][<?php echo $env; ?>][nit]" id="cf_<?php echo $env; ?>_nit"
                           value="<?php echo htmlspecialchars($s['nit'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_usuario"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_USUARIO'); ?></label>
                    <input type="text" class="form-control" name="jform[certificador][<?php echo $env; ?>][usuario]" id="cf_<?php echo $env; ?>_usuario"
                           value="<?php echo htmlspecialchars($s['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cf_<?php echo $env; ?>_clave"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE'); ?></label>
                    <input type="password" class="form-control" name="jform[certificador][<?php echo $env; ?>][clave]" id="cf_<?php echo $env; ?>_clave"
                           value="" autocomplete="new-password"
                           placeholder="<?php echo htmlspecialchars($hasClave ? Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE_PLACEHOLDER_KEEP') : '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($hasClave) : ?>
                        <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_CLAVE_KEEP_HINT'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
};
?>
<div class="card mb-3">
    <div class="card-header">
        <h2 class="card-title mb-0">
            <i class="fas fa-file-signature"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_CERTIFICADOR_FACT_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body py-2">
        <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_AJUSTES_CERTIFICADOR_FACT_DESC'); ?></p>
    </div>
</div>

<?php
$maintLog = (isset($this->certificadorTokenMaintainLastLog) && \is_array($this->certificadorTokenMaintainLastLog))
    ? $this->certificadorTokenMaintainLastLog
    : ['at' => '', 'summary' => ['test' => '', 'prod' => '', 'errors' => [], 'forced' => false]];
$maintAt = trim((string) ($maintLog['at'] ?? ''));
$maintSummary = isset($maintLog['summary']) && \is_array($maintLog['summary'])
    ? $maintLog['summary']
    : ['test' => '', 'prod' => '', 'errors' => [], 'forced' => false];
$maintStatusKeys = [
    'refreshed'       => 'COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_REFRESHED',
    'current'         => 'COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_CURRENT',
    'no_credentials'  => 'COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_NO_CREDENTIALS',
    'auth_failed'     => 'COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_AUTH_FAILED',
    'exception'       => 'COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_EXCEPTION',
    'skipped'         => 'COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_SKIPPED',
];
$maintLabel = static function (string $code) use ($maintStatusKeys): string {
    $code = trim($code);
    if ($code !== '' && isset($maintStatusKeys[$code])) {
        return Text::_($maintStatusKeys[$code]);
    }

    return $code !== '' ? $code : Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_STATUS_UNKNOWN');
};
$maintAtDisplay = '';
if ($maintAt !== '') {
    try {
        $maintAtDisplay = HTMLHelper::_('date', $maintAt, Text::_('DATE_FORMAT_LC2'));
    } catch (\Throwable $e) {
        $maintAtDisplay = $maintAt;
    }
}
?>
<div class="card mb-3 border-info">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="fas fa-history"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_LOG_TITLE'); ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if ($maintAt === '') : ?>
            <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_LOG_NEVER'); ?></p>
        <?php else : ?>
            <p class="mb-2">
                <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_LOG_AT'); ?>:</strong>
                <?php echo htmlspecialchars($maintAtDisplay, ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <?php if (!empty($maintSummary['forced'])) : ?>
                <p class="small text-warning mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_FORCED'); ?></p>
            <?php endif; ?>
            <ul class="mb-2 ps-3">
                <li>
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_LOG_TEST'); ?>:</strong>
                    <?php echo htmlspecialchars($maintLabel((string) ($maintSummary['test'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                </li>
                <li>
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_LOG_PROD'); ?>:</strong>
                    <?php echo htmlspecialchars($maintLabel((string) ($maintSummary['prod'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                </li>
            </ul>
            <?php
            $merrs = isset($maintSummary['errors']) && \is_array($maintSummary['errors']) ? $maintSummary['errors'] : [];
            if ($merrs !== []) :
                ?>
                <div class="alert alert-warning py-2 mb-0">
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_TOKEN_MAINTAIN_LOG_ERRORS'); ?></strong>
                    <ul class="mb-0 mt-1 ps-3 small">
                        <?php foreach ($merrs as $me) : ?>
                            <li><?php echo htmlspecialchars((string) $me, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.saveCertificadorFact'); ?>" method="post" name="adminForm" id="ajustes-certificador-fact-form" class="form-validate">
    <?php echo HTMLHelper::_('form.token'); ?>
    <div class="card mb-4 border-secondary">
        <div class="card-body">
            <label class="form-label fw-semibold mb-2 d-block" for="fel_certificador_modo_switch">
                <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_MODO_LABEL'); ?>
            </label>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span id="fel_modo_label_test" class="small<?php echo $felModo === 'test' ? ' text-primary fw-semibold' : ' text-muted'; ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_MODO_PRUEBA'); ?>
                </span>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="fel_certificador_modo_switch"
                           style="width: 2.75rem; height: 1.35rem; cursor: pointer;"
                           <?php echo $felModo === 'prod' ? 'checked ' : ''; ?>
                           aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_MODO_SWITCH_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <span id="fel_modo_label_prod" class="small<?php echo $felModo === 'prod' ? ' text-primary fw-semibold' : ' text-muted'; ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_MODO_PRODUCCION'); ?>
                </span>
                <span class="vr d-none d-sm-inline align-self-stretch mx-1" style="min-height: 1.5rem;"></span>
                <span id="fel_frontend_debug_label" class="small<?php echo $felFrontendDebug ? ' text-primary fw-semibold' : ' text-muted'; ?>">
                    <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_FRONTEND_DEBUG_LABEL'); ?>
                </span>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="fel_certificador_frontend_debug_switch"
                           style="width: 2.75rem; height: 1.35rem; cursor: pointer;"
                           <?php echo $felFrontendDebug ? 'checked ' : ''; ?>
                           aria-label="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_FRONTEND_DEBUG_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <input type="hidden" name="jform[certificador_frontend_debug]" id="fel_certificador_frontend_debug_value"
                       value="<?php echo $felFrontendDebug ? '1' : '0'; ?>">
                <input type="hidden" name="jform[certificador_modo]" id="fel_certificador_modo_value"
                       value="<?php echo htmlspecialchars($felModo, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <p class="form-text mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_MODO_HELP'); ?></p>
            <p class="form-text mb-0 mt-1"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_FRONTEND_DEBUG_HELP'); ?></p>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary btn-sm" id="fel-test-certificador-auth">
                    <i class="fas fa-plug"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_CONNECTION'); ?>
                </button>
                <span class="text-muted small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_CONNECTION_HINT'); ?></span>
            </div>
            <div id="fel-test-certificador-result" class="mt-2 small" hidden></div>
        </div>
    </div>
    <?php
    $renderSection('test', 'COM_ORDENPRODUCCION_CERTIFICADOR_FACT_SECTION_PRUEBA', 'flask');
    $renderSection('prod', 'COM_ORDENPRODUCCION_CERTIFICADOR_FACT_SECTION_PRODUCCION', 'industry');
    ?>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i>
            <?php echo Text::_('JSAVE'); ?>
        </button>
    </div>
</form>
<script>
(function () {
    var sw = document.getElementById('fel_certificador_modo_switch');
    var hidden = document.getElementById('fel_certificador_modo_value');
    var lt = document.getElementById('fel_modo_label_test');
    var lp = document.getElementById('fel_modo_label_prod');
    if (!sw || !hidden) {
        return;
    }
    function syncLabels() {
        var prod = sw.checked;
        hidden.value = prod ? 'prod' : 'test';
        if (lt) {
            lt.className = 'small' + (prod ? ' text-muted' : ' text-primary fw-semibold');
        }
        if (lp) {
            lp.className = 'small' + (prod ? ' text-primary fw-semibold' : ' text-muted');
        }
    }
    sw.addEventListener('change', syncLabels);
    syncLabels();
})();
(function () {
    var sw = document.getElementById('fel_certificador_frontend_debug_switch');
    var hidden = document.getElementById('fel_certificador_frontend_debug_value');
    var lbl = document.getElementById('fel_frontend_debug_label');
    if (!sw || !hidden) {
        return;
    }
    function syncDbg() {
        var on = sw.checked;
        hidden.value = on ? '1' : '0';
        if (lbl) {
            lbl.className = 'small' + (on ? ' text-primary fw-semibold' : ' text-muted');
        }
    }
    sw.addEventListener('change', syncDbg);
    syncDbg();
})();
(function () {
    var btn = document.getElementById('fel-test-certificador-auth');
    var form = document.getElementById('ajustes-certificador-fact-form');
    var box = document.getElementById('fel-test-certificador-result');
    if (!btn || !form || !box) {
        return;
    }
    var testUrl = <?php echo json_encode($testAuthTaskUrl); ?>;
    var busyText = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_BUSY')); ?>;
    var lblExp = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_EXPIRES')); ?>;
    var lblGranted = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_GRANTED')); ?>;
    var invalidResp = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_INVALID_RESPONSE')); ?>;
    var networkErr = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_CERTIFICADOR_FACT_TEST_NETWORK')); ?>;
    var origHtml = btn.innerHTML;
    btn.addEventListener('click', function () {
        var fd = new FormData(form);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + busyText;
        box.hidden = false;
        box.className = 'mt-2 small alert alert-secondary py-2 mb-0';
        box.textContent = '';
        fetch(testUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                if (!j || typeof j.success === 'undefined') {
                    box.className = 'mt-2 small alert alert-danger py-2 mb-0';
                    box.textContent = invalidResp;

                    return;
                }
                if (j.success) {
                    box.className = 'mt-2 small alert alert-success py-2 mb-0';
                    box.textContent = '';
                    var p = document.createElement('p');
                    p.className = 'mb-1 fw-semibold';
                    p.textContent = j.message || '';
                    box.appendChild(p);
                    if (j.data && j.data.token) {
                        var pre = document.createElement('pre');
                        pre.className = 'mb-0 mt-1 small text-break user-select-all';
                        pre.style.whiteSpace = 'pre-wrap';
                        pre.style.maxHeight = '12rem';
                        pre.style.overflow = 'auto';
                        pre.textContent = j.data.token;
                        box.appendChild(pre);
                    }
                    if (j.data && j.data.expira_en) {
                        var d = document.createElement('div');
                        d.className = 'mt-1 text-muted';
                        d.textContent = lblExp + ': ' + j.data.expira_en;
                        box.appendChild(d);
                    }
                    if (j.data && j.data.otorgado_a) {
                        var g = document.createElement('div');
                        g.className = 'mt-1 text-muted';
                        g.textContent = lblGranted + ': ' + j.data.otorgado_a;
                        box.appendChild(g);
                    }

                    return;
                }
                box.className = 'mt-2 small alert alert-danger py-2 mb-0';
                box.textContent = j.message || 'Error';
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                box.className = 'mt-2 small alert alert-danger py-2 mb-0';
                box.textContent = networkErr;
            });
    });
})();
</script>
