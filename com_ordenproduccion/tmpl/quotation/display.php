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
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 30px;
            background: #fff;
            border-radius: 0;
            box-shadow: none;
            width: 100%;
            max-width: none;
        }
        
        .quotation-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .quotation-header h2 {
            color: #007cba;
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .order-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
            display: inline-block;
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
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
            background: #fff;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 3px rgba(0, 124, 186, 0.2);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            font-family: Arial, sans-serif;
            line-height: 1.4;
        }
        
        .btn-submit {
            background: #007cba;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto;
        }
        
        .btn-submit:hover {
            background: #005a87;
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
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
    </style>

<div class="quotation-form-container">
        <div class="quotation-header">
            <h2><?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_FORM_TITLE'); ?></h2>
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