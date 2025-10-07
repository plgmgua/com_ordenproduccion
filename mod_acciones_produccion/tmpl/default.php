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
    <?php if (!$hasProductionAccess): ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock"></i>
            <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ACCESS_DENIED'); ?>
        </div>
    <?php elseif ($orderId && $workOrderData): ?>
        
                <!-- PDF Generation Buttons (Top) -->
                <div class="pdf-actions">
                    <a href="index.php?option=com_ordenproduccion&task=orden.generatePdf&id=<?php echo $orderId; ?>" 
                       target="_blank" 
                       class="btn btn-primary btn-block">
                        <i class="fas fa-file-pdf"></i>
                        Generar PDF
                    </a>
                    
                    <a href="index.php?option=com_ordenproduccion&task=orden.generateShippingSlip&id=<?php echo $orderId; ?>" 
                       target="_blank" 
                       class="btn btn-info btn-block">
                        <i class="fas fa-shipping-fast"></i>
                        Generar Envio
                    </a>
                </div>
        
                <!-- Work Order Info with Status Change -->
                <div class="work-order-info">
                    <p><strong>Estado Actual:</strong> 
                        <span class="status-badge status-<?php echo htmlspecialchars($workOrderData->status ?? 'en_progreso'); ?>">
                            <?php echo htmlspecialchars($statusOptions[$workOrderData->status ?? 'en_progreso'] ?? 'En Progreso'); ?>
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

    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No se encontró información de la orden de trabajo.
        </div>
    <?php endif; ?>
</div>

<style>
.mod-acciones-produccion {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
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

        .status-en_progreso { 
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); 
            color: #fff; 
        }
        .status-terminada { 
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); 
            color: #fff; 
        }
        .status-entregada { 
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
    const form = document.getElementById('status-change-form');
    const messageDiv = document.getElementById('status-message');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const orderId = formData.get('order_id');
            const newStatus = formData.get('new_status');
            
            if (!newStatus) {
                showMessage('Por favor selecciona un estado', 'error');
                return;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
            submitBtn.disabled = true;
            
            // Convert FormData to URL-encoded string
            const urlEncodedData = new URLSearchParams(formData).toString();
            
            // Make AJAX request - use direct database version (gets user from session table)
            fetch('/components/com_ordenproduccion/change_status_direct_db.php', {
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
                return response.text(); // Get as text first
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showMessage(data.message, 'success');
                        // Reload page after 2 seconds to show updated status
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showMessage(data.message, 'error');
                    }
                } catch (e) {
                    console.error('Response text:', text);
                    showMessage('Error: Respuesta no válida del servidor', 'error');
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                showMessage('Error de conexión: ' + error.message, 'error');
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    function showMessage(message, type) {
        messageDiv.innerHTML = message;
        messageDiv.className = 'status-message ' + type;
        messageDiv.style.display = 'block';
        
        // Hide message after 5 seconds
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
});
</script>
