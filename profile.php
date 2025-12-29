<?php
session_start();

// Database connection configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'clinic_system';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user) {
    $_SESSION['error_message'] = "User not found";
    header("Location: login.php");
    exit();
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists (excluding current user)
        $check_email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_email_sql);
        $check_stmt->bind_param("si", $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $errors[] = "Email already in use by another account";
        }
        $check_stmt->close();
    }
    
    // Phone validation for Egypt
    if (!empty($phone) && !preg_match('/^(01)[0-9]{9}$/', $phone)) {
        $errors[] = "Please enter a valid Egyptian phone: 01XXXXXXXXX";
    }
    
    // Emergency contact phone validation
    if (!empty($emergency_contact_phone) && !preg_match('/^(01)[0-9]{9}$/', $emergency_contact_phone)) {
        $errors[] = "Please enter a valid Egyptian phone for emergency contact: 01XXXXXXXXX";
    }
    
    if (empty($errors)) {
        // Update user in database
        $update_sql = "UPDATE users SET 
                      first_name = ?, 
                      last_name = ?, 
                      email = ?, 
                      phone = ?, 
                      address = ?, 
                      date_of_birth = ?, 
                      emergency_contact_name = ?, 
                      emergency_contact_phone = ?,
                      updated_at = NOW()
                      WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            "ssssssssi",
            $first_name,
            $last_name,
            $email,
            $phone,
            $address,
            $date_of_birth,
            $emergency_contact_name,
            $emergency_contact_phone,
            $user_id
        );
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            
            // Update session data
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            // Refresh user data
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            $user_stmt->close();
            
            // Add to audit log
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id) 
                          VALUES (?, 'update', 'users', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("ii", $user_id, $user_id);
            $audit_stmt->execute();
            $audit_stmt->close();
        } else {
            $error_message = "Failed to update profile: " . $conn->error;
        }
        
        $update_stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle password change - PLAIN TEXT VERSION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Verify current password - PLAIN TEXT COMPARISON
    // Note: Assuming password is stored in 'password_hash' field but in plain text
    if ($current_password !== $user['password_hash']) {
        $errors[] = "Current password is incorrect";
    }
    
    // Validate new password
    if (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)/', $new_password)) {
        $errors[] = "New password must contain letters and numbers";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        // Store password in plain text
        $password_sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $password_stmt = $conn->prepare($password_sql);
        $password_stmt->bind_param("si", $new_password, $user_id);
        
        if ($password_stmt->execute()) {
            $success_message = "Password changed successfully!";
            
            // Add to audit log
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id) 
                          VALUES (?, 'password_change', 'users', ?)";
            $audit_stmt = $conn->prepare($audit_sql);
            $audit_stmt->bind_param("ii", $user_id, $user_id);
            $audit_stmt->execute();
            $audit_stmt->close();
        } else {
            $error_message = "Failed to change password";
        }
        
        $password_stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings — MediFlow Clinic</title>
    <link rel="stylesheet" href="CSS/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .demo-banner {
            background: linear-gradient(135deg, #0047AB 0%, #0066cc 100%);
            color: white;
            padding: 10px 20px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 71, 171, 0.2);
        }
        .demo-banner i {
            margin-right: 10px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0047AB 0%, #0066cc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0, 71, 171, 0.3);
        }
        .user-type-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 10px;
        }
        .user-type-badge.patient {
            background: #28a745;
            color: white;
        }
        .user-type-badge.doctor {
            background: #0047AB;
            color: white;
        }
        .user-type-badge.admin {
            background: #6c757d;
            color: white;
        }
        .security-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 0.9rem;
        }
        .security-warning i {
            color: #ffc107;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Demo Banner -->
    <div class="demo-banner">
        <i class="fas fa-user-md"></i>
        Welcome to MediFlow Clinic - Profile Management
        <small style="opacity: 0.9; display: block; margin-top: 5px;">
            Logged in as: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
            | User Type: <?php echo ucfirst($user['user_type']); ?>
            | User ID: <?php echo $user_id; ?>
        </small>
    </div>

    <!-- Header -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><i class="fas fa-stethoscope"></i> MediFlow Clinic</h1>
            </div>
            <nav class="main-nav">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="my_appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a>
                <a href="booking.php"><i class="fas fa-plus-circle"></i> Book Appointment</a>
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Security Warning (for development only) -->
    <div class="security-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Security Note:</strong> This system is using plain text password storage. For production environments, please implement proper password hashing.
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="message success" id="successMessage">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success_message; ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="message error" id="errorMessage">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error_message; ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="profile-main">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="profile-info">
                    <h2>
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        <span class="user-type-badge <?php echo $user['user_type']; ?>">
                            <?php echo ucfirst($user['user_type']); ?>
                        </span>
                    </h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?></p>
                    <p><i class="fas fa-calendar"></i> Member since: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <!-- Profile Sections -->
            <div class="profile-sections">
                <!-- Personal Information -->
                <section class="profile-section" id="personalInfo">
                    <div class="section-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        <button class="edit-btn" data-section="personalInfo">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    
                    <form method="POST" action="" class="profile-form" id="personalInfoForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                       readonly required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                       readonly required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" 
                                       readonly required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>" 
                                       placeholder="01XXXXXXXXX"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" id="address" name="address" 
                                   value="<?php echo htmlspecialchars($user['address'] ?: ''); ?>" 
                                   placeholder="Your address"
                                   readonly>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_of_birth"><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($user['date_of_birth'] ?: ''); ?>" 
                                       readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="username"><i class="fas fa-user-circle"></i> Username</label>
                                <input type="text" id="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" 
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact_name"><i class="fas fa-user-friends"></i> Emergency Contact Name</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" 
                                       value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?: ''); ?>" 
                                       placeholder="Emergency contact name"
                                       readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="emergency_contact_phone"><i class="fas fa-phone-alt"></i> Emergency Contact Phone</label>
                                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" 
                                       value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?: ''); ?>" 
                                       placeholder="01XXXXXXXXX"
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="form-actions" style="display: none;">
                            <button type="button" class="cancel-btn" data-section="personalInfo">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="save-btn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Change Password -->
                <section class="profile-section" id="passwordSection">
                    <div class="section-header">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                    </div>
                    
                    <form method="POST" action="" class="profile-form" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="current_password" name="current_password" 
                                       placeholder="Enter current password" required>
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" 
                                       placeholder="Enter new password" required>
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                                <span class="strength-text" id="strengthText">Password strength</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm new password" required>
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="save-btn">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Account Security -->
                <section class="profile-section" id="securitySection">
                    <div class="section-header">
                        <h3><i class="fas fa-shield-alt"></i> Account Security</h3>
                    </div>
                    
                    <div class="security-info">
                        <div class="security-item">
                            <i class="fas fa-envelope"></i>
                            <div class="security-details">
                                <h4>Email Verification</h4>
                                <p><?php echo $user['email_verified'] ? 'Verified' : 'Not verified'; ?></p>
                            </div>
                            <?php if (!$user['email_verified']): ?>
                                <button class="verify-btn">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="security-item">
                            <i class="fas fa-clock"></i>
                            <div class="security-details">
                                <h4>Last Login</h4>
                                <p><?php echo date('M d, Y H:i', strtotime($user['updated_at'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="security-item">
                            <i class="fas fa-calendar-alt"></i>
                            <div class="security-details">
                                <h4>Account Created</h4>
                                <p><?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Medical Information (for patients) -->
                <?php if ($user['user_type'] == 'patient'): ?>
                <section class="profile-section" id="medicalSection">
                    <div class="section-header">
                        <h3><i class="fas fa-heartbeat"></i> Medical Information</h3>
                        <button class="edit-btn" data-section="medicalSection">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    
                    <div class="medical-info">
                        <?php
                        // Get patient medical records
                        $medical_sql = "SELECT * FROM medical_records WHERE patient_id = ? ORDER BY record_date DESC LIMIT 1";
                        $medical_stmt = $conn->prepare($medical_sql);
                        $medical_stmt->bind_param("i", $user_id);
                        $medical_stmt->execute();
                        $medical_result = $medical_stmt->get_result();
                        $medical_record = $medical_result->fetch_assoc();
                        $medical_stmt->close();
                        ?>
                        
                        <div class="medical-item">
                            <i class="fas fa-weight"></i>
                            <div class="medical-details">
                                <h4>Height & Weight</h4>
                                <p>
                                    <?php 
                                    if ($medical_record && $medical_record['height'] && $medical_record['weight']) {
                                        echo $medical_record['height'] . ' cm, ' . $medical_record['weight'] . ' kg';
                                    } else {
                                        echo 'Not recorded';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="medical-item">
                            <i class="fas fa-tint"></i>
                            <div class="medical-details">
                                <h4>Blood Type</h4>
                                <p><?php echo $medical_record['blood_type'] ?? 'Not recorded'; ?></p>
                            </div>
                        </div>
                        
                        <div class="medical-item">
                            <i class="fas fa-allergies"></i>
                            <div class="medical-details">
                                <h4>Allergies</h4>
                                <p>
                                    <?php 
                                    if ($medical_record && $medical_record['allergies']) {
                                        $allergies = json_decode($medical_record['allergies'], true);
                                        echo implode(', ', $allergies);
                                    } else {
                                        echo 'None recorded';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="medical-actions">
                        <a href="medical_records.php" class="view-all-btn">
                            <i class="fas fa-file-medical"></i> View All Medical Records
                        </a>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-stethoscope"></i> MediFlow Clinic</h3>
                <p>Providing quality healthcare with modern technology</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <a href="index.php">Home</a>
                <a href="my_appointments.php">My Appointments</a>
                <a href="profile.php">Profile</a>
                <a href="contact.php">Contact Us</a>
            </div>
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-phone"></i> +1-555-HEALTH</p>
                <p><i class="fas fa-envelope"></i> support@mediflow.com</p>
                <p><i class="fas fa-clock"></i> Mon-Fri: 8 AM - 8 PM</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 MediFlow Clinic. All rights reserved.</p>
            <div class="footer-links">
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="help.php">Help Center</a>
            </div>
        </div>
    </footer>

    <script src="JS/profile.js"></script>
</body>
</html>
<?php $conn->close(); ?>