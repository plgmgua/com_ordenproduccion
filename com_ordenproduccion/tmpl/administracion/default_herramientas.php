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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

$banks = $this->banks ?? [];
$token = HTMLHelper::_('form.token');
$tokenName = Session::getFormToken();
?>

<style>
.banks-management-container {
    max-width: 1200px;
    margin: 0 auto;
}

.banks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #dee2e6;
}

.banks-header h2 {
    margin: 0;
    color: #333;
}

.btn-add-bank {
    background: #667eea;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.3s;
}

.btn-add-bank:hover {
    background: #5568d3;
}

.banks-list {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.bank-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    background: white;
    transition: background 0.2s;
    cursor: move;
}

.bank-item:hover {
    background: #f8f9fa;
}

.bank-item:last-child {
    border-bottom: none;
}

.bank-item.dragging {
    opacity: 0.5;
    background: #e9ecef;
}

.bank-handle {
    cursor: move;
    color: #6c757d;
    margin-right: 15px;
    font-size: 18px;
}

.bank-handle:hover {
    color: #667eea;
}

.bank-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 20px;
}

.bank-code {
    font-weight: 600;
    color: #495057;
    min-width: 150px;
}

.bank-name {
    flex: 1;
    color: #212529;
}

.bank-default-badge {
    background: #28a745;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
}

.bank-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-set-default,
.btn-edit-bank,
.btn-delete-bank {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.btn-set-default {
    background: #ffc107;
    color: #212529;
}

.btn-set-default:hover {
    background: #e0a800;
}

.btn-edit-bank {
    background: #17a2b8;
    color: white;
}

.btn-edit-bank:hover {
    background: #138496;
}

.btn-delete-bank {
    background: #dc3545;
    color: white;
}

.btn-delete-bank:hover {
    background: #c82333;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #dee2e6;
}

/* Modal Styles */
.bank-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.bank-modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.bank-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #dee2e6;
}

.bank-modal-header h3 {
    margin: 0;
    color: #333;
}

.close-modal {
    background: none;
    border: none;
    font-size: 28px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    line-height: 30px;
}

.close-modal:hover {
    color: #333;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group-checkbox input[type="checkbox"] {
    width: auto;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.btn-save,
.btn-cancel {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-save {
    background: #667eea;
    color: white;
}

.btn-save:hover {
    background: #5568d3;
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
}

.alert {
    padding: 12px 20px;
    margin-bottom: 20px;
    border-radius: 4px;
    border-left: 4px solid;
}

.alert-success {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

.alert-info {
    background: #d1ecf1;
    border-color: #17a2b8;
    color: #0c5460;
}
</style>

<div class="banks-management-container">
    <div class="banks-header">
        <h2>
            <i class="fas fa-university"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_BANKS_MANAGEMENT_TITLE'); ?>
        </h2>
        <button class="btn-add-bank" onclick="openBankModal()">
            <i class="fas fa-plus"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_BANK_ADD_NEW'); ?>
        </button>
    </div>

    <div id="alert-container"></div>

    <div class="banks-list" id="banks-list">
        <?php if (empty($banks)): ?>
            <div class="empty-state">
                <i class="fas fa-university"></i>
                <p><?php echo Text::_('COM_ORDENPRODUCCION_BANKS_EMPTY_STATE'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($banks as $bank): ?>
                <div class="bank-item" data-id="<?php echo $bank->id; ?>" data-code="<?php echo htmlspecialchars($bank->code); ?>">
                    <div class="bank-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="bank-info">
                        <div class="bank-code"><?php echo htmlspecialchars($bank->code); ?></div>
                        <div class="bank-name">
                            <?php echo htmlspecialchars($bank->name); ?>
                            <?php if ($bank->is_default): ?>
                                <span class="bank-default-badge"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_DEFAULT'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bank-actions">
                        <?php if (!$bank->is_default): ?>
                            <button class="btn-set-default" onclick="setDefaultBank(<?php echo $bank->id; ?>)">
                                <i class="fas fa-star"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_BANK_SET_DEFAULT'); ?>
                            </button>
                        <?php endif; ?>
                        <button class="btn-edit-bank" onclick="editBank(<?php echo $bank->id; ?>, '<?php echo htmlspecialchars($bank->code, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($bank->name, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($bank->name_en ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($bank->name_es ?? '', ENT_QUOTES); ?>')">
                            <i class="fas fa-edit"></i>
                            <?php echo Text::_('JEDIT'); ?>
                        </button>
                        <button class="btn-delete-bank" onclick="deleteBank(<?php echo $bank->id; ?>)">
                            <i class="fas fa-trash"></i>
                            <?php echo Text::_('JDELETE'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bank Modal -->
<div id="bank-modal" class="bank-modal">
    <div class="bank-modal-content">
        <div class="bank-modal-header">
            <h3 id="modal-title"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ADD_NEW'); ?></h3>
            <button class="close-modal" onclick="closeBankModal()">&times;</button>
        </div>
        <form id="bank-form">
            <input type="hidden" id="bank-id" name="id" value="0">
            <?php echo $token; ?>
            
            <div class="form-group">
                <label for="bank-code"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_CODE'); ?> *</label>
                <input type="text" id="bank-code" name="code" required pattern="[a-z0-9_]+" placeholder="banco_example">
                <small style="color: #6c757d;"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_CODE_DESC'); ?></small>
            </div>

            <div class="form-group">
                <label for="bank-name"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_NAME'); ?> *</label>
                <input type="text" id="bank-name" name="name" required placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_BANK_NAME_PLACEHOLDER'); ?>">
            </div>

            <div class="form-group">
                <label for="bank-name-en"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_NAME_EN'); ?></label>
                <input type="text" id="bank-name-en" name="name_en" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_BANK_NAME_EN_PLACEHOLDER'); ?>">
            </div>

            <div class="form-group">
                <label for="bank-name-es"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_NAME_ES'); ?></label>
                <input type="text" id="bank-name-es" name="name_es" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_BANK_NAME_ES_PLACEHOLDER'); ?>">
            </div>

            <div class="form-group form-group-checkbox">
                <input type="checkbox" id="bank-is-default" name="is_default" value="1">
                <label for="bank-is-default"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_SET_AS_DEFAULT'); ?></label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeBankModal()">
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i>
                    <?php echo Text::_('JSAVE'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
(function() {
    'use strict';
    
    const baseUrl = '<?php echo Route::_('index.php?option=com_ordenproduccion', false); ?>';
    
    // Initialize drag and drop
    const banksList = document.getElementById('banks-list');
    if (banksList) {
        const sortable = Sortable.create(banksList, {
            handle: '.bank-handle',
            animation: 150,
            onEnd: function(evt) {
                const order = Array.from(banksList.querySelectorAll('.bank-item')).map(item => item.dataset.id);
                reorderBanks(order);
            }
        });
    }
    
    // Open modal for new bank
    window.openBankModal = function() {
        document.getElementById('modal-title').textContent = '<?php echo Text::_('COM_ORDENPRODUCCION_BANK_ADD_NEW'); ?>';
        document.getElementById('bank-form').reset();
        document.getElementById('bank-id').value = '0';
        document.getElementById('bank-modal').style.display = 'block';
    };
    
    // Close modal
    window.closeBankModal = function() {
        document.getElementById('bank-modal').style.display = 'none';
    };
    
    // Edit bank
    window.editBank = function(id, code, name, nameEn, nameEs) {
        document.getElementById('modal-title').textContent = '<?php echo Text::_('COM_ORDENPRODUCCION_BANK_EDIT'); ?>';
        document.getElementById('bank-id').value = id;
        document.getElementById('bank-code').value = code;
        document.getElementById('bank-code').readOnly = true;
        document.getElementById('bank-name').value = name;
        document.getElementById('bank-name-en').value = nameEn || '';
        document.getElementById('bank-name-es').value = nameEs || '';
        document.getElementById('bank-modal').style.display = 'block';
    };
    
    // Save bank
    document.getElementById('bank-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('task', 'bank.save');
        formData.append('format', 'json');
        // Token is already in the form via hidden input
        
        fetch(baseUrl + '&task=bank.save&format=json', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'Error: ' + error.message);
        });
    });
    
    // Delete bank
    window.deleteBank = function(id) {
        if (!confirm('<?php echo Text::_('COM_ORDENPRODUCCION_BANK_DELETE_CONFIRM'); ?>')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('task', 'bank.delete');
        formData.append('format', 'json');
        formData.append('id', id);
        formData.append('<?php echo $tokenName; ?>', '1');
        
        fetch(baseUrl + '&task=bank.delete&format=json', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'Error: ' + error.message);
        });
    };
    
    // Set default bank
    window.setDefaultBank = function(id) {
        const formData = new FormData();
        formData.append('task', 'bank.setDefault');
        formData.append('format', 'json');
        formData.append('id', id);
        formData.append('<?php echo $tokenName; ?>', '1');
        
        fetch(baseUrl + '&task=bank.setDefault&format=json', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'Error: ' + error.message);
        });
    };
    
    // Reorder banks
    function reorderBanks(order) {
        const formData = new FormData();
        formData.append('task', 'bank.reorder');
        formData.append('format', 'json');
        order.forEach((id) => {
            formData.append('order[]', id);
        });
        formData.append('<?php echo $tokenName; ?>', '1');
        
        fetch(baseUrl + '&task=bank.reorder&format=json', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
            } else {
                showAlert('error', data.message);
                location.reload();
            }
        })
        .catch(error => {
            showAlert('error', 'Error: ' + error.message);
            location.reload();
        });
    }
    
    // Show alert
    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type;
        alert.textContent = message;
        container.innerHTML = '';
        container.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
    
    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('bank-modal');
        if (event.target === modal) {
            closeBankModal();
        }
    };
})();
</script>
