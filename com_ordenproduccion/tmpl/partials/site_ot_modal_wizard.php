<?php

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// Load component language from package (covers tag-specific files) so Text::_ never shows raw keys on locales without merged INI.
$lang = Factory::getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

if (!\function_exists('op_ot_wizard_label')) {
    /**
     * Return translation or Spanish/English fallbacks when the INI key is missing (e.g. es-GT without override).
     */
    function op_ot_wizard_label(string $key, string $es, string $en): string
    {
        $t = Text::_($key);
        if ($t !== $key) {
            return $t;
        }
        $tag = strtolower(Factory::getLanguage()->getTag());
        if (\strpos($tag, 'en') === 0) {
            return $en;
        }

        return $es;
    }
}

/** @var array $wizardParams */
$params           = isset($wizardParams['params']) ? $wizardParams['params'] : ComponentHelper::getParams('com_ordenproduccion');
$user             = $wizardParams['user'] ?? Factory::getUser();
$submitReturnOnly = !empty($wizardParams['submit_mode_return']);
$returnUrl        = isset($wizardParams['return_url']) ? (string) $wizardParams['return_url'] : '';
$cotOtStep3Enabled = !empty($wizardParams['cotizacion_ot_step3_instructions']);
$cotOtSaveInstrUrl = isset($wizardParams['cot_ot_save_instrucciones_json_url'])
    ? (string) $wizardParams['cot_ot_save_instrucciones_json_url'] : '';
$cotOtCreateOrdenUrl = isset($wizardParams['cot_ot_create_orden_json_url'])
    ? (string) $wizardParams['cot_ot_create_orden_json_url'] : '';
$cotOtStepTotal    = $cotOtStep3Enabled ? 3 : 2;
$cotOtReturnUrlJs  = $submitReturnOnly && $returnUrl !== ''
    ? $returnUrl
    : Route::_('index.php?option=com_ordenproduccion&view=cotizaciones');
$step3BarLabel       = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_STEP3_PROGRESS',
    'Paso 3: Instrucciones por proceso',
    'Step 3: Process instructions'
);
$step3Intro          = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_STEP3_INFO',
    'Revise y edite las instrucciones por proceso para esta línea. Se guardarán al crear la orden de trabajo.',
    'Review and edit process instructions for this line. They will be saved when you create the work order.'
);
$step3EmptyFallback  = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_STEP3_NO_FIELDS',
    'No se encontraron campos de instrucción para esta pre-cotización.',
    'No instruction fields found for this pre-quotation.'
);
$otStep3FechaLabel   = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_STEP3_FECHA_ENTREGA',
    'Fecha de entrega',
    'Delivery date'
);
$otStep3InstrLabel   = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_STEP3_INSTRUCCIONES',
    'Instrucciones',
    'Instructions'
);
$otProgressDeliver   = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_PROGRESS_STEP1_DELIVERY',
    'Paso 1: Dirección de entrega',
    'Step 1: Delivery address'
);
$otProgressContact   = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_PROGRESS_STEP2_CONTACT',
    'Paso 2: Persona de contacto',
    'Step 2: Contact person'
);
$otWizardModalTitle  = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_MODAL_TITLE',
    'Crear orden de trabajo',
    'Create work order'
);
$otWizardSubmitBtn   = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_OT_WIZARD_SUBMIT_BTN',
    'Crear orden de trabajo',
    'Create work order'
);
$otWizardNextBtn     = op_ot_wizard_label(
    'COM_ORDENPRODUCCION_CONFIRMAR_NEXT',
    'Siguiente',
    'Next'
);
$otWizardLangIsEn    = (strpos(strtolower(Factory::getLanguage()->getTag()), 'en') === 0);
$otStepIndicatorInitial = $otWizardLangIsEn
    ? sprintf('(Step 1 of %d)', (int) $cotOtStepTotal)
    : sprintf('(Paso 1 de %d)', (int) $cotOtStepTotal);

?>
<!-- OT (Orden de Trabajo) Modal — same UI as Mis Clientes (wizard) -->
<div class="modal fade" id="otModal" tabindex="-1" aria-labelledby="otModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="otModalLabel">
                    <i class="fas fa-truck"></i> <?php echo htmlspecialchars($otWizardModalTitle, ENT_QUOTES, 'UTF-8'); ?> <span id="otStepIndicator"><?php echo htmlspecialchars($otStepIndicatorInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Progress Bar -->
                <div class="progress mb-4" style="height: 25px;">
                    <div id="otProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100">
                        <?php echo htmlspecialchars($otProgressDeliver, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                
                <!-- Credit Limit Banner -->
                <div id="otCreditLimitBanner" class="alert alert-info mb-3" style="display: none; height: 25px; padding: 0.25rem 0.75rem; line-height: 1.5; align-items: center;">
                    <i class="fas fa-credit-card" style="margin-right: 0.5rem;"></i>
                    <strong>Límite de Crédito:</strong> 
                    <span id="otCreditLimitAmount" style="margin-left: 0.25rem;">-</span>
                </div>
                
                <!-- Step 1: Delivery Information -->
                <div id="otStep1" style="display: block;">
                    <div class="alert alert-info" style="height: 25px; padding: 0.25rem 0.75rem; line-height: 1.5; display: flex; align-items: center;">
                        <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                        Seleccione la dirección de entrega y agregue instrucciones.
                    </div>
                    
                    <!-- Client Information (Read-only) -->
                    <div class="card mb-3">
                        <div class="card-body" style="padding: 0.5rem 1rem;">
                            <div class="row mb-1">
                                <div class="col-3">
                                    <strong>Cliente:</strong>
                                </div>
                                <div class="col-9">
                                    <span id="otClientName"></span>
                                </div>
                            </div>
                            <div class="row mb-0">
                                <div class="col-3">
                                    <strong>NIT:</strong>
                                </div>
                                <div class="col-9">
                                    <span id="otClientVat"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delivery Type Selection -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-shipping-fast"></i> Tipo de Entrega *
                        </label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="otDeliveryType" id="otDeliveryTypeDomicilio" value="domicilio" checked>
                            <label class="btn btn-outline-primary" for="otDeliveryTypeDomicilio">
                                <i class="fas fa-truck"></i> Entrega a Domicilio
                            </label>
                            
                            <input type="radio" class="btn-check" name="otDeliveryType" id="otDeliveryTypeRecoger" value="recoger">
                            <label class="btn btn-outline-success" for="otDeliveryTypeRecoger">
                                <i class="fas fa-store"></i> Recoger en Oficina
                            </label>
                        </div>
                        <div class="form-text">
                            Seleccione si desea entrega a domicilio o recoger en nuestras instalaciones.
                        </div>
                    </div>
                    
                    <!-- Delivery Address Container (shown only for domicilio) -->
                    <div id="otDeliveryAddressContainer">
                        <!-- Delivery Address Selection -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Dirección de Entrega
                            </label>
                            <div id="otDeliveryAddressList" class="list-group" style="max-height: 200px; overflow-y: auto;">
                                <div class="text-center text-muted p-3">
                                    Cargando direcciones...
                                </div>
                            </div>
                            <div class="form-text">
                                Seleccione una dirección existente o cree una nueva.
                            </div>
                        </div>
                    
                    <!-- Button to show new address form -->
                    <div class="text-center mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="otShowNewAddressBtn" onclick="toggleNewAddressForm()">
                            <i class="fas fa-plus"></i> Crear una nueva dirección de entrega
                        </button>
                    </div>
                    
                    <!-- Manual Delivery Address Input -->
                    <div id="otNewAddressForm" class="card mb-3" style="display: none;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-map-marker-alt"></i> Ingresar Nueva Dirección de Entrega</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="otManualAddressName" class="form-label">Nombre de Dirección *</label>
                                        <input type="text" id="otManualAddressName" class="form-control" 
                                               placeholder="Ej: Bodega Central, Oficina Principal, etc." />
                                        <div class="form-text">
                                            Ingrese un nombre descriptivo para esta dirección.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="otManualStreet" class="form-label">Dirección *</label>
                                        <input type="text" id="otManualStreet" class="form-control" 
                                               placeholder="Calle, número, zona, etc." />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="otManualCity" class="form-label">Ciudad *</label>
                                        <input type="text" id="otManualCity" class="form-control" 
                                               placeholder="Ciudad" />
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="otSaveAddressToOdoo" value="1">
                                <label class="form-check-label" for="otSaveAddressToOdoo">
                                    <i class="fas fa-save"></i> Agregar dirección a cliente
                                </label>
                                <div class="form-text">
                                    Marque esta opción para guardar esta dirección como hija del cliente en Odoo.
                                </div>
                            </div>
                            <div id="otSaveAddressButtonContainer" style="display: none;">
                                <button type="button" class="btn btn-success btn-sm" onclick="saveDeliveryAddressNow()">
                                    <i class="fas fa-save"></i> Guardar Dirección
                                </button>
                            </div>
                        </div>
                    </div>
                    </div><!-- End Delivery Address Container -->
                    
                    <!-- Delivery Instructions -->
                    <div class="mb-3">
                        <label for="otDeliveryInstructions" class="form-label">
                            <i class="fas fa-clipboard-list"></i> Instrucciones de Entrega
                        </label>
                        <textarea id="otDeliveryInstructions" class="form-control" rows="4" 
                                  placeholder="Ingrese instrucciones especiales para la entrega..."></textarea>
                        <div class="form-text">
                            Opcional: Agregue cualquier instrucción especial para la entrega (horario, contacto, etc.)
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Contact Selection -->
                <div id="otStep2" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Seleccione o ingrese la persona de contacto para esta orden de trabajo.
                    </div>
                    
                    <!-- Contact Selection List -->
                    <div id="otContactListSection" class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user-tie"></i> Persona de Contacto
                        </label>
                        <div id="otContactList" class="list-group" style="max-height: 200px; overflow-y: auto;">
                            <div class="text-center text-muted p-3">
                                Cargando contactos...
                            </div>
                        </div>
                        <div class="form-text">
                            Seleccione una persona de contacto existente o cree una nueva.
                        </div>
                    </div>
                    
                    <!-- Button to show new contact form -->
                    <div class="text-center mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="otShowNewContactBtn" onclick="toggleNewContactForm()">
                            <i class="fas fa-plus"></i> Crear un nuevo contacto
                        </button>
                    </div>
                    
                    <!-- Manual Contact Input -->
                    <div id="otNewContactForm" class="card mb-3" style="display: none;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user-plus"></i> Ingresar Nuevo Contacto</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="otManualContactName" class="form-label">Nombre *</label>
                                        <input type="text" id="otManualContactName" class="form-control" 
                                               placeholder="Nombre de la persona de contacto" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="otManualContactPhone" class="form-label">Teléfono *</label>
                                        <input type="tel" id="otManualContactPhone" class="form-control" 
                                               placeholder="Teléfono de contacto" />
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="otSaveContactToOdoo" value="1">
                                <label class="form-check-label" for="otSaveContactToOdoo">
                                    <i class="fas fa-save"></i> Agregar contacto a cliente
                                </label>
                                <div class="form-text">
                                    Marque esta opción para guardar este contacto como hijo del cliente en Odoo.
                                </div>
                            </div>
                            <div id="otSaveContactButtonContainer" style="display: none;">
                                <button type="button" class="btn btn-success btn-sm" onclick="saveContactNow()">
                                    <i class="fas fa-save"></i> Guardar Contacto
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary Card -->
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-clipboard-check"></i> Resumen de Orden de Trabajo</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Dirección de Entrega:</strong> <span id="otSummaryAddress"></span></p>
                            <p class="mb-0"><strong>Instrucciones:</strong> <span id="otSummaryInstructions"></span></p>
                        </div>
                    </div>
                </div>
                <?php if ($cotOtStep3Enabled) : ?>
                <div id="otStep3" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <?php echo htmlspecialchars($step3Intro); ?>
                    </div>
                    <div class="mb-3">
                        <label for="otStep3FechaEntrega" class="form-label"><?php echo htmlspecialchars($otStep3FechaLabel, ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="date" class="form-control" id="otStep3FechaEntrega" autocomplete="off" />
                    </div>
                    <div id="otStep3InstructionsRoot" class="ot-step3-instructions-root"></div>
                    <p id="otStep3NoFields" class="text-muted small mb-0" style="display: none;"></p>
                    <div class="mb-0 mt-3">
                        <label for="otStep3InstruccionesGenerales" class="form-label"><?php echo htmlspecialchars($otStep3InstrLabel, ENT_QUOTES, 'UTF-8'); ?></label>
                        <textarea class="form-control" id="otStep3InstruccionesGenerales" rows="3" placeholder=""></textarea>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Hidden fields for form data -->
                <input type="hidden" id="otClientId" value="" />
                <input type="hidden" id="otSelectedStreet" value="" />
                <input type="hidden" id="otSelectedCity" value="" />
                <input type="hidden" id="otSelectedContactName" value="" />
                <input type="hidden" id="otSelectedContactPhone" value="" />
                <input type="hidden" id="otAgentName" value="<?php echo htmlspecialchars($user->name); ?>" />
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" id="otBtnBack" class="btn btn-outline-secondary" onclick="goOtWizardBack()" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Atrás
                </button>
                <button type="button" id="otBtnNext" class="btn btn-primary" onclick="goToStep2()">
                    <i class="fas fa-arrow-right"></i> Siguiente
                </button>
                <?php if ($cotOtStep3Enabled) : ?>
                <button type="button" id="otBtnNextStep2" class="btn btn-primary" onclick="goToStep3()" style="display: none;">
                    <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($otWizardNextBtn, ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <?php endif; ?>
                <button type="button" id="otBtnSubmit" class="btn btn-success" onclick="submitOT()" style="display: none;">
                    <i class="fas fa-check"></i> <?php echo htmlspecialchars($otWizardSubmitBtn, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
var otChildContacts = [];
var otDestinationUrl = <?php echo json_encode((string) $params->get('ot_destination_url', '')); ?>;
var cotizacionDestinationUrl = <?php echo json_encode((string) $params->get('cotizacion_destination_url', '')); ?>;
var oteDestinationUrl = <?php echo json_encode((string) $params->get('ote_destination_url', '')); ?>;
var otDebugMode = <?php echo $params->get('enable_debug', 0) ? 'true' : 'false'; ?>;
var comOpOtWizardStepTotal = <?php echo (int) $cotOtStepTotal; ?>;
var comOpOtUseThreeSteps = <?php echo $cotOtStep3Enabled ? 'true' : 'false'; ?>;
var comOpOtSaveInstruccionesJsonUrl = <?php echo json_encode($cotOtSaveInstrUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var comOpOtCreateOrdenJsonUrl = <?php echo json_encode($cotOtCreateOrdenUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var comOpOtReturnAfterSubmitUrl = <?php echo json_encode($cotOtReturnUrlJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var cotOtSubmitReturnOnly = <?php echo $submitReturnOnly ? 'true' : 'false'; ?>;
var comOpOtFormTokenName = <?php echo json_encode(Session::getFormToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var comOpOtWizardPreId = 0;
var otWizardCurrentStep = 1;
var otWizardProgressLabels = <?php echo json_encode([
    $otProgressDeliver,
    $otProgressContact,
    $step3BarLabel,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var otWizardLangIsEn = <?php echo json_encode((bool) $otWizardLangIsEn); ?>;
var otStep3MissingBlockMsg = <?php echo json_encode($step3EmptyFallback); ?>;

function showNotification(message, type) {
    // Create notification element
    var notification = document.createElement('div');
    notification.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.innerHTML = message;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Auto-remove after 4 seconds
    setTimeout(function() {
        notification.classList.remove('show');
        setTimeout(function() {
            notification.remove();
        }, 150);
    }, 4000);
}
function opOtGetBootstrapModalCtor() {
    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
        return window.bootstrap.Modal;
    }
    /* global bootstrap — some bundles bind only globally */
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        return bootstrap.Modal;
    }
    return null;
}

function opOtShowOtModalBackdrop() {
    var modalEl = document.getElementById('otModal');
    if (!modalEl) {
        return false;
    }
    var ModalCtor = opOtGetBootstrapModalCtor();
    if (ModalCtor && typeof ModalCtor.getOrCreateInstance === 'function') {
        ModalCtor.getOrCreateInstance(modalEl).show();
        return true;
    }
    /* Fallback if Bootstrap bundle is not globally exposed yet */
    modalEl.classList.add('show');
    modalEl.setAttribute('aria-hidden', 'false');
    modalEl.style.display = 'block';
    modalEl.style.paddingRight = '0px';
    document.body.classList.add('modal-open');
    return false;
}
function opOtHideOtModal() {
    var modalEl = document.getElementById('otModal');
    if (!modalEl) {
        return;
    }
    var ModalCtor = opOtGetBootstrapModalCtor();
    if (ModalCtor && typeof ModalCtor.getOrCreateInstance === 'function') {
        var inst = ModalCtor.getInstance(modalEl) || ModalCtor.getOrCreateInstance(modalEl);
        if (inst && typeof inst.hide === 'function') {
            inst.hide();
            return;
        }
    }
    modalEl.classList.remove('show');
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.style.display = 'none';
    document.body.classList.remove('modal-open');
}
function otWizardApplyStepUi(step) {
    var total = comOpOtWizardStepTotal || 2;
    var ind = document.getElementById('otStepIndicator');
    if (ind) {
        ind.textContent = otWizardLangIsEn
            ? ('(Step ' + step + ' of ' + total + ')')
            : ('(Paso ' + step + ' de ' + total + ')');
    }
    var bar = document.getElementById('otProgressBar');
    if (!bar || !otWizardProgressLabels || !otWizardProgressLabels.length) {
        return;
    }
    if (total <= 2) {
        if (step === 1) {
            bar.style.width = '50%';
            bar.textContent = otWizardProgressLabels[0] || '';
        } else {
            bar.style.width = '100%';
            bar.textContent = otWizardProgressLabels[1] || '';
        }
        return;
    }
    var pct = step === 1 ? '33%' : (step === 2 ? '66%' : '100%');
    bar.style.width = pct;
    bar.textContent = otWizardProgressLabels[step - 1] || '';
}
function opOtUnmountStep3InstructionsIfMounted() {
    var modal = document.getElementById('instruccionesOrdenModal');
    var modalBody = modal && modal.querySelector('.modal-body');
    var root = document.getElementById('otStep3InstructionsRoot');
    if (!modalBody || !root) {
        return;
    }
    var blocks = root.querySelectorAll('.instrucciones-orden-block');
    blocks.forEach(function(block) {
        block.classList.add('d-none');
        modalBody.appendChild(block);
    });
}
function opOtMountInstructionsIntoWizardStep3() {
    var root = document.getElementById('otStep3InstructionsRoot');
    var emptyEl = document.getElementById('otStep3NoFields');
    var modal = document.getElementById('instruccionesOrdenModal');
    if (!comOpOtUseThreeSteps || !root || !modal) {
        return;
    }
    var pid = parseInt(String(comOpOtWizardPreId || '0'), 10) || 0;
    root.innerHTML = '';
    if (emptyEl) {
        emptyEl.style.display = 'none';
        emptyEl.textContent = '';
    }
    if (!pid) {
        if (emptyEl) {
            emptyEl.textContent = otStep3MissingBlockMsg || '';
            emptyEl.style.display = '';
        }
        return;
    }
    var modalBody = modal.querySelector('.modal-body');
    var sel = modalBody.querySelector('.instrucciones-orden-block[data-pre-cotizacion-id="' + pid + '"]');
    if (!sel) {
        if (emptyEl) {
            emptyEl.textContent = otStep3MissingBlockMsg || '';
            emptyEl.style.display = '';
        }
        return;
    }
    sel.classList.remove('d-none');
    root.appendChild(sel);
}
function validateOtContactInputs() {
    var selectedRadio = document.querySelector('input[name="otContact"]:checked');
    var manualName = document.getElementById('otManualContactName').value.trim();
    var manualPhone = document.getElementById('otManualContactPhone').value.trim();
    if (selectedRadio) {
        return true;
    }
    return !!(manualName && manualPhone);
}
function openOTModal(clientId, clientName, clientVat, preCotizacionId) {
    preCotizacionId = parseInt(preCotizacionId === undefined || preCotizacionId === null ? '0' : preCotizacionId, 10);
    if (isNaN(preCotizacionId)) {
        preCotizacionId = 0;
    }
    comOpOtWizardPreId = preCotizacionId;
    otWizardCurrentStep = 1;
    opOtUnmountStep3InstructionsIfMounted();

    document.getElementById('otClientId').value = clientId;
    document.getElementById('otClientName').textContent = clientName;
    document.getElementById('otClientVat').textContent = clientVat || 'N/A';
    
    // Reset delivery type to domicilio
    document.getElementById('otDeliveryTypeDomicilio').checked = true;
    document.getElementById('otDeliveryAddressContainer').style.display = 'block';
    
    // Clear previous selections - delivery address
    document.getElementById('otDeliveryAddressList').innerHTML = '<div class="text-center text-muted p-3">Cargando direcciones...</div>';
    document.getElementById('otManualAddressName').value = '';
    document.getElementById('otManualStreet').value = '';
    document.getElementById('otManualCity').value = '';
    document.getElementById('otSaveAddressToOdoo').checked = false;
    document.getElementById('otDeliveryInstructions').value = '';
    var otFe = document.getElementById('otStep3FechaEntrega');
    var otIg = document.getElementById('otStep3InstruccionesGenerales');
    if (otFe) {
        otFe.value = '';
    }
    if (otIg) {
        otIg.value = '';
    }
    document.getElementById('otSaveAddressButtonContainer').style.display = 'none';
    
    // Hide new address form and reset button
    document.getElementById('otNewAddressForm').style.display = 'none';
    var showBtn = document.getElementById('otShowNewAddressBtn');
    if (showBtn) {
        showBtn.innerHTML = '<i class="fas fa-plus"></i> Crear una nueva dirección de entrega';
        showBtn.classList.remove('btn-outline-secondary');
        showBtn.classList.add('btn-outline-primary');
    }
    
    // Clear selected address data
    document.getElementById('otSelectedStreet').value = '';
    document.getElementById('otSelectedCity').value = '';
    
    // Clear radio button selections
    var radios = document.querySelectorAll('input[name="otDeliveryAddress"]');
    radios.forEach(function(radio) {
        radio.checked = false;
        if (radio.closest('.list-group-item')) {
            radio.closest('.list-group-item').classList.remove('active');
        }
    });
    
    // Clear previous selections - contact
    document.getElementById('otContactList').innerHTML = '<div class="text-center text-muted p-3">Cargando contactos...</div>';
    document.getElementById('otManualContactName').value = '';
    document.getElementById('otManualContactPhone').value = '';
    document.getElementById('otSaveContactToOdoo').checked = false;
    document.getElementById('otSaveContactButtonContainer').style.display = 'none';
    
    // Hide new contact form and reset button
    document.getElementById('otNewContactForm').style.display = 'none';
    var showContactBtn = document.getElementById('otShowNewContactBtn');
    if (showContactBtn) {
        showContactBtn.innerHTML = '<i class="fas fa-plus"></i> Crear un nuevo contacto';
        showContactBtn.classList.remove('btn-outline-secondary');
        showContactBtn.classList.add('btn-outline-primary');
    }
    
    // Clear selected contact data
    document.getElementById('otSelectedContactName').value = '';
    document.getElementById('otSelectedContactPhone').value = '';
    
    // Clear radio button selections
    var contactRadios = document.querySelectorAll('input[name="otContact"]');
    contactRadios.forEach(function(radio) {
        radio.checked = false;
        if (radio.closest('.list-group-item')) {
            radio.closest('.list-group-item').classList.remove('active');
        }
    });
    
    // Reset UI: Step 1
    document.getElementById('otStep1').style.display = 'block';
    document.getElementById('otStep2').style.display = 'none';
    var otStep3El = document.getElementById('otStep3');
    if (otStep3El) {
        otStep3El.style.display = 'none';
    }
    document.getElementById('otBtnNext').style.display = 'inline-block';
    document.getElementById('otBtnBack').style.display = 'none';
    document.getElementById('otBtnSubmit').style.display = 'none';
    var nt2 = document.getElementById('otBtnNextStep2');
    if (nt2) {
        nt2.style.display = 'none';
    }
    otWizardApplyStepUi(1);

    // Load child contacts and parent contact via AJAX
    loadChildContacts(clientId, clientName);
    
    // Load credit limit
    loadCreditLimit(clientId);
    
    // Show modal (Joomla/template may expose window.bootstrap.Modal instead of bootstrap)
    opOtShowOtModalBackdrop();
}

window.openOTModal = openOTModal;
(function () {
    var otRoot = document.getElementById('otModal');
    if (otRoot) {
        otRoot.addEventListener('hidden.bs.modal', function () {
            opOtUnmountStep3InstructionsIfMounted();
        });
    }
})();

// Load credit limit for the selected client
function loadCreditLimit(clientId) {
    // Hide banner initially
    var banner = document.getElementById('otCreditLimitBanner');
    var amountSpan = document.getElementById('otCreditLimitAmount');
    banner.style.display = 'none';
    
    // Make AJAX call to get credit limit
    fetch('<?php echo Route::_("index.php?option=com_ordenproduccion&task=cliente.getCreditLimit&format=json"); ?>&id=' + clientId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.credit_limit !== null && data.credit_limit !== undefined) {
                // Format the credit limit as currency
                var creditLimit = parseFloat(data.credit_limit);
                if (!isNaN(creditLimit) && creditLimit > 100) {
                    // Format as currency (Q for Quetzales, or adjust as needed)
                    var formattedAmount = new Intl.NumberFormat('es-GT', {
                        style: 'currency',
                        currency: 'GTQ',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(creditLimit);
                    
                    amountSpan.textContent = formattedAmount;
                    banner.style.display = 'flex';
                } else {
                    banner.style.display = 'none';
                }
            } else {
                banner.style.display = 'none';
            }
        })
        .catch(error => {
            if (otDebugMode) console.error('Error loading credit limit:', error);
            banner.style.display = 'none';
        });
}

// Load child contacts for the selected client
function loadChildContacts(clientId, parentName) {
    // Make AJAX call to get child contacts and parent contact info
    return Promise.all([
        fetch('<?php echo Route::_("index.php?option=com_ordenproduccion&task=cliente.getChildContacts&format=json"); ?>&id=' + clientId),
        fetch('<?php echo Route::_("index.php?option=com_ordenproduccion&task=cliente.getParentContact&format=json"); ?>&id=' + clientId)
    ]).then(responses => Promise.all(responses.map(r => r.json())))
      .then(data => {
          otChildContacts = data[0].data || [];
          var parentContact = data[1].data || null;
          
          // Add parent contact to the list with special flag
          if (parentContact) {
              parentContact.isParent = true;
          }
          
          populateDeliveryAddresses(parentContact);
          populateContactPersons(parentContact);
          return data;
      })
      .catch(error => {
          if (otDebugMode) console.error('Error loading contacts:', error);
          otChildContacts = [];
          populateDeliveryAddresses(null);
          populateContactPersons(null);
          throw error;
      });
}

// Populate delivery address list with checkboxes
function populateDeliveryAddresses(parentContact) {
    var listContainer = document.getElementById('otDeliveryAddressList');
    listContainer.innerHTML = '';
    
    if (otChildContacts.length === 0) {
        listContainer.innerHTML = '<div class="text-center text-muted p-3">No hay direcciones disponibles - use campos manuales abajo</div>';
        return;
    }
    
    // Filter only delivery addresses (exclude 'contact' type as those are for contact persons)
    var deliveryAddresses = otChildContacts.filter(c => c.type === 'delivery');
    
    // Add delivery addresses
    if (deliveryAddresses.length > 0) {
        deliveryAddresses.forEach(function(contact) {
            var listItem = createAddressListItem(contact, 'otDeliveryAddress');
            listContainer.appendChild(listItem);
        });
    } else {
        listContainer.innerHTML = '<div class="text-center text-muted p-3">No hay direcciones de entrega disponibles - use campos manuales abajo</div>';
    }
}

// Toggle new contact form visibility
function toggleNewContactForm() {
    var form = document.getElementById('otNewContactForm');
    var button = document.getElementById('otShowNewContactBtn');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        button.innerHTML = '<i class="fas fa-times"></i> Cancelar';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-outline-secondary');
    } else {
        form.style.display = 'none';
        button.innerHTML = '<i class="fas fa-plus"></i> Crear un nuevo contacto';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-outline-primary');
        
        // Clear form fields when hiding
        document.getElementById('otManualContactName').value = '';
        document.getElementById('otManualContactPhone').value = '';
        document.getElementById('otSaveContactToOdoo').checked = false;
        document.getElementById('otSaveContactButtonContainer').style.display = 'none';
        
        // Clear any radio button selections
        var selectedRadio = document.querySelector('input[name="otContact"]:checked');
        if (selectedRadio) {
            selectedRadio.checked = false;
            selectedRadio.closest('.list-group-item').classList.remove('active');
        }
        document.getElementById('otSelectedContactName').value = '';
        document.getElementById('otSelectedContactPhone').value = '';
    }
}

// Toggle new address form visibility
function toggleNewAddressForm() {
    var form = document.getElementById('otNewAddressForm');
    var button = document.getElementById('otShowNewAddressBtn');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        button.innerHTML = '<i class="fas fa-times"></i> Cancelar';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-outline-secondary');
    } else {
        form.style.display = 'none';
        button.innerHTML = '<i class="fas fa-plus"></i> Crear una nueva dirección de entrega';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-outline-primary');
        
        // Clear form fields when hiding
        document.getElementById('otManualAddressName').value = '';
        document.getElementById('otManualStreet').value = '';
        document.getElementById('otManualCity').value = '';
        document.getElementById('otSaveAddressToOdoo').checked = false;
        document.getElementById('otSaveAddressButtonContainer').style.display = 'none';
        
        // Clear any radio button selections
        var selectedRadio = document.querySelector('input[name="otDeliveryAddress"]:checked');
        if (selectedRadio) {
            selectedRadio.checked = false;
            selectedRadio.closest('.list-group-item').classList.remove('active');
        }
        document.getElementById('otSelectedStreet').value = '';
        document.getElementById('otSelectedCity').value = '';
    }
}

// Create a list item with radio button for address selection
function createAddressListItem(contact, name) {
    var listItem = document.createElement('label');
    listItem.className = 'list-group-item d-flex align-items-start';
    listItem.style.cursor = 'pointer';
    
    var radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = name;
    radio.value = contact.id;
    radio.className = 'form-check-input me-2 mt-1';
    radio.dataset.street = contact.street || '';
    radio.dataset.city = contact.city || '';
    
    var content = document.createElement('div');
    content.className = 'flex-grow-1';
    
    var nameDiv = document.createElement('div');
    nameDiv.className = 'fw-bold';
    nameDiv.textContent = contact.name;
    
    var addressDiv = document.createElement('div');
    addressDiv.className = 'text-muted small';
    var addressText = contact.street || 'Sin dirección';
    if (contact.city) {
        addressText += ', ' + contact.city;
    }
    addressDiv.textContent = addressText;
    
    content.appendChild(nameDiv);
    content.appendChild(addressDiv);
    
    listItem.appendChild(radio);
    listItem.appendChild(content);
    
    // Handle selection change
    radio.addEventListener('change', function() {
        if (this.checked) {
            // Clear manual inputs
            document.getElementById('otManualAddressName').value = '';
            document.getElementById('otManualStreet').value = '';
            document.getElementById('otManualCity').value = '';
            document.getElementById('otSaveAddressToOdoo').checked = false;
            
            // Set selected address data
            document.getElementById('otSelectedStreet').value = this.dataset.street;
            document.getElementById('otSelectedCity').value = this.dataset.city;
            
            // Remove selection from other radio buttons
            var allRadios = document.querySelectorAll('input[name="' + name + '"]');
            allRadios.forEach(function(r) {
                r.closest('.list-group-item').classList.remove('active');
            });
            this.closest('.list-group-item').classList.add('active');
        }
    });
    
    return listItem;
}

// Populate contact persons list with radio buttons
function populateContactPersons(parentContact) {
    var listContainer = document.getElementById('otContactList');
    listContainer.innerHTML = '';
    
    var hasContacts = false;
    
    // Add parent contact first
    if (parentContact) {
        var parentContactObj = {
            id: parentContact.id || 0,
            name: 'Contacto Principal - ' + (parentContact.name || 'Sin nombre'),
            phone: parentContact.phone || parentContact.mobile || '',
            isParent: true
        };
        var listItem = createContactListItem(parentContactObj, 'otContact');
        listContainer.appendChild(listItem);
        hasContacts = true;
    }
    
    // Add all child contacts (no type filter - show all)
    otChildContacts.forEach(function(contact) {
        var listItem = createContactListItem(contact, 'otContact');
        listContainer.appendChild(listItem);
        hasContacts = true;
    });
    
    if (!hasContacts) {
        listContainer.innerHTML = '<div class="text-center text-muted p-3">No hay personas de contacto disponibles - use campos manuales abajo</div>';
    }
}

// Create a list item with radio button for contact selection
function createContactListItem(contact, name) {
    var listItem = document.createElement('label');
    listItem.className = 'list-group-item d-flex align-items-start';
    listItem.style.cursor = 'pointer';
    
    var radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = name;
    radio.value = contact.id || 0;
    radio.className = 'form-check-input me-2 mt-1';
    radio.dataset.name = contact.name || '';
    radio.dataset.phone = contact.phone || contact.mobile || '';
    
    var content = document.createElement('div');
    content.className = 'flex-grow-1';
    
    var nameDiv = document.createElement('div');
    nameDiv.className = 'fw-bold';
    nameDiv.textContent = contact.name || 'Sin nombre';
    
    var phoneDiv = document.createElement('div');
    phoneDiv.className = 'text-muted small';
    phoneDiv.textContent = radio.dataset.phone || 'Sin teléfono';
    
    content.appendChild(nameDiv);
    content.appendChild(phoneDiv);
    
    listItem.appendChild(radio);
    listItem.appendChild(content);
    
    // Handle selection change
    radio.addEventListener('change', function() {
        if (this.checked) {
            // Clear manual inputs
            document.getElementById('otManualContactName').value = '';
            document.getElementById('otManualContactPhone').value = '';
            document.getElementById('otSaveContactToOdoo').checked = false;
            
            // Set selected contact data
            document.getElementById('otSelectedContactName').value = this.dataset.name;
            document.getElementById('otSelectedContactPhone').value = this.dataset.phone;
            
            // Remove selection from other radio buttons
            var allRadios = document.querySelectorAll('input[name="' + name + '"]');
            allRadios.forEach(function(r) {
                r.closest('.list-group-item').classList.remove('active');
            });
            this.closest('.list-group-item').classList.add('active');
        }
    });
    
    return listItem;
}
document.addEventListener('DOMContentLoaded', function() {
    // Delivery type toggle handler
    var deliveryTypeDomicilio = document.getElementById('otDeliveryTypeDomicilio');
    var deliveryTypeRecoger = document.getElementById('otDeliveryTypeRecoger');
    var deliveryAddressContainer = document.getElementById('otDeliveryAddressContainer');
    
    if (deliveryTypeDomicilio && deliveryTypeRecoger && deliveryAddressContainer) {
        deliveryTypeDomicilio.addEventListener('change', function() {
            if (this.checked) {
                deliveryAddressContainer.style.display = 'block';
            }
        });
        
        deliveryTypeRecoger.addEventListener('change', function() {
            if (this.checked) {
                deliveryAddressContainer.style.display = 'none';
            }
        });
    }
    
    // Clear delivery dropdown when manual address inputs are used
    var manualAddressFields = ['otManualAddressName', 'otManualStreet', 'otManualCity'];
    manualAddressFields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                if (this.value) {
                    // Clear radio button selection
                    var selectedRadio = document.querySelector('input[name="otDeliveryAddress"]:checked');
                    if (selectedRadio) {
                        selectedRadio.checked = false;
                        selectedRadio.closest('.list-group-item').classList.remove('active');
                    }
                    document.getElementById('otSelectedStreet').value = '';
                    document.getElementById('otSelectedCity').value = '';
                }
                // Show/hide save button based on checkbox and manual inputs
                toggleSaveAddressButton();
            });
        }
    });
    
    // Toggle save button when checkbox changes
    var saveAddressCheckbox = document.getElementById('otSaveAddressToOdoo');
    if (saveAddressCheckbox) {
        saveAddressCheckbox.addEventListener('change', toggleSaveAddressButton);
    }
    
    // Clear contact dropdown when manual contact inputs are used
    var manualNameInput = document.getElementById('otManualContactName');
    var manualPhoneInput = document.getElementById('otManualContactPhone');
    
    if (manualNameInput) {
        manualNameInput.addEventListener('input', function() {
                if (this.value) {
                    // Clear radio button selection
                    var selectedRadio = document.querySelector('input[name="otContact"]:checked');
                    if (selectedRadio) {
                        selectedRadio.checked = false;
                        selectedRadio.closest('.list-group-item').classList.remove('active');
                    }
                    document.getElementById('otSelectedContactName').value = '';
                    document.getElementById('otSelectedContactPhone').value = '';
                }
            toggleSaveContactButton();
        });
    }
    
    if (manualPhoneInput) {
        manualPhoneInput.addEventListener('input', function() {
                if (this.value) {
                    // Clear radio button selection
                    var selectedRadio = document.querySelector('input[name="otContact"]:checked');
                    if (selectedRadio) {
                        selectedRadio.checked = false;
                        selectedRadio.closest('.list-group-item').classList.remove('active');
                    }
                    document.getElementById('otSelectedContactName').value = '';
                    document.getElementById('otSelectedContactPhone').value = '';
                }
            toggleSaveContactButton();
        });
    }
    
    // Toggle save button when checkbox changes
    var saveContactCheckbox = document.getElementById('otSaveContactToOdoo');
    if (saveContactCheckbox) {
        saveContactCheckbox.addEventListener('change', toggleSaveContactButton);
    }
});
function toggleSaveAddressButton() {
    var checkbox = document.getElementById('otSaveAddressToOdoo');
    var name = document.getElementById('otManualAddressName').value.trim();
    var street = document.getElementById('otManualStreet').value.trim();
    var city = document.getElementById('otManualCity').value.trim();
    var buttonContainer = document.getElementById('otSaveAddressButtonContainer');
    
    if (otDebugMode) {
        console.log('Toggle Address Button - Checkbox:', checkbox.checked, 'Name:', name, 'Street:', street, 'City:', city);
    }
    
    // Show button only if checkbox is checked AND all manual fields have values
    if (checkbox.checked && name && street && city) {
        if (otDebugMode) console.log('Showing address save button');
        buttonContainer.style.display = 'block';
    } else {
        if (otDebugMode) console.log('Hiding address save button');
        buttonContainer.style.display = 'none';
    }
}

function toggleSaveContactButton() {
    var checkbox = document.getElementById('otSaveContactToOdoo');
    var name = document.getElementById('otManualContactName').value.trim();
    var phone = document.getElementById('otManualContactPhone').value.trim();
    var buttonContainer = document.getElementById('otSaveContactButtonContainer');
    
    if (otDebugMode) {
        console.log('Toggle Contact Button - Checkbox:', checkbox.checked, 'Name:', name, 'Phone:', phone);
    }
    
    // Show button only if checkbox is checked AND all manual fields have values
    if (checkbox.checked && name && phone) {
        if (otDebugMode) console.log('Showing contact save button');
        buttonContainer.style.display = 'block';
    } else {
        if (otDebugMode) console.log('Hiding contact save button');
        buttonContainer.style.display = 'none';
    }
}

function goToStep2() {
    // Check delivery type
    var deliveryType = document.querySelector('input[name="otDeliveryType"]:checked').value;
    var deliveryStreet = '';
    var deliveryCity = '';
    
    if (deliveryType === 'recoger') {
        // Pickup option - no address validation needed
        deliveryStreet = 'Recoger en Oficina';
        deliveryCity = '';
        
        // Store for later use
        document.getElementById('otSelectedStreet').value = deliveryStreet;
        document.getElementById('otSelectedCity').value = '';
    } else {
        // Delivery to address - validate address selection
        var selectedRadio = document.querySelector('input[name="otDeliveryAddress"]:checked');
        var manualAddressName = document.getElementById('otManualAddressName').value.trim();
        var manualStreet = document.getElementById('otManualStreet').value.trim();
        var manualCity = document.getElementById('otManualCity').value.trim();
        
        // Validate: either radio selection OR manual inputs
        if (selectedRadio) {
            // Using selected address from list
            deliveryStreet = document.getElementById('otSelectedStreet').value;
            deliveryCity = document.getElementById('otSelectedCity').value;
        } else if (manualAddressName && manualStreet && manualCity) {
            // Using manual input
            deliveryStreet = manualStreet;
            deliveryCity = manualCity;
            
            // Store for later use
            document.getElementById('otSelectedStreet').value = manualStreet;
            document.getElementById('otSelectedCity').value = manualCity;
        } else {
            alert('Por favor seleccione una dirección de entrega o ingrese los campos manualmente (nombre, dirección y ciudad).');
            return;
        }
    }
    
    // Update summary
    var deliveryAddress = deliveryStreet + (deliveryCity ? ', ' + deliveryCity : '');
    var instructions = document.getElementById('otDeliveryInstructions').value || 'Ninguna';
    
    document.getElementById('otSummaryAddress').textContent = deliveryAddress;
    document.getElementById('otSummaryInstructions').textContent = instructions;
    
    // Hide Step 1, Show Step 2
    document.getElementById('otStep1').style.display = 'none';
    document.getElementById('otStep2').style.display = 'block';

    document.getElementById('otBtnNext').style.display = 'none';
    document.getElementById('otBtnBack').style.display = 'inline-block';

    document.getElementById('otBtnSubmit').style.display = 'none';
    var nt2 = document.getElementById('otBtnNextStep2');
    if (comOpOtUseThreeSteps && nt2) {
        nt2.style.display = 'inline-block';
    } else {
        if (nt2) nt2.style.display = 'none';
        document.getElementById('otBtnSubmit').style.display = 'inline-block';
    }

    otWizardApplyStepUi(2);
    otWizardCurrentStep = 2;
}

// Navigate back within wizard (contact step -> delivery, or instructions -> contact)
function goOtWizardBack() {
    if (comOpOtUseThreeSteps && otWizardCurrentStep === 3) {
        opOtUnmountStep3InstructionsIfMounted();
        var s3b = document.getElementById('otStep3');
        if (s3b) s3b.style.display = 'none';
        document.getElementById('otStep2').style.display = 'block';

        document.getElementById('otBtnNext').style.display = 'none';
        document.getElementById('otBtnBack').style.display = 'inline-block';
        var nt2 = document.getElementById('otBtnNextStep2');
        if (nt2) nt2.style.display = 'inline-block';
        document.getElementById('otBtnSubmit').style.display = 'none';

        otWizardApplyStepUi(2);
        otWizardCurrentStep = 2;
        return;
    }
    goToStep1();
}
function goToStep3() {
    if (!comOpOtUseThreeSteps) return;
    if (!validateOtContactInputs()) {
        alert('Por favor seleccione un contacto o ingrese nombre y teléfono manualmente.');
        return;
    }

    opOtMountInstructionsIntoWizardStep3();

    document.getElementById('otStep2').style.display = 'none';
    var s3 = document.getElementById('otStep3');
    if (s3) s3.style.display = 'block';

    document.getElementById('otBtnNext').style.display = 'none';
    document.getElementById('otBtnBack').style.display = 'inline-block';
    var nt2 = document.getElementById('otBtnNextStep2');
    if (nt2) nt2.style.display = 'none';
    document.getElementById('otBtnSubmit').style.display = 'inline-block';

    otWizardApplyStepUi(3);
    otWizardCurrentStep = 3;
}

// Navigate back to Step 1
function goToStep1() {
    opOtUnmountStep3InstructionsIfMounted();
    document.getElementById('otStep2').style.display = 'none';
    var otStep3El = document.getElementById('otStep3');
    if (otStep3El) {
        otStep3El.style.display = 'none';
    }
    document.getElementById('otStep1').style.display = 'block';

    document.getElementById('otBtnNext').style.display = 'inline-block';
    document.getElementById('otBtnBack').style.display = 'none';
    document.getElementById('otBtnSubmit').style.display = 'none';
    var ntb = document.getElementById('otBtnNextStep2');
    if (ntb) ntb.style.display = 'none';

    otWizardApplyStepUi(1);
    otWizardCurrentStep = 1;
}

// Submit OT Form
function submitOT() {
    var clientId = document.getElementById('otClientId').value;
    var selectedContactRadio = document.querySelector('input[name="otContact"]:checked');
    var manualName = document.getElementById('otManualContactName').value.trim();
    var manualPhone = document.getElementById('otManualContactPhone').value.trim();

    var contactName = '';
    var contactPhone = '';

    if (selectedContactRadio) {
        contactName = document.getElementById('otSelectedContactName').value;
        contactPhone = document.getElementById('otSelectedContactPhone').value;
    } else if (manualName && manualPhone) {
        contactName = manualName;
        contactPhone = manualPhone;
    } else {
        alert('Por favor seleccione un contacto o ingrese nombre y teléfono manualmente.');
        return;
    }

    var clientName = document.getElementById('otClientName').textContent;
    var clientVat = document.getElementById('otClientVat').textContent;
    var deliveryStreet = document.getElementById('otSelectedStreet').value;
    var deliveryCity = document.getElementById('otSelectedCity').value;
    var instructions = document.getElementById('otDeliveryInstructions').value;
    var agentName = document.getElementById('otAgentName').value;
    var deliveryType = document.querySelector('input[name="otDeliveryType"]:checked').value;

    var deliveryAddress = deliveryStreet;
    if (deliveryCity) {
        deliveryAddress += (deliveryStreet ? ', ' : '') + deliveryCity;
    }

    if (!cotOtSubmitReturnOnly) {
        var url = otDestinationUrl;
        url += '?client_id=' + encodeURIComponent(clientId);
        url += '&contact_name=' + encodeURIComponent(clientName);
        url += '&contact_vat=' + encodeURIComponent(clientVat);
        url += '&x_studio_agente_de_ventas=' + encodeURIComponent(agentName);
        url += '&tipo_entrega=' + encodeURIComponent(deliveryType);
        url += '&delivery_address=' + encodeURIComponent(deliveryAddress);
        url += '&instrucciones_entrega=' + encodeURIComponent(instructions);
        url += '&contact_person_name=' + encodeURIComponent(contactName);
        url += '&contact_person_phone=' + encodeURIComponent(contactPhone);
        window.location.href = url;
        return;
    }

    var retUrl = comOpOtReturnAfterSubmitUrl || 'index.php?option=com_ordenproduccion&view=cotizaciones';
    if (comOpOtUseThreeSteps && otWizardCurrentStep === 3 && comOpOtSaveInstruccionesJsonUrl) {
        opOtSaveInstruccionesOrdenThenRedirect(retUrl);
        return;
    }
    opOtHideOtModal();
    window.location.href = retUrl;
}
function opOtSaveInstruccionesOrdenThenRedirect(doneUrl) {
    var pid = parseInt(String(comOpOtWizardPreId || '0'), 10);
    var formEl = pid ? document.getElementById('instrucciones-orden-form-' + pid) : null;
    if (!formEl || !comOpOtSaveInstruccionesJsonUrl) {
        opOtHideOtModal();
        window.location.href = doneUrl;
        return;
    }
    var submitBtn = document.getElementById('otBtnSubmit');
    if (submitBtn) submitBtn.disabled = true;
    var fd = new FormData(formEl);
    var otFeEl = document.getElementById('otStep3FechaEntrega');
    var otIgEl = document.getElementById('otStep3InstruccionesGenerales');
    if (otFeEl) {
        fd.append('ot_fecha_entrega', otFeEl.value || '');
    }
    if (otIgEl) {
        fd.append('ot_instrucciones_generales', otIgEl.value || '');
    }
    fetch(comOpOtSaveInstruccionesJsonUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function(response) {
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return { success: false, message: text ? text.substring(0, 200) : '' };
                }
            });
        })
        .then(function(data) {
            if (submitBtn) submitBtn.disabled = false;
            if (data && data.success) {
                // If configured, create OT internally from this PRE and decide redirect (OT vs return to cotización)
                if (comOpOtCreateOrdenJsonUrl && String(comOpOtCreateOrdenJsonUrl).length > 0) {
                    opOtCreateOrdenFromWizard(formEl, doneUrl);
                    return;
                }
                opOtUnmountStep3InstructionsIfMounted();
                opOtHideOtModal();
                window.location.href = doneUrl;
            } else {
                alert((data && data.message) ? data.message : 'No se pudieron guardar las instrucciones.');
            }
        })
        .catch(function() {
            if (submitBtn) submitBtn.disabled = false;
            alert('Error de conexión al guardar las instrucciones.');
        });
}

function opOtCreateOrdenFromWizard(instruccionesFormEl, fallbackReturnUrl) {
    if (!instruccionesFormEl || !comOpOtCreateOrdenJsonUrl) {
        opOtUnmountStep3InstructionsIfMounted();
        opOtHideOtModal();
        window.location.href = fallbackReturnUrl;
        return;
    }
    var pid = parseInt(String(comOpOtWizardPreId || '0'), 10) || 0;
    var qidEl = instruccionesFormEl.querySelector('input[name="quotation_id"]');
    var pidEl = instruccionesFormEl.querySelector('input[name="pre_cotizacion_id"]');
    var qid = qidEl ? parseInt(String(qidEl.value || '0'), 10) : 0;
    var preId = pidEl ? parseInt(String(pidEl.value || '0'), 10) : pid;
    if (!qid || !preId) {
        opOtUnmountStep3InstructionsIfMounted();
        opOtHideOtModal();
        window.location.href = fallbackReturnUrl;
        return;
    }

    var clientId = document.getElementById('otClientId') ? document.getElementById('otClientId').value : '';
    var deliveryStreet = document.getElementById('otSelectedStreet') ? document.getElementById('otSelectedStreet').value : '';
    var deliveryCity = document.getElementById('otSelectedCity') ? document.getElementById('otSelectedCity').value : '';
    var instructions = document.getElementById('otDeliveryInstructions') ? document.getElementById('otDeliveryInstructions').value : '';
    var deliveryTypeEl = document.querySelector('input[name="otDeliveryType"]:checked');
    var deliveryType = deliveryTypeEl ? deliveryTypeEl.value : '';
    var contactName = document.getElementById('otSelectedContactName') ? document.getElementById('otSelectedContactName').value : '';
    var contactPhone = document.getElementById('otSelectedContactPhone') ? document.getElementById('otSelectedContactPhone').value : '';

    var deliveryAddress = deliveryStreet;
    if (deliveryCity) {
        deliveryAddress += (deliveryStreet ? ', ' : '') + deliveryCity;
    }

    var fd = new FormData();
    fd.append('quotation_id', String(qid));
    fd.append('pre_cotizacion_id', String(preId));
    if (clientId) fd.append('client_id', String(clientId));
    if (deliveryType) fd.append('tipo_entrega', String(deliveryType));
    if (deliveryAddress) fd.append('delivery_address', String(deliveryAddress));
    if (instructions) fd.append('instrucciones_entrega', String(instructions));
    if (contactName) fd.append('contact_person_name', String(contactName));
    if (contactPhone) fd.append('contact_person_phone', String(contactPhone));
    if (comOpOtFormTokenName) fd.append(String(comOpOtFormTokenName), '1');

    var submitBtn = document.getElementById('otBtnSubmit');
    if (submitBtn) submitBtn.disabled = true;

    fetch(comOpOtCreateOrdenJsonUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function(response) {
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return { success: false, message: text ? text.substring(0, 200) : '' };
                }
            });
        })
        .then(function(data) {
            if (submitBtn) submitBtn.disabled = false;
            if (data && data.success && data.redirect_url) {
                opOtUnmountStep3InstructionsIfMounted();
                opOtHideOtModal();
                window.location.href = data.redirect_url;
                return;
            }
            var err = (data && data.message) ? data.message : 'No se pudo crear la orden de trabajo.';
            if (data && data.detail) {
                err += "\n" + String(data.detail).substring(0, 500);
            }
            alert(err);
        })
        .catch(function() {
            if (submitBtn) submitBtn.disabled = false;
            alert('Error de conexión al crear la orden de trabajo.');
        });
}
function saveDeliveryAddressNow() {
    var addressName = document.getElementById('otManualAddressName').value.trim();
    var street = document.getElementById('otManualStreet').value.trim();
    var city = document.getElementById('otManualCity').value.trim();
    var agentName = document.getElementById('otAgentName').value;
    
    // Validate inputs
    if (!addressName || !street || !city) {
        showNotification('Por favor complete todos los campos de dirección', 'warning');
        return;
    }
    
    // Disable button while saving
    var saveBtn = event.target;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    var formData = new FormData();
    formData.append('parent_id', clientId);
    formData.append('name', addressName);
    formData.append('street', street);
    formData.append('city', city);
    formData.append('type', 'delivery');
    formData.append('x_studio_agente_de_ventas', agentName);
    formData.append('<?php echo Session::getFormToken(); ?>', '1');
    
    fetch('<?php echo Route::_("index.php?option=com_ordenproduccion&task=cliente.saveDeliveryAddressAsync&format=json"); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              showNotification('Dirección guardada exitosamente', 'success');
              
              // Reload addresses to include the new one
              var clientId = document.getElementById('otClientId').value;
              var clientName = document.getElementById('otClientName').textContent;
              
              // Clear manual inputs and hide button first
              document.getElementById('otManualAddressName').value = '';
              document.getElementById('otManualStreet').value = '';
              document.getElementById('otManualCity').value = '';
              document.getElementById('otSaveAddressToOdoo').checked = false;
              document.getElementById('otSaveAddressButtonContainer').style.display = 'none';
              
              // Hide the new address form
              document.getElementById('otNewAddressForm').style.display = 'none';
              var showBtn = document.getElementById('otShowNewAddressBtn');
              showBtn.innerHTML = '<i class="fas fa-plus"></i> Crear una nueva dirección de entrega';
              showBtn.classList.remove('btn-outline-secondary');
              showBtn.classList.add('btn-outline-primary');
              
              // Reload child contacts which will repopulate the address list
              loadChildContacts(clientId, clientName).then(function() {
                  // After reloading, try to select the newly created address
                  setTimeout(function() {
                      var newAddressRadio = document.querySelector('input[name="otDeliveryAddress"][value="' + (data.address_id || 'new') + '"]');
                      if (newAddressRadio) {
                          newAddressRadio.checked = true;
                          newAddressRadio.dispatchEvent(new Event('change'));
                      } else {
                          // If we can't find it by ID, select the first delivery address
                          var firstDeliveryRadio = document.querySelector('input[name="otDeliveryAddress"]');
                          if (firstDeliveryRadio) {
                              firstDeliveryRadio.checked = true;
                              firstDeliveryRadio.dispatchEvent(new Event('change'));
                          }
                      }
                  }, 100);
              });
          } else {
              showNotification('Error al guardar: ' + (data.message || 'Error desconocido') + '. Por favor contacte a soporte.', 'danger');
          }
          
          // Re-enable button
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Dirección';
      })
      .catch(error => {
          showNotification('Error de conexión. Por favor contacte a soporte.', 'danger');
          if (otDebugMode) console.error('Error saving delivery address:', error);
          
          // Re-enable button
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Dirección';
      });
}

// Save contact to Odoo now (synchronous with feedback)
function saveContactNow() {
    var clientId = document.getElementById('otClientId').value;
    var contactName = document.getElementById('otManualContactName').value.trim();
    var contactPhone = document.getElementById('otManualContactPhone').value.trim();
    var agentName = document.getElementById('otAgentName').value;
    
    // Validate inputs
    if (!contactName || !contactPhone) {
        showNotification('Por favor complete nombre y teléfono del contacto', 'warning');
        return;
    }
    
    // Disable button while saving
    var saveBtn = event.target;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    var formData = new FormData();
    formData.append('parent_id', clientId);
    formData.append('name', contactName);
    formData.append('phone', contactPhone);
    formData.append('type', 'contact');
    formData.append('x_studio_agente_de_ventas', agentName);
    formData.append('<?php echo Session::getFormToken(); ?>', '1');
    
    fetch('<?php echo Route::_("index.php?option=com_ordenproduccion&task=cliente.saveChildContactAsync&format=json"); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              showNotification('Contacto guardado exitosamente', 'success');
              
              // Clear manual inputs and hide button first
              document.getElementById('otManualContactName').value = '';
              document.getElementById('otManualContactPhone').value = '';
              document.getElementById('otSaveContactToOdoo').checked = false;
              document.getElementById('otSaveContactButtonContainer').style.display = 'none';
              
              // Hide the new contact form
              document.getElementById('otNewContactForm').style.display = 'none';
              var showContactBtn = document.getElementById('otShowNewContactBtn');
              showContactBtn.innerHTML = '<i class="fas fa-plus"></i> Crear un nuevo contacto';
              showContactBtn.classList.remove('btn-outline-secondary');
              showContactBtn.classList.add('btn-outline-primary');
              
              // Reload child contacts which will repopulate the contact list
              var clientId = document.getElementById('otClientId').value;
              var clientName = document.getElementById('otClientName').textContent;
              
              loadChildContacts(clientId, clientName).then(function() {
                  // After reloading, try to select the newly created contact
                  setTimeout(function() {
                      var newContactRadio = document.querySelector('input[name="otContact"][value="' + (data.contact_id || 'new') + '"]');
                      if (newContactRadio) {
                          newContactRadio.checked = true;
                          newContactRadio.dispatchEvent(new Event('change'));
                      } else {
                          // If we can't find it by ID, select the first contact
                          var firstContactRadio = document.querySelector('input[name="otContact"]');
                          if (firstContactRadio) {
                              firstContactRadio.checked = true;
                              firstContactRadio.dispatchEvent(new Event('change'));
                          }
                      }
                  }, 100);
              });
          } else {
              showNotification('Error al guardar: ' + (data.message || 'Error desconocido') + '. Por favor contacte a soporte.', 'danger');
          }
          
          // Re-enable button
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Contacto';
      })
      .catch(error => {
          showNotification('Error de conexión. Por favor contacte a soporte.', 'danger');
          if (otDebugMode) console.error('Error saving contact:', error);
          
          // Re-enable button
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Contacto';
      });
}
</script>
