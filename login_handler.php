<?php
// login.php - Backend for login processing
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'mediflow_clinic';
$username = 'root'; // Change this to your database username
$password = ''; // Change this to your database password

// Connect to database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);
    $rememberMe = isset($_POST['rememberMe']) ? true : false;
    
    // Validation
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email/Username is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, check credentials
    if (empty($errors)) {
        // Query to check user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email OR username = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Verify password (assuming password is hashed)
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Set cookie if "Remember Me" is checked (7 days)
                if ($rememberMe) {
                    setcookie('user_email', $email, time() + (7 * 24 * 60 * 60), "/");
                    setcookie('user_remember', '1', time() + (7 * 24 * 60 * 60), "/");
                }
                
                // Redirect based on role
                switch ($user['role']) {
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
                        header("Location: dashboard.php");
                }
                exit();
            } else {
                $errors[] = "Invalid password";
            }
        } else {
            $errors[] = "User not found";
        }
    }
    
    // If there are errors, store them in session
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['old_email'] = $email;
        header("Location: login.html");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: login.html");
    exit();
}
?>