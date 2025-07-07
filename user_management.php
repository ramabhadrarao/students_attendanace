<?php
global $conn;

// Check if user is admin
if ($user_type !== 'admin') {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            Access denied. This section is only available to administrators.
          </div>';
    return;
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = validateInput($_POST['username']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                $user_type_new = validateInput($_POST['user_type']);
                $status = validateInput($_POST['status']);
                
                if (empty($username) || empty($password) || empty($user_type_new)) {
                    $error = "Please fill in all required fields.";
                } elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    // Check if username already exists
                    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ?");
                    mysqli_stmt_bind_param($stmt, "s", $username);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Username already exists!";
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, user_type, status) VALUES (?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $user_type_new, $status);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "User added successfully!";
                        } else {
                            $error = "Error adding user: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'edit':
                $user_id = validateInput($_POST['user_id']);
                $username = validateInput($_POST['username']);
                $user_type_edit = validateInput($_POST['user_type']);
                $status = validateInput($_POST['status']);
                
                if (empty($username) || empty($user_type_edit)) {
                    $error = "Please fill in all required fields.";
                } else {
                    // Check if username already exists (excluding current user)
                    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                    mysqli_stmt_bind_param($stmt, "si", $username, $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $error = "Username already exists!";
                    } else {
                        $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, user_type = ?, status = ? WHERE user_id = ?");
                        mysqli_stmt_bind_param($stmt, "sssi", $username, $user_type_edit, $status, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "User updated successfully!";
                        } else {
                            $error = "Error updating user: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'reset_password':
                $user_id = validateInput($_POST['user_id']);
                $new_password = $_POST['new_password'];
                $confirm_new_password = $_POST['confirm_new_password'];
                
                if (empty($new_password)) {
                    $error = "Please enter a new password.";
                } elseif ($new_password !== $confirm_new_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Password reset successfully!";
                    } else {
                        $error = "Error resetting password: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
                
            case 'delete':
                $user_id = validateInput($_POST['user_id']);
                
                // Prevent deletion of current admin user
                if ($user_id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account!";
                } else {
                    // Check if user is linked to students or faculty
                    $stmt = mysqli_prepare($conn, "SELECT 
                                          (SELECT COUNT(*) FROM students WHERE user_id = ?) as student_count,
                                          (SELECT COUNT(*) FROM faculty WHERE user_id = ?) as faculty_count");
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $counts = mysqli_fetch_assoc($result);
                    
                    if ($counts['student_count'] > 0 || $counts['faculty_count'] > 0) {
                        $error = "Cannot delete user. User is linked to student or faculty records.";
                    } else {
                        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = "User deleted successfully!";
                        } else {
                            $error = "Error deleting user: " . mysqli_error($conn);
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
        }
    }
}

// Get all users with additional information
$stmt = mysqli_prepare($conn, "SELECT u.*, 
                              CASE 
                                WHEN u.user_type = 'student' THEN (SELECT CONCAT(s.first_name, ' ', s.last_name) FROM students s WHERE s.user_id = u.user_id)
                                WHEN u.user_type = 'faculty' THEN (SELECT CONCAT(f.first_name, ' ', f.last_name) FROM faculty f WHERE f.user_id = u.user_id)
                                ELSE NULL
                              END as full_name,
                              CASE 
                                WHEN u.user_type = 'student' THEN (SELECT s.admission_number FROM students s WHERE s.user_id = u.user_id)
                                WHEN u.user_type = 'faculty' THEN (SELECT f.employee_id FROM faculty f WHERE f.user_id = u.user_id)
                                ELSE NULL
                              END as identifier
                              FROM users u 
                              ORDER BY u.user_type, u.username");
mysqli_stmt_execute($stmt);
$users = mysqli_stmt_get_result($stmt);
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
                <h5 class="mb-0"><i class="fas fa-users-cog"></i> User Management <span class="badge bg-danger">ADMIN ONLY</span></h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This section allows you to manage all user accounts in the system. Use with caution.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Identifier</th>
                                <th>User Type</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($users)): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php if ($user['identifier']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($user['identifier']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            switch($user['user_type']) {
                                                case 'admin': echo 'bg-danger'; break;
                                                case 'hod': echo 'bg-warning'; break;
                                                case 'faculty': echo 'bg-info'; break;
                                                case 'student': echo 'bg-success'; break;
                                                default: echo 'bg-primary'; break;
                                            }
                                        ?>">
                                        <?php echo strtoupper($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="resetPassword(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-trash"></i>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="form-text">Username must be unique and will be used for login</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="user_type" class="form-label">User Type *</label>
                        <select class="form-select" id="user_type" name="user_type" required>
                            <option value="">Select User Type</option>
                            <option value="admin">Admin</option>
                            <option value="hod">HOD</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Password must be at least 6 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
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
                        <strong>Note:</strong> For students and faculty, it's recommended to create accounts through their respective management sections.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_user_type" class="form-label">User Type *</label>
                        <select class="form-select" id="edit_user_type" name="user_type" required>
                            <option value="admin">Admin</option>
                            <option value="hod">HOD</option>
                            <option value="faculty">Faculty</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> Changing user type may affect system permissions and access.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Resetting password for user: <strong id="reset_username"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Password must be at least 6 characters long</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to delete the user "<span id="delete_username"></span>"?
                        <br><br>
                        <strong>This action cannot be undone and will permanently remove the user account!</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_user_type').value = user.user_type;
    document.getElementById('edit_status').value = user.status;
    
    var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    
    // Clear password fields
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_new_password').value = '';
    
    var resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    resetModal.show();
}

function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').textContent = username;
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    deleteModal.show();
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation for add user
    const addForm = document.querySelector('#addUserModal form');
    const addPassword = document.getElementById('password');
    const addConfirmPassword = document.getElementById('confirm_password');
    
    if (addPassword && addConfirmPassword) {
        function validateAddPasswords() {
            if (addPassword.value !== addConfirmPassword.value) {
                addConfirmPassword.setCustomValidity('Passwords do not match');
            } else {
                addConfirmPassword.setCustomValidity('');
            }
        }
        
        addPassword.addEventListener('input', validateAddPasswords);
        addConfirmPassword.addEventListener('input', validateAddPasswords);
    }
    
    // Password confirmation validation for reset password
    const resetPassword = document.getElementById('new_password');
    const resetConfirmPassword = document.getElementById('confirm_new_password');
    
    if (resetPassword && resetConfirmPassword) {
        function validateResetPasswords() {
            if (resetPassword.value !== resetConfirmPassword.value) {
                resetConfirmPassword.setCustomValidity('Passwords do not match');
            } else {
                resetConfirmPassword.setCustomValidity('');
            }
        }
        
        resetPassword.addEventListener('input', validateResetPasswords);
        resetConfirmPassword.addEventListener('input', validateResetPasswords);
    }
    
    // Username validation
    const usernameInputs = ['username', 'edit_username'];
    usernameInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                const username = this.value;
                // Username should be alphanumeric and underscore, 3-20 characters
                if (username && !/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                    this.setCustomValidity('Username should be 3-20 characters and contain only letters, numbers, and underscores');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });
    
    // Password strength validation
    const passwordInputs = ['password', 'new_password'];
    passwordInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', function() {
                const password = this.value;
                if (password.length < 6) {
                    this.setCustomValidity('Password must be at least 6 characters long');
                } else if (password.length < 8) {
                    // Weak password warning (but still valid)
                    this.setCustomValidity('');
                    this.setAttribute('data-warning', 'Consider using a stronger password (8+ characters)');
                } else {
                    this.setCustomValidity('');
                    this.removeAttribute('data-warning');
                }
            });
        }
    });
});

// Show user type specific warnings
function showUserTypeWarning(userType) {
    let warningText = '';
    
    switch(userType) {
        case 'admin':
            warningText = 'Admin users have full system access including user management.';
            break;
        case 'hod':
            warningText = 'HOD users have access to most administrative functions.';
            break;
        case 'faculty':
            warningText = 'Faculty users can manage attendance and view student information.';
            break;
        case 'student':
            warningText = 'Student users have limited access to view their own information.';
            break;
    }
    
    return warningText;
}

// Add event listener for user type changes
document.addEventListener('DOMContentLoaded', function() {
    const userTypeSelects = ['user_type', 'edit_user_type'];
    
    userTypeSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            select.addEventListener('change', function() {
                const userType = this.value;
                const warning = showUserTypeWarning(userType);
                
                // Find or create warning element
                let warningElement = this.parentNode.querySelector('.user-type-warning');
                if (!warningElement) {
                    warningElement = document.createElement('div');
                    warningElement.className = 'user-type-warning form-text text-info mt-1';
                    this.parentNode.appendChild(warningElement);
                }
                
                warningElement.innerHTML = '<i class="fas fa-info-circle"></i> ' + warning;
            });
        }
    });
});
</script>