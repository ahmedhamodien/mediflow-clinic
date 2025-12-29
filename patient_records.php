<?php
// ============================================
// PATIENT RECORDS PAGE - PHP BACKEND CODE
// ============================================
session_start();

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize variables
$doctor_id = getDoctorId($_SESSION['user_id']);
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$patient = null;
$medical_records = [];
$patient_conditions = [];
$patient_medications = [];
$appointments = [];
$patients_list = [];
$errors = [];
$success = '';

if (!$doctor_id) {
    $errors['doctor'] = "Doctor profile not found";
} else {
    // Get all patients for this doctor
    $patients_sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, u.date_of_birth 
                    FROM users u 
                    INNER JOIN appointments a ON u.id = a.patient_id 
                    WHERE a.doctor_id = ? 
                    AND u.user_type = 'patient'
                    ORDER BY u.last_name, u.first_name";
    
    $stmt = $conn->prepare($patients_sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $patients_result = $stmt->get_result();
    $patients_list = $patients_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Load patient data if patient_id is provided
    if ($patient_id > 0) {
        // Verify this patient has appointments with this doctor
        $verify_sql = "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1";
        $stmt = $conn->prepare($verify_sql);
        $stmt->bind_param("ii", $patient_id, $doctor_id);
        $stmt->execute();
        $verify_result = $stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            // Get patient basic info
            $patient_sql = "SELECT id, first_name, last_name, email, phone, date_of_birth, 
                           address, emergency_contact_name, emergency_contact_phone 
                           FROM users WHERE id = ? AND user_type = 'patient'";
            $stmt2 = $conn->prepare($patient_sql);
            $stmt2->bind_param("i", $patient_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            
            if ($result->num_rows > 0) {
                $patient = $result->fetch_assoc();
                
                // Get medical records by this doctor
                $records_sql = "SELECT mr.*, d.user_id as doctor_user_id, 
                               CONCAT(du.first_name, ' ', du.last_name) as doctor_name 
                               FROM medical_records mr 
                               LEFT JOIN doctors d ON mr.doctor_id = d.id 
                               LEFT JOIN users du ON d.user_id = du.id 
                               WHERE mr.patient_id = ? AND mr.doctor_id = ?
                               ORDER BY mr.record_date DESC, mr.created_at DESC";
                $stmt3 = $conn->prepare($records_sql);
                $stmt3->bind_param("ii", $patient_id, $doctor_id);
                $stmt3->execute();
                $medical_records = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get patient conditions diagnosed by this doctor
                $conditions_sql = "SELECT pc.*, CONCAT(du.first_name, ' ', du.last_name) as diagnosed_by_name 
                                  FROM patient_conditions pc 
                                  LEFT JOIN doctors d ON pc.diagnosed_by = d.id 
                                  LEFT JOIN users du ON d.user_id = du.id 
                                  WHERE pc.patient_id = ? AND pc.diagnosed_by = ?
                                  ORDER BY pc.diagnosed_date DESC";
                $stmt4 = $conn->prepare($conditions_sql);
                $stmt4->bind_param("ii", $patient_id, $doctor_id);
                $stmt4->execute();
                $patient_conditions = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get patient medications prescribed by this doctor
                $medications_sql = "SELECT pm.*, CONCAT(du.first_name, ' ', du.last_name) as prescribed_by_name 
                                   FROM patient_medications pm 
                                   LEFT JOIN doctors d ON pm.prescribed_by = d.id 
                                   LEFT JOIN users du ON d.user_id = du.id 
                                   WHERE pm.patient_id = ? AND pm.prescribed_by = ?
                                   ORDER BY pm.start_date DESC";
                $stmt5 = $conn->prepare($medications_sql);
                $stmt5->bind_param("ii", $patient_id, $doctor_id);
                $stmt5->execute();
                $patient_medications = $stmt5->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Get recent appointments with this doctor
                $appointments_sql = "SELECT a.*, c.name as clinic_name, 
                                    CONCAT(du.first_name, ' ', du.last_name) as doctor_name 
                                    FROM appointments a 
                                    LEFT JOIN clinics c ON a.clinic_id = c.id 
                                    LEFT JOIN doctors d ON a.doctor_id = d.id 
                                    LEFT JOIN users du ON d.user_id = du.id 
                                    WHERE a.patient_id = ? AND a.doctor_id = ?
                                    ORDER BY a.appointment_date DESC, a.appointment_time DESC 
                                    LIMIT 10";
                $stmt6 = $conn->prepare($appointments_sql);
                $stmt6->bind_param("ii", $patient_id, $doctor_id);
                $stmt6->execute();
                $appointments = $stmt6->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $stmt3->close();
                $stmt4->close();
                $stmt5->close();
                $stmt6->close();
            } else {
                $errors['patient'] = "Patient not found";
            }
            $stmt2->close();
        } else {
            $errors['patient'] = "Patient is not associated with this doctor";
        }
        $stmt->close();
    }
}

// Add new medical record
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_record']) && $doctor_id && $patient_id > 0) {
    $record_date = trim($_POST['record_date']);
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $blood_type = !empty($_POST['blood_type']) ? $_POST['blood_type'] : null;
    $allergies = !empty($_POST['allergies']) ? json_encode(explode(',', $_POST['allergies'])) : null;
    $diagnosis = trim($_POST['diagnosis']);
    $prescription = !empty($_POST['medication_name']) ? json_encode([
        'medication' => $_POST['medication_name'],
        'dosage' => $_POST['dosage'],
        'frequency' => $_POST['frequency'],
        'duration' => $_POST['duration']
    ]) : null;
    $treatment_plan = trim($_POST['treatment_plan']);
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $notes = trim($_POST['notes']);
    
    if (empty($record_date) || empty($diagnosis)) {
        $errors['record'] = "Record date and diagnosis are required";
    } else {
        $sql = "INSERT INTO medical_records (patient_id, doctor_id, record_date, height, weight, 
                blood_type, allergies, diagnosis, prescription, treatment_plan, 
                follow_up_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissssssssss", $patient_id, $doctor_id, $record_date, $height, $weight, 
                         $blood_type, $allergies, $diagnosis, $prescription, $treatment_plan, 
                         $follow_up_date, $notes);
        
        if ($stmt->execute()) {
            $success = "Medical record added successfully";
            // Refresh page to show new record
            header("Location: patient_records.php?patient_id=" . $patient_id . "&success=1");
            exit();
        } else {
            $errors['record'] = "Failed to add medical record";
        }
        $stmt->close();
    }
}

// Add new condition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_condition']) && $doctor_id && $patient_id > 0) {
    $condition_name = trim($_POST['condition_name']);
    $diagnosed_date = trim($_POST['diagnosed_date']);
    $status = $_POST['status'];
    $severity = $_POST['severity'];
    $notes = trim($_POST['condition_notes']);
    
    if (empty($condition_name) || empty($diagnosed_date)) {
        $errors['condition'] = "Condition name and diagnosis date are required";
    } else {
        $sql = "INSERT INTO patient_conditions (patient_id, condition_name, diagnosed_date, 
                diagnosed_by, status, severity, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ississs", $patient_id, $condition_name, $diagnosed_date, 
                         $doctor_id, $status, $severity, $notes);
        
        if ($stmt->execute()) {
            $success = "Condition added successfully";
            header("Location: patient_records.php?patient_id=" . $patient_id . "&success=1");
            exit();
        } else {
            $errors['condition'] = "Failed to add condition";
        }
        $stmt->close();
    }
}

// Add new medication
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_medication']) && $doctor_id && $patient_id > 0) {
    $medication_name = trim($_POST['new_medication_name']);
    $dosage = trim($_POST['new_dosage']);
    $frequency = trim($_POST['new_frequency']);
    $route = $_POST['route'];
    $start_date = trim($_POST['med_start_date']);
    $end_date = !empty($_POST['med_end_date']) ? $_POST['med_end_date'] : null;
    $refills = isset($_POST['refills']) ? intval($_POST['refills']) : 0;
    $pharmacy_notes = trim($_POST['pharmacy_notes']);
    
    if (empty($medication_name) || empty($start_date)) {
        $errors['medication'] = "Medication name and start date are required";
    } else {
        $sql = "INSERT INTO patient_medications (patient_id, medication_name, dosage, frequency, 
                route, start_date, end_date, prescribed_by, refills_remaining, pharmacy_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssiis", $patient_id, $medication_name, $dosage, $frequency, 
                         $route, $start_date, $end_date, $doctor_id, $refills, $pharmacy_notes);
        
        if ($stmt->execute()) {
            $success = "Medication added successfully";
            header("Location: patient_records.php?patient_id=" . $patient_id . "&success=1");
            exit();
        } else {
            $errors['medication'] = "Failed to add medication";
        }
        $stmt->close();
    }
}

// Helper function to get doctor ID
function getDoctorId($user_id) {
    global $conn;
    $sql = "SELECT id FROM doctors WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    return 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - MediFlow Clinic</title>
    
    <!-- Patient Records CSS -->
    <link rel="stylesheet" href="CSS/patient_records.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- ============================================
    HEADER SECTION
    ============================================ -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><i class="fas fa-stethoscope"></i> MediFlow Clinic</h1>
            </div>
            
            <nav class="main-nav">
                <a href="doctor_dashboard.php"><i class="fas fa-columns"></i> Dashboard</a>
                <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> Schedule</a>
                <a href="patient_records.php" class="active"><i class="fas fa-file-medical"></i> My Patients</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

    <!-- ============================================
    MAIN CONTENT
    ============================================ -->
    <main class="records-main">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-users-medical"></i> My Patients</h1>
                <p>View and manage medical records for your patients</p>
            </div>
            
            <?php if (isset($errors['doctor'])): ?>
                <div class="error-card">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p><?php echo $errors['doctor']; ?></p>
                </div>
            <?php endif; ?>

            <div class="two-column-layout">
                <!-- Left Column: Patients List -->
                <div class="patients-sidebar">
                    <div class="sidebar-header">
                        <h3><i class="fas fa-user-friends"></i> Patient List</h3>
                        <span class="badge"><?php echo count($patients_list); ?> patients</span>
                    </div>
                    
                    <?php if (empty($patients_list)): ?>
                        <div class="no-patients">
                            <i class="fas fa-user-slash"></i>
                            <p>No patients assigned to you yet</p>
                        </div>
                    <?php else: ?>
                        <div class="patients-list">
                            <?php foreach ($patients_list as $p): 
                                $is_active = ($patient && $patient['id'] == $p['id']);
                            ?>
                            <a href="patient_records.php?patient_id=<?php echo $p['id']; ?>" 
                               class="patient-item <?php echo $is_active ? 'active' : ''; ?>">
                                <div class="patient-avatar-small">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                                <div class="patient-info-small">
                                    <h4><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></h4>
                                    <p class="patient-meta-small">
                                        <?php if ($p['date_of_birth']): 
                                            $birthDate = new DateTime($p['date_of_birth']);
                                            $today = new DateTime('today');
                                            $age = $birthDate->diff($today)->y;
                                        ?>
                                            <span><?php echo $age; ?> yrs</span> • 
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($p['phone']); ?></span>
                                    </p>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Patient Details -->
                <div class="patient-details-main">
                    <?php if (!$patient && !isset($errors['patient'])): ?>
                        <!-- No patient selected view -->
                        <div class="welcome-card">
                            <div class="welcome-icon">
                                <i class="fas fa-file-medical-alt"></i>
                            </div>
                            <h2>Welcome to Patient Records</h2>
                            <p>Select a patient from the list to view their medical records, conditions, and medications.</p>
                           
                        </div>
                    <?php elseif (isset($errors['patient'])): ?>
                        <!-- Error view -->
                        <div class="error-card">
                            <i class="fas fa-exclamation-circle"></i>
                            <h3><?php echo $errors['patient']; ?></h3>
                            <p>Please select a valid patient from the list.</p>
                        </div>
                    <?php else: ?>
                        <!-- Patient details view -->
                        <div class="patient-header-card">
                            <div class="patient-header-top">
                                <div class="patient-avatar-large">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                                <div class="patient-header-info">
                                    <h2><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                                    <div class="patient-meta-large">
                                        <span><i class="fas fa-id-badge"></i> ID: <?php echo $patient['id']; ?></span>
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?></span>
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?></span>
                                        <?php if ($patient['date_of_birth']): 
                                            $birthDate = new DateTime($patient['date_of_birth']);
                                            $today = new DateTime('today');
                                            $age = $birthDate->diff($today)->y;
                                        ?>
                                            <span><i class="fas fa-birthday-cake"></i> <?php echo $age; ?> years old</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="patient-actions">
                                    <button class="btn-secondary" onclick="printPatientSummary()">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <button class="btn-primary" data-modal="addRecordModal">
                                        <i class="fas fa-plus"></i> Add Record
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($patient['emergency_contact_name']): ?>
                                <div class="emergency-contact">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($patient['emergency_contact_name']); ?> - <?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tabs Section -->
                        <div class="tabs-section">
                            <div class="tabs-header">
                                <button class="tab-btn active" data-tab="medical-records">
                                    <i class="fas fa-file-medical"></i> Medical Records
                                </button>
                                <button class="tab-btn" data-tab="conditions">
                                    <i class="fas fa-diagnoses"></i> Conditions
                                </button>
                                <button class="tab-btn" data-tab="medications">
                                    <i class="fas fa-pills"></i> Medications
                                </button>
                                <button class="tab-btn" data-tab="appointments">
                                    <i class="fas fa-calendar-check"></i> Appointments
                                </button>
                            </div>
                            
                            <!-- Medical Records Tab -->
                            <div class="tab-content active" id="medical-records">
                                <div class="tab-header">
                                    <h3><i class="fas fa-file-medical"></i> Medical Records</h3>
                                    <button class="btn-small" data-modal="addRecordModal">
                                        <i class="fas fa-plus"></i> Add Record
                                    </button>
                                </div>
                                
                                <?php if (empty($medical_records)): ?>
                                    <div class="no-data">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No medical records found for this patient</p>
                                    </div>
                                <?php else: ?>
                                    <div class="records-grid">
                                        <?php foreach ($medical_records as $record): ?>
                                        <div class="record-card">
                                            <div class="record-header">
                                                <h4><?php echo date('M d, Y', strtotime($record['record_date'])); ?></h4>
                                                <span class="record-doctor"><?php echo htmlspecialchars($record['doctor_name']); ?></span>
                                            </div>
                                            <div class="record-content">
                                                <?php if ($record['diagnosis']): ?>
                                                    <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?></p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['height'] && $record['weight']): ?>
                                                    <p><strong>Vitals:</strong> 
                                                        Height: <?php echo $record['height']; ?> cm, 
                                                        Weight: <?php echo $record['weight']; ?> kg
                                                        <?php if ($record['height'] > 0 && $record['weight'] > 0): 
                                                            $bmi = $record['weight'] / (($record['height']/100) * ($record['height']/100));
                                                        ?>
                                                            (BMI: <?php echo number_format($bmi, 1); ?>)
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['blood_type']): ?>
                                                    <p><strong>Blood Type:</strong> <?php echo $record['blood_type']; ?></p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['prescription']): 
                                                    $prescription = json_decode($record['prescription'], true);
                                                    if ($prescription && isset($prescription['medication'])): ?>
                                                    <p><strong>Prescription:</strong> 
                                                        <?php echo htmlspecialchars($prescription['medication']); ?> 
                                                        <?php echo htmlspecialchars($prescription['dosage']); ?> 
                                                        <?php echo htmlspecialchars($prescription['frequency']); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['notes']): ?>
                                                    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars(substr($record['notes'], 0, 200))); ?>...</p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['follow_up_date']): ?>
                                                    <p class="follow-up">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        Follow-up: <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="record-actions">
                                                <button class="action-btn" onclick="viewRecordDetails(<?php echo $record['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Conditions Tab -->
                            <div class="tab-content" id="conditions">
                                <div class="tab-header">
                                    <h3><i class="fas fa-diagnoses"></i> Medical Conditions</h3>
                                    <button class="btn-small" data-modal="addConditionModal">
                                        <i class="fas fa-plus"></i> Add Condition
                                    </button>
                                </div>
                                
                                <?php if (empty($patient_conditions)): ?>
                                    <div class="no-data">
                                        <i class="fas fa-heartbeat"></i>
                                        <p>No medical conditions recorded</p>
                                    </div>
                                <?php else: ?>
                                    <div class="conditions-list">
                                        <?php foreach ($patient_conditions as $condition): ?>
                                        <div class="condition-card">
                                            <div class="condition-header">
                                                <h4><?php echo htmlspecialchars($condition['condition_name']); ?></h4>
                                                <span class="condition-date">Diagnosed: <?php echo date('M d, Y', strtotime($condition['diagnosed_date'])); ?></span>
                                            </div>
                                            <div class="condition-details">
                                                <div class="condition-status">
                                                    <span class="status-badge status-<?php echo $condition['status']; ?>">
                                                        <?php echo ucfirst($condition['status']); ?>
                                                    </span>
                                                    <span class="severity-badge severity-<?php echo $condition['severity']; ?>">
                                                        <?php echo ucfirst($condition['severity']); ?>
                                                    </span>
                                                </div>
                                                <?php if ($condition['notes']): ?>
                                                    <p class="condition-notes"><?php echo nl2br(htmlspecialchars($condition['notes'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="condition-actions">
                                                <button class="action-btn-small" onclick="viewConditionDetails(<?php echo $condition['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Medications Tab -->
                            <div class="tab-content" id="medications">
                                <div class="tab-header">
                                    <h3><i class="fas fa-pills"></i> Medications</h3>
                                    <button class="btn-small" data-modal="addMedicationModal">
                                        <i class="fas fa-plus"></i> Add Medication
                                    </button>
                                </div>
                                
                                <?php if (empty($patient_medications)): ?>
                                    <div class="no-data">
                                        <i class="fas fa-prescription-bottle-alt"></i>
                                        <p>No medications recorded</p>
                                    </div>
                                <?php else: ?>
                                    <div class="medications-list">
                                        <?php foreach ($patient_medications as $med): 
                                            $is_active = (!$med['end_date'] || strtotime($med['end_date']) > time());
                                        ?>
                                        <div class="medication-card">
                                            <div class="medication-header">
                                                <h4><?php echo htmlspecialchars($med['medication_name']); ?></h4>
                                                <span class="medication-status <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                                    <?php echo $is_active ? 'Active' : 'Completed'; ?>
                                                </span>
                                            </div>
                                            <div class="medication-details">
                                                <div class="medication-info">
                                                    <p><strong>Dosage:</strong> <?php echo htmlspecialchars($med['dosage']); ?></p>
                                                    <p><strong>Frequency:</strong> <?php echo htmlspecialchars($med['frequency']); ?></p>
                                                    <p><strong>Route:</strong> <?php echo ucfirst($med['route']); ?></p>
                                                </div>
                                                <div class="medication-dates">
                                                    <p><strong>Started:</strong> <?php echo date('M d, Y', strtotime($med['start_date'])); ?></p>
                                                    <?php if ($med['end_date']): ?>
                                                        <p><strong>Ends:</strong> <?php echo date('M d, Y', strtotime($med['end_date'])); ?></p>
                                                    <?php else: ?>
                                                        <p><strong>Ends:</strong> Ongoing</p>
                                                    <?php endif; ?>
                                                    <?php if ($med['refills_remaining'] > 0): ?>
                                                        <p><strong>Refills:</strong> <?php echo $med['refills_remaining']; ?> remaining</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($med['pharmacy_notes']): ?>
                                                <div class="medication-notes">
                                                    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($med['pharmacy_notes'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <div class="medication-actions">
                                                <button class="action-btn-small" onclick="viewMedicationDetails(<?php echo $med['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Appointments Tab -->
                            <div class="tab-content" id="appointments">
                                <div class="tab-header">
                                    <h3><i class="fas fa-calendar-check"></i> Recent Appointments</h3>
                                </div>
                                
                                <?php if (empty($appointments)): ?>
                                    <div class="no-data">
                                        <i class="fas fa-calendar-times"></i>
                                        <p>No appointment history</p>
                                    </div>
                                <?php else: ?>
                                    <div class="appointments-list">
                                        <?php foreach ($appointments as $appointment): ?>
                                        <div class="appointment-card">
                                            <div class="appointment-date">
                                                <span class="date-day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></span>
                                                <span class="date-month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></span>
                                                <span class="date-year"><?php echo date('Y', strtotime($appointment['appointment_date'])); ?></span>
                                            </div>
                                            <div class="appointment-details">
                                                <h4><?php echo $appointment['clinic_name']; ?></h4>
                                                <p><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
                                                <?php if ($appointment['reason']): ?>
                                                    <p class="appointment-reason"><?php echo htmlspecialchars(substr($appointment['reason'], 0, 100)); ?>...</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="appointment-status">
                                                <span class="status-badge status-<?php echo str_replace('_', '-', $appointment['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- ============================================
    MODALS
    ============================================ -->
    
    <!-- Add Medical Record Modal -->
    <div class="modal" id="addRecordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-medical"></i> Add Medical Record</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="record_date"><i class="fas fa-calendar"></i> Record Date *</label>
                        <input type="date" id="record_date" name="record_date" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="height"><i class="fas fa-ruler-vertical"></i> Height (cm)</label>
                        <input type="number" id="height" name="height" step="0.1" min="0" max="300">
                    </div>
                    <div class="form-group">
                        <label for="weight"><i class="fas fa-weight"></i> Weight (kg)</label>
                        <input type="number" id="weight" name="weight" step="0.1" min="0" max="300">
                    </div>
                    <div class="form-group">
                        <label for="blood_type"><i class="fas fa-tint"></i> Blood Type</label>
                        <select id="blood_type" name="blood_type">
                            <option value="">Select Blood Type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="allergies"><i class="fas fa-allergies"></i> Allergies (comma-separated)</label>
                    <input type="text" id="allergies" name="allergies" placeholder="Penicillin, Peanuts, etc.">
                </div>
                
                <div class="form-group">
                    <label for="diagnosis"><i class="fas fa-stethoscope"></i> Diagnosis *</label>
                    <textarea id="diagnosis" name="diagnosis" rows="3" required placeholder="Enter diagnosis..."></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-prescription"></i> Prescription (Optional)</label>
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="medication_name" placeholder="Medication Name">
                        </div>
                        <div class="form-group">
                            <input type="text" name="dosage" placeholder="Dosage (e.g., 500mg)">
                        </div>
                        <div class="form-group">
                            <input type="text" name="frequency" placeholder="Frequency (e.g., 2x daily)">
                        </div>
                        <div class="form-group">
                            <input type="text" name="duration" placeholder="Duration (e.g., 7 days)">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="treatment_plan"><i class="fas fa-clipboard-list"></i> Treatment Plan</label>
                    <textarea id="treatment_plan" name="treatment_plan" rows="3" placeholder="Enter treatment plan..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="follow_up_date"><i class="fas fa-calendar-check"></i> Follow-up Date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes"><i class="fas fa-notes-medical"></i> Additional Notes</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Enter additional notes..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary modal-close">Cancel</button>
                    <button type="submit" name="add_record" class="btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Condition Modal -->
    <div class="modal" id="addConditionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-diagnoses"></i> Add Medical Condition</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="form-group">
                    <label for="condition_name"><i class="fas fa-heartbeat"></i> Condition Name *</label>
                    <input type="text" id="condition_name" name="condition_name" required 
                           placeholder="e.g., Hypertension, Diabetes Type 2">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="diagnosed_date"><i class="fas fa-calendar"></i> Diagnosed Date *</label>
                        <input type="date" id="diagnosed_date" name="diagnosed_date" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                        <select id="status" name="status" required>
                            <option value="active" selected>Active</option>
                            <option value="resolved">Resolved</option>
                            <option value="chronic">Chronic</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="severity"><i class="fas fa-exclamation-triangle"></i> Severity</label>
                        <select id="severity" name="severity" required>
                            <option value="mild">Mild</option>
                            <option value="moderate" selected>Moderate</option>
                            <option value="severe">Severe</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="condition_notes"><i class="fas fa-notes-medical"></i> Notes</label>
                    <textarea id="condition_notes" name="condition_notes" rows="4" 
                              placeholder="Enter notes about this condition..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary modal-close">Cancel</button>
                    <button type="submit" name="add_condition" class="btn-primary">Save Condition</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Medication Modal -->
    <div class="modal" id="addMedicationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-pills"></i> Add Medication</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="form-group">
                    <label for="new_medication_name"><i class="fas fa-capsules"></i> Medication Name *</label>
                    <input type="text" id="new_medication_name" name="new_medication_name" required 
                           placeholder="e.g., Amoxicillin, Lisinopril">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_dosage"><i class="fas fa-prescription"></i> Dosage *</label>
                        <input type="text" id="new_dosage" name="new_dosage" required 
                               placeholder="e.g., 500mg, 10mg">
                    </div>
                    <div class="form-group">
                        <label for="new_frequency"><i class="fas fa-clock"></i> Frequency *</label>
                        <input type="text" id="new_frequency" name="new_frequency" required 
                               placeholder="e.g., 2x daily, Once daily">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="route"><i class="fas fa-syringe"></i> Route</label>
                        <select id="route" name="route" required>
                            <option value="oral" selected>Oral</option>
                            <option value="injection">Injection</option>
                            <option value="topical">Topical</option>
                            <option value="inhalation">Inhalation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="refills"><i class="fas fa-redo"></i> Refills</label>
                        <input type="number" id="refills" name="refills" min="0" max="12" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="med_start_date"><i class="fas fa-calendar-plus"></i> Start Date *</label>
                        <input type="date" id="med_start_date" name="med_start_date" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="med_end_date"><i class="fas fa-calendar-minus"></i> End Date (Optional)</label>
                        <input type="date" id="med_end_date" name="med_end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pharmacy_notes"><i class="fas fa-notes-medical"></i> Pharmacy Notes</label>
                    <textarea id="pharmacy_notes" name="pharmacy_notes" rows="3" 
                              placeholder="Enter pharmacy instructions or notes..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary modal-close">Cancel</button>
                    <button type="submit" name="add_medication" class="btn-primary">Save Medication</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
    FOOTER SECTION
    ============================================ -->
    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-stethoscope"></i> MediFlow Clinic</h3>
                <p>Comprehensive Patient Record Management</p>
            </div>
            
            <div class="footer-section">
                <h4>Quick Access</h4>
                <a href="doctor_dashboard.php"><i class="fas fa-columns"></i> Dashboard</a>
                <a href="doctor_schedule.php"><i class="fas fa-calendar-alt"></i> Schedule</a>
                <a href="patient_records.php"><i class="fas fa-file-medical"></i> My Patients</a>
            </div>
            
            <div class="footer-section">
                <h4>Contact Support</h4>
                <p><i class="fas fa-envelope"></i> records@mediflow.com</p>
                <p><i class="fas fa-phone"></i> +20 123 456 7890</p>
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

    <!-- ============================================
    JAVASCRIPT
    ============================================ -->
    <!-- Patient Records JavaScript -->
    <script src="JS/patient_records.js"></script>
</body>
</html>