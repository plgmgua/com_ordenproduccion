/**
 * Asistencia (Time & Attendance) JavaScript
 * @package     com_ordenproduccion
 * @version     3.2.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize attendance interface
        initializeAsistencia();
    });

    /**
     * Initialize the asistencia interface
     */
    function initializeAsistencia() {
        // Auto-submit form on filter change
        const filterInputs = document.querySelectorAll('#adminForm select, #adminForm input[type="date"]');
        
        filterInputs.forEach(function(input) {
            // Debounce text input
            if (input.type === 'text') {
                let timeout;
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        submitFilterForm();
                    }, 500);
                });
            } else {
                input.addEventListener('change', function() {
                    submitFilterForm();
                });
            }
        });

        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Add click handlers for statistics cards
        addStatisticsCardHandlers();
    }

    /**
     * Submit the filter form
     */
    function submitFilterForm() {
        const form = document.getElementById('adminForm');
        if (form) {
            form.submit();
        }
    }

    /**
     * Add handlers for statistics cards
     */
    function addStatisticsCardHandlers() {
        const cards = document.querySelectorAll('.com-ordenproduccion-asistencia .card');
        
        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                // Add click animation
                card.style.transform = 'scale(0.98)';
                setTimeout(function() {
                    card.style.transform = 'translateY(-2px)';
                }, 100);
            });
        });
    }

    /**
     * Format hours for display
     * @param {number} hours - Hours in decimal format
     * @returns {string} Formatted hours (e.g., "8h 30m")
     */
    function formatHours(hours) {
        const h = Math.floor(hours);
        const m = Math.round((hours - h) * 60);
        return h + 'h ' + m + 'm';
    }

    /**
     * Calculate time difference between two times
     * @param {string} startTime - Start time (HH:MM:SS)
     * @param {string} endTime - End time (HH:MM:SS)
     * @returns {number} Hours difference
     */
    function calculateTimeDifference(startTime, endTime) {
        const start = new Date('1970-01-01 ' + startTime);
        const end = new Date('1970-01-01 ' + endTime);
        const diff = (end - start) / 1000 / 60 / 60; // Convert to hours
        return diff;
    }

    /**
     * Export data to CSV
     */
    window.exportData = function() {
        const form = document.getElementById('adminForm');
        if (!form) return;

        const originalAction = form.action;
        const originalTask = form.querySelector('input[name="task"]').value;
        
        // Change form action to export task
        const url = new URL(form.action, window.location.origin);
        url.searchParams.set('task', 'asistencia.export');
        form.action = url.toString();
        
        // Submit form
        form.submit();
        
        // Restore original action
        setTimeout(function() {
            form.action = originalAction;
            form.querySelector('input[name="task"]').value = originalTask;
        }, 100);
    };

    /**
     * Recalculate summaries
     */
    window.recalculateSummaries = function() {
        const dateFrom = document.getElementById('filter_date_from').value;
        const dateTo = document.getElementById('filter_date_to').value;
        
        if (!dateFrom || !dateTo) {
            alert('Date range is required for recalculation');
            return;
        }
        
        if (confirm('Are you sure you want to recalculate all summaries for the selected date range?')) {
            const url = new URL(window.location.origin + '/index.php');
            url.searchParams.set('option', 'com_ordenproduccion');
            url.searchParams.set('task', 'asistencia.recalculate');
            url.searchParams.set('date_from', dateFrom);
            url.searchParams.set('date_to', dateTo);
            
            window.location.href = url.toString();
        }
    };

    /**
     * Clear filters
     */
    window.clearFilters = function() {
        const form = document.getElementById('adminForm');
        if (!form) return;
        
        // Clear all filter inputs
        form.querySelector('#filter_search').value = '';
        form.querySelector('#filter_date_from').value = '';
        form.querySelector('#filter_date_to').value = '';
        // Multi-select: deselect all options
        [].forEach.call(form.querySelectorAll('#filter_cardno option, #filter_group_id option'), function(opt) {
            opt.selected = false;
        });
        form.querySelector('#filter_is_complete').value = '';
        form.querySelector('#filter_is_late').value = '';
        
        // Submit form
        form.submit();
    };

    /**
     * Set date range to today
     */
    window.setTodayRange = function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('filter_date_from').value = today;
        document.getElementById('filter_date_to').value = today;
        submitFilterForm();
    };

    /**
     * Set date range to current week
     */
    window.setWeekRange = function() {
        const today = new Date();
        const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
        const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 6));
        
        document.getElementById('filter_date_from').value = firstDay.toISOString().split('T')[0];
        document.getElementById('filter_date_to').value = lastDay.toISOString().split('T')[0];
        submitFilterForm();
    };

    /**
     * Set date range to current month
     */
    window.setMonthRange = function() {
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        document.getElementById('filter_date_from').value = firstDay.toISOString().split('T')[0];
        document.getElementById('filter_date_to').value = lastDay.toISOString().split('T')[0];
        submitFilterForm();
    };

    /**
     * Print report
     */
    window.printReport = function() {
        window.print();
    };

    /**
     * Show loading state
     */
    function showLoading() {
        const container = document.querySelector('.com-ordenproduccion-asistencia');
        if (container) {
            const loading = document.createElement('div');
            loading.className = 'loading';
            loading.textContent = 'Loading';
            container.appendChild(loading);
        }
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        const loading = document.querySelector('.com-ordenproduccion-asistencia .loading');
        if (loading) {
            loading.remove();
        }
    }

    // Expose utility functions globally
    window.AsistenciaUtils = {
        formatHours: formatHours,
        calculateTimeDifference: calculateTimeDifference,
        showLoading: showLoading,
        hideLoading: hideLoading
    };

})();

