<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediFlow Clinic</title>
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body>
    <!-- header section -->
    <header>
        <h1>MediFlow Clinic</h1>
        <nav>
            <?php if (!$isLoggedIn): ?>
                <a href="index.php">Home</a>
                <a href="register.php">Register</a>
                <a href="login.php">Log In</a>
                <a href="contact.php">Contact</a>
            <?php else: ?>
                <a href="index.php">Home</a>
                <?php if ($userType == 'patient'): ?>
                    <a href="patient/dashboard.php">Dashboard</a>
                    <a href="patient/appointments.php">My Appointments</a>
                    <a href="patient/profile.php">Profile</a>
                <?php elseif ($userType == 'doctor'): ?>
                    <a href="doctor/dashboard.php">Dashboard</a>
                    <a href="doctor/appointments.php">Appointments</a>
                    <a href="doctor/profile.php">Profile</a>
                <?php elseif ($userType == 'admin'): ?>
                    <a href="admin/dashboard.php">Dashboard</a>
                    <a href="admin/doctors.php">Doctors</a>
                    <a href="admin/patients.php">Patients</a>
                <?php endif; ?>
                <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['first_name']); ?>)</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Display session messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="session-message success">
            <?php echo $_SESSION['success']; ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="session-message error">
            <?php echo $_SESSION['error']; ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>