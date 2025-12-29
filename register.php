<?php

session_start();

// Include database connection
require_once 'database.php';
require_once 'functions.php';

// Initialize variables
$full_name = $email = $phone = $user_type = '';
$errors = [];
$success = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['role'] == 'patient') {
        header("Location: booking.php");
    } elseif ($_SESSION['role'] == 'doctor') {
        header("Location: doctor_dashboard.php");
    } elseif ($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php");
    }
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    } elseif (strlen($full_name) < 3) {
        $errors['full_name'] = "Full name must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    } else {
        // Check if email already exists
        $check_email_sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors['email'] = "Email already registered";
        }
        $stmt->close();
    }
    
    // Phone validation for Egypt (01XXXXXXXXX)
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required";
    } elseif (!preg_match('/^(01)[0-9]{9}$/', $phone)) {
        $errors['phone'] = "Please enter a valid Egyptian phone: 01XXXXXXXXX";
    }
    
    // User type validation
    if (empty($user_type)) {
        $errors['user_type'] = "Please select user type";
    } elseif (!in_array($user_type, ['patient', 'doctor'])) {
        $errors['user_type'] = "Invalid user type selected";
    }
    
    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)/', $password)) {
        $errors['password'] = "Password must contain letters and numbers";
    }
    
    // Confirm password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Extract first and last name
        $name_parts = explode(' ', $full_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        $username = strtolower(preg_replace('/[^a-zA-Z]/', '', $first_name)) . rand(100, 999);
        
        // Insert user
        $sql = "INSERT INTO users (username, email, password_hash, user_type, first_name, last_name, phone, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $user_type, $first_name, $last_name, $phone);
        
        if ($stmt->execute()) {
            $success = "Registration successful! You can now login.";
            // Clear form
            $full_name = $email = $phone = $user_type = '';
        } else {
            $errors['database'] = "Registration failed. Please try again.";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediFlow Clinic</title>
    
    <!-- Register Page CSS -->
    <link rel="stylesheet" href="register.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- header area -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><i class="fas fa-stethoscope"></i> MediFlow Clinic</h1>
            </div>
            
            <nav class="main-nav">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="register.php" class="active"><i class="fas fa-user-plus"></i> Register</a>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Log In</a>
                <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
            </nav>
            
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errors['database'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $errors['database']; ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <!-- main section -->
    <main class="register-main">
        <div class="register-container">
            <div class="register-header">
                <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
                <p>Join MediFlow Clinic to manage your health appointments online</p>
            </div>
            
            <form method="POST" action="" class="register-form" id="registerForm">
                <div class="form-group">
                    <label for="full_name">
                        <i class="fas fa-user"></i> Full Name
                    </label>
                    <input type="text" id="full_name" name="full_name" 
                           placeholder="Enter your full name" required
                           value="<?php echo htmlspecialchars($full_name); ?>">
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['full_name']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email" required
                           value="<?php echo htmlspecialchars($email); ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['email']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">
                        <i class="fas fa-phone"></i> Phone Number
                    </label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="01XXXXXXXXX" required
                           value="<?php echo htmlspecialchars($phone); ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['phone']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="user_type">
                        <i class="fas fa-user-md"></i> I am a:
                    </label>
                    <div class="user-type-options">
                        <label class="user-type-option">
                            <input type="radio" name="user_type" value="patient" 
                                   <?php echo ($user_type == 'patient') ? 'checked' : ''; ?>>
                            <div class="option-content">
                                <i class="fas fa-user-injured"></i>
                                <span>Patient</span>
                                <p>Book appointments and manage your health</p>
                            </div>
                        </label>
                        
                        <label class="user-type-option">
                            <input type="radio" name="user_type" value="doctor"
                                   <?php echo ($user_type == 'doctor') ? 'checked' : ''; ?>>
                            <div class="option-content">
                                <i class="fas fa-user-md"></i>
                                <span>Doctor</span>
                                <p>Manage appointments and patient care</p>
                            </div>
                        </label>
                    </div>
                    <?php if (isset($errors['user_type'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['user_type']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                        <span class="strength-text" id="strengthText">Password strength</span>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['password']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i> Confirm Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Re-enter your password" required>
                        <button type="button" class="toggle-password" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['confirm_password']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group terms-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="terms" name="terms" required>
                        <span>I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></span>
                    </label>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Log in here</a>
                </div>
            </form>
            
            <div class="register-info">
                <h3><i class="fas fa-shield-alt"></i> Your Security Matters</h3>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Secure password storage</li>
                    <li><i class="fas fa-check-circle"></i> Encrypted connection</li>
                    <li><i class="fas fa-check-circle"></i> Privacy protected</li>
                </ul>
                <div class="role-explanation">
                    <h4><i class="fas fa-info-circle"></i> Account Types</h4>
                    <p><strong>Patient:</strong> Book appointments, view medical history, manage prescriptions</p>
                    <p><strong>Doctor:</strong> Manage appointments, view patient records, update medical notes</p>
                    <p><em>Note: Admin accounts are created by existing administrators only.</em></p>
                </div>
            </div>
        </div>
    </main>

    <!-- footerr  -->
    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-stethoscope"></i> MediFlow Clinic</h3>
                <p>Providing quality healthcare with modern technology</p>
            </div>
            
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-map-marker-alt"></i> 123 Medical Street, Cairo</p>
                <p><i class="fas fa-phone"></i> +20 123 456 7890</p>
                <p><i class="fas fa-envelope"></i> info@mediflow.com</p>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="services.php">Services</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 MediFlow Clinic. All rights reserved.</p>
            <div class="footer-links">
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
            </div>
        </div>
    </footer>

    <!-- JS file connection -->
    <!-- Register Page JavaScript -->
    <script src="register.js"></script>
    
    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</body>

</html>
