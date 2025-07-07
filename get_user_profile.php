<?php
include 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit();
}

$user_type = getUserType();
$user_id = $_SESSION['user_id'];

if ($user_type == 'student') {
    // Get student profile
    $stmt = mysqli_prepare($conn, "SELECT s.*, p.programme_name, p.programme_code, b.batch_name, 
                                  sem.semester_name, cs.section_name, u.username
                                  FROM students s
                                  JOIN users u ON s.user_id = u.user_id
                                  LEFT JOIN programme p ON s.programme_id = p.programme_id
                                  LEFT JOIN batch b ON s.batch_id = b.batch_id
                                  LEFT JOIN semester sem ON s.semester_id = sem.semester_id
                                  LEFT JOIN class_section cs ON s.section_id = cs.section_id
                                  WHERE s.user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    
    if ($profile) {
        echo '<div class="row">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>' . htmlspecialchars($profile['username']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Full Name:</strong></td>
                            <td>' . htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Admission Number:</strong></td>
                            <td>' . htmlspecialchars($profile['admission_number']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Roll Number:</strong></td>
                            <td>' . htmlspecialchars($profile['roll_number']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Father Name:</strong></td>
                            <td>' . htmlspecialchars($profile['father_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Mobile:</strong></td>
                            <td>' . htmlspecialchars($profile['mobile_number'] ?: 'Not provided') . '</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>' . htmlspecialchars($profile['email'] ?: 'Not provided') . '</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Academic Information</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Programme:</strong></td>
                            <td>' . htmlspecialchars($profile['programme_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Batch:</strong></td>
                            <td>' . htmlspecialchars($profile['batch_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Semester:</strong></td>
                            <td>' . htmlspecialchars($profile['semester_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Section:</strong></td>
                            <td>' . htmlspecialchars($profile['section_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge ' . ($profile['student_status'] == 'active' ? 'bg-success' : 'bg-secondary') . '">' . ucfirst($profile['student_status']) . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Date of Birth:</strong></td>
                            <td>' . date('F j, Y', strtotime($profile['date_of_birth'])) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Gender:</strong></td>
                            <td>' . ucfirst($profile['gender']) . '</td>
                        </tr>
                    </table>
                </div>
              </div>';
    }
    
} elseif ($user_type == 'faculty') {
    // Get faculty profile
    $stmt = mysqli_prepare($conn, "SELECT f.*, u.username
                                  FROM faculty f
                                  JOIN users u ON f.user_id = u.user_id
                                  WHERE f.user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    
    if ($profile) {
        echo '<div class="row">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>' . htmlspecialchars($profile['username']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Employee ID:</strong></td>
                            <td>' . htmlspecialchars($profile['employee_id']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Full Name:</strong></td>
                            <td>' . htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Designation:</strong></td>
                            <td>' . htmlspecialchars($profile['designation']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Department:</strong></td>
                            <td>' . htmlspecialchars($profile['department']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Mobile:</strong></td>
                            <td>' . htmlspecialchars($profile['mobile_number'] ?: 'Not provided') . '</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>' . htmlspecialchars($profile['email'] ?: 'Not provided') . '</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Professional Information</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Qualification:</strong></td>
                            <td>' . htmlspecialchars($profile['qualification'] ?: 'Not specified') . '</td>
                        </tr>
                        <tr>
                            <td><strong>Experience:</strong></td>
                            <td>' . ($profile['experience_years'] ?: '0') . ' Years</td>
                        </tr>
                        <tr>
                            <td><strong>Faculty Type:</strong></td>
                            <td><span class="badge bg-info">' . ucfirst($profile['faculty_type']) . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Date of Joining:</strong></td>
                            <td>' . ($profile['date_of_joining'] ? date('F j, Y', strtotime($profile['date_of_joining'])) : 'Not specified') . '</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge ' . ($profile['status'] == 'active' ? 'bg-success' : 'bg-secondary') . '">' . ucfirst($profile['status']) . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Address:</strong></td>
                            <td>' . htmlspecialchars($profile['address'] ?: 'Not provided') . '</td>
                        </tr>
                    </table>
                </div>
              </div>';
    }
    
} else {
    // For admin/hod - show basic user info
    $stmt = mysqli_prepare($conn, "SELECT username, user_type, status, created_at FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    
    if ($profile) {
        echo '<div class="row">
                <div class="col-md-12">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-shield fa-3x text-primary mb-3"></i>
                        <h5>' . ucfirst($profile['user_type']) . ' Account</h5>
                    </div>
                    
                    <h6 class="border-bottom pb-2 mb-3">Account Information</h6>
                    <table class="table table-borderless">
                        <tr>
                            <td width="30%"><strong>Username:</strong></td>
                            <td>' . htmlspecialchars($profile['username']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>User Type:</strong></td>
                            <td><span class="badge bg-primary">' . strtoupper($profile['user_type']) . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge ' . ($profile['status'] == 'active' ? 'bg-success' : 'bg-secondary') . '">' . ucfirst($profile['status']) . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Account Created:</strong></td>
                            <td>' . date('F j, Y g:i A', strtotime($profile['created_at'])) . '</td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> As a ' . $profile['user_type'] . ', your detailed profile information is managed separately. 
                        Contact the system administrator for any profile updates.
                    </div>
                </div>
              </div>';
    }
}

if (!isset($profile) || !$profile) {
    echo '<div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Profile information not found. Please contact the administrator.
          </div>';
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>