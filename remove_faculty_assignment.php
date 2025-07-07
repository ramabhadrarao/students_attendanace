<?php
include 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has proper permissions
if (!isLoggedIn() || !in_array(getUserType(), ['admin', 'hod'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$allocation_id = isset($_POST['allocation_id']) ? (int)$_POST['allocation_id'] : 0;

if ($allocation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid allocation ID']);
    exit();
}

// Check if there are any attendance records for this assignment
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM attendance a 
                              JOIN subject_faculty sf ON a.faculty_id = sf.faculty_id AND a.subject_id = sf.subject_id
                              WHERE sf.allocation_id = ?");
mysqli_stmt_bind_param($stmt, "i", $allocation_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$attendance_count = mysqli_fetch_assoc($result)['count'];

if ($attendance_count > 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot remove assignment. Attendance records exist for this faculty-subject combination.']);
    mysqli_stmt_close($stmt);
    exit();
}

// Remove the faculty assignment
$stmt = mysqli_prepare($conn, "DELETE FROM subject_faculty WHERE allocation_id = ?");
mysqli_stmt_bind_param($stmt, "i", $allocation_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Faculty assignment removed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error removing faculty assignment: ' . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>