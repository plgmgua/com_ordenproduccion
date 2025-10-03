/**
 * Dashboard JavaScript for com_ordenproduccion
 * 
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

(function($) {
    'use strict';

    /**
     * Dashboard functionality
     */
    window.OrdenproduccionDashboard = {
        
        /**
         * Configuration
         */
        config: {
            currentYear: new Date().getFullYear(),
            currentMonth: new Date().getMonth() + 1,
            ajaxUrl: '',
            monthNames: [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ]
        },

        /**
         * Initialize the dashboard
         * 
         * @param {Object} options Configuration options
         */
        init: function(options) {
            // Merge configuration
            $.extend(this.config, options);
            
            // Initialize components
            this.initCalendar();
            this.initQuickActions();
            this.initExportButtons();
            this.initRefreshButton();
            this.initTooltips();
            
            // Load initial calendar data
            this.loadCalendarData();
        },

        /**
         * Initialize calendar functionality
         */
        initCalendar: function() {
            var self = this;
            
            // Previous month button
            $('#prevMonth').on('click', function() {
                self.config.currentMonth--;
                if (self.config.currentMonth < 1) {
                    self.config.currentMonth = 12;
                    self.config.currentYear--;
                }
                self.updateCalendarTitle();
                self.loadCalendarData();
            });
            
            // Next month button
            $('#nextMonth').on('click', function() {
                self.config.currentMonth++;
                if (self.config.currentMonth > 12) {
                    self.config.currentMonth = 1;
                    self.config.currentYear++;
                }
                self.updateCalendarTitle();
                self.loadCalendarData();
            });
        },

        /**
         * Initialize quick actions
         */
        initQuickActions: function() {
            // Add click handlers for quick action buttons
            $('.quick-action').on('click', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                if (action && typeof OrdenproduccionDashboard['quick' + action] === 'function') {
                    OrdenproduccionDashboard['quick' + action]();
                }
            });
        },

        /**
         * Initialize export buttons
         */
        initExportButtons: function() {
            var self = this;
            
            // Export buttons
            $('[data-format][data-type]').on('click', function(e) {
                e.preventDefault();
                var format = $(this).data('format');
                var type = $(this).data('type');
                self.exportData(format, type);
            });
        },

        /**
         * Initialize refresh button
         */
        initRefreshButton: function() {
            var self = this;
            
            $('.refresh-stats').on('click', function(e) {
                e.preventDefault();
                self.refreshStatistics();
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Initialize Bootstrap tooltips if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        },

        /**
         * Update calendar title
         */
        updateCalendarTitle: function() {
            var monthName = this.config.monthNames[this.config.currentMonth - 1];
            $('#calendarTitle').text(monthName + ' ' + this.config.currentYear);
        },

        /**
         * Load calendar data via AJAX
         */
        loadCalendarData: function() {
            var self = this;
            var $calendarGrid = $('#calendarGrid');
            
            // Show loading state
            $calendarGrid.addClass('loading');
            
            // Make AJAX request
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'GET',
                data: {
                    year: this.config.currentYear,
                    month: this.config.currentMonth
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.renderCalendar(response.data);
                    } else {
                        self.showError('Error loading calendar data: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Error loading calendar data: ' + error);
                },
                complete: function() {
                    $calendarGrid.removeClass('loading');
                }
            });
        },

        /**
         * Render calendar grid
         * 
         * @param {Object} data Calendar data
         */
        renderCalendar: function(data) {
            var $grid = $('#calendarGrid');
            var year = this.config.currentYear;
            var month = this.config.currentMonth;
            
            // Clear existing content
            $grid.empty();
            
            // Get first day of month and number of days
            var firstDay = new Date(year, month - 1, 1).getDay();
            var daysInMonth = new Date(year, month, 0).getDate();
            var today = new Date();
            var isCurrentMonth = year === today.getFullYear() && month === today.getMonth() + 1;
            
            // Add day headers
            var dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayHeaders.forEach(function(day) {
                $grid.append('<div class="calendar-day-header">' + day + '</div>');
            });
            
            // Add empty cells for days before the first day of the month
            for (var i = 0; i < firstDay; i++) {
                $grid.append('<div class="calendar-day other-month"></div>');
            }
            
            // Add days of the month
            for (var day = 1; day <= daysInMonth; day++) {
                var $day = $('<div class="calendar-day">' + day + '</div>');
                var dateKey = year + '-' + this.padZero(month) + '-' + this.padZero(day);
                
                // Check if this day has orders
                if (data[dateKey] && data[dateKey] > 0) {
                    $day.addClass('has-orders');
                    $day.attr('title', data[dateKey] + ' orders on ' + dateKey);
                }
                
                // Check if this is today
                if (isCurrentMonth && day === today.getDate()) {
                    $day.addClass('today');
                }
                
                $grid.append($day);
            }
            
            // Add fade-in animation
            $grid.addClass('fade-in');
            setTimeout(function() {
                $grid.removeClass('fade-in');
            }, 500);
        },

        /**
         * Pad number with leading zero
         * 
         * @param {number} num Number to pad
         * @returns {string} Padded number
         */
        padZero: function(num) {
            return num < 10 ? '0' + num : num;
        },

        /**
         * Export data
         * 
         * @param {string} format Export format (csv, json)
         * @param {string} type Data type to export
         */
        exportData: function(format, type) {
            var $form = $('#dashboardForm');
            $form.find('input[name="task"]').val('dashboard.exportData');
            $form.find('input[name="format"]').val(format);
            $form.append('<input type="hidden" name="type" value="' + type + '">');
            
            if (type === 'calendar') {
                $form.append('<input type="hidden" name="year" value="' + this.config.currentYear + '">');
                $form.append('<input type="hidden" name="month" value="' + this.config.currentMonth + '">');
            }
            
            $form.submit();
            
            // Clean up
            $form.find('input[name="type"]').remove();
            $form.find('input[name="year"]').remove();
            $form.find('input[name="month"]').remove();
        },

        /**
         * Refresh statistics
         */
        refreshStatistics: function() {
            var $button = $('.refresh-stats');
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true).text('Refreshing...');
            
            // Reload page after a short delay to show the loading state
            setTimeout(function() {
                window.location.reload();
            }, 500);
        },

        /**
         * Show error message
         * 
         * @param {string} message Error message
         */
        showError: function(message) {
            // Create error alert if it doesn't exist
            if ($('#dashboard-error').length === 0) {
                $('.com-ordenproduccion-dashboard').prepend(
                    '<div id="dashboard-error" class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '<span class="error-message"></span>' +
                    '</div>'
                );
            }
            
            $('#dashboard-error .error-message').text(message);
            $('#dashboard-error').show();
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('#dashboard-error').fadeOut();
            }, 5000);
        },

        /**
         * Show success message
         * 
         * @param {string} message Success message
         */
        showSuccess: function(message) {
            // Create success alert if it doesn't exist
            if ($('#dashboard-success').length === 0) {
                $('.com-ordenproduccion-dashboard').prepend(
                    '<div id="dashboard-success" class="alert alert-success alert-dismissible fade show" role="alert">' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '<span class="success-message"></span>' +
                    '</div>'
                );
            }
            
            $('#dashboard-success .success-message').text(message);
            $('#dashboard-success').show();
            
            // Auto-hide after 3 seconds
            setTimeout(function() {
                $('#dashboard-success').fadeOut();
            }, 3000);
        },

        /**
         * Quick action: New Order
         */
        quickNewOrder: function() {
            window.location.href = 'index.php?option=com_ordenproduccion&view=orden&layout=edit';
        },

        /**
         * Quick action: View Orders
         */
        quickViewOrders: function() {
            window.location.href = 'index.php?option=com_ordenproduccion&view=ordenes';
        },

        /**
         * Quick action: View Technicians
         */
        quickViewTechnicians: function() {
            window.location.href = 'index.php?option=com_ordenproduccion&view=technicians';
        },

        /**
         * Quick action: Webhook Config
         */
        quickWebhookConfig: function() {
            window.location.href = 'index.php?option=com_ordenproduccion&view=webhook';
        },

        /**
         * Quick action: Debug Console
         */
        quickDebugConsole: function() {
            window.location.href = 'index.php?option=com_ordenproduccion&view=debug';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Auto-initialize if config is available
        if (typeof window.OrdenproduccionDashboardConfig !== 'undefined') {
            OrdenproduccionDashboard.init(window.OrdenproduccionDashboardConfig);
        }
    });

})(jQuery);
