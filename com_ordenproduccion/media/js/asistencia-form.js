/**
 * Asistencia Entry Form JavaScript
 * @package     com_ordenproduccion
 * @version     3.2.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initializeAsistenciaForm();
    });

    /**
     * Initialize the asistencia entry form
     */
    function initializeAsistenciaForm() {
        // Initialize employee selection handler
        initializeEmployeeSelection();
        
        // Initialize date and time pickers
        initializeDateTimePickers();
        
        // Initialize form validation
        initializeFormValidation();

        // Initialize auto-save (optional)
        // initializeAutoSave();
    }

    /**
     * Initialize employee selection dropdown
     */
    function initializeEmployeeSelection() {
        const employeeSelect = document.getElementById('jform_cardno');
        const nameInput = document.getElementById('jform_personname');
        
        if (!employeeSelect || !nameInput) return;
        
        employeeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const employeeName = selectedOption.getAttribute('data-name');
            
            if (employeeName) {
                nameInput.value = employeeName;
                nameInput.readOnly = true;
            } else {
                nameInput.value = '';
                nameInput.readOnly = false;
            }
        });
    }

    /**
     * Initialize date and time pickers
     */
    function initializeDateTimePickers() {
        const dateInput = document.getElementById('jform_authdate');
        const timeInput = document.getElementById('jform_authtime');
        
        // Set current date if empty
        if (dateInput && !dateInput.value) {
            const today = new Date();
            dateInput.value = today.toISOString().split('T')[0];
        }
        
        // Set current time if empty
        if (timeInput && !timeInput.value) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            timeInput.value = hours + ':' + minutes + ':' + seconds;
        }
        
        // Validate date (not in future)
        if (dateInput) {
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate > today) {
                    alert('Date cannot be in the future');
                    this.value = today.toISOString().split('T')[0];
                }
            });
        }
    }

    /**
     * Initialize form validation
     */
    function initializeFormValidation() {
        const form = document.getElementById('adminForm');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Validate form fields
     * @returns {boolean} True if form is valid
     */
    function validateForm() {
        const cardno = document.getElementById('jform_cardno').value;
        const personname = document.getElementById('jform_personname').value;
        const authdate = document.getElementById('jform_authdate').value;
        const authtime = document.getElementById('jform_authtime').value;
        
        if (!cardno || !personname || !authdate || !authtime) {
            alert('Please fill in all required fields');
            return false;
        }
        
        // Validate time format (HH:MM:SS or HH:MM)
        const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/;
        if (!timeRegex.test(authtime)) {
            alert('Please enter a valid time (HH:MM:SS or HH:MM)');
            return false;
        }
        
        return true;
    }

    /**
     * Initialize auto-save functionality (optional)
     */
    function initializeAutoSave() {
        const form = document.getElementById('adminForm');
        if (!form) return;
        
        const inputs = form.querySelectorAll('input, select, textarea');
        let autoSaveTimeout;
        
        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(function() {
                    saveFormData();
                }, 2000);
            });
        });
    }

    /**
     * Save form data to localStorage
     */
    function saveFormData() {
        const form = document.getElementById('adminForm');
        if (!form) return;
        
        const formData = {};
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(function(input) {
            if (input.name && input.name.startsWith('jform[')) {
                formData[input.name] = input.value;
            }
        });
        
        localStorage.setItem('asistencia_form_autosave', JSON.stringify(formData));
    }

    /**
     * Restore form data from localStorage
     */
    function restoreFormData() {
        const savedData = localStorage.getItem('asistencia_form_autosave');
        if (!savedData) return;
        
        try {
            const formData = JSON.parse(savedData);
            
            for (const [name, value] of Object.entries(formData)) {
                const input = document.querySelector('[name="' + name + '"]');
                if (input && !input.value) {
                    input.value = value;
                }
            }
        } catch (e) {
            console.error('Error restoring form data:', e);
        }
    }

    /**
     * Clear auto-save data
     */
    function clearAutoSaveData() {
        localStorage.removeItem('asistencia_form_autosave');
    }

    /**
     * Set current time
     */
    window.setCurrentTime = function() {
        const timeInput = document.getElementById('jform_authtime');
        if (!timeInput) return;
        
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        timeInput.value = hours + ':' + minutes + ':' + seconds;
    };

    /**
     * Set current date
     */
    window.setCurrentDate = function() {
        const dateInput = document.getElementById('jform_authdate');
        if (!dateInput) return;
        
        const today = new Date();
        dateInput.value = today.toISOString().split('T')[0];
    };

    /**
     * Delete entry with confirmation
     */
    window.deleteEntry = function() {
        if (confirm('Are you sure you want to delete this attendance entry?')) {
            const form = document.getElementById('adminForm');
            if (form) {
                form.querySelector('input[name="task"]').value = 'asistenciaentry.delete';
                form.submit();
            }
        }
    };

    // Expose utility functions globally
    window.AsistenciaFormUtils = {
        validateForm: validateForm,
        saveFormData: saveFormData,
        restoreFormData: restoreFormData,
        clearAutoSaveData: clearAutoSaveData
    };

})();

