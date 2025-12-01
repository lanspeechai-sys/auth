<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('school_admin');
requireApproval();

$user = getCurrentUser();
$db = getDB();

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('../logout.php', 'School information not found.', 'error');
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$db) {
        $error_message = 'Database connection failed';
    } else {
        try {
            if (isset($_POST['update_school'])) {
                // Update school information
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $founded_year = intval($_POST['founded_year'] ?? 0);
                
                if (empty($name) || empty($location) || empty($contact_email)) {
                    $error_message = 'School name, location, and contact email are required';
                } else {
                    // Handle logo upload
                    $logo_path = $school['logo'] ?? '';
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadFile($_FILES['logo'], 'logos', ['jpg', 'jpeg', 'png'], 2 * 1024 * 1024);
                        if ($upload_result['success']) {
                            // Delete old logo if exists
                            if (!empty($school['logo']) && file_exists("../uploads/logos/" . $school['logo'])) {
                                unlink("../uploads/logos/" . $school['logo']);
                            }
                            $logo_path = $upload_result['filename'];
                        } else {
                            $error_message = $upload_result['message'];
                        }
                    }
                    
                    if (!$error_message) {
                        $stmt = $db->prepare("
                            UPDATE schools SET 
                            name = ?, description = ?, location = ?, contact_email = ?, 
                            website = ?, phone = ?, founded_year = ?, logo = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $name, $description, $location, $contact_email, 
                            $website, $phone, $founded_year ?: null, $logo_path, 
                            $user['school_id']
                        ]);
                        
                        $success_message = 'School information updated successfully';
                        // Refresh school data
                        $school = getSchoolInfo($user['school_id']);
                    }
                }
            } elseif (isset($_POST['update_admin_profile'])) {
                // Update admin profile
                $admin_name = trim($_POST['admin_name'] ?? '');
                $admin_email = trim($_POST['admin_email'] ?? '');
                $admin_phone = trim($_POST['admin_phone'] ?? '');
                
                if (empty($admin_name) || empty($admin_email)) {
                    $error_message = 'Name and email are required';
                } else {
                    // Check if email is already taken by another user
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$admin_email, $user['id']]);
                    
                    if ($stmt->fetch()) {
                        $error_message = 'Email address is already taken';
                    } else {
                        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                        $stmt->execute([$admin_name, $admin_email, $admin_phone, $user['id']]);
                        $success_message = 'Admin profile updated successfully';
                        
                        // Refresh user data
                        $_SESSION['user'] = null; // Force reload
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
            }
        } catch (PDOException $e) {
            $error_message = 'Database error occurred';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Get school statistics
$stats = [
    'total_members' => 0,
    'pending_requests' => 0,
    'total_posts' => 0,
    'total_events' => 0
];

if ($db) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND approved = 1 AND role = 'student'");
        $stmt->execute([$user['school_id']]);
        $stats['total_members'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM join_requests WHERE school_id = ? AND status = 'pending'");
        $stmt->execute([$user['school_id']]);
        $stats['pending_requests'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE school_id = ?");
        $stmt->execute([$user['school_id']]);
        $stats['total_posts'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE school_id = ?");
        $stmt->execute([$user['school_id']]);
        $stats['total_events'] = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Settings - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                        <?php if (!empty($school['logo'])): ?>
                            <img src="../uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
                                 alt="School Logo" class="rounded-circle mb-2" 
                                 style="width: 60px; height: 60px; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-building" style="font-size: 2.5rem;"></i>
                        <?php endif; ?>
                        <h6 class="mt-2"><?php echo htmlspecialchars($school['name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($user['name']); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="join-requests.php">
                                <i class="bi bi-person-plus"></i> Join Requests
                                <?php if ($stats['pending_requests'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $stats['pending_requests']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="members.php">
                                <i class="bi bi-people"></i> Members
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="posts.php">
                                <i class="bi bi-megaphone"></i> Posts & Updates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="events.php">
                                <i class="bi bi-calendar-event"></i> Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="opportunities.php">
                                <i class="bi bi-briefcase"></i> Opportunities
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="profile.php">
                                <i class="bi bi-gear"></i> School Settings
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
                    <h1 class="h2">
                        <i class="bi bi-gear"></i> School Settings & Profile
                    </h1>
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

                <!-- School Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="m-0">
                                    <i class="bi bi-building text-primary"></i> School Overview
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <?php if (!empty($school['logo'])): ?>
                                            <img src="../uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
                                                 alt="School Logo" class="rounded mb-3" 
                                                 style="width: 120px; height: 120px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded mb-3 d-flex align-items-center justify-content-center mx-auto" 
                                                 style="width: 120px; height: 120px;">
                                                <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-9">
                                        <h4><?php echo htmlspecialchars($school['name']); ?></h4>
                                        <p class="text-muted mb-2">
                                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($school['location']); ?>
                                        </p>
                                        <?php if (!empty($school['description'])): ?>
                                            <p><?php echo nl2br(htmlspecialchars($school['description'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h5 class="text-primary"><?php echo $stats['total_members']; ?></h5>
                                                    <small class="text-muted">Alumni & Students</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h5 class="text-success"><?php echo $stats['total_posts']; ?></h5>
                                                    <small class="text-muted">Posts</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h5 class="text-info"><?php echo $stats['total_events']; ?></h5>
                                                    <small class="text-muted">Events</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h5 class="text-warning"><?php echo $stats['pending_requests']; ?></h5>
                                                    <small class="text-muted">Pending Requests</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- School Information -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-building-gear"></i> School Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label for="name" class="form-label">School Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($school['name']); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="founded_year" class="form-label">Founded Year</label>
                                            <input type="number" class="form-control" id="founded_year" name="founded_year" 
                                                   value="<?php echo htmlspecialchars($school['founded_year'] ?? ''); ?>" min="1800" max="<?php echo date('Y'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">School Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                  placeholder="Brief description of your school, its history, achievements, etc."><?php echo htmlspecialchars($school['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location *</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo htmlspecialchars($school['location']); ?>" 
                                               placeholder="City, State/Province, Country" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="contact_email" class="form-label">Contact Email *</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($school['contact_email']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($school['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="website" class="form-label">School Website</label>
                                        <input type="url" class="form-control" id="website" name="website" 
                                               value="<?php echo htmlspecialchars($school['website'] ?? ''); ?>" 
                                               placeholder="https://www.example.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="logo" class="form-label">School Logo</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept=".jpg,.jpeg,.png">
                                        <div class="form-text">
                                            Upload a new logo (JPG, PNG, max 2MB). Current logo will be replaced.
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_school" class="btn btn-primary">
                                        <i class="bi bi-check"></i> Update School Information
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Profile & Security -->
                    <div class="col-lg-4">
                        <!-- Admin Profile -->
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-person-gear"></i> Admin Profile
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="admin_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="admin_name" name="admin_name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="admin_email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="admin_phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="admin_phone" name="admin_phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <button type="submit" name="update_admin_profile" class="btn btn-success btn-sm">
                                        <i class="bi bi-check"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
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
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" minlength="6" required>
                                    </div>
                                    
                                    <button type="submit" name="change_password" class="btn btn-warning btn-sm">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-info-circle"></i> Account Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Role:</strong> School Administrator
                                </div>
                                <div class="mb-2">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="mb-2">
                                    <strong>Member Since:</strong> 
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Last Login:</strong> 
                                    <?php echo date('M d, Y H:i', strtotime($user['last_login'] ?? $user['created_at'])); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>School Registered:</strong> 
                                    <?php echo date('M d, Y', strtotime($school['created_at'] ?? 'now')); ?>
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

        // Preview logo before upload
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You can add logo preview functionality here if needed
                };
                reader.readAsDataURL(file);
            }
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    setTimeout(() => {
                        if (alert.parentNode) {
                            bsAlert.close();
                        }
                    }, 5000);
                }
            });
        }, 100);
    </script>
</body>
</html>