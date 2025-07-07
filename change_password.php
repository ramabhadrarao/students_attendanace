<?php
include 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Please login first.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate input
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
    exit();
}

// Check if new password is same as current password
if ($current_password === $new_password) {
    echo json_encode(['success' => false, 'message' => 'New password must be different from current password.']);
    exit();
}

// Get current password hash from database
$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit();
}

// Hash new password
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database
$stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "si", $new_password_hash, $user_id);

if (mysqli_stmt_execute($stmt)) {
    // Log the password change (optional)
    $log_stmt = mysqli_prepare($conn, "INSERT INTO password_change_log (user_id, changed_at, ip_address) VALUES (?, NOW(), ?)");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    mysqli_stmt_bind_param($log_stmt, "is", $user_id, $ip_address);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password changed successfully! Please remember your new password.'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating password. Please try again later.'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>