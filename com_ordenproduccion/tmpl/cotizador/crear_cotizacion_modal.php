<?php

/**
 * Barra + modal: crear cotización (misma URL y parámetros que Mis Clientes).
 *
 * @package     com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$userModal      = Factory::getUser();
$agentDisplay   = (!$userModal->guest && $userModal->name !== '') ? (string) $userModal->name : '';
$cotDestination = isset($cotDestUrlModal) ? (string) $cotDestUrlModal : '';
$preCotizacionParaCotUrl = isset($preCotizacionIdParaCotUrl) ? (int) $preCotizacionIdParaCotUrl : 0;

if ($userModal->guest || trim($cotDestination) === '') {
    return;
}

$tokenParts       = preg_split('/\s*=\s*/', (string) Session::getFormToken(), 2);
$tokenInputName   = isset($tokenParts[0]) ? trim($tokenParts[0]) : '';
$searchAjaxUrlRaw = Route::_('index.php?option=com_ordenproduccion&task=cliente.searchContactsForCotizacion&format=json', false);

?>
<div class="mt-3 crear-cotizacion-precot-wrap">
    <button type="button" class="btn btn-primary btn-sm mb-0"
            data-bs-toggle="modal"
            data-bs-target="#cotizadorCrearCotizacionModal">
        <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_COTIZADOR_BTN_CREAR_COTIZACION'); ?>
    </button>
</div>

<div class="modal fade" id="cotizadorCrearCotizacionModal" tabindex="-1"
     aria-labelledby="cotizadorCrearCotizacionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cotizadorCrearCotizacionModalTitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_TITLE'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="cotizador-buscar-cliente-input">
                        <?php echo Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_SEARCH_LABEL'); ?>
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="cotizador-buscar-cliente-input" autocomplete="off"
                               placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_SEARCH_HINT'), ENT_QUOTES, 'UTF-8'); ?>" />
                        <button type="button" class="btn btn-outline-secondary" id="cotizador-buscar-cliente-btn">
                            <?php echo Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_SEARCH_BTN'); ?>
                        </button>
                    </div>
                </div>
                <div id="cotizador-buscar-cliente-loading" class="small text-muted d-none"><?php echo Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_LOADING'); ?></div>
                <div id="cotizador-buscar-cliente-error" class="alert alert-danger d-none py-2 small" role="alert"></div>
                <div id="cotizador-buscar-cliente-results" class="list-group" style="max-height: 340px;"></div>
                <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_FOOTNOTE'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var cotizacionDestinationUrl = <?php echo json_encode($cotDestination); ?>;
    var agentName = <?php echo json_encode($agentDisplay); ?>;
    var searchUrl = <?php echo json_encode(html_entity_decode($searchAjaxUrlRaw, ENT_COMPAT, 'UTF-8')); ?>;
    var tokenName = <?php echo json_encode($tokenInputName); ?>;

    function openCotizacionPrecot(clientId, clientName, clientVat) {
        var url = cotizacionDestinationUrl || '';
        if (!url || (url.indexOf('http') !== 0 && url.indexOf('/') !== 0 && url.indexOf('index.') !== 0)) {
            window.alert('Configure la URL de cotización del componente (Global Configuration)');
            return;
        }
        url += (url.indexOf('?') !== -1) ? '&' : '?';
        url += 'client_id=' + encodeURIComponent(String(clientId));
        url += '&contact_name=' + encodeURIComponent(clientName || '');
        url += '&contact_vat=' + encodeURIComponent(clientVat || '');
        url += '&x_studio_agente_de_ventas=' + encodeURIComponent(agentName || '');
        var preIdPc = <?php echo (int) $preCotizacionParaCotUrl; ?>;
        if (preIdPc > 0) {
            url += '&precotizacion_id=' + encodeURIComponent(String(preIdPc));
        }
        window.open(url, '_blank');
    }

    function runSearch() {
        var inp = document.getElementById('cotizador-buscar-cliente-input');
        var loading = document.getElementById('cotizador-buscar-cliente-loading');
        var errEl = document.getElementById('cotizador-buscar-cliente-error');
        var out = document.getElementById('cotizador-buscar-cliente-results');
        var q = inp ? String(inp.value).trim() : '';
        errEl.textContent = '';
        errEl.classList.add('d-none');
        out.innerHTML = '';
        if (!tokenName) {
            errEl.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_ERR_TOKEN')); ?>;
            errEl.classList.remove('d-none');
            return;
        }
        loading.classList.remove('d-none');

        fetch(searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&' + encodeURIComponent(tokenName) + '=1')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loading.classList.add('d-none');
                if (!data || !data.success) {
                    errEl.textContent = (data && data.message)
                        ? data.message : <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_ERR_GENERIC')); ?>;
                    errEl.classList.remove('d-none');
                    return;
                }
                var list = data.contacts || [];
                if (list.length === 0) {
                    errEl.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_EMPTY')); ?>;
                    errEl.classList.remove('d-none');
                    return;
                }
                list.forEach(function (c) {
                    var lid = parseInt(String(c.id), 10) || 0;
                    var name = c.name ? String(c.name) : '';
                    var nit = c.vat != null ? String(c.vat) : '';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start text-start';

                    var main = document.createElement('div');
                    var t = document.createElement('strong');
                    t.textContent = name || ('#' + lid);
                    var sub = document.createElement('div');
                    sub.className = 'small text-muted';
                    sub.textContent = nit ? ('NIT / VAT: ' + nit) : '';
                    main.appendChild(t);
                    if (nit) {
                        main.appendChild(sub);
                    }

                    var hint = document.createElement('span');
                    hint.className = 'badge bg-primary align-self-center';
                    hint.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_OPEN_BTN')); ?>;

                    btn.appendChild(main);
                    btn.appendChild(hint);

                    btn.addEventListener('click', function () {
                        if (lid < 1) {
                            errEl.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_ERR_GENERIC')); ?>;
                            errEl.classList.remove('d-none');
                            return;
                        }
                        openCotizacionPrecot(lid, name, nit);
                        var modalEl = document.getElementById('cotizadorCrearCotizacionModal');
                        if (typeof bootstrap !== 'undefined' && modalEl) {
                            var inst = window.bootstrap.Modal.getInstance(modalEl);
                            if (inst) {
                                inst.hide();
                            }
                        }
                    });
                    out.appendChild(btn);
                });
            })
            .catch(function () {
                loading.classList.add('d-none');
                errEl.textContent = <?php echo json_encode(Text::_('COM_ORDENPRODUCCION_COTIZADOR_MODAL_CREAR_COTIZACION_ERR_GENERIC')); ?>;
                errEl.classList.remove('d-none');
            });
    }

    var searchBtn = document.getElementById('cotizador-buscar-cliente-btn');
    var searchInp = document.getElementById('cotizador-buscar-cliente-input');
    if (searchBtn) {
        searchBtn.addEventListener('click', runSearch);
    }
    if (searchInp) {
        searchInp.addEventListener('keydown', function (e) {
            if ((e.key || '') === 'Enter') {
                e.preventDefault();
                runSearch();
            }
        });
    }
})();
</script>
