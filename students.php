<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Student basic information
                $admission_number = validateInput($_POST['admission_number']);
                $roll_number = validateInput($_POST['roll_number']);
                $first_name = validateInput($_POST['first_name']);
                $middle_name = validateInput($_POST['middle_name']);
                $last_name = validateInput($_POST['last_name']);
                $father_name = validateInput($_POST['father_name']);
                $mother_name = validateInput($_POST['mother_name']);
                $date_of_birth = validateInput($_POST['date_of_birth']);
                $gender = validateInput($_POST['gender']);
                $category = validateInput($_POST['category']);
                $religion = validateInput($_POST['religion']);
                $caste = validateInput($_POST['caste']);
                $mobile_number = validateInput($_POST['mobile_number']);
                $email = validateInput($_POST['email']);
                $aadhar_number = validateInput($_POST['aadhar_number']);
                $address = validateInput($_POST['address']);
                $district = validateInput($_POST['district']);
                $state = validateInput($_POST['state']);
                $pincode = validateInput($_POST['pincode']);
                $programme_id = validateInput($_POST['programme_id']);
                $batch_id = validateInput($_POST['batch_id']);
                $semester_id = validateInput($_POST['semester_id']);
                $section_id = validateInput($_POST['section_id']);
                $student_status = validateInput($_POST['student_status']);
                $admission_date = validateInput($_POST['admission_date']);
                
                // Validate required fields
                if (empty($admission_number) || empty($first_name) || empty($last_name) || 
                    empty($father_name) || empty($mother_name) || empty($date_of_birth) || 
                    empty($gender) || empty($category)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if admission number already exists
                    $stmt = mysqli_prepare($conn, "SELECT student_id FROM students WHERE admission_number = ?");
                    mysqli_stmt_bind_param($stmt, "s", $admission_number);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Admission number already exists!";
                    } else {
                        // Create user account first
                        $username = strtolower($admission_number);
                        $default_password = password_hash('password123', PASSWORD_DEFAULT);
                        
                        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')");
                        mysqli_stmt_bind_param($stmt, "ss", $username, $default_password);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $user_id = mysqli_insert_id($conn);
                            
                            // Insert student record
                            $stmt = mysqli_prepare($conn, "INSERT INTO students (user_id, admission_number, roll_number, first_name, middle_name, last_name, father_name, mother_name, date_of_birth, gender, category, religion, caste, mobile_number, email, aadhar_number, address, district, state, pincode, programme_id, batch_id, semester_id, section_id, student_status, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            mysqli_stmt_bind_param($stmt, "issssssssssssssssssiiiiss", 
                                $user_id, $admission_number, $roll_number, $first_name, $middle_name, $last_name, 
                                $father_name, $mother_name, $date_of_birth, $gender, $category, $religion, $caste, 
                                $mobile_number, $email, $aadhar_number, $address, $district, $state, $pincode, 
                                $programme_id, $batch_id, $semester_id, $section_id, $student_status, $admission_date);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                $message = "Student added successfully! Default login: $username / password123";
                            } else {
                                $error = "Error adding student: " . mysqli_error($conn);
                            }
                        } else {
                            $error = "Error creating user account: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit_status':
                $student_id = validateInput($_POST['student_id']);
                $student_status = validateInput($_POST['student_status']);
                
                $stmt = mysqli_prepare($conn, "UPDATE students SET student_status = ? WHERE student_id = ?");
                mysqli_stmt_bind_param($stmt, "si", $student_status, $student_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Student status updated successfully!";
                } else {
                    $error = "Error updating student status: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Get filter parameters
$programme_filter = isset($_GET['programme']) ? $_GET['programme'] : '';
$batch_filter = isset($_GET['batch']) ? $_GET['batch'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause for filters
$where_conditions = array();
$params = array();
$param_types = '';

if (!empty($programme_filter)) {
    $where_conditions[] = "s.programme_id = ?";
    $params[] = $programme_filter;
    $param_types .= 'i';
}

if (!empty($batch_filter)) {
    $where_conditions[] = "s.batch_id = ?";
    $params[] = $batch_filter;
    $param_types .= 'i';
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.student_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all students with filters
$query = "SELECT s.*, p.programme_name, b.batch_name, sem.semester_name, cs.section_name
          FROM students s
          LEFT JOIN programme p ON s.programme_id = p.programme_id
          LEFT JOIN batch b ON s.batch_id = b.batch_id
          LEFT JOIN semester sem ON s.semester_id = sem.semester_id
          LEFT JOIN class_section cs ON s.section_id = cs.section_id
          $where_clause
          ORDER BY s.admission_date DESC, s.first_name";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$students = mysqli_stmt_get_result($stmt);

// Get data for dropdowns
$programmes = mysqli_query($conn, "SELECT * FROM programme WHERE status = 'active' ORDER BY programme_name");
$batches = mysqli_query($conn, "SELECT * FROM batch WHERE status = 'active' ORDER BY batch_year DESC");
$semesters = mysqli_query($conn, "SELECT * FROM semester WHERE status = 'active' ORDER BY semester_number");
$sections = mysqli_query($conn, "SELECT cs.*, p.programme_name FROM class_section cs LEFT JOIN programme p ON cs.programme_id = p.programme_id WHERE cs.status = 'active' ORDER BY p.programme_name, cs.section_name");
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-filter"></i> Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="page" value="students">
            <div class="row">
                <div class="col-md-3">
                    <select class="form-select" name="programme">
                        <option value="">All Programmes</option>
                        <?php 
                        mysqli_data_seek($programmes, 0);
                        while ($prog = mysqli_fetch_assoc($programmes)): 
                        ?>
                        <option value="<?php echo $prog['programme_id']; ?>" <?php echo $programme_filter == $prog['programme_id'] ? 'selected' : ''; ?>>
                            <?php echo $prog['programme_name']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="batch">
                        <option value="">All Batches</option>
                        <?php 
                        mysqli_data_seek($batches, 0);
                        while ($batch = mysqli_fetch_assoc($batches)): 
                        ?>
                        <option value="<?php echo $batch['batch_id']; ?>" <?php echo $batch_filter == $batch['batch_id'] ? 'selected' : ''; ?>>
                            <?php echo $batch['batch_name']; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="detained" <?php echo $status_filter == 'detained' ? 'selected' : ''; ?>>Detained</option>
                        <option value="dropout" <?php echo $status_filter == 'dropout' ? 'selected' : ''; ?>>Dropout</option>
                        <option value="passout" <?php echo $status_filter == 'passout' ? 'selected' : ''; ?>>Passout</option>
                        <option value="transferred" <?php echo $status_filter == 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="dashboard.php?page=students" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Student Management</h5>
                <?php if (in_array($user_type, ['admin', 'hod'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="fas fa-plus"></i> Add New Student
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Student Name</th>
                                <th>Father Name</th>
                                <th>Programme</th>
                                <th>Batch</th>
                                <th>Semester</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th>Mobile</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = mysqli_fetch_assoc($students)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['roll_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['programme_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['semester_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['section_name']); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            switch($student['student_status']) {
                                                case 'active': echo 'bg-success'; break;
                                                case 'detained': echo 'bg-warning'; break;
                                                case 'dropout': echo 'bg-danger'; break;
                                                case 'passout': echo 'bg-info'; break;
                                                case 'transferred': echo 'bg-secondary'; break;
                                                default: echo 'bg-primary'; break;
                                            }
                                        ?>">
                                        <?php echo ucfirst($student['student_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($student['mobile_number']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="viewStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (in_array($user_type, ['admin', 'hod'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="editStudentStatus(<?php echo $student['student_id']; ?>, '<?php echo $student['student_status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($user_type, ['admin', 'hod'])): ?>
<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <!-- Personal Information -->
                    <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admission_number" class="form-label">Admission Number *</label>
                                <input type="text" class="form-control" id="admission_number" name="admission_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="roll_number" class="form-label">Roll Number</label>
                                <input type="text" class="form-control" id="roll_number" name="roll_number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="father_name" class="form-label">Father Name *</label>
                                <input type="text" class="form-control" id="father_name" name="father_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="mother_name" class="form-label">Mother Name *</label>
                                <input type="text" class="form-control" id="mother_name" name="mother_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender *</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="general">General</option>
                                    <option value="obc">OBC</option>
                                    <option value="sc">SC</option>
                                    <option value="st">ST</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="religion" class="form-label">Religion</label>
                                <input type="text" class="form-control" id="religion" name="religion">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="caste" class="form-label">Caste</label>
                                <input type="text" class="form-control" id="caste" name="caste">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="mobile_number" class="form-label">Mobile Number</label>
                                <input type="tel" class="form-control" id="mobile_number" name="mobile_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="aadhar_number" class="form-label">Aadhar Number</label>
                                <input type="text" class="form-control" id="aadhar_number" name="aadhar_number" maxlength="12">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="district" class="form-label">District</label>
                                <input type="text" class="form-control" id="district" name="district">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" value="Andhra Pradesh">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pincode" class="form-label">Pincode</label>
                                <input type="text" class="form-control" id="pincode" name="pincode" maxlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Academic Information -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Academic Information</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="programme_id" class="form-label">Programme</label>
                                <select class="form-select" id="programme_id" name="programme_id">
                                    <option value="">Select Programme</option>
                                    <?php 
                                    mysqli_data_seek($programmes, 0);
                                    while ($prog = mysqli_fetch_assoc($programmes)): 
                                    ?>
                                    <option value="<?php echo $prog['programme_id']; ?>">
                                        <?php echo $prog['programme_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="batch_id" class="form-label">Batch</label>
                                <select class="form-select" id="batch_id" name="batch_id">
                                    <option value="">Select Batch</option>
                                    <?php 
                                    mysqli_data_seek($batches, 0);
                                    while ($batch = mysqli_fetch_assoc($batches)): 
                                    ?>
                                    <option value="<?php echo $batch['batch_id']; ?>">
                                        <?php echo $batch['batch_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="semester_id" class="form-label">Semester</label>
                                <select class="form-select" id="semester_id" name="semester_id">
                                    <option value="">Select Semester</option>
                                    <?php 
                                    mysqli_data_seek($semesters, 0);
                                    while ($sem = mysqli_fetch_assoc($semesters)): 
                                    ?>
                                    <option value="<?php echo $sem['semester_id']; ?>">
                                        <?php echo $sem['semester_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="section_id" class="form-label">Section</label>
                                <select class="form-select" id="section_id" name="section_id">
                                    <option value="">Select Section</option>
                                    <?php 
                                    mysqli_data_seek($sections, 0);
                                    while ($section = mysqli_fetch_assoc($sections)): 
                                    ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo $section['programme_name'] . ' - ' . $section['section_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="student_status" class="form-label">Student Status</label>
                                <select class="form-select" id="student_status" name="student_status">
                                    <option value="active">Active</option>
                                    <option value="detained">Detained</option>
                                    <option value="dropout">Dropout</option>
                                    <option value="passout">Passout</option>
                                    <option value="transferred">Transferred</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="admission_date" class="form-label">Admission Date</label>
                                <input type="date" class="form-control" id="admission_date" name="admission_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Student Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Admission Number:</strong></td>
                                <td id="view_admission_number"></td>
                            </tr>
                            <tr>
                                <td><strong>Roll Number:</strong></td>
                                <td id="view_roll_number"></td>
                            </tr>
                            <tr>
                                <td><strong>Full Name:</strong></td>
                                <td id="view_full_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Father Name:</strong></td>
                                <td id="view_father_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Mother Name:</strong></td>
                                <td id="view_mother_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Date of Birth:</strong></td>
                                <td id="view_date_of_birth"></td>
                            </tr>
                            <tr>
                                <td><strong>Gender:</strong></td>
                                <td id="view_gender"></td>
                            </tr>
                            <tr>
                                <td><strong>Category:</strong></td>
                                <td id="view_category"></td>
                            </tr>
                            <tr>
                                <td><strong>Religion:</strong></td>
                                <td id="view_religion"></td>
                            </tr>
                            <tr>
                                <td><strong>Caste:</strong></td>
                                <td id="view_caste"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Contact & Academic Info</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Mobile:</strong></td>
                                <td id="view_mobile_number"></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td id="view_email"></td>
                            </tr>
                            <tr>
                                <td><strong>Aadhar Number:</strong></td>
                                <td id="view_aadhar_number"></td>
                            </tr>
                            <tr>
                                <td><strong>Address:</strong></td>
                                <td id="view_address"></td>
                            </tr>
                            <tr>
                                <td><strong>District:</strong></td>
                                <td id="view_district"></td>
                            </tr>
                            <tr>
                                <td><strong>State:</strong></td>
                                <td id="view_state"></td>
                            </tr>
                            <tr>
                                <td><strong>Pincode:</strong></td>
                                <td id="view_pincode"></td>
                            </tr>
                            <tr>
                                <td><strong>Programme:</strong></td>
                                <td id="view_programme_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Batch:</strong></td>
                                <td id="view_batch_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Semester:</strong></td>
                                <td id="view_semester_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Section:</strong></td>
                                <td id="view_section_name"></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span id="view_student_status" class="badge"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Admission Date:</strong></td>
                                <td id="view_admission_date"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($user_type, ['admin', 'hod'])): ?>
<!-- Edit Student Status Modal -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_status">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    
                    <div class="mb-3">
                        <label for="edit_student_status" class="form-label">Student Status</label>
                        <select class="form-select" id="edit_student_status" name="student_status" required>
                            <option value="active">Active</option>
                            <option value="detained">Detained</option>
                            <option value="dropout">Dropout</option>
                            <option value="passout">Passout</option>
                            <option value="transferred">Transferred</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Status Definitions:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Active:</strong> Currently studying</li>
                            <li><strong>Detained:</strong> Not promoted to next semester</li>
                            <li><strong>Dropout:</strong> Left studies incomplete</li>
                            <li><strong>Passout:</strong> Successfully completed the course</li>
                            <li><strong>Transferred:</strong> Moved to another institution</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function viewStudent(student) {
    // Personal Information
    document.getElementById('view_admission_number').textContent = student.admission_number || '-';
    document.getElementById('view_roll_number').textContent = student.roll_number || '-';
    
    let fullName = student.first_name;
    if (student.middle_name) fullName += ' ' + student.middle_name;
    fullName += ' ' + student.last_name;
    document.getElementById('view_full_name').textContent = fullName;
    
    document.getElementById('view_father_name').textContent = student.father_name || '-';
    document.getElementById('view_mother_name').textContent = student.mother_name || '-';
    
    // Format date of birth
    if (student.date_of_birth) {
        const dob = new Date(student.date_of_birth);
        document.getElementById('view_date_of_birth').textContent = dob.toLocaleDateString('en-IN');
    } else {
        document.getElementById('view_date_of_birth').textContent = '-';
    }
    
    document.getElementById('view_gender').textContent = student.gender ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1) : '-';
    document.getElementById('view_category').textContent = student.category ? student.category.toUpperCase() : '-';
    document.getElementById('view_religion').textContent = student.religion || '-';
    document.getElementById('view_caste').textContent = student.caste || '-';
    
    // Contact Information
    document.getElementById('view_mobile_number').textContent = student.mobile_number || '-';
    document.getElementById('view_email').textContent = student.email || '-';
    document.getElementById('view_aadhar_number').textContent = student.aadhar_number || '-';
    document.getElementById('view_address').textContent = student.address || '-';
    document.getElementById('view_district').textContent = student.district || '-';
    document.getElementById('view_state').textContent = student.state || '-';
    document.getElementById('view_pincode').textContent = student.pincode || '-';
    
    // Academic Information
    document.getElementById('view_programme_name').textContent = student.programme_name || '-';
    document.getElementById('view_batch_name').textContent = student.batch_name || '-';
    document.getElementById('view_semester_name').textContent = student.semester_name || '-';
    document.getElementById('view_section_name').textContent = student.section_name || '-';
    
    // Status with badge styling
    const statusBadge = document.getElementById('view_student_status');
    statusBadge.textContent = student.student_status ? student.student_status.charAt(0).toUpperCase() + student.student_status.slice(1) : '-';
    
    // Remove existing badge classes
    statusBadge.className = 'badge';
    
    // Add appropriate badge class based on status
    switch(student.student_status) {
        case 'active':
            statusBadge.classList.add('bg-success');
            break;
        case 'detained':
            statusBadge.classList.add('bg-warning');
            break;
        case 'dropout':
            statusBadge.classList.add('bg-danger');
            break;
        case 'passout':
            statusBadge.classList.add('bg-info');
            break;
        case 'transferred':
            statusBadge.classList.add('bg-secondary');
            break;
        default:
            statusBadge.classList.add('bg-primary');
            break;
    }
    
    // Format admission date
    if (student.admission_date) {
        const admissionDate = new Date(student.admission_date);
        document.getElementById('view_admission_date').textContent = admissionDate.toLocaleDateString('en-IN');
    } else {
        document.getElementById('view_admission_date').textContent = '-';
    }
    
    // Show the modal
    var viewModal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
    viewModal.show();
}

<?php if (in_array($user_type, ['admin', 'hod'])): ?>
function editStudentStatus(studentId, currentStatus) {
    document.getElementById('edit_student_id').value = studentId;
    document.getElementById('edit_student_status').value = currentStatus;
    
    var editModal = new bootstrap.Modal(document.getElementById('editStatusModal'));
    editModal.show();
}
<?php endif; ?>

// Form validation for add student modal
document.addEventListener('DOMContentLoaded', function() {
    // Mobile number validation
    const mobileInput = document.getElementById('mobile_number');
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
    
    // Aadhar number validation
    const aadharInput = document.getElementById('aadhar_number');
    if (aadharInput) {
        aadharInput.addEventListener('input', function() {
            const aadhar = this.value;
            if (aadhar && !/^\d{12}$/.test(aadhar)) {
                this.setCustomValidity('Please enter a valid 12-digit Aadhar number');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Pincode validation
    const pincodeInput = document.getElementById('pincode');
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
    
    // Email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const email = this.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && !emailPattern.test(email)) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Age validation based on date of birth
    const dobInput = document.getElementById('date_of_birth');
    if (dobInput) {
        dobInput.addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            const age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age < 15 || age > 35) {
                this.setCustomValidity('Student age should be between 15 and 35 years');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

// Auto-generate roll number based on admission number (optional)
function generateRollNumber() {
    const admissionNumber = document.getElementById('admission_number').value;
    const rollNumberField = document.getElementById('roll_number');
    
    if (admissionNumber && !rollNumberField.value) {
        // Simple roll number generation logic - you can customize this
        const programmeSelect = document.getElementById('programme_id');
        const batchSelect = document.getElementById('batch_id');
        
        if (programmeSelect.value && batchSelect.value) {
            const programmeOption = programmeSelect.options[programmeSelect.selectedIndex];
            const batchOption = batchSelect.options[batchSelect.selectedIndex];
            
            if (programmeOption.text && batchOption.text) {
                // Extract programme code (first 3 letters)
                const progCode = programmeOption.text.substring(0, 3).toUpperCase();
                // Extract batch year (last 2 digits)
                const batchYear = batchOption.text.slice(-2);
                // Use last 4 digits of admission number
                const admissionSuffix = admissionNumber.slice(-4);
                
                rollNumberField.value = `${progCode}${batchYear}${admissionSuffix}`;
            }
        }
    }
}

// Add event listeners for auto-generation
document.addEventListener('DOMContentLoaded', function() {
    const admissionInput = document.getElementById('admission_number');
    const programmeSelect = document.getElementById('programme_id');
    const batchSelect = document.getElementById('batch_id');
    
    if (admissionInput) {
        admissionInput.addEventListener('blur', generateRollNumber);
    }
    if (programmeSelect) {
        programmeSelect.addEventListener('change', generateRollNumber);
    }
    if (batchSelect) {
        batchSelect.addEventListener('change', generateRollNumber);
    }
});
</script>