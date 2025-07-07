<?php
global $conn;

$message = '';
$error = '';

// Get current student data
if ($user_type != 'student') {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            Access denied. This section is only available to students.
          </div>';
    return;
}

// Get student information
$stmt = mysqli_prepare($conn, "SELECT s.*, p.programme_name, p.programme_code, b.batch_name, 
                              sem.semester_name, cs.section_name
                              FROM students s
                              LEFT JOIN programme p ON s.programme_id = p.programme_id
                              LEFT JOIN batch b ON s.batch_id = b.batch_id
                              LEFT JOIN semester sem ON s.semester_id = sem.semester_id
                              LEFT JOIN class_section cs ON s.section_id = cs.section_id
                              WHERE s.user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student_data = mysqli_fetch_assoc($result);

if (!$student_data) {
    echo '<div class="alert alert-danger">Student profile not found.</div>';
    return;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_profile') {
        $mobile_number = validateInput($_POST['mobile_number']);
        $email = validateInput($_POST['email']);
        $address = validateInput($_POST['address']);
        $district = validateInput($_POST['district']);
        $state = validateInput($_POST['state']);
        $pincode = validateInput($_POST['pincode']);
        
        $stmt = mysqli_prepare($conn, "UPDATE students SET mobile_number = ?, email = ?, address = ?, district = ?, state = ?, pincode = ? WHERE student_id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssi", $mobile_number, $email, $address, $district, $state, $pincode, $student_data['student_id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Profile updated successfully!";
            // Refresh student data
            $stmt = mysqli_prepare($conn, "SELECT s.*, p.programme_name, p.programme_code, b.batch_name, 
                                          sem.semester_name, cs.section_name
                                          FROM students s
                                          LEFT JOIN programme p ON s.programme_id = p.programme_id
                                          LEFT JOIN batch b ON s.batch_id = b.batch_id
                                          LEFT JOIN semester sem ON s.semester_id = sem.semester_id
                                          LEFT JOIN class_section cs ON s.section_id = cs.section_id
                                          WHERE s.user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $student_data = mysqli_fetch_assoc($result);
        } else {
            $error = "Error updating profile: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get attendance statistics
$stmt = mysqli_prepare($conn, "SELECT 
                              COUNT(*) as total_classes,
                              SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_classes,
                              SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_classes,
                              SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_classes
                              FROM attendance WHERE student_id = ?");
mysqli_stmt_bind_param($stmt, "i", $student_data['student_id']);
mysqli_stmt_execute($stmt);
$attendance_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$attendance_percentage = $attendance_stats['total_classes'] > 0 ? 
    round(($attendance_stats['present_classes'] / $attendance_stats['total_classes']) * 100, 2) : 0;

// Get subject-wise attendance
$stmt = mysqli_prepare($conn, "SELECT 
                              sub.subject_code,
                              sub.subject_name,
                              COUNT(*) as total_classes,
                              SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_classes,
                              ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                              FROM attendance a
                              JOIN subjects sub ON a.subject_id = sub.subject_id
                              WHERE a.student_id = ?
                              GROUP BY sub.subject_id
                              ORDER BY sub.subject_name");
mysqli_stmt_bind_param($stmt, "i", $student_data['student_id']);
mysqli_stmt_execute($stmt);
$subject_attendance = mysqli_stmt_get_result($stmt);
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

<!-- Student Profile Overview -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user"></i> Profile Overview</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h4><?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($student_data['admission_number']); ?></p>
                <span class="badge <?php echo $student_data['student_status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?> mb-3">
                    <?php echo ucfirst($student_data['student_status']); ?>
                </span>
                
                <div class="row text-center">
                    <div class="col-4">
                        <h6 class="mb-0"><?php echo $attendance_stats['total_classes']; ?></h6>
                        <small class="text-muted">Total Classes</small>
                    </div>
                    <div class="col-4">
                        <h6 class="mb-0"><?php echo $attendance_stats['present_classes']; ?></h6>
                        <small class="text-muted">Present</small>
                    </div>
                    <div class="col-4">
                        <h6 class="mb-0 <?php echo $attendance_percentage >= 75 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $attendance_percentage; ?>%
                        </h6>
                        <small class="text-muted">Attendance</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Attendance Overview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="attendanceChart" width="300" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo $attendance_stats['present_classes']; ?></h4>
                                        <small>Present</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo $attendance_stats['absent_classes']; ?></h4>
                                        <small>Absent</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo $attendance_stats['late_classes']; ?></h4>
                                        <small>Late</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo $attendance_percentage; ?>%</h4>
                                        <small>Overall</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Personal Information -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-id-card"></i> Personal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Full Name:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['first_name'] . ' ' . ($student_data['middle_name'] ? $student_data['middle_name'] . ' ' : '') . $student_data['last_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Father's Name:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['father_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Mother's Name:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['mother_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date of Birth:</strong></td>
                        <td><?php echo date('F j, Y', strtotime($student_data['date_of_birth'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Gender:</strong></td>
                        <td><?php echo ucfirst($student_data['gender']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Category:</strong></td>
                        <td><?php echo strtoupper($student_data['category']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Religion:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['religion']) ?: 'Not specified'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Aadhar Number:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['aadhar_number']) ?: 'Not provided'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Academic Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Admission Number:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['admission_number']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Roll Number:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['roll_number']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Programme:</strong></td>
                        <td>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($student_data['programme_code']); ?></span>
                            <?php echo htmlspecialchars($student_data['programme_name']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Batch:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['batch_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Semester:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['semester_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Section:</strong></td>
                        <td><?php echo htmlspecialchars($student_data['section_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Admission Date:</strong></td>
                        <td><?php echo $student_data['admission_date'] ? date('F j, Y', strtotime($student_data['admission_date'])) : 'Not specified'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge <?php echo $student_data['student_status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($student_data['student_status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Contact Information (Editable) -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-address-book"></i> Contact Information</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editContactModal">
                    <i class="fas fa-edit"></i> Edit Contact Info
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Mobile Number:</strong></td>
                                <td><?php echo htmlspecialchars($student_data['mobile_number']) ?: 'Not provided'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo htmlspecialchars($student_data['email']) ?: 'Not provided'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Address:</strong></td>
                                <td><?php echo htmlspecialchars($student_data['address']) ?: 'Not provided'; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>District:</strong></td>
                                <td><?php echo htmlspecialchars($student_data['district']) ?: 'Not specified'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>State:</strong></td>
                                <td><?php echo htmlspecialchars($student_data['state']) ?: 'Not specified'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Pincode:</strong></td>
                                <td><?php echo htmlspecialchars($student_data['pincode']) ?: 'Not specified'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject-wise Attendance -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-books"></i> Subject-wise Attendance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Total Classes</th>
                                <th>Present</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($subject_attendance) == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="py-4">
                                        <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                        <h6 class="text-muted">No attendance records found</h6>
                                        <p class="text-muted mb-0">Your attendance will appear here once faculty starts marking.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php while ($subject = mysqli_fetch_assoc($subject_attendance)): ?>
                            <tr>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($subject['subject_code']); ?></span></td>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo $subject['total_classes']; ?></td>
                                <td><?php echo $subject['present_classes']; ?></td>
                                <td>
                                    <span class="badge <?php echo $subject['percentage'] >= 75 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $subject['percentage']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if ($subject['percentage'] >= 75): ?>
                                        <span class="badge bg-success">Good</span>
                                    <?php elseif ($subject['percentage'] >= 65): ?>
                                        <span class="badge bg-warning">Needs Improvement</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Critical</span>
                                    <?php endif; ?>
                                </td>
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

<!-- Edit Contact Modal -->
<div class="modal fade" id="editContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Contact Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label for="mobile_number" class="form-label">Mobile Number</label>
                        <input type="tel" class="form-control" id="mobile_number" name="mobile_number" 
                               value="<?php echo htmlspecialchars($student_data['mobile_number']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($student_data['email']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student_data['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="district" class="form-label">District</label>
                                <input type="text" class="form-control" id="district" name="district" 
                                       value="<?php echo htmlspecialchars($student_data['district']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo htmlspecialchars($student_data['state']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="pincode" name="pincode" 
                                       value="<?php echo htmlspecialchars($student_data['pincode']); ?>" maxlength="6">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Information</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Pie Chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
const attendanceChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent', 'Late'],
        datasets: [{
            data: [<?php echo $attendance_stats['present_classes']; ?>, 
                   <?php echo $attendance_stats['absent_classes']; ?>, 
                   <?php echo $attendance_stats['late_classes']; ?>],
            backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const mobileInput = document.getElementById('mobile_number');
    const pincodeInput = document.getElementById('pincode');
    
    if (mobileInput) {
        mobileInput.addEventListener('input', function() {
            const mobile = this.value;
            if (mobile && !/^[6-9]\d{9}$/.test(mobile)) {
                this.setCustomValidity('Please enter a valid 10-digit mobile number starting with 6-9');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    if (pincodeInput) {
        pincodeInput.addEventListener('input', function() {
            const pincode = this.value;
            if (pincode && !/^\d{6}$/.test(pincode)) {
                this.setCustomValidity('Please enter a valid 6-digit pincode');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});
</script>