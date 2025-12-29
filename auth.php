<?php
// Authentication related functions

function registerUser($conn, $data) {
    $errors = [];
    
    // Extract data
    $full_name = sanitizeInput($data['full_name']);
    $email = sanitizeInput($data['email']);
    $phone = sanitizeInput($data['phone']);
    $password = $data['password'];
    $confirm_password = $data['confirm_password'];
    
    // Validation
    if (strlen($full_name) < 3) {
        $errors['full_name'] = "Full name must be at least 3 characters";
    }
    
    if (!validateEmail($email)) {
        $errors['email'] = "Invalid email format";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors['email'] = "Email already registered";
        }
        $stmt->close();
    }
    
    if (!validateEgyptPhone($phone)) {
        $errors['phone'] = "Please enter a valid Egyptian phone: 01XXXXXXXXX";
    }
    
    if (!validatePassword($password)) {
        $errors['password'] = "Password must be at least 8 characters and contain letters and numbers";
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // If validation passed
    if (empty($errors)) {
        // Prepare user data
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $name_parts = explode(' ', $full_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        $username = generateUsername($full_name);
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, user_type, first_name, last_name, phone) VALUES (?, ?, ?, 'patient', ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $hashed_password, $first_name, $last_name, $phone);
        
        if ($stmt->execute()) {
            $stmt->close();
            return [
                'success' => true,
                'message' => 'Registration successful! You can now login.',
                'user_id' => $conn->insert_id
            ];
        } else {
            $errors['database'] = "Registration failed: " . $conn->error;
        }
        
        $stmt->close();
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

function loginUser($conn, $email, $password) {
    $errors = [];
    
    if (empty($email)) {
        $errors['email'] = "Email is required";
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, username, email, password_hash, user_type, first_name, last_name, is_active FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                if ($user['is_active']) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    
                    $stmt->close();
                    return [
                        'success' => true,
                        'user_type' => $user['user_type']
                    ];
                } else {
                    $errors['account'] = "Your account is deactivated. Please contact administrator.";
                }
            } else {
                $errors['password'] = "Invalid password";
            }
        } else {
            $errors['email'] = "No account found with this email";
        }
        
        $stmt->close();
    }
    
    return [
        'success' => false,
        'errors' => $errors
    ];
}

function logoutUser() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    redirect('login.php');
}

function checkEmailExists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}
?>