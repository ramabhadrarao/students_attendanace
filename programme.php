<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $programme_name = validateInput($_POST['programme_name']);
                $programme_code = validateInput($_POST['programme_code']);
                $duration_years = validateInput($_POST['duration_years']);
                $status = validateInput($_POST['status']);
                
                if (empty($programme_name) || empty($programme_code) || empty($duration_years)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if programme code already exists
                    $stmt = mysqli_prepare($conn, "SELECT programme_id FROM programme WHERE programme_code = ?");
                    mysqli_stmt_bind_param($stmt, "s", $programme_code);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Programme code already exists!";
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO programme (programme_name, programme_code, duration_years, status) VALUES (?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssis", $programme_name, $programme_code, $duration_years, $status);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Programme added successfully!";
                        } else {
                            $error = "Error adding programme: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $programme_id = validateInput($_POST['programme_id']);
                $programme_name = validateInput($_POST['programme_name']);
                $programme_code = validateInput($_POST['programme_code']);
                $duration_years = validateInput($_POST['duration_years']);
                $status = validateInput($_POST['status']);
                
                if (empty($programme_name) || empty($programme_code) || empty($duration_years)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if programme code already exists (excluding current record)
                    $stmt = mysqli_prepare($conn, "SELECT programme_id FROM programme WHERE programme_code = ? AND programme_id != ?");
                    mysqli_stmt_bind_param($stmt, "si", $programme_code, $programme_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Programme code already exists!";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE programme SET programme_name = ?, programme_code = ?, duration_years = ?, status = ? WHERE programme_id = ?");
                        mysqli_stmt_bind_param($stmt, "ssisi", $programme_name, $programme_code, $duration_years, $status, $programme_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Programme updated successfully!";
                        } else {
                            $error = "Error updating programme: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'delete':
                $programme_id = validateInput($_POST['programme_id']);
                
                // Check if programme is being used
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM students WHERE programme_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $programme_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $usage_count = mysqli_fetch_assoc($result)['count'];
                
                if ($usage_count > 0) {
                    $error = "Cannot delete programme. It is being used by " . $usage_count . " students.";
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM programme WHERE programme_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $programme_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Programme deleted successfully!";
                    } else {
                        $error = "Error deleting programme: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Get all programmes
$stmt = mysqli_prepare($conn, "SELECT p.*, 
                              (SELECT COUNT(*) FROM students s WHERE s.programme_id = p.programme_id) as student_count,
                              (SELECT COUNT(*) FROM class_section cs WHERE cs.programme_id = p.programme_id) as section_count,
                              (SELECT COUNT(*) FROM subjects sub WHERE sub.programme_id = p.programme_id) as subject_count
                              FROM programme p ORDER BY p.programme_name");
mysqli_stmt_execute($stmt);
$programmes = mysqli_stmt_get_result($stmt);
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
                <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Programme Management</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgrammeModal">
                    <i class="fas fa-plus"></i> Add New Programme
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Programme ID</th>
                                <th>Programme Name</th>
                                <th>Programme Code</th>
                                <th>Duration (Years)</th>
                                <th>Students Count</th>
                                <th>Sections Count</th>
                                <th>Subjects Count</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($programme = mysqli_fetch_assoc($programmes)): ?>
                            <tr>
                                <td><?php echo $programme['programme_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($programme['programme_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($programme['programme_code']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $programme['duration_years']; ?> Years</span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $programme['student_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?php echo $programme['section_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $programme['subject_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $programme['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($programme['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($programme['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editProgramme(<?php echo htmlspecialchars(json_encode($programme)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteProgramme(<?php echo $programme['programme_id']; ?>, '<?php echo htmlspecialchars($programme['programme_name']); ?>')">
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

<!-- Add Programme Modal -->
<div class="modal fade" id="addProgrammeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Programme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="programme_name" class="form-label">Programme Name *</label>
                        <input type="text" class="form-control" id="programme_name" name="programme_name" 
                               placeholder="e.g., Bachelor of Computer Applications" required>
                        <div class="form-text">Enter the full name of the programme</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="programme_code" class="form-label">Programme Code *</label>
                        <input type="text" class="form-control" id="programme_code" name="programme_code" 
                               placeholder="e.g., BCA, BCOM, BSC" style="text-transform: uppercase;" required>
                        <div class="form-text">Enter a unique code for the programme (usually 3-5 characters)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration_years" class="form-label">Duration (Years) *</label>
                        <select class="form-select" id="duration_years" name="duration_years" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Once created, you can add subjects and sections specific to this programme.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Programme</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Programme Modal -->
<div class="modal fade" id="editProgrammeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Programme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="programme_id" id="edit_programme_id">
                    
                    <div class="mb-3">
                        <label for="edit_programme_name" class="form-label">Programme Name *</label>
                        <input type="text" class="form-control" id="edit_programme_name" name="programme_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_programme_code" class="form-label">Programme Code *</label>
                        <input type="text" class="form-control" id="edit_programme_code" name="programme_code" 
                               style="text-transform: uppercase;" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_duration_years" class="form-label">Duration (Years) *</label>
                        <select class="form-select" id="edit_duration_years" name="duration_years" required>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
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
                    <button type="submit" class="btn btn-primary">Update Programme</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteProgrammeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Programme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="programme_id" id="delete_programme_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the programme "<span id="delete_programme_name"></span>"?
                        <br><br>
                        <strong>This action cannot be undone!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Programme</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProgramme(programme) {
    document.getElementById('edit_programme_id').value = programme.programme_id;
    document.getElementById('edit_programme_name').value = programme.programme_name;
    document.getElementById('edit_programme_code').value = programme.programme_code;
    document.getElementById('edit_duration_years').value = programme.duration_years;
    document.getElementById('edit_status').value = programme.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editProgrammeModal'));
    editModal.show();
}

function deleteProgramme(programmeId, programmeName) {
    document.getElementById('delete_programme_id').value = programmeId;
    document.getElementById('delete_programme_name').textContent = programmeName;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteProgrammeModal'));
    deleteModal.show();
}

// Auto-generate programme code based on programme name
document.addEventListener('DOMContentLoaded', function() {
    const programmeNameInput = document.getElementById('programme_name');
    const programmeCodeInput = document.getElementById('programme_code');
    
    if (programmeNameInput && programmeCodeInput) {
        programmeNameInput.addEventListener('blur', function() {
            const programmeName = this.value;
            if (programmeName && !programmeCodeInput.value) {
                // Extract first letters of each word
                const words = programmeName.split(' ');
                let code = '';
                for (let word of words) {
                    if (word.length > 0 && code.length < 5) {
                        code += word.charAt(0).toUpperCase();
                    }
                }
                programmeCodeInput.value = code;
            }
        });
    }
    
    // Programme code format validation and auto-uppercase
    const codeInputs = ['programme_code', 'edit_programme_code'];
    codeInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
                const code = this.value;
                if (code && !/^[A-Z]{2,5}$/.test(code)) {
                    this.setCustomValidity('Programme code should be 2-5 uppercase letters');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
});
</script>