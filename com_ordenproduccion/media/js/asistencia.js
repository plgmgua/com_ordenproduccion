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

        // Initialize checkbox dropdowns (Grupo, Empleado)
        initCheckboxDropdowns();
    }

    /**
     * Initialize checkbox dropdowns for multi-select filters
     */
    function initCheckboxDropdowns() {
        const dropdowns = document.querySelectorAll('.com-ordenproduccion-asistencia .checkbox-dropdown');
        dropdowns.forEach(function(dd) {
            const toggle = dd.querySelector('.checkbox-dropdown-toggle');
            const panel = dd.querySelector('.checkbox-dropdown-panel');
            const labelEl = dd.querySelector('.checkbox-dropdown-label');
            const checkboxes = panel.querySelectorAll('input[type="checkbox"]');
            const allLabel = dd.getAttribute('data-all-label') || 'All';
            const selectedLabel = dd.getAttribute('data-selected-label') || '%d selected';

            function updateLabel() {
                const n = [].slice.call(checkboxes).filter(function(cb) { return cb.checked; }).length;
                labelEl.textContent = n === 0 ? allLabel : selectedLabel.replace('%d', n);
            }

            function close() {
                dd.classList.remove('open');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }

            function open() {
                dd.classList.add('open');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
            }

            if (toggle && panel) {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    if (dd.classList.contains('open')) {
                        close();
                    } else {
                        document.querySelectorAll('.com-ordenproduccion-asistencia .checkbox-dropdown.open').forEach(function(o) {
                            o.classList.remove('open');
                            o.querySelector('.checkbox-dropdown-toggle').setAttribute('aria-expanded', 'false');
                        });
                        open();
                    }
                });
            }

            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    updateLabel();
                    submitFilterForm();
                });
            });

            updateLabel();
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.checkbox-dropdown')) {
                document.querySelectorAll('.com-ordenproduccion-asistencia .checkbox-dropdown.open').forEach(function(dd) {
                    dd.classList.remove('open');
                    const t = dd.querySelector('.checkbox-dropdown-toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            }
        });
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
        const searchEl = form.querySelector('#filter_search');
        if (searchEl) searchEl.value = '';
        const dateFromEl = form.querySelector('#filter_date_from');
        if (dateFromEl) dateFromEl.value = '';
        const dateToEl = form.querySelector('#filter_date_to');
        if (dateToEl) dateToEl.value = '';
        // Checkbox dropdowns: uncheck all
        [].forEach.call(form.querySelectorAll('.checkbox-dropdown input[type="checkbox"]'), function(cb) {
            cb.checked = false;
        });
        const isCompleteEl = form.querySelector('#filter_is_complete');
        if (isCompleteEl) isCompleteEl.value = '';
        const isLateEl = form.querySelector('#filter_is_late');
        if (isLateEl) isLateEl.value = '';
        
        // Update dropdown labels
        document.querySelectorAll('.com-ordenproduccion-asistencia .checkbox-dropdown').forEach(function(dd) {
            const labelEl = dd.querySelector('.checkbox-dropdown-label');
            const allLabel = dd.getAttribute('data-all-label') || 'All';
            if (labelEl) labelEl.textContent = allLabel;
        });
        
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

