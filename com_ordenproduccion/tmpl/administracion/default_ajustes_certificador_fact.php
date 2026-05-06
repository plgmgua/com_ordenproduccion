<?php
/**
 * Ajustes > Certificador de Fact: URLs and credentials (test + production).
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

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=administracion.saveCertificadorFact'); ?>" method="post" name="adminForm" id="ajustes-certificador-fact-form" class="form-validate">
    <?php echo HTMLHelper::_('form.token'); ?>
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
