<?php
/**
 * @package     Joomla.Administrator
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

?>

<div class="com-ordenproduccion-settings">
    <!-- Settings Header -->
    <div class="settings-header mb-4">
        <div class="row">
            <div class="col-md-8">
                <h1 class="settings-title">
                    <i class="icon-cog"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_SETTINGS'); ?>
                </h1>
                <p class="settings-subtitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_SETTINGS_DESC'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=settings'); ?>" 
          method="post" name="adminForm" id="adminForm" class="form-validate">
        
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>

        <div class="row">
            <!-- Main Settings -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="icon-list-numbered"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER_SETTINGS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jform_next_order_number" class="form-label">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER'); ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" 
                                           name="jform[next_order_number]" 
                                           id="jform_next_order_number" 
                                           value="<?php echo htmlspecialchars($this->item->next_order_number ?? '1000'); ?>" 
                                           class="form-control required" 
                                           min="1" 
                                           required />
                                    <small class="form-text text-muted">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER_DESC'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jform_order_number_prefix" class="form-label">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER_PREFIX'); ?>
                                    </label>
                                    <input type="text" 
                                           name="jform[order_number_prefix]" 
                                           id="jform_order_number_prefix" 
                                           value="<?php echo htmlspecialchars($this->item->order_number_prefix ?? 'ORD'); ?>" 
                                           class="form-control" 
                                           maxlength="10" />
                                    <small class="form-text text-muted">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER_PREFIX_DESC'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="jform_order_number_format" class="form-label">
                                <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER_FORMAT'); ?>
                            </label>
                            <input type="text" 
                                   name="jform[order_number_format]" 
                                   id="jform_order_number_format" 
                                   value="<?php echo htmlspecialchars($this->item->order_number_format ?? '{PREFIX}-{NUMBER}'); ?>" 
                                   class="form-control" />
                            <small class="form-text text-muted">
                                <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER_FORMAT_DESC'); ?>
                                <br>
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_AVAILABLE_PLACEHOLDERS'); ?>:</strong>
                                <code>{PREFIX}</code>, <code>{NUMBER}</code>
                            </small>
                        </div>

                        <!-- Preview -->
                        <div class="form-group">
                            <label class="form-label">
                                <?php echo Text::_('COM_ORDENPRODUCCION_PREVIEW'); ?>
                            </label>
                            <div class="alert alert-info" id="order-number-preview">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER_WILL_BE'); ?>:</strong>
                                <span id="preview-text">ORD-1000</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Webhook Settings -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="icon-link"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_SETTINGS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jform_auto_assign_technicians" class="form-label">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_AUTO_ASSIGN_TECHNICIANS'); ?>
                                    </label>
                                    <select name="jform[auto_assign_technicians]" 
                                            id="jform_auto_assign_technicians" 
                                            class="form-control">
                                        <option value="0" <?php echo ($this->item->auto_assign_technicians ?? '0') == '0' ? 'selected' : ''; ?>>
                                            <?php echo Text::_('JNO'); ?>
                                        </option>
                                        <option value="1" <?php echo ($this->item->auto_assign_technicians ?? '0') == '1' ? 'selected' : ''; ?>>
                                            <?php echo Text::_('JYES'); ?>
                                        </option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_AUTO_ASSIGN_TECHNICIANS_DESC'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="jform_default_order_status" class="form-label">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_DEFAULT_ORDER_STATUS'); ?>
                                    </label>
                                    <select name="jform[default_order_status]" 
                                            id="jform_default_order_status" 
                                            class="form-control">
                                        <option value="nueva" <?php echo ($this->item->default_order_status ?? 'nueva') == 'nueva' ? 'selected' : ''; ?>>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_STATUS_NUEVA'); ?>
                                        </option>
                                        <option value="en_proceso" <?php echo ($this->item->default_order_status ?? 'nueva') == 'en_proceso' ? 'selected' : ''; ?>>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_STATUS_EN_PROCESO'); ?>
                                        </option>
                                        <option value="terminada" <?php echo ($this->item->default_order_status ?? 'nueva') == 'terminada' ? 'selected' : ''; ?>>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_STATUS_TERMINADA'); ?>
                                        </option>
                                        <option value="cerrada" <?php echo ($this->item->default_order_status ?? 'nueva') == 'cerrada' ? 'selected' : ''; ?>>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_STATUS_CERRADA'); ?>
                                        </option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?php echo Text::_('COM_ORDENPRODUCCION_DEFAULT_ORDER_STATUS_DESC'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="icon-info"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_INFORMATION'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBERING_INFO'); ?></h6>
                            <p><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBERING_INFO_DESC'); ?></p>
                        </div>

                        <div class="alert alert-warning">
                            <h6><?php echo Text::_('COM_ORDENPRODUCCION_IMPORTANT'); ?></h6>
                            <p><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER_IMPORTANT_DESC'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Current Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="icon-stats"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_CURRENT_STATUS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-12">
                                <h3 class="text-primary">
                                    <?php echo htmlspecialchars($this->item->next_order_number ?? '1000'); ?>
                                </h3>
                                <p class="text-muted">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update preview when form fields change
    function updatePreview() {
        const prefix = document.getElementById('jform_order_number_prefix').value || 'ORD';
        const number = document.getElementById('jform_next_order_number').value || '1000';
        const format = document.getElementById('jform_order_number_format').value || '{PREFIX}-{NUMBER}';
        
        const preview = format
            .replace('{PREFIX}', prefix)
            .replace('{NUMBER}', number.padStart(4, '0'));
        
        document.getElementById('preview-text').textContent = preview;
    }

    // Add event listeners
    document.getElementById('jform_order_number_prefix').addEventListener('input', updatePreview);
    document.getElementById('jform_next_order_number').addEventListener('input', updatePreview);
    document.getElementById('jform_order_number_format').addEventListener('input', updatePreview);

    // Initial preview update
    updatePreview();
});
</script>
