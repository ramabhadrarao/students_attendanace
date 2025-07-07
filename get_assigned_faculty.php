<?php
include 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit();
}

// Get user type
$user_type = getUserType();

$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($subject_id <= 0) {
    echo '<div class="alert alert-danger">Invalid subject ID.</div>';
    exit();
}

// Get assigned faculty for the subject
$stmt = mysqli_prepare($conn, "SELECT sf.*, f.employee_id, f.first_name, f.last_name, f.designation, f.department,
                              cs.section_name, p.programme_name, p.programme_code
                              FROM subject_faculty sf
                              JOIN faculty f ON sf.faculty_id = f.faculty_id
                              JOIN class_section cs ON sf.section_id = cs.section_id
                              JOIN programme p ON cs.programme_id = p.programme_id
                              WHERE sf.subject_id = ? AND sf.status = 'active'
                              ORDER BY f.first_name, f.last_name");
mysqli_stmt_bind_param($stmt, "i", $subject_id);
mysqli_stmt_execute($stmt);
$assignments = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($assignments) === 0) {
    echo '<div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-2x mb-2"></i>
            <h6>No Faculty Assigned</h6>
            <p class="mb-0">No faculty members are currently assigned to this subject.</p>
          </div>';
} else {
    echo '<input type="hidden" id="current_subject_id" value="' . $subject_id . '">';
    echo '<div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Faculty</th>
                        <th>Department</th>
                        <th>Section</th>
                        <th>Programme</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
    
    while ($assignment = mysqli_fetch_assoc($assignments)) {
        $faculty_name = $assignment['first_name'] . ' ' . $assignment['last_name'];
        echo '<tr>
                <td>
                    <strong>' . htmlspecialchars($faculty_name) . '</strong><br>
                    <small class="text-muted">' . htmlspecialchars($assignment['employee_id']) . ' - ' . htmlspecialchars($assignment['designation']) . '</small>
                </td>
                <td>' . htmlspecialchars($assignment['department']) . '</td>
                <td>
                    <span class="badge bg-primary">' . htmlspecialchars($assignment['section_name']) . '</span>
                </td>
                <td>
                    <span class="badge bg-info">' . htmlspecialchars($assignment['programme_code']) . '</span><br>
                    <small>' . htmlspecialchars($assignment['programme_name']) . '</small>
                </td>
                <td>' . htmlspecialchars($assignment['academic_year']) . '</td>
                <td>
                    <span class="badge bg-success">Active</span>
                </td>
                <td>';
        
        // Only show remove button for admin/HOD
        if (in_array($user_type, ['admin', 'hod'])) {
            echo '<button type="button" class="btn btn-sm btn-outline-danger" 
                    onclick="removeFacultyAssignment(' . $assignment['allocation_id'] . ', \'' . htmlspecialchars($faculty_name) . '\', \'' . htmlspecialchars($assignment['section_name']) . '\')">
                    <i class="fas fa-times"></i> Remove
                  </button>';
        } else {
            echo '<span class="text-muted">-</span>';
        }
        
        echo '</td>
              </tr>';
    }
    
    echo '</tbody>
          </table>
          </div>';
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>