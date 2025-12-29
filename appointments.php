<?php

session_start();

// Include database connection
require_once 'config/database.php';
// include helper functions
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Handle appointment actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $action = $_POST['action'];
    
    if ($appointment_id) {
        switch($action) {
            case 'confirm':
                $sql = "UPDATE appointments SET status = 'confirmed', updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $appointment_id);
                $stmt->execute();
                $message = "Appointment confirmed successfully!";
                break;
                
            case 'cancel':
                $sql = "UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $appointment_id);
                $stmt->execute();
                $message = "Appointment cancelled successfully!";
                break;
                
            case 'complete':
                $sql = "UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $appointment_id);
                $stmt->execute();
                $message = "Appointment marked as completed!";
                break;
        }
        $stmt->close();
    }
}

// Get all appointments with patient and doctor details
$sql = "SELECT a.*, 
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
               c.name AS clinic_name,
               doc.specialization
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users d ON doc.user_id = d.id
        JOIN clinics c ON a.clinic_id = c.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$result = $conn->query($sql);

// Get appointment statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Filter appointments if requested
$filter = $_GET['filter'] ?? 'all';
if ($filter != 'all') {
    $sql .= " WHERE a.status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - MediFlow Clinic</title>
    
    <!-- Appointments Page CSS -->
    <link rel="stylesheet" href="adminappointments.css">
    
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
            
            
            
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!--main content of the page -->
    <main class="appointments-main">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> Appointments Management</h1>
                <p>Manage all clinic appointments, confirmations, and cancellations</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total'] ?? 0; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon scheduled">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['scheduled'] ?? 0; ?></h3>
                        <p>Scheduled</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon confirmed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['confirmed'] ?? 0; ?></h3>
                        <p>Confirmed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        All Appointments
                    </a>
                    <a href="?filter=scheduled" class="filter-tab <?php echo $filter == 'scheduled' ? 'active' : ''; ?>">
                        Scheduled
                    </a>
                    <a href="?filter=confirmed" class="filter-tab <?php echo $filter == 'confirmed' ? 'active' : ''; ?>">
                        Confirmed
                    </a>
                    <a href="?filter=cancelled" class="filter-tab <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">
                        Cancelled
                    </a>
                    <a href="?filter=completed" class="filter-tab <?php echo $filter == 'completed' ? 'active' : ''; ?>">
                        Completed
                    </a>
                </div>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search appointments...">
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="appointments-table-container">
                <table class="appointments-table" id="appointmentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Clinic</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <?php 
                                $status_class = '';
                                switch($row['status']) {
                                    case 'scheduled': $status_class = 'status-scheduled'; break;
                                    case 'confirmed': $status_class = 'status-confirmed'; break;
                                    case 'cancelled': $status_class = 'status-cancelled'; break;
                                    case 'completed': $status_class = 'status-completed'; break;
                                    default: $status_class = 'status-scheduled';
                                }
                                
                                $status_text = ucfirst($row['status']);
                                $appointment_date = date('M d, Y', strtotime($row['appointment_date']));
                                $appointment_time = date('h:i A', strtotime($row['appointment_time']));
                                ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                                    <td><?php echo htmlspecialchars($row['clinic_name']); ?></td>
                                    <td><?php echo $appointment_date; ?></td>
                                    <td><?php echo $appointment_time; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($row['status'] == 'scheduled'): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn btn-confirm">
                                                        <i class="fas fa-check"></i> Confirm
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($row['status'] != 'cancelled' && $row['status'] != 'completed'): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($row['status'] == 'confirmed'): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="submit" class="btn btn-complete">
                                                        <i class="fas fa-clipboard-check"></i> Complete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-view" onclick="viewAppointment(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-calendar-times"></i>
                                    No appointments found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Appointment Details Modal -->
            <div id="appointmentModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-calendar-check"></i> Appointment Details</h2>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body" id="appointmentDetails">
                        <!-- Details will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!--footer section-->
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
                <a href="dashboard.php">Dashboard</a>
                <a href="appointments.php">Appointments</a>
                <a href="patients.php">Patients</a>
                <a href="doctors.php">Doctors</a>
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

    <!-- javascript link-->
    <!-- Appointments Page JavaScript -->
    <script src="appointments.js"></script>
    
    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</body>

</html>
