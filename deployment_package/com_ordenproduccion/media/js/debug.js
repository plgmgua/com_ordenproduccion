/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Debug Console JavaScript
 */
window.OrdenproduccionDebug = (function() {
    'use strict';

    var config = {
        ajaxUrl: '',
        statsUrl: '',
        autoRefreshInterval: null,
        refreshInterval: 5000
    };

    /**
     * Initialize the debug console
     */
    function init(options) {
        if (options) {
            config = Object.assign(config, options);
        }

        // Initialize event listeners
        initEventListeners();
        
        // Load initial data
        loadStats();
        
        console.log('OrdenproduccionDebug initialized');
    }

    /**
     * Initialize event listeners
     */
    function initEventListeners() {
        // Auto refresh toggle
        var autoRefreshBtn = document.getElementById('autoRefresh');
        if (autoRefreshBtn) {
            autoRefreshBtn.addEventListener('click', toggleAutoRefresh);
        }

        // Refresh logs button
        var refreshBtn = document.getElementById('refreshLogs');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshLogs);
        }

        // Test logging button
        var testBtn = document.getElementById('testLogging');
        if (testBtn) {
            testBtn.addEventListener('click', testLogging);
        }

        // Clear logs button
        var clearBtn = document.getElementById('clearLogs');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearLogs);
        }

        // Cleanup logs button
        var cleanupBtn = document.getElementById('cleanupLogs');
        if (cleanupBtn) {
            cleanupBtn.addEventListener('click', cleanupLogs);
        }

        // Export logs button
        var exportBtn = document.getElementById('exportLogs');
        if (exportBtn) {
            exportBtn.addEventListener('click', exportLogs);
        }

        // Configuration form
        var configForm = document.getElementById('debugConfigForm');
        if (configForm) {
            configForm.addEventListener('submit', handleConfigSubmit);
        }
    }

    /**
     * Toggle auto refresh
     */
    function toggleAutoRefresh() {
        var btn = document.getElementById('autoRefresh');
        
        if (config.autoRefreshInterval) {
            clearInterval(config.autoRefreshInterval);
            config.autoRefreshInterval = null;
            btn.classList.remove('active');
            btn.innerHTML = '<i class="icon-refresh"></i> Auto Refresh';
        } else {
            config.autoRefreshInterval = setInterval(refreshLogs, config.refreshInterval);
            btn.classList.add('active');
            btn.innerHTML = '<i class="icon-pause"></i> Stop Auto Refresh';
        }
    }

    /**
     * Refresh logs via AJAX
     */
    function refreshLogs() {
        if (!config.ajaxUrl) {
            console.warn('AJAX URL not configured');
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', config.ajaxUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            updateLogsDisplay(response.data);
                        } else {
                            showError('Failed to refresh logs: ' + response.message);
                        }
                    } catch (e) {
                        showError('Error parsing response: ' + e.message);
                    }
                } else {
                    showError('Failed to refresh logs. Status: ' + xhr.status);
                }
            }
        };
        
        xhr.send();
    }

    /**
     * Load statistics
     */
    function loadStats() {
        if (!config.statsUrl) {
            console.warn('Stats URL not configured');
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', config.statsUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            updateStatsDisplay(response.data);
                        }
                    } catch (e) {
                        console.error('Error parsing stats response:', e);
                    }
                }
            }
        };
        
        xhr.send();
    }

    /**
     * Update logs display
     */
    function updateLogsDisplay(logs) {
        var container = document.getElementById('debugLogs');
        if (!container) return;

        if (!logs || logs.length === 0) {
            container.innerHTML = '<div class="text-center py-4"><i class="icon-file-text icon-3x text-muted"></i><p class="text-muted mt-2">No debug logs available</p></div>';
            return;
        }

        var html = '';
        logs.forEach(function(line) {
            var parsed = parseLogLine(line);
            html += createLogEntryHTML(parsed);
        });

        container.innerHTML = html;
        
        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    /**
     * Update statistics display
     */
    function updateStatsDisplay(data) {
        // Update log entries count
        var entriesElement = document.querySelector('.stat-card-primary .stat-number');
        if (entriesElement && data.stats) {
            entriesElement.textContent = formatNumber(data.stats.line_count || 0);
        }

        // Update file size
        var sizeElement = document.querySelector('.stat-card-info .stat-number');
        if (sizeElement && data.stats) {
            sizeElement.textContent = data.stats.file_size_formatted || '0 B';
        }

        // Update log level
        var levelElement = document.querySelector('.stat-card-warning .stat-number');
        if (levelElement && data.config) {
            levelElement.textContent = data.config.log_level || 'DEBUG';
        }

        // Update retention days
        var retentionElement = document.querySelector('.stat-card-success .stat-number');
        if (retentionElement && data.config) {
            retentionElement.textContent = data.config.retention_days || '7';
        }
    }

    /**
     * Parse log line
     */
    function parseLogLine(line) {
        var parsed = {
            timestamp: '',
            level: '',
            version: '',
            user_id: '',
            user_name: '',
            message: '',
            context: '',
            raw: line
        };

        // Parse log line format: [timestamp] [level] [version] [User:id:name] message context
        var match = line.match(/^\[([^\]]+)\] \[([^\]]+)\] \[([^\]]+)\] \[User:([^:]+):([^\]]+)\] (.+?)(?:\s+(.+))?$/);
        if (match) {
            parsed.timestamp = match[1];
            parsed.level = match[2];
            parsed.version = match[3];
            parsed.user_id = match[4];
            parsed.user_name = match[5];
            parsed.message = match[6];
            parsed.context = match[7] || '';
        }

        return parsed;
    }

    /**
     * Create log entry HTML
     */
    function createLogEntryHTML(parsed) {
        var levelColor = getLogLevelColor(parsed.level);
        var levelText = getLogLevelText(parsed.level);
        
        var html = '<div class="log-entry log-level-' + parsed.level.toLowerCase() + '">';
        html += '<div class="log-header">';
        html += '<span class="log-timestamp">' + escapeHtml(parsed.timestamp) + '</span>';
        html += '<span class="badge bg-' + levelColor + '">' + levelText + '</span>';
        html += '<span class="log-user">' + escapeHtml(parsed.user_name) + '</span>';
        html += '</div>';
        html += '<div class="log-message">' + escapeHtml(parsed.message) + '</div>';
        
        if (parsed.context) {
            html += '<div class="log-context">';
            html += '<pre>' + escapeHtml(parsed.context) + '</pre>';
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }

    /**
     * Get log level color
     */
    function getLogLevelColor(level) {
        var colors = {
            'ERROR': 'danger',
            'WARNING': 'warning',
            'INFO': 'info',
            'DEBUG': 'secondary'
        };
        return colors[level] || 'secondary';
    }

    /**
     * Get log level text
     */
    function getLogLevelText(level) {
        var texts = {
            'ERROR': 'Error',
            'WARNING': 'Warning',
            'INFO': 'Info',
            'DEBUG': 'Debug'
        };
        return texts[level] || level;
    }

    /**
     * Handle configuration form submit
     */
    function handleConfigSubmit(e) {
        e.preventDefault();
        
        var form = e.target;
        var formData = new FormData(form);
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        // Submit form normally (page reload)
        form.submit();
    }

    /**
     * Test logging
     */
    function testLogging() {
        var form = document.getElementById('debugForm');
        if (form) {
            form.task.value = 'debug.testLogging';
            form.submit();
        }
    }

    /**
     * Clear logs
     */
    function clearLogs() {
        if (confirm('Are you sure you want to clear all debug logs?')) {
            var form = document.getElementById('debugForm');
            if (form) {
                form.task.value = 'debug.clearLogs';
                form.submit();
            }
        }
    }

    /**
     * Cleanup logs
     */
    function cleanupLogs() {
        var form = document.getElementById('debugForm');
        if (form) {
            form.task.value = 'debug.cleanupLogs';
            form.submit();
        }
    }

    /**
     * Export logs
     */
    function exportLogs() {
        var form = document.getElementById('debugForm');
        if (form) {
            form.task.value = 'debug.exportLogs';
            form.submit();
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        console.error('OrdenproduccionDebug Error:', message);
        
        // You could implement a toast notification here
        // For now, just log to console
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format number
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Public API
     */
    return {
        init: init,
        refreshLogs: refreshLogs,
        loadStats: loadStats,
        toggleAutoRefresh: toggleAutoRefresh
    };

})();
