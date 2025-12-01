/**
 * SchoolLink Africa - Main JavaScript File
 * Contains common functionality across all pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize form validations
    initializeFormValidation();
    
    // Initialize AJAX loading states
    initializeAjaxLoading();
    
    // Initialize smooth scrolling
    initializeSmoothScrolling();
    
    // Auto-dismiss alerts after 5 seconds
    autoDissmissAlerts();
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    // Add Bootstrap validation classes
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Real-time email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            validateEmail(this);
        });
    });
    
    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"][name="password"], input[type="password"][name="admin_password"]');
    passwordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            showPasswordStrength(this);
        });
    });
}

/**
 * Validate email format
 */
function validateEmail(input) {
    const email = input.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        input.classList.add('is-invalid');
        showValidationMessage(input, 'Please enter a valid email address');
    } else if (email) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        hideValidationMessage(input);
    }
}

/**
 * Show password strength indicator
 */
function showPasswordStrength(input) {
    const password = input.value;
    const strength = calculatePasswordStrength(password);
    
    let strengthIndicator = input.parentNode.querySelector('.password-strength');
    if (!strengthIndicator) {
        strengthIndicator = document.createElement('div');
        strengthIndicator.className = 'password-strength mt-1';
        input.parentNode.appendChild(strengthIndicator);
    }
    
    const strengthColors = {
        'Very Weak': 'danger',
        'Weak': 'warning',
        'Fair': 'info',
        'Good': 'primary',
        'Strong': 'success'
    };
    
    const strengthText = getPasswordStrengthText(strength);
    const colorClass = strengthColors[strengthText] || 'secondary';
    
    strengthIndicator.innerHTML = `
        <div class="progress" style="height: 4px;">
            <div class="progress-bar bg-${colorClass}" style="width: ${(strength / 5) * 100}%"></div>
        </div>
        <small class="text-${colorClass}">Password strength: ${strengthText}</small>
    `;
}

/**
 * Calculate password strength score
 */
function calculatePasswordStrength(password) {
    let score = 0;
    
    if (password.length >= 6) score++;
    if (password.length >= 10) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z\d]/.test(password)) score++;
    
    return score;
}

/**
 * Get password strength text
 */
function getPasswordStrengthText(score) {
    switch (score) {
        case 0:
        case 1: return 'Very Weak';
        case 2: return 'Weak';
        case 3: return 'Fair';
        case 4: return 'Good';
        case 5: return 'Strong';
        default: return 'Very Weak';
    }
}

/**
 * Show validation message
 */
function showValidationMessage(input, message) {
    hideValidationMessage(input);
    
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback validation-message';
    feedback.textContent = message;
    
    input.parentNode.appendChild(feedback);
}

/**
 * Hide validation message
 */
function hideValidationMessage(input) {
    const existing = input.parentNode.querySelector('.validation-message');
    if (existing) {
        existing.remove();
    }
}

/**
 * Initialize AJAX loading states
 */
function initializeAjaxLoading() {
    // Add loading states to forms with AJAX submission
    const ajaxForms = document.querySelectorAll('form[data-ajax="true"]');
    
    ajaxForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                setLoadingState(submitBtn, true);
            }
        });
    });
}

/**
 * Set loading state for buttons
 */
function setLoadingState(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || button.innerHTML;
    }
}

/**
 * Initialize smooth scrolling for anchor links
 */
function initializeSmoothScrolling() {
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    
    anchorLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

/**
 * Auto-dismiss alerts after delay
 */
function autoDissmissAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    
    alerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
}

/**
 * Show success toast notification
 */
function showSuccessToast(message) {
    showToast(message, 'success');
}

/**
 * Show error toast notification
 */
function showErrorToast(message) {
    showToast(message, 'danger');
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1050';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    // Show toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

/**
 * Confirm action with modal
 */
function confirmAction(title, message, callback) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('confirmModal');
    if (!modal) {
        const modalHTML = `
            <div class="modal fade" id="confirmModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Action</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="modal-message"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        modal = document.getElementById('confirmModal');
    }
    
    // Update modal content
    modal.querySelector('.modal-title').textContent = title;
    modal.querySelector('.modal-message').textContent = message;
    
    // Set up confirm button
    const confirmBtn = modal.querySelector('#confirmBtn');
    confirmBtn.onclick = function() {
        callback();
        bootstrap.Modal.getInstance(modal).hide();
    };
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Format date for display
 */
function formatDate(dateString, options = {}) {
    const date = new Date(dateString);
    const defaultOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    return date.toLocaleDateString('en-US', { ...defaultOptions, ...options });
}

/**
 * Debounce function calls
 */
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        
        if (callNow) func.apply(context, args);
    };
}

/**
 * Simple AJAX helper
 */
function ajax(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const config = { ...defaultOptions, ...options };
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('AJAX error:', error);
            throw error;
        });
}

/**
 * Handle file upload with preview
 */
function handleFileUpload(input, previewContainer) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        showErrorToast('Please select a valid image file (JPG, PNG, GIF)');
        input.value = '';
        return;
    }
    
    // Validate file size (5MB limit)
    if (file.size > 5 * 1024 * 1024) {
        showErrorToast('File size must be less than 5MB');
        input.value = '';
        return;
    }
    
    // Create preview
    const reader = new FileReader();
    reader.onload = function(e) {
        if (previewContainer) {
            previewContainer.innerHTML = `
                <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 d-block" onclick="clearFilePreview('${input.id}', '${previewContainer.id}')">
                    Remove
                </button>
            `;
        }
    };
    reader.readAsDataURL(file);
}

/**
 * Clear file preview
 */
function clearFilePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (input) input.value = '';
    if (preview) preview.innerHTML = '';
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showSuccessToast('Copied to clipboard!');
    }).catch(function(err) {
        console.error('Failed to copy: ', err);
        showErrorToast('Failed to copy to clipboard');
    });
}

/**
 * Generate random password
 */
function generateRandomPassword(length = 12) {
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    
    return password;
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showSuccessToast,
        showErrorToast,
        showToast,
        confirmAction,
        formatDate,
        debounce,
        ajax,
        copyToClipboard,
        generateRandomPassword
    };
}