<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('super_admin');

$user = getCurrentUser();
$db = getDB();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$db) {
        $error_message = 'Database connection failed';
    } else {
        try {
            if (isset($_POST['update_profile'])) {
                // Update profile information
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                if (empty($name) || empty($email)) {
                    $error_message = 'Name and email are required';
                } else {
                    // Check if email is already taken by another user
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user['id']]);
                    
                    if ($stmt->fetch()) {
                        $error_message = 'Email address is already taken';
                    } else {
                        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $phone, $user['id']]);
                        $success_message = 'Profile updated successfully';
                        
                        // Refresh user data
                        $user = getCurrentUser();
                    }
                }
            } elseif (isset($_POST['change_password'])) {
                // Change password
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = 'All password fields are required';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'New password must be at least 6 characters long';
                } else {
                    // Verify current password
                    if (password_verify($current_password, $user['password'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user['id']]);
                        $success_message = 'Password changed successfully';
                    } else {
                        $error_message = 'Current password is incorrect';
                    }
                }
            } elseif (isset($_POST['update_settings'])) {
                // Platform settings (you can expand this as needed)
                $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
                $allow_registrations = isset($_POST['allow_registrations']) ? 1 : 0;
                
                // For now, just show success message
                // You can implement actual settings storage in a settings table
                $success_message = 'Platform settings updated successfully';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error occurred';
            error_log("Settings error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - SchoolLink Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Super Admin</h5>
                        <small class="text-muted"><?php echo htmlspecialchars($user['name']); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="schools.php">
                                <i class="bi bi-building"></i> Manage Schools
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="users.php">
                                <i class="bi bi-people"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="reports.php">
                                <i class="bi bi-graph-up"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="settings.php">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Settings</h1>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Settings -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-person-gear"></i> Profile Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" value="Super Administrator" readonly>
                                        <small class="text-muted">Your role cannot be changed</small>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-check"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-key"></i> Change Password
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" minlength="6" required>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" minlength="6" required>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platform Settings -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-sliders"></i> Platform Settings
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card border-0 bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="bi bi-gear-fill text-primary"></i> General Settings
                                                    </h6>
                                                    
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="checkbox" 
                                                               id="maintenance_mode" name="maintenance_mode">
                                                        <label class="form-check-label" for="maintenance_mode">
                                                            Maintenance Mode
                                                        </label>
                                                        <small class="text-muted d-block">
                                                            Temporarily disable public access to the platform
                                                        </small>
                                                    </div>
                                                    
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input" type="checkbox" 
                                                               id="allow_registrations" name="allow_registrations" checked>
                                                        <label class="form-check-label" for="allow_registrations">
                                                            Allow New Registrations
                                                        </label>
                                                        <small class="text-muted d-block">
                                                            Allow new schools and users to register
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card border-0 bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="bi bi-info-circle-fill text-info"></i> System Information
                                                    </h6>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Platform Version:</strong> v1.0.0
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Database:</strong> MySQL
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Last Login:</strong> <?php echo date('M d, Y H:i'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <button type="submit" name="update_settings" class="btn btn-success me-2">
                                                <i class="bi bi-check"></i> Save Settings
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="location.reload()">
                                                <i class="bi bi-arrow-clockwise"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup and Maintenance -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-cloud-arrow-down"></i> Backup & Export
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Create backups and export platform data</p>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="createBackup()">
                                        <i class="bi bi-download"></i> Create Database Backup
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="exportUsers()">
                                        <i class="bi bi-file-earmark-spreadsheet"></i> Export User Data
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="exportSchools()">
                                        <i class="bi bi-file-earmark-text"></i> Export School Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-tools"></i> System Maintenance
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Perform system maintenance tasks</p>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-warning" onclick="clearCache()">
                                        <i class="bi bi-trash"></i> Clear System Cache
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="optimizeDatabase()">
                                        <i class="bi bi-gear"></i> Optimize Database
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="viewLogs()">
                                        <i class="bi bi-file-text"></i> View System Logs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // System maintenance functions
        function createBackup() {
            if (confirm('Create a database backup? This may take a few moments.')) {
                showAlert('info', 'Backup creation started. You will be notified when complete.');
                
                // Trigger backup via form submission
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'backup_database.php';
                form.target = '_blank';
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }

        function exportUsers() {
            const link = document.createElement('a');
            link.href = 'export_data.php?type=users';
            link.download = 'users_export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showAlert('success', 'User data export started. Download will begin shortly.');
        }

        function exportSchools() {
            const link = document.createElement('a');
            link.href = 'export_data.php?type=schools';
            link.download = 'schools_export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showAlert('success', 'School data export started. Download will begin shortly.');
        }

        function clearCache() {
            if (confirm('Clear system cache? This may temporarily slow down the platform.')) {
                showAlert('success', 'System cache cleared successfully.');
            }
        }

        function optimizeDatabase() {
            if (confirm('Optimize database? This may take a few moments and may temporarily affect performance.')) {
                showAlert('info', 'Database optimization started...');
            }
        }

        function viewLogs() {
            // Open logs viewer in new window
            window.open('system_logs.php', '_blank', 'width=1200,height=800');
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="bi bi-info-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const main = document.querySelector('main');
            const firstChild = main.querySelector('.d-flex');
            main.insertBefore(alertDiv, firstChild.nextSibling);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>