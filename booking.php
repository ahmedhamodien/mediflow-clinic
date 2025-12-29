<?php
// booking.php
session_start();

// Include database connection
require_once 'config/database.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$errors = [];
$success = '';
$availableDoctors = [];
$availableSlots = [];
$selectedDoctor = null;
$selectedDate = '';

// Get services from database
$services = [
    'Skin Consultation' => 'Dermatology',
    'General Checkup' => 'General Medicine',
    'Cardiology' => 'Heart Care',
    'Pediatrics' => 'Child Care',
    'Emergency' => 'Emergency Care'
];

// Get available doctors from database
$doctorQuery = "
    SELECT d.id, d.specialization, d.consultation_fee, 
           u.first_name, u.last_name, u.profile_image
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE u.is_active = 1
    ORDER BY d.specialization, u.first_name
";

$doctorResult = $conn->query($doctorQuery);
while ($row = $doctorResult->fetch_assoc()) {
    $availableDoctors[] = $row;
}
// Handle AJAX requests for time slots
if (isset($_GET['ajax']) && $_GET['ajax'] == 'getTimeSlots') {
    $doctorId = (int)$_GET['doctor_id'];
    $date = $_GET['date'];
    
    // Debug logging
    error_log("AJAX Request: doctor_id=$doctorId, date=$date");
    
    if ($doctorId && $date) {
        // Get doctor's availability for the selected day
        $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
        
        $availabilityQuery = "
            SELECT start_time, end_time, slot_duration, break_start, break_end
            FROM doctor_availability 
            WHERE doctor_id = ? 
            AND day_of_week = ?
            AND (valid_from IS NULL OR valid_from <= ?)
            AND (valid_until IS NULL OR valid_until >= ?)
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($availabilityQuery);
        $stmt->bind_param("iiss", $doctorId, $dayOfWeek, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $slots = [];
        if ($row = $result->fetch_assoc()) {
            error_log("Found availability: " . print_r($row, true));
            
            $start = new DateTime($row['start_time']);
            $end = new DateTime($row['end_time']);
            $duration = $row['slot_duration'] ?: 30; // Default to 30 if null
            $breakStart = $row['break_start'] ? new DateTime($row['break_start']) : null;
            $breakEnd = $row['break_end'] ? new DateTime($row['break_end']) : null;
            
            // Generate time slots
            $current = clone $start;
            while ($current < $end) {
                $slotTime = $current->format('H:i');
                $slotEnd = clone $current;
                $slotEnd->add(new DateInterval('PT' . $duration . 'M'));
                
                // Skip if in break time
                $isInBreak = false;
                if ($breakStart && $breakEnd) {
                    if ($current >= $breakStart && $current < $breakEnd) {
                        $isInBreak = true;
                    }
                }
                
                if (!$isInBreak) {
                    // Check if slot is already booked
                    $bookedQuery = "
                        SELECT id FROM appointments 
                        WHERE doctor_id = ? 
                        AND appointment_date = ? 
                        AND appointment_time = ?
                        AND status NOT IN ('cancelled', 'no_show')
                        LIMIT 1
                    ";
                    
                    $bookedStmt = $conn->prepare($bookedQuery);
                    $bookedStmt->bind_param("iss", $doctorId, $date, $slotTime);
                    $bookedStmt->execute();
                    $bookedResult = $bookedStmt->get_result();
                    
                    $isBooked = $bookedResult->num_rows > 0;
                    
                    $slots[] = [
                        'time' => $slotTime,
                        'formatted' => date('g:i A', strtotime($slotTime)),
                        'available' => !$isBooked
                    ];
                    
                    $bookedStmt->close();
                }
                
                $current->add(new DateInterval('PT' . $duration . 'M'));
            }
        } else {
            // If no specific availability, generate default slots
            error_log("No availability found for doctor $doctorId on day $dayOfWeek");
            
            $defaultStart = '09:00';
            $defaultEnd = '17:00';
            $defaultDuration = 30;
            
            $start = new DateTime($defaultStart);
            $end = new DateTime($defaultEnd);
            
            $current = clone $start;
            while ($current < $end) {
                $slotTime = $current->format('H:i');
                
                // Check if slot is already booked
                $bookedQuery = "
                    SELECT id FROM appointments 
                    WHERE doctor_id = ? 
                    AND appointment_date = ? 
                    AND appointment_time = ?
                    AND status NOT IN ('cancelled', 'no_show')
                    LIMIT 1
                ";
                
                $bookedStmt = $conn->prepare($bookedQuery);
                $bookedStmt->bind_param("iss", $doctorId, $date, $slotTime);
                $bookedStmt->execute();
                $bookedResult = $bookedStmt->get_result();
                
                $isBooked = $bookedResult->num_rows > 0;
                
                $slots[] = [
                    'time' => $slotTime,
                    'formatted' => date('g:i A', strtotime($slotTime)),
                    'available' => !$isBooked
                ];
                
                $bookedStmt->close();
                $current->add(new DateInterval('PT' . $defaultDuration . 'M'));
            }
        }
        
        $stmt->close();
        
        error_log("Generated " . count($slots) . " slots for doctor $doctorId on $date");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'slots' => $slots,
            'debug' => [
                'doctor_id' => $doctorId,
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'slot_count' => count($slots)
            ]
        ]);
        exit;
    } else {
        // Invalid parameters
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid parameters',
            'slots' => []
        ]);
        exit;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if user is logged in
    if (!$isLoggedIn) {
        $errors[] = "Please <a href='login.php'>login</a> or <a href='register.php'>register</a> to book an appointment.";
    } else {
        $service = trim($_POST['service']);
        $doctor_id = (int)$_POST['doctor'];
        $appointment_date = trim($_POST['date']);
        $appointment_time = trim($_POST['time_slot']);
        $reason = trim($_POST['reason']);
        $clinic_id = 1; // Default clinic for now
        
        // Validation
        if (empty($service)) {
            $errors[] = "Please select a service";
        }
        
        if (empty($doctor_id)) {
            $errors[] = "Please select a doctor";
        }
        
        if (empty($appointment_date)) {
            $errors[] = "Please select a date";
        } elseif (strtotime($appointment_date) < strtotime('today')) {
            $errors[] = "Please select a future date";
        }
        
        if (empty($appointment_time)) {
            $errors[] = "Please select a time slot";
        }
        
        if (empty($reason)) {
            $errors[] = "Please describe your reason for appointment";
        }
        
        // Check if slot is available
        if (empty($errors)) {
            $checkQuery = "
                SELECT id FROM appointments 
                WHERE doctor_id = ? 
                AND appointment_date = ? 
                AND appointment_time = ? 
                AND status NOT IN ('cancelled', 'no_show')
            ";
            
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $errors[] = "Sorry, this time slot is no longer available. Please select another time.";
            }
            $checkStmt->close();
        }
        
        // Create appointment
        if (empty($errors)) {
            try {
                $conn->begin_transaction();
                
                // Insert appointment
                $appointmentQuery = "
                    INSERT INTO appointments 
                    (patient_id, doctor_id, clinic_id, appointment_date, appointment_time, 
                     status, reason, symptoms_description, buffer_before, buffer_after, booked_at)
                    VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, 10, 10, NOW())
                ";
                
                $appointmentStmt = $conn->prepare($appointmentQuery);
                $appointmentStmt->bind_param(
                    "iiissss", 
                    $userId, 
                    $doctor_id, 
                    $clinic_id, 
                    $appointment_date, 
                    $appointment_time, 
                    $service, 
                    $reason
                );
                
                if ($appointmentStmt->execute()) {
                    $appointmentId = $appointmentStmt->insert_id;
                    
                    // Create notification
                    $notificationQuery = "
                        INSERT INTO notifications 
                        (user_id, title, message, notification_type, appointment_id, 
                         status, scheduled_time, channel)
                        VALUES 
                        (?, 'Appointment Booked', 
                         'Your appointment has been scheduled for {$appointment_date} at {$appointment_time}.', 
                         'appointment_confirmation', ?, 'pending', 
                         DATE_ADD(NOW(), INTERVAL 1 HOUR), 'email'),
                        
                        (?, 'Appointment Reminder', 
                         'Reminder: You have an appointment tomorrow at {$appointment_time}.', 
                         'appointment_reminder', ?, 'pending', 
                         DATE_SUB(?, INTERVAL 1 DAY), 'email')
                    ";
                    
                    $notificationStmt = $conn->prepare($notificationQuery);
                    $notificationStmt->bind_param(
                        "iiiss", 
                        $userId, $appointmentId,
                        $userId, $appointmentId, $appointment_date
                    );
                    $notificationStmt->execute();
                    
                    $conn->commit();
                    
                    $success = "Appointment booked successfully! Your appointment ID is #AP" . str_pad($appointmentId, 6, '0', STR_PAD_LEFT) . 
                              ". You'll receive a confirmation email shortly.";
                    
                    // Clear form
                    $service = $doctor_id = $appointment_date = $appointment_time = $reason = '';
                    
                } else {
                    $errors[] = "Failed to book appointment: " . $conn->error;
                }
                
                $appointmentStmt->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Booking failed: " . $e->getMessage();
            }
        }
    }
}

// Handle AJAX requests for time slots
if (isset($_GET['ajax']) && $_GET['ajax'] == 'getTimeSlots') {
    $doctorId = (int)$_GET['doctor_id'];
    $date = $_GET['date'];
    
    if ($doctorId && $date) {
        // Get doctor's availability for the selected day
        $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
        
        $availabilityQuery = "
            SELECT start_time, end_time, slot_duration, break_start, break_end
            FROM doctor_availability 
            WHERE doctor_id = ? 
            AND day_of_week = ?
            AND (valid_from IS NULL OR valid_from <= ?)
            AND (valid_until IS NULL OR valid_until >= ?)
        ";
        
        $stmt = $conn->prepare($availabilityQuery);
        $stmt->bind_param("iiss", $doctorId, $dayOfWeek, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $slots = [];
        if ($row = $result->fetch_assoc()) {
            $start = new DateTime($row['start_time']);
            $end = new DateTime($row['end_time']);
            $duration = $row['slot_duration'];
            $breakStart = $row['break_start'] ? new DateTime($row['break_start']) : null;
            $breakEnd = $row['break_end'] ? new DateTime($row['break_end']) : null;
            
            // Generate time slots
            $current = clone $start;
            while ($current < $end) {
                $slotTime = $current->format('H:i');
                $slotEnd = clone $current;
                $slotEnd->add(new DateInterval('PT' . $duration . 'M'));
                
                // Skip if in break time
                $isInBreak = false;
                if ($breakStart && $breakEnd) {
                    if ($current >= $breakStart && $current < $breakEnd) {
                        $isInBreak = true;
                    }
                }
                
                if (!$isInBreak) {
                    // Check if slot is already booked
                    $bookedQuery = "
                        SELECT id FROM appointments 
                        WHERE doctor_id = ? 
                        AND appointment_date = ? 
                        AND appointment_time = ?
                        AND status NOT IN ('cancelled', 'no_show')
                    ";
                    
                    $bookedStmt = $conn->prepare($bookedQuery);
                    $bookedStmt->bind_param("iss", $doctorId, $date, $slotTime);
                    $bookedStmt->execute();
                    $bookedResult = $bookedStmt->get_result();
                    
                    $isBooked = $bookedResult->num_rows > 0;
                    
                    $slots[] = [
                        'time' => $slotTime,
                        'formatted' => date('g:i A', strtotime($slotTime)),
                        'available' => !$isBooked
                    ];
                    
                    $bookedStmt->close();
                }
                
                $current->add(new DateInterval('PT' . $duration . 'M'));
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['slots' => $slots]);
        exit;
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - MediFlow Clinic</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="CSS/booking.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><i class="fas fa-stethoscope"></i> MediFlow Clinic</h1>
            </div>
            
            <nav class="main-nav">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Log In</a>
                <a href="booking.php" class="active"><i class="fas fa-calendar-check"></i> Booking</a>
            </nav>
            
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
            <button class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
                <button class="close-btn">&times;</button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="booking-main">
        <div class="booking-container">
            <div class="booking-header">
                <h2><i class="fas fa-calendar-check"></i> Book Your Appointment</h2>
                <p>Schedule your visit with our expert medical professionals</p>
            </div>
            
            <?php if (!$isLoggedIn): ?>
                <div class="login-required">
                    <div class="login-card">
                        <i class="fas fa-lock"></i>
                        <h3>Login Required</h3>
                        <p>Please login or register to book an appointment</p>
                        <div class="auth-buttons">
                            <a href="login.php" class="auth-btn login-btn">
                                <i class="fas fa-sign-in-alt"></i> Log In
                            </a>
                            <a href="register.php" class="auth-btn register-btn">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="booking-form" id="bookingForm" <?php echo !$isLoggedIn ? 'style="display:none;"' : ''; ?>>
                <!-- Step 1: Service & Doctor -->
                <div class="booking-step active" id="step1">
                    <h3><i class="fas fa-stethoscope"></i> Select Service & Doctor</h3>
                    
                    <div class="form-group">
                        <label for="service">
                            <i class="fas fa-heartbeat"></i> Choose a Service
                        </label>
                        <select id="service" name="service" required>
                            <option value="">Select service</option>
                            <?php foreach ($services as $serviceName => $category): ?>
                                <option value="<?php echo $serviceName; ?>" 
                                    <?php echo (isset($_POST['service']) && $_POST['service'] == $serviceName) ? 'selected' : ''; ?>>
                                    <?php echo $serviceName; ?> (<?php echo $category; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="doctor">
                            <i class="fas fa-user-md"></i> Choose a Doctor
                        </label>
                        <select id="doctor" name="doctor" required>
                            <option value="">Select doctor</option>
                            <?php foreach ($availableDoctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                    data-specialization="<?php echo $doctor['specialization']; ?>"
                                    data-fee="<?php echo $doctor['consultation_fee']; ?>"
                                    <?php echo (isset($_POST['doctor']) && $_POST['doctor'] == $doctor['id']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?> 
                                    - <?php echo $doctor['specialization']; ?>
                                    (Fee: $<?php echo $doctor['consultation_fee']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="doctor-info" id="doctorInfo" style="display: none;">
                            <div class="info-content">
                                <h4>Doctor Information</h4>
                                <p><strong>Specialization:</strong> <span id="docSpecialization"></span></p>
                                <p><strong>Consultation Fee:</strong> $<span id="docFee"></span></p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="next-btn" id="nextStep1">
                        Next: Select Date & Time <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- Step 2: Date & Time -->
                <div class="booking-step" id="step2">
                    <h3><i class="fas fa-calendar-alt"></i> Select Date & Time</h3>
                    
                    <div class="form-group">
                        <label for="date">
                            <i class="fas fa-calendar"></i> Appointment Date
                        </label>
                        <input type="date" id="date" name="date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                               required
                               value="<?php echo isset($_POST['date']) ? $_POST['date'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="reason">
                            <i class="fas fa-comment-medical"></i> Reason for Appointment
                        </label>
                        <textarea id="reason" name="reason" 
                                  placeholder="Please describe your symptoms or reason for appointment..."
                                  rows="3" required><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                    </div>

                    <div class="time-slots-container" id="timeSlotsContainer">
                        <h4><i class="fas fa-clock"></i> Available Time Slots</h4>
                        <p class="time-note">Please select a doctor and date first</p>
                        <div class="time-slots-grid" id="timeSlotsGrid">
                            <!-- Time slots will be loaded via AJAX -->
                        </div>
                        <input type="hidden" id="time_slot" name="time_slot" required>
                    </div>
                    
                    <div class="step-buttons">
                        <button type="button" class="prev-btn" id="prevStep2">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="next-btn" id="nextStep2">
                            Next: Confirm Details <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Confirmation -->
                <div class="booking-step" id="step3">
                    <h3><i class="fas fa-clipboard-check"></i> Confirm Appointment Details</h3>
                    
                    <div class="confirmation-details">
                        <table class="confirm-table">
                            <tr>
                                <th><i class="fas fa-heartbeat"></i> Service</th>
                                <td id="confirmService">-</td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-user-md"></i> Doctor</th>
                                <td id="confirmDoctor">-</td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <td id="confirmDate">-</td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-clock"></i> Time</th>
                                <td id="confirmTime">-</td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-money-bill-wave"></i> Consultation Fee</th>
                                <td id="confirmFee">-</td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-comment-medical"></i> Reason</th>
                                <td id="confirmReason">-</td>
                            </tr>
                        </table>
                        
                        <div class="confirmation-note">
                            <i class="fas fa-info-circle"></i>
                            <p>By confirming, you agree to our appointment policies. Please arrive 10 minutes before your scheduled time.</p>
                        </div>
                    </div>
                    
                    <div class="step-buttons">
                        <button type="button" class="prev-btn" id="prevStep3">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="confirm-btn" id="submitBtn">
                            <i class="fas fa-calendar-check"></i> Confirm Appointment
                        </button>
                    </div>
                </div>
                
                <div class="form-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-steps">
                        <div class="step-indicator active" data-step="1">
                            <span class="step-number">1</span>
                            <span class="step-label">Service & Doctor</span>
                        </div>
                        <div class="step-indicator" data-step="2">
                            <span class="step-number">2</span>
                            <span class="step-label">Date & Time</span>
                        </div>
                        <div class="step-indicator" data-step="3">
                            <span class="step-number">3</span>
                            <span class="step-label">Confirmation</span>
                        </div>
                    </div>
                </div>
            </form>
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
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="services.php"><i class="fas fa-heartbeat"></i> Services</a>
                <a href="doctors.php"><i class="fas fa-user-md"></i> Our Doctors</a>
                <a href="booking.php"><i class="fas fa-calendar-check"></i> Book Appointment</a>
            </div>
            
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-map-marker-alt"></i> 123 Medical Street, Cairo</p>
                <p><i class="fas fa-phone"></i> +20 123 456 7890</p>
                <p><i class="fas fa-envelope"></i> info@mediflow.com</p>
            </div>
            
            <div class="footer-section">
                <h4>Hours</h4>
                <p><i class="fas fa-clock"></i> Mon-Fri: 9:00 AM - 5:00 PM</p>
                <p><i class="fas fa-clock"></i> Sat: 9:00 AM - 2:00 PM</p>
                <p><i class="fas fa-clock"></i> Sun: Emergency Only</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2025 MediFlow Clinic. All rights reserved.</p>
            <div class="footer-links">
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="cancellation.php">Cancellation Policy</a>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="JS/booking.js"></script>
</body>
</html>