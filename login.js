// login JS

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const rememberCheckbox = document.getElementById('remember_me');
    const submitBtn = document.getElementById('submitBtn');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const closeBtns = document.querySelectorAll('.close-btn');
    const googleBtn = document.querySelector('.google-btn');
    const facebookBtn = document.querySelector('.facebook-btn');
    
    // State
    let isFormValid = false;
    
    // initialize
    function init() {
        setupEventListeners();
        updateSubmitButton();
        
        // Check for saved credentials
        checkSavedCredentials();
        
        // Auto-close messages after 5 seconds
        autoCloseMessages();
    }
    
    // event Listeners
    function setupEventListeners() {
        // Form submission
        form.addEventListener('submit', handleFormSubmit);
        
        // Real-time validation
        emailInput.addEventListener('input', () => validateField(emailInput, validateEmail));
        passwordInput.addEventListener('input', () => validateField(passwordInput, validatePassword));
        
        // Password visibility toggle
        togglePasswordBtn.addEventListener('click', () => togglePasswordVisibility(passwordInput, togglePasswordBtn));
        
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
        
        // Social login buttons
        if (googleBtn) {
            googleBtn.addEventListener('click', handleGoogleLogin);
        }
        
        if (facebookBtn) {
            facebookBtn.addEventListener('click', handleFacebookLogin);
        }
    }
    
    // input validation
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
    
    function validateEmail(email) {
        // Accept both email and username
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
        
        if (!emailRegex.test(email) && !usernameRegex.test(email)) {
            return 'Please enter a valid email or username';
        }
        return null;
    }
    
    function validatePassword(password) {
        if (password.length < 6) {
            return 'Password must be at least 6 characters';
        }
        return null;
    }
    
    // helper functions
    function checkSavedCredentials() {
        // Check for remember me cookie
        if (document.cookie.includes('remember_token')) {
            rememberCheckbox.checked = true;
            
           
        }
    }
    
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
            emailInput,
            passwordInput
        ].every(input => input.value.trim() !== '');
        
        const noErrors = !document.querySelector('.error');
        
        isFormValid = allFilled && noErrors;
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
    
    // form hanle
    function handleFormSubmit(event) {
        // Prevent default only if validation fails
        if (!isFormValid) {
            event.preventDefault();
            
            // Validate all fields
            validateField(emailInput, validateEmail);
            validateField(passwordInput, validatePassword);
            
            // Scroll to first error
            const firstError = document.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return;
        }
        
        // If valid, show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        
        // Form will submit normally
    }
    
   
    //  initialize
    init();
});