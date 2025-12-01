<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = getCurrentUser();

// Redirect if user is already approved or is super admin
if ($user['approved'] || $user['role'] == 'super_admin') {
    switch ($user['role']) {
        case 'super_admin':
            redirect('admin/dashboard.php');
            break;
        case 'school_admin':
            redirect('school-admin/dashboard.php');
            break;
        case 'student':
            redirect('user/dashboard.php');
            break;
        default:
            redirect('index.php');
    }
}

// Get user's join request status
$join_request = null;
if ($user['school_id']) {
    $db = getDB();
    if ($db) {
        try {
            $stmt = $db->prepare("
                SELECT jr.*, s.name as school_name, s.location 
                FROM join_requests jr 
                JOIN schools s ON jr.school_id = s.id 
                WHERE jr.user_id = ? 
                ORDER BY jr.created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user['id']]);
            $join_request = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching join request: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - SchoolLink Africa</title>
    
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
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 5rem;"></i>
                        </div>
                        
                        <h2 class="text-warning mb-3">Account Pending Approval</h2>
                        
                        <p class="lead text-muted mb-4">
                            Your account has been created successfully, but it requires approval before you can access all features.
                        </p>

                        <?php if ($join_request): ?>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-info-circle text-info"></i> Request Details
                                    </h6>
                                    <div class="text-start">
                                        <p class="mb-2">
                                            <strong>School:</strong> <?php echo htmlspecialchars($join_request['school_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($join_request['location']); ?></small>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Status:</strong> 
                                            <?php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$join_request['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusColor; ?>">
                                                <?php echo ucfirst($join_request['status']); ?>
                                            </span>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Submitted:</strong> 
                                            <small class="text-muted"><?php echo timeAgo($join_request['created_at']); ?></small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="bi bi-lightbulb"></i> What's Next?
                            </h6>
                            <ul class="list-unstyled mb-0 text-start">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i> 
                                    Your school administrator will review your request
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i> 
                                    They may verify your details (name, graduation year, etc.)
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success"></i> 
                                    You'll receive an email notification once approved
                                </li>
                                <li class="mb-0">
                                    <i class="bi bi-check-circle text-success"></i> 
                                    After approval, you can access your school's community
                                </li>
                            </ul>
                        </div>

                        <div class="row g-2 mt-4">
                            <div class="col-md-6">
                                <button class="btn btn-outline-primary w-100" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Check Status
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="logout.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Support Card -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-question-circle text-info"></i> Need Help?
                        </h6>
                        <p class="card-text text-muted mb-3">
                            If you've been waiting for more than 48 hours or have questions about your approval status:
                        </p>
                        
                        <div class="d-grid gap-2">
                            <?php if ($join_request && !empty($join_request['school_name'])): ?>
                                <small class="text-muted">Contact your school administrator directly, or:</small>
                            <?php endif; ?>
                            
                            <a href="mailto:support@schoollink.africa" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-envelope"></i> Contact Support
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Account Information Card -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-person-badge text-primary"></i> Your Account Information
                        </h6>
                        <div class="row text-start">
                            <div class="col-sm-4 text-muted">Name:</div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($user['name']); ?></div>
                            
                            <div class="col-sm-4 text-muted mt-2">Email:</div>
                            <div class="col-sm-8 mt-2"><?php echo htmlspecialchars($user['email']); ?></div>
                            
                            <div class="col-sm-4 text-muted mt-2">Role:</div>
                            <div class="col-sm-8 mt-2">
                                <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-refresh notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div class="toast" id="refreshToast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="bi bi-info-circle text-primary me-2"></i>
                <strong class="me-auto">Status Check</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                We'll automatically refresh this page every 2 minutes to check for updates.
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Auto-refresh page every 2 minutes to check for approval status
        setTimeout(function() {
            location.reload();
        }, 120000); // 2 minutes

        // Show refresh toast on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const toast = new bootstrap.Toast(document.getElementById('refreshToast'));
                toast.show();
            }, 2000);
        });

        // Add animation to the hourglass icon
        const hourglass = document.querySelector('.bi-hourglass-split');
        if (hourglass) {
            hourglass.style.animation = 'spin 2s linear infinite';
        }

        // Add custom CSS for animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>