<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $semester_name = validateInput($_POST['semester_name']);
                $semester_number = validateInput($_POST['semester_number']);
                $academic_year = validateInput($_POST['academic_year']);
                $status = validateInput($_POST['status']);
                
                if (empty($semester_name) || empty($semester_number) || empty($academic_year)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if semester number already exists for the academic year
                    $stmt = mysqli_prepare($conn, "SELECT semester_id FROM semester WHERE semester_number = ? AND academic_year = ?");
                    mysqli_stmt_bind_param($stmt, "is", $semester_number, $academic_year);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Semester number already exists for this academic year!";
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO semester (semester_name, semester_number, academic_year, status) VALUES (?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "siss", $semester_name, $semester_number, $academic_year, $status);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Semester added successfully!";
                        } else {
                            $error = "Error adding semester: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $semester_id = validateInput($_POST['semester_id']);
                $semester_name = validateInput($_POST['semester_name']);
                $semester_number = validateInput($_POST['semester_number']);
                $academic_year = validateInput($_POST['academic_year']);
                $status = validateInput($_POST['status']);
                
                if (empty($semester_name) || empty($semester_number) || empty($academic_year)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if semester number already exists for the academic year (excluding current record)
                    $stmt = mysqli_prepare($conn, "SELECT semester_id FROM semester WHERE semester_number = ? AND academic_year = ? AND semester_id != ?");
                    mysqli_stmt_bind_param($stmt, "isi", $semester_number, $academic_year, $semester_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Semester number already exists for this academic year!";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE semester SET semester_name = ?, semester_number = ?, academic_year = ?, status = ? WHERE semester_id = ?");
                        mysqli_stmt_bind_param($stmt, "sissi", $semester_name, $semester_number, $academic_year, $status, $semester_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Semester updated successfully!";
                        } else {
                            $error = "Error updating semester: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'delete':
                $semester_id = validateInput($_POST['semester_id']);
                
                // Check if semester is being used
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM students WHERE semester_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $semester_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $usage_count = mysqli_fetch_assoc($result)['count'];
                
                if ($usage_count > 0) {
                    $error = "Cannot delete semester. It is being used by " . $usage_count . " students.";
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM semester WHERE semester_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $semester_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Semester deleted successfully!";
                    } else {
                        $error = "Error deleting semester: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Get all semesters
$stmt = mysqli_prepare($conn, "SELECT s.*, 
                              (SELECT COUNT(*) FROM students st WHERE st.semester_id = s.semester_id) as student_count,
                              (SELECT COUNT(*) FROM subjects sub WHERE sub.semester_id = s.semester_id) as subject_count
                              FROM semester s ORDER BY s.academic_year DESC, s.semester_number");
mysqli_stmt_execute($stmt);
$semesters = mysqli_stmt_get_result($stmt);
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
                <h5 class="mb-0"><i class="fas fa-book"></i> Semester Management</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSemesterModal">
                    <i class="fas fa-plus"></i> Add New Semester
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Semester ID</th>
                                <th>Semester Name</th>
                                <th>Semester Number</th>
                                <th>Academic Year</th>
                                <th>Students Count</th>
                                <th>Subjects Count</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($semester = mysqli_fetch_assoc($semesters)): ?>
                            <tr>
                                <td><?php echo $semester['semester_id']; ?></td>
                                <td><?php echo htmlspecialchars($semester['semester_name']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $semester['semester_number']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($semester['academic_year']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $semester['student_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $semester['subject_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $semester['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($semester['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($semester['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editSemester(<?php echo htmlspecialchars(json_encode($semester)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteSemester(<?php echo $semester['semester_id']; ?>, '<?php echo htmlspecialchars($semester['semester_name']); ?>')">
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

<!-- Add Semester Modal -->
<div class="modal fade" id="addSemesterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="semester_name" class="form-label">Semester Name *</label>
                        <input type="text" class="form-control" id="semester_name" name="semester_name" 
                               placeholder="e.g., Semester I, First Semester" required>
                        <div class="form-text">Enter the full name of the semester</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester_number" class="form-label">Semester Number *</label>
                        <select class="form-select" id="semester_number" name="semester_number" required>
                            <option value="">Select Semester Number</option>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                            <option value="3">3rd Semester</option>
                            <option value="4">4th Semester</option>
                            <option value="5">5th Semester</option>
                            <option value="6">6th Semester</option>
                            <option value="7">7th Semester</option>
                            <option value="8">8th Semester</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Academic Year *</label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" 
                               placeholder="e.g., 2024-25, 2025-26" value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>" required>
                        <div class="form-text">Format: YYYY-YY (e.g., 2024-25)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Semester Modal -->
<div class="modal fade" id="editSemesterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="semester_id" id="edit_semester_id">
                    <div class="mb-3">
                        <label for="edit_semester_name" class="form-label">Semester Name *</label>
                        <input type="text" class="form-control" id="edit_semester_name" name="semester_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_semester_number" class="form-label">Semester Number *</label>
                        <select class="form-select" id="edit_semester_number" name="semester_number" required>
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                            <option value="3">3rd Semester</option>
                            <option value="4">4th Semester</option>
                            <option value="5">5th Semester</option>
                            <option value="6">6th Semester</option>
                            <option value="7">7th Semester</option>
                            <option value="8">8th Semester</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_academic_year" class="form-label">Academic Year *</label>
                        <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSemesterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="semester_id" id="delete_semester_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the semester "<span id="delete_semester_name"></span>"?
                        <br><br>
                        <strong>This action cannot be undone!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSemester(semester) {
    document.getElementById('edit_semester_id').value = semester.semester_id;
    document.getElementById('edit_semester_name').value = semester.semester_name;
    document.getElementById('edit_semester_number').value = semester.semester_number;
    document.getElementById('edit_academic_year').value = semester.academic_year;
    document.getElementById('edit_status').value = semester.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editSemesterModal'));
    editModal.show();
}

function deleteSemester(semesterId, semesterName) {
    document.getElementById('delete_semester_id').value = semesterId;
    document.getElementById('delete_semester_name').textContent = semesterName;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteSemesterModal'));
    deleteModal.show();
}

// Auto-generate semester name based on semester number
document.addEventListener('DOMContentLoaded', function() {
    const semesterNumberSelect = document.getElementById('semester_number');
    const semesterNameInput = document.getElementById('semester_name');
    
    if (semesterNumberSelect && semesterNameInput) {
        semesterNumberSelect.addEventListener('change', function() {
            const semesterNumber = this.value;
            if (semesterNumber && !semesterNameInput.value) {
                const romanNumerals = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];
                if (semesterNumber <= 8) {
                    semesterNameInput.value = `Semester ${romanNumerals[semesterNumber]}`;
                }
            }
        });
    }
    
    // Academic year format validation
    const academicYearInput = document.getElementById('academic_year');
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
</script>