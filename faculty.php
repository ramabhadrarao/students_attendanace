<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $employee_id = validateInput($_POST['employee_id']);
                $first_name = validateInput($_POST['first_name']);
                $middle_name = validateInput($_POST['middle_name']);
                $last_name = validateInput($_POST['last_name']);
                $designation = validateInput($_POST['designation']);
                $department = validateInput($_POST['department']);
                $qualification = validateInput($_POST['qualification']);
                $experience_years = validateInput($_POST['experience_years']);
                $mobile_number = validateInput($_POST['mobile_number']);
                $email = validateInput($_POST['email']);
                $address = validateInput($_POST['address']);
                $date_of_joining = validateInput($_POST['date_of_joining']);
                $faculty_type = validateInput($_POST['faculty_type']);
                $status = validateInput($_POST['status']);
                
                if (empty($employee_id) || empty($first_name) || empty($last_name) || empty($designation)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if employee ID already exists
                    $stmt = mysqli_prepare($conn, "SELECT faculty_id FROM faculty WHERE employee_id = ?");
                    mysqli_stmt_bind_param($stmt, "s", $employee_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Employee ID already exists!";
                    } else {
                        // Create user account first
                        $username = strtolower($employee_id);
                        $default_password = password_hash('faculty123', PASSWORD_DEFAULT);
                        
                        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, user_type) VALUES (?, ?, 'faculty')");
                        mysqli_stmt_bind_param($stmt, "ss", $username, $default_password);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $user_id = mysqli_insert_id($conn);
                            
                            // Insert faculty record
                            $stmt = mysqli_prepare($conn, "INSERT INTO faculty (user_id, employee_id, first_name, middle_name, last_name, designation, department, qualification, experience_years, mobile_number, email, address, date_of_joining, faculty_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            mysqli_stmt_bind_param($stmt, "isssssssissssss", 
                                $user_id, $employee_id, $first_name, $middle_name, $last_name, 
                                $designation, $department, $qualification, $experience_years, 
                                $mobile_number, $email, $address, $date_of_joining, $faculty_type, $status);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                $message = "Faculty added successfully! Default login: $username / faculty123";
                            } else {
                                $error = "Error adding faculty: " . mysqli_error($conn);
                            }
                        } else {
                            $error = "Error creating user account: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $faculty_id = validateInput($_POST['faculty_id']);
                $employee_id = validateInput($_POST['employee_id']);
                $first_name = validateInput($_POST['first_name']);
                $middle_name = validateInput($_POST['middle_name']);
                $last_name = validateInput($_POST['last_name']);
                $designation = validateInput($_POST['designation']);
                $department = validateInput($_POST['department']);
                $qualification = validateInput($_POST['qualification']);
                $experience_years = validateInput($_POST['experience_years']);
                $mobile_number = validateInput($_POST['mobile_number']);
                $email = validateInput($_POST['email']);
                $address = validateInput($_POST['address']);
                $date_of_joining = validateInput($_POST['date_of_joining']);
                $faculty_type = validateInput($_POST['faculty_type']);
                $status = validateInput($_POST['status']);
                
                if (empty($employee_id) || empty($first_name) || empty($last_name) || empty($designation)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if employee ID already exists (excluding current record)
                    $stmt = mysqli_prepare($conn, "SELECT faculty_id FROM faculty WHERE employee_id = ? AND faculty_id != ?");
                    mysqli_stmt_bind_param($stmt, "si", $employee_id, $faculty_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Employee ID already exists!";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE faculty SET employee_id = ?, first_name = ?, middle_name = ?, last_name = ?, designation = ?, department = ?, qualification = ?, experience_years = ?, mobile_number = ?, email = ?, address = ?, date_of_joining = ?, faculty_type = ?, status = ? WHERE faculty_id = ?");
                        
                        mysqli_stmt_bind_param($stmt, "sssssssississsi", 
                            $employee_id, $first_name, $middle_name, $last_name, 
                            $designation, $department, $qualification, $experience_years, 
                            $mobile_number, $email, $address, $date_of_joining, $faculty_type, $status, $faculty_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Faculty updated successfully!";
                        } else {
                            $error = "Error updating faculty: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
                case 'delete':
                    $faculty_id = validateInput($_POST['faculty_id']);
                    
                    // Check if faculty is assigned to any subjects
                    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM subject_faculty WHERE faculty_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $faculty_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $usage_count = mysqli_fetch_assoc($result)['count'];
                    
                    if ($usage_count > 0) {
                        $error = "Cannot delete faculty. They are assigned to " . $usage_count . " subjects.";
                    } else {
                        // Get user_id before deleting faculty
                        $stmt = mysqli_prepare($conn, "SELECT user_id FROM faculty WHERE faculty_id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $faculty_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $faculty_data = mysqli_fetch_assoc($result);
                        
                        if ($faculty_data) {
                            // Delete faculty record
                            $stmt = mysqli_prepare($conn, "DELETE FROM faculty WHERE faculty_id = ?");
                            mysqli_stmt_bind_param($stmt, "i", $faculty_id);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                // Delete user account
                                $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
                                mysqli_stmt_bind_param($stmt, "i", $faculty_data['user_id']);
                                mysqli_stmt_execute($stmt);
                                
                                $message = "Faculty deleted successfully!";
                            } else {
                                $error = "Error deleting faculty: " . mysqli_error($conn);
                            }
                        }
                    }
                    mysqli_stmt_close($stmt);
                    break;
            }
        }
    }
    
    // Get all faculty members
    $stmt = mysqli_prepare($conn, "SELECT f.*, 
                                  (SELECT COUNT(*) FROM subject_faculty sf WHERE sf.faculty_id = f.faculty_id AND sf.status = 'active') as subject_count
                                  FROM faculty f ORDER BY f.first_name, f.last_name");
    mysqli_stmt_execute($stmt);
    $faculty_members = mysqli_stmt_get_result($stmt);
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
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Faculty Management</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
                        <i class="fas fa-plus"></i> Add New Faculty
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped data-table">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Faculty Name</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Experience</th>
                                    <th>Contact</th>
                                    <th>Subjects</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($faculty = mysqli_fetch_assoc($faculty_members)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($faculty['employee_id']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($faculty['qualification']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($faculty['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($faculty['department']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $faculty['experience_years']; ?> Years</span>
                                    </td>
                                    <td>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($faculty['mobile_number']); ?>
                                        <br>
                                        <i class="fas fa-envelope"></i> <small><?php echo htmlspecialchars($faculty['email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $faculty['subject_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($faculty['faculty_type']) {
                                                    case 'regular': echo 'bg-success'; break;
                                                    case 'guest': echo 'bg-warning'; break;
                                                    case 'contract': echo 'bg-info'; break;
                                                    default: echo 'bg-primary'; break;
                                                }
                                            ?>">
                                            <?php echo ucfirst($faculty['faculty_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $faculty['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($faculty['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="viewFaculty(<?php echo htmlspecialchars(json_encode($faculty)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editFaculty(<?php echo htmlspecialchars(json_encode($faculty)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteFaculty(<?php echo $faculty['faculty_id']; ?>, '<?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    
    <!-- Add Faculty Modal -->
    <div class="modal fade" id="addFacultyModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Faculty</h5>
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
                                    <label for="employee_id" class="form-label">Employee ID *</label>
                                    <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="faculty_type" class="form-label">Faculty Type</label>
                                    <select class="form-select" id="faculty_type" name="faculty_type">
                                        <option value="regular">Regular</option>
                                        <option value="guest">Guest</option>
                                        <option value="contract">Contract</option>
                                    </select>
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
                        
                        <!-- Professional Information -->
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Professional Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="designation" class="form-label">Designation *</label>
                                    <input type="text" class="form-control" id="designation" name="designation" 
                                           placeholder="e.g., Assistant Professor, Associate Professor" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department" 
                                           placeholder="e.g., Computer Science, Commerce">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="qualification" class="form-label">Qualification</label>
                                    <input type="text" class="form-control" id="qualification" name="qualification" 
                                           placeholder="e.g., M.Tech, M.Com, Ph.D">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="experience_years" class="form-label">Experience (Years)</label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                           min="0" max="50" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_joining" class="form-label">Date of Joining</label>
                                    <input type="date" class="form-control" id="date_of_joining" name="date_of_joining" 
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <h6 class="border-bottom pb-2 mb-3 mt-4">Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mobile_number" class="form-label">Mobile Number</label>
                                    <input type="tel" class="form-control" id="mobile_number" name="mobile_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Faculty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Faculty Modal -->
    <div class="modal fade" id="viewFacultyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Faculty Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>Employee ID:</strong></td>
                                    <td id="view_employee_id"></td>
                                </tr>
                                <tr>
                                    <td><strong>Full Name:</strong></td>
                                    <td id="view_full_name"></td>
                                </tr>
                                <tr>
                                    <td><strong>Designation:</strong></td>
                                    <td id="view_designation"></td>
                                </tr>
                                <tr>
                                    <td><strong>Department:</strong></td>
                                    <td id="view_department"></td>
                                </tr>
                                <tr>
                                    <td><strong>Qualification:</strong></td>
                                    <td id="view_qualification"></td>
                                </tr>
                                <tr>
                                    <td><strong>Experience:</strong></td>
                                    <td id="view_experience_years"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">Contact & Other Info</h6>
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
                                    <td><strong>Address:</strong></td>
                                    <td id="view_address"></td>
                                </tr>
                                <tr>
                                    <td><strong>Date of Joining:</strong></td>
                                    <td id="view_date_of_joining"></td>
                                </tr>
                                <tr>
                                    <td><strong>Faculty Type:</strong></td>
                                    <td><span id="view_faculty_type" class="badge"></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td><span id="view_status" class="badge"></span></td>
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
    <!-- Edit Faculty Modal -->
<div class="modal fade" id="editFacultyModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="faculty_id" id="edit_faculty_id">
                    
                    <!-- Personal Information -->
                    <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_employee_id" class="form-label">Employee ID *</label>
                                <input type="text" class="form-control" id="edit_employee_id" name="employee_id" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_faculty_type" class="form-label">Faculty Type</label>
                                <select class="form-select" id="edit_faculty_type" name="faculty_type">
                                    <option value="regular">Regular</option>
                                    <option value="guest">Guest</option>
                                    <option value="contract">Contract</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Professional Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_designation" class="form-label">Designation *</label>
                                <input type="text" class="form-control" id="edit_designation" name="designation" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="edit_department" name="department">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="edit_qualification" name="qualification">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_experience_years" class="form-label">Experience (Years)</label>
                                <input type="number" class="form-control" id="edit_experience_years" name="experience_years" 
                                       min="0" max="50">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_of_joining" class="form-label">Date of Joining</label>
                                <input type="date" class="form-control" id="edit_date_of_joining" name="date_of_joining">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_mobile_number" class="form-label">Mobile Number</label>
                                <input type="tel" class="form-control" id="edit_mobile_number" name="mobile_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="edit_address" class="form-label">Address</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteFacultyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Faculty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="faculty_id" id="delete_faculty_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the faculty "<span id="delete_faculty_name"></span>"?
                        <br><br>
                        <strong>This action will also delete their user account and cannot be undone!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewFaculty(faculty) {
    document.getElementById('view_employee_id').textContent = faculty.employee_id || '-';
    
    let fullName = faculty.first_name;
    if (faculty.middle_name) fullName += ' ' + faculty.middle_name;
    fullName += ' ' + faculty.last_name;
    document.getElementById('view_full_name').textContent = fullName;
    
    document.getElementById('view_designation').textContent = faculty.designation || '-';
    document.getElementById('view_department').textContent = faculty.department || '-';
    document.getElementById('view_qualification').textContent = faculty.qualification || '-';
    document.getElementById('view_experience_years').textContent = faculty.experience_years ? faculty.experience_years + ' Years' : '-';
    document.getElementById('view_mobile_number').textContent = faculty.mobile_number || '-';
    document.getElementById('view_email').textContent = faculty.email || '-';
    document.getElementById('view_address').textContent = faculty.address || '-';
    
    // Format date of joining
    if (faculty.date_of_joining) {
        const doj = new Date(faculty.date_of_joining);
        document.getElementById('view_date_of_joining').textContent = doj.toLocaleDateString('en-IN');
    } else {
        document.getElementById('view_date_of_joining').textContent = '-';
    }
    
    // Faculty type with badge styling
    const facultyTypeBadge = document.getElementById('view_faculty_type');
    facultyTypeBadge.textContent = faculty.faculty_type ? faculty.faculty_type.charAt(0).toUpperCase() + faculty.faculty_type.slice(1) : '-';
    facultyTypeBadge.className = 'badge';
    
    switch(faculty.faculty_type) {
        case 'regular':
            facultyTypeBadge.classList.add('bg-success');
            break;
        case 'guest':
            facultyTypeBadge.classList.add('bg-warning');
            break;
        case 'contract':
            facultyTypeBadge.classList.add('bg-info');
            break;
        default:
            facultyTypeBadge.classList.add('bg-primary');
            break;
    }
    
    // Status with badge styling
    const statusBadge = document.getElementById('view_status');
    statusBadge.textContent = faculty.status ? faculty.status.charAt(0).toUpperCase() + faculty.status.slice(1) : '-';
    statusBadge.className = 'badge';
    statusBadge.classList.add(faculty.status === 'active' ? 'bg-success' : 'bg-secondary');
    
    var viewModal = new bootstrap.Modal(document.getElementById('viewFacultyModal'));
    viewModal.show();
}

function editFaculty(faculty) {
    document.getElementById('edit_faculty_id').value = faculty.faculty_id;
    document.getElementById('edit_employee_id').value = faculty.employee_id;
    document.getElementById('edit_first_name').value = faculty.first_name;
    document.getElementById('edit_middle_name').value = faculty.middle_name || '';
    document.getElementById('edit_last_name').value = faculty.last_name;
    document.getElementById('edit_designation').value = faculty.designation;
    document.getElementById('edit_department').value = faculty.department || '';
    document.getElementById('edit_qualification').value = faculty.qualification || '';
    document.getElementById('edit_experience_years').value = faculty.experience_years || 0;
    document.getElementById('edit_mobile_number').value = faculty.mobile_number || '';
    document.getElementById('edit_email').value = faculty.email || '';
    document.getElementById('edit_address').value = faculty.address || '';
    document.getElementById('edit_date_of_joining').value = faculty.date_of_joining || '';
    document.getElementById('edit_faculty_type').value = faculty.faculty_type;
    document.getElementById('edit_status').value = faculty.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editFacultyModal'));
    editModal.show();
}

function deleteFaculty(facultyId, facultyName) {
    document.getElementById('delete_faculty_id').value = facultyId;
    document.getElementById('delete_faculty_name').textContent = facultyName;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteFacultyModal'));
    deleteModal.show();
}

// Form validation for faculty management
document.addEventListener('DOMContentLoaded', function() {
    // Mobile number validation
    const mobileInputs = ['mobile_number', 'edit_mobile_number'];
    mobileInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                const mobile = this.value;
                if (mobile && !/^[6-9]\d{9}$/.test(mobile)) {
                    this.setCustomValidity('Please enter a valid 10-digit mobile number starting with 6-9');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Email validation
    const emailInputs = ['email', 'edit_email'];
    emailInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                const email = this.value;
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email && !emailPattern.test(email)) {
                    this.setCustomValidity('Please enter a valid email address');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Employee ID validation
    const employeeIdInputs = ['employee_id', 'edit_employee_id'];
    employeeIdInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                const empId = this.value;
                if (empId && !/^[A-Z0-9]{3,10}$/.test(empId)) {
                    this.setCustomValidity('Employee ID should be 3-10 uppercase letters/numbers');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Experience years validation
    const experienceInputs = ['experience_years', 'edit_experience_years'];
    experienceInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                const years = parseInt(this.value);
                if (years < 0 || years > 50) {
                    this.setCustomValidity('Experience should be between 0 and 50 years');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Date of joining validation
    const dateInputs = ['date_of_joining', 'edit_date_of_joining'];
    dateInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                const minDate = new Date('1970-01-01');
                
                if (selectedDate > today) {
                    this.setCustomValidity('Date of joining cannot be in the future');
                } else if (selectedDate < minDate) {
                    this.setCustomValidity('Please enter a valid date');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
});

// Auto-generate employee ID based on name and designation
function generateEmployeeId() {
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    const designation = document.getElementById('designation').value;
    const employeeIdField = document.getElementById('employee_id');
    
    if (firstName && lastName && designation && !employeeIdField.value) {
        let prefix = 'FAC'; // Default prefix for faculty
        
        // Generate prefix based on designation
        if (designation.toLowerCase().includes('professor')) {
            prefix = 'PROF';
        } else if (designation.toLowerCase().includes('assistant')) {
            prefix = 'ASST';
        } else if (designation.toLowerCase().includes('associate')) {
            prefix = 'ASSOC';
        } else if (designation.toLowerCase().includes('lecturer')) {
            prefix = 'LECT';
        } else if (designation.toLowerCase().includes('head')) {
            prefix = 'HOD';
        }
        
        // Generate random number
        const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        
        employeeIdField.value = prefix + randomNum;
    }
}

// Add event listeners for auto-generation
document.addEventListener('DOMContentLoaded', function() {
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    const designationInput = document.getElementById('designation');
    
    if (firstNameInput && lastNameInput && designationInput) {
        [firstNameInput, lastNameInput, designationInput].forEach(input => {
            input.addEventListener('blur', generateEmployeeId);
        });
    }
});

// Faculty type change handler
document.addEventListener('DOMContentLoaded', function() {
    const facultyTypeSelects = ['faculty_type', 'edit_faculty_type'];
    
    facultyTypeSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            select.addEventListener('change', function() {
                const facultyType = this.value;
                let infoText = '';
                
                switch(facultyType) {
                    case 'regular':
                        infoText = 'Permanent faculty member with full benefits';
                        break;
                    case 'guest':
                        infoText = 'Temporary faculty for specific courses or periods';
                        break;
                    case 'contract':
                        infoText = 'Faculty on contractual basis for fixed duration';
                        break;
                }
                
                // Find or create info element
                let infoElement = this.parentNode.querySelector('.faculty-type-info');
                if (!infoElement) {
                    infoElement = document.createElement('div');
                    infoElement.className = 'faculty-type-info form-text text-muted mt-1';
                    this.parentNode.appendChild(infoElement);
                }
                
                infoElement.innerHTML = '<i class="fas fa-info-circle"></i> ' + infoText;
            });
        }
    });
});
</script>