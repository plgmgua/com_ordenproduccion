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

$lang = Factory::getApplication()->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$paymentTypes = isset($this->paymentTypes) && is_array($this->paymentTypes) ? $this->paymentTypes : [];
$token = HTMLHelper::_('form.token');
$tokenName = Session::getFormToken();
$langTag = $lang->getTag();
$isSpanish = (strpos($langTag, 'es') === 0);
?>

<style>
.paymenttypes-management-container { max-width: 1200px; margin: 0 auto; }
.paymenttypes-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6; gap: 20px; }
.paymenttypes-header h2 { margin: 0 0 8px 0; color: #333; font-size: 24px; }
.paymenttypes-header .header-description { margin: 0; color: #6c757d; font-size: 14px; }
.btn-add-paymenttype { background: #667eea; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
.btn-add-paymenttype:hover { background: #5568d3; }
.paymenttypes-list { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
.paymenttype-item { display: flex; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e9ecef; cursor: move; }
.paymenttype-item:hover { background: #f8f9fa; }
.paymenttype-item:last-child { border-bottom: none; }
.paymenttype-handle { cursor: move; color: #6c757d; margin-right: 15px; font-size: 18px; }
.paymenttype-info { flex: 1; display: flex; align-items: center; gap: 20px; }
.paymenttype-code { font-weight: 600; color: #495057; min-width: 180px; }
.paymenttype-name { flex: 1; color: #212529; }
.paymenttype-badge { background: #17a2b8; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px; }
.paymenttype-actions { display: flex; gap: 10px; }
.btn-edit-paymenttype, .btn-delete-paymenttype { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
.btn-edit-paymenttype { background: #17a2b8; color: white; }
.btn-edit-paymenttype:hover { background: #138496; }
.btn-delete-paymenttype { background: #dc3545; color: white; }
.btn-delete-paymenttype:hover { background: #c82333; }
.pt-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
.pt-modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.pt-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6; }
.pt-modal-header h3 { margin: 0; color: #333; }
.pt-form-group { margin-bottom: 20px; }
.pt-form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
.pt-form-group input, .pt-form-group select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; }
.pt-form-checkbox { display: flex; align-items: center; gap: 10px; }
.pt-form-checkbox input { width: auto; }
.pt-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6; }
.pt-btn-save, .pt-btn-cancel { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; }
.pt-btn-save { background: #667eea; color: white; }
.pt-btn-cancel { background: #6c757d; color: white; }
#pt-alert-container { margin-bottom: 20px; }
.pt-alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid; }
.pt-alert-success { background: #d4edda; border-color: #28a745; color: #155724; }
.pt-alert-error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
.sorting-hint { background: #e7f3ff; border-left: 4px solid #17a2b8; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px; color: #0c5460; font-size: 14px; display: flex; align-items: center; gap: 10px; }
.paymenttype-item.dragging { opacity: 0.5; background: #e9ecef; }
</style>

<div class="paymenttypes-management-container">
    <div class="paymenttypes-header">
        <div>
            <h2><i class="fas fa-credit-card"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPES_MANAGEMENT_TITLE'); ?></h2>
            <p class="header-description"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPES_MANAGEMENT_DESC'); ?></p>
        </div>
        <button class="btn-add-paymenttype" onclick="openPTModal()">
            <i class="fas fa-plus"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ADD_NEW'); ?>
        </button>
    </div>

    <div id="pt-alert-container"></div>

    <?php if (!empty($paymentTypes)): ?>
        <div class="sorting-hint"><i class="fas fa-info-circle"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPES_SORT_HINT'); ?></div>
    <?php endif; ?>

    <div class="paymenttypes-list" id="paymenttypes-list">
        <?php if (empty($paymentTypes)): ?>
            <div style="text-align:center;padding:60px 20px;color:#6c757d;">
                <i class="fas fa-credit-card" style="font-size:64px;color:#dee2e6;margin-bottom:20px;display:block;"></i>
                <p><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPES_EMPTY_STATE'); ?></p>
                <p style="font-size:14px;margin-top:10px;"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPES_RUN_MIGRATION'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($paymentTypes as $pt): ?>
                <?php
                $displayName = ($isSpanish && !empty(trim($pt->name_es ?? ''))) ? trim($pt->name_es) : (trim($pt->name_en ?? $pt->name ?? '') ?: trim($pt->name ?? $pt->code));
                $requiresBank = !empty($pt->requires_bank);
                ?>
                <div class="paymenttype-item" data-id="<?php echo (int) $pt->id; ?>" data-code="<?php echo htmlspecialchars($pt->code); ?>">
                    <div class="paymenttype-handle"><i class="fas fa-grip-vertical"></i></div>
                    <div class="paymenttype-info">
                        <div class="paymenttype-code"><?php echo htmlspecialchars($pt->code); ?></div>
                        <div class="paymenttype-name"><?php echo htmlspecialchars($displayName); ?>
                            <?php if (!$requiresBank): ?><span class="paymenttype-badge"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_NO_BANK'); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="paymenttype-actions">
                        <button class="btn-edit-paymenttype" onclick="editPT(<?php echo (int) $pt->id; ?>, '<?php echo htmlspecialchars($pt->code, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pt->name ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pt->name_en ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pt->name_es ?? '', ENT_QUOTES); ?>', <?php echo $requiresBank ? '1' : '0'; ?>)">
                            <i class="fas fa-edit"></i> <?php echo Text::_('JEDIT'); ?>
                        </button>
                        <button class="btn-delete-paymenttype" onclick="deletePT(<?php echo (int) $pt->id; ?>)">
                            <i class="fas fa-trash"></i> <?php echo Text::_('JDELETE'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="pt-modal" class="pt-modal">
    <div class="pt-modal-content">
        <div class="pt-modal-header">
            <h3 id="pt-modal-title"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ADD_NEW'); ?></h3>
            <button type="button" style="background:none;border:none;font-size:28px;color:#6c757d;cursor:pointer;" onclick="closePTModal()">&times;</button>
        </div>
        <form id="pt-form">
            <input type="hidden" id="pt-id" name="id" value="0">
            <?php echo $token; ?>
            <div class="pt-form-group">
                <label for="pt-code"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_CODE'); ?> *</label>
                <input type="text" id="pt-code" name="code" required pattern="[a-z0-9_]+" placeholder="efectivo">
                <small style="color:#6c757d;"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_CODE_DESC'); ?></small>
            </div>
            <div class="pt-form-group">
                <label for="pt-name"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_NAME'); ?> *</label>
                <input type="text" id="pt-name" name="name" required placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_NAME_PLACEHOLDER'); ?>">
            </div>
            <div class="pt-form-group">
                <label for="pt-name-en"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_NAME_EN'); ?></label>
                <input type="text" id="pt-name-en" name="name_en" placeholder="">
            </div>
            <div class="pt-form-group">
                <label for="pt-name-es"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_NAME_ES'); ?></label>
                <input type="text" id="pt-name-es" name="name_es" placeholder="">
            </div>
            <div class="pt-form-group pt-form-checkbox">
                <input type="checkbox" id="pt-requires-bank" name="requires_bank" value="1" checked>
                <label for="pt-requires-bank"><?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_REQUIRES_BANK'); ?></label>
            </div>
            <div class="pt-modal-actions">
                <button type="button" class="pt-btn-cancel" onclick="closePTModal()"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="submit" class="pt-btn-save"><i class="fas fa-save"></i> <?php echo Text::_('JSAVE'); ?></button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
(function() {
    const baseUrl = '<?php echo Route::_('index.php?option=com_ordenproduccion&controller=paymenttype', false); ?>';
    const tokenName = '<?php echo $tokenName; ?>';

    let sortable = null;
    const list = document.getElementById('paymenttypes-list');
    if (list && list.querySelectorAll('.paymenttype-item').length > 0) {
        sortable = Sortable.create(list, {
            handle: '.paymenttype-handle',
            animation: 150,
            ghostClass: 'dragging',
            filter: '.empty-state',
            onEnd: function() {
                const order = Array.from(list.querySelectorAll('.paymenttype-item')).map(el => parseInt(el.dataset.id)).filter(id => !isNaN(id));
                if (order.length) reorderPT(order);
            }
        });
    }

    window.openPTModal = function() {
        document.getElementById('pt-modal-title').textContent = '<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ADD_NEW')); ?>';
        document.getElementById('pt-form').reset();
        document.getElementById('pt-id').value = '0';
        document.getElementById('pt-code').readOnly = false;
        document.getElementById('pt-modal').style.display = 'block';
    };

    window.closePTModal = function() { document.getElementById('pt-modal').style.display = 'none'; };

    window.editPT = function(id, code, name, nameEn, nameEs, requiresBank) {
        document.getElementById('pt-modal-title').textContent = '<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_EDIT')); ?>';
        document.getElementById('pt-id').value = id;
        document.getElementById('pt-code').value = code;
        document.getElementById('pt-code').readOnly = true;
        document.getElementById('pt-name').value = name || '';
        document.getElementById('pt-name-en').value = nameEn || '';
        document.getElementById('pt-name-es').value = nameEs || '';
        document.getElementById('pt-requires-bank').checked = !!requiresBank;
        document.getElementById('pt-modal').style.display = 'block';
    };

    document.getElementById('pt-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const fd = new FormData();
        fd.append('id', formData.get('id') || '0');
        fd.append('code', formData.get('code') || '');
        fd.append('name', formData.get('name') || '');
        fd.append('name_en', formData.get('name_en') || '');
        fd.append('name_es', formData.get('name_es') || '');
        fd.append('requires_bank', document.getElementById('pt-requires-bank').checked ? '1' : '0');
        fd.append(tokenName, '1');

        fetch(baseUrl + '&controller=paymenttype&task=save&format=json', { method: 'POST', body: fd })
            .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch(e) { throw new Error(t); } })
            .then(d => {
                if (d && d.success) { showPTAlert('success', d.message); closePTModal(); setTimeout(() => location.reload(), 1000); }
                else { showPTAlert('error', (d && d.message) || 'Error al guardar'); }
            })
            .catch(err => { showPTAlert('error', err.message || 'Error'); });
    });

    window.deletePT = function(id) {
        if (!confirm('<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_DELETE_CONFIRM')); ?>')) return;
        const fd = new FormData();
        fd.append('format', 'json');
        fd.append('id', id);
        fd.append(tokenName, '1');
        fetch(baseUrl + '&controller=paymenttype&task=delete&format=json', { method: 'POST', body: fd })
            .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch(e) { return { success: false, message: t }; } })
            .then(d => {
                if (d && d.success) { showPTAlert('success', d.message); setTimeout(() => location.reload(), 1000); }
                else { showPTAlert('error', (d && d.message) || 'Error al eliminar'); }
            })
            .catch(err => showPTAlert('error', err.message));
    };

    function reorderPT(order) {
        const fd = new FormData();
        fd.append('format', 'json');
        order.forEach(id => fd.append('order[]', id));
        fd.append(tokenName, '1');
        fetch(baseUrl + '&controller=paymenttype&task=reorder&format=json', { method: 'POST', body: fd })
            .then(r => r.text()).then(t => { try { return JSON.parse(t); } catch(e) { return {}; } })
            .then(d => { if (d && d.success) showPTAlert('success', d.message); else if (d && d.message) showPTAlert('error', d.message); });
    }

    function showPTAlert(type, msg) {
        const c = document.getElementById('pt-alert-container');
        const d = document.createElement('div');
        d.className = 'pt-alert pt-alert-' + type;
        d.textContent = msg;
        c.innerHTML = '';
        c.appendChild(d);
        setTimeout(() => d.remove(), 5000);
    }

    window.onclick = function(ev) { if (ev.target.id === 'pt-modal') closePTModal(); };
})();
</script>
