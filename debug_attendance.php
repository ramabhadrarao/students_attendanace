<?php
include 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    exit();
}

echo '<h3>Debug: Attendance Submission</h3>';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo '<div class="card mb-3">';
    echo '<div class="card-header"><h5>POST Data Received:</h5></div>';
    echo '<div class="card-body">';
    echo '<pre>' . print_r($_POST, true) . '</pre>';
    echo '</div>';
    echo '</div>';
    
    // Check specific values
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
    $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
    $attendance_date = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : '';
    $period_number = isset($_POST['period_number']) ? (int)$_POST['period_number'] : 0;
    
    echo '<div class="card mb-3">';
    echo '<div class="card-header"><h5>Parsed Values:</h5></div>';
    echo '<div class="card-body">';
    echo '<ul>';
    echo '<li><strong>Subject ID:</strong> ' . $subject_id . '</li>';
    echo '<li><strong>Section ID:</strong> ' . $section_id . '</li>';
    echo '<li><strong>Date:</strong> ' . $attendance_date . '</li>';
    echo '<li><strong>Period:</strong> ' . $period_number . '</li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
    
    // Check attendance data
    $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : array();
    echo '<div class="card mb-3">';
    echo '<div class="card-header"><h5>Attendance Data (' . count($attendance_data) . ' students):</h5></div>';
    echo '<div class="card-body">';
    if (empty($attendance_data)) {
        echo '<div class="alert alert-danger">NO ATTENDANCE DATA FOUND!</div>';
    } else {
        echo '<table class="table table-sm">';
        echo '<tr><th>Student ID</th><th>Status</th><th>Remarks</th></tr>';
        foreach ($attendance_data as $student_id => $status) {
            $remarks = isset($_POST['remarks'][$student_id]) ? $_POST['remarks'][$student_id] : '';
            echo '<tr>';
            echo '<td>' . $student_id . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $remarks . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    echo '</div>';
    
    // Check if students exist in section
    if ($section_id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT student_id, first_name, last_name, admission_number FROM students WHERE section_id = ? AND student_status = 'active'");
        mysqli_stmt_bind_param($stmt, "i", $section_id);
        mysqli_stmt_execute($stmt);
        $students = mysqli_stmt_get_result($stmt);
        
        echo '<div class="card mb-3">';
        echo '<div class="card-header"><h5>Students in Section ' . $section_id . ' (' . mysqli_num_rows($students) . ' students):</h5></div>';
        echo '<div class="card-body">';
        
        if (mysqli_num_rows($students) == 0) {
            echo '<div class="alert alert-danger">NO STUDENTS FOUND IN SECTION!</div>';
        } else {
            echo '<table class="table table-sm">';
            echo '<tr><th>Student ID</th><th>Name</th><th>Admission No</th><th>In Form?</th></tr>';
            while ($student = mysqli_fetch_assoc($students)) {
                $in_form = isset($attendance_data[$student['student_id']]) ? 'YES' : 'NO';
                $row_class = $in_form == 'YES' ? 'table-success' : 'table-danger';
                echo '<tr class="' . $row_class . '">';
                echo '<td>' . $student['student_id'] . '</td>';
                echo '<td>' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</td>';
                echo '<td>' . htmlspecialchars($student['admission_number']) . '</td>';
                echo '<td><strong>' . $in_form . '</strong></td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</div>';
        echo '</div>';
        
        mysqli_stmt_close($stmt);
    }
    
} else {
    echo '<div class="alert alert-info">Submit attendance form to see debug information.</div>';
}

echo '<div class="mt-3">';
echo '<a href="dashboard.php?page=attendance" class="btn btn-secondary">Back to Attendance</a>';
echo '</div>';

mysqli_close($conn);
?>