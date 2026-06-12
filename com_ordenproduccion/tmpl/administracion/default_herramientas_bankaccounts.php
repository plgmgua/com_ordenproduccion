<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$lang = Factory::getApplication()->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$rows = isset($this->bankAccounts) && is_array($this->bankAccounts) ? $this->bankAccounts : [];
$token = HTMLHelper::_('form.token');
$tokenName = Session::getFormToken();
?>

<style>
.ba-mgmt { max-width: 1200px; margin: 0 auto; }
.ba-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6; gap: 20px; }
.ba-header h2 { margin: 0 0 8px 0; color: #333; font-size: 24px; }
.ba-header .header-description { margin: 0; color: #6c757d; font-size: 14px; }
.btn-add-ba { background: #667eea; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
.btn-add-ba:hover { background: #5568d3; }
.ba-table-wrap { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
.ba-table { margin-bottom: 0; font-size: 0.9375rem; }
.ba-table th { font-weight: 600; background: #f8f9fa; }
.ba-badge-active { background: #28a745; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.ba-badge-inactive { background: #6c757d; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.ba-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-edit-ba { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; background: #17a2b8; color: white; display: inline-flex; align-items: center; gap: 5px; }
.btn-edit-ba:hover { background: #138496; }
.btn-del-ba { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; background: #dc3545; color: white; }
.btn-del-ba:hover { background: #c82333; }
.btn-set-default-ba { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; background: #ffc107; color: #212529; }
.btn-set-default-ba:hover { background: #e0a800; }
.ba-default-badge { background: #28a745; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.ba-form-checkbox { display: flex; align-items: center; gap: 10px; }
.ba-form-checkbox input[type="checkbox"] { width: auto; }
.ba-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
.ba-modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 520px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.ba-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #dee2e6; }
.ba-modal-header h3 { margin: 0; color: #333; }
.ba-form-group { margin-bottom: 20px; }
.ba-form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
.ba-form-group input, .ba-form-group select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; }
.ba-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #dee2e6; }
.ba-btn-save { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; background: #667eea; color: white; }
.ba-btn-cancel { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; background: #6c757d; color: white; }
#ba-alert-container { margin-bottom: 20px; }
.ba-alert { padding: 12px 20px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid; }
.ba-alert-success { background: #d4edda; border-color: #28a745; color: #155724; }
.ba-alert-error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
.ba-empty { text-align: center; padding: 60px 20px; color: #6c757d; }
.ba-empty i { font-size: 48px; margin-bottom: 15px; color: #dee2e6; display: block; }
</style>

<div class="ba-mgmt">
    <div class="ba-header">
        <div>
            <h2><i class="fas fa-piggy-bank"></i> <?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNTS_TITLE'); ?></h2>
            <p class="header-description"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNTS_DESC'); ?></p>
        </div>
        <button type="button" class="btn-add-ba" onclick="openBaModal()">
            <i class="fas fa-plus"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ADD'); ?>
        </button>
    </div>

    <div id="ba-alert-container"></div>

    <div class="ba-table-wrap table-responsive">
        <?php if ($rows === []) : ?>
            <div class="ba-empty">
                <i class="fas fa-piggy-bank"></i>
                <p><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNTS_EMPTY'); ?></p>
            </div>
        <?php else : ?>
            <table class="table table-striped table-bordered align-middle ba-table">
                <thead>
                    <tr>
                        <th scope="col" style="width:90px;"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_ID'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_NAME'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_NUMBER'); ?></th>
                        <th scope="col" style="width:180px;"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_DEFAULT'); ?></th>
                        <th scope="col" style="width:140px;"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_STATE'); ?></th>
                        <th scope="col" style="width:260px;"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r) : ?>
                        <?php
                        $rid = (int) ($r->id ?? 0);
                        $rname = (string) ($r->name ?? '');
                        $rnum = (string) ($r->account_number ?? '');
                        $active = !isset($r->state) || (int) $r->state === 1;
                        $isDef = !empty($r->is_default) && (int) $r->is_default === 1;
                        ?>
                        <tr>
                            <td><?php echo $rid; ?></td>
                            <td><?php echo htmlspecialchars($rname, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $rnum !== '' ? '<code>' . htmlspecialchars($rnum, ENT_QUOTES, 'UTF-8') . '</code>' : '—'; ?></td>
                            <td>
                                <?php if ($isDef) : ?>
                                    <span class="ba-default-badge"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_DEFAULT'); ?></span>
                                <?php else : ?>
                                    <button type="button" class="btn-set-default-ba" onclick="setDefaultBa(<?php echo $rid; ?>)">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_SET_DEFAULT'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($active) : ?>
                                    <span class="ba-badge-active"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_STATE_ACTIVE'); ?></span>
                                <?php else : ?>
                                    <span class="ba-badge-inactive"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_STATE_INACTIVE'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="ba-actions">
                                <button type="button" class="btn-edit-ba" onclick="editBa(<?php echo $rid; ?>, <?php echo json_encode($rname); ?>, <?php echo json_encode($rnum); ?>, <?php echo $active ? '1' : '0'; ?>, <?php echo $isDef ? '1' : '0'; ?>)">
                                    <i class="fas fa-edit"></i> <?php echo Text::_('JEDIT'); ?>
                                </button>
                                <button type="button" class="btn-del-ba" onclick="deleteBa(<?php echo $rid; ?>)">
                                    <i class="fas fa-trash"></i> <?php echo Text::_('JDELETE'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div id="ba-modal" class="ba-modal">
    <div class="ba-modal-content">
        <div class="ba-modal-header">
            <h3 id="ba-modal-title"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ADD'); ?></h3>
            <button type="button" style="background:none;border:none;font-size:28px;color:#6c757d;cursor:pointer;" onclick="closeBaModal()" aria-label="<?php echo Text::_('JCLOSE'); ?>">&times;</button>
        </div>
        <form id="ba-form">
            <input type="hidden" id="ba-id" name="id" value="0">
            <?php echo $token; ?>
            <div class="ba-form-group">
                <label for="ba-name"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_NAME'); ?> *</label>
                <input type="text" id="ba-name" name="name" required maxlength="255" autocomplete="off">
            </div>
            <div class="ba-form-group">
                <label for="ba-account-number"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_NUMBER'); ?></label>
                <input type="text" id="ba-account-number" name="account_number" maxlength="32" autocomplete="off" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_NUMBER_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-text small"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_NUMBER_DESC'); ?></div>
            </div>
            <div class="ba-form-group">
                <label for="ba-state"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_COL_STATE'); ?></label>
                <select id="ba-state" name="state">
                    <option value="1"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_STATE_ACTIVE'); ?></option>
                    <option value="0"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_STATE_INACTIVE'); ?></option>
                </select>
            </div>
            <div class="ba-form-group ba-form-checkbox">
                <input type="checkbox" id="ba-is-default" name="is_default" value="1">
                <label for="ba-is-default"><?php echo Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_SET_AS_DEFAULT'); ?></label>
            </div>
            <div class="ba-modal-actions">
                <button type="button" class="ba-btn-cancel" onclick="closeBaModal()"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="submit" class="ba-btn-save"><i class="fas fa-save"></i> <?php echo Text::_('JSAVE'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const baseUrl = '<?php echo Route::_('index.php?option=com_ordenproduccion&controller=bankaccount', false); ?>';
    const tokenName = '<?php echo $tokenName; ?>';

    window.openBaModal = function() {
        document.getElementById('ba-modal-title').textContent = '<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ADD')); ?>';
        document.getElementById('ba-form').reset();
        document.getElementById('ba-id').value = '0';
        document.getElementById('ba-state').value = '1';
        document.getElementById('ba-is-default').checked = false;
        document.getElementById('ba-account-number').value = '';
        document.getElementById('ba-modal').style.display = 'block';
    };

    window.closeBaModal = function() { document.getElementById('ba-modal').style.display = 'none'; };

    window.editBa = function(id, name, accountNumber, state1, isDef) {
        document.getElementById('ba-modal-title').textContent = '<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_EDIT')); ?>';
        document.getElementById('ba-id').value = id;
        document.getElementById('ba-name').value = name || '';
        document.getElementById('ba-account-number').value = accountNumber || '';
        document.getElementById('ba-state').value = (state1 === true || state1 === 1 || state1 === '1') ? '1' : '0';
        document.getElementById('ba-is-default').checked = (isDef === true || isDef === 1 || isDef === '1');
        document.getElementById('ba-modal').style.display = 'block';
    };

    document.getElementById('ba-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('id', document.getElementById('ba-id').value || '0');
        fd.append('name', (document.getElementById('ba-name').value || '').trim());
        fd.append('account_number', (document.getElementById('ba-account-number').value || '').trim());
        fd.append('state', document.getElementById('ba-state').value);
        fd.append('is_default', document.getElementById('ba-is-default').checked ? '1' : '0');
        fd.append(tokenName, '1');

        fetch(baseUrl + '&controller=bankaccount&task=save&format=json', { method: 'POST', body: fd })
            .then(function(r) { return r.text(); })
            .then(function(t) { try { return JSON.parse(t); } catch (e) { throw new Error(t); } })
            .then(function(d) {
                if (d && d.success) { showBaAlert('success', d.message); closeBaModal(); setTimeout(function() { location.reload(); }, 800); }
                else { showBaAlert('error', (d && d.message) || 'Error'); }
            })
            .catch(function(err) { showBaAlert('error', err.message || 'Error'); });
    });

    window.setDefaultBa = function(id) {
        const fd = new FormData();
        fd.append('format', 'json');
        fd.append('id', id);
        fd.append(tokenName, '1');
        fetch(baseUrl + '&controller=bankaccount&task=setDefault&format=json', { method: 'POST', body: fd })
            .then(function(r) { return r.text(); })
            .then(function(t) { try { return JSON.parse(t); } catch (e) { return { success: false, message: t }; } })
            .then(function(d) {
                if (d && d.success) { showBaAlert('success', d.message); setTimeout(function() { location.reload(); }, 800); }
                else { showBaAlert('error', (d && d.message) || 'Error'); }
            })
            .catch(function(err) { showBaAlert('error', err.message || 'Error'); });
    };

    window.deleteBa = function(id) {
        if (!confirm('<?php echo addslashes(Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_DELETE_CONFIRM')); ?>')) return;
        const fd = new FormData();
        fd.append('format', 'json');
        fd.append('id', id);
        fd.append(tokenName, '1');
        fetch(baseUrl + '&controller=bankaccount&task=delete&format=json', { method: 'POST', body: fd })
            .then(function(r) { return r.text(); })
            .then(function(t) { try { return JSON.parse(t); } catch (e) { return { success: false, message: t }; } })
            .then(function(d) {
                if (d && d.success) { showBaAlert('success', d.message); setTimeout(function() { location.reload(); }, 800); }
                else { showBaAlert('error', (d && d.message) || 'Error'); }
            })
            .catch(function(err) { showBaAlert('error', err.message || 'Error'); });
    };

    function showBaAlert(type, msg) {
        var c = document.getElementById('ba-alert-container');
        c.innerHTML = '<div class="ba-alert ba-alert-' + type + '">' + String(msg) + '</div>';
        setTimeout(function() { c.innerHTML = ''; }, 6000);
    }

    window.addEventListener('click', function(ev) {
        if (ev.target && ev.target.id === 'ba-modal') closeBaModal();
    });
})();
</script>
