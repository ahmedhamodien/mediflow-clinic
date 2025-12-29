<?php
session_start();

// Database connection configuration
define('DB_HOST', 'interchange.proxy.rlwy.net');
define('DB_USER', 'root');
define('DB_PASS', 'tfjmUwdPwmljUBeGyqkIXukwLdJDYnNK');
define('DB_NAME', 'railway');


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
    $_SESSION['user_id'] = 7;
    $_SESSION['user_type'] = 'patient';
    $_SESSION['user_name'] = 'John Doe';
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_sql = "SELECT first_name, last_name, email, phone FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// If user not found, use session data
if (!$user) {
    $user = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@email.com',
        'phone' => '+1-555-1001'
    ];
}

// Handle appointment actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointment_id = intval($_POST['appointment_id']);
    
    switch ($action) {
        case 'cancel':
            $cancellation_reason = $_POST['cancellation_reason'] ?? 'No reason provided';
            
            $cancel_sql = "UPDATE appointments SET 
                          status = 'cancelled', 
                          cancellation_reason = ?, 
                          cancellation_time = NOW(),
                          updated_at = NOW()
                          WHERE id = ? AND patient_id = ?";
            $cancel_stmt = $conn->prepare($cancel_sql);
            $cancel_stmt->bind_param("sii", $cancellation_reason, $appointment_id, $user_id);
            
            if ($cancel_stmt->execute()) {
                $_SESSION['success_message'] = "Appointment cancelled successfully.";
                
                // Add to audit log
                $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values) 
                              VALUES (?, ?, 'appointments', ?, ?)";
                $new_values = json_encode(['status' => 'cancelled', 'cancellation_reason' => $cancellation_reason]);
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bind_param("issi", $user_id, $action, $appointment_id, $new_values);
                $audit_stmt->execute();
                $audit_stmt->close();
            } else {
                $_SESSION['error_message'] = "Failed to cancel appointment: " . $conn->error;
            }
            $cancel_stmt->close();
            break;
            
        case 'reschedule':
            // Store appointment ID for rescheduling
            $_SESSION['reschedule_appointment_id'] = $appointment_id;
            $_SESSION['success_message'] = "Please select new date and time for rescheduling.";
            header("Location: booking.php?reschedule=" . $appointment_id);
            exit();
            break;
            
        case 'confirm':
            $confirm_sql = "UPDATE appointments SET 
                           status = 'confirmed',
                           updated_at = NOW()
                           WHERE id = ? AND patient_id = ? AND status = 'scheduled'";
            $confirm_stmt = $conn->prepare($confirm_sql);
            $confirm_stmt->bind_param("ii", $appointment_id, $user_id);
            
            if ($confirm_stmt->execute()) {
                $_SESSION['success_message'] = "Appointment confirmed successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to confirm appointment.";
            }
            $confirm_stmt->close();
            break;
    }
    
    // Refresh page
    header("Location: my_appointments.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Handle delete action (only for cancelled appointments)
if (isset($_GET['delete'])) {
    $appointment_id = intval($_GET['delete']);
    
    // Check if appointment exists and belongs to user
    $check_sql = "SELECT status FROM appointments WHERE id = ? AND patient_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $appointment_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $appointment = $check_result->fetch_assoc();
        
        // Only allow deletion of cancelled appointments
        if ($appointment['status'] === 'cancelled') {
            $delete_sql = "DELETE FROM appointments WHERE id = ? AND patient_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $appointment_id, $user_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Cancelled appointment removed successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to remove appointment.";
            }
            $delete_stmt->close();
        } else {
            $_SESSION['error_message'] = "Only cancelled appointments can be removed.";
        }
    }
    
    $check_stmt->close();
    
    header("Location: my_appointments.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Get appointments with filters
$status_filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// Base SQL query
$appointments_sql = "
    SELECT 
        a.*,
        d.specialization,
        d.consultation_fee,
        c.name as clinic_name,
        c.address as clinic_address,
        c.phone as clinic_phone,
        u.phone as doctor_phone,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        TIMESTAMP(a.appointment_date, a.appointment_time) as appointment_datetime
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN clinics c ON a.clinic_id = c.id
    WHERE a.patient_id = ?
";

// Apply filters
$params = [$user_id];
$param_types = "i";

switch ($status_filter) {
    case 'past':
        $appointments_sql .= " AND (a.appointment_date < ? OR a.status = 'completed')";
        $params[] = $today;
        $param_types .= "s";
        $appointments_sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        break;
        
    case 'cancelled':
        $appointments_sql .= " AND a.status = 'cancelled'";
        $appointments_sql .= " ORDER BY a.cancellation_time DESC";
        break;
        
    case 'completed':
        $appointments_sql .= " AND a.status = 'completed'";
        $appointments_sql .= " ORDER BY a.appointment_date DESC";
        break;
        
    case 'scheduled':
        $appointments_sql .= " AND a.status = 'scheduled'";
        $appointments_sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        break;
        
    case 'confirmed':
        $appointments_sql .= " AND a.status = 'confirmed'";
        $appointments_sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        break;
        
    case 'upcoming':
    default:
        $appointments_sql .= " AND a.appointment_date >= ? AND a.status IN ('scheduled', 'confirmed')";
        $params[] = $today;
        $param_types .= "s";
        $appointments_sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        break;
}

// Prepare and execute
$stmt = $conn->prepare($appointments_sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$appointments_result = $stmt->get_result();
$appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Insert sample appointments if none exist for this user
if (empty($appointments) && $user_id == 7) {
    $sample_appointments = [
        [
            'doctor_id' => 1,
            'clinic_id' => 1,
            'appointment_date' => date('Y-m-d', strtotime('+2 days')),
            'appointment_time' => '10:00:00',
            'status' => 'confirmed',
            'reason' => 'Annual physical examination',
            'symptoms_description' => 'Routine checkup',
            'cancellation_reason' => null
        ],
        [
            'doctor_id' => 2,
            'clinic_id' => 1,
            'appointment_date' => date('Y-m-d', strtotime('+5 days')),
            'appointment_time' => '14:30:00',
            'status' => 'scheduled',
            'reason' => 'Cardiology consultation',
            'symptoms_description' => 'Chest pain and shortness of breath',
            'cancellation_reason' => null
        ],
        [
            'doctor_id' => 3,
            'clinic_id' => 1,
            'appointment_date' => date('Y-m-d', strtotime('-3 days')),
            'appointment_time' => '11:15:00',
            'status' => 'completed',
            'reason' => 'Pediatric checkup',
            'symptoms_description' => 'Child vaccination',
            'cancellation_reason' => null
        ],
        [
            'doctor_id' => 4,
            'clinic_id' => 3,
            'appointment_date' => date('Y-m-d', strtotime('-10 days')),
            'appointment_time' => '15:45:00',
            'status' => 'cancelled',
            'reason' => 'Dermatology consultation',
            'symptoms_description' => 'Skin rash',
            'cancellation_reason' => 'Schedule conflict'
        ]
    ];
    
    foreach ($sample_appointments as $appointment) {
        $insert_sql = "INSERT INTO appointments 
                      (patient_id, doctor_id, clinic_id, appointment_date, appointment_time, 
                       status, reason, symptoms_description, cancellation_reason, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "iiissssss",
            $user_id,
            $appointment['doctor_id'],
            $appointment['clinic_id'],
            $appointment['appointment_date'],
            $appointment['appointment_time'],
            $appointment['status'],
            $appointment['reason'],
            $appointment['symptoms_description'],
            $appointment['cancellation_reason']
        );
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    // Refresh to show new appointments
    header("Location: my_appointments.php");
    exit();
}

// Get accurate appointment statistics directly from database
$stats_sql = "
    SELECT 
        status,
        COUNT(*) as count
    FROM appointments 
    WHERE patient_id = ?
    GROUP BY status
    UNION ALL
    SELECT 
        'upcoming' as status,
        COUNT(*) as count
    FROM appointments 
    WHERE patient_id = ? 
    AND appointment_date >= CURDATE() 
    AND status IN ('scheduled', 'confirmed')
    UNION ALL
    SELECT 
        'past' as status,
        COUNT(*) as count
    FROM appointments 
    WHERE patient_id = ? 
    AND (appointment_date < CURDATE() OR status = 'completed')
    UNION ALL
    SELECT 
        'today' as status,
        COUNT(*) as count
    FROM appointments 
    WHERE patient_id = ? 
    AND appointment_date = CURDATE() 
    AND status IN ('scheduled', 'confirmed')
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();

// Initialize all stats to 0
$all_stats = [
    'scheduled' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'upcoming' => 0,
    'past' => 0,
    'today' => 0
];

while ($row = $stats_result->fetch_assoc()) {
    $all_stats[$row['status']] = $row['count'];
}
$stats_stmt->close();

// Calculate upcoming count (scheduled + confirmed for future dates)
$upcoming_sql = "SELECT COUNT(*) as count FROM appointments 
                 WHERE patient_id = ? 
                 AND appointment_date >= CURDATE() 
                 AND status IN ('scheduled', 'confirmed')";
$upcoming_stmt = $conn->prepare($upcoming_sql);
$upcoming_stmt->bind_param("i", $user_id);
$upcoming_stmt->execute();
$upcoming_result = $upcoming_stmt->get_result();
$upcoming_row = $upcoming_result->fetch_assoc();
$all_stats['upcoming'] = $upcoming_row['count'] ?? 0;
$upcoming_stmt->close();

// Calculate past appointments
$past_sql = "SELECT COUNT(*) as count FROM appointments 
             WHERE patient_id = ? 
             AND (appointment_date < CURDATE() OR status = 'completed')";
$past_stmt = $conn->prepare($past_sql);
$past_stmt->bind_param("i", $user_id);
$past_stmt->execute();
$past_result = $past_stmt->get_result();
$past_row = $past_result->fetch_assoc();
$all_stats['past'] = $past_row['count'] ?? 0;
$past_stmt->close();

// Today's appointments
$today_sql = "SELECT COUNT(*) as count FROM appointments 
              WHERE patient_id = ? 
              AND appointment_date = CURDATE() 
              AND status IN ('scheduled', 'confirmed')";
$today_stmt = $conn->prepare($today_sql);
$today_stmt->bind_param("i", $user_id);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_row = $today_result->fetch_assoc();
$all_stats['today'] = $today_row['count'] ?? 0;
$today_stmt->close();

// For completed tab - only show completed status
$completed_sql = "SELECT COUNT(*) as count FROM appointments 
                  WHERE patient_id = ? AND status = 'completed'";
$completed_stmt = $conn->prepare($completed_sql);
$completed_stmt->bind_param("i", $user_id);
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();
$completed_row = $completed_result->fetch_assoc();
$all_stats['completed'] = $completed_row['count'] ?? 0;
$completed_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments — MediFlow Clinic</title>
    <link rel="stylesheet" href="my_appointments.css">
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
        .time-indicator {
            display: inline-block;
            margin-left: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .time-indicator.soon {
            background: #dc3545;
            color: white;
        }
        .time-indicator.today {
            background: #ffc107;
            color: #212529;
        }
        .time-indicator.tomorrow {
            background: #28a745;
            color: white;
        }
        .appointment-notes {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        .fee-badge {
            background: #6f42c1;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        .action-buttons-small {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        /* Appointments Grid in Modal */
        .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .appointment-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
            transition: transform 0.2s;
        }
        
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .status-badge {
            padding: 3px 10px;
            border-radius: 15px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .appointment-body p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .appointment-body i {
            width: 20px;
            color: #666;
        }
        
        .appointment-actions {
            margin-top: 10px;
            text-align: right;
        }
        
        .btn-small {
            padding: 5px 10px;
            background-color: #0047AB;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-small:hover {
            background-color: #003380;
        }
        
        /* Search input focus effect */
        #searchInput:focus {
            outline: 2px solid #0047AB;
            cursor: pointer;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }
        
        .error {
            text-align: center;
            padding: 20px;
            color: #dc3545;
        }
        
        /* Active tab styling */
        .tab.active {
            background-color: #0047AB;
            color: white;
            border-color: #0047AB;
        }
        
        /* Stats card colors */
        .stat-card.today {
            border-left: 4px solid #17a2b8;
        }
        
        .stat-card.upcoming {
            border-left: 4px solid #28a745;
        }
        
        .stat-card.past {
            border-left: 4px solid #6c757d;
        }
        
        .stat-card.cancelled {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <!-- Demo Banner -->
    <div class="demo-banner">
        <i class="fas fa-user-md"></i>
        Welcome to MediFlow Clinic - Appointments Management System
        <small style="opacity: 0.9; display: block; margin-top: 5px;">
            Logged in as: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
            | Patient ID: <?php echo $user_id; ?>
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
                <a href="my_appointments.php" class="active"><i class="fas fa-calendar-check"></i> My Appointments</a>
                <a href="booking.php"><i class="fas fa-plus-circle"></i> Book Appointment</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success" id="successMessage">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error" id="errorMessage">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Main Section -->
    <section class="appointments-container">
        <div class="welcome-section">
            <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
            <p>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! Manage your medical appointments here.</p>
            
           
        </div>
        
        <!-- Filter Tabs -->
        <div class="tabs">
            <a href="?filter=upcoming" class="tab <?php echo $status_filter == 'upcoming' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Upcoming (<?php echo $all_stats['upcoming']; ?>)
            </a>
            <a href="?filter=completed" class="tab <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Completed (<?php echo $all_stats['completed']; ?>)
            </a>
            <a href="?filter=cancelled" class="tab <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Cancelled (<?php echo $all_stats['cancelled']; ?>)
            </a>
            <a href="?filter=scheduled" class="tab <?php echo $status_filter == 'scheduled' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> Scheduled (<?php echo $all_stats['scheduled']; ?>)
            </a>
            <a href="?filter=confirmed" class="tab <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Confirmed (<?php echo $all_stats['confirmed']; ?>)
            </a>
        </div>

        <!-- Search and Sort -->
        <div class="search-sort-container">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" 
                       placeholder="Search by doctor, clinic, or reason..."
                       onclick="showAllAppointments()">
            </div>
            <div class="sort-options">
                <select id="sortSelect" onchange="sortTable(this.value)">
                    <option value="date_asc">Sort by: Date (Ascending)</option>
                    <option value="date_desc">Sort by: Date (Descending)</option>
                    <option value="doctor_asc">Sort by: Doctor (A-Z)</option>
                    <option value="doctor_desc">Sort by: Doctor (Z-A)</option>
                    <option value="status">Sort by: Status</option>
                </select>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="table-container">
            <?php if (empty($appointments)): ?>
                <div class="no-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No appointments found</h3>
                    <p>You don't have any <?php echo $status_filter; ?> appointments.</p>
                    <a href="booking.php" class="book-btn">Book Your First Appointment</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="appointmentsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user-md"></i> Doctor & Clinic</th>
                                <th><i class="fas fa-calendar"></i> Date & Time</th>
                                <th><i class="fas fa-info-circle"></i> Details</th>
                                <th><i class="fas fa-tag"></i> Fee & Status</th>
                                <th><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <?php
                                // Format date and time
                                $appointment_date = date('M d, Y', strtotime($appointment['appointment_date']));
                                $appointment_time = date('h:i A', strtotime($appointment['appointment_time']));
                                $appointment_datetime = strtotime($appointment['appointment_datetime']);
                                $now = time();
                                
                                // Time status
                                $time_diff = $appointment_datetime - $now;
                                $hours_diff = floor($time_diff / 3600);
                                $days_diff = floor($time_diff / (3600 * 24));
                                
                                $time_indicator = '';
                                if ($appointment['status'] == 'confirmed' || $appointment['status'] == 'scheduled') {
                                    if ($hours_diff <= 2 && $hours_diff > 0) {
                                        $time_indicator = '<span class="time-indicator soon">Soon (' . ceil($hours_diff * 60) . ' min)</span>';
                                    } elseif ($days_diff == 0 && $hours_diff > 0) {
                                        $time_indicator = '<span class="time-indicator today">Today</span>';
                                    } elseif ($days_diff == 1) {
                                        $time_indicator = '<span class="time-indicator tomorrow">Tomorrow</span>';
                                    }
                                }
                                
                                // Status class and icon
                                $status_class = strtolower($appointment['status']);
                                $status_icon = '';
                                $status_text = ucfirst($appointment['status']);
                                
                                switch($appointment['status']) {
                                    case 'confirmed':
                                        $status_icon = 'fa-check-circle';
                                        $status_color = '#28a745';
                                        break;
                                    case 'scheduled':
                                        $status_icon = 'fa-clock';
                                        $status_color = '#ffc107';
                                        break;
                                    case 'completed':
                                        $status_icon = 'fa-check';
                                        $status_color = '#17a2b8';
                                        break;
                                    case 'cancelled':
                                        $status_icon = 'fa-times-circle';
                                        $status_color = '#dc3545';
                                        break;
                                    default:
                                        $status_icon = 'fa-info-circle';
                                        $status_color = '#6c757d';
                                }
                                
                                // Consultation fee
                                $fee = number_format($appointment['consultation_fee'] ?? 0, 2);
                                $fee_badge = $fee > 0 ? '<span class="fee-badge">$' . $fee . '</span>' : '';
                                
                                // Appointment notes
                                $notes = !empty($appointment['reason']) ? $appointment['reason'] : 
                                        (!empty($appointment['symptoms_description']) ? $appointment['symptoms_description'] : 'No notes');
                                
                                // Is appointment editable?
                                $is_upcoming = $appointment_datetime > $now && 
                                             in_array($appointment['status'], ['scheduled', 'confirmed']);
                                $is_cancellable = $is_upcoming;
                                $is_confirmable = $appointment['status'] == 'scheduled' && $is_upcoming;
                                ?>
                                <tr data-status="<?php echo $appointment['status']; ?>"
                                    data-date="<?php echo $appointment['appointment_date']; ?>"
                                    data-time="<?php echo $appointment['appointment_time']; ?>"
                                    data-doctor="<?php echo htmlspecialchars($appointment['doctor_name']); ?>">
                                    <td>
                                        <div class="doctor-clinic-info">
                                            <strong><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></strong>
                                            <div class="clinic-details">
                                                <i class="fas fa-hospital"></i> <?php echo htmlspecialchars($appointment['clinic_name']); ?>
                                                <?php if (!empty($appointment['clinic_phone'])): ?>
                                                    <br><small><i class="fas fa-phone"></i> <?php echo $appointment['clinic_phone']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="specialization">
                                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($appointment['specialization']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="datetime-info">
                                            <strong><i class="fas fa-calendar"></i> <?php echo $appointment_date; ?></strong>
                                            <br>
                                            <i class="fas fa-clock"></i> <?php echo $appointment_time; ?>
                                            <?php echo $time_indicator; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="appointment-details">
                                            <?php if (!empty($appointment['reason'])): ?>
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($appointment['symptoms_description'])): ?>
                                                <strong>Symptoms:</strong> <?php echo htmlspecialchars($appointment['symptoms_description']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($appointment['status'] == 'cancelled' && !empty($appointment['cancellation_reason'])): ?>
                                                <strong>Cancellation Reason:</strong> 
                                                <span style="color: #dc3545;"><?php echo htmlspecialchars($appointment['cancellation_reason']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($appointment['notes'])): ?>
                                                <div class="appointment-notes">
                                                    <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($appointment['notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="status-fee-info">
                                            <span class="status <?php echo $status_class; ?>" style="border-left-color: <?php echo $status_color; ?>">
                                                <i class="fas <?php echo $status_icon; ?>"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                            <?php echo $fee_badge; ?>
                                            <br>
                                            <small style="display: block; margin-top: 5px; color: #666;">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($appointment['clinic_address']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons-small">
                                            <!-- View Details -->
                                            <button class="action-btn view-btn" 
                                                    onclick="viewAppointmentDetails(<?php echo $appointment['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($is_confirmable): ?>
                                                <!-- Confirm Appointment -->
                                                <button class="action-btn confirm-btn" 
                                                        onclick="confirmAppointment(<?php echo $appointment['id']; ?>)"
                                                        title="Confirm Appointment">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($is_cancellable): ?>
                                                <!-- Cancel Appointment -->
                                                <button class="action-btn cancel-btn" 
                                                        onclick="showCancelModal(<?php echo $appointment['id']; ?>, 'Dr. <?php echo addslashes($appointment['doctor_name']); ?> on <?php echo $appointment_date; ?> at <?php echo $appointment_time; ?>')"
                                                        title="Cancel Appointment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                
                                                <!-- Reschedule Appointment -->
                                                <button class="action-btn reschedule-btn" 
                                                        onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>)" 
                                                        title="Reschedule">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] == 'completed'): ?>
                                                <!-- View Medical Record -->
                                                <button class="action-btn record-btn"
                                                        onclick="viewMedicalRecord(<?php echo $appointment['id']; ?>)"
                                                        title="View Medical Record">
                                                    <i class="fas fa-file-medical"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] == 'cancelled'): ?>
                                                <!-- Remove Cancelled Appointment -->
                                                <button class="action-btn delete-btn"
                                                        onclick="deleteAppointment(<?php echo $appointment['id']; ?>)"
                                                        title="Remove from list">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Summary -->
                <div class="table-summary">
                    <p>Showing <?php echo count($appointments); ?> appointment(s) 
                    <span id="filterInfo"><?php echo $status_filter != 'upcoming' ? 'filtered by ' . ucfirst($status_filter) : ''; ?></span></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Cancel Appointment Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Cancel Appointment</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this appointment?</p>
                <p><strong id="modalAppointmentInfo"></strong></p>
                
                <form method="POST" action="" id="cancelForm">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                    
                    <div class="form-group">
                        <label for="cancellation_reason"><i class="fas fa-comment"></i> Reason for cancellation:</label>
                        <select name="cancellation_reason" id="cancellation_reason" required>
                            <option value="">Select a reason</option>
                            <option value="Schedule conflict">Schedule conflict</option>
                            <option value="Found another doctor">Found another doctor</option>
                            <option value="Not feeling well">Not feeling well</option>
                            <option value="Transportation issue">Transportation issue</option>
                            <option value="Financial reasons">Financial reasons</option>
                            <option value="Emergency">Emergency</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="otherReasonGroup" style="display: none;">
                        <label for="other_reason"><i class="fas fa-pen"></i> Please specify:</label>
                        <input type="text" name="other_reason" id="other_reason" placeholder="Enter your reason">
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary close-modal">No, Keep It</button>
                        <button type="submit" class="btn-danger">
                            <i class="fas fa-times"></i> Yes, Cancel Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Appointment Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- All Appointments Modal -->
    <div id="allAppointmentsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-list-alt"></i> All Appointment Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="search-filter-box" style="margin-bottom: 15px;">
                    <input type="text" id="modalSearch" placeholder="Filter appointments..." 
                           onkeyup="filterModalAppointments()" style="width: 100%; padding: 8px;">
                </div>
                <div id="allAppointmentsList" style="max-height: 400px; overflow-y: auto;">
                    <!-- Appointments will be loaded here -->
                </div>
            </div>
        </div>
    </div>

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
                <a href="booking.php">Book Appointment</a>
                <a href="contact.php">Contact Us</a>
            </div>
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-phone"></i> +1-555-HEALTH</p>
                <p><i class="fas fa-envelope"></i> appointments@mediflow.com</p>
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

    <script>
        // Function to show all appointments in modal
        function showAllAppointments() {
            // Show loading
            document.getElementById('allAppointmentsList').innerHTML = 
                '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</div>';
            
            // Show modal
            document.getElementById('allAppointmentsModal').style.display = 'block';
            
            // Fetch all appointments via AJAX
            fetch('get_all_appointments.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    displayAllAppointments(data);
                })
                .catch(error => {
                    document.getElementById('allAppointmentsList').innerHTML = 
                        '<div class="error">Error loading appointments. Please try again.</div>';
                    console.error('Error:', error);
                });
        }

        // Function to display all appointments in modal
        function displayAllAppointments(appointments) {
            const container = document.getElementById('allAppointmentsList');
            
            if (!appointments || appointments.length === 0) {
                container.innerHTML = '<div class="no-data">No appointments found.</div>';
                return;
            }
            
            let html = '<div class="appointments-grid">';
            
            appointments.forEach(appointment => {
                const date = new Date(appointment.appointment_date + ' ' + appointment.appointment_time);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    weekday: 'short', 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                const formattedTime = date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                
                // Status color
                let statusColor = '#6c757d';
                let statusText = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
                switch(appointment.status) {
                    case 'confirmed': statusColor = '#28a745'; break;
                    case 'scheduled': statusColor = '#ffc107'; break;
                    case 'completed': statusColor = '#17a2b8'; break;
                    case 'cancelled': statusColor = '#dc3545'; break;
                }
                
                html += `
                    <div class="appointment-card" data-search="${appointment.doctor_name} ${appointment.clinic_name} ${appointment.reason}">
                        <div class="appointment-header">
                            <h4>Dr. ${appointment.doctor_name}</h4>
                            <span class="status-badge" style="background-color: ${statusColor}">
                                ${statusText}
                            </span>
                        </div>
                        <div class="appointment-body">
                            <p><i class="fas fa-hospital"></i> ${appointment.clinic_name}</p>
                            <p><i class="fas fa-calendar"></i> ${formattedDate}</p>
                            <p><i class="fas fa-clock"></i> ${formattedTime}</p>
                            <p><i class="fas fa-stethoscope"></i> ${appointment.specialization}</p>
                            ${appointment.reason ? `<p><i class="fas fa-comment-medical"></i> ${appointment.reason}</p>` : ''}
                            ${appointment.consultation_fee ? `<p><i class="fas fa-money-bill"></i> $${parseFloat(appointment.consultation_fee).toFixed(2)}</p>` : ''}
                        </div>
                        <div class="appointment-actions">
                            <button class="btn-small" onclick="viewAppointmentDetails(${appointment.id}); document.getElementById('allAppointmentsModal').style.display='none';">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        // Function to filter appointments in modal
        function filterModalAppointments() {
            const searchTerm = document.getElementById('modalSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.appointment-card');
            
            cards.forEach(card => {
                const searchData = card.getAttribute('data-search').toLowerCase();
                if (searchData.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Close modal when clicking X or outside
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal') || 
                    e.target.classList.contains('close-modal')) {
                    modal.style.display = 'none';
                }
            });
        });

        // Also close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // Handle other reason selection
        document.getElementById('cancellation_reason').addEventListener('change', function() {
            const otherGroup = document.getElementById('otherReasonGroup');
            if (this.value === 'Other') {
                otherGroup.style.display = 'block';
            } else {
                otherGroup.style.display = 'none';
                document.getElementById('other_reason').value = '';
            }
        });

        // View appointment details
        function viewAppointmentDetails(appointmentId) {
            fetch('get_appointment_details.php?id=' + appointmentId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('detailsContent').innerHTML = html;
                    document.getElementById('detailsModal').style.display = 'block';
                });
        }

        // Confirm appointment
        function confirmAppointment(appointmentId) {
            if (confirm('Are you sure you want to confirm this appointment?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'confirm';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'appointment_id';
                idInput.value = appointmentId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Show cancel modal
        function showCancelModal(appointmentId, appointmentInfo) {
            document.getElementById('cancelAppointmentId').value = appointmentId;
            document.getElementById('modalAppointmentInfo').textContent = appointmentInfo;
            document.getElementById('cancelModal').style.display = 'block';
        }

        // Reschedule appointment
        function rescheduleAppointment(appointmentId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'reschedule';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'appointment_id';
            idInput.value = appointmentId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }

        // View medical record
        function viewMedicalRecord(appointmentId) {
            alert('Medical record view for appointment ID: ' + appointmentId + ' would open here.');
            // In a real application, this would redirect to medical records page
        }

        // Delete appointment
        function deleteAppointment(appointmentId) {
            if (confirm('Are you sure you want to remove this cancelled appointment from your list?')) {
                window.location.href = '?delete=' + appointmentId + '&filter=<?php echo $status_filter; ?>';
            }
        }

        // Show medical records
        function showMedicalRecords() {
            alert('Medical records feature would open here.');
        }

        // Export appointments
        function exportAppointments() {
            alert('Export feature would open here.');
        }

        // Sort table
        function sortTable(value) {
            alert('Sort by ' + value + ' would be implemented here.');
        }

        // Handle form submission for other reason
        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            const reasonSelect = document.getElementById('cancellation_reason');
            const otherReason = document.getElementById('other_reason').value;
            
            if (reasonSelect.value === 'Other' && otherReason.trim() === '') {
                e.preventDefault();
                alert('Please specify your reason for cancellation.');
                document.getElementById('other_reason').focus();
                return;
            }
            
            // If other reason is provided, set it as the cancellation reason
            if (reasonSelect.value === 'Other') {
                // Create a hidden input with the other reason
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'cancellation_reason';
                hiddenInput.value = otherReason;
                this.appendChild(hiddenInput);
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>

