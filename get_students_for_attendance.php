<?php
include 'config.php';

// Check if user is logged in and has proper permissions
if (!isLoggedIn() || !in_array(getUserType(), ['admin', 'hod', 'faculty'])) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit();
}

$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$date = isset($_POST['date']) ? validateInput($_POST['date']) : '';
$period = isset($_POST['period']) ? (int)$_POST['period'] : 0;

if ($subject_id <= 0 || $section_id <= 0 || empty($date) || $period <= 0) {
    echo '<div class="alert alert-danger">Invalid parameters provided.</div>';
    exit();
}

// Check if attendance already exists for this date and period
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM attendance WHERE subject_id = ? AND attendance_date = ? AND period_number = ?");
mysqli_stmt_bind_param($stmt, "isi", $subject_id, $date, $period);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$existing_count = mysqli_fetch_assoc($result)['count'];

if ($existing_count > 0) {
    echo '<div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Warning:</strong> Attendance has already been marked for this subject, date, and period.
            You can view the existing records in the attendance list below.
          </div>';
    mysqli_stmt_close($stmt);
    exit();
}

// Get section and subject information
$stmt = mysqli_prepare($conn, "SELECT cs.*, p.programme_name, p.programme_code, 
                              s.subject_name, s.subject_code
                              FROM class_section cs 
                              JOIN programme p ON cs.programme_id = p.programme_id 
                              JOIN subjects s ON s.subject_id = ?
                              WHERE cs.section_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $subject_id, $section_id);
mysqli_stmt_execute($stmt);
$info_result = mysqli_stmt_get_result($stmt);
$section_info = mysqli_fetch_assoc($info_result);

if (!$section_info) {
    echo '<div class="alert alert-danger">Section or subject not found.</div>';
    mysqli_stmt_close($stmt);
    exit();
}

// Get students in the section
$stmt = mysqli_prepare($conn, "SELECT s.student_id, s.admission_number, s.roll_number, 
                              s.first_name, s.last_name, p.programme_code 
                              FROM students s 
                              JOIN class_section cs ON s.section_id = cs.section_id
                              JOIN programme p ON cs.programme_id = p.programme_id
                              WHERE s.section_id = ? AND s.student_status = 'active'
                              ORDER BY s.roll_number, s.first_name, s.last_name");
mysqli_stmt_bind_param($stmt, "i", $section_id);
mysqli_stmt_execute($stmt);
$students = mysqli_stmt_get_result($stmt);
$student_count = mysqli_num_rows($students);

if ($student_count === 0) {
    echo '<div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            <strong>No Students Found!</strong>
            <br><br>
            No active students are enrolled in this section.
          </div>';
    mysqli_stmt_close($stmt);
    exit();
}
?>

<!-- Fixed form submission to attendance.php instead of debug -->
<form method="POST" action="dashboard.php?page=attendance" id="attendance_mark_form">
    <input type="hidden" name="action" value="mark_attendance">
    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
    <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
    <input type="hidden" name="attendance_date" value="<?php echo $date; ?>">
    <input type="hidden" name="period_number" value="<?php echo $period; ?>">
    
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Subject:</strong><br>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($section_info['subject_code']); ?></span>
                            <?php echo htmlspecialchars($section_info['subject_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Section:</strong><br>
                            <span class="badge bg-info"><?php echo htmlspecialchars($section_info['programme_code']); ?></span>
                            Section <?php echo htmlspecialchars($section_info['section_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Date:</strong><br>
                            <?php echo date('F j, Y', strtotime($date)); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Period:</strong><br>
                            <span class="badge bg-secondary">Period <?php echo $period; ?></span>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <small class="text-success">
                                <i class="fas fa-check-circle"></i> Found <?php echo $student_count; ?> active students in this section
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="btn-toolbar" role="toolbar">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-success btn-sm" onclick="markAllPresent(); updateAttendanceStats();">
                        <i class="fas fa-check-circle"></i> Mark All Present
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="markAllAbsent(); updateAttendanceStats();">
                        <i class="fas fa-times-circle"></i> Mark All Absent
                    </button>
                </div>
                <div class="btn-group me-2">
                    <input type="text" class="form-control form-control-sm" id="student_search" 
                           placeholder="Search students..." onkeyup="searchStudents()" style="width: 200px;">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Attendance Statistics -->
    <div id="attendance_stats" class="mb-3">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5>0</h5>
                        <small>Present</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5>0</h5>
                        <small>Absent</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5>0</h5>
                        <small>Late</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5>0%</h5>
                        <small>Attendance</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Students List -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th width="5%">#</th>
                    <th width="20%">Student Name</th>
                    <th width="15%">Admission No</th>
                    <th width="15%">Roll No</th>
                    <th width="25%">Attendance</th>
                    <th width="20%">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                while ($student = mysqli_fetch_assoc($students)): 
                ?>
                <tr class="student-row">
                    <td><?php echo $count++; ?></td>
                    <td>
                        <div class="student-name">
                            <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-secondary admission-no"><?php echo htmlspecialchars($student['admission_number']); ?></span>
                    </td>
                    <td>
                        <span class="badge bg-primary roll-no"><?php echo htmlspecialchars($student['roll_number']); ?></span>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['student_id']; ?>]" 
                                   id="present_<?php echo $student['student_id']; ?>" value="present" autocomplete="off">
                            <label class="btn btn-outline-success btn-sm" for="present_<?php echo $student['student_id']; ?>">
                                <i class="fas fa-check"></i> Present
                            </label>
                            
                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['student_id']; ?>]" 
                                   id="absent_<?php echo $student['student_id']; ?>" value="absent" autocomplete="off">
                            <label class="btn btn-outline-danger btn-sm" for="absent_<?php echo $student['student_id']; ?>">
                                <i class="fas fa-times"></i> Absent
                            </label>
                            
                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['student_id']; ?>]" 
                                   id="late_<?php echo $student['student_id']; ?>" value="late" autocomplete="off">
                            <label class="btn btn-outline-warning btn-sm" for="late_<?php echo $student['student_id']; ?>">
                                <i class="fas fa-clock"></i> Late
                            </label>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" 
                               name="remarks[<?php echo $student['student_id']; ?>]" 
                               placeholder="Optional remarks">
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Submit Section -->
    <div class="row mt-4">
        <div class="col-md-12 text-center">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Instructions:</strong> 
                Select attendance status for each student. Students with no selection will be marked as absent.
                Use keyboard shortcuts: Ctrl+A (Mark All Present), Ctrl+D (Mark All Absent), Ctrl+S (Submit).
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg me-2">
                <i class="fas fa-save"></i> Submit Attendance (<?php echo $student_count; ?> Students)
            </button>
            <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.reload();">
                <i class="fas fa-redo"></i> Reset Form
            </button>
        </div>
    </div>
</form>

<script>
// Initialize stats on load
document.addEventListener('DOMContentLoaded', function() {
    updateAttendanceStats();
    
    // Add change listeners to attendance inputs for real-time stats
    document.addEventListener('change', function(e) {
        if (e.target.name && e.target.name.startsWith('attendance[')) {
            updateAttendanceStats();
        }
    });
});

// Mark all students as present
function markAllPresent() {
    const radioButtons = document.querySelectorAll('input[name^="attendance"][value="present"]');
    radioButtons.forEach(radio => {
        radio.checked = true;
    });
}

// Mark all students as absent
function markAllAbsent() {
    const radioButtons = document.querySelectorAll('input[name^="attendance"][value="absent"]');
    radioButtons.forEach(radio => {
        radio.checked = true;
    });
}

// Update attendance statistics
function updateAttendanceStats() {
    const presentCount = document.querySelectorAll('input[name^="attendance"][value="present"]:checked').length;
    const absentCount = document.querySelectorAll('input[name^="attendance"][value="absent"]:checked').length;
    const lateCount = document.querySelectorAll('input[name^="attendance"][value="late"]:checked').length;
    const totalStudents = document.querySelectorAll('.student-row').length;
    
    const statsContainer = document.getElementById('attendance_stats');
    if (statsContainer) {
        const percentage = totalStudents > 0 ? Math.round(((presentCount + lateCount) / totalStudents) * 100) : 0;
        
        // Update the stats cards
        const cards = statsContainer.querySelectorAll('.card h5');
        if (cards.length >= 4) {
            cards[0].textContent = presentCount;  // Present
            cards[1].textContent = absentCount;   // Absent
            cards[2].textContent = lateCount;     // Late
            cards[3].textContent = percentage + '%'; // Percentage
        }
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

// Form validation before submission
document.getElementById('attendance_mark_form').addEventListener('submit', function(e) {
    const checkedInputs = this.querySelectorAll('input[name^="attendance"]:checked');
    
    if (checkedInputs.length === 0) {
        e.preventDefault();
        alert('Please mark attendance for at least one student before submitting.');
        return false;
    }
    
    // Show confirmation for partial attendance
    const totalStudents = this.querySelectorAll('.student-row').length;
    if (checkedInputs.length < totalStudents) {
        const unmarkedCount = totalStudents - checkedInputs.length;
        if (!confirm(`${unmarkedCount} students have no attendance marked. They will be marked as absent. Continue?`)) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        markAllPresent();
        updateAttendanceStats();
    } else if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        markAllAbsent();
        updateAttendanceStats();
    } else if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('attendance_mark_form').submit();
    }
});
</script>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>