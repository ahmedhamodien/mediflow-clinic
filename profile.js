
// ============================================
// PROFILE PAGE JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const closeBtns = document.querySelectorAll('.close-btn');
    const editBtns = document.querySelectorAll('.edit-btn');
    const passwordForm = document.getElementById('passwordForm');
    const currentPasswordInput = document.getElementById('current_password');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const togglePasswordBtns = document.querySelectorAll('.toggle-password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    // State
    let isEditing = false;
    
    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        setupEventListeners();
        setupPasswordStrengthChecker();
        setupPhoneFormatting();
        autoCloseMessages();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Mobile menu toggle
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        // Close message buttons
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const message = this.closest('.message');
                message.style.opacity = '0';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 300);
            });
        });
        
        // Edit buttons
        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const sectionId = this.dataset.section;
                toggleEditMode(sectionId, this);
            });
        });
        
        // Cancel buttons
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const sectionId = this.dataset.section;
                cancelEdit(sectionId);
            });
        });
        
        // Password visibility toggle
        togglePasswordBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const wrapper = this.closest('.password-wrapper');
                const input = wrapper.querySelector('input');
                togglePasswordVisibility(input, this);
            });
        });
        
        // Password strength
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', checkPasswordStrength);
        }
        
        // Password form validation
        if (passwordForm) {
            passwordForm.addEventListener('submit', validatePasswordForm);
        }
        
        // Phone number formatting
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', formatPhoneNumber);
        });
        
        // Click outside to close mobile menu
        document.addEventListener('click', function(event) {
            if (mainNav && mainNav.classList.contains('active') && 
                !event.target.closest('.main-nav') && 
                !event.target.closest('.mobile-menu-btn')) {
                closeMobileMenu();
            }
        });
        
        // Escape key to close mobile menu
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });
    }
    
    // ============================================
    // MOBILE MENU FUNCTIONS
    // ============================================
    function toggleMobileMenu() {
        mainNav.classList.toggle('active');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    }
    
    function closeMobileMenu() {
        mainNav.classList.remove('active');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }
    
    // ============================================
    // EDIT MODE FUNCTIONS
    // ============================================
    function toggleEditMode(sectionId, button) {
        const section = document.getElementById(sectionId);
        const form = section.querySelector('form');
        const inputs = form.querySelectorAll('input:not([type="hidden"])');
        const formActions = form.querySelector('.form-actions');
        const cancelBtn = form.querySelector('.cancel-btn');
        
        if (isEditing) {
            showNotification('Please save or cancel current edits first', 'warning');
            return;
        }
        
        isEditing = true;
        button.style.display = 'none';
        
        // Make inputs editable
        inputs.forEach(input => {
            input.removeAttribute('readonly');
            input.focus();
        });
        
        // Show form actions
        if (formActions) {
            formActions.style.display = 'flex';
        }
        
        // Set section ID on cancel button
        if (cancelBtn) {
            cancelBtn.dataset.section = sectionId;
        }
        
        // Scroll to form
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function cancelEdit(sectionId) {
        const section = document.getElementById(sectionId);
        const form = section.querySelector('form');
        const inputs = form.querySelectorAll('input:not([type="hidden"])');
        const formActions = form.querySelector('.form-actions');
        const editBtn = section.querySelector('.edit-btn');
        
        // Reset inputs to original values (form will handle this on page reload)
        inputs.forEach(input => {
            input.setAttribute('readonly', true);
        });
        
        // Hide form actions
        if (formActions) {
            formActions.style.display = 'none';
        }
        
        // Show edit button
        if (editBtn) {
            editBtn.style.display = 'flex';
        }
        
        isEditing = false;
        
        showNotification('Edit cancelled', 'info');
    }
    
    // ============================================
    // PASSWORD FUNCTIONS
    // ============================================
    function togglePasswordVisibility(input, button) {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const icon = button.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }
    
    function checkPasswordStrength() {
        const password = newPasswordInput.value;
        let strength = 0;
        let text = 'Password strength';
        let color = '#dc3545';
        let width = '0%';
        
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        switch(strength) {
            case 0:
                text = 'Very Weak';
                color = '#dc3545';
                width = '20%';
                break;
            case 1:
                text = 'Weak';
                color = '#dc3545';
                width = '40%';
                break;
            case 2:
                text = 'Fair';
                color = '#ffc107';
                width = '60%';
                break;
            case 3:
                text = 'Good';
                color = '#28a745';
                width = '80%';
                break;
            case 4:
            case 5:
                text = 'Strong';
                color = '#20c997';
                width = '100%';
                break;
        }
        
        if (strengthBar) {
            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
        }
        if (strengthText) {
            strengthText.textContent = text;
            strengthText.style.color = color;
        }
    }
    
    function validatePasswordForm(event) {
        const password = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        let isValid = true;
        const errors = [];
        
        // Clear previous errors
        clearPasswordErrors();
        
        // Validate current password
        if (!currentPasswordInput.value) {
            showPasswordError(currentPasswordInput, 'Current password is required');
            isValid = false;
        }
        
        // Validate new password
        if (!password) {
            showPasswordError(newPasswordInput, 'New password is required');
            isValid = false;
        } else if (password.length < 8) {
            showPasswordError(newPasswordInput, 'Password must be at least 8 characters');
            isValid = false;
        } else if (!/(?=.*[A-Za-z])(?=.*\d)/.test(password)) {
            showPasswordError(newPasswordInput, 'Password must contain letters and numbers');
            isValid = false;
        }
        
        // Validate confirm password
        if (!confirmPassword) {
            showPasswordError(confirmPasswordInput, 'Please confirm your new password');
            isValid = false;
        } else if (password !== confirmPassword) {
            showPasswordError(confirmPasswordInput, 'Passwords do not match');
            isValid = false;
        }
        
        if (!isValid) {
            event.preventDefault();
            
            // Scroll to first error
            const firstError = document.querySelector('.password-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
            submitBtn.disabled = true;
            
            // Restore button after 2 seconds (form will submit)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        }
    }
    
    function showPasswordError(input, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text password-error';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        const parent = input.parentNode.parentNode;
        parent.appendChild(errorDiv);
    }
    
    function clearPasswordErrors() {
        document.querySelectorAll('.password-error').forEach(error => error.remove());
    }
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    function setupPasswordStrengthChecker() {
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', checkPasswordStrength);
        }
    }
    
    function setupPhoneFormatting() {
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', formatPhoneNumber);
        });
    }
    
    function formatPhoneNumber(event) {
        const input = event.target;
        let value = input.value.replace(/\D/g, '');
        
        if (value.startsWith('0')) {
            value = value.substring(1);
        }
        
        if (value.length > 0) {
            value = '01' + value;
        }
        
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        
        input.value = value;
    }
    
    function autoCloseMessages() {
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.opacity = '0';
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 300);
            });
        }, 5000);
    }
    
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        document.querySelectorAll('.notification').forEach(notification => {
            notification.remove();
        });
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
            color: ${type === 'warning' ? '#212529' : 'white'};
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            max-width: 350px;
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
});

// Add CSS for notifications
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .error-text {
        color: #dc3545;
        font-size: 0.85rem;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
`;
document.head.appendChild(notificationStyles);
