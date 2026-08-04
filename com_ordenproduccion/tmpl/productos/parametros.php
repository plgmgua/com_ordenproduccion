<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Productos\HtmlView $this */

$baseUrl = 'index.php?option=com_ordenproduccion&view=productos';
$basePliegos = $baseUrl . '&section=pliegos';
$baseElementos = $baseUrl . '&section=elementos';
$baseParametros = $baseUrl . '&section=parametros';
$baseOfertas = $baseUrl . '&section=ofertas';
$baseEnvios = $baseUrl . '&section=envios';
$baseAjustes = $baseUrl . '&section=ajustes&tab=ajustes_cotizacion';
$baseTarjetaCredito = $baseUrl . '&section=tarjeta_credito';

$l = function ($key, $fallback) {
    $t = Text::_($key);
    return ($t === $key) ? $fallback : $t;
};

$margen = isset($this->margenGanancia) ? (float) $this->margenGanancia : 0;
$iva = isset($this->iva) ? (float) $this->iva : 0;
$isr = isset($this->isr) ? (float) $this->isr : 0;
$comisionVenta = isset($this->comisionVenta) ? (float) $this->comisionVenta : 0;
$comisionMargenAdicional = isset($this->comisionMargenAdicional) ? (float) $this->comisionMargenAdicional : 0;
$impuestoImprenta = isset($this->impuestoImprenta) ? (float) $this->impuestoImprenta : 0;
$impuestoImprentaEtiqueta = isset($this->impuestoImprentaEtiqueta) ? (string) $this->impuestoImprentaEtiqueta : '';
$impuestoImprentaPalabras = isset($this->impuestoImprentaPalabras) && \is_array($this->impuestoImprentaPalabras)
    ? $this->impuestoImprentaPalabras
    : \Grimpsa\Component\Ordenproduccion\Site\Helper\ImpuestoImprentaHelper::getDefaultKeywords();
$impuestoImprentaPalabrasJson = json_encode(array_values($impuestoImprentaPalabras), JSON_UNESCAPED_UNICODE);
$imprentaParamsOk = !isset($this->imprentaParametrosConfigured) || !empty($this->imprentaParametrosConfigured);
?>
<div class="com-ordenproduccion-productos">
    <div class="container-fluid">
        <h1 class="page-title"><?php echo $l('COM_ORDENPRODUCCION_ADMIN_IMPRENTA_TITLE', 'Administración de Imprenta'); ?></h1>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($basePliegos . '&tab=sizes'); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_PLIEGOS', 'Pliegos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseElementos); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_ELEMENTOS', 'Elementos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="<?php echo Route::_($baseParametros); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_PARAMETROS', 'Parámetros'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseOfertas); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_OFERTAS', 'Ofertas'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseEnvios); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_ENVIOS', 'Envíos'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseAjustes); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_TAB_AJUSTES', 'Ajustes'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo Route::_($baseTarjetaCredito); ?>">
                    <?php echo $l('COM_ORDENPRODUCCION_PRODUCTOS_SECTION_TARJETA_CREDITO', 'Tarjeta de Crédito'); ?>
                </a>
            </li>
        </ul>

        <?php if (!$imprentaParamsOk) : ?>
        <div class="alert alert-danger mb-3" role="alert">
            <?php echo htmlspecialchars(\Grimpsa\Component\Ordenproduccion\Site\Helper\ImprentaParametrosHelper::getAdminWarningMessage()); ?>
        </div>
        <?php endif; ?>

        <div class="parametros-form-wrap">
            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=productos&section=parametros'); ?>"
                  method="post" name="adminForm" id="adminForm" class="form-horizontal">
                <input type="hidden" name="task" value="productos.saveParametros" />
                <?php echo HTMLHelper::_('form.token'); ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="param_margen_ganancia" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA', 'Margen de Ganancia'); ?> (%)
                            </label>
                            <input type="number" name="margen_ganancia" id="param_margen_ganancia"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $margen, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_iva" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_IVA', 'IVA'); ?> (%)
                            </label>
                            <input type="number" name="iva" id="param_iva"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $iva, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_isr" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_ISR', 'ISR'); ?> (%)
                            </label>
                            <input type="number" name="isr" id="param_isr"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $isr, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_comision_venta" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_COMISION_VENTA', 'Comisión de venta'); ?> (%)
                            </label>
                            <input type="number" name="comision_venta" id="param_comision_venta"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $comisionVenta, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_comision_margen_adicional" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_COMISION_MARGEN_ADICIONAL', 'Comisión de margen adicional'); ?> (%)
                            </label>
                            <input type="number" name="comision_margen_adicional" id="param_comision_margen_adicional"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $comisionMargenAdicional, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="param_impuesto_imprenta" class="form-label">
                                <?php echo $l('COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA', 'Impuesto de imprenta'); ?> (%)
                            </label>
                            <input type="number" name="impuesto_imprenta" id="param_impuesto_imprenta"
                                   class="form-control" min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars((string) $impuestoImprenta, ENT_QUOTES, 'UTF-8'); ?>" />
                            <div class="form-text">
                                <?php echo $l(
                                    'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA_DESC',
                                    'Applied on cotización save when a line description matches a configured keyword or phrase below. The % is calculated on the linked pre-cotización total and added as a separate cotización line.'
                                ); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="param_impuesto_imprenta_etiqueta" class="form-label">
                                <?php echo $l(
                                    'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA_ETIQUETA',
                                    'Etiqueta de Impuesto de imprenta'
                                ); ?>
                            </label>
                            <input type="text" name="impuesto_imprenta_etiqueta" id="param_impuesto_imprenta_etiqueta"
                                   class="form-control" maxlength="255"
                                   value="<?php echo htmlspecialchars($impuestoImprentaEtiqueta, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="<?php echo htmlspecialchars(
                                       $l('COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA', 'Impuesto de imprenta'),
                                       ENT_QUOTES,
                                       'UTF-8'
                                   ); ?>" />
                            <div class="form-text">
                                <?php echo $l(
                                    'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA_ETIQUETA_DESC',
                                    'Texto mostrado en la línea de impuesto de imprenta de la cotización. Si se deja vacío, se usa «Impuesto de imprenta».'
                                ); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="param_impuesto_imprenta_palabras_input" class="form-label">
                                <?php echo $l(
                                    'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA_PALABRAS',
                                    'Palabras y frases afectas al impuesto de imprenta'
                                ); ?>
                            </label>
                            <div id="param_impuesto_imprenta_palabras_tags"
                                 class="form-control d-flex flex-wrap align-items-center gap-1 py-2"
                                 style="min-height: 2.75rem; height: auto;">
                                <input type="text"
                                       id="param_impuesto_imprenta_palabras_input"
                                       class="border-0 flex-grow-1"
                                       style="min-width: 12rem; outline: none;"
                                       autocomplete="off"
                                       placeholder="<?php echo htmlspecialchars(
                                           $l(
                                               'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA_PALABRAS_PLACEHOLDER',
                                               'Escriba una palabra o frase y presione Enter'
                                           ),
                                           ENT_QUOTES,
                                           'UTF-8'
                                       ); ?>" />
                            </div>
                            <input type="hidden"
                                   name="impuesto_imprenta_palabras"
                                   id="param_impuesto_imprenta_palabras"
                                   value="<?php echo htmlspecialchars((string) $impuestoImprentaPalabrasJson, ENT_QUOTES, 'UTF-8'); ?>" />
                            <div class="form-text">
                                <?php echo $l(
                                    'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA_PALABRAS_DESC',
                                    'Presione Enter para agregar cada palabra o frase. Se buscarán en la descripción de las líneas de cotización al guardar.'
                                ); ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <?php echo Text::_('JSAVE'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
.com-ordenproduccion-productos .impuesto-imprenta-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    background: #e9ecef;
    font-size: 0.875rem;
    line-height: 1.4;
}
.com-ordenproduccion-productos .impuesto-imprenta-tag-remove {
    border: 0;
    background: transparent;
    color: #6c757d;
    padding: 0;
    line-height: 1;
    cursor: pointer;
}
.com-ordenproduccion-productos .impuesto-imprenta-tag-remove:hover {
    color: #212529;
}
</style>
<script>
(function () {
    var container = document.getElementById('param_impuesto_imprenta_palabras_tags');
    var input = document.getElementById('param_impuesto_imprenta_palabras_input');
    var hidden = document.getElementById('param_impuesto_imprenta_palabras');
    if (!container || !input || !hidden) {
        return;
    }

    var tags = [];

    function normalizeTag(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function syncHidden() {
        hidden.value = JSON.stringify(tags);
    }

    function renderTags() {
        container.querySelectorAll('.impuesto-imprenta-tag').forEach(function (el) {
            el.remove();
        });

        tags.forEach(function (tag, index) {
            var badge = document.createElement('span');
            badge.className = 'impuesto-imprenta-tag';
            badge.dataset.index = String(index);

            var label = document.createElement('span');
            label.textContent = tag;

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'impuesto-imprenta-tag-remove';
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.textContent = '\u00d7';
            removeBtn.addEventListener('click', function () {
                tags.splice(index, 1);
                renderTags();
            });

            badge.appendChild(label);
            badge.appendChild(removeBtn);
            container.insertBefore(badge, input);
        });

        syncHidden();
    }

    function addTag(rawValue) {
        var value = normalizeTag(rawValue);
        if (!value) {
            return;
        }

        var exists = tags.some(function (tag) {
            return tag.toLowerCase() === value.toLowerCase();
        });
        if (exists) {
            input.value = '';
            return;
        }

        tags.push(value);
        input.value = '';
        renderTags();
    }

    try {
        var initial = JSON.parse(hidden.value || '[]');
        if (Array.isArray(initial)) {
            var seen = {};
            tags = initial.map(normalizeTag).filter(function (value) {
                if (value === '') {
                    return false;
                }
                var key = value.toLowerCase();
                if (seen[key]) {
                    return false;
                }
                seen[key] = true;
                return true;
            });
        }
    } catch (e) {
        tags = [];
    }
    renderTags();

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addTag(input.value);
            return;
        }

        if (event.key === 'Backspace' && input.value === '' && tags.length) {
            tags.pop();
            renderTags();
        }
    });

    input.addEventListener('blur', function () {
        if (normalizeTag(input.value) !== '') {
            addTag(input.value);
        }
    });
})();
</script>
