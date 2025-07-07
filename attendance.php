<?php
// Fixed attendance.php - Attendance marking section
global $conn;

$message = '';
$error = '';
$attendance_data = array();

// Get user-specific data based on role
$current_faculty_id = 0;
$current_student_id = 0;

if ($user_type == 'faculty') {
    // Get faculty ID
    $stmt = mysqli_prepare($conn, "SELECT faculty_id FROM faculty WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $faculty_data = mysqli_fetch_assoc($result);
    $current_faculty_id = $faculty_data ? $faculty_data['faculty_id'] : 0;
    mysqli_stmt_close($stmt);
} elseif ($user_type == 'student') {
    // Get student ID
    $stmt = mysqli_prepare($conn, "SELECT student_id FROM students WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_data = mysqli_fetch_assoc($result);
    $current_student_id = $student_data ? $student_data['student_id'] : 0;
    mysqli_stmt_close($stmt);
}

// Handle attendance marking (Faculty only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && in_array($user_type, ['admin', 'hod', 'faculty'])) {
    switch ($_POST['action']) {
        case 'mark_attendance':
            $subject_id = (int)validateInput($_POST['subject_id']);
            $section_id = (int)validateInput($_POST['section_id']);
            $attendance_date = validateInput($_POST['attendance_date']);
            $period_number = (int)validateInput($_POST['period_number']);
            $faculty_id = $user_type == 'faculty' ? $current_faculty_id : (int)validateInput($_POST['faculty_id']);
            
            // Validate and format date
            if (!empty($attendance_date)) {
                $date_obj = DateTime::createFromFormat('Y-m-d', $attendance_date);
                if (!$date_obj || $date_obj->format('Y-m-d') !== $attendance_date) {
                    $error = "Invalid date format. Please use YYYY-MM-DD format.";
                    break;
                }
                // Ensure date is not in the future
                $today = new DateTime();
                if ($date_obj > $today) {
                    $error = "Cannot mark attendance for future dates.";
                    break;
                }
            }
            
            // Validate inputs
            if (empty($subject_id) || empty($section_id) || empty($attendance_date) || empty($period_number)) {
                $error = "Please fill in all required fields.";
                break;
            }
            
            // Additional date validation
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
                $error = "Invalid date format. Expected format: YYYY-MM-DD";
                break;
            }
            
            if ($faculty_id <= 0) {
                $error = "Faculty information not found. Please contact administrator.";
                break;
            }
            
            // Check if attendance already marked for this date and period
            $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM attendance WHERE subject_id = ? AND attendance_date = ? AND period_number = ?");
            mysqli_stmt_bind_param($check_stmt, "isi", $subject_id, $attendance_date, $period_number);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $existing_count = mysqli_fetch_assoc($check_result)['count'];
            mysqli_stmt_close($check_stmt);
            
            if ($existing_count > 0) {
                $error = "Attendance already marked for this subject, date, and period!";
                break;
            }
            
            // Get attendance data from form
            $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : array();
            $remarks_data = isset($_POST['remarks']) ? $_POST['remarks'] : array();
            
            if (empty($attendance_data)) {
                $error = "No attendance data received. Please mark attendance for students and try again.";
                break;
            }
            
            // Begin transaction for data integrity
            mysqli_autocommit($conn, FALSE);
            
            $marked_count = 0;
            $present_count = 0;
            $absent_count = 0;
            $late_count = 0;
            $success = true;
            
            try {
                // Process each student's attendance
                foreach ($attendance_data as $student_id => $status) {
                    $student_id = (int)$student_id;
                    $status = validateInput($status);
                    $remarks = isset($remarks_data[$student_id]) ? validateInput($remarks_data[$student_id]) : '';
                    
                    // Validate status
                    if (!in_array($status, ['present', 'absent', 'late'])) {
                        continue; // Skip invalid status
                    }
                    
                    // Verify student exists and belongs to the section
                    $verify_stmt = mysqli_prepare($conn, "SELECT student_id FROM students WHERE student_id = ? AND section_id = ? AND student_status = 'active'");
                    mysqli_stmt_bind_param($verify_stmt, "ii", $student_id, $section_id);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);
                    
                    if (mysqli_num_rows($verify_result) > 0) {
                        // Insert attendance record
                        $insert_stmt = mysqli_prepare($conn, "INSERT INTO attendance (student_id, subject_id, faculty_id, attendance_date, period_number, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($insert_stmt, "iiiisss", $student_id, $subject_id, $faculty_id, $attendance_date, $period_number, $status, $remarks);
                        
                        if (mysqli_stmt_execute($insert_stmt)) {
                            $marked_count++;
                            switch($status) {
                                case 'present': $present_count++; break;
                                case 'absent': $absent_count++; break;
                                case 'late': $late_count++; break;
                            }
                        } else {
                            $success = false;
                            $error = "Error inserting attendance for student ID $student_id: " . mysqli_error($conn);
                            break;
                        }
                        mysqli_stmt_close($insert_stmt);
                    } else {
                        // Student not found or not in section
                        $error = "Student ID $student_id not found in the selected section.";
                        $success = false;
                        break;
                    }
                    mysqli_stmt_close($verify_stmt);
                }
                
                if ($success && $marked_count > 0) {
                    // Commit transaction
                    mysqli_commit($conn);
                    $message = "Attendance marked successfully for $marked_count students. Present: $present_count, Absent: $absent_count, Late: $late_count";
                } else if ($success && $marked_count == 0) {
                    mysqli_rollback($conn);
                    $error = "No valid students found to mark attendance.";
                } else {
                    // Rollback on error
                    mysqli_rollback($conn);
                    if (empty($error)) {
                        $error = "Error occurred while marking attendance.";
                    }
                }
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Database error: " . $e->getMessage();
            }
            
            // Restore autocommit
            mysqli_autocommit($conn, TRUE);
            break;
    }
}

// Get filter parameters
$subject_filter = isset($_GET['subject']) ? $_GET['subject'] : '';
$section_filter = isset($_GET['section']) ? $_GET['section'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Build attendance query based on user type
$where_conditions = array();
$params = array();
$param_types = '';

if ($user_type == 'faculty' && $current_faculty_id) {
    $where_conditions[] = "a.faculty_id = ?";
    $params[] = $current_faculty_id;
    $param_types .= 'i';
} elseif ($user_type == 'student' && $current_student_id) {
    $where_conditions[] = "a.student_id = ?";
    $params[] = $current_student_id;
    $param_types .= 'i';
}

if (!empty($subject_filter)) {
    $where_conditions[] = "a.subject_id = ?";
    $params[] = $subject_filter;
    $param_types .= 'i';
}

if (!empty($section_filter)) {
    $where_conditions[] = "s.section_id = ?";
    $params[] = $section_filter;
    $param_types .= 'i';
}

if (!empty($date_filter)) {
    $where_conditions[] = "a.attendance_date = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get attendance records
$query = "SELECT a.*, st.first_name, st.last_name, st.admission_number, st.roll_number,
          sub.subject_name, sub.subject_code, f.first_name as faculty_first_name, f.last_name as faculty_last_name,
          cs.section_name, p.programme_code
          FROM attendance a
          JOIN students st ON a.student_id = st.student_id
          JOIN subjects sub ON a.subject_id = sub.subject_id
          JOIN faculty f ON a.faculty_id = f.faculty_id
          JOIN class_section cs ON st.section_id = cs.section_id
          JOIN programme p ON cs.programme_id = p.programme_id
          $where_clause
          ORDER BY a.attendance_date DESC, a.period_number DESC, st.first_name";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$attendance_records = mysqli_stmt_get_result($stmt);

// Get data for dropdowns
if (in_array($user_type, ['admin', 'hod', 'faculty'])) {
    if ($user_type == 'faculty' && $current_faculty_id > 0) {
        // Get subjects assigned to this faculty
        $subjects = mysqli_query($conn, "SELECT DISTINCT s.*, p.programme_name FROM subjects s 
                                       JOIN subject_faculty sf ON s.subject_id = sf.subject_id 
                                       JOIN programme p ON s.programme_id = p.programme_id
                                       WHERE sf.faculty_id = $current_faculty_id AND sf.status = 'active'
                                       ORDER BY s.subject_name");
        
        // Get sections assigned to this faculty
        $sections = mysqli_query($conn, "SELECT DISTINCT cs.*, p.programme_name FROM class_section cs 
                                       JOIN subject_faculty sf ON cs.section_id = sf.section_id 
                                       JOIN programme p ON cs.programme_id = p.programme_id
                                       WHERE sf.faculty_id = $current_faculty_id AND sf.status = 'active'
                                       ORDER BY p.programme_name, cs.section_name");
    } else {
        // For admin/hod or if faculty not found, show all subjects and sections
        $subjects = mysqli_query($conn, "SELECT s.*, p.programme_name FROM subjects s 
                                       JOIN programme p ON s.programme_id = p.programme_id 
                                       WHERE s.status = 'active' ORDER BY s.subject_name");
        
        $sections = mysqli_query($conn, "SELECT cs.*, p.programme_name FROM class_section cs 
                                       JOIN programme p ON cs.programme_id = p.programme_id 
                                       WHERE cs.status = 'active' ORDER BY p.programme_name, cs.section_name");
    }
    
    // Check if faculty has any assignments
    if ($user_type == 'faculty' && $current_faculty_id > 0) {
        $assignment_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM subject_faculty WHERE faculty_id = $current_faculty_id AND status = 'active'");
        $assignment_result = mysqli_fetch_assoc($assignment_check);
        $has_assignments = $assignment_result['count'] > 0;
    } else {
        $has_assignments = true; // Admin/HOD always have access
    }
}
?>

<!-- Messages -->
<?php if (!empty($message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Mark Attendance Section (Faculty/Admin/HOD only) -->
<?php if (in_array($user_type, ['admin', 'hod', 'faculty'])): ?>

<?php if ($user_type == 'faculty' && $current_faculty_id == 0): ?>
<!-- Faculty Profile Not Found -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Faculty Profile Not Found!</strong> 
            Your faculty profile is not properly set up. Please contact the administrator to create your faculty profile.
        </div>
    </div>
</div>
<?php elseif ($user_type == 'faculty' && isset($has_assignments) && !$has_assignments): ?>
<!-- No Subject Assignments -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>No Subject Assignments Found!</strong> 
            You are not currently assigned to any subjects. Please contact the HOD or administrator to assign subjects to you.
        </div>
    </div>
</div>
<?php else: ?>
<!-- Normal Mark Attendance Form -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clipboard-check"></i> Mark Attendance</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="attendance_form">
                    <input type="hidden" name="page" value="attendance">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="mark_subject" class="form-label">Subject *</label>
                            <select class="form-select" id="mark_subject" name="mark_subject" required>
                                <option value="">Select Subject</option>
                                <?php 
                                if (isset($subjects) && mysqli_num_rows($subjects) > 0) {
                                    mysqli_data_seek($subjects, 0);
                                    while ($subject = mysqli_fetch_assoc($subjects)): 
                                ?>
                                <option value="<?php echo $subject['subject_id']; ?>">
                                    <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                                </option>
                                <?php endwhile; 
                                } else {
                                    echo '<option value="">No subjects available</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="mark_section" class="form-label">Section *</label>
                            <select class="form-select" id="mark_section" name="mark_section" required>
                                <option value="">Select Section</option>
                                <?php 
                                if (isset($sections) && mysqli_num_rows($sections) > 0) {
                                    mysqli_data_seek($sections, 0);
                                    while ($section = mysqli_fetch_assoc($sections)): 
                                ?>
                                <option value="<?php echo $section['section_id']; ?>">
                                    <?php echo $section['programme_name'] . ' - Section ' . $section['section_name']; ?>
                                </option>
                                <?php endwhile; 
                                } else {
                                    echo '<option value="">No sections available</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="mark_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="mark_date" name="mark_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label for="mark_period" class="form-label">Period *</label>
                            <select class="form-select" id="mark_period" name="mark_period" required>
                                <option value="">Period</option>
                                <option value="1">Period 1</option>
                                <option value="2">Period 2</option>
                                <option value="3">Period 3</option>
                                <option value="4">Period 4</option>
                                <option value="5">Period 5</option>
                                <option value="6">Period 6</option>
                                <option value="7">Period 7</option>
                                <option value="8">Period 8</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-primary d-block" onclick="loadStudentsForAttendance()">
                                <i class="fas fa-users"></i> Load Students
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Students List for Attendance -->
                <div id="students_attendance_list" class="mt-4" style="display: none;">
                    <!-- Students will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-filter"></i> View Attendance Records</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="attendance">
            <div class="row">
                <?php if (in_array($user_type, ['admin', 'hod'])): ?>
                <div class="col-md-3">
                    <select class="form-select" name="subject">
                        <option value="">All Subjects</option>
                        <?php 
                        if (isset($subjects)) {
                            mysqli_data_seek($subjects, 0);
                            while ($subject = mysqli_fetch_assoc($subjects)): 
                        ?>
                        <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subject_filter == $subject['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                        </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="section">
                        <option value="">All Sections</option>
                        <?php 
                        if (isset($sections)) {
                            mysqli_data_seek($sections, 0);
                            while ($section = mysqli_fetch_assoc($sections)): 
                        ?>
                        <option value="<?php echo $section['section_id']; ?>" <?php echo $section_filter == $section['section_id'] ? 'selected' : ''; ?>>
                            <?php echo $section['programme_name'] . ' - Section ' . $section['section_name']; ?>
                        </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="dashboard.php?page=attendance" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Records -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Attendance Records</h5>
                <?php if ($user_type == 'student'): ?>
                <div>
                    <span class="badge bg-info">My Attendance Records</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Period</th>
                                <?php if ($user_type != 'student'): ?>
                                <th>Student</th>
                                <th>Admission No</th>
                                <?php endif; ?>
                                <th>Subject</th>
                                <th>Section</th>
                                <?php if ($user_type != 'faculty'): ?>
                                <th>Faculty</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($attendance_records) == 0): ?>
                            <tr>
                                <td colspan="<?php echo ($user_type == 'student') ? '6' : (($user_type == 'faculty') ? '7' : '9'); ?>" class="text-center">
                                    <div class="py-4">
                                        <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                        <h6 class="text-muted">No attendance records found</h6>
                                        <p class="text-muted mb-0">
                                            <?php if (in_array($user_type, ['admin', 'hod', 'faculty'])): ?>
                                                Try adjusting the filters or mark attendance for students.
                                            <?php else: ?>
                                                Attendance records will appear here once your faculty marks attendance.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php while ($record = mysqli_fetch_assoc($attendance_records)): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                <td>
                                    <span class="badge bg-primary">Period <?php echo $record['period_number']; ?></span>
                                </td>
                                <?php if ($user_type != 'student'): ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['roll_number']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($record['admission_number']); ?></span>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($record['subject_code']); ?></span>
                                    <br>
                                    <small><?php echo htmlspecialchars($record['subject_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-dark"><?php echo htmlspecialchars($record['programme_code'] . '-' . $record['section_name']); ?></span>
                                </td>
                                <?php if ($user_type != 'faculty'): ?>
                                <td><?php echo htmlspecialchars($record['faculty_first_name'] . ' ' . $record['faculty_last_name']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            switch($record['status']) {
                                                case 'present': echo 'bg-success'; break;
                                                case 'absent': echo 'bg-danger'; break;
                                                case 'late': echo 'bg-warning'; break;
                                                default: echo 'bg-secondary'; break;
                                            }
                                        ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($record['remarks']) ?: '-'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function loadStudentsForAttendance() {
    const subject = document.getElementById('mark_subject').value;
    const section = document.getElementById('mark_section').value;
    const date = document.getElementById('mark_date').value;
    const period = document.getElementById('mark_period').value;
    
    if (!subject || !section || !date || !period) {
        alert('Please select all required fields first.');
        return;
    }
    
    // Show loading
    const container = document.getElementById('students_attendance_list');
    container.style.display = 'block';
    container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading students...</div>';
    
    // Fetch students
    fetch('get_students_for_attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `subject_id=${subject}&section_id=${section}&date=${date}&period=${period}`
    })
    .then(response => response.text())
    .then(data => {
        container.innerHTML = data;
    })
    .catch(error => {
        container.innerHTML = '<div class="alert alert-danger">Error loading students. Please try again.</div>';
    });
}

function markAllPresent() {
    const checkboxes = document.querySelectorAll('input[name^="attendance"]');
    checkboxes.forEach(checkbox => {
        if (checkbox.value === 'present') {
            checkbox.checked = true;
        }
    });
}

function markAllAbsent() {
    const checkboxes = document.querySelectorAll('input[name^="attendance"]');
    checkboxes.forEach(checkbox => {
        if (checkbox.value === 'absent') {
            checkbox.checked = true;
        }
    });
}

function toggleAttendance(studentId) {
    const presentRadio = document.querySelector(`input[name="attendance[${studentId}]"][value="present"]`);
    const absentRadio = document.querySelector(`input[name="attendance[${studentId}]"][value="absent"]`);
    const lateRadio = document.querySelector(`input[name="attendance[${studentId}]"][value="late"]`);
    
    // Toggle logic: present -> absent -> late -> present
    if (presentRadio.checked) {
        absentRadio.checked = true;
    } else if (absentRadio.checked) {
        lateRadio.checked = true;
    } else {
        presentRadio.checked = true;
    }
}

function submitAttendance() {
    const form = document.getElementById('attendance_mark_form');
    if (!form) {
        alert('No students loaded for attendance marking.');
        return;
    }
    
    // Validate that at least one attendance is marked
    const attendanceInputs = form.querySelectorAll('input[name^="attendance"]:checked');
    if (attendanceInputs.length === 0) {
        alert('Please mark attendance for at least one student.');
        return;
    }
    
    // Debug: Log form data
    const formData = new FormData(form);
    console.log('Form data being submitted:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Show confirmation
    const totalStudents = form.querySelectorAll('.student-row').length;
    const markedStudents = attendanceInputs.length;
    
    if (markedStudents < totalStudents) {
        if (!confirm(`You have marked attendance for ${markedStudents} out of ${totalStudents} students. Unmarked students will be marked as absent. Continue?`)) {
            return;
        }
    }
    
    // Add a loading indicator
    const submitBtn = document.querySelector('button[onclick="submitAttendance()"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;
    
    // Submit form
    setTimeout(() => {
        form.submit();
    }, 500);
}

// Auto-save functionality (optional)
function autoSaveAttendance() {
    const form = document.getElementById('attendance_mark_form');
    if (!form) return;
    
    const formData = new FormData(form);
    formData.append('action', 'auto_save');
    
    fetch('auto_save_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show subtle success indicator
            const indicator = document.getElementById('auto_save_indicator');
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-check text-success"></i> Auto-saved';
                setTimeout(() => {
                    indicator.innerHTML = '';
                }, 2000);
            }
        }
    })
    .catch(error => {
        console.log('Auto-save failed:', error);
    });
}

// Attendance statistics
function updateAttendanceStats() {
    const presentCount = document.querySelectorAll('input[name^="attendance"][value="present"]:checked').length;
    const absentCount = document.querySelectorAll('input[name^="attendance"][value="absent"]:checked').length;
    const lateCount = document.querySelectorAll('input[name^="attendance"][value="late"]:checked').length;
    const totalStudents = document.querySelectorAll('.student-row').length;
    
    const statsContainer = document.getElementById('attendance_stats');
    if (statsContainer) {
        const percentage = totalStudents > 0 ? Math.round((presentCount / totalStudents) * 100) : 0;
        
        statsContainer.innerHTML = `
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>${presentCount}</h5>
                            <small>Present</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5>${absentCount}</h5>
                            <small>Absent</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5>${lateCount}</h5>
                            <small>Late</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5>${percentage}%</h5>
                            <small>Attendance</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
}

// Search students in attendance list
function searchStudents() {
    const searchTerm = document.getElementById('student_search').value.toLowerCase();
    const studentRows = document.querySelectorAll('.student-row');
    
    studentRows.forEach(row => {
        const studentName = row.querySelector('.student-name').textContent.toLowerCase();
        const admissionNo = row.querySelector('.admission-no').textContent.toLowerCase();
        const rollNo = row.querySelector('.roll-no').textContent.toLowerCase();
        
        if (studentName.includes(searchTerm) || admissionNo.includes(searchTerm) || rollNo.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add change listeners to attendance inputs for real-time stats
    document.addEventListener('change', function(e) {
        if (e.target.name && e.target.name.startsWith('attendance[')) {
            updateAttendanceStats();
        }
    });
    
    // Auto-save every 30 seconds
    setInterval(autoSaveAttendance, 30000);
    
    // Prevent accidental page leave
    let attendanceChanged = false;
    document.addEventListener('change', function(e) {
        if (e.target.name && e.target.name.startsWith('attendance[')) {
            attendanceChanged = true;
        }
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (attendanceChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved attendance changes. Are you sure you want to leave?';
        }
    });
    
    // Clear the flag when form is submitted
    document.addEventListener('submit', function(e) {
        if (e.target.id === 'attendance_mark_form') {
            attendanceChanged = false;
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + A for mark all present
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        markAllPresent();
        updateAttendanceStats();
    }
    
    // Ctrl + D for mark all absent
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        markAllAbsent();
        updateAttendanceStats();
    }
    
    // Ctrl + S for submit attendance
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        submitAttendance();
    }
});

// Export attendance data
function exportAttendance() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'csv');
    window.location.href = currentUrl.toString();
}

// Print attendance sheet
function printAttendanceSheet() {
    const subject = document.getElementById('mark_subject').value;
    const section = document.getElementById('mark_section').value;
    const date = document.getElementById('mark_date').value;
    const period = document.getElementById('mark_period').value;
    
    if (!subject || !section || !date || !period) {
        alert('Please select all fields to print attendance sheet.');
        return;
    }
    
    const printUrl = `print_attendance_sheet.php?subject=${subject}&section=${section}&date=${date}&period=${period}`;
    window.open(printUrl, '_blank');
}
</script>