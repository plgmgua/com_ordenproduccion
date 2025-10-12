<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<!-- Duplicar Solicitud Modal -->
<div id="duplicateModal" class="duplicate-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-copy"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_DUPLICATE_REQUEST'); ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeDuplicateModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="duplicateForm" class="modal-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong><?php echo Text::_('COM_ORDENPRODUCCION_DUPLICATE_VALIDATION_INFO'); ?></strong>
                <p><?php echo Text::_('COM_ORDENPRODUCCION_DUPLICATE_VALIDATION_DESC'); ?></p>
            </div>
            
            <!-- Core Information -->
            <fieldset class="form-section">
                <legend><?php echo Text::_('COM_ORDENPRODUCCION_CORE_INFORMATION'); ?></legend>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dup_cliente"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENTE'); ?> *</label>
                        <input type="text" id="dup_cliente" name="cliente" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="dup_nit"><?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?></label>
                        <input type="text" id="dup_nit" name="nit" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dup_valor_factura"><?php echo Text::_('COM_ORDENPRODUCCION_VALOR_FACTURA'); ?></label>
                        <input type="number" id="dup_valor_factura" name="valor_factura" class="form-control" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label for="dup_agente_de_ventas"><?php echo Text::_('COM_ORDENPRODUCCION_AGENTE_VENTAS'); ?></label>
                        <input type="text" id="dup_agente_de_ventas" name="agente_de_ventas" class="form-control" readonly style="background-color: #e9ecef; cursor: not-allowed;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="dup_descripcion_trabajo"><?php echo Text::_('COM_ORDENPRODUCCION_DESCRIPCION_TRABAJO'); ?> *</label>
                    <textarea id="dup_descripcion_trabajo" name="descripcion_trabajo" class="form-control" rows="3" required></textarea>
                </div>
            </fieldset>
            
            <!-- Production Specifications -->
            <fieldset class="form-section">
                <legend><?php echo Text::_('COM_ORDENPRODUCCION_PRODUCTION_SPECS'); ?></legend>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dup_color_impresion"><?php echo Text::_('COM_ORDENPRODUCCION_COLOR_IMPRESION'); ?></label>
                        <input type="text" id="dup_color_impresion" name="color_impresion" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="dup_tiro_retiro"><?php echo Text::_('COM_ORDENPRODUCCION_TIRO_RETIRO'); ?></label>
                        <input type="text" id="dup_tiro_retiro" name="tiro_retiro" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dup_medidas"><?php echo Text::_('COM_ORDENPRODUCCION_MEDIDAS'); ?></label>
                        <input type="text" id="dup_medidas" name="medidas" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="dup_material"><?php echo Text::_('COM_ORDENPRODUCCION_MATERIAL'); ?></label>
                        <input type="text" id="dup_material" name="material" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dup_fecha_entrega"><?php echo Text::_('COM_ORDENPRODUCCION_FECHA_ENTREGA'); ?></label>
                        <input type="date" id="dup_fecha_entrega" name="fecha_entrega" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="dup_fecha_de_solicitud"><?php echo Text::_('COM_ORDENPRODUCCION_FECHA_SOLICITUD'); ?></label>
                        <input type="datetime-local" id="dup_fecha_de_solicitud" name="fecha_de_solicitud" class="form-control">
                    </div>
                </div>
            </fieldset>
            
            <!-- Cotizacion File -->
            <fieldset class="form-section">
                <legend><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACION_FILE'); ?></legend>
                
                <div id="currentFileSection" style="display: none;">
                    <div class="alert alert-success">
                        <i class="fas fa-file-pdf"></i>
                        <strong><?php echo Text::_('COM_ORDENPRODUCCION_CURRENT_FILE'); ?>:</strong>
                        <span id="currentFileName"></span>
                        <button type="button" class="btn btn-sm btn-info" onclick="viewCurrentFile()">
                            <i class="fas fa-eye"></i> <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_FILE'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="dup_cotizacion"><?php echo Text::_('COM_ORDENPRODUCCION_UPLOAD_NEW_FILE'); ?></label>
                    <input type="file" id="dup_cotizacion" name="cotizacion" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small class="form-text text-muted">
                        <?php echo Text::_('COM_ORDENPRODUCCION_FILE_UPLOAD_HELP'); ?>
                    </small>
                </div>
            </fieldset>
            
            <!-- Acabados (Finishing) -->
            <fieldset class="form-section">
                <legend><?php echo Text::_('COM_ORDENPRODUCCION_ACABADOS'); ?></legend>
                
                <div class="acabados-grid">
                    <?php
                    $acabados = [
                        'corte' => 'Corte',
                        'blocado' => 'Blocado',
                        'doblado' => 'Doblado',
                        'laminado' => 'Laminado',
                        'lomo' => 'Lomo',
                        'pegado' => 'Pegado',
                        'numerado' => 'Numerado',
                        'sizado' => 'Sizado',
                        'engrapado' => 'Engrapado',
                        'troquel' => 'Troquel',
                        'barniz' => 'Barniz',
                        'impresion_blanco' => 'Impresión Blanco',
                        'despuntado' => 'Despuntado',
                        'ojetes' => 'Ojetes',
                        'perforado' => 'Perforado'
                    ];
                    
                    foreach ($acabados as $key => $label):
                    ?>
                        <div class="acabado-item">
                            <div class="form-check">
                                <input type="checkbox" id="dup_<?php echo $key; ?>" name="<?php echo $key; ?>" class="form-check-input acabado-checkbox" data-target="detalles_<?php echo $key; ?>">
                                <label for="dup_<?php echo $key; ?>" class="form-check-label"><?php echo $label; ?></label>
                            </div>
                            <input type="text" id="dup_detalles_<?php echo $key; ?>" name="detalles_<?php echo $key; ?>" class="form-control form-control-sm acabado-details" placeholder="Detalles..." style="display: none;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            
            <!-- Shipping Information -->
            <fieldset class="form-section">
                <legend><?php echo Text::_('COM_ORDENPRODUCCION_SHIPPING_INFO'); ?></legend>
                
                <div class="form-group">
                    <label for="dup_direccion_entrega"><?php echo Text::_('COM_ORDENPRODUCCION_DIRECCION_ENTREGA'); ?></label>
                    <textarea id="dup_direccion_entrega" name="direccion_entrega" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dup_contacto_nombre"><?php echo Text::_('COM_ORDENPRODUCCION_CONTACTO_NOMBRE'); ?></label>
                        <input type="text" id="dup_contacto_nombre" name="contacto_nombre" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="dup_contacto_telefono"><?php echo Text::_('COM_ORDENPRODUCCION_CONTACTO_TELEFONO'); ?></label>
                        <input type="tel" id="dup_contacto_telefono" name="contacto_telefono" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="dup_instrucciones_entrega"><?php echo Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ENTREGA'); ?></label>
                    <textarea id="dup_instrucciones_entrega" name="instrucciones_entrega" class="form-control" rows="2"></textarea>
                </div>
            </fieldset>
            
            <!-- Instructions -->
            <fieldset class="form-section">
                <legend><?php echo Text::_('COM_ORDENPRODUCCION_INSTRUCTIONS'); ?></legend>
                
                <div class="form-group">
                    <label for="dup_instrucciones"><?php echo Text::_('COM_ORDENPRODUCCION_GENERAL_INSTRUCTIONS'); ?></label>
                    <textarea id="dup_instrucciones" name="instrucciones" class="form-control" rows="3"></textarea>
                </div>
            </fieldset>
            
            <input type="hidden" id="dup_order_id" name="order_id">
            <input type="hidden" id="dup_cotizacion_url" name="cotizacion_url">
        </form>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-info" onclick="previewPayload()" title="Preview and copy payload for testing">
                <i class="fas fa-eye"></i>
                Preview Payload
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeDuplicateModal()">
                <i class="fas fa-times"></i>
                <?php echo Text::_('JCANCEL'); ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="submitDuplicateRequest()">
                <i class="fas fa-paper-plane"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_SEND_REQUEST'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.duplicate-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
}

.modal-content {
    position: relative;
    background: #fff;
    width: 90%;
    max-width: 1200px;
    max-height: 90vh;
    margin: 5vh auto;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 2px solid #e9ecef;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 12px 12px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 24px;
}

.modal-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-body {
    padding: 30px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px 30px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.form-section {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-section legend {
    font-size: 18px;
    font-weight: 600;
    color: #495057;
    padding: 0 10px;
    width: auto;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.form-check-input {
    margin-right: 8px;
}

.acabados-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.acabado-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
}

.acabado-details {
    margin-top: 8px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: #fff;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-info {
    background: #17a2b8;
    color: #fff;
    border: none;
    padding: 4px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 10px;
}

.btn-sm {
    padding: 4px 12px;
    font-size: 12px;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 2.5vh auto;
    }
    
    .acabados-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Store current file URL
let currentCotizacionUrl = '';

// Open modal and populate with work order data
function openDuplicateModal(orderData) {
    // Populate form fields
    document.getElementById('dup_order_id').value = orderData.id || '';
    document.getElementById('dup_cliente').value = orderData.client_name || '';
    document.getElementById('dup_nit').value = orderData.nit || '';
    document.getElementById('dup_valor_factura').value = orderData.invoice_value || '';
    document.getElementById('dup_descripcion_trabajo').value = orderData.work_description || '';
    document.getElementById('dup_color_impresion').value = orderData.print_color || '';
    document.getElementById('dup_tiro_retiro').value = orderData.tiro_retiro || '';
    document.getElementById('dup_medidas').value = orderData.dimensions || '';
    document.getElementById('dup_fecha_entrega').value = orderData.delivery_date || '';
    document.getElementById('dup_material').value = orderData.material || '';
    document.getElementById('dup_agente_de_ventas').value = orderData.sales_agent || '';
    
    // Set request date to CURRENT date/time (not from old order)
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    document.getElementById('dup_fecha_de_solicitud').value = currentDateTime;
    
    document.getElementById('dup_direccion_entrega').value = orderData.shipping_address || '';
    document.getElementById('dup_contacto_nombre').value = orderData.shipping_contact || '';
    document.getElementById('dup_contacto_telefono').value = orderData.shipping_phone || '';
    document.getElementById('dup_instrucciones_entrega').value = orderData.instrucciones_entrega || '';
    document.getElementById('dup_instrucciones').value = orderData.instructions || '';
    
    // Handle cotizacion file
    if (orderData.quotation_files) {
        currentCotizacionUrl = orderData.quotation_files;
        document.getElementById('dup_cotizacion_url').value = currentCotizacionUrl;
        document.getElementById('currentFileName').textContent = extractFileName(currentCotizacionUrl);
        document.getElementById('currentFileSection').style.display = 'block';
    } else {
        document.getElementById('currentFileSection').style.display = 'none';
    }
    
    // Populate acabados - Map English DB field names to Spanish form field names
    const acabadosMapping = {
        'corte': 'cutting',
        'blocado': 'blocking',
        'doblado': 'folding',
        'laminado': 'laminating',
        'lomo': 'spine',
        'pegado': 'gluing',
        'numerado': 'numbering',
        'sizado': 'sizing',
        'engrapado': 'stapling',
        'troquel': 'die_cutting',
        'barniz': 'varnish',
        'impresion_blanco': 'white_print',
        'despuntado': 'trimming',
        'ojetes': 'eyelets',
        'perforado': 'perforation'
    };
    
    Object.keys(acabadosMapping).forEach(spanishName => {
        const englishName = acabadosMapping[spanishName];
        const checkbox = document.getElementById('dup_' + spanishName);
        const detailsInput = document.getElementById('dup_detalles_' + spanishName);
        
        // Get value from orderData using English field name
        const value = orderData[englishName] || 'NO';
        const details = orderData[englishName + '_details'] || '';
        
        if (checkbox && detailsInput) {
            checkbox.checked = (value === 'SI' || value === 'YES' || value === 'yes' || value === 'si');
            detailsInput.value = details;
            detailsInput.style.display = checkbox.checked ? 'block' : 'none';
        }
    });
    
    // Show modal
    document.getElementById('duplicateModal').style.display = 'block';
}

// Close modal
function closeDuplicateModal() {
    document.getElementById('duplicateModal').style.display = 'none';
}

// View current file
function viewCurrentFile() {
    if (currentCotizacionUrl) {
        // Clean the URL before opening
        const cleanUrl = cleanFileUrl(currentCotizacionUrl);
        window.open(cleanUrl, '_blank');
    }
}

// Clean file URL (remove brackets, quotes, fix double slashes)
function cleanFileUrl(url) {
    if (!url) return '';
    
    // Remove brackets if present
    url = url.replace(/^\[|\]$/g, '');
    
    // Remove quotes if present
    url = url.replace(/^["']|["']$/g, '');
    
    // Parse if it's JSON string
    try {
        if (url.startsWith('[') || url.startsWith('"')) {
            url = JSON.parse(url);
            if (Array.isArray(url)) {
                url = url[0]; // Get first file if it's an array
            }
        }
    } catch (e) {
        // Not JSON, continue with string
    }
    
    // Fix double slashes
    url = url.replace(/\/\//g, '/');
    
    // Ensure it starts with / or http
    if (!url.startsWith('http') && !url.startsWith('/')) {
        url = '/' + url;
    }
    
    // If it's a relative URL, prepend the domain
    if (url.startsWith('/')) {
        url = window.location.origin + url;
    }
    
    return url;
}

// Extract filename from URL
function extractFileName(url) {
    if (!url) return '';
    const cleanUrl = cleanFileUrl(url);
    const parts = cleanUrl.split('/');
    return parts[parts.length - 1];
}

// Handle acabado checkbox changes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.acabado-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const detailsId = 'dup_' + this.dataset.target;
            const detailsInput = document.getElementById(detailsId);
            detailsInput.style.display = this.checked ? 'block' : 'none';
        });
    });
});

// Submit duplicate request
async function submitDuplicateRequest() {
    console.log('=== STARTING DUPLICATE REQUEST SUBMISSION ===');
    
    const form = document.getElementById('duplicateForm');
    const submitBtn = document.querySelector('.modal-footer .btn-primary');
    
    console.log('Form element:', form);
    console.log('Submit button:', submitBtn);
    
    // Validate required fields
    if (!form.checkValidity()) {
        console.log('Form validation failed');
        form.reportValidity();
        return;
    }
    
    console.log('Form validation passed');
    
    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    
    try {
        // Step 1: Get endpoint settings
        console.log('Fetching settings from get_duplicate_settings.php...');
        const settingsResponse = await fetch('/components/com_ordenproduccion/get_duplicate_settings.php');
        
        if (!settingsResponse.ok) {
            console.error('Settings fetch failed:', settingsResponse.status, settingsResponse.statusText);
            throw new Error('No se pudo obtener la configuración del endpoint. Error: ' + settingsResponse.status);
        }
        
        let settings;
        try {
            settings = await settingsResponse.json();
            console.log('Settings received:', settings);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            throw new Error('Error al leer la configuración del endpoint (formato JSON inválido)');
        }
        
        if (!settings.success) {
            const errorMsg = settings.error || 'Error desconocido al obtener configuración';
            console.error('Settings error:', errorMsg);
            throw new Error(errorMsg);
        }
        
        if (!settings.endpoint || settings.endpoint.trim() === '') {
            throw new Error('Endpoint no configurado. Por favor vaya a: Administrador → Orden Produccion → Settings → Configuración de Ventas y configure el "Endpoint para Duplicar Solicitud".');
        }
        
        console.log('Using endpoint:', settings.endpoint);
        
        // Step 2: Handle file upload (if new file selected)
        let uploadedFileUrl = document.getElementById('dup_cotizacion_url').value;
        const fileInput = document.getElementById('dup_cotizacion');
        
        if (fileInput.files.length > 0) {
            uploadedFileUrl = await uploadFile(fileInput.files[0]);
        }
        
        // Step 3: Build URL with all parameters (GET request)
        const urlParams = buildUrlParams(uploadedFileUrl);
        const finalUrl = settings.endpoint + (settings.endpoint.includes('?') ? '&' : '?') + urlParams;
        
        console.log('========================================');
        console.log('URL TO BE SENT (GET REQUEST):');
        console.log('========================================');
        console.log(finalUrl);
        console.log('========================================');
        console.log('URL length:', finalUrl.length, 'characters');
        console.log('========================================');
        
        // Show URL preview
        const formData = getFormData();
        if (confirm('📋 URL ready to send!\n\n✅ Check browser console (F12) to see the full URL.\n\n📊 Data preview:\n' + 
                    'Cliente: ' + formData.cliente + '\n' +
                    'NIT: ' + formData.nit + '\n' +
                    'Valor: ' + formData.valor_factura + '\n' +
                    'Agente: ' + formData.agente_de_ventas + '\n' +
                    'Descripción: ' + formData.descripcion_trabajo.substring(0, 50) + '...\n\n' +
                    '🚀 Click OK to send, CANCEL to abort')) {
            console.log('✅ User confirmed - sending request...');
        } else {
            console.log('❌ User cancelled - aborting submission');
            throw new Error('Envío cancelado por el usuario');
        }
        
        // Step 4: Send GET request by navigating to URL in new window/tab
        console.log('Opening URL in new window...');
        const resultWindow = window.open(finalUrl, '_blank');
        
        if (!resultWindow) {
            throw new Error('No se pudo abrir la ventana. Por favor permite ventanas emergentes.');
        }
        
        // Step 5: Show success message
        showSuccessMessage('Solicitud duplicada enviada correctamente');
        
        // Step 6: Close modal after 2 seconds
        setTimeout(() => {
            closeDuplicateModal();
            // Optionally reload page to show new request
            // window.location.reload();
        }, 2000);
        
    } catch (error) {
        console.error('Submission error:', error);
        console.error('Error type:', typeof error);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        // Ensure we have a meaningful error message
        let errorMessage = 'Error desconocido';
        
        if (error && error.message) {
            errorMessage = error.message;
        } else if (typeof error === 'string') {
            errorMessage = error;
        } else if (error && error.toString) {
            errorMessage = error.toString();
        }
        
        showErrorMessage(errorMessage);
        
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <?php echo Text::_('COM_ORDENPRODUCCION_SEND_REQUEST'); ?>';
    }
}

// Upload file to server
async function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    const response = await fetch('/components/com_ordenproduccion/upload_cotizacion.php', {
        method: 'POST',
        body: formData
    });
    
    if (!response.ok) {
        throw new Error('Error al subir archivo');
    }
    
    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.error || 'Error al subir archivo');
    }
    
    return result.file_url;
}

// Get form data as simple object
function getFormData() {
    return {
        // Core fields
        cliente: document.getElementById('dup_cliente').value,
        nit: document.getElementById('dup_nit').value,
        valor_factura: document.getElementById('dup_valor_factura').value,
        descripcion_trabajo: document.getElementById('dup_descripcion_trabajo').value,
        agente_de_ventas: document.getElementById('dup_agente_de_ventas').value,
        
        // Production specs
        color_impresion: document.getElementById('dup_color_impresion').value,
        tiro_retiro: document.getElementById('dup_tiro_retiro').value,
        medidas: document.getElementById('dup_medidas').value,
        material: document.getElementById('dup_material').value,
        
        // Dates
        fecha_de_solicitud: document.getElementById('dup_fecha_de_solicitud').value,
        fecha_entrega: document.getElementById('dup_fecha_entrega').value,
        
        // Shipping
        direccion_entrega: document.getElementById('dup_direccion_entrega').value,
        contacto_nombre: document.getElementById('dup_contacto_nombre').value,
        contacto_telefono: document.getElementById('dup_contacto_telefono').value,
        instrucciones_entrega: document.getElementById('dup_instrucciones_entrega').value,
        
        // Instructions
        instrucciones: document.getElementById('dup_instrucciones').value
    };
}

// Build URL parameters from form data
function buildUrlParams(cotizacionUrl) {
    const params = new URLSearchParams();
    
    // Meta data
    params.append('supplier_name', 'TecniSystems');
    params.append('source', 'Joomla-DuplicarSolicitud');
    params.append('original_order_id', document.getElementById('dup_order_id').value);
    
    // Core fields
    params.append('client_name', document.getElementById('dup_cliente').value);
    params.append('client_id', document.getElementById('dup_nit').value);
    params.append('contact_name', 'Navla');
    params.append('contact_email', document.getElementById('dup_agente_de_ventas').value); // Using agent as email placeholder
    params.append('contact_phone', document.getElementById('dup_contacto_telefono').value);
    params.append('supplier_name', document.getElementById('dup_agente_de_ventas').value);
    params.append('tipo_entrega', 'domicilio');
    
    // Delivery address
    params.append('delivery_address', document.getElementById('dup_direccion_entrega').value);
    params.append('instrucciones_entrega', document.getElementById('dup_instrucciones_entrega').value);
    
    // Contact person
    params.append('contact_person_name', document.getElementById('dup_contacto_nombre').value);
    params.append('contact_person_phone', document.getElementById('dup_contacto_telefono').value);
    
    // Production details
    params.append('work_description', document.getElementById('dup_descripcion_trabajo').value);
    params.append('print_color', document.getElementById('dup_color_impresion').value);
    params.append('tiro_retiro', document.getElementById('dup_tiro_retiro').value);
    params.append('dimensions', document.getElementById('dup_medidas').value);
    params.append('material', document.getElementById('dup_material').value);
    
    // Dates
    params.append('request_date', document.getElementById('dup_fecha_de_solicitud').value);
    params.append('delivery_date', document.getElementById('dup_fecha_entrega').value);
    
    // File (if uploaded)
    if (cotizacionUrl) {
        params.append('cotizacion', cotizacionUrl);
    }
    
    // Acabados (finishing)
    const acabadosMapping = {
        'corte': 'cutting',
        'blocado': 'blocking',
        'doblado': 'folding',
        'laminado': 'laminating',
        'lomo': 'spine',
        'pegado': 'gluing',
        'numerado': 'numbering',
        'sizado': 'sizing',
        'engrapado': 'stapling',
        'troquel': 'die_cutting',
        'barniz': 'varnish',
        'impresion_blanco': 'white_print',
        'despuntado': 'trimming',
        'ojetes': 'eyelets',
        'perforado': 'perforation'
    };
    
    Object.keys(acabadosMapping).forEach(spanishName => {
        const englishName = acabadosMapping[spanishName];
        const checkbox = document.getElementById('dup_' + spanishName);
        const detailsInput = document.getElementById('dup_detalles_' + spanishName);
        
        if (checkbox) {
            params.append(englishName, checkbox.checked ? 'SI' : 'NO');
            if (checkbox.checked && detailsInput && detailsInput.value) {
                params.append(englishName + '_details', detailsInput.value);
            }
        }
    });
    
    // Instructions
    params.append('instructions', document.getElementById('dup_instrucciones').value);
    
    return params.toString();
}

// Show success message
function showSuccessMessage(message) {
    const modalBody = document.querySelector('.modal-body');
    const alert = document.createElement('div');
    alert.className = 'alert alert-success';
    alert.style.position = 'sticky';
    alert.style.top = '0';
    alert.style.zIndex = '1000';
    alert.innerHTML = '<i class="fas fa-check-circle"></i> <strong>' + message + '</strong>';
    modalBody.insertBefore(alert, modalBody.firstChild);
    modalBody.scrollTop = 0;
}

// Show error message
function showErrorMessage(message) {
    console.log('Showing error message:', message);
    
    const modalBody = document.querySelector('.modal-body');
    
    // Remove any existing error alerts
    const existingAlerts = modalBody.querySelectorAll('.alert-danger');
    existingAlerts.forEach(alert => alert.remove());
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.style.position = 'sticky';
    alert.style.top = '0';
    alert.style.zIndex = '1000';
    alert.style.marginBottom = '20px';
    alert.style.padding = '15px';
    alert.style.border = '2px solid #dc3545';
    alert.style.borderRadius = '8px';
    alert.style.backgroundColor = '#f8d7da';
    alert.style.color = '#721c24';
    
    // Escape HTML to prevent XSS
    const escapedMessage = String(message).replace(/[&<>"']/g, function (match) {
        const escape = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return escape[match];
    });
    
    alert.innerHTML = '<i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> ' + escapedMessage;
    modalBody.insertBefore(alert, modalBody.firstChild);
    modalBody.scrollTop = 0;
    
    console.log('Error alert added to DOM');
}

// Preview URL before sending
function previewPayload() {
    console.log('=== PREVIEWING URL ===');
    
    // Get current file URL
    let uploadedFileUrl = document.getElementById('dup_cotizacion_url').value;
    const fileInput = document.getElementById('dup_cotizacion');
    
    if (fileInput.files.length > 0) {
        uploadedFileUrl = 'NEW FILE WILL BE UPLOADED: ' + fileInput.files[0].name;
    }
    
    // Build URL parameters
    const urlParams = buildUrlParams(uploadedFileUrl);
    
    // Get endpoint (or use placeholder)
    const endpoint = 'https://your-endpoint.com/api';
    const fullUrl = endpoint + '?' + urlParams;
    
    console.log('========================================');
    console.log('URL PREVIEW (GET REQUEST):');
    console.log('========================================');
    console.log(fullUrl);
    console.log('========================================');
    console.log('URL Length:', fullUrl.length, 'characters');
    console.log('Parameters:', urlParams.split('&').length);
    console.log('========================================');
    
    // Show parameter breakdown
    const params = new URLSearchParams(urlParams);
    console.log('PARAMETERS:');
    for (const [key, value] of params) {
        console.log('  ' + key + ' = ' + value);
    }
    console.log('========================================');
    
    // Copy to clipboard
    if (navigator.clipboard) {
        navigator.clipboard.writeText(fullUrl).then(() => {
            alert('✅ URL copied to clipboard!\n\n' +
                  '📋 The complete URL with parameters has been copied.\n\n' +
                  '🔍 You can:\n' +
                  '  • Paste it in browser address bar\n' +
                  '  • Test it directly\n' +
                  '  • Share it with your team\n\n' +
                  '📊 URL Stats:\n' +
                  '  • Length: ' + fullUrl.length + ' characters\n' +
                  '  • Parameters: ' + urlParams.split('&').length + '\n' +
                  '  • Method: GET request\n\n' +
                  '💡 Check browser console (F12) for full details!');
        }).catch(err => {
            console.error('Failed to copy to clipboard:', err);
            alert('URL (check console for full version):\n\n' + fullUrl.substring(0, 500) + '...');
        });
    } else {
        alert('URL (check console for full version):\n\n' + fullUrl.substring(0, 500) + '...');
    }
    
    showSuccessMessage('URL preview generated! Check console and clipboard.');
}

// Close modal when clicking overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeDuplicateModal();
    }
});
</script>

