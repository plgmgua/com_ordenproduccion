<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_acciones_produccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$currentUrl = Uri::current();
?>

<div class="mod-acciones-produccion">
    <h4 class="module-title">
        <i class="fas fa-tasks"></i> ACCIONES
    </h4>
    
    <?php 
    // DEBUG: Check if condition is met
    error_log("MOD_ACCIONES_PRODUCCION DEBUG - orderId: " . var_export($orderId, true));
    error_log("MOD_ACCIONES_PRODUCCION DEBUG - workOrderData exists: " . var_export(!empty($workOrderData), true));
    if (!empty($workOrderData)) {
        error_log("MOD_ACCIONES_PRODUCCION DEBUG - workOrderData->id: " . var_export($workOrderData->id ?? 'NO ID', true));
    }
    ?>
    
    <?php if ($orderId && $workOrderData): ?>
        
        <!-- PRODUCCION SECTION -->
        <?php if ($hasProductionAccess): ?>
        <div class="section-produccion">
            <h5 class="section-title">
                <i class="fas fa-cogs"></i> PRODUCCION
            </h5>
            
            <!-- PDF Generation Button (Top) -->
            <div class="pdf-actions">
                <a href="index.php?option=com_ordenproduccion&task=orden.generatePdf&id=<?php echo $orderId; ?>" 
                   target="_blank" 
                   class="btn btn-primary btn-block">
                    <i class="fas fa-print"></i>
                    Imprimir Orden
                </a>
            </div>
            
            <!-- Work Order Info with Status Change -->
            <div class="work-order-info">
                <p><strong>Estado Actual:</strong> 
                    <span class="status-badge status-<?php echo htmlspecialchars($workOrderData->status ?? 'Nueva'); ?>">
                        <?php echo htmlspecialchars($statusOptions[$workOrderData->status ?? 'Nueva'] ?? 'Nueva'); ?>
                    </span>
                </p>
                
                <form id="status-change-form" class="status-form">
                    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                    <input type="hidden" name="<?php echo $app->getFormToken(); ?>" value="1">
                    <input type="hidden" name="option" value="com_ordenproduccion">
                    <input type="hidden" name="task" value="ajax.changeStatus">
                    
                    <div class="form-group">
                        <select name="new_status" id="new_status" class="form-control" required>
                            <option value="">Seleccionar nuevo estado...</option>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" 
                                        <?php echo ($workOrderData->status === $value) ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-save"></i>
                        Actualizar Estado
                    </button>
                </form>
                
                <div id="status-message" class="status-message" style="display: none;"></div>
            </div>

            <!-- Shipping Slip Form -->
            <div class="shipping-form-section">
                <h6><i class="fas fa-shipping-fast"></i> Generar Envio</h6>
                <form id="shipping-form" class="shipping-form">
                    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                    <input type="hidden" name="<?php echo $app->getFormToken(); ?>" value="1">
                    <input type="hidden" name="option" value="com_ordenproduccion">
                    <input type="hidden" name="task" value="orden.generateShippingSlip">
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Envio:</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="tipo_envio" value="completo" checked>
                                <span class="radio-text">Completo</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="tipo_envio" value="parcial">
                                <span class="radio-text">Parcial</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Mensajería:</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="tipo_mensajeria" value="propio" checked>
                                <span class="radio-text">Propio</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="tipo_mensajeria" value="terceros">
                                <span class="radio-text">Terceros</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-info btn-block" id="shipping-submit-btn">
                        <i class="fas fa-shipping-fast"></i>
                        Generar Envio
                    </button>
                </form>
                
                <div id="shipping-message" class="shipping-message" style="display: none;"></div>
            </div>
            
            <!-- Shipping Description Modal -->
            <div id="shipping-description-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
                <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; color: #333;">
                            <i class="fas fa-shipping-fast"></i> Descripción de Envío
                        </h3>
                        <button onclick="closeShippingDescriptionModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
                    </div>
                    
                    <form id="shipping-description-form">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                                Descripción de Envío <span style="color: red;">*</span>
                            </label>
                            <textarea id="descripcion_envio" name="descripcion_envio" required rows="5" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
                            <small style="display: block; margin-top: 5px; color: #6c757d; font-size: 12px;">Esta descripción aparecerá en el PDF de envío debajo de la línea "Trabajo"</small>
                        </div>
                    </form>
                    
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" onclick="closeShippingDescriptionModal()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            Cancelar
                        </button>
                        <button type="button" onclick="submitShippingWithDescription()" style="padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-shipping-fast"></i> Generar Envío
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- VENTAS SECTION -->
    <?php if ($hasSalesAccess && $isOwner): ?>
    <div class="section-ventas">
        <h5 class="section-title">
            <i class="fas fa-handshake"></i> VENTAS
        </h5>
        
        <div class="ventas-actions">
            <button type="button" id="duplicate-request-btn" class="btn btn-warning btn-block" onclick="openDuplicateForm()">
                <i class="fas fa-copy"></i>
                Duplicar Solicitud
            </button>
            <div id="duplicate-message" class="duplicate-message" style="display: none;"></div>
        </div>
    </div>
    
    <!-- Duplicate Request Form Modal -->
    <div id="duplicate-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
        <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333;">
                    <i class="fas fa-copy"></i> Duplicar Solicitud
                </h3>
                <button onclick="closeDuplicateForm()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
            </div>
            
            <form id="duplicate-form" onsubmit="submitDuplicateForm(event)">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                        Cliente <span style="color: red;">*</span>
                    </label>
                    <input type="text" id="dup_client_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                        NIT <span style="color: red;">*</span>
                    </label>
                    <input type="text" id="dup_nit" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                        Dirección de Entrega <span style="color: red;">*</span>
                    </label>
                    <textarea id="dup_shipping_address" required rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                        Contacto de Entrega <span style="color: red;">*</span>
                    </label>
                    <input type="text" id="dup_shipping_contact" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                        Teléfono de Contacto <span style="color: red;">*</span>
                    </label>
                    <input type="tel" id="dup_shipping_phone" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #555;">
                        Instrucciones de Entrega <span style="color: red;">*</span>
                    </label>
                    <textarea id="dup_instrucciones_entrega" required rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical;"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" onclick="closeDuplicateForm()" style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    console.log('Module script loading...');
    
    // Store work order data for JavaScript access
    window.currentOrderData = <?php echo json_encode([
        'id' => $workOrderData->id ?? null,
        'work_description' => $workOrderData->work_description ?? $workOrderData->description ?? '',
        'client_name' => $workOrderData->client_name ?? '',
        'nit' => $workOrderData->nit ?? '',
        'shipping_address' => $workOrderData->shipping_address ?? '',
        'shipping_contact' => $workOrderData->shipping_contact ?? '',
        'shipping_phone' => $workOrderData->shipping_phone ?? '',
        'instrucciones_entrega' => $workOrderData->instrucciones_entrega ?? ''
    ]); ?>;
    
    console.log('currentOrderData set:', window.currentOrderData);
    
    // Define shipping modal functions in global scope for onclick handlers
    window.closeShippingDescriptionModal = function() {
        const overlay = document.getElementById('shipping-description-modal-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    };
    
    console.log('closeShippingDescriptionModal defined');
    
    window.submitShippingWithDescription = function() {
        const shippingForm = document.getElementById('shipping-form');
        const descripcionTextarea = document.getElementById('descripcion_envio');
        
        if (!shippingForm || !descripcionTextarea) {
            alert('Error: No se pudo encontrar el formulario');
            return;
        }
        
        const formData = new FormData(shippingForm);
        const descripcionEnvio = descripcionTextarea.value.trim();
        
        if (!descripcionEnvio) {
            alert('Por favor ingrese una descripción de envío');
            return;
        }
        
        const orderId = formData.get('order_id');
        const tipoEnvio = formData.get('tipo_envio');
        const tipoMensajeria = formData.get('tipo_mensajeria');
        
        if (!tipoEnvio) {
            alert('Por favor selecciona un tipo de envio');
            window.closeShippingDescriptionModal();
            return;
        }
        
        if (!tipoMensajeria) {
            alert('Por favor selecciona un tipo de mensajería');
            window.closeShippingDescriptionModal();
            return;
        }
        
        const submitBtn = document.getElementById('shipping-submit-btn');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            submitBtn.disabled = true;
        }
        
        // Close modal
        window.closeShippingDescriptionModal();
        
        // Build URL with shipping description
        const params = new URLSearchParams();
        params.append('order_id', orderId);
        params.append('tipo_envio', tipoEnvio);
        params.append('tipo_mensajeria', tipoMensajeria);
        params.append('descripcion_envio', descripcionEnvio);
        // Add CSRF token - get the token name from the form hidden input
        const tokenInput = shippingForm.querySelector('input[type="hidden"]');
        if (tokenInput && tokenInput.name && tokenInput.name.includes('token')) {
            params.append(tokenInput.name, tokenInput.value);
        }
        
        const urlEncodedData = params.toString();
        
        fetch('index.php?option=com_ordenproduccion&task=orden.generateShippingSlip&id=' + orderId + '&tipo_envio=' + tipoEnvio + '&tipo_mensajeria=' + tipoMensajeria, {
            method: 'POST',
            body: urlEncodedData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
        .then(response => {
            if (response.ok) {
                window.open('index.php?option=com_ordenproduccion&task=orden.generateShippingSlip&id=' + orderId + '&tipo_envio=' + tipoEnvio + '&tipo_mensajeria=' + tipoMensajeria + '&descripcion_envio=' + encodeURIComponent(descripcionEnvio), '_blank');
                const shippingMessageDiv = document.getElementById('shipping-message');
                if (shippingMessageDiv) {
                    shippingMessageDiv.innerHTML = 'Envio generado correctamente';
                    shippingMessageDiv.className = 'shipping-message success';
                    shippingMessageDiv.style.display = 'block';
                    setTimeout(() => {
                        shippingMessageDiv.style.display = 'none';
                    }, 5000);
                }
            } else {
                throw new Error('HTTP error! status: ' + response.status);
            }
        })
        .catch(error => {
            console.error('Shipping Error:', error);
            alert('Error al generar envio: ' + error.message);
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-shipping-fast"></i> Generar Envio';
                submitBtn.disabled = false;
            }
        });
    };
    
    // Open duplicate form modal and pre-fill fields
    function openDuplicateForm() {
        const orderData = window.currentOrderData || {};
        
        // Pre-fill form fields with current order data
        document.getElementById('dup_client_name').value = orderData.client_name || '';
        document.getElementById('dup_nit').value = orderData.nit || '';
        document.getElementById('dup_shipping_address').value = orderData.shipping_address || '';
        document.getElementById('dup_shipping_contact').value = orderData.shipping_contact || '';
        document.getElementById('dup_shipping_phone').value = orderData.shipping_phone || '';
        document.getElementById('dup_instrucciones_entrega').value = orderData.instrucciones_entrega || '';
        
        // Show modal
        document.getElementById('duplicate-modal-overlay').style.display = 'block';
    }
    
    // Close duplicate form modal
    function closeDuplicateForm() {
        document.getElementById('duplicate-modal-overlay').style.display = 'none';
    }
    
    // Submit duplicate form
    async function submitDuplicateForm(event) {
        event.preventDefault();
        
        const submitButton = event.target.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        
        try {
            // Get form values (edited by user)
            const formData = {
                client_name: document.getElementById('dup_client_name').value,
                nit: document.getElementById('dup_nit').value,
                shipping_address: document.getElementById('dup_shipping_address').value,
                shipping_contact: document.getElementById('dup_shipping_contact').value,
                shipping_phone: document.getElementById('dup_shipping_phone').value,
                instrucciones_entrega: document.getElementById('dup_instrucciones_entrega').value
            };
            
            // Get original order data for other fields
            const orderData = window.currentOrderData || {};
            
            // Fetch settings
            const settingsResponse = await fetch('/components/com_ordenproduccion/get_duplicate_settings.php');
            if (!settingsResponse.ok) {
                throw new Error('No se pudo obtener la configuración del endpoint');
            }
            
            const settings = await settingsResponse.json();
            if (!settings.success || !settings.endpoint) {
                throw new Error('Endpoint no configurado. Por favor configure el endpoint en Configuración.');
            }
            
            // Build URL parameters with edited values
            const urlParams = buildDuplicateUrlParamsWithFormData(orderData, formData);
            const finalUrl = settings.endpoint + (settings.endpoint.includes('?') ? '&' : '?') + urlParams;
            
            console.log('========================================');
            console.log('DUPLICAR SOLICITUD - URL GENERADA:');
            console.log('========================================');
            console.log(finalUrl);
            console.log('========================================');
            
            // Navigate to URL in current tab
            window.location.href = finalUrl;
            
        } catch (error) {
            console.error('Error al duplicar solicitud:', error);
            alert('❌ Error: ' + error.message);
            
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    }
    
    // Build URL parameters with edited form data
    function buildDuplicateUrlParamsWithFormData(orderData, formData) {
        const params = new URLSearchParams();
        
        // Use EDITED values from form for required fields
        if (formData.client_name) params.append('contact_name', formData.client_name);
        if (formData.nit) params.append('contact_vat', formData.nit);
        if (orderData.invoice_value) params.append('invoice_value', orderData.invoice_value);
        if (orderData.work_description) params.append('work_description', orderData.work_description);
        if (orderData.print_color) params.append('print_color', orderData.print_color);
        if (orderData.tiro_retiro) params.append('tiro_retiro', orderData.tiro_retiro);
        if (orderData.dimensions) params.append('dimensions', orderData.dimensions);
        if (orderData.delivery_date) params.append('delivery_date', orderData.delivery_date);
        if (orderData.material) params.append('material', orderData.material);
        if (orderData.quotation_files) params.append('quotation', orderData.quotation_files);
        
        // Acabados (finishing) - SI/NO values
        const acabadosFields = [
            'cutting', 'blocking', 'folding', 'laminating', 'spine', 
            'gluing', 'numbering', 'sizing', 'stapling', 'die_cutting', 
            'varnish', 'white_print', 'trimming', 'eyelets', 'perforation'
        ];
        
        acabadosFields.forEach(field => {
            if (orderData[field]) {
                params.append(field, orderData[field]);
                // Add details if exists
                const detailsField = field + '_details';
                if (orderData[detailsField]) {
                    params.append(detailsField, orderData[detailsField]);
                }
            }
        });
        
        // Additional fields
        if (orderData.instructions) params.append('instructions', orderData.instructions);
        if (orderData.sales_agent) params.append('x_studio_agente_de_ventas', orderData.sales_agent);
        
        // Use current date/time for request_date
        const now = new Date();
        const requestDate = now.toISOString().slice(0, 19).replace('T', ' ');
        params.append('request_date', requestDate);
        
        // Use EDITED values from form for shipping fields
        if (formData.shipping_address) params.append('shipping_address', formData.shipping_address);
        if (formData.instrucciones_entrega) params.append('instrucciones_entrega', formData.instrucciones_entrega);
        if (formData.shipping_contact) params.append('shipping_contact', formData.shipping_contact);
        if (formData.shipping_phone) params.append('shipping_phone', formData.shipping_phone);
        
        return params.toString();
    }
    </script>
    
    <?php endif; // Close orderId && workOrderData condition ?>

    <?php else: ?>
        <!-- DEBUG: Condition failed -->
        <div class="alert alert-warning" style="margin: 10px 0;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>DEBUG:</strong> Condición no cumplida - orderId: <?php echo var_export($orderId, true); ?>, workOrderData: <?php echo var_export(!empty($workOrderData), true); ?>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No se encontró información de la orden de trabajo.
        </div>
    <?php endif; ?>
</div>

<!-- Ensure functions are available globally even if there are script errors -->
<script>
// Failsafe: Define functions in global scope if not already defined
if (typeof window.submitShippingWithDescription === 'undefined') {
    console.warn('submitShippingWithDescription was not defined in module script, defining failsafe');
    window.submitShippingWithDescription = function() {
        alert('Error: La función de envío no se cargó correctamente. Por favor, recargue la página.');
    };
}
if (typeof window.closeShippingDescriptionModal === 'undefined') {
    window.closeShippingDescriptionModal = function() {
        const overlay = document.getElementById('shipping-description-modal-overlay');
        if (overlay) overlay.style.display = 'none';
    };
}
</script>

<style>
.mod-acciones-produccion {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.module-title {
    color: #343a40;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
    text-align: center;
}

.section-produccion,
.section-ventas {
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.section-title {
    color: #495057;
    font-size: 14px;
    font-weight: 700;
    margin: 0 0 15px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.work-order-info {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.work-order-info p {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 500;
    color: #495057;
}

.work-order-info .form-group {
    margin-bottom: 15px;
}

.work-order-info .form-control {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
}

.work-order-info .btn {
    width: 100%;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
}

.status-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.status-nueva { 
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); 
    color: #fff; 
}
.status-en_proceso { 
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); 
    color: #fff; 
}
.status-terminada { 
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); 
    color: #fff; 
}
.status-cerrada { 
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); 
    color: #fff; 
}

.pdf-actions {
    text-align: center;
    margin-bottom: 15px;
}

.pdf-actions .btn {
    margin-bottom: 8px;
}

.shipping-form-section {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
}

.shipping-form-section h6 {
    color: #495057;
    margin-bottom: 15px;
    font-size: 14px;
}

.form-label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #495057;
}

.radio-group {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.radio-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
}

.radio-label input[type="radio"] {
    margin-right: 8px;
    transform: scale(1.2);
}

.radio-text {
    color: #495057;
}

.shipping-message {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    font-size: 14px;
}

.shipping-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.shipping-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status-message {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    font-size: 14px;
}

.status-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.ventas-actions {
    text-align: center;
}

.ventas-actions .btn {
    margin-bottom: 8px;
}

.duplicate-message {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    font-size: 14px;
}

.duplicate-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.duplicate-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert {
    font-size: 14px;
    padding: 15px;
    margin-bottom: 0;
    text-align: center;
}

.alert i {
    margin-right: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status change form
    const statusForm = document.getElementById('status-change-form');
    const statusMessageDiv = document.getElementById('status-message');
    
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(statusForm);
            const orderId = formData.get('order_id');
            const newStatus = formData.get('new_status');
            
            if (!newStatus) {
                showStatusMessage('Por favor selecciona un estado', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = statusForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            submitBtn.disabled = true;
            
            // Convert FormData to URL-encoded string
            const urlEncodedData = new URLSearchParams(formData).toString();
            
            // Make AJAX request
            fetch('/components/com_ordenproduccion/change_status.php', {
                method: 'POST',
                body: urlEncodedData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Server response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    if (data.success) {
                        showStatusMessage(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        let errorMsg = data.message;
                        if (data.file && data.line) {
                            errorMsg += ' (' + data.file + ':' + data.line + ')';
                        }
                        showStatusMessage(errorMsg, 'error');
                        console.error('Server error:', data);
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                    console.error('Response text:', text);
                    showStatusMessage('Error: Respuesta no válida del servidor. Ver consola para detalles.', 'error');
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                showStatusMessage('Error de conexión: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Shipping form - intercept button click to show modal
    const shippingForm = document.getElementById('shipping-form');
    const shippingMessageDiv = document.getElementById('shipping-message');
    const shippingSubmitBtn = document.getElementById('shipping-submit-btn');
    
    // Open shipping description modal - attach to window for global access
    window.openShippingDescriptionModal = function() {
        const orderData = window.currentOrderData || {};
        const descripcionTextarea = document.getElementById('descripcion_envio');
        
        if (!descripcionTextarea) {
            console.error('Textarea not found');
            return;
        }
        
        // Pre-fill with work_description from order data
        if (orderData.work_description) {
            descripcionTextarea.value = orderData.work_description;
        }
        
        // Show modal
        const overlay = document.getElementById('shipping-description-modal-overlay');
        if (overlay) {
            overlay.style.display = 'block';
        }
    };
    
    // Attach click handler to shipping submit button
    if (shippingSubmitBtn) {
        shippingSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get tipo_envio value from form
            const shippingForm = document.getElementById('shipping-form');
            if (!shippingForm) return;
            
            const formData = new FormData(shippingForm);
            const tipoEnvio = formData.get('tipo_envio');
            
            // Only show modal if tipo_envio is "parcial"
            if (tipoEnvio === 'parcial') {
                window.openShippingDescriptionModal();
            } else {
                // For "completo", submit directly without description
                window.submitShippingWithoutDescription();
            }
        });
    }
    
    // Submit shipping form without description (for "completo" tipo_envio)
    window.submitShippingWithoutDescription = function() {
        const shippingForm = document.getElementById('shipping-form');
        const shippingMessageDiv = document.getElementById('shipping-message');
        
        if (!shippingForm) {
            alert('Error: No se pudo encontrar el formulario');
            return;
        }
        
        const formData = new FormData(shippingForm);
        const orderId = formData.get('order_id');
        const tipoEnvio = formData.get('tipo_envio');
        const tipoMensajeria = formData.get('tipo_mensajeria');
        
        if (!tipoEnvio) {
            alert('Por favor selecciona un tipo de envio');
            return;
        }
        
        if (!tipoMensajeria) {
            alert('Por favor selecciona un tipo de mensajería');
            return;
        }
        
        const submitBtn = document.getElementById('shipping-submit-btn');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            submitBtn.disabled = true;
        }
        
        // Build URL without shipping description
        const params = new URLSearchParams();
        params.append('order_id', orderId);
        params.append('tipo_envio', tipoEnvio);
        params.append('tipo_mensajeria', tipoMensajeria);
        // Add CSRF token
        const tokenInput = shippingForm.querySelector('input[type="hidden"]');
        if (tokenInput && tokenInput.name && tokenInput.name.includes('token')) {
            params.append(tokenInput.name, tokenInput.value);
        }
        
        const urlEncodedData = params.toString();
        
        fetch('index.php?option=com_ordenproduccion&task=orden.generateShippingSlip&id=' + orderId + '&tipo_envio=' + tipoEnvio + '&tipo_mensajeria=' + tipoMensajeria, {
            method: 'POST',
            body: urlEncodedData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
        .then(response => {
            if (response.ok) {
                window.open('index.php?option=com_ordenproduccion&task=orden.generateShippingSlip&id=' + orderId + '&tipo_envio=' + tipoEnvio + '&tipo_mensajeria=' + tipoMensajeria, '_blank');
                if (shippingMessageDiv) {
                    shippingMessageDiv.innerHTML = 'Envio generado correctamente';
                    shippingMessageDiv.className = 'shipping-message success';
                    shippingMessageDiv.style.display = 'block';
                    setTimeout(() => {
                        shippingMessageDiv.style.display = 'none';
                    }, 5000);
                }
            } else {
                throw new Error('HTTP error! status: ' + response.status);
            }
        })
        .catch(error => {
            console.error('Shipping Error:', error);
            alert('Error al generar envio: ' + error.message);
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-shipping-fast"></i> Generar Envio';
                submitBtn.disabled = false;
            }
        });
    };
    
    // Duplicate request button now handled by modal in orden/duplicate_modal.php
    
    function showStatusMessage(message, type) {
        statusMessageDiv.innerHTML = message;
        statusMessageDiv.className = 'status-message ' + type;
        statusMessageDiv.style.display = 'block';
        
        setTimeout(() => {
            statusMessageDiv.style.display = 'none';
        }, 5000);
    }
    
    function showShippingMessage(message, type) {
        shippingMessageDiv.innerHTML = message;
        shippingMessageDiv.className = 'shipping-message ' + type;
        shippingMessageDiv.style.display = 'block';
        
        setTimeout(() => {
            shippingMessageDiv.style.display = 'none';
        }, 5000);
    }
    
    function showDuplicateMessage(message, type) {
        duplicateMessageDiv.innerHTML = message;
        duplicateMessageDiv.className = 'duplicate-message ' + type;
        duplicateMessageDiv.style.display = 'block';
        
        setTimeout(() => {
            duplicateMessageDiv.style.display = 'none';
        }, 5000);
    }
});
</script>

