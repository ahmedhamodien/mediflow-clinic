<?php
// ============================================================================
// DOCTOR DASHBOARD - FIXED VERSION ALIGNED WITH DATABASE
// ============================================================================

// Start output buffering
ob_start();

// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
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
    die("Database connection failed. Please try again later.");
}

// Set charset
$conn->set_charset("utf8mb4");

// ============================================================================
// SESSION VALIDATION
// ============================================================================

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Validate user ID
$user_id = (int)$_SESSION['user_id'];
if ($user_id <= 0) {
    header("Location: login.php");
    exit();
}

// Check if user is a doctor
if ($_SESSION['user_type'] !== 'doctor') {
    switch($_SESSION['user_type']) {
        case 'admin':
            header("Location: adminDoctors.php");
            exit();
        case 'patient':
            header("Location: patient_dashboard.php");
            exit();
        default:
            header("Location: login.php");
            exit();
    }
}

// ============================================================================
// DOCTOR VERIFICATION
// ============================================================================

// Get doctor info from database (using your actual database schema)
$user_sql = "SELECT u.id as user_id, u.first_name, u.last_name, u.email, u.phone, 
                    u.profile_image, u.date_of_birth,
                    d.id as doctor_id, d.specialization, d.license_number, 
                    d.consultation_fee, d.years_of_experience, d.bio
             FROM users u 
             JOIN doctors d ON u.id = d.user_id 
             WHERE u.id = ? AND u.user_type = 'doctor'";
             
$user_stmt = $conn->prepare($user_sql);
if (!$user_stmt) {
    die("Database error. Please try again.");
}

$user_stmt->bind_param("i", $user_id);
if (!$user_stmt->execute()) {
    $user_stmt->close();
    die("Database error. Please try again.");
}

$user_result = $user_stmt->get_result();
$doctor = $user_result->fetch_assoc();
$user_stmt->close();

// Initialize error flag
$doctor_not_found = false;
$error_message = "";

// Doctor verification
if (!$doctor) {
    $doctor_not_found = true;
    $error_message = "Your doctor account is not properly configured. Please contact administrator.";
}

// If doctor found, get the doctor_id and fetch dashboard data
if ($doctor && !$doctor_not_found) {
    $doctor_id = $doctor['doctor_id'];
    
    // ============================================================================
    // DASHBOARD DATA FETCHING (ALIGNED WITH DATABASE SCHEMA)
    // ============================================================================
    
    $today = date('Y-m-d');
    
    // 1. Today's appointments count (using status enum from appointments table)
    $today_appointments_sql = "SELECT COUNT(*) as count FROM appointments 
                               WHERE doctor_id = ? 
                               AND appointment_date = ?
                               AND status IN ('scheduled', 'confirmed')";
    $today_stmt = $conn->prepare($today_appointments_sql);
    if ($today_stmt) {
        $today_stmt->bind_param("is", $doctor_id, $today);
        $today_stmt->execute();
        $today_result = $today_stmt->get_result();
        $today_stats = $today_result->fetch_assoc();
        $today_stmt->close();
    } else {
        $today_stats = ['count' => 0];
    }
    
    // 2. Upcoming appointments count (next 7 days)
    $upcoming_sql = "SELECT COUNT(*) as count FROM appointments 
                     WHERE doctor_id = ? 
                     AND appointment_date >= ?
                     AND appointment_date <= DATE_ADD(?, INTERVAL 7 DAY)
                     AND status IN ('scheduled', 'confirmed')";
    $upcoming_stmt = $conn->prepare($upcoming_sql);
    if ($upcoming_stmt) {
        $upcoming_stmt->bind_param("iss", $doctor_id, $today, $today);
        $upcoming_stmt->execute();
        $upcoming_result = $upcoming_stmt->get_result();
        $upcoming_stats = $upcoming_result->fetch_assoc();
        $upcoming_stmt->close();
    } else {
        $upcoming_stats = ['count' => 0];
    }
    
    // 3. Completed appointments count (today)
    $completed_sql = "SELECT COUNT(*) as count FROM appointments 
                      WHERE doctor_id = ? 
                      AND status = 'completed' 
                      AND appointment_date = ?";
    $completed_stmt = $conn->prepare($completed_sql);
    if ($completed_stmt) {
        $completed_stmt->bind_param("is", $doctor_id, $today);
        $completed_stmt->execute();
        $completed_result = $completed_stmt->get_result();
        $completed_stats = $completed_result->fetch_assoc();
        $completed_stmt->close();
    } else {
        $completed_stats = ['count' => 0];
    }
    
    // 4. Pending appointments count (status = 'scheduled' only)
    $pending_sql = "SELECT COUNT(*) as count FROM appointments 
                    WHERE doctor_id = ? 
                    AND status = 'scheduled' 
                    AND appointment_date >= ?";
    $pending_stmt = $conn->prepare($pending_sql);
    if ($pending_stmt) {
        $pending_stmt->bind_param("is", $doctor_id, $today);
        $pending_stmt->execute();
        $pending_result = $pending_stmt->get_result();
        $pending_stats = $pending_result->fetch_assoc();
        $pending_stmt->close();
    } else {
        $pending_stats = ['count' => 0];
    }
    
    // 5. Get today's appointments with all details from database schema
    $todays_appointments_sql = "
        SELECT 
            a.id,
            a.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.phone as patient_phone,
            p.date_of_birth as patient_dob,
            a.appointment_time,
            TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
            a.status,
            a.reason,
            a.symptoms_description,
            a.notes as appointment_notes,
            c.id as clinic_id,
            c.name as clinic_name,
            c.address as clinic_address,
            c.phone as clinic_phone,
            a.created_at,
            a.booked_at
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        JOIN clinics c ON a.clinic_id = c.id
        WHERE a.doctor_id = ? 
        AND a.appointment_date = ?
        AND a.status IN ('scheduled', 'confirmed')
        ORDER BY a.appointment_time ASC
        LIMIT 10
    ";
    $todays_appointments = [];
    $todays_appointments_stmt = $conn->prepare($todays_appointments_sql);
    if ($todays_appointments_stmt) {
        $todays_appointments_stmt->bind_param("is", $doctor_id, $today);
        $todays_appointments_stmt->execute();
        $todays_appointments_result = $todays_appointments_stmt->get_result();
        $todays_appointments = $todays_appointments_result->fetch_all(MYSQLI_ASSOC);
        $todays_appointments_stmt->close();
    }
    
    // 6. Get upcoming appointments (next 7 days)
    $upcoming_list_sql = "
        SELECT 
            a.id,
            a.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            a.appointment_date,
            DATE_FORMAT(a.appointment_date, '%b %d, %Y') as formatted_date,
            a.appointment_time,
            TIME_FORMAT(a.appointment_time, '%h:%i %p') as formatted_time,
            a.status,
            a.reason,
            c.name as clinic_name,
            c.address as clinic_address
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        JOIN clinics c ON a.clinic_id = c.id
        WHERE a.doctor_id = ? 
        AND a.appointment_date >= ?
        AND a.appointment_date <= DATE_ADD(?, INTERVAL 7 DAY)
        AND a.status IN ('scheduled', 'confirmed')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 8
    ";
    $upcoming_appointments = [];
    $upcoming_list_stmt = $conn->prepare($upcoming_list_sql);
    if ($upcoming_list_stmt) {
        $upcoming_list_stmt->bind_param("iss", $doctor_id, $today, $today);
        $upcoming_list_stmt->execute();
        $upcoming_list_result = $upcoming_list_stmt->get_result();
        $upcoming_appointments = $upcoming_list_result->fetch_all(MYSQLI_ASSOC);
        $upcoming_list_stmt->close();
    }
    
    // 7. Get doctor's schedule from doctor_availability table
    $schedule_sql = "
        SELECT 
            da.id,
            da.doctor_id,
            da.clinic_id,
            c.name as clinic_name,
            c.address as clinic_address,
            da.day_of_week,
            da.start_time,
            da.end_time,
            TIME_FORMAT(da.start_time, '%h:%i %p') as formatted_start,
            TIME_FORMAT(da.end_time, '%h:%i %p') as formatted_end,
            da.slot_duration,
            da.is_recurring,
            da.max_appointments_per_day,
            da.break_start,
            da.break_end,
            CASE da.day_of_week 
                WHEN 1 THEN 'Monday'
                WHEN 2 THEN 'Tuesday'
                WHEN 3 THEN 'Wednesday'
                WHEN 4 THEN 'Thursday'
                WHEN 5 THEN 'Friday'
                WHEN 6 THEN 'Saturday'
                WHEN 7 THEN 'Sunday'
            END as day_name
        FROM doctor_availability da
        JOIN clinics c ON da.clinic_id = c.id
        WHERE da.doctor_id = ?
        AND (da.valid_from IS NULL OR da.valid_from <= CURDATE())
        AND (da.valid_until IS NULL OR da.valid_until >= CURDATE())
        ORDER BY da.day_of_week, da.start_time
    ";
    $doctor_schedule = [];
    $schedule_stmt = $conn->prepare($schedule_sql);
    if ($schedule_stmt) {
        $schedule_stmt->bind_param("i", $doctor_id);
        $schedule_stmt->execute();
        $schedule_result = $schedule_stmt->get_result();
        $doctor_schedule = $schedule_result->fetch_all(MYSQLI_ASSOC);
        $schedule_stmt->close();
    }
    
    // 8. Get recent patients with medical history
    $recent_patients_sql = "
        SELECT DISTINCT
            p.id as patient_id,
            p.first_name,
            p.last_name,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.email,
            p.phone,
            p.date_of_birth,
            MAX(a.appointment_date) as last_visit,
            COUNT(a.id) as total_visits,
            (
                SELECT COUNT(*) 
                FROM medical_records mr 
                WHERE mr.patient_id = p.id 
                AND mr.doctor_id = ?
            ) as medical_records_count
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        WHERE a.doctor_id = ?
        GROUP BY p.id
        ORDER BY last_visit DESC
        LIMIT 5
    ";
    $recent_patients = [];
    $recent_patients_stmt = $conn->prepare($recent_patients_sql);
    if ($recent_patients_stmt) {
        $recent_patients_stmt->bind_param("ii", $doctor_id, $doctor_id);
        $recent_patients_stmt->execute();
        $recent_patients_result = $recent_patients_stmt->get_result();
        $recent_patients = $recent_patients_result->fetch_all(MYSQLI_ASSOC);
        $recent_patients_stmt->close();
    }
    
    // 9. Get weekly appointment statistics
    $weekly_data_sql = "
        SELECT 
            DAYNAME(a.appointment_date) as day_name,
            DAYOFWEEK(a.appointment_date) as day_number,
            COUNT(*) as appointment_count,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM appointments a
        WHERE a.doctor_id = ?
        AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DAYNAME(a.appointment_date), DAYOFWEEK(a.appointment_date)
        ORDER BY day_number
    ";
    $weekly_chart_data = [];
    $weekly_data_stmt = $conn->prepare($weekly_data_sql);
    if ($weekly_data_stmt) {
        $weekly_data_stmt->bind_param("i", $doctor_id);
        $weekly_data_stmt->execute();
        $weekly_data_result = $weekly_data_stmt->get_result();
        $weekly_chart_data = $weekly_data_result->fetch_all(MYSQLI_ASSOC);
        $weekly_data_stmt->close();
    }
    
    // 10. Get doctor's clinics with fees from doctor_clinic table
    $clinics_sql = "
        SELECT 
            dc.doctor_id,
            dc.clinic_id,
            dc.is_primary,
            dc.consultation_fee_at_clinic,
            c.name as clinic_name,
            c.address,
            c.phone,
            c.email,
            c.website
        FROM doctor_clinic dc
        JOIN clinics c ON dc.clinic_id = c.id
        WHERE dc.doctor_id = ?
        ORDER BY dc.is_primary DESC
    ";
    $doctor_clinics = [];
    $clinics_stmt = $conn->prepare($clinics_sql);
    if ($clinics_stmt) {
        $clinics_stmt->bind_param("i", $doctor_id);
        $clinics_stmt->execute();
        $clinics_result = $clinics_stmt->get_result();
        $doctor_clinics = $clinics_result->fetch_all(MYSQLI_ASSOC);
        $clinics_stmt->close();
    }
    
    // 11. Get notifications for doctor (from notifications table)
    $notifications_sql = "
        SELECT 
            id,
            title,
            message,
            notification_type,
            appointment_id,
            status,
            channel,
            created_at
        FROM notifications
        WHERE user_id = ?
        AND status = 'pending'
        ORDER BY scheduled_time ASC
        LIMIT 5
    ";
    $pending_notifications = [];
    $notifications_stmt = $conn->prepare($notifications_sql);
    if ($notifications_stmt) {
        $notifications_stmt->bind_param("i", $user_id);
        $notifications_stmt->execute();
        $notifications_result = $notifications_stmt->get_result();
        $pending_notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);
        $notifications_stmt->close();
    }
    
    // Prepare chart data
    $chart_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $chart_data = array_fill(0, 7, 0);
    $completed_data = array_fill(0, 7, 0);
    
    foreach ($weekly_chart_data as $data) {
        $day_index = ($data['day_number'] - 1) % 7;
        $chart_data[$day_index] = (int)$data['appointment_count'];
        $completed_data[$day_index] = (int)$data['completed_count'];
    }
}

// Close database connection
$conn->close();

// DEVELOPMENT ONLY - Debug flag
$show_debug = false; // Set to false in production
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard — Clinic Management System</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Status badge colors based on appointment status */
        .status-scheduled { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-no_show { background: #e2e3e5; color: #383d41; }
        
        /* Clinic badge */
        .clinic-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin: 2px 0;
        }
        
        /* Patient age calculation */
        .patient-age {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Clinic System</h2>
                <div class="doctor-info">
                    <div class="doctor-avatar">
                        <?php if ($doctor && !$doctor_not_found && !empty($doctor['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($doctor['profile_image']); ?>" alt="Doctor Avatar">
                        <?php else: ?>
                            <i class="fas fa-user-md"></i>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-details">
                        <?php if ($doctor && !$doctor_not_found): ?>
                        <strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong>
                        <small><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                        <small>License: <?php echo htmlspecialchars($doctor['license_number']); ?></small>
                        <?php else: ?>
                        <strong>Doctor Account</strong>
                        <small>Profile not configured</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="doctor_dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <?php if ($doctor && !$doctor_not_found): ?>
              
               
                <a href="patient_records.php">
                    <i class="fas fa-file-medical"></i> Medical Records
                </a>
                
                
                <?php endif; ?>
                <div class="divider"></div>
                <a href="profile.php">
                    <i class="fas fa-user-cog"></i> Profile
                </a>
                
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <small><i class="fas fa-calendar-day"></i> <?php echo date('F j, Y'); ?></small>
                <small><i class="fas fa-clock"></i> <?php echo date('h:i A'); ?></small>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Doctor Dashboard</h1>
                </div>
                <div class="header-right">
                    <?php if ($doctor && !$doctor_not_found): ?>
                    <!-- Notifications with count -->
                   
                    <?php endif; ?>
                    <div class="user-menu">
                        <?php if ($doctor && !$doctor_not_found): ?>
                        <span>Dr. <?php echo htmlspecialchars($doctor['first_name']); ?></span>
                        <i class="fas fa-user-md"></i>
                        <?php else: ?>
                        <span>Doctor Account</span>
                        <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if ($doctor && !$doctor_not_found): ?>
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="banner-content">
                    <div>
                        <h2><i class="fas fa-heartbeat"></i> Welcome, Dr. <?php echo htmlspecialchars($doctor['last_name']); ?>!</h2>
                        <p><?php echo htmlspecialchars($doctor['specialization']); ?> • <?php echo $doctor['years_of_experience']; ?> years experience</p>
                    </div>
                    <div class="banner-stats">
                        <div class="stat">
                            <i class="fas fa-calendar-day"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($today_stats['count'] ?? 0); ?></strong>
                                <small>Today's Appointments</small>
                            </div>
                        </div>
                        <div class="stat">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($pending_stats['count'] ?? 0); ?></strong>
                                <small>Pending</small>
                            </div>
                        </div>
                        <div class="stat">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($completed_stats['count'] ?? 0); ?></strong>
                                <small>Completed Today</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #4e73df;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Today's Appointments</h3>
                        <p class="stat-number"><?php echo htmlspecialchars($today_stats['count'] ?? 0); ?></p>
                        <small>Scheduled for today</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #1cc88a;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Upcoming Appointments</h3>
                        <p class="stat-number"><?php echo htmlspecialchars($upcoming_stats['count'] ?? 0); ?></p>
                        <small>Next 7 days</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #36b9cc;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Completed Today</h3>
                        <p class="stat-number"><?php echo htmlspecialchars($completed_stats['count'] ?? 0); ?></p>
                        <small>Consultations completed</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #f6c23e;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Recent Patients</h3>
                        <p class="stat-number"><?php echo count($recent_patients); ?></p>
                        <small>Last 5 patients</small>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Left Column -->
                <div class="content-left">
                    <!-- Today's Appointments -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3><i class="fas fa-calendar-day"></i> Today's Appointments</h3>
                            <span class="badge"><?php echo htmlspecialchars($today_stats['count'] ?? 0); ?></span>
                        </div>
                        <div class="appointment-list">
                            <?php if (empty($todays_appointments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No appointments scheduled for today</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($todays_appointments as $appointment): 
                                    // Calculate patient age
                                    $patient_age = '';
                                    if (!empty($appointment['patient_dob'])) {
                                        $birthDate = new DateTime($appointment['patient_dob']);
                                        $today = new DateTime('today');
                                        $age = $birthDate->diff($today)->y;
                                        $patient_age = $age . ' years';
                                    }
                                ?>
                                <div class="appointment-item">
                                    <div class="appointment-time">
                                        <strong><?php echo htmlspecialchars($appointment['formatted_time']); ?></strong>
                                    </div>
                                    <div class="appointment-details">
                                        <div class="patient-info">
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                            <?php if ($patient_age): ?>
                                                <span class="patient-age"><?php echo htmlspecialchars($patient_age); ?></span>
                                            <?php endif; ?>
                                            <span class="clinic-badge"><?php echo htmlspecialchars($appointment['clinic_name']); ?></span>
                                        </div>
                                        <div class="appointment-reason">
                                            <small><?php echo htmlspecialchars($appointment['reason'] ?? 'No reason specified'); ?></small>
                                            <?php if (!empty($appointment['symptoms_description'])): ?>
                                                <small class="text-muted">Symptoms: <?php echo htmlspecialchars(substr($appointment['symptoms_description'], 0, 50)); ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="appointment-actions">
                                        <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                        </span>
                                        <a href="appointment_details.php?id=<?php echo htmlspecialchars($appointment['id']); ?>" 
                                           class="btn-action" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="table-footer">
                            <a href="appointments.php?date=<?php echo urlencode($today); ?>">
                                <i class="fas fa-calendar-alt"></i> View All Appointments
                            </a>
                        </div>
                    </div>

                    <!-- Weekly Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-line"></i> Weekly Appointments Trend</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="content-right">
                    <!-- Upcoming Appointments -->
                    <div class="table-card">
                        <div class="table-header">
                            <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                            <button class="refresh-btn" id="refreshUpcoming">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="upcoming-list">
                            <?php if (empty($upcoming_appointments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-plus"></i>
                                    <p>No upcoming appointments</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-date">
                                        <strong><?php echo htmlspecialchars($appointment['formatted_date']); ?></strong>
                                        <small><?php echo htmlspecialchars($appointment['formatted_time']); ?></small>
                                    </div>
                                    <div class="upcoming-details">
                                        <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($appointment['clinic_name']); ?></small>
                                        <div class="appointment-reason">
                                            <small><?php echo htmlspecialchars(substr($appointment['reason'], 0, 50)); ?>...</small>
                                        </div>
                                    </div>
                                    <div class="upcoming-status">
                                        <span class="status-badge status-<?php echo htmlspecialchars($appointment['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Doctor's Schedule -->
                    <div class="table-card">
                        <h3><i class="fas fa-clock"></i> Weekly Schedule</h3>
                        <div class="schedule-list">
                            <?php if (empty($doctor_schedule)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No schedule set</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                $schedule_by_day = [];
                                foreach ($doctor_schedule as $slot) {
                                    $schedule_by_day[$slot['day_name']][] = $slot;
                                }
                                ksort($schedule_by_day); // Sort by day name
                                ?>
                                <?php foreach ($schedule_by_day as $day => $slots): ?>
                                <div class="schedule-day">
                                    <div class="day-header">
                                        <strong><?php echo htmlspecialchars($day); ?></strong>
                                    </div>
                                    <div class="day-slots">
                                        <?php foreach ($slots as $slot): ?>
                                        <div class="time-slot">
                                            <span><?php echo htmlspecialchars($slot['clinic_name']); ?></span>
                                            <small><?php echo htmlspecialchars($slot['formatted_start']); ?> - <?php echo htmlspecialchars($slot['formatted_end']); ?></small>
                                            <?php if ($slot['slot_duration']): ?>
                                                <small class="text-muted">Slot: <?php echo $slot['slot_duration']; ?> min</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                    </div>

                    <!-- Recent Patients -->
                    <div class="table-card">
                        <h3><i class="fas fa-user-injured"></i> Recent Patients</h3>
                        <div class="patients-list">
                            <?php if (empty($recent_patients)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    <p>No patients yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_patients as $patient): 
                                    // Calculate age
                                    $patient_age = '';
                                    if (!empty($patient['date_of_birth'])) {
                                        $birthDate = new DateTime($patient['date_of_birth']);
                                        $today = new DateTime('today');
                                        $age = $birthDate->diff($today)->y;
                                        $patient_age = $age . ' years';
                                    }
                                ?>
                                <div class="patient-item">
                                    <div class="patient-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="patient-details">
                                        <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                        <?php if ($patient_age): ?>
                                            <small><?php echo htmlspecialchars($patient_age); ?></small>
                                        <?php endif; ?>
                                        <small>Last visit: <?php echo htmlspecialchars(date('M d, Y', strtotime($patient['last_visit']))); ?></small>
                                        <small>Visits: <?php echo htmlspecialchars($patient['total_visits']); ?> • Records: <?php echo htmlspecialchars($patient['medical_records_count']); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Message for invalid doctor accounts -->
            <div class="empty-state">
                <i class="fas fa-user-md"></i>
                <h3>Doctor Account Not Configured</h3>
                <p>Your user account is registered as a doctor, but your doctor profile is not set up in the database.</p>
                <p>Please contact the clinic administrator to complete your profile setup.</p>
                <a href="logout.php" class="btn btn-primary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <footer class="dashboard-footer">
                <p><i class="fas fa-hospital-alt"></i> Clinic Management System v1.0</p>
                <div class="footer-links">
                    <small>Session: <?php echo htmlspecialchars(session_id()); ?></small>
                    <small>•</small>
                    <small>Last sync: <?php echo htmlspecialchars(date('h:i A')); ?></small>
                </div>
            </footer>
        </main>
    </div>

    <script>
        <?php if ($doctor && !$doctor_not_found && isset($chart_data)): ?>
        // Weekly Chart Data
        const chartData = {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Total Appointments',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Completed',
                    data: <?php echo json_encode($completed_data); ?>,
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }
            ]
        };

        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('weeklyChart');
            if (ctx) {
                const weeklyChart = new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Refresh upcoming appointments
            const refreshBtn = document.getElementById('refreshUpcoming');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    this.classList.add('spinning');
                    setTimeout(() => {
                        this.classList.remove('spinning');
                        location.reload();
                    }, 1000);
                });
            }

            // Menu toggle
            document.getElementById('menuToggle')?.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('collapsed');
            });

            // Notification bell click
            document.querySelector('.notification-bell')?.addEventListener('click', function() {
                alert('Notifications feature coming soon!');
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php ob_end_flush(); ?>