<?php


// Start output buffering to prevent header errors
ob_start();

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Include database connection
require_once 'database.php';

// Initialize variables
$email = '';
$errors = [];
$success = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // FIX: Check BOTH possible session variable names
    $user_role = $_SESSION['user_type'] ?? $_SESSION['user_role'] ?? '';
    
    if ($user_role == 'doctor') {
        safe_redirect('doctor_dashboard.php');
    } elseif ($user_role == 'admin') {
        safe_redirect('admin_dashboard.php');
    } else {
        safe_redirect('index.php');
    }
}

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success = "Registration successful! Please login with your credentials.";
}

// Check for logout
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = "You have been successfully logged out.";
}

// Check for session expired
if (isset($_GET['expired']) && $_GET['expired'] == 'true') {
    $errors['session'] = "Your session has expired. Please login again.";
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    // Validation
    if (empty($email)) {
        $errors['email'] = "Email/Username is required";
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
    }
    
    // If no errors, check credentials
    if (empty($errors)) {
        // Check if input is email or username
        $is_email = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        // Query to check user
        if ($is_email) {
            $sql = "SELECT * FROM users WHERE email = ?";
        } else {
            $sql = "SELECT * FROM users WHERE username = ?";
        }
        
        // Debug: Check database connection
        if (!isset($conn) || $conn->connect_error) {
            die("Database connection error. Please check config/database.php");
        }
        
        // Prepare statement with error checking
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            // Show SQL error for debugging
            $error_msg = "SQL Error: " . $conn->error;
            error_log("Login SQL Error: " . $conn->error . " | Query: " . $sql);
            $errors['database'] = "System error. Please try again later.";
        } else {
            // Bind parameters and execute
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active (if column exists)
                if (isset($user['is_active']) && $user['is_active'] == 0) {
                    $errors['email'] = "Account is inactive. Please contact administrator.";
                } else {
                    // DIRECT PASSWORD COMPARISON (NO HASHING)
                    // NOTE: This is insecure for production!
                    // Compare plain text password with stored password
                    if ($password === $user['password_hash']) {
                        // FIX: Set BOTH session variables for compatibility
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // CRITICAL FIX: Set BOTH user_type AND user_role
                        $_SESSION['user_type'] = $user['user_type'];  // For dashboard
                        $_SESSION['user_role'] = $user['user_type'];  // For login checks
                        
                        $_SESSION['login_time'] = time();
                        
                        // Set remember me cookie (30 days)
                        if ($remember_me) {
                            $token = bin2hex(random_bytes(32));
                            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), "/", "", true, true);
                            setcookie('user_id', $user['id'], time() + (30 * 24 * 60 * 60), "/", "", true, true);
                            
                            // Store token in database if column exists
                            $update_sql = "UPDATE users SET remember_token = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param("si", $token, $user['id']);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                        
                        // Update last login if column exists
                        $update_login_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $update_login_stmt = $conn->prepare($update_login_sql);
                        if ($update_login_stmt) {
                            $update_login_stmt->bind_param("i", $user['id']);
                            $update_login_stmt->execute();
                            $update_login_stmt->close();
                        }
                        
                        // Redirect based on user type
                        switch ($user['user_type']) {
                            case 'admin':
                                safe_redirect('adminDoctors.php');
                                break;
                            case 'doctor':
                                safe_redirect('doctor_dashboard.php');
                                break;
                            case 'patient':
                                safe_redirect('my_appointments.php');
                                break;
                            default:
                                safe_redirect('index.php');
                        }
                    } else {
                        $errors['password'] = "Invalid password";
                    }
                }
            } else {
                $errors['email'] = "User not found";
            }
            
            $stmt->close();
        }
    }
}

// redirection functions


function safe_redirect($url, $http_code = 302) {
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Get full URL
    $base = get_base_url();
    $full_url = $base . ltrim($url, '/');
    
    // Add no-cache headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Redirect if headers not sent
    if (!headers_sent()) {
        header("Location: $full_url", true, $http_code);
    } else {
        // Fallback - JavaScript redirect
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Redirecting...</title>
            <script>
                window.location.href = "' . htmlspecialchars($full_url, ENT_QUOTES) . '";
            </script>
            <noscript>
                <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($full_url, ENT_QUOTES) . '">
            </noscript>
        </head>
        <body>
            <p>If you are not redirected automatically, <a href="' . htmlspecialchars($full_url, ENT_QUOTES) . '">click here</a>.</p>
        </body>
        </html>';
    }
    
    exit();
}

/**
 * Get base URL for secure redirects
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                ? 'https://' 
                : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove trailing slash if not root
    if ($script_dir !== '/') {
        $script_dir = rtrim($script_dir, '/');
    }
    
    return $protocol . $host . $script_dir . '/';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediFlow Clinic</title>
    
    <!-- Login Page CSS -->
    <link rel="stylesheet" href="login.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
       
    </style>
</head>
<body>
    <!-- Debug info -->
    <div class="debug-info">
        DEBUG: Checking session variables | 
        User Type: <?php echo $_SESSION['user_type'] ?? 'NOT SET'; ?> | 
        User Role: <?php echo $_SESSION['user_role'] ?? 'NOT SET'; ?> |
        User ID: <?php echo $_SESSION['user_id'] ?? 'NOT SET'; ?>
    </div>
    
<!-- header  -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><i class="fas fa-stethoscope"></i> MediFlow Clinic</h1>
            </div>
            
            <nav class="main-nav">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                <a href="login.php" class="active"><i class="fas fa-sign-in-alt"></i> Log In</a>
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
            <span><?php echo htmlspecialchars($success); ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errors['session'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($errors['session']); ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errors['database'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($errors['database']); ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <!-- main area-->
    <main class="login-main">
        <div class="login-container">
            <div class="login-header">
                <h2><i class="fas fa-sign-in-alt"></i> Welcome Back</h2>
                <p>Login to access your MediFlow Clinic account</p>
            </div>
            
            <form method="POST" action="" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email or Username
                    </label>
                    <input type="text" id="email" name="email" 
                           placeholder="Enter your email or username" required
                           value="<?php echo htmlspecialchars($email); ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($errors['email']); ?>
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
                    <?php if (isset($errors['password'])): ?>
                        <div class="error-text">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($errors['password']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group remember-forgot">
                    <label class="checkbox-label">
                        <input type="checkbox" id="remember_me" name="remember_me">
                        <span>Remember me</span>
                    </label>
                    
                    <a href="forgot-password.php" class="forgot-link">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Create account</a>
                </div>
            </form>
            
            <div class="login-info">
                <h3><i class="fas fa-shield-alt"></i> Important Security Notice</h3>
                <p style="color: #dc3545; font-size: 14px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    This is a development system. For production use, 
                    password hashing must be implemented for security.
                </p>
            </div>
        </div>
    </main>

    <!--footer-->
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

    <!-- JS -->
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        // Close message buttons
        document.querySelectorAll('.close-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email or username');
                return false;
            }
            
            if (!password) {
                e.preventDefault();
                alert('Please enter your password');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            submitBtn.disabled = true;
        });
        
        // Hide debug info
        document.querySelector('.debug-info').style.display = 'none';
    </script>
</body>
</html>

<?php ob_end_flush(); ?>
