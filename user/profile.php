<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireApproval();

$user = getCurrentUser();
$db = getDB();

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('../logout.php', 'School information not found.', 'error');
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $year_group = trim($_POST['year_group'] ?? '');
        $status = $_POST['status'] ?? '';
        $current_occupation = trim($_POST['current_occupation'] ?? '');
        $linkedin_profile = trim($_POST['linkedin_profile'] ?? '');
        
        // Validation
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required.';
        }
        
        if (empty($year_group)) {
            $errors[] = 'Year group is required.';
        }
        
        if (!in_array($status, ['Current Student', 'Graduated', 'Left'])) {
            $errors[] = 'Please select a valid status.';
        }
        
        if (!empty($linkedin_profile) && !filter_var($linkedin_profile, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid LinkedIn URL.';
        }
        
        // Check if email is already taken by another user
        if (empty($errors) && $db) {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user['id']]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email address is already registered to another user.';
                }
            } catch (PDOException $e) {
                error_log("Email check error: " . $e->getMessage());
                $errors[] = 'Database error occurred. Please try again.';
            }
        }
        
        // Handle profile photo upload
        $profile_photo = $user['profile_photo'] ?? null; // Keep existing photo by default
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/profiles/';
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['profile_photo']['type'], $allowed_types)) {
                $errors[] = 'Profile photo must be a JPEG, PNG, or GIF image.';
            } elseif ($_FILES['profile_photo']['size'] > $max_size) {
                $errors[] = 'Profile photo must be less than 5MB.';
            } else {
                // Create upload directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user['id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    // Delete old profile photo if exists
                    if (!empty($user['profile_photo']) && file_exists($upload_dir . $user['profile_photo'])) {
                        unlink($upload_dir . $user['profile_photo']);
                    }
                    $profile_photo = $new_filename;
                } else {
                    $errors[] = 'Failed to upload profile photo. Please try again.';
                }
            }
        }
        
        // Update user profile if no errors
        if (empty($errors) && $db) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    UPDATE users SET 
                        name = ?, email = ?, phone = ?, bio = ?, year_group = ?, 
                        status = ?, current_occupation = ?, linkedin_profile = ?, 
                        profile_photo = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $name, $email, $phone, $bio, $year_group, 
                    $status, $current_occupation, $linkedin_profile, 
                    $profile_photo, $user['id']
                ]);
                
                if ($result) {
                    $db->commit();
                    
                    // Update session data
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['profile_photo'] = $profile_photo;
                    
                    $success_message = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $user = getCurrentUser();
                } else {
                    $db->rollBack();
                    $errors[] = 'Failed to update profile. Please try again.';
                }
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Profile update error: " . $e->getMessage());
                $errors[] = 'Database error occurred. Please try again.';
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - SchoolLink Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <?php if ($school['logo']): ?>
                    <img src="../uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
                         alt="Logo" class="me-2 rounded-circle" 
                         style="width: 40px; height: 40px; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-building me-2"></i>
                <?php endif; ?>
                SchoolLink Africa
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="directory.php">
                            <i class="bi bi-people"></i> Alumni Directory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="posts.php">
                            <i class="bi bi-megaphone"></i> Posts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="bi bi-calendar-event"></i> Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="opportunities.php">
                            <i class="bi bi-briefcase"></i> Opportunities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-dots"></i> Messages
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                           id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile" class="rounded-circle me-2" 
                                     style="width: 30px; height: 30px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-1"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a></li>
                            <li><a class="dropdown-item active" href="profile.php">
                                <i class="bi bi-person"></i> Edit Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2">
                            <i class="bi bi-person-gear"></i> Edit Profile
                        </h1>
                        <p class="text-muted">Update your personal information and preferences</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Error!</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Photo Section -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-camera"></i> Profile Photo
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile Photo" id="currentPhoto" class="rounded-circle mb-3" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                     id="currentPhoto" style="width: 150px; height: 150px; font-size: 4rem;">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <input type="file" class="form-control" id="profilePhotoInput" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif" onchange="previewPhoto(this)">
                            <small class="text-muted">
                                Max size: 5MB. Formats: JPEG, PNG, GIF
                            </small>
                        </div>
                        
                        <!-- Photo preview -->
                        <div id="photoPreview" class="mt-3" style="display: none;">
                            <img id="previewImage" class="rounded-circle" 
                                 style="width: 100px; height: 100px; object-fit: cover;">
                            <p class="mt-2 text-success">
                                <i class="bi bi-check-circle"></i> New photo ready to upload
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Stats -->
                <div class="card shadow mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-graph-up"></i> Profile Completion
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $completion_items = [
                            'Name' => !empty($user['name']),
                            'Email' => !empty($user['email']),
                            'Phone' => !empty($user['phone']),
                            'Bio' => !empty($user['bio']),
                            'Year Group' => !empty($user['year_group']),
                            'Occupation' => !empty($user['current_occupation']),
                            'LinkedIn' => !empty($user['linkedin_profile']),
                            'Photo' => !empty($user['profile_photo'])
                        ];
                        
                        $completed = count(array_filter($completion_items));
                        $total = count($completion_items);
                        $percentage = round(($completed / $total) * 100);
                        ?>
                        
                        <div class="progress mb-3">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%" 
                                 aria-valuenow="<?php echo $percentage; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                                <?php echo $percentage; ?>%
                            </div>
                        </div>
                        
                        <small class="text-muted">
                            <?php echo $completed; ?> of <?php echo $total; ?> fields completed
                        </small>
                        
                        <?php if ($percentage < 100): ?>
                            <div class="mt-2">
                                <small class="text-info">
                                    <i class="bi bi-info-circle"></i>
                                    Complete your profile to improve networking opportunities!
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Profile Form -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-lines-fill"></i> Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="profile_photo" id="hiddenPhotoInput">
                            
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="year_group" class="form-label">
                                        Year Group <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="year_group" name="year_group" 
                                           value="<?php echo htmlspecialchars($user['year_group'] ?? ''); ?>" 
                                           placeholder="e.g., 2018, 2019-2021, Class of 2020" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">
                                        Student Status <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="Current Student" <?php echo $user['status'] == 'Current Student' ? 'selected' : ''; ?>>
                                            Current Student
                                        </option>
                                        <option value="Graduated" <?php echo $user['status'] == 'Graduated' ? 'selected' : ''; ?>>
                                            Graduated
                                        </option>
                                        <option value="Left" <?php echo $user['status'] == 'Left' ? 'selected' : ''; ?>>
                                            Left School
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="current_occupation" class="form-label">Current Occupation</label>
                                    <input type="text" class="form-control" id="current_occupation" name="current_occupation" 
                                           value="<?php echo htmlspecialchars($user['current_occupation'] ?? ''); ?>" 
                                           placeholder="e.g., Software Engineer, Doctor, Student">
                                </div>
                                
                                <!-- Biography -->
                                <div class="col-12 mb-3">
                                    <label for="bio" class="form-label">Biography</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4" 
                                              placeholder="Tell us about yourself, your interests, achievements, and goals..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    <small class="text-muted">
                                        This will be visible to other alumni and can help with networking.
                                    </small>
                                </div>
                                
                                <!-- Social Links -->
                                <div class="col-12 mb-3">
                                    <label for="linkedin_profile" class="form-label">LinkedIn Profile URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-linkedin"></i>
                                        </span>
                                        <input type="url" class="form-control" id="linkedin_profile" name="linkedin_profile" 
                                               value="<?php echo htmlspecialchars($user['linkedin_profile'] ?? ''); ?>" 
                                               placeholder="https://linkedin.com/in/yourprofile">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="bi bi-arrow-clockwise"></i> Reset Changes
                                </button>
                                
                                <div>
                                    <button type="button" class="btn btn-outline-danger me-2" onclick="changePassword()">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="passwordForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>SchoolLink Africa</h5>
                    <p>Connecting alumni across African schools and building stronger communities.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 SchoolLink Africa. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        function previewPhoto(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('photoPreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('photoPreview').style.display = 'none';
            }
        }

        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                location.reload();
            }
        }

        function changePassword() {
            const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
            modal.show();
        }

        // Handle password change form
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 8) {
                showErrorToast('Password must be at least 8 characters long');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showErrorToast('New passwords do not match');
                return;
            }
            
            // In a real application, you would submit this via AJAX
            showSuccessToast('Password change functionality would be implemented here');
            bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>