<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$schools = getApprovedSchools();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $school_id = sanitizeInput($_POST['school_id'] ?? '');
    $year_group = sanitizeInput($_POST['year_group'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? '');
    $student_id = sanitizeInput($_POST['student_id'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($school_id) || empty($year_group) || empty($status)) {
        $error = 'Please fill in all required fields';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Register user
        $result = registerUser([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'student',
            'school_id' => $school_id,
            'year_group' => $year_group,
            'status' => $status,
            'student_id' => $student_id ?: null
        ]);
        
        if ($result['success']) {
            // Create join request
            $db = getDB();
            if ($db) {
                try {
                    $stmt = $db->prepare("INSERT INTO join_requests (user_id, school_id, message) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $result['user_id'], 
                        $school_id,
                        "Registration request for $name (Year Group: $year_group, Status: $status)"
                    ]);
                    
                    $success = 'Registration successful! Your request has been sent to the school admin for approval.';
                } catch (PDOException $e) {
                    error_log("Join request error: " . $e->getMessage());
                    $success = 'Registration successful! Please contact your school admin for approval.';
                }
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as Student/Alumni - SchoolLink Africa</title>
    
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
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="bi bi-mortarboard text-success" style="font-size: 4rem;"></i>
                            <h2 class="mt-3 mb-0">Register as Student/Alumni</h2>
                            <p class="text-muted">Join your school's community on SchoolLink Africa</p>
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
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> Login Now
                                    </a>
                                    <a href="index.php" class="btn btn-outline-primary">
                                        <i class="bi bi-house"></i> Go Home
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="6" required>
                                    <div class="form-text">At least 6 characters</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="school_id" class="form-label">Select Your School *</label>
                                <select class="form-select" id="school_id" name="school_id" required>
                                    <option value="">Choose your school...</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo $school['id']; ?>" 
                                                <?php echo (isset($school_id) && $school_id == $school['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($school['name'] . ' - ' . $school['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Don't see your school? <a href="register-school.php">Register it here</a>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="year_group" class="form-label">Year Group *</label>
                                    <input type="text" class="form-control" id="year_group" name="year_group" 
                                           placeholder="e.g., 2015, 2020-2022" 
                                           value="<?php echo htmlspecialchars($year_group ?? ''); ?>" required>
                                    <div class="form-text">Graduation year or class years</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select status...</option>
                                        <option value="Graduated" <?php echo (isset($status) && $status == 'Graduated') ? 'selected' : ''; ?>>
                                            Graduated
                                        </option>
                                        <option value="Left" <?php echo (isset($status) && $status == 'Left') ? 'selected' : ''; ?>>
                                            Left School
                                        </option>
                                        <option value="Current Student" <?php echo (isset($status) && $status == 'Current Student') ? 'selected' : ''; ?>>
                                            Current Student
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="student_id" class="form-label">Student ID (Optional)</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" 
                                       placeholder="Your school student ID if available"
                                       value="<?php echo htmlspecialchars($student_id ?? ''); ?>">
                                <div class="form-text">This helps with verification</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-person-plus"></i> Register & Request Access
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Sign In
                            </a>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$success): ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-info-circle text-info"></i> What happens after registration?
                        </h6>
                        <ul class="mb-0 text-muted small">
                            <li>Your registration request will be sent to your school's admin</li>
                            <li>The school admin will verify your details and approve your request</li>
                            <li>Once approved, you can access your school's community and features</li>
                            <li>You'll receive email notifications about the approval status</li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                document.getElementById('confirm_password').focus();
            }
        });

        // Real-time password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
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
    </script>
</body>
</html>