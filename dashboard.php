<?php
include 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Get user information
$user_type = getUserType();
$username = $_SESSION['username'];

// Get current page
$current_page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Define allowed pages based on user type
$allowed_pages = array(
    'admin' => array('home', 'batch', 'semester', 'programme', 'section', 'students', 'faculty', 'subjects', 'attendance', 'reports', 'user_management'),
    'hod' => array('home', 'batch', 'semester', 'programme', 'section', 'students', 'faculty', 'subjects', 'attendance', 'reports'),
    'faculty' => array('home', 'students', 'attendance', 'reports'),
    'student' => array('home', 'attendance', 'profile')
);

// Check if current page is allowed for user type
if (!in_array($current_page, $allowed_pages[$user_type])) {
    $current_page = 'home';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Attendance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
        }
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .stats-card h3 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .navbar-brand {
            font-weight: 700;
            color: #333;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
        }
        .badge {
            border-radius: 20px;
            padding: 5px 12px;
        }
        .user-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .user-info h5 {
            color: white;
            margin: 0;
            font-weight: 600;
        }
        .user-info p {
            color: rgba(255, 255, 255, 0.8);
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        .navbar {
            border-radius: 15px;
            border: none;
        }
        .user-avatar {
            display: flex;
            align-items: center;
        }
        .user-info {
            text-align: left;
        }
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        .user-role {
            font-size: 12px;
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            min-width: 200px;
        }
        .dropdown-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px 10px 0 0;
            margin: -5px -5px 0 -5px;
        }
        .dropdown-item {
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
        .breadcrumb {
            background: none;
            padding: 0;
        }
        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
        }
        .breadcrumb-item.active {
            color: #6c757d;
        }
        .current-time {
            font-size: 14px;
        }
        .navbar .nav-link {
            color: #333 !important;
            font-weight: 500;
        }
        .navbar .dropdown-toggle::after {
            margin-left: 0.5em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="d-flex flex-column h-100">
                    <div class="user-info">
                        <i class="fas fa-user-circle fa-3x mb-2"></i>
                        <h5><?php echo ucfirst($username); ?></h5>
                        <p><?php echo strtoupper($user_type); ?></p>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link <?php echo ($current_page == 'home') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=home">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        
                        <?php if (in_array($user_type, ['admin', 'hod'])): ?>
                        <a class="nav-link <?php echo ($current_page == 'batch') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=batch">
                            <i class="fas fa-calendar-alt"></i> Batch Management
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'semester') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=semester">
                            <i class="fas fa-book"></i> Semester Management
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'programme') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=programme">
                            <i class="fas fa-graduation-cap"></i> Programme Management
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'section') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=section">
                            <i class="fas fa-users"></i> Section Management
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'faculty') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=faculty">
                            <i class="fas fa-chalkboard-teacher"></i> Faculty Management
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'subjects') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=subjects">
                            <i class="fas fa-book-open"></i> Subject Management
                        </a>
                        <?php endif; ?>
                        
                        <a class="nav-link <?php echo ($current_page == 'students') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=students">
                            <i class="fas fa-user-graduate"></i> Student Management
                        </a>
                        
                        <a class="nav-link <?php echo ($current_page == 'attendance') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=attendance">
                            <i class="fas fa-clipboard-check"></i> Attendance
                        </a>
                        
                        <?php if ($user_type == 'student'): ?>
                        <a class="nav-link <?php echo ($current_page == 'profile') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=profile">
                            <i class="fas fa-user-edit"></i> My Profile
                        </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($user_type, ['admin', 'hod', 'faculty'])): ?>
                        <a class="nav-link <?php echo ($current_page == 'reports') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=reports">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($user_type == 'admin'): ?>
                        <a class="nav-link <?php echo ($current_page == 'user_management') ? 'active' : ''; ?>" 
                           href="dashboard.php?page=user_management">
                            <i class="fas fa-users-cog"></i> User Management
                        </a>
                        <?php endif; ?>
                    </nav>
                    
                    <div class="mt-auto">
                        <a class="nav-link" href="dashboard.php?logout=1">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Top Navigation Bar -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
                    <div class="container-fluid">
                        <h2 class="navbar-brand mb-0">
                            <i class="fas fa-tachometer-alt"></i> 
                            <?php echo ucwords(str_replace('_', ' ', $current_page)); ?>
                        </h2>
                        
                        <!-- User Profile Dropdown -->
                        <div class="navbar-nav ms-auto">
                            <div class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" 
                                   role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar me-2">
                                        <i class="fas fa-user-circle fa-lg text-primary"></i>
                                    </div>
                                    <div class="user-info d-none d-md-block">
                                        <div class="user-name"><?php echo ucfirst($username); ?></div>
                                        <small class="user-role text-muted"><?php echo strtoupper($user_type); ?></small>
                                    </div>
                                    <i class="fas fa-chevron-down ms-2"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                    <li class="dropdown-header">
                                        <i class="fas fa-user"></i> <?php echo ucfirst($username); ?>
                                        <br><small class="text-muted"><?php echo strtoupper($user_type); ?></small>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    
                                    <?php if ($user_type == 'student'): ?>
                                    <li>
                                        <a class="dropdown-item" href="dashboard.php?page=profile">
                                            <i class="fas fa-user-edit"></i> My Profile
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewProfileModal">
                                            <i class="fas fa-user"></i> View Profile
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                            <i class="fas fa-key"></i> Change Password
                                        </a>
                                    </li>
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="confirmLogout()">
                                            <i class="fas fa-sign-out-alt text-danger"></i> <span class="text-danger">Logout</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Date and Time Display -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="breadcrumb-info">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <?php if ($current_page != 'home'): ?>
                                <li class="breadcrumb-item active"><?php echo ucwords(str_replace('_', ' ', $current_page)); ?></li>
                                <?php endif; ?>
                            </ol>
                        </nav>
                    </div>
                    <div class="current-time text-muted">
                        <i class="fas fa-clock"></i> 
                        <span id="current-time"><?php echo date('F j, Y - g:i A'); ?></span>
                    </div>
                </div>
                
                <?php
                // Include the requested page
                switch ($current_page) {
                    case 'batch':
                        include 'batch.php';
                        break;
                    case 'semester':
                        include 'semester.php';
                        break;
                    case 'programme':
                        include 'programme.php';
                        break;
                    case 'section':
                        include 'section.php';
                        break;
                    case 'students':
                        include 'students.php';
                        break;
                    case 'faculty':
                        include 'faculty.php';
                        break;
                    case 'subjects':
                        include 'subjects.php';
                        break;
                    case 'attendance':
                        include 'attendance.php';
                        break;
                    case 'reports':
                        include 'reports.php';
                        break;
                    case 'profile':
                        include 'profile.php';
                        break;
                    case 'user_management':
                        include 'user_management.php';
                        break;
                    default:
                        include 'home.php';
                        break;
                }
                ?>
            </div>
        </div>
    </div>

    <!-- View Profile Modal -->
    <div class="modal fade" id="viewProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user"></i> My Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="profile-content">
                        <!-- Profile content will be loaded here -->
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                            <p class="mt-2">Loading profile...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="changePasswordForm" method="POST" action="change_password.php">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Password Requirements:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Minimum 6 characters</li>
                                <li>Recommended: Mix of letters, numbers, and symbols</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <div id="password-strength" class="mt-1"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <div id="password-match" class="mt-1"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="changePasswordBtn">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i class="fas fa-sign-out-alt"></i> Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                        <h6>Are you sure you want to logout?</h6>
                        <p class="text-muted">You will need to login again to access the system.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="dashboard.php?logout=1" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 10,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
            
            // Update time every minute
            setInterval(updateTime, 60000);
            
            // Load user profile when modal is shown
            $('#viewProfileModal').on('show.bs.modal', function() {
                loadUserProfile();
            });
        });
        
        // Update current time
        function updateTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric', 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            };
            document.getElementById('current-time').textContent = now.toLocaleDateString('en-US', options);
        }
        
        // Load user profile
        function loadUserProfile() {
            fetch('get_user_profile.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('profile-content').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('profile-content').innerHTML = 
                        '<div class="alert alert-danger">Error loading profile information.</div>';
                });
        }
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                button.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                button.className = 'fas fa-eye';
            }
        }
        
        // Password strength checker
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('password-strength');
            const matchDiv = document.getElementById('password-match');
            const changeBtn = document.getElementById('changePasswordBtn');
            
            if (newPasswordField) {
                newPasswordField.addEventListener('input', function() {
                    const password = this.value;
                    const strength = checkPasswordStrength(password);
                    
                    strengthDiv.innerHTML = `
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar ${strength.class}" 
                                 style="width: ${strength.percentage}%"></div>
                        </div>
                        <small class="${strength.textClass}">${strength.text}</small>
                    `;
                    
                    checkPasswordMatch();
                });
            }
            
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', checkPasswordMatch);
            }
            
            function checkPasswordStrength(password) {
                let strength = 0;
                let feedback = [];
                
                if (password.length >= 6) strength += 1;
                else feedback.push('At least 6 characters');
                
                if (/[a-z]/.test(password)) strength += 1;
                else feedback.push('lowercase letter');
                
                if (/[A-Z]/.test(password)) strength += 1;
                else feedback.push('uppercase letter');
                
                if (/[0-9]/.test(password)) strength += 1;
                else feedback.push('number');
                
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                else feedback.push('special character');
                
                const strengthLevels = [
                    { class: 'bg-danger', textClass: 'text-danger', text: 'Very Weak', percentage: 20 },
                    { class: 'bg-danger', textClass: 'text-danger', text: 'Weak', percentage: 40 },
                    { class: 'bg-warning', textClass: 'text-warning', text: 'Fair', percentage: 60 },
                    { class: 'bg-info', textClass: 'text-info', text: 'Good', percentage: 80 },
                    { class: 'bg-success', textClass: 'text-success', text: 'Strong', percentage: 100 }
                ];
                
                return strengthLevels[Math.min(strength, 4)];
            }
            
            function checkPasswordMatch() {
                const newPassword = newPasswordField.value;
                const confirmPassword = confirmPasswordField.value;
                
                if (confirmPassword.length > 0) {
                    if (newPassword === confirmPassword) {
                        matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> Passwords match</small>';
                        changeBtn.disabled = false;
                    } else {
                        matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> Passwords do not match</small>';
                        changeBtn.disabled = true;
                    }
                } else {
                    matchDiv.innerHTML = '';
                    changeBtn.disabled = false;
                }
            }
            
            // Handle change password form submission
            const changePasswordForm = document.getElementById('changePasswordForm');
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    changeBtn.disabled = true;
                    changeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
                    
                    fetch('change_password.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success alert-dismissible fade show';
                            alertDiv.innerHTML = `
                                <i class="fas fa-check-circle"></i> ${data.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            
                            // Close modal and show message
                            const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                            modal.hide();
                            
                            // Add alert to main content
                            const mainContent = document.querySelector('.main-content');
                            mainContent.insertBefore(alertDiv, mainContent.firstChild);
                            
                            // Reset form
                            changePasswordForm.reset();
                            strengthDiv.innerHTML = '';
                            matchDiv.innerHTML = '';
                        } else {
                            // Show error in modal
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger';
                            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
                            
                            const modalBody = document.querySelector('#changePasswordModal .modal-body');
                            modalBody.insertBefore(errorDiv, modalBody.firstChild);
                            
                            // Remove error after 5 seconds
                            setTimeout(() => {
                                errorDiv.remove();
                            }, 5000);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while changing password.');
                    })
                    .finally(() => {
                        changeBtn.disabled = false;
                        changeBtn.innerHTML = '<i class="fas fa-save"></i> Change Password';
                    });
                });
            }
        });
        
        // Confirm logout
        function confirmLogout() {
            const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
        }
    </script>
</body>
</html>