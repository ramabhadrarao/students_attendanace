<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $subject_name = validateInput($_POST['subject_name']);
                $subject_code = validateInput($_POST['subject_code']);
                $credits = validateInput($_POST['credits']);
                $programme_id = validateInput($_POST['programme_id']);
                $semester_id = validateInput($_POST['semester_id']);
                $subject_type = validateInput($_POST['subject_type']);
                $status = validateInput($_POST['status']);
                
                if (empty($subject_name) || empty($subject_code) || empty($programme_id) || empty($semester_id)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if subject code already exists
                    $stmt = mysqli_prepare($conn, "SELECT subject_id FROM subjects WHERE subject_code = ?");
                    mysqli_stmt_bind_param($stmt, "s", $subject_code);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Subject code already exists!";
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO subjects (subject_name, subject_code, credits, programme_id, semester_id, subject_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssiisss", $subject_name, $subject_code, $credits, $programme_id, $semester_id, $subject_type, $status);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Subject added successfully!";
                        } else {
                            $error = "Error adding subject: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $subject_id = validateInput($_POST['subject_id']);
                $subject_name = validateInput($_POST['subject_name']);
                $subject_code = validateInput($_POST['subject_code']);
                $credits = validateInput($_POST['credits']);
                $programme_id = validateInput($_POST['programme_id']);
                $semester_id = validateInput($_POST['semester_id']);
                $subject_type = validateInput($_POST['subject_type']);
                $status = validateInput($_POST['status']);
                
                if (empty($subject_name) || empty($subject_code) || empty($programme_id) || empty($semester_id)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if subject code already exists (excluding current record)
                    $stmt = mysqli_prepare($conn, "SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ?");
                    mysqli_stmt_bind_param($stmt, "si", $subject_code, $subject_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Subject code already exists!";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE subjects SET subject_name = ?, subject_code = ?, credits = ?, programme_id = ?, semester_id = ?, subject_type = ?, status = ? WHERE subject_id = ?");
                        mysqli_stmt_bind_param($stmt, "ssiisssi", $subject_name, $subject_code, $credits, $programme_id, $semester_id, $subject_type, $status, $subject_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Subject updated successfully!";
                        } else {
                            $error = "Error updating subject: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'delete':
                $subject_id = validateInput($_POST['subject_id']);
                
                // Check if subject is being used
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM subject_faculty WHERE subject_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $subject_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $usage_count = mysqli_fetch_assoc($result)['count'];
                
                if ($usage_count > 0) {
                    $error = "Cannot delete subject. It is assigned to " . $usage_count . " faculty members.";
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM subjects WHERE subject_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $subject_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Subject deleted successfully!";
                    } else {
                        $error = "Error deleting subject: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
                
            case 'assign_faculty':
                $subject_id = validateInput($_POST['subject_id']);
                $faculty_id = validateInput($_POST['faculty_id']);
                $section_id = validateInput($_POST['section_id']);
                $academic_year = validateInput($_POST['academic_year']);
                
                if (empty($subject_id) || empty($faculty_id) || empty($section_id) || empty($academic_year)) {
                    $error = "Please fill in all required fields for faculty assignment.";
                } else {
                    // Check if assignment already exists
                    $stmt = mysqli_prepare($conn, "SELECT allocation_id FROM subject_faculty WHERE subject_id = ? AND faculty_id = ? AND section_id = ? AND academic_year = ?");
                    mysqli_stmt_bind_param($stmt, "iiis", $subject_id, $faculty_id, $section_id, $academic_year);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "This faculty is already assigned to this subject for the selected section and academic year!";
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO subject_faculty (subject_id, faculty_id, section_id, academic_year, status) VALUES (?, ?, ?, ?, 'active')");
                        mysqli_stmt_bind_param($stmt, "iiis", $subject_id, $faculty_id, $section_id, $academic_year);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Faculty assigned to subject successfully!";
                        } else {
                            $error = "Error assigning faculty: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
        }
    }
}

// Get all subjects with related information
$stmt = mysqli_prepare($conn, "SELECT s.*, p.programme_name, p.programme_code, sem.semester_name,
                              (SELECT COUNT(*) FROM subject_faculty sf WHERE sf.subject_id = s.subject_id AND sf.status = 'active') as faculty_count
                              FROM subjects s
                              LEFT JOIN programme p ON s.programme_id = p.programme_id
                              LEFT JOIN semester sem ON s.semester_id = sem.semester_id
                              ORDER BY p.programme_name, sem.semester_name, s.subject_name");
mysqli_stmt_execute($stmt);
$subjects = mysqli_stmt_get_result($stmt);

// Get data for dropdowns
$programmes = mysqli_query($conn, "SELECT * FROM programme WHERE status = 'active' ORDER BY programme_name");
$semesters = mysqli_query($conn, "SELECT * FROM semester WHERE status = 'active' ORDER BY semester_number");
$faculty_members = mysqli_query($conn, "SELECT * FROM faculty WHERE status = 'active' ORDER BY first_name, last_name");
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

<!-- Subject Management -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-book-open"></i> Subject Management</h5>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#assignFacultyModal">
                        <i class="fas fa-user-plus"></i> Assign Faculty
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="fas fa-plus"></i> Add New Subject
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Programme</th>
                                <th>Semester</th>
                                <th>Credits</th>
                                <th>Type</th>
                                <th>Faculty Assigned</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subject = mysqli_fetch_assoc($subjects)): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($subject['programme_code']); ?></span>
                                    <br>
                                    <small><?php echo htmlspecialchars($subject['programme_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($subject['semester_name']); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $subject['credits']; ?></span>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            switch($subject['subject_type']) {
                                                case 'theory': echo 'bg-success'; break;
                                                case 'practical': echo 'bg-warning'; break;
                                                case 'project': echo 'bg-info'; break;
                                                default: echo 'bg-primary'; break;
                                            }
                                        ?>">
                                        <?php echo ucfirst($subject['subject_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-dark"><?php echo $subject['faculty_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $subject['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($subject['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                            onclick="viewAssignedFaculty(<?php echo $subject['subject_id']; ?>, '<?php echo htmlspecialchars($subject['subject_name']); ?>')">
                                        <i class="fas fa-users"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteSubject(<?php echo $subject['subject_id']; ?>, '<?php echo htmlspecialchars($subject['subject_name']); ?>')">
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="subject_name" class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" id="subject_name" name="subject_name" 
                                       placeholder="e.g., Data Structures, Financial Accounting" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" 
                                       placeholder="e.g., CS101, ACC201" style="text-transform: uppercase;" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="programme_id" class="form-label">Programme *</label>
                                <select class="form-select" id="programme_id" name="programme_id" required>
                                    <option value="">Select Programme</option>
                                    <?php 
                                    mysqli_data_seek($programmes, 0);
                                    while ($prog = mysqli_fetch_assoc($programmes)): 
                                    ?>
                                    <option value="<?php echo $prog['programme_id']; ?>">
                                        <?php echo $prog['programme_code'] . ' - ' . $prog['programme_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="semester_id" class="form-label">Semester *</label>
                                <select class="form-select" id="semester_id" name="semester_id" required>
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
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="credits" class="form-label">Credits</label>
                                <select class="form-select" id="credits" name="credits">
                                    <option value="1">1 Credit</option>
                                    <option value="2">2 Credits</option>
                                    <option value="3" selected>3 Credits</option>
                                    <option value="4">4 Credits</option>
                                    <option value="5">5 Credits</option>
                                    <option value="6">6 Credits</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="subject_type" class="form-label">Subject Type</label>
                                <select class="form-select" id="subject_type" name="subject_type">
                                    <option value="theory">Theory</option>
                                    <option value="practical">Practical</option>
                                    <option value="project">Project</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Faculty Modal -->
<div class="modal fade" id="assignFacultyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Assign Faculty to Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_faculty">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assign_subject_id" class="form-label">Subject *</label>
                                <select class="form-select" id="assign_subject_id" name="subject_id" required>
                                    <option value="">Select Subject</option>
                                    <?php 
                                    mysqli_data_seek($subjects, 0);
                                    while ($subj = mysqli_fetch_assoc($subjects)): 
                                    ?>
                                    <option value="<?php echo $subj['subject_id']; ?>">
                                        <?php echo $subj['subject_code'] . ' - ' . $subj['subject_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assign_faculty_id" class="form-label">Faculty *</label>
                                <select class="form-select" id="assign_faculty_id" name="faculty_id" required>
                                    <option value="">Select Faculty</option>
                                    <?php 
                                    mysqli_data_seek($faculty_members, 0);
                                    while ($faculty = mysqli_fetch_assoc($faculty_members)): 
                                    ?>
                                    <option value="<?php echo $faculty['faculty_id']; ?>">
                                        <?php echo $faculty['employee_id'] . ' - ' . $faculty['first_name'] . ' ' . $faculty['last_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assign_section_id" class="form-label">Section *</label>
                                <select class="form-select" id="assign_section_id" name="section_id" required>
                                    <option value="">Select Section</option>
                                    <?php 
                                    mysqli_data_seek($sections, 0);
                                    while ($section = mysqli_fetch_assoc($sections)): 
                                    ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo $section['programme_name'] . ' - Section ' . $section['section_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assign_academic_year" class="form-label">Academic Year *</label>
                                <input type="text" class="form-control" id="assign_academic_year" name="academic_year" 
                                       placeholder="e.g., 2024-25" value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> This will assign the selected faculty to teach the subject for the specified section during the academic year.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Assign Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="subject_id" id="edit_subject_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit_subject_name" class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_subject_code" class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" id="edit_subject_code" name="subject_code" 
                                       style="text-transform: uppercase;" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_programme_id" class="form-label">Programme *</label>
                                <select class="form-select" id="edit_programme_id" name="programme_id" required>
                                    <?php 
                                    mysqli_data_seek($programmes, 0);
                                    while ($prog = mysqli_fetch_assoc($programmes)): 
                                    ?>
                                    <option value="<?php echo $prog['programme_id']; ?>">
                                        <?php echo $prog['programme_code'] . ' - ' . $prog['programme_name']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_semester_id" class="form-label">Semester *</label>
                                <select class="form-select" id="edit_semester_id" name="semester_id" required>
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
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_credits" class="form-label">Credits</label>
                                <select class="form-select" id="edit_credits" name="credits">
                                    <option value="1">1 Credit</option>
                                    <option value="2">2 Credits</option>
                                    <option value="3">3 Credits</option>
                                    <option value="4">4 Credits</option>
                                    <option value="5">5 Credits</option>
                                    <option value="6">6 Credits</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_subject_type" class="form-label">Subject Type</label>
                                <select class="form-select" id="edit_subject_type" name="subject_type">
                                    <option value="theory">Theory</option>
                                    <option value="practical">Practical</option>
                                    <option value="project">Project</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Subject Modal -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="subject_id" id="delete_subject_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the subject "<span id="delete_subject_name"></span>"?
                        <br><br>
                        <strong>This action cannot be undone!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Assigned Faculty Modal -->
<div class="modal fade" id="viewAssignedFacultyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-users"></i> Assigned Faculty - <span id="faculty_subject_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <div class="modal-body">
                <div id="assigned_faculty_content">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function editSubject(subject) {
    document.getElementById('edit_subject_id').value = subject.subject_id;
    document.getElementById('edit_subject_name').value = subject.subject_name;
    document.getElementById('edit_subject_code').value = subject.subject_code;
    document.getElementById('edit_programme_id').value = subject.programme_id;
    document.getElementById('edit_semester_id').value = subject.semester_id;
    document.getElementById('edit_credits').value = subject.credits;
    document.getElementById('edit_subject_type').value = subject.subject_type;
    document.getElementById('edit_status').value = subject.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editSubjectModal'));
    editModal.show();
}

function deleteSubject(subjectId, subjectName) {
    document.getElementById('delete_subject_id').value = subjectId;
    document.getElementById('delete_subject_name').textContent = subjectName;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteSubjectModal'));
    deleteModal.show();
}

function viewAssignedFaculty(subjectId, subjectName) {
    document.getElementById('faculty_subject_name').textContent = subjectName;
    
    // Fetch assigned faculty using AJAX
    fetch('get_assigned_faculty.php?subject_id=' + subjectId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('assigned_faculty_content').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('assigned_faculty_content').innerHTML = 
                '<div class="alert alert-danger">Error loading faculty assignments.</div>';
        });
    
    var viewModal = new bootstrap.Modal(document.getElementById('viewAssignedFacultyModal'));
    viewModal.show();
}

// Auto-generate subject code based on subject name
document.addEventListener('DOMContentLoaded', function() {
    const subjectNameInput = document.getElementById('subject_name');
    const subjectCodeInput = document.getElementById('subject_code');
    
    if (subjectNameInput && subjectCodeInput) {
        subjectNameInput.addEventListener('blur', function() {
            const subjectName = this.value;
            if (subjectName && !subjectCodeInput.value) {
                // Extract first letters of each word (max 6 characters)
                const words = subjectName.split(' ');
                let code = '';
                for (let word of words) {
                    if (word.length > 0 && code.length < 6) {
                        code += word.charAt(0).toUpperCase();
                    }
                }
                // Add some numbers if too short
                if (code.length < 3) {
                    code += '101';
                }
                subjectCodeInput.value = code;
            }
        });
    }
    
    // Subject code format validation and auto-uppercase
    const codeInputs = ['subject_code', 'edit_subject_code'];
    codeInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                const code = this.value;
                if (code && !/^[A-Z0-9]{3,8}$/.test(code)) {
                    this.setCustomValidity('Subject code should be 3-8 uppercase letters/numbers');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Academic year format validation
    const academicYearInput = document.getElementById('assign_academic_year');
    if (academicYearInput) {
        academicYearInput.addEventListener('input', function() {
            const year = this.value;
            const pattern = /^\d{4}-\d{2}$/;
            if (year && !pattern.test(year)) {
                this.setCustomValidity('Please enter academic year in format YYYY-YY (e.g., 2024-25)');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});

// Remove faculty assignment
function removeFacultyAssignment(allocationId, facultyName, subjectName) {
    if (confirm('Are you sure you want to remove ' + facultyName + ' from ' + subjectName + '?')) {
        fetch('remove_faculty_assignment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'allocation_id=' + allocationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the faculty assignments
                const subjectId = document.getElementById('current_subject_id').value;
                viewAssignedFaculty(subjectId, subjectName);
                
                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> Faculty assignment removed successfully!';
                document.querySelector('.card-body').insertBefore(alertDiv, document.querySelector('.table-responsive'));
                
                // Auto-dismiss after 3 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 3000);
            } else {
                alert('Error removing faculty assignment: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error removing faculty assignment.');
        });
    }
}
</script>