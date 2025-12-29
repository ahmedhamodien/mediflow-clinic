<?php
// includes/functions.php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_type'] ?? null;
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function redirectIfNotRole($required_role) {
    redirectIfNotLoggedIn();
    
    $user_role = getUserRole();
    
    if ($user_role !== $required_role) {
        // Redirect to appropriate dashboard based on role
        switch($user_role) {
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            case 'doctor':
                header("Location: doctor/dashboard.php");
                break;
            case 'patient':
                header("Location: patient/dashboard.php");
                break;
            default:
                header("Location: index.php");
        }
        exit();
    }
}

function requireRole($role) {
    if (!isLoggedIn() || getUserRole() !== $role) {
        http_response_code(403);
        die("Access Denied. You don't have permission to access this page.");
    }
}
?>