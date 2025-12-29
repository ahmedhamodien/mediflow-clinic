<?php
// index.php - HOMEPAGE
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - MediFlow Clinic</title>
    
    <!-- Home Page CSS -->
    <link rel="stylesheet" href="CSS/index.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- header-->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><i class="fas fa-stethoscope"></i> MediFlow Clinic</h1>
            </div>
            
            <nav class="main-nav">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Home</a>
                <?php if (!$isLoggedIn): ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a> 
                    <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                <?php else: ?>
                    <span class="welcome-text">Welcome, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="my_appointments.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php endif; ?>
                <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
            </nav>
            
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- main content-->
    <main class="index-main">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1><i class="fas fa-heartbeat"></i> Welcome to MediFlow Clinic</h1>
                        <p class="hero-subtitle">Your Health, Our Priority</p>
                        <p class="hero-description">
                            At MediFlow Clinic, we provide comprehensive healthcare services with state-of-the-art facilities 
                            and experienced medical professionals. Your well-being is our top priority.
                        </p>
                        
                        <div class="hero-buttons">
                            <?php if (!$isLoggedIn): ?>
                                <a href="register.php" class="hero-btn primary-btn">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </a>
                            <?php else: ?>
                                <a href="booking.php" class="hero-btn primary-btn">
                                    <i class="fas fa-calendar-check"></i> Book Appointment
                                </a>
                            <?php endif; ?>
                            <a href="#services" class="hero-btn secondary-btn">
                                <i class="fas fa-stethoscope"></i> Our Services
                            </a>
                            <a href="contact.php" class="hero-btn outline-btn">
                                <i class="fas fa-phone-alt"></i> Contact Us
                            </a>
                        </div>
                        
                        <div class="hero-stats">
                            <div class="stat-item">
                                <h3>5000+</h3>
                                <p>Happy Patients</p>
                            </div>
                            <div class="stat-item">
                                <h3>50+</h3>
                                <p>Expert Doctors</p>
                            </div>
                            <div class="stat-item">
                                <h3>24/7</h3>
                                <p>Emergency Service</p>
                            </div>
                            <div class="stat-item">
                                <h3>15+</h3>
                                <p>Specialties</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hero-image">
                        <div class="image-wrapper">
                            <img src="images/index.jpeg" alt="Modern Clinic">
                            <div class="image-badge">
                                <i class="fas fa-shield-alt"></i>
                                <span>Safe & Hygienic</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section class="services-section" id="services">
            <div class="section-container">
                <div class="section-header">
                    <h2><i class="fas fa-medkit"></i> Our Specialized Services</h2>
                    <p>Comprehensive healthcare services for all your medical needs</p>
                </div>
                
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3>Cardiology</h3>
                        <p>Advanced heart care and diagnostics with modern equipment.</p>
                        <a href="services.php#cardiology" class="service-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-lungs"></i>
                        </div>
                        <h3>Pulmonology</h3>
                        <p>Expert care for respiratory disorders and lung health.</p>
                        <a href="services.php#pulmonology" class="service-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3>Neurology</h3>
                        <p>Specialized treatment for neurological conditions.</p>
                        <a href="services.php#neurology" class="service-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-bone"></i>
                        </div>
                        <h3>Orthopedics</h3>
                        <p>Bone, joint, and muscle care with advanced surgical options.</p>
                        <a href="services.php#orthopedics" class="service-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-baby"></i>
                        </div>
                        <h3>Pediatrics</h3>
                        <p>Comprehensive healthcare for children of all ages.</p>
                        <a href="services.php#pediatrics" class="service-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-allergies"></i>
                        </div>
                        <h3>Dermatology</h3>
                        <p>Skin care treatments and cosmetic procedures.</p>
                        <a href="services.php#dermatology" class="service-link">
                            Learn More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="section-cta">
                    <?php if (!$isLoggedIn): ?>
                        <a href="register.php" class="cta-btn">
                            <i class="fas fa-user-plus"></i> Join Us Today
                        </a>
                    <?php else: ?>
                        <a href="booking.php" class="cta-btn">
                            <i class="fas fa-calendar-check"></i> Book Appointment Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="section-container">
                <div class="section-header">
                    <h2><i class="fas fa-star"></i> Why Choose MediFlow Clinic?</h2>
                    <p>Experience healthcare excellence with our patient-centered approach</p>
                </div>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h3>Expert Doctors</h3>
                        <p>Our team consists of board-certified specialists with extensive experience.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clinic-medical"></i>
                        </div>
                        <h3>Modern Equipment</h3>
                        <p>State-of-the-art medical technology for accurate diagnosis and treatment.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>24/7 Emergency</h3>
                        <p>Round-the-clock emergency services with immediate medical attention.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h3>Patient Care</h3>
                        <p>Personalized treatment plans focused on individual patient needs.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Safe Environment</h3>
                        <p>Strict hygiene protocols and safety measures for your well-being.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-comments-dollar"></i>
                        </div>
                        <h3>Affordable Care</h3>
                        <p>Transparent pricing and quality healthcare at reasonable costs.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <div class="cta-container">
                <div class="cta-content">
                    <h2><i class="fas fa-calendar-plus"></i> Ready to Take Control of Your Health?</h2>
                    <p>Join thousands of satisfied patients who trust MediFlow Clinic with their healthcare needs.</p>
                    
                    <div class="cta-buttons">
                        <?php if (!$isLoggedIn): ?>
                            <a href="register.php" class="cta-btn primary-cta">
                                <i class="fas fa-user-plus"></i> Register Now
                            </a>
                            <a href="login.php" class="cta-btn secondary-cta">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php" class="cta-btn primary-cta">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </a>
                            <a href="booking.php" class="cta-btn secondary-cta">
                                <i class="fas fa-calendar-check"></i> Book Appointment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!--footerrr-->
    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-stethoscope"></i> MediFlow Clinic</h3>
                <p>Providing quality healthcare with modern technology and compassionate care since 2005.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h4><i class="fas fa-link"></i> Quick Links</h4>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="services.php">Services</a>
                <a href="doctors.php">Our Doctors</a>
                <a href="contact.php">Contact</a>
            </div>
            
            <div class="footer-section">
                <h4><i class="fas fa-medkit"></i> Services</h4>
                <a href="services.php#cardiology">Cardiology</a>
                <a href="services.php#neurology">Neurology</a>
                <a href="services.php#orthopedics">Orthopedics</a>
                <a href="services.php#pediatrics">Pediatrics</a>
                <a href="services.php#dermatology">Dermatology</a>
            </div>
            
            <div class="footer-section">
                <h4><i class="fas fa-address-card"></i> Contact Info</h4>
                <p><i class="fas fa-map-marker-alt"></i> 123 Medical Street, Cairo</p>
                <p><i class="fas fa-phone"></i> +20 123 456 7890</p>
                <p><i class="fas fa-envelope"></i> info@mediflow.com</p>
                <p><i class="fas fa-clock"></i> Mon-Sun: 8:00 AM - 10:00 PM</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 MediFlow Clinic. All rights reserved.</p>
            <div class="footer-links">
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="sitemap.php">Sitemap</a>
            </div>
        </div>
    </footer>

    <!-- javascript connection-->
    <script src="JS/index.js"></script>
</body>
</html>