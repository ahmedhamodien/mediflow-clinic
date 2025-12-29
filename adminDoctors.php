<?php

session_start();

// Check if user is admin and logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'database.php';

// Initialize variables
$search = '';
$specialization_filter = '';
$status_filter = '';
$doctors = [];
$total_doctors = 0;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $specialization_filter = isset($_GET['specialization']) ? $_GET['specialization'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build query
    $sql = "SELECT u.*, d.*, 
                   GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as clinics,
                   GROUP_CONCAT(DISTINCT da.day_of_week SEPARATOR ', ') as availability_days,
                   COUNT(DISTINCT a.id) as appointment_count
            FROM users u 
            INNER JOIN doctors d ON u.id = d.user_id
            LEFT JOIN doctor_clinic dc ON d.id = dc.doctor_id
            LEFT JOIN clinics c ON dc.clinic_id = c.id
            LEFT JOIN doctor_availability da ON d.id = da.doctor_id
            LEFT JOIN appointments a ON d.id = a.doctor_id
            WHERE u.user_type = 'doctor'";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR d.specialization LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
        $types .= "sssss";
    }
    
    if (!empty($specialization_filter)) {
        $sql .= " AND d.specialization = ?";
        $params[] = $specialization_filter;
        $types .= "s";
    }
    
    if (!empty($status_filter)) {
        if ($status_filter === 'active') {
            $sql .= " AND u.is_active = 1";
        } elseif ($status_filter === 'inactive') {
            $sql .= " AND u.is_active = 0";
        }
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'doctor'";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_doctors = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Get doctors
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
    }
}

// Get unique specializations for filter
$specializations = [];
$spec_sql = "SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization";
$spec_result = $conn->query($spec_sql);
if ($spec_result) {
    while ($row = $spec_result->fetch_assoc()) {
        $specializations[] = $row['specialization'];
    }
}

// Handle doctor deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_doctor'])) {
    $doctor_id = intval($_POST['doctor_id']);
    
    // Check if doctor exists
    $check_sql = "SELECT d.id, u.id as user_id 
                  FROM doctors d 
                  INNER JOIN users u ON d.user_id = u.id 
                  WHERE d.id = ? AND u.user_type = 'doctor'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $doctor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $doctor_data = $check_result->fetch_assoc();
        $user_id = $doctor_data['user_id'];
        
        // Soft delete (deactivate) instead of hard delete
        $update_sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Doctor deactivated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to deactivate doctor.";
        }
        $update_stmt->close();
    }
    $check_stmt->close();
    
    header("Location: adminDoctors.php");
    exit();
}

// Handle doctor activation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['activate_doctor'])) {
    $doctor_id = intval($_POST['doctor_id']);
    
    // Get user_id from doctor_id
    $get_user_sql = "SELECT user_id FROM doctors WHERE id = ?";
    $get_user_stmt = $conn->prepare($get_user_sql);
    $get_user_stmt->bind_param("i", $doctor_id);
    $get_user_stmt->execute();
    $user_result = $get_user_stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $user_id = $user_data['user_id'];
        
        $update_sql = "UPDATE users SET is_active = 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Doctor activated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to activate doctor.";
        }
        $update_stmt->close();
    }
    $get_user_stmt->close();
    
    header("Location: adminDoctors.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Management - Admin Panel</title>
    
    <!-- Admin Doctors CSS -->
    <link rel="stylesheet" href="adminDoctors.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-hospital-alt"></i> MediFlow Admin</h2>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="adminPatients.php"><i class="fas fa-users"></i> Patients</a></li>
        <li><a href="adminDoctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a></li>
        <li><a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="admin-profile">
            <img src="https://ui-avatars.com/api/?name=Admin+User&background=0047AB&color=fff" alt="Admin">
            <div class="admin-info">
                <span class="admin-name"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                <span class="admin-role">Administrator</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <h1><i class="fas fa-user-md"></i> Doctors Management</h1>
            <p>Manage and monitor all registered doctors and staff</p>
        </div>
        
        <div class="header-right">
            <button class="btn btn-primary" id="addDoctorBtn">
                <i class="fas fa-user-md"></i> Add New Doctor
            </button>
           
        </div>
    </header>

    <!-- Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            <button class="close-btn">&times;</button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            <button class="close-btn">&times;</button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Search doctors by name, specialty, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </div>
            
            <div class="filter-group">
                <select name="specialization" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Specialties</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec); ?>" 
                            <?php echo $specialization_filter === $spec ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <button type="button" class="btn btn-outline" id="clearFilters">
                    <i class="fas fa-filter-circle-xmark"></i> Clear Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;">
                <i class="fas fa-user-md" style="color: #1976d2;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($total_doctors); ?></h3>
                <p>Total Doctors</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;">
                <i class="fas fa-stethoscope" style="color: #388e3c;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count(array_unique(array_column($doctors, 'specialization'))); ?></h3>
                <p>Specialties</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0;">
                <i class="fas fa-calendar-check" style="color: #f57c00;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format(array_reduce($doctors, function($carry, $doctor) {
                    return $carry + ($doctor['appointment_count'] ?? 0);
                }, 0)); ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fce4ec;">
                <i class="fas fa-user-clock" style="color: #c2185b;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format(array_reduce($doctors, function($carry, $doctor) {
                    return $carry + (!$doctor['is_active'] ? 1 : 0);
                }, 0)); ?></h3>
                <p>Inactive Doctors</p>
            </div>
        </div>
    </div>

    <!-- Doctors Table -->
    <div class="table-container">
        <table class="doctors-table">
            <thead>
                <tr>
                    <th>
                        <label class="checkbox-container">
                            <input type="checkbox" id="selectAll">
                            <span class="checkmark"></span>
                        </label>
                    </th>
                    <th>ID</th>
                    <th>Doctor Information</th>
                    <th>Specialization</th>
                    <th>Contact</th>
                    <th>Experience & Fees</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            <i class="fas fa-user-md"></i>
                            <p>No doctors found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr>
                            <td>
                                <label class="checkbox-container">
                                    <input type="checkbox" class="doctor-checkbox" data-id="<?php echo $doctor['id']; ?>">
                                    <span class="checkmark"></span>
                                </label>
                            </td>
                            <td>#<?php echo htmlspecialchars($doctor['id']); ?></td>
                            <td>
                                <div class="doctor-info">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($doctor['first_name'] . ' ' . $doctor['last_name']); ?>&background=0047AB&color=fff" 
                                         alt="<?php echo htmlspecialchars($doctor['first_name']); ?>"
                                         class="doctor-avatar">
                                    <div>
                                        <strong><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong>
                                        <small>
                                            <i class="fas fa-id-card"></i> License: <?php echo htmlspecialchars($doctor['license_number'] ?? 'N/A'); ?>
                                        </small>
                                        <?php if (!empty($doctor['clinics'])): ?>
                                            <small>
                                                <i class="fas fa-clinic-medical"></i> Clinics: <?php echo htmlspecialchars($doctor['clinics']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="specialization-info">
                                    <span class="specialization-badge">
                                        <?php echo htmlspecialchars($doctor['specialization'] ?? 'General'); ?>
                                    </span>
                                    <?php if ($doctor['years_of_experience']): ?>
                                        <small>
                                            <i class="fas fa-calendar-alt"></i> <?php echo $doctor['years_of_experience']; ?> years experience
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($doctor['availability_days'])): ?>
                                        <div>
                                            <i class="fas fa-calendar"></i> 
                                            <?php echo formatDays($doctor['availability_days']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="fee-info">
                                    <div class="fee-amount">
                                        <strong><?php echo htmlspecialchars($doctor['consultation_fee'] ? '$' . number_format($doctor['consultation_fee'], 2) : '$0.00'); ?></strong>
                                        <small>Consultation Fee</small>
                                    </div>
                                    <div class="appointment-count">
                                        <i class="fas fa-calendar-check"></i>
                                        <span><?php echo $doctor['appointment_count'] ?? 0; ?> appts</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <br>
                                <small>Last updated: <?php echo date('M d, Y', strtotime($doctor['updated_at'])); ?></small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view-btn" title="View Profile" data-id="<?php echo $doctor['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit-btn" title="Edit Doctor" data-id="<?php echo $doctor['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($doctor['is_active']): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to deactivate this doctor?');">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="delete_doctor" class="action-btn delete-btn" title="Deactivate">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                            <button type="submit" name="activate_doctor" class="action-btn activate-btn" title="Activate">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="action-btn schedule-btn" title="Manage Schedule" data-id="<?php echo $doctor['id']; ?>">
                                        <i class="fas fa-calendar-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
      
        </div>
    </div>
</main>

<!-- Add Doctor Modal -->
<div class="modal" id="addDoctorModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-md"></i> Add New Doctor</h3>
            <button class="close-modal">&times;</button>
        </div>
        
        <form method="POST" action="add_doctor.php" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name *</label>
                    <input type="text" id="lastName" name="last_name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="01XXXXXXXXX">
                </div>
                <div class="form-group">
                    <label for="license">License Number *</label>
                    <input type="text" id="license" name="license_number" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="specialization">Specialization *</label>
                    <select id="specialization" name="specialization" required>
                        <option value="">Select Specialization</option>
                        <option value="General Physician">General Physician</option>
                        <option value="Cardiologist">Cardiologist</option>
                        <option value="Pediatrician">Pediatrician</option>
                        <option value="Dermatologist">Dermatologist</option>
                        <option value="Neurologist">Neurologist</option>
                        <option value="Orthopedic">Orthopedic</option>
                        <option value="Gynecologist">Gynecologist</option>
                        <option value="Dentist">Dentist</option>
                        <option value="Psychiatrist">Psychiatrist</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="experience">Years of Experience</label>
                    <input type="number" id="experience" name="years_of_experience" min="0" max="50">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="consultationFee">Consultation Fee</label>
                    <div class="input-with-icon">
                        <span class="input-icon">$</span>
                        <input type="number" id="consultationFee" name="consultation_fee" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="clinic">Primary Clinic</label>
                    <select id="clinic" name="primary_clinic_id">
                        <option value="">Select Clinic</option>
                        <option value="1">City Medical Center</option>
                        <option value="2">Westside Clinic</option>
                        <option value="3">Downtown Health Hub</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="bio">Professional Bio</label>
                <textarea id="bio" name="bio" rows="3" placeholder="Brief professional biography..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="password">Temporary Password *</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small>Doctor will be asked to change this on first login</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Doctor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Doctor Modal -->
<div class="modal" id="viewDoctorModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-md"></i> Doctor Details</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body" id="doctorDetails">
            <!-- Doctor details will be loaded here via AJAX -->
        </div>
    </div>
</div>

<!-- Include JavaScript -->
<script src="adminDoctors.js"></script>
</body>
</html>

<?php
// Helper function to format days of week
function formatDays($days_string) {
    if (empty($days_string)) return 'Not Set';
    
    $days_map = [
        '1' => 'Mon',
        '2' => 'Tue',
        '3' => 'Wed',
        '4' => 'Thu',
        '5' => 'Fri',
        '6' => 'Sat',
        '7' => 'Sun'
    ];
    
    $days_array = explode(', ', $days_string);
    $formatted_days = [];
    
    foreach ($days_array as $day) {
        if (isset($days_map[$day])) {
            $formatted_days[] = $days_map[$day];
        }
    }
    
    return !empty($formatted_days) ? implode(', ', $formatted_days) : 'Not Set';
}

?>
