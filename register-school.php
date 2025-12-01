<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_name = sanitizeInput($_POST['school_name'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $admin_name = sanitizeInput($_POST['admin_name'] ?? '');
    $admin_email = sanitizeInput($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($school_name) || empty($location) || empty($contact_email) || 
        empty($admin_name) || empty($admin_email) || empty($admin_password)) {
        $error = 'Please fill in all required fields';
    } elseif (!isValidEmail($contact_email) || !isValidEmail($admin_email)) {
        $error = 'Please enter valid email addresses';
    } elseif (strlen($admin_password) < 6) {
        $error = 'Admin password must be at least 6 characters long';
    } elseif ($admin_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $db = getDB();
        if (!$db) {
            $error = 'Database connection failed';
        } else {
            try {
                // Check if school email or admin email already exists
                $stmt = $db->prepare("SELECT id FROM schools WHERE contact_email = ?");
                $stmt->execute([$contact_email]);
                if ($stmt->fetch()) {
                    $error = 'A school with this contact email already exists';
                } else {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$admin_email]);
                    if ($stmt->fetch()) {
                        $error = 'A user with this admin email already exists';
                    } else {
                        // Start transaction
                        $db->beginTransaction();
                        
                        try {
                            // Handle logo upload if provided
                            $logo_filename = null;
                            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                                $upload_result = uploadFile($_FILES['logo'], 'uploads/logos', ['jpg', 'jpeg', 'png']);
                                if ($upload_result['success']) {
                                    $logo_filename = $upload_result['filename'];
                                }
                            }
                            
                            // Insert school
                            $stmt = $db->prepare("
                                INSERT INTO schools (name, location, logo, contact_email, description) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$school_name, $location, $logo_filename, $contact_email, $description]);
                            $school_id = $db->lastInsertId();
                            
                            // Insert admin user
                            $stmt = $db->prepare("
                                INSERT INTO users (name, email, password, role, school_id, approved) 
                                VALUES (?, ?, ?, 'school_admin', ?, FALSE)
                            ");
                            $hashed_password = hashPassword($admin_password);
                            $stmt->execute([$admin_name, $admin_email, $hashed_password, $school_id]);
                            
                            // Commit transaction
                            $db->commit();
                            
                            $success = 'School registration successful! Your school and admin account have been created and are pending approval by our administrators.';
                            
                        } catch (Exception $e) {
                            $db->rollback();
                            error_log("School registration error: " . $e->getMessage());
                            $error = 'Registration failed. Please try again.';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Your School - SchoolLink Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-mortarboard-fill"></i> SchoolLink Africa
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            <i class="bi bi-building text-warning" style="font-size: 4rem;"></i>
                            <h2 class="mt-3 mb-0">Register Your School</h2>
                            <p class="text-muted">Join SchoolLink Africa and connect with your alumni community</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                                <hr>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                    <a href="login.php?type=school_admin" class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> Login as School Admin
                                    </a>
                                    <a href="index.php" class="btn btn-outline-primary">
                                        <i class="bi bi-house"></i> Go Home
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-12">
                                    <h4 class="text-primary mb-3">
                                        <i class="bi bi-building"></i> School Information
                                    </h4>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="school_name" class="form-label">School Name *</label>
                                    <input type="text" class="form-control" id="school_name" name="school_name" 
                                           value="<?php echo htmlspecialchars($school_name ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="logo" class="form-label">School Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo" 
                                           accept=".jpg,.jpeg,.png">
                                    <div class="form-text">Optional: JPG, PNG (max 5MB)</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location *</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           placeholder="City, State/Region, Country"
                                           value="<?php echo htmlspecialchars($location ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="contact_email" class="form-label">School Contact Email *</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                           value="<?php echo htmlspecialchars($contact_email ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="description" class="form-label">School Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Brief description of your school (optional)"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            </div>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-12">
                                    <h4 class="text-primary mb-3">
                                        <i class="bi bi-person-badge"></i> School Administrator Account
                                    </h4>
                                    <p class="text-muted mb-4">
                                        This will be the main administrator account for your school
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_name" class="form-label">Administrator Name *</label>
                                    <input type="text" class="form-control" id="admin_name" name="admin_name" 
                                           value="<?php echo htmlspecialchars($admin_name ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="admin_email" class="form-label">Administrator Email *</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars($admin_email ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                           minlength="6" required>
                                    <div class="form-text">At least 6 characters</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-info-circle text-info"></i> What happens next?
                                        </h6>
                                        <ul class="mb-0 small">
                                            <li>Your school registration will be reviewed by our administrators</li>
                                            <li>We'll verify the school information and contact details</li>
                                            <li>Once approved, you'll receive email confirmation</li>
                                            <li>You can then start managing your school's alumni community</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="bi bi-building"></i> Register School & Create Admin Account
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted">Already registered your school?</p>
                            <a href="login.php?type=school_admin" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right"></i> School Admin Login
                            </a>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                document.getElementById('confirm_password').focus();
            }
        });

        // Real-time password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = this.value;
            
            if (password && confirmPassword) {
                if (password === confirmPassword) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }
        });

        // File upload preview
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) { // 5MB limit
                    alert('File size must be less than 5MB');
                    this.value = '';
                }
            }
        });
    </script>
</body>
</html>