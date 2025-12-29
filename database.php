<?php
// Database configuration
define('DB_HOST', 'interchange.proxy.rlwy.net');
define('DB_USER', 'root');
define('DB_PASS', 'tfjmUwdPwmljUBeGyqkIXukwLdJDYnNK');
define('DB_NAME', 'railway');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Timezone
date_default_timezone_set('Africa/Cairo');

// Error reporting (only for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
