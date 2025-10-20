/**
 * Payment Proof JavaScript for Com Orden Produccion
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof
 * @subpackage  PaymentProof
 * @since       3.1.3
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Initialize payment proof functionality
    initPaymentProof();

    function initPaymentProof() {
        // Add event listeners
        setupFormValidation();
        setupFileUpload();
        setupPaymentTypeChange();
    }

    function setupFormValidation() {
        const form = document.getElementById('payment-proof-form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });
    }

    function setupFileUpload() {
        const fileInput = document.getElementById('payment_proof_file');
        if (!fileInput) return;

        fileInput.addEventListener('change', function(e) {
            validateFileUpload(e.target);
        });

        // Drag and drop functionality
        const fileDropZone = fileInput.parentElement;
        if (fileDropZone) {
            fileDropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileDropZone.classList.add('drag-over');
            });

            fileDropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileDropZone.classList.remove('drag-over');
            });

            fileDropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fileDropZone.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    validateFileUpload(fileInput);
                }
            });
        }
    }

    function setupPaymentTypeChange() {
        const paymentTypeSelect = document.getElementById('payment_type');
        const bankSelect = document.getElementById('bank');
        
        if (!paymentTypeSelect || !bankSelect) return;

        paymentTypeSelect.addEventListener('change', function() {
            const paymentType = this.value;
            
            // Show/hide bank field based on payment type
            if (paymentType === 'efectivo') {
                bankSelect.parentElement.style.display = 'none';
                bankSelect.required = false;
            } else {
                bankSelect.parentElement.style.display = 'block';
                bankSelect.required = true;
            }
        });

        // Trigger change event on page load
        paymentTypeSelect.dispatchEvent(new Event('change'));
    }

    function validateForm() {
        const paymentType = document.getElementById('payment_type');
        const documentNumber = document.getElementById('document_number');
        
        let isValid = true;
        let errorMessage = '';

        // Validate payment type
        if (!paymentType || !paymentType.value) {
            isValid = false;
            errorMessage += '• Tipo de pago es requerido\n';
        }

        // Validate document number
        if (!documentNumber || !documentNumber.value.trim()) {
            isValid = false;
            errorMessage += '• Número de documento es requerido\n';
        }

        // Validate bank for non-cash payments
        if (paymentType && paymentType.value !== 'efectivo') {
            const bankSelect = document.getElementById('bank');
            if (!bankSelect || !bankSelect.value) {
                isValid = false;
                errorMessage += '• Banco es requerido para este tipo de pago\n';
            }
        }

        if (!isValid) {
            showAlert('error', 'Por favor corrija los siguientes errores:\n\n' + errorMessage);
        }

        return isValid;
    }

    function validateFileUpload(fileInput) {
        const file = fileInput.files[0];
        if (!file) return true;

        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!allowedTypes.includes(file.type)) {
            showAlert('error', 'Tipo de archivo no válido. Solo se permiten archivos JPG, PNG y PDF.');
            fileInput.value = '';
            return false;
        }
        
        // Check file size
        if (file.size > maxSize) {
            showAlert('error', 'El archivo es demasiado grande. El tamaño máximo permitido es 5MB.');
            fileInput.value = '';
            return false;
        }

        // Show success message
        showAlert('success', 'Archivo seleccionado correctamente: ' + file.name);
        return true;
    }

    function showAlert(type, message) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.payment-proof-alert');
        existingAlerts.forEach(alert => alert.remove());

        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} payment-proof-alert`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
            ${message.replace(/\n/g, '<br>')}
        `;

        document.body.appendChild(alertDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Utility function to format currency
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('es-GT', {
            style: 'currency',
            currency: 'GTQ'
        }).format(amount);
    };

    // Utility function to format date
    window.formatDate = function(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('es-GT', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };
});
