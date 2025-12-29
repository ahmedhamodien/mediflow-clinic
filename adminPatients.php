<?php
// ============================================
// ADMIN PATIENTS MANAGEMENT
// ============================================
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
$status_filter = '';
$patients = [];
$total_patients = 0;

// Handle search and filters
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build query
    $sql = "SELECT u.*, COUNT(DISTINCT a.id) as appointment_count 
            FROM users u 
            LEFT JOIN appointments a ON u.id = a.patient_id 
            WHERE u.user_type = 'patient'";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
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
    $count_sql = "SELECT COUNT(*) as total FROM users WHERE user_type = 'patient'";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_patients = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Get patients
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
            $patients[] = $row;
        }
    }
}

// Handle patient deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_patient'])) {
    $patient_id = intval($_POST['patient_id']);
    
    // Check if patient exists
    $check_sql = "SELECT id FROM users WHERE id = ? AND user_type = 'patient'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Soft delete (deactivate) instead of hard delete
        $update_sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $patient_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Patient deactivated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to deactivate patient.";
        }
        $update_stmt->close();
    }
    $check_stmt->close();
    
    header("Location: adminPatients.php");
    exit();
}

// Handle patient activation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['activate_patient'])) {
    $patient_id = intval($_POST['patient_id']);
    
    $update_sql = "UPDATE users SET is_active = 1 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $patient_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Patient activated successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to activate patient.";
    }
    $update_stmt->close();
    
    header("Location: adminPatients.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Directory - Admin Panel</title>
    
    <!-- Admin Patients CSS -->
    <link rel="stylesheet" href="adminPatients.css">
    
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
        <li><a href="adminPatients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
        <li><a href="adminDoctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
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
            <h1><i class="fas fa-users"></i> Patient Directory</h1>
            <p>Manage and monitor all registered patients</p>
        </div>
        
        <div class="header-right">
            <button class="btn btn-primary" id="addPatientBtn">
                <i class="fas fa-user-plus"></i> Add New Patient
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
                           placeholder="Search patients by name, email, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">Search</button>
                </div>
            </div>
            
            <div class="filter-group">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name_asc">Name (A-Z)</option>
                    <option value="name_desc">Name (Z-A)</option>
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
                <i class="fas fa-users" style="color: #1976d2;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($total_patients); ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;">
                <i class="fas fa-user-check" style="color: #388e3c;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format(array_reduce($patients, function($carry, $patient) {
                    return $carry + ($patient['is_active'] ? 1 : 0);
                }, 0)); ?></h3>
                <p>Active Patients</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3e0;">
                <i class="fas fa-calendar-check" style="color: #f57c00;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format(array_reduce($patients, function($carry, $patient) {
                    return $carry + ($patient['appointment_count'] ?? 0);
                }, 0)); ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fce4ec;">
                <i class="fas fa-user-clock" style="color: #c2185b;"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format(array_reduce($patients, function($carry, $patient) {
                    return $carry + (!$patient['is_active'] ? 1 : 0);
                }, 0)); ?></h3>
                <p>Inactive Patients</p>
            </div>
        </div>
    </div>

    <!-- Patients Table -->
    <div class="table-container">
        <table class="patients-table">
            <thead>
                <tr>
                    <th>
                        <label class="checkbox-container">
                            <input type="checkbox" id="selectAll">
                            <span class="checkmark"></span>
                        </label>
                    </th>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Date of Birth</th>
                    <th>Status</th>
                    <th>Appointments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="9" class="no-data">
                            <i class="fas fa-users-slash"></i>
                            <p>No patients found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <label class="checkbox-container">
                                    <input type="checkbox" class="patient-checkbox" data-id="<?php echo $patient['id']; ?>">
                                    <span class="checkmark"></span>
                                </label>
                            </td>
                            <td>#<?php echo htmlspecialchars($patient['id']); ?></td>
                            <td>
                                <div class="patient-info">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($patient['first_name'] . ' ' . $patient['last_name']); ?>&background=0047AB&color=fff" 
                                         alt="<?php echo htmlspecialchars($patient['first_name']); ?>"
                                         class="patient-avatar">
                                    <div>
                                        <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                                        <small>Joined: <?php echo date('M d, Y', strtotime($patient['created_at'])); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($patient['date_of_birth']): ?>
                                    <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?>
                                    <br><small><?php echo calculateAge($patient['date_of_birth']); ?> years</small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $patient['is_active'] ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo $patient['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="appointment-count">
                                    <?php echo $patient['appointment_count'] ?? 0; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn view-btn" title="View Profile" data-id="<?php echo $patient['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit-btn" title="Edit Patient" data-id="<?php echo $patient['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($patient['is_active']): ?>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to deactivate this patient?');">
                                            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                            <button type="submit" name="delete_patient" class="action-btn delete-btn" title="Deactivate">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                            <button type="submit" name="activate_patient" class="action-btn activate-btn" title="Activate">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="action-btn more-btn" title="More Options">
                                        <i class="fas fa-ellipsis-v"></i>
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

<!-- Add Patient Modal -->
<div class="modal" id="addPatientModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Patient</h3>
            <button class="close-modal">&times;</button>
        </div>
        
        <form method="POST" action="add_patient.php" class="modal-form">
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
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="date_of_birth">
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="2"></textarea>
            </div>
            
            <div class="form-group">
                <label for="emergencyContact">Emergency Contact</label>
                <input type="text" id="emergencyContact" name="emergency_contact_name" placeholder="Contact Name">
                <input type="tel" id="emergencyPhone" name="emergency_contact_phone" placeholder="Phone Number">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline close-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Patient
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Patient Modal -->
<div class="modal" id="viewPatientModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> Patient Details</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body" id="patientDetails">
            <!-- Patient details will be loaded here via AJAX -->
        </div>
    </div>
</div>

<!-- Include JavaScript -->
<script src="adminPatients.js"></script>
</body>
</html>

<?php
// Helper function to calculate age
function calculateAge($birthDate) {
    $birthDate = new DateTime($birthDate);
    $today = new DateTime('today');
    $age = $today->diff($birthDate)->y;
    return $age;
}

?>
