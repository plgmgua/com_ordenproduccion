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

// Load component language files
$app = Factory::getApplication();
$lang = $app->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

// Get active subtab
$activeSubTab = isset($this->activeSubTab) ? $this->activeSubTab : 'banks';
$app->input->set('subtab', $activeSubTab);

// Get banks data - ensure it's an array
$banks = isset($this->banks) && is_array($this->banks) ? $this->banks : [];
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
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #dee2e6;
    gap: 20px;
}

.header-title-section {
    flex: 1;
}

.banks-header h2 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 24px;
}

.header-description {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
    line-height: 1.5;
}

.btn-add-bank {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    flex-shrink: 0;
}

.btn-add-bank:hover {
    background: #5568d3;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

.btn-add-bank i {
    font-size: 16px;
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
    display: inline-flex;
    align-items: center;
    gap: 5px;
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

.sorting-hint {
    background: #e7f3ff;
    border-left: 4px solid #17a2b8;
    padding: 12px 16px;
    margin-bottom: 20px;
    border-radius: 4px;
    color: #0c5460;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sorting-hint i {
    color: #17a2b8;
    font-size: 18px;
}
.subtab-content {
    margin-top: 20px;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="herramientas-container">
    <!-- Subtab Navigation -->
    <div class="herramientas-subtabs">
        <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=herramientas&subtab=banks'); ?>" 
           class="herramientas-subtab <?php echo $activeSubTab === 'banks' ? 'subtab-active' : ''; ?>">
            <i class="fas fa-university"></i>
            <?php echo Text::_('COM_ORDENPRODUCCION_SUBTAB_BANKS'); ?>
        </a>
        <!-- Add more subtabs here in the future -->
    </div>

    <!-- Subtab Content -->
    <div class="subtab-content">
        <?php if ($activeSubTab === 'banks'): ?>
            <?php include __DIR__ . '/default_herramientas_banks.php'; ?>
        <?php else: ?>
            <div class="empty-state">
                <p><?php echo Text::_('COM_ORDENPRODUCCION_SUBTAB_NOT_FOUND'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.herramientas-container {
    max-width: 1400px;
    margin: 0 auto;
}

.herramientas-subtabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 30px;
}

.herramientas-subtab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.herramientas-subtab:hover {
    color: #667eea;
    text-decoration: none;
    background: rgba(102, 126, 234, 0.05);
}

.subtab-active {
    color: #667eea;
    border-bottom-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
}
</style>
