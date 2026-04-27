/**
 * Odoo Contacts Component JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Auto-submit search form on Enter key
    const searchInput = document.getElementById('filter_search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('adminForm').submit();
            }
        });
    }

    // Form validation enhancement
    const form = document.getElementById('adminForm');
    if (form && form.classList.contains('form-validate')) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('.required');
            let isValid = true;

            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    }

    // Phone number formatting
    const phoneFields = document.querySelectorAll('input[type="tel"]');
    phoneFields.forEach(function(field) {
        field.addEventListener('input', function(e) {
            // Remove non-numeric characters
            let value = e.target.value.replace(/\D/g, '');
            
            // Format as needed (this is a basic example)
            if (value.length >= 8) {
                value = value.substring(0, 8);
            }
            
            e.target.value = value;
        });
    });

    // Email validation
    const emailFields = document.querySelectorAll('input[type="email"]');
    emailFields.forEach(function(field) {
        field.addEventListener('blur', function(e) {
            const email = e.target.value;
            if (email && !isValidEmail(email)) {
                e.target.classList.add('is-invalid');
            } else {
                e.target.classList.remove('is-invalid');
            }
        });
    });

    // Auto-save draft functionality (optional)
    const formFields = document.querySelectorAll('#adminForm input, #adminForm textarea, #adminForm select');
    formFields.forEach(function(field) {
        field.addEventListener('change', function() {
            saveDraft();
        });
    });
});

/**
 * Validate email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Save form data as draft (localStorage)
 */
function saveDraft() {
    const form = document.getElementById('adminForm');
    if (!form) return;

    const formData = new FormData(form);
    const draftData = {};
    
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('jform[') && key !== 'jform[id]') {
            draftData[key] = value;
        }
    }

    localStorage.setItem('odoo_contact_draft', JSON.stringify(draftData));
}

/**
 * Load draft data
 */
function loadDraft() {
    const draftData = localStorage.getItem('odoo_contact_draft');
    if (!draftData) return;

    try {
        const data = JSON.parse(draftData);
        Object.keys(data).forEach(function(key) {
            const field = document.querySelector(`[name="${key}"]`);
            if (field && !field.value) {
                field.value = data[key];
            }
        });
    } catch (e) {
        console.error('Error loading draft:', e);
    }
}

/**
 * Clear draft data
 */
function clearDraft() {
    localStorage.removeItem('odoo_contact_draft');
}

/**
 * Confirm delete action
 */
function deleteContact(contactId, contactName) {
    document.getElementById('deleteContactId').value = contactId;
    document.getElementById('deleteContactName').textContent = contactName;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

/**
 * Create quote for contact (placeholder)
 */
function createQuote(contactId, contactName) {
    // Show a professional modal or redirect to quote creation
    if (confirm(`Create a new quote for: ${contactName}?\n\nThis will redirect you to the quote creation page.`)) {
        // TODO: Implement actual quote creation
        // For now, just show an alert
        alert('Quote creation functionality will be implemented here.\n\nContact ID: ' + contactId + '\nContact Name: ' + contactName);
        
        // Future implementation might look like:
        // window.location.href = 'index.php?option=com_quotes&view=quote&layout=edit&contact_id=' + contactId;
    }
}

/**
 * Refresh contacts list
 */
function refreshContacts() {
    // Add loading state
    const refreshBtn = document.querySelector('[onclick="refreshContacts()"]');
    if (refreshBtn) {
        const originalText = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        // Reload the page
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
}

/**
 * Export contacts (placeholder)
 */
function exportContacts() {
    if (confirm('Export all your contacts to CSV format?')) {
        // TODO: Implement actual export functionality
        alert('Export functionality will be implemented here.\n\nThis will generate a CSV file with all your contacts.');
        
        // Future implementation might look like:
        // window.location.href = 'index.php?option=com_ordenproduccion&task=clientes.export&format=csv';
    }
}

/**
 * Show loading state
 */
function showLoading(element) {
    if (element) {
        element.classList.add('loading');
        const spinner = document.createElement('div');
        spinner.className = 'spinner-border spinner-border-sm me-2';
        spinner.setAttribute('role', 'status');
        element.insertBefore(spinner, element.firstChild);
    }
}

/**
 * Hide loading state
 */
function hideLoading(element) {
    if (element) {
        element.classList.remove('loading');
        const spinner = element.querySelector('.spinner-border');
        if (spinner) {
            spinner.remove();
        }
    }
}