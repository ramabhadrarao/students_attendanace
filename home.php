<?php
// Get statistics based on user type
$stats = array();

if (in_array($user_type, ['admin', 'hod'])) {
    // Total Students
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM students WHERE student_status = 'active'");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total_students'] = mysqli_fetch_assoc($result)['total'];
    
    // Total Faculty
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM faculty WHERE status = 'active'");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total_faculty'] = mysqli_fetch_assoc($result)['total'];
    
    // Total Programmes
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM programme WHERE status = 'active'");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total_programmes'] = mysqli_fetch_assoc($result)['total'];
    
    // Total Sections
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM class_section WHERE status = 'active'");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['total_sections'] = mysqli_fetch_assoc($result)['total'];
    
    // Student Status Distribution
    $stmt = mysqli_prepare($conn, "SELECT student_status, COUNT(*) as count FROM students GROUP BY student_status");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_status_data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $student_status_data[$row['student_status']] = $row['count'];
    }
    
} elseif ($user_type == 'faculty') {
    // Get faculty-specific data
    $stmt = mysqli_prepare($conn, "SELECT faculty_id FROM faculty WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $faculty_data = mysqli_fetch_assoc($result);
    
    if ($faculty_data) {
        $faculty_id = $faculty_data['faculty_id'];
        
        // Students under this faculty
        $stmt = mysqli_prepare($conn, "SELECT COUNT(DISTINCT s.student_id) as total 
                                     FROM students s 
                                     JOIN subject_faculty sf ON s.section_id = sf.section_id 
                                     WHERE sf.faculty_id = ? AND s.student_status = 'active'");
        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['my_students'] = mysqli_fetch_assoc($result)['total'];
        
        // Subjects taught
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM subject_faculty WHERE faculty_id = ? AND status = 'active'");
        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['my_subjects'] = mysqli_fetch_assoc($result)['total'];
    }
    
} elseif ($user_type == 'student') {
    // Get student-specific data
    $stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_data = mysqli_fetch_assoc($result);
    
    if ($student_data) {
        // Get attendance percentage
        $stmt = mysqli_prepare($conn, "SELECT 
                                     COUNT(*) as total_classes,
                                     SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_classes
                                     FROM attendance WHERE student_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $student_data['student_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $attendance_data = mysqli_fetch_assoc($result);
        
        $stats['attendance_percentage'] = $attendance_data['total_classes'] > 0 ? 
            round(($attendance_data['present_classes'] / $attendance_data['total_classes']) * 100, 2) : 0;
        $stats['total_classes'] = $attendance_data['total_classes'];
        $stats['present_classes'] = $attendance_data['present_classes'];
    }
}

// Get recent activities
$recent_activities = array();
$stmt = mysqli_prepare($conn, "SELECT 
                              a.attendance_date,
                              s.first_name,
                              s.last_name,
                              sub.subject_name,
                              a.status
                              FROM attendance a
                              JOIN students s ON a.student_id = s.student_id
                              JOIN subjects sub ON a.subject_id = sub.subject_id
                              ORDER BY a.attendance_date DESC, a.attendance_id DESC
                              LIMIT 10");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $recent_activities[] = $row;
}
?>

<div class="row">
    <?php if (in_array($user_type, ['admin', 'hod'])): ?>
    <!-- Admin/HOD Statistics -->
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-user-graduate fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['total_students']); ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['total_faculty']); ?></h3>
            <p>Total Faculty</p>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-graduation-cap fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['total_programmes']); ?></h3>
            <p>Total Programmes</p>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-users fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['total_sections']); ?></h3>
            <p>Total Sections</p>
        </div>
    </div>
    
    <?php elseif ($user_type == 'faculty'): ?>
    <!-- Faculty Statistics -->
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-user-graduate fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['my_students']); ?></h3>
            <p>My Students</p>
        </div>
    </div>
    
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-book-open fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['my_subjects']); ?></h3>
            <p>Subjects Teaching</p>
        </div>
    </div>
    
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-calendar-day fa-2x mb-2"></i>
            <h3><?php echo date('d'); ?></h3>
            <p>Today's Date</p>
        </div>
    </div>
    
    <?php elseif ($user_type == 'student'): ?>
    <!-- Student Statistics -->
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-percentage fa-2x mb-2"></i>
            <h3><?php echo $stats['attendance_percentage']; ?>%</h3>
            <p>Attendance</p>
        </div>
    </div>
    
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-check-circle fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['present_classes']); ?></h3>
            <p>Classes Attended</p>
        </div>
    </div>
    
    <div class="col-md-4 col-sm-6 mb-4">
        <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-book fa-2x mb-2"></i>
            <h3><?php echo number_format($stats['total_classes']); ?></h3>
            <p>Total Classes</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Student Status Distribution (Admin/HOD only) -->
<?php if (in_array($user_type, ['admin', 'hod']) && !empty($student_status_data)): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Student Status Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($student_status_data as $status => $count): ?>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="text-center">
                            <div class="badge 
                                <?php 
                                    switch($status) {
                                        case 'active': echo 'bg-success'; break;
                                        case 'detained': echo 'bg-warning'; break;
                                        case 'dropout': echo 'bg-danger'; break;
                                        case 'passout': echo 'bg-info'; break;
                                        case 'transferred': echo 'bg-secondary'; break;
                                        default: echo 'bg-primary'; break;
                                    }
                                ?> 
                                fs-6 px-3 py-2 mb-2 d-block">
                                <?php echo number_format($count); ?>
                            </div>
                            <small class="text-muted"><?php echo ucfirst($status); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activities -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No recent activities found</h5>
                    <p class="text-muted">Activities will appear here as attendance is recorded.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student Name</th>
                                <th>Subject</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($activity['attendance_date'])); ?></td>
                                <td><?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?></td>
                                <td><?php echo $activity['subject_name']; ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            switch($activity['status']) {
                                                case 'present': echo 'bg-success'; break;
                                                case 'absent': echo 'bg-danger'; break;
                                                case 'late': echo 'bg-warning'; break;
                                                default: echo 'bg-secondary'; break;
                                            }
                                        ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (in_array($user_type, ['admin', 'hod'])): ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="dashboard.php?page=students" class="btn btn-primary btn-block">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="dashboard.php?page=faculty" class="btn btn-primary btn-block">
                            <i class="fas fa-user-tie"></i> Add Faculty
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="dashboard.php?page=programme" class="btn btn-primary btn-block">
                            <i class="fas fa-graduation-cap"></i> Add Programme
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="dashboard.php?page=reports" class="btn btn-primary btn-block">
                            <i class="fas fa-chart-bar"></i> View Reports
                        </a>
                    </div>
                    <?php elseif ($user_type == 'faculty'): ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="dashboard.php?page=attendance" class="btn btn-primary btn-block">
                            <i class="fas fa-clipboard-check"></i> Mark Attendance
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="dashboard.php?page=students" class="btn btn-primary btn-block">
                            <i class="fas fa-users"></i> View Students
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="dashboard.php?page=reports" class="btn btn-primary btn-block">
                            <i class="fas fa-chart-line"></i> View Reports
                        </a>
                    </div>
                    <?php elseif ($user_type == 'student'): ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="dashboard.php?page=attendance" class="btn btn-primary btn-block">
                            <i class="fas fa-eye"></i> View Attendance
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="dashboard.php?page=profile" class="btn btn-primary btn-block">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <a href="#" class="btn btn-primary btn-block">
                            <i class="fas fa-download"></i> Download Report
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>