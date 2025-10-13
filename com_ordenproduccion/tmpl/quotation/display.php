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

<!-- Font Awesome for icons (if not already loaded) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
        /* Reset and base styles for standalone form */
        * {
            box-sizing: border-box;
        }
        
        .quotation-form-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 35px;
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
        
        .original-quotation-link {
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        
        .original-quotation-link h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
            font-weight: 600;
        }
        
        .quotation-link {
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
        
        .quotation-link:hover {
            background: linear-gradient(135deg, #005a87 0%, #007cba 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 124, 186, 0.4);
            color: white;
            text-decoration: none;
        }
    </style>

<div class="quotation-form-container">
        <div class="quotation-header">
            <h2>Crear Factura</h2>
            <div class="order-info">
                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?>:</strong> <?php echo htmlspecialchars($this->orderNumber); ?>
            </div>
        </div>

        <div class="form-note">
            <i class="fas fa-info-circle"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_FORM_NOTE'); ?>
        </div>

        <form id="quotationForm" onsubmit="submitQuotationForm(event)">
            <div class="form-group">
                <label for="cliente">
                    <?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?> <span class="required">*</span>
                </label>
                <input type="text" 
                       id="cliente" 
                       name="cliente" 
                       value="<?php echo htmlspecialchars($orderData->client_name ?? ''); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="nit">
                    <?php echo Text::_('COM_ORDENPRODUCCION_NIT'); ?> <span class="required">*</span>
                </label>
                <input type="text" 
                       id="nit" 
                       name="nit" 
                       value="<?php echo htmlspecialchars($orderData->nit ?? ''); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="direccion">
                    <?php echo Text::_('COM_ORDENPRODUCCION_ADDRESS'); ?> <span class="required">*</span>
                </label>
                <input type="text" 
                       id="direccion" 
                       name="direccion" 
                       value="<?php echo htmlspecialchars($orderData->shipping_address ?? ''); ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="detalles">
                    <?php echo Text::_('COM_ORDENPRODUCCION_DETAILS'); ?>
                </label>
                <textarea id="detalles" 
                          name="detalles" 
                          placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_DETAILS_PLACEHOLDER'); ?>"><?php echo htmlspecialchars($orderData->work_description ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_SUBMIT_QUOTATION'); ?>
                </button>
            </div>
        </form>
        
        <!-- Original Quotation File Link -->
        <?php if (!empty($this->quotationFile)): ?>
        <div class="original-quotation-link">
            <h4>
                <i class="fas fa-file-alt"></i>
                Cotización Original
            </h4>
            <a href="<?php echo htmlspecialchars($this->quotationFile); ?>" target="_blank" class="quotation-link">
                <i class="fas fa-external-link-alt"></i>
                Ver Cotización PDF
            </a>
        </div>
        <?php endif; ?>
</div>

<script>
    function submitQuotationForm(event) {
        event.preventDefault();
        
        const submitButton = event.target.querySelector('.btn-submit');
        const originalText = submitButton.innerHTML;
        
        // Disable button and show loading
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROCESSING'); ?>...';
        
        // Get form data
        const formData = {
            order_id: <?php echo $this->orderId; ?>,
            order_number: '<?php echo htmlspecialchars($this->orderNumber); ?>',
            cliente: document.getElementById('cliente').value,
            nit: document.getElementById('nit').value,
            direccion: document.getElementById('direccion').value,
            detalles: document.getElementById('detalles').value
        };
        
        // Simulate form submission (you can modify this to actually save the data)
        setTimeout(() => {
            alert('<?php echo Text::_('COM_ORDENPRODUCCION_FORM_SUBMITTED_SUCCESS'); ?>');
            
            // Re-enable button
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
            
            // Optionally close the modal after success
            setTimeout(() => {
                if (typeof closeQuotationModal === 'function') {
                    closeQuotationModal();
                }
            }, 1500);
        }, 2000);
    }
</script>