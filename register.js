// register JS

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('registerForm');
    const fullNameInput = document.getElementById('full_name');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const userTypeInputs = document.querySelectorAll('input[name="user_type"]');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const termsCheckbox = document.getElementById('terms');
    const submitBtn = document.getElementById('submitBtn');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const closeBtns = document.querySelectorAll('.close-btn');
    
    // State
    let isFormValid = false;
    let selectedUserType = '';
    
    //initialize
    function init() {
        setupEventListeners();
        updateSubmitButton();
        checkPasswordStrength();
        
        // Auto-close messages after 5 seconds
        autoCloseMessages();
    }
    
    // event listeners
    function setupEventListeners() {
        // Form submission
        form.addEventListener('submit', handleFormSubmit);
        
        // Real-time validation
        fullNameInput.addEventListener('input', () => validateField(fullNameInput, validateFullName));
        emailInput.addEventListener('input', () => validateField(emailInput, validateEmail));
        phoneInput.addEventListener('input', () => validateField(phoneInput, validatePhone));
        
        // User type selection
        userTypeInputs.forEach(input => {
            input.addEventListener('change', function() {
                selectedUserType = this.value;
                validateUserType();
                updateSubmitButton();
            });
        });
        
        // Password validation
        passwordInput.addEventListener('input', () => {
            checkPasswordStrength();
            validateField(passwordInput, validatePassword);
            validateConfirmPassword();
        });
        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        termsCheckbox.addEventListener('change', updateSubmitButton);
        
        // Password visibility toggle
        togglePasswordBtn.addEventListener('click', () => togglePasswordVisibility(passwordInput, togglePasswordBtn));
        toggleConfirmPasswordBtn.addEventListener('click', () => togglePasswordVisibility(confirmPasswordInput, toggleConfirmPasswordBtn));
        
        // Mobile menu toggle
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        // Close message buttons
        closeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.message').style.display = 'none';
            });
        });
        
        // Click outside to close mobile menu
        document.addEventListener('click', function(event) {
            if (mainNav && mainNav.classList.contains('active') && 
                !event.target.closest('.main-nav') && 
                !event.target.closest('.mobile-menu-btn')) {
                closeMobileMenu();
            }
        });
    }
    
    // validation functions
    function validateField(input, validationFunction) {
        const value = input.value.trim();
        const errorElement = input.parentNode.querySelector('.error-text') || 
                            input.parentNode.parentNode.querySelector('.error-text');
        
        // Clear previous error
        if (errorElement) {
            errorElement.remove();
        }
        
        // Remove error/success classes
        input.classList.remove('error', 'success');
        
        // Skip validation if empty (required validation on submit)
        if (!value) {
            updateSubmitButton();
            return;
        }
        
        const error = validationFunction(value);
        
        if (error) {
            showError(input, error);
            input.classList.add('error');
        } else {
            input.classList.add('success');
        }
        
        updateSubmitButton();
    }
    
    function validateFullName(name) {
        if (name.length < 3) {
            return 'Full name must be at least 3 characters';
        }
        return null;
    }
    
    function validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return 'Please enter a valid email address';
        }
        return null;
    }
    
    function validatePhone(phone) {
        // First, remove any non-digit characters
        const digitsOnly = phone.replace(/\D/g, '');
        
        // Check if it's exactly 11 digits starting with 01
        const phoneRegex = /^(01)[0-9]{9}$/;
        if (!phoneRegex.test(digitsOnly)) {
            return 'Please enter a valid Egyptian phone (01XXXXXXXXX)';
        }
        return null;
    }
    
    function validateUserType() {
        const errorElement = document.querySelector('.user-type-options').parentNode.querySelector('.error-text');
        
        // Clear previous error
        if (errorElement) {
            errorElement.remove();
        }
        
        if (!selectedUserType) {
            showError(document.querySelector('.user-type-options'), 'Please select user type');
            return false;
        }
        
        return true;
    }
    
    function validatePassword(password) {
        if (password.length < 8) {
            return 'Password must be at least 8 characters';
        }
        
        const hasLetter = /[a-zA-Z]/.test(password);
        const hasNumber = /\d/.test(password);
        
        if (!hasLetter || !hasNumber) {
            return 'Password must contain both letters and numbers';
        }
        
        return null;
    }
    
    function validateConfirmPassword() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const errorElement = confirmPasswordInput.parentNode.parentNode.querySelector('.error-text');
        
        // Clear previous error
        if (errorElement) {
            errorElement.remove();
        }
        
        confirmPasswordInput.classList.remove('error', 'success');
        
        if (!confirmPassword) {
            updateSubmitButton();
            return;
        }
        
        if (password !== confirmPassword) {
            showError(confirmPasswordInput, 'Passwords do not match');
            confirmPasswordInput.classList.add('error');
        } else if (password) {
            confirmPasswordInput.classList.add('success');
        }
        
        updateSubmitButton();
    }
    
    // password strength checkers
    function checkPasswordStrength() {
        const password = passwordInput.value;
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
        
        strengthBar.style.width = width;
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    }
    
    // additional helper fun
    function showError(input, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        const parent = input.parentNode.parentNode || input.parentNode;
        parent.appendChild(errorDiv);
    }
    
    function togglePasswordVisibility(input, button) {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const icon = button.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }
    
    function updateSubmitButton() {
        const allFilled = [
            fullNameInput,
            emailInput,
            phoneInput,
            passwordInput,
            confirmPasswordInput
        ].every(input => input.value.trim() !== '');
        
        const userTypeSelected = selectedUserType !== '';
        const noErrors = !document.querySelector('.error');
        const termsAccepted = termsCheckbox.checked;
        
        isFormValid = allFilled && userTypeSelected && noErrors && termsAccepted;
        submitBtn.disabled = !isFormValid;
    }
    
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
    
    // handle form submit
    function handleFormSubmit(event) {
        // Prevent default only if validation fails
        if (!isFormValid) {
            event.preventDefault();
            
            // Validate all fields
            validateField(fullNameInput, validateFullName);
            validateField(emailInput, validateEmail);
            validateField(phoneInput, validatePhone);
            validateUserType();
            validateField(passwordInput, validatePassword);
            validateConfirmPassword();
            
            // Check terms
            if (!termsCheckbox.checked) {
                const termsError = document.createElement('div');
                termsError.className = 'error-text';
                termsError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please accept the terms and conditions';
                termsCheckbox.parentNode.appendChild(termsError);
            }
            
            // Scroll to first error
            const firstError = document.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return;
        }
        
        // If valid, show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        
        // Form will submit normally
    }
    
    // initialize
    init();
});