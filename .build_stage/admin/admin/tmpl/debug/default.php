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
use Joomla\CMS\Session\Session;

?>

<div class="com-ordenproduccion-debug">
    <!-- Debug Header -->
    <div class="debug-header mb-4">
        <div class="row">
            <div class="col-md-8">
                <h1 class="debug-title">
                    <i class="icon-bug"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_CONSOLE'); ?>
                </h1>
                <p class="debug-subtitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_SUBTITLE'); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="debug-status">
                    <span class="badge bg-<?php echo $this->config['enabled'] ? 'success' : 'danger'; ?>">
                        <i class="icon-<?php echo $this->config['enabled'] ? 'checkmark' : 'cancel'; ?>"></i>
                        <?php echo $this->config['enabled'] ? Text::_('COM_ORDENPRODUCCION_DEBUG_ENABLED') : Text::_('COM_ORDENPRODUCCION_DEBUG_DISABLED'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-primary">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-file-text"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo number_format($this->stats['line_count']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_LOG_ENTRIES'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-info">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-hdd"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->stats['file_size_formatted']; ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_LOG_FILE_SIZE'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-warning">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->config['log_level']; ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_LOG_LEVEL'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->config['retention_days']; ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_RETENTION_DAYS'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Debug Configuration -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-cog"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_CONFIGURATION'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form id="debugConfigForm" action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=debug.updateConfig'); ?>" method="post">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        
                        <div class="mb-3">
                            <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_MODE'); ?></label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="debug_mode" name="debug_mode" value="1" 
                                       <?php echo $this->config['enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="debug_mode">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_ENABLE_DEBUG_MODE'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="debug_log_level" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_LOG_LEVEL'); ?></label>
                            <select class="form-select" id="debug_log_level" name="debug_log_level">
                                <option value="ERROR" <?php echo $this->config['log_level'] === 'ERROR' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_LOG_LEVEL_ERROR'); ?>
                                </option>
                                <option value="WARNING" <?php echo $this->config['log_level'] === 'WARNING' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_LOG_LEVEL_WARNING'); ?>
                                </option>
                                <option value="INFO" <?php echo $this->config['log_level'] === 'INFO' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_LOG_LEVEL_INFO'); ?>
                                </option>
                                <option value="DEBUG" <?php echo $this->config['log_level'] === 'DEBUG' ? 'selected' : ''; ?>>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_LOG_LEVEL_DEBUG'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="debug_log_retention_days" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_RETENTION_DAYS'); ?></label>
                            <input type="number" class="form-control" id="debug_log_retention_days" name="debug_log_retention_days" 
                                   value="<?php echo $this->config['retention_days']; ?>" min="1" max="365">
                            <div class="form-text"><?php echo Text::_('COM_ORDENPRODUCCION_RETENTION_DAYS_DESC'); ?></div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_SAVE_CONFIGURATION'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Debug Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-lightning"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_ACTIONS'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" id="testLogging">
                            <i class="icon-checkmark"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_TEST_LOGGING'); ?>
                        </button>
                        
                        <button type="button" class="btn btn-outline-info" id="refreshLogs">
                            <i class="icon-refresh"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_REFRESH_LOGS'); ?>
                        </button>
                        
                        <button type="button" class="btn btn-outline-warning" id="cleanupLogs">
                            <i class="icon-trash"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_CLEANUP_LOGS'); ?>
                        </button>
                        
                        <button type="button" class="btn btn-outline-danger" id="clearLogs">
                            <i class="icon-cancel"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_CLEAR_LOGS'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Logs -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-list"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_DEBUG_LOGS'); ?>
                    </h5>
                    <div class="card-tools">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="autoRefresh">
                                <i class="icon-refresh"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_AUTO_REFRESH'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportLogs">
                                <i class="icon-download"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_EXPORT_LOGS'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="debug-logs-container">
                        <?php if (!empty($this->logs)): ?>
                            <div class="debug-logs" id="debugLogs">
                                <?php foreach ($this->logs as $line): ?>
                                    <?php $parsed = $this->parseLogLine($line); ?>
                                    <div class="log-entry log-level-<?php echo strtolower($parsed['level']); ?>">
                                        <div class="log-header">
                                            <span class="log-timestamp"><?php echo htmlspecialchars($parsed['timestamp']); ?></span>
                                            <span class="badge bg-<?php echo $this->getLogLevelColor($parsed['level']); ?>">
                                                <?php echo $this->getLogLevelText($parsed['level']); ?>
                                            </span>
                                            <span class="log-user"><?php echo htmlspecialchars($parsed['user_name']); ?></span>
                                        </div>
                                        <div class="log-message">
                                            <?php echo htmlspecialchars($parsed['message']); ?>
                                        </div>
                                        <?php if (!empty($parsed['context'])): ?>
                                            <div class="log-context">
                                                <pre><?php echo htmlspecialchars($parsed['context']); ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="icon-file-text icon-3x text-muted"></i>
                                <p class="text-muted mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_NO_DEBUG_LOGS'); ?></p>
                                <button type="button" class="btn btn-primary" id="testLoggingEmpty">
                                    <i class="icon-checkmark"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_TEST_LOGGING'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Statistics -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-chart"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_LOG_STATISTICS'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_LOG_FILE'); ?>:</strong>
                                <span class="text-muted"><?php echo basename($this->config['log_file']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_FILE_SIZE'); ?>:</strong>
                                <span class="text-muted"><?php echo $this->stats['file_size_formatted']; ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_LAST_MODIFIED'); ?>:</strong>
                                <span class="text-muted"><?php echo $this->stats['last_modified_formatted'] ?? '-'; ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_COMPONENT_VERSION'); ?>:</strong>
                                <span class="text-muted"><?php echo $this->config['version']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for AJAX requests -->
<form id="debugForm" style="display: none;">
    <input type="hidden" name="option" value="com_ordenproduccion">
    <input type="hidden" name="task" value="">
    <input type="hidden" name="format" value="json">
    <input type="hidden" name="<?php echo Factory::getSession()->getFormToken(); ?>" value="1">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug configuration form
    const configForm = document.getElementById('debugConfigForm');
    if (configForm) {
        configForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> <?php echo Text::_('COM_ORDENPRODUCCION_SAVING'); ?>...';
            submitBtn.disabled = true;
            
            // Submit form
            this.submit();
        });
    }
    
    // Test logging buttons
    const testButtons = document.querySelectorAll('#testLogging, #testLoggingEmpty');
    testButtons.forEach(button => {
        button.addEventListener('click', function() {
            const form = document.getElementById('debugForm');
            form.task.value = 'debug.testLogging';
            form.submit();
        });
    });
    
    // Clear logs button
    const clearButton = document.getElementById('clearLogs');
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            if (confirm('<?php echo Text::_('COM_ORDENPRODUCCION_CONFIRM_CLEAR_LOGS'); ?>')) {
                const form = document.getElementById('debugForm');
                form.task.value = 'debug.clearLogs';
                form.submit();
            }
        });
    }
    
    // Cleanup logs button
    const cleanupButton = document.getElementById('cleanupLogs');
    if (cleanupButton) {
        cleanupButton.addEventListener('click', function() {
            const form = document.getElementById('debugForm');
            form.task.value = 'debug.cleanupLogs';
            form.submit();
        });
    }
    
    // Export logs button
    const exportButton = document.getElementById('exportLogs');
    if (exportButton) {
        exportButton.addEventListener('click', function() {
            const form = document.getElementById('debugForm');
            form.task.value = 'debug.exportLogs';
            form.submit();
        });
    }
    
    // Auto refresh functionality
    let autoRefreshInterval;
    const autoRefreshButton = document.getElementById('autoRefresh');
    if (autoRefreshButton) {
        autoRefreshButton.addEventListener('click', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                this.classList.remove('active');
                this.innerHTML = '<i class="icon-refresh"></i> <?php echo Text::_('COM_ORDENPRODUCCION_AUTO_REFRESH'); ?>';
            } else {
                autoRefreshInterval = setInterval(function() {
                    // Refresh logs via AJAX
                    if (typeof OrdenproduccionDebug !== 'undefined') {
                        OrdenproduccionDebug.refreshLogs();
                    }
                }, 5000);
                this.classList.add('active');
                this.innerHTML = '<i class="icon-pause"></i> <?php echo Text::_('COM_ORDENPRODUCCION_STOP_AUTO_REFRESH'); ?>';
            }
        });
    }
});
</script>
