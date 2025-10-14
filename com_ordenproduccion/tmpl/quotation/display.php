<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Get order data from the database
$orderData = $this->getOrderData();
?>

<!-- Font Awesome for icons (if not already loaded) - v3.12.0 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css?v=3.12.0">

<style>
        /* Reset and base styles for standalone form - v3.15.0 - CLIENT TABLE LAYOUT */
        * {
            box-sizing: border-box;
        }
        
        .quotation-form-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .quotation-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            border-radius: 10px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 124, 186, 0.3);
        }
        
        .quotation-header h2 {
            color: white;
            margin: 0 0 12px 0;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .order-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            display: inline-block;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 8px;
        }
        
        .version-info {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .client-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            table-layout: fixed;
        }
        
        .client-table th {
            background: #007cba;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #dee2e6;
        }
        
        .client-table td {
            padding: 0;
            border: 1px solid #dee2e6;
        }
        
        .client-table input {
            width: 100%;
            padding: 12px 15px;
            border: none;
            font-size: 14px;
            background: transparent;
            transition: background-color 0.3s;
        }
        
        .client-table input:focus {
            outline: none;
            background: #f8f9fa;
        }
        
        .client-table tr:hover {
            background: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: #fff;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            font-family: Arial, sans-serif;
            line-height: 1.4;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 auto;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-submit:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-note {
            background: #e7f3ff;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 3px solid #007cba;
            font-size: 13px;
            color: #333;
        }
        
        .pdf-viewer-section {
            margin-top: 25px;
            margin-left: -25px;
            margin-right: -25px;
            margin-bottom: -25px;
            padding: 0;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
            border: 1px solid #e9ecef;
        }
        
        .pdf-viewer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 15px 20px 10px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .pdf-viewer-title {
            margin: 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-controls {
            display: flex;
            gap: 8px;
        }
        
        .pdf-control-btn {
            padding: 6px 12px;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .pdf-control-btn:hover {
            background: #005a87;
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }
        
        .pdf-embed-container {
            position: relative;
            width: 100%;
            height: 70vh;
            min-height: 600px;
            border: none;
            border-radius: 0;
            overflow: hidden;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        
        .pdf-embed {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .pdf-fallback {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .pdf-fallback-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .pdf-fallback-text {
            color: #495057;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .pdf-fallback-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 124, 186, 0.3);
        }
        
        .pdf-fallback-link:hover {
            background: linear-gradient(135deg, #005a87 0%, #007cba 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 124, 186, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .items-table-section {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .items-table-title {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .items-table th {
            background: #007cba;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #dee2e6;
        }
        
        .items-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        
        .items-table tr:last-child td {
            border-bottom: 1px solid #dee2e6;
        }
        
        .items-table input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            transition: border-color 0.3s;
        }
        
        .items-table input:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
        }
        
        .items-table tr:hover {
            background: #f8f9fa;
        }
        
        .quotation-image-container {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .quotation-image {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .quotation-image:hover {
            transform: scale(1.02);
        }
    </style>

<div class="quotation-form-container">
        <div class="quotation-header">
            <h2>Crear Factura para orden de trabajo #<?php echo htmlspecialchars($this->orderNumber); ?></h2>
        </div>

        <div class="form-note">
            <i class="fas fa-info-circle"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FORM_NOTE'); ?>
        </div>

        <form id="quotationForm" onsubmit="submitQuotationForm(event)">
            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($this->orderId); ?>">
            <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($this->orderNumber); ?>">

            <!-- Client Information Table -->
            <table class="client-table">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?> <span class="required">*</span></th>
                        <th style="width: 20%;"><?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?> <span class="required">*</span></th>
                        <th style="width: 55%;"><?php echo Text::_('COM_ORDENPRODUCCION_ADDRESS'); ?> <span class="required">*</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" id="cliente" name="cliente" value="<?php echo htmlspecialchars($orderData->client_name ?? ''); ?>" required></td>
                        <td><input type="text" id="nit" name="nit" value="<?php echo htmlspecialchars($orderData->nit ?? ''); ?>" required></td>
                        <td><input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($orderData->shipping_address ?? ''); ?>" required></td>
                    </tr>
                </tbody>
            </table>

            <!-- Items Table Section -->
            <div class="items-table-section">
                <h4 class="items-table-title">
                    <i class="fas fa-list"></i>
                    Detalles de Factura
                </h4>
                <table class="items-table" id="invoiceItemsTable">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Cantidad</th>
                            <th style="width: 40%;">Descripción</th>
                            <th style="width: 20%;">Precio Unitario</th>
                            <th style="width: 20%;">Subtotal</th>
                            <th style="width: 5%;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItemsBody">
                        <tr class="invoice-item-row">
                            <td><input type="number" name="items[1][cantidad]" class="cantidad-input" placeholder="1" min="1" step="1" oninput="calculateSubtotal(this)"></td>
                            <td><input type="text" name="items[1][descripcion]" placeholder="Descripción del artículo"></td>
                            <td><input type="number" name="items[1][precio_unitario]" class="precio-unitario-input" placeholder="0.00" min="0" step="0.01" oninput="calculateSubtotal(this)"></td>
                            <td><input type="number" name="items[1][subtotal]" class="subtotal-input" placeholder="0.00" readonly></td>
                            <td><button type="button" class="btn-delete-row" onclick="deleteRow(this)" style="background: #dc3545; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;"><i class="fas fa-trash"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight: bold; padding: 10px;">TOTAL:</td>
                            <td><input type="number" id="totalAmount" name="total_amount" placeholder="0.00" readonly style="font-weight: bold; background: #f8f9fa;"></td>
                            <td><button type="button" class="btn-add-row" onclick="addRow()" style="background: #28a745; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;"><i class="fas fa-plus"></i></button></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_SUBMIT_QUOTATION'); ?>
                </button>
            </div>
        </form>
        
        <!-- Embedded PDF Viewer -->
        <?php if (!empty($this->quotationFile)): ?>
        <div class="pdf-viewer-section">
            <div class="pdf-viewer-header">
                <h4 class="pdf-viewer-title">
                    <i class="fas fa-file-pdf"></i>
                    Cotización Original
                </h4>
                <div class="pdf-controls">
                    <button type="button" class="pdf-control-btn" onclick="togglePdfSize()">
                        <i class="fas fa-expand"></i>
                        Expandir
                    </button>
                    <a href="<?php echo htmlspecialchars($this->quotationFile); ?>" target="_blank" class="pdf-control-btn">
                        <i class="fas fa-external-link-alt"></i>
                        Abrir
                    </a>
                </div>
            </div>
            
            <div class="pdf-embed-container" id="pdfContainer">
                <iframe 
                    src="<?php echo htmlspecialchars($this->quotationFile); ?>#toolbar=1&navpanes=1&scrollbar=1&page=1&view=FitH" 
                    class="pdf-embed"
                    id="pdfEmbed"
                    title="Cotización PDF">
                    <div class="pdf-fallback">
                        <i class="fas fa-file-pdf pdf-fallback-icon"></i>
                        <p class="pdf-fallback-text">
                            Tu navegador no soporta la visualización de PDFs embebidos.
                        </p>
                        <a href="<?php echo htmlspecialchars($this->quotationFile); ?>" target="_blank" class="pdf-fallback-link">
                            <i class="fas fa-external-link-alt"></i>
                            Abrir PDF en nueva ventana
                        </a>
                    </div>
                </iframe>
            </div>
        </div>
        <?php endif; ?>
</div>

<script>
    let rowCounter = 1; // Keep track of row numbers
    
    function addRow() {
        rowCounter++;
        const tbody = document.getElementById('invoiceItemsBody');
        const newRow = document.createElement('tr');
        newRow.className = 'invoice-item-row';
        newRow.innerHTML = `
            <td><input type="number" name="items[${rowCounter}][cantidad]" class="cantidad-input" placeholder="1" min="1" step="1" oninput="calculateSubtotal(this)"></td>
            <td><input type="text" name="items[${rowCounter}][descripcion]" placeholder="Descripción del artículo"></td>
            <td><input type="number" name="items[${rowCounter}][precio_unitario]" class="precio-unitario-input" placeholder="0.00" min="0" step="0.01" oninput="calculateSubtotal(this)"></td>
            <td><input type="number" name="items[${rowCounter}][subtotal]" class="subtotal-input" placeholder="0.00" readonly></td>
            <td><button type="button" class="btn-delete-row" onclick="deleteRow(this)" style="background: #dc3545; color: white; border: none; padding: 5px 8px; border-radius: 3px; cursor: pointer;"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(newRow);
    }
    
    function deleteRow(button) {
        const row = button.closest('tr');
        row.remove();
        calculateTotal();
    }
    
    function calculateSubtotal(input) {
        const row = input.closest('tr');
        const cantidad = parseFloat(row.querySelector('.cantidad-input').value) || 0;
        const precioUnitario = parseFloat(row.querySelector('.precio-unitario-input').value) || 0;
        const subtotal = cantidad * precioUnitario;
        
        const subtotalInput = row.querySelector('.subtotal-input');
        subtotalInput.value = subtotal.toFixed(2);
        
        calculateTotal();
    }
    
    function calculateTotal() {
        const subtotalInputs = document.querySelectorAll('.subtotal-input');
        let total = 0;
        
        subtotalInputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });
        
        const totalInput = document.getElementById('totalAmount');
        totalInput.value = total.toFixed(2);
    }
    
    function submitQuotationForm(event) {
        event.preventDefault();
        
        const submitButton = event.target.querySelector('.btn-submit');
        const originalText = submitButton.innerHTML;
        
        // Disable button and show loading
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROCESSING'); ?>...';
        
        // Get form data
        const formData = new FormData();
        formData.append('task', 'invoice.create');
        formData.append('option', 'com_ordenproduccion');
        formData.append('<?php echo JSession::getFormToken(); ?>', '1');  // CSRF token
        formData.append('order_id', <?php echo $this->orderId; ?>);
        formData.append('order_number', '<?php echo htmlspecialchars($this->orderNumber); ?>');
        formData.append('cliente', document.getElementById('cliente').value);
        formData.append('nit', document.getElementById('nit').value);
        formData.append('direccion', document.getElementById('direccion').value);
        
        // Get invoice items data
        const rows = document.querySelectorAll('.invoice-item-row');
        rows.forEach((row, index) => {
            const cantidad = row.querySelector('.cantidad-input').value;
            const descripcion = row.querySelector('input[name*="[descripcion]"]').value;
            const precioUnitario = row.querySelector('.precio-unitario-input').value;
            const subtotal = row.querySelector('.subtotal-input').value;
            
            if (cantidad && precioUnitario) {
                formData.append(`items[${index + 1}][cantidad]`, cantidad);
                formData.append(`items[${index + 1}][descripcion]`, descripcion);
                formData.append(`items[${index + 1}][precio_unitario]`, precioUnitario);
                formData.append(`items[${index + 1}][subtotal]`, subtotal);
            }
        });
        
        // Submit to backend via Joomla's index.php
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if redirect or success
            if (response.redirected) {
                window.location.href = response.url;
            } else if (response.ok) {
                // Success - redirect to work orders page
                window.location.href = 'index.php?option=com_ordenproduccion&view=administracion&tab=workorders';
            } else {
                throw new Error('Server error: ' + response.status);
            }
        })
        .catch(error => {
            // Error - re-enable button and show error
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
            alert('Error al crear la factura. Por favor, intente nuevamente.');
            console.error('Error:', error);
        });
    }

    // PDF Viewer Controls
    let isPdfExpanded = false;
    
    function togglePdfSize() {
        const container = document.getElementById('pdfContainer');
        const expandBtn = document.querySelector('.pdf-control-btn i');
        const expandText = expandBtn.nextSibling;
        
        if (isPdfExpanded) {
            // Collapse to normal size
            container.style.height = '70vh';
            container.style.minHeight = '600px';
            container.style.position = 'relative';
            container.style.zIndex = 'auto';
            container.style.borderRadius = '8px';
            container.style.width = '100%';
            container.style.left = 'auto';
            container.style.top = 'auto';
            expandBtn.className = 'fas fa-expand';
            expandText.textContent = ' Expandir';
            isPdfExpanded = false;
        } else {
            // Expand to full size
            container.style.height = '80vh';
            container.style.position = 'fixed';
            container.style.top = '10vh';
            container.style.left = '5%';
            container.style.width = '90%';
            container.style.zIndex = '9999';
            container.style.borderRadius = '12px';
            container.style.boxShadow = '0 10px 40px rgba(0,0,0,0.3)';
            expandBtn.className = 'fas fa-compress';
            expandText.textContent = ' Contraer';
            isPdfExpanded = true;
        }
    }

    // Close expanded PDF on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && isPdfExpanded) {
            togglePdfSize();
        }
    });
</script>