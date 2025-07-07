<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'ramabhadrarao');
define('DB_PASS', 'nihita1981');
define('DB_NAME', 'student_attendance_db');

// Database Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

// Session configuration
session_start();

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check user type
function getUserType() {
    return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
}

// Function to redirect based on user type
function redirectToDashboard() {
    header("Location: dashboard.php");
    exit();
}

// Function to logout
function logout() {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Function to validate and sanitize form data
function validateInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}
?>