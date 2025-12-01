<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['role']) {
        case 'super_admin':
            header('Location: admin/dashboard.php');
            break;
        case 'school_admin':
            header('Location: school-admin/dashboard.php');
            break;
        case 'student':
            header('Location: user/dashboard.php');
            break;
    }
    exit();
}

$login_type = $_GET['type'] ?? 'student';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address';
    } else {
        $user = authenticateUser($email, $password);
        
        if ($user) {
            // Check if user role matches login type
            if ($login_type == 'super_admin' && $user['role'] != 'super_admin') {
                $error = 'Access denied. Super admin credentials required.';
            } elseif ($login_type == 'school_admin' && $user['role'] != 'school_admin') {
                $error = 'Access denied. School admin credentials required.';
            } elseif ($login_type == 'student' && $user['role'] == 'super_admin') {
                $error = 'Please use the appropriate admin login.';
            } else {
                loginUser($user);
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'super_admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'school_admin':
                        header('Location: school-admin/dashboard.php');
                        break;
                    case 'student':
                        if ($user['approved']) {
                            header('Location: user/dashboard.php');
                        } else {
                            header('Location: pending-approval.php');
                        }
                        break;
                }
                exit();
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$page_titles = [
    'super_admin' => 'Super Admin Login',
    'school_admin' => 'School Admin Login',
    'student' => 'Student/Alumni Login'
];

$page_title = $page_titles[$login_type] ?? 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SchoolLink Africa</title>
    
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

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <?php
                            $icons = [
                                'super_admin' => 'shield-lock-fill text-danger',
                                'school_admin' => 'building text-primary',
                                'student' => 'mortarboard text-success'
                            ];
                            $icon = $icons[$login_type] ?? 'person-circle';
                            ?>
                            <i class="bi bi-<?php echo $icon; ?>" style="font-size: 4rem;"></i>
                            <h2 class="mt-3 mb-0"><?php echo $page_title; ?></h2>
                            <p class="text-muted">Enter your credentials to continue</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['message'])): ?>
                            <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_GET['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <a href="forgot-password.php" class="text-decoration-none">
                                Forgot your password?
                            </a>
                        </div>

                        <?php if ($login_type == 'student'): ?>
                            <hr class="my-4">
                            <div class="text-center">
                                <p class="text-muted mb-2">Don't have an account?</p>
                                <a href="register.php" class="btn btn-outline-success">
                                    <i class="bi bi-person-plus"></i> Register as Student/Alumni
                                </a>
                            </div>
                        <?php elseif ($login_type == 'school_admin'): ?>
                            <hr class="my-4">
                            <div class="text-center">
                                <p class="text-muted mb-2">Want to register your school?</p>
                                <a href="register-school.php" class="btn btn-outline-warning">
                                    <i class="bi bi-building"></i> Register School
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Login Type Switcher -->
                        <div class="mt-4">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100" type="button" 
                                        data-bs-toggle="dropdown">
                                    Switch Login Type
                                </button>
                                <ul class="dropdown-menu w-100">
                                    <li>
                                        <a class="dropdown-item <?php echo $login_type == 'student' ? 'active' : ''; ?>" 
                                           href="login.php?type=student">
                                            <i class="bi bi-mortarboard"></i> Student/Alumni
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $login_type == 'school_admin' ? 'active' : ''; ?>" 
                                           href="login.php?type=school_admin">
                                            <i class="bi bi-building"></i> School Admin
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $login_type == 'super_admin' ? 'active' : ''; ?>" 
                                           href="login.php?type=super_admin">
                                            <i class="bi bi-shield-lock"></i> Super Admin
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>