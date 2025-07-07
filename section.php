<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $section_name = validateInput($_POST['section_name']);
                $programme_id = validateInput($_POST['programme_id']);
                $batch_id = validateInput($_POST['batch_id']);
                $semester_id = validateInput($_POST['semester_id']);
                $capacity = validateInput($_POST['capacity']);
                $status = validateInput($_POST['status']);
                
                if (empty($section_name) || empty($programme_id) || empty($batch_id) || empty($semester_id)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if section already exists for the same programme, batch, and semester
                    $stmt = mysqli_prepare($conn, "SELECT section_id FROM class_section WHERE section_name = ? AND programme_id = ? AND batch_id = ? AND semester_id = ?");
                    mysqli_stmt_bind_param($stmt, "siii", $section_name, $programme_id, $batch_id, $semester_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Section already exists for this programme, batch, and semester combination!";
                    } else {
                        $stmt = mysqli_prepare($conn, "INSERT INTO class_section (section_name, programme_id, batch_id, semester_id, capacity, status) VALUES (?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "siiiis", $section_name, $programme_id, $batch_id, $semester_id, $capacity, $status);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Section added successfully!";
                        } else {
                            $error = "Error adding section: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $section_id = validateInput($_POST['section_id']);
                $section_name = validateInput($_POST['section_name']);
                $programme_id = validateInput($_POST['programme_id']);
                $batch_id = validateInput($_POST['batch_id']);
                $semester_id = validateInput($_POST['semester_id']);
                $capacity = validateInput($_POST['capacity']);
                $status = validateInput($_POST['status']);
                
                if (empty($section_name) || empty($programme_id) || empty($batch_id) || empty($semester_id)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if section already exists (excluding current record)
                    $stmt = mysqli_prepare($conn, "SELECT section_id FROM class_section WHERE section_name = ? AND programme_id = ? AND batch_id = ? AND semester_id = ? AND section_id != ?");
                    mysqli_stmt_bind_param($stmt, "siiii", $section_name, $programme_id, $batch_id, $semester_id, $section_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Section already exists for this programme, batch, and semester combination!";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE class_section SET section_name = ?, programme_id = ?, batch_id = ?, semester_id = ?, capacity = ?, status = ? WHERE section_id = ?");
                        mysqli_stmt_bind_param($stmt, "siiiisi", $section_name, $programme_id, $batch_id, $semester_id, $capacity, $status, $section_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "Section updated successfully!";
                        } else {
                            $error = "Error updating section: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'delete':
                $section_id = validateInput($_POST['section_id']);
                
                // Check if section is being used
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM students WHERE section_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $section_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $usage_count = mysqli_fetch_assoc($result)['count'];
                
                if ($usage_count > 0) {
                    $error = "Cannot delete section. It is being used by " . $usage_count . " students.";
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM class_section WHERE section_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $section_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Section deleted successfully!";
                    } else {
                        $error = "Error deleting section: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Get all sections with related information
$stmt = mysqli_prepare($conn, "SELECT cs.*, p.programme_name, p.programme_code, b.batch_name, s.semester_name,
                              (SELECT COUNT(*) FROM students st WHERE st.section_id = cs.section_id) as student_count
                              FROM class_section cs
                              LEFT JOIN programme p ON cs.programme_id = p.programme_id
                              LEFT JOIN batch b ON cs.batch_id = b.batch_id
                              LEFT JOIN semester s ON cs.semester_id = s.semester_id
                              ORDER BY p.programme_name, b.batch_name, s.semester_name, cs.section_name");
mysqli_stmt_execute($stmt);
$sections = mysqli_stmt_get_result($stmt);

// Get data for dropdowns
$programmes = mysqli_query($conn, "SELECT * FROM programme WHERE status = 'active' ORDER BY programme_name");
$batches = mysqli_query($conn, "SELECT * FROM batch WHERE status = 'active' ORDER BY batch_year DESC");
$semesters = mysqli_query($conn, "SELECT * FROM semester WHERE status = 'active' ORDER BY semester_number");
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
                <h5 class="mb-0"><i class="fas fa-users"></i> Section Management</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="fas fa-plus"></i> Add New Section
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Section ID</th>
                                <th>Section Name</th>
                                <th>Programme</th>
                                <th>Batch</th>
                                <th>Semester</th>
                                <th>Capacity</th>
                                <th>Current Students</th>
                                <th>Occupancy</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($section = mysqli_fetch_assoc($sections)): ?>
                            <?php 
                                $occupancy_percent = $section['capacity'] > 0 ? round(($section['student_count'] / $section['capacity']) * 100, 1) : 0;
                                $occupancy_class = $occupancy_percent >= 90 ? 'bg-danger' : ($occupancy_percent >= 75 ? 'bg-warning' : 'bg-success');
                            ?>
                            <tr>
                                <td><?php echo $section['section_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($section['section_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($section['programme_code']); ?></span>
                                    <br>
                                    <small><?php echo htmlspecialchars($section['programme_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($section['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($section['semester_name']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $section['capacity']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $section['student_count']; ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php echo $occupancy_class; ?>" 
                                             style="width: <?php echo $occupancy_percent; ?>%">
                                            <?php echo $occupancy_percent; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $section['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($section['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteSection(<?php echo $section['section_id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')">
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

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="section_name" class="form-label">Section Name *</label>
                                <input type="text" class="form-control" id="section_name" name="section_name" 
                                       placeholder="e.g., A, B, C" style="text-transform: uppercase;" required>
                                <div class="form-text">Usually single letters like A, B, C</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="60" min="1" max="100">
                                <div class="form-text">Maximum number of students</div>
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
                                <label for="batch_id" class="form-label">Batch *</label>
                                <select class="form-select" id="batch_id" name="batch_id" required>
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
                    </div>
                    
                    <div class="row">
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
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Sections group students within the same programme, batch, and semester for class organization.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_section_name" class="form-label">Section Name *</label>
                                <input type="text" class="form-control" id="edit_section_name" name="section_name" 
                                       style="text-transform: uppercase;" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_capacity" class="form-label">Capacity</label>
                                <input type="number" class="form-control" id="edit_capacity" name="capacity" 
                                       min="1" max="100">
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
                                <label for="edit_batch_id" class="form-label">Batch *</label>
                                <select class="form-select" id="edit_batch_id" name="batch_id" required>
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
                    </div>
                    
                    <div class="row">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="section_id" id="delete_section_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the section "<span id="delete_section_name"></span>"?
                        <br><br>
                        <strong>This action cannot be undone!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSection(section) {
    document.getElementById('edit_section_id').value = section.section_id;
    document.getElementById('edit_section_name').value = section.section_name;
    document.getElementById('edit_programme_id').value = section.programme_id;
    document.getElementById('edit_batch_id').value = section.batch_id;
    document.getElementById('edit_semester_id').value = section.semester_id;
    document.getElementById('edit_capacity').value = section.capacity;
    document.getElementById('edit_status').value = section.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editSectionModal'));
    editModal.show();
}

function deleteSection(sectionId, sectionName) {
    document.getElementById('delete_section_id').value = sectionId;
    document.getElementById('delete_section_name').textContent = sectionName;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteSectionModal'));
    deleteModal.show();
}

// Auto-uppercase section name
document.addEventListener('DOMContentLoaded', function() {
    const sectionInputs = ['section_name', 'edit_section_name'];
    sectionInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        }
    });
});
</script>