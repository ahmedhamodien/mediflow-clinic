// ============================================
// INDEX PAGE JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    const serviceCards = document.querySelectorAll('.service-card');
    const featureCards = document.querySelectorAll('.feature-card');
    const statItems = document.querySelectorAll('.stat-item');
    const ctaButtons = document.querySelectorAll('.cta-btn, .hero-btn');
    
    // State
    let animationTriggered = false;
    
    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        setupEventListeners();
        setupAnimations();
        setupSmoothScrolling();
        updateActiveNav();
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    function setupEventListeners() {
        // Mobile menu toggle
        if (mobileMenuBtn && mainNav) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
            
            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (mainNav.classList.contains('active') && 
                    !event.target.closest('.main-nav') && 
                    !event.target.closest('.mobile-menu-btn')) {
                    closeMobileMenu();
                }
            });
            
            // Close menu on link click
            mainNav.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', closeMobileMenu);
            });
        }
        
        // Card hover effects
        serviceCards.forEach(card => {
            card.addEventListener('mouseenter', () => enhanceCard(card));
            card.addEventListener('mouseleave', () => resetCard(card));
        });
        
        featureCards.forEach(card => {
            card.addEventListener('mouseenter', () => enhanceCard(card));
            card.addEventListener('mouseleave', () => resetCard(card));
        });
        
        // Stat counter animation
        if (isElementInViewport(document.querySelector('.hero-stats'))) {
            animateStats();
        } else {
            window.addEventListener('scroll', handleScrollAnimation);
        }
        
        // CTA button hover effects
        ctaButtons.forEach(button => {
            button.addEventListener('mouseenter', () => enhanceButton(button));
            button.addEventListener('mouseleave', () => resetButton(button));
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
        
        // Toggle body scroll
        document.body.style.overflow = mainNav.classList.contains('active') ? 'hidden' : '';
    }
    
    function closeMobileMenu() {
        mainNav.classList.remove('active');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
        document.body.style.overflow = '';
    }
    
    // ============================================
    // ANIMATION FUNCTIONS
    // ============================================
    function setupAnimations() {
        // Add animation classes on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    
                    // Specific animations for different elements
                    if (entry.target.classList.contains('service-card')) {
                        animateServiceCard(entry.target);
                    } else if (entry.target.classList.contains('feature-card')) {
                        animateFeatureCard(entry.target);
                    }
                }
            });
        }, observerOptions);
        
        // Observe elements
        document.querySelectorAll('.service-card, .feature-card').forEach(el => {
            observer.observe(el);
        });
    }
    
    function animateServiceCard(card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    }
    
    function animateFeatureCard(card) {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }, 100);
    }
    
    function enhanceCard(card) {
        card.style.transform = 'translateY(-10px)';
        card.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.1)';
        
        // Add pulse effect to icon
        const icon = card.querySelector('.service-icon, .feature-icon');
        if (icon) {
            icon.style.animation = 'pulse 1s ease';
        }
    }
    
    function resetCard(card) {
        card.style.transform = 'translateY(0)';
        card.style.boxShadow = '';
        
        const icon = card.querySelector('.service-icon, .feature-icon');
        if (icon) {
            icon.style.animation = '';
        }
    }
    
    function enhanceButton(button) {
        button.style.transform = 'translateY(-3px)';
        
        if (button.classList.contains('primary-btn') || button.classList.contains('primary-cta')) {
            button.style.boxShadow = '0 10px 25px rgba(0, 71, 171, 0.3)';
        } else if (button.classList.contains('secondary-btn') || button.classList.contains('secondary-cta')) {
            button.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.1)';
        }
        
        // Add bounce to icon
        const icon = button.querySelector('i');
        if (icon) {
            icon.style.transform = 'scale(1.2)';
            icon.style.transition = 'transform 0.3s ease';
        }
    }
    
    function resetButton(button) {
        button.style.transform = '';
        button.style.boxShadow = '';
        
        const icon = button.querySelector('i');
        if (icon) {
            icon.style.transform = '';
        }
    }
    
    // ============================================
    // STAT COUNTER ANIMATION
    // ============================================
    function animateStats() {
        if (animationTriggered) return;
        
        animationTriggered = true;
        statItems.forEach((item, index) => {
            const numberElement = item.querySelector('h3');
            const targetNumber = parseInt(numberElement.textContent);
            let currentNumber = 0;
            const increment = Math.ceil(targetNumber / 50);
            const delay = index * 100;
            
            setTimeout(() => {
                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= targetNumber) {
                        currentNumber = targetNumber;
                        clearInterval(timer);
                    }
                    numberElement.textContent = currentNumber + '+';
                }, 30);
            }, delay);
        });
    }
    
    function handleScrollAnimation() {
        if (!animationTriggered && isElementInViewport(document.querySelector('.hero-stats'))) {
            animateStats();
            window.removeEventListener('scroll', handleScrollAnimation);
        }
    }
    
    // ============================================
    // HELPER FUNCTIONS
    // ============================================
    function setupSmoothScrolling() {
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '#!') return;
                
                e.preventDefault();
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    const headerHeight = document.querySelector('.main-header').offsetHeight;
                    const targetPosition = targetElement.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Update URL without page reload
                    history.pushState(null, null, href);
                }
            });
        });
    }
    
    function updateActiveNav() {
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.main-nav a');
        
        navLinks.forEach(link => {
            const linkHref = link.getAttribute('href');
            if (linkHref === currentPage || 
                (currentPage === '' && linkHref === 'index.php') ||
                (currentPage === 'index.php' && linkHref === 'index.php')) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }
    
    function isElementInViewport(el) {
        if (!el) return false;
        
        const rect = el.getBoundingClientRect();
        return (
            rect.top <= (window.innerHeight || document.documentElement.clientHeight) * 0.8 &&
            rect.bottom >= 0
        );
    }
    
    // ============================================
    // UTILITY FUNCTIONS
    // ============================================
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    // ============================================
    // WINDOW EVENT LISTENERS
    // ============================================
    // Debounced scroll handler
    window.addEventListener('scroll', debounce(function() {
        // Add parallax effect to hero image
        const heroImage = document.querySelector('.hero-image');
        if (heroImage && window.scrollY < window.innerHeight) {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            heroImage.style.transform = 'translate3d(0px, ' + rate + 'px, 0px)';
        }
    }, 10));
    
    // Resize handler
    window.addEventListener('resize', debounce(function() {
        if (window.innerWidth > 768 && mainNav.classList.contains('active')) {
            closeMobileMenu();
        }
    }, 250));
    
    // ============================================
    // INITIALIZE
    // ============================================
    init();
    
    // Log initialization
    console.log('MediFlow Clinic - Home Page initialized');
});
// Update this function in your index.js file
function updateActiveNav() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.main-nav a');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        
        // Remove active class from all links
        link.classList.remove('active');
        
        // Add active class to current page
        if (linkHref === currentPage) {
            link.classList.add('active');
        }
        
        // Special case for index.php when on root
        if ((currentPage === '' || currentPage === '/') && linkHref === 'index.php') {
            link.classList.add('active');
        }
    });
}

// Also add this function to handle link clicks
function setupNavigationLinks() {
    const navLinks = document.querySelectorAll('.main-nav a');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // If it's an anchor link, handle smooth scrolling
            if (href.startsWith('#')) {
                e.preventDefault();
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    const headerHeight = document.querySelector('.main-header').offsetHeight;
                    const targetPosition = targetElement.offsetTop - headerHeight - 20;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            }
            
            // Close mobile menu after clicking
            if (window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });
    });
}

// Update the init function to include this:
function init() {
    setupEventListeners();
    setupAnimations();
    setupSmoothScrolling();
    setupNavigationLinks(); // Add this line
    updateActiveNav();
}