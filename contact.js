// contact JS

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('contactForm');
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const subjectInput = document.getElementById('subject');
    const messageInput = document.getElementById('message');
    const privacyCheckbox = document.getElementById('privacy');
    const submitBtn = document.getElementById('submitBtn');
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const closeBtns = document.querySelectorAll('.close-btn');
    
    // State
    let isFormValid = false;
    
    // initialize
    function init() {
        setupEventListeners();
        updateSubmitButton();
        
        // Auto-close messages after 5 seconds
        autoCloseMessages();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Form submission
        form.addEventListener('submit', handleFormSubmit);
        
        // Real-time validation
        nameInput.addEventListener('input', () => validateField(nameInput, validateName));
        emailInput.addEventListener('input', () => validateField(emailInput, validateEmail));
        subjectInput.addEventListener('change', () => validateField(subjectInput, validateSubject));
        messageInput.addEventListener('input', () => validateField(messageInput, validateMessage));
        privacyCheckbox.addEventListener('change', updateSubmitButton);
        
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
    
    // validation fun
    function validateField(input, validationFunction) {
        const value = input.value.trim();
        const errorElement = input.parentNode.querySelector('.error-text');
        
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
    
    function validateName(name) {
        if (name.length < 2) {
            return 'Name must be at least 2 characters';
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
    
    function validateSubject(subject) {
        if (!subject) {
            return 'Please select a subject';
        }
        return null;
    }
    
    function validateMessage(message) {
        if (message.length < 10) {
            return 'Message must be at least 10 characters';
        }
        if (message.length > 2000) {
            return 'Message is too long (max 2000 characters)';
        }
        return null;
    }
    
    // additional fun
    function showError(input, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        const parent = input.parentNode;
        parent.appendChild(errorDiv);
    }
    
    function updateSubmitButton() {
        const allFilled = [
            nameInput,
            emailInput,
            messageInput
        ].every(input => input.value.trim() !== '');
        
        const subjectSelected = subjectInput.value !== '';
        const noErrors = !document.querySelector('.error');
        const privacyAccepted = privacyCheckbox.checked;
        
        isFormValid = allFilled && subjectSelected && noErrors && privacyAccepted;
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
    
    // form submit handle
    function handleFormSubmit(event) {
        // Prevent default only if validation fails
        if (!isFormValid) {
            event.preventDefault();
            
            // Validate all fields
            validateField(nameInput, validateName);
            validateField(emailInput, validateEmail);
            validateField(subjectInput, validateSubject);
            validateField(messageInput, validateMessage);
            
            // Check privacy policy
            if (!privacyCheckbox.checked) {
                const privacyError = document.createElement('div');
                privacyError.className = 'error-text';
                privacyError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please accept the privacy policy';
                privacyCheckbox.parentNode.parentNode.appendChild(privacyError);
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
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Message...';
        
        // Form will submit normally
    }
    
    // initialize
    init();
});