<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 'request') {
        // Step 1: Request password reset
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address';
        } else {
            $db = getDB();
            if ($db) {
                try {
                    // Check if user exists
                    $stmt = $db->prepare("SELECT id, name, role FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Generate reset token
                        $reset_token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        
                        // Store token in database (you may want to create a password_resets table)
                        // For now, we'll store it in the users table temporarily
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET reset_token = ?, reset_token_expires = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$reset_token, $expires_at, $user['id']]);
                        
                        // In a real application, you would send an email here
                        // For demonstration, we'll show the reset link
                        $reset_link = $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?step=reset&token=" . $reset_token;
                        
                        $success = "Password reset instructions have been sent to your email address. 
                                   <br><br><strong>Demo Link:</strong> 
                                   <a href='forgot-password.php?step=reset&token=$reset_token' class='btn btn-sm btn-primary'>
                                   Reset Password</a>";
                    } else {
                        // Don't reveal if email exists or not for security
                        $success = "If an account with that email exists, you will receive password reset instructions.";
                    }
                } catch (PDOException $e) {
                    error_log("Password reset error: " . $e->getMessage());
                    $error = 'An error occurred. Please try again.';
                }
            } else {
                $error = 'Database connection failed';
            }
        }
        
    } elseif ($step == 'reset' && !empty($token)) {
        // Step 2: Reset password with token
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            $db = getDB();
            if ($db) {
                try {
                    // Verify token
                    $stmt = $db->prepare("
                        SELECT id, name, email, role 
                        FROM users 
                        WHERE reset_token = ? AND reset_token_expires > NOW()
                    ");
                    $stmt->execute([$token]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        // Update password and clear reset token
                        $hashed_password = hashPassword($new_password);
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET password = ?, reset_token = NULL, reset_token_expires = NULL 
                            WHERE id = ?
                        ");
                        $stmt->execute([$hashed_password, $user['id']]);
                        
                        $success = 'Your password has been reset successfully. You can now login with your new password.';
                        $step = 'complete';
                    } else {
                        $error = 'Invalid or expired reset token. Please request a new password reset.';
                    }
                } catch (PDOException $e) {
                    error_log("Password reset error: " . $e->getMessage());
                    $error = 'An error occurred. Please try again.';
                }
            } else {
                $error = 'Database connection failed';
            }
        }
    }
}

// If accessing reset step, verify token first
if ($step == 'reset' && !empty($token) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    $db = getDB();
    if ($db) {
        try {
            $stmt = $db->prepare("
                SELECT id, name, email 
                FROM users 
                WHERE reset_token = ? AND reset_token_expires > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Invalid or expired reset token. Please request a new password reset.';
                $step = 'request';
            }
        } catch (PDOException $e) {
            error_log("Token verification error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
            $step = 'request';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        echo $step == 'reset' ? 'Reset Password' : 
             ($step == 'complete' ? 'Password Reset Complete' : 'Forgot Password'); 
        ?> - SchoolLink Africa
    </title>
    
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
                        
                        <?php if ($step == 'request'): ?>
                            <!-- Step 1: Request Password Reset -->
                            <div class="text-center mb-4">
                                <i class="bi bi-key text-warning" style="font-size: 4rem;"></i>
                                <h2 class="mt-3 mb-0">Forgot Password</h2>
                                <p class="text-muted">Enter your email to reset your password</p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                                </div>
                            <?php else: ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="Enter your registered email address" required>
                                    </div>
                                    <div class="form-text">
                                        We'll send password reset instructions to this email address.
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="bi bi-send"></i> Send Reset Instructions
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <p class="text-muted">Remember your password?</p>
                                <a href="login.php" class="btn btn-outline-primary">
                                    <i class="bi bi-box-arrow-in-right"></i> Back to Login
                                </a>
                            </div>

                            <?php endif; ?>

                        <?php elseif ($step == 'reset'): ?>
                            <!-- Step 2: Reset Password Form -->
                            <div class="text-center mb-4">
                                <i class="bi bi-shield-lock text-success" style="font-size: 4rem;"></i>
                                <h2 class="mt-3 mb-0">Reset Password</h2>
                                <p class="text-muted">Enter your new password</p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" minlength="6" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Password must be at least 6 characters long</div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle"></i> Reset Password
                                    </button>
                                </div>
                            </form>

                        <?php elseif ($step == 'complete'): ?>
                            <!-- Step 3: Success Message -->
                            <div class="text-center mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                                <h2 class="mt-3 mb-0 text-success">Password Reset Complete</h2>
                                <p class="text-muted">Your password has been successfully updated</p>
                            </div>

                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="login.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> Login Now
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-house"></i> Go to Homepage
                                </a>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-question-circle text-info"></i> Need Help?
                        </h6>
                        <p class="card-text text-muted mb-3">
                            If you're having trouble resetting your password or don't receive the reset email:
                        </p>
                        <div class="d-grid gap-2 d-md-flex">
                            <a href="mailto:support@schoollink.africa" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-envelope"></i> Contact Support
                            </a>
                            <a href="login.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const password = document.getElementById('new_password');
                const icon = this.querySelector('i');
                
                if (password.type === 'password') {
                    password.type = 'text';
                    icon.className = 'bi bi-eye-slash';
                } else {
                    password.type = 'password';
                    icon.className = 'bi bi-eye';
                }
            });
        }

        // Real-time password confirmation validation
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                const password = document.getElementById('new_password').value;
                const confirmPasswordValue = this.value;
                
                if (password && confirmPasswordValue) {
                    if (password === confirmPasswordValue) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                }
            });
        }
    </script>
</body>
</html>