<?php
global $conn;

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $batch_name = validateInput($_POST['batch_name']);
                $batch_year = validateInput($_POST['batch_year']);
                $status = validateInput($_POST['status']);
                
                if (empty($batch_name) || empty($batch_year)) {
                    $error = "Please fill in all required fields.";
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO batch (batch_name, batch_year, status) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "sis", $batch_name, $batch_year, $status);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Batch added successfully!";
                    } else {
                        $error = "Error adding batch: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $batch_id = validateInput($_POST['batch_id']);
                $batch_name = validateInput($_POST['batch_name']);
                $batch_year = validateInput($_POST['batch_year']);
                $status = validateInput($_POST['status']);
                
                if (empty($batch_name) || empty($batch_year)) {
                    $error = "Please fill in all required fields.";
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE batch SET batch_name = ?, batch_year = ?, status = ? WHERE batch_id = ?");
                    mysqli_stmt_bind_param($stmt, "sisi", $batch_name, $batch_year, $status, $batch_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Batch updated successfully!";
                    } else {
                        $error = "Error updating batch: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'delete':
                $batch_id = validateInput($_POST['batch_id']);
                
                // Check if batch is being used
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM students WHERE batch_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $batch_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $usage_count = mysqli_fetch_assoc($result)['count'];
                
                if ($usage_count > 0) {
                    $error = "Cannot delete batch. It is being used by " . $usage_count . " students.";
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM batch WHERE batch_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $batch_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Batch deleted successfully!";
                    } else {
                        $error = "Error deleting batch: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Get all batches
$stmt = mysqli_prepare($conn, "SELECT b.*, 
                              (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.batch_id) as student_count
                              FROM batch b ORDER BY b.batch_year DESC, b.batch_name");
mysqli_stmt_execute($stmt);
$batches = mysqli_stmt_get_result($stmt);
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
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Batch Management</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                    <i class="fas fa-plus"></i> Add New Batch
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Batch ID</th>
                                <th>Batch Name</th>
                                <th>Batch Year</th>
                                <th>Students Count</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($batch = mysqli_fetch_assoc($batches)): ?>
                            <tr>
                                <td><?php echo $batch['batch_id']; ?></td>
                                <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                <td><?php echo $batch['batch_year']; ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $batch['student_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $batch['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($batch['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($batch['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editBatch(<?php echo htmlspecialchars(json_encode($batch)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteBatch(<?php echo $batch['batch_id']; ?>, '<?php echo htmlspecialchars($batch['batch_name']); ?>')">
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

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="batch_name" class="form-label">Batch Name *</label>
                        <input type="text" class="form-control" id="batch_name" name="batch_name" required>
                        <div class="form-text">e.g., 2024-2025, Batch A, etc.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="batch_year" class="form-label">Batch Year *</label>
                        <input type="number" class="form-control" id="batch_year" name="batch_year" 
                               min="2020" max="2030" value="<?php echo date('Y'); ?>" required>
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
                    <button type="submit" class="btn btn-primary">Add Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Batch Modal -->
<div class="modal fade" id="editBatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="batch_id" id="edit_batch_id">
                    
                    <div class="mb-3">
                        <label for="edit_batch_name" class="form-label">Batch Name *</label>
                        <input type="text" class="form-control" id="edit_batch_name" name="batch_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_batch_year" class="form-label">Batch Year *</label>
                        <input type="number" class="form-control" id="edit_batch_year" name="batch_year" 
                               min="2020" max="2030" required>
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
                    <button type="submit" class="btn btn-primary">Update Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="batch_id" id="delete_batch_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the batch "<span id="delete_batch_name"></span>"?
                        <br><br>
                        <strong>This action cannot be undone!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBatch(batch) {
    document.getElementById('edit_batch_id').value = batch.batch_id;
    document.getElementById('edit_batch_name').value = batch.batch_name;
    document.getElementById('edit_batch_year').value = batch.batch_year;
    document.getElementById('edit_status').value = batch.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editBatchModal'));
    editModal.show();
}

function deleteBatch(batchId, batchName) {
    document.getElementById('delete_batch_id').value = batchId;
    document.getElementById('delete_batch_name').textContent = batchName;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteBatchModal'));
    deleteModal.show();
}
</script>