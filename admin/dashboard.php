<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('super_admin');

$user = getCurrentUser();
$db = getDB();

// Get statistics
$stats = [
    'total_schools' => 0,
    'pending_schools' => 0,
    'total_users' => 0,
    'pending_users' => 0
];

if ($db) {
    try {
        // School statistics
        $stmt = $db->prepare("SELECT approved, COUNT(*) as count FROM schools GROUP BY approved");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            if ($row['approved']) {
                $stats['total_schools'] = $row['count'];
            } else {
                $stats['pending_schools'] = $row['count'];
            }
        }
        
        // User statistics
        $stmt = $db->prepare("SELECT approved, COUNT(*) as count FROM users WHERE role = 'student' GROUP BY approved");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            if ($row['approved']) {
                $stats['total_users'] = $row['count'];
            } else {
                $stats['pending_users'] = $row['count'];
            }
        }
        
    } catch (PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
    }
}

// Handle AJAX requests for school approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] == 'approve_school' || $_POST['action'] == 'reject_school') {
        $school_id = intval($_POST['school_id'] ?? 0);
        $approved = $_POST['action'] == 'approve_school' ? 1 : 0;
        
        try {
            if ($_POST['action'] == 'reject_school') {
                // Delete rejected school
                $stmt = $db->prepare("DELETE FROM schools WHERE id = ?");
                $stmt->execute([$school_id]);
                $response['message'] = 'School rejected and removed';
            } else {
                // Approve school
                $stmt = $db->prepare("UPDATE schools SET approved = 1 WHERE id = ?");
                $stmt->execute([$school_id]);
                $response['message'] = 'School approved successfully';
            }
            $response['success'] = true;
        } catch (PDOException $e) {
            $response['message'] = 'Database error occurred';
            error_log("School approval error: " . $e->getMessage());
        }
    }
    
    sendJSONResponse($response);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - SchoolLink Africa</title>
    
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
                            <a class="nav-link active text-white" href="dashboard.php">
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
                        
                        <!-- E-commerce Section -->
                        <li class="nav-item mt-3">
                            <h6 class="sidebar-heading text-muted px-3 mt-4 mb-1 text-uppercase">
                                <span>E-Commerce Management</span>
                            </h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="../category.php">
                                <i class="bi bi-grid-3x3-gap"></i> Manage Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="../brand.php">
                                <i class="bi bi-tags"></i> Manage Brands
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="../product.php">
                                <i class="bi bi-box"></i> Manage Products
                            </a>
                        </li>
                        
                        <li class="nav-item mt-3">
                            <a class="nav-link text-white-50" href="settings.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <?php echo getFlashMessage(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 border-start border-primary border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Approved Schools
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_schools']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-building text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 border-start border-warning border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Schools
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pending_schools']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 border-start border-success border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_users']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 border-start border-info border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Pending Approvals
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pending_users']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-check text-info" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Schools Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-building"></i> Schools Pending Approval
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get pending schools
                                $pending_schools = [];
                                if ($db) {
                                    try {
                                        $stmt = $db->prepare("SELECT * FROM schools WHERE approved = 0 ORDER BY created_at DESC");
                                        $stmt->execute();
                                        $pending_schools = $stmt->fetchAll();
                                    } catch (PDOException $e) {
                                        error_log("Error fetching pending schools: " . $e->getMessage());
                                    }
                                }
                                ?>
                                
                                <?php if (empty($pending_schools)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No schools pending approval</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>School Name</th>
                                                    <th>Location</th>
                                                    <th>Contact Email</th>
                                                    <th>Registered</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_schools as $school): ?>
                                                    <tr id="school-<?php echo $school['id']; ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($school['name']); ?></strong>
                                                            <?php if ($school['description']): ?>
                                                                <br><small class="text-muted">
                                                                    <?php echo truncateText(htmlspecialchars($school['description']), 100); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($school['location']); ?></td>
                                                        <td>
                                                            <a href="mailto:<?php echo htmlspecialchars($school['contact_email']); ?>">
                                                                <?php echo htmlspecialchars($school['contact_email']); ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php echo timeAgo($school['created_at']); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-sm btn-success" 
                                                                        onclick="approveSchool(<?php echo $school['id']; ?>)">
                                                                    <i class="bi bi-check"></i> Approve
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger" 
                                                                        onclick="rejectSchool(<?php echo $school['id']; ?>)">
                                                                    <i class="bi bi-x"></i> Reject
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="schools.php" class="btn btn-outline-primary">
                                        <i class="bi bi-building"></i> Manage All Schools
                                    </a>
                                    <a href="users.php" class="btn btn-outline-success">
                                        <i class="bi bi-people"></i> Manage Users
                                    </a>
                                    <a href="reports.php" class="btn btn-outline-info">
                                        <i class="bi bi-graph-up"></i> View Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">System Info</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Platform:</strong> SchoolLink Africa</p>
                                <p><strong>Version:</strong> 1.0.0</p>
                                <p><strong>Last Login:</strong> <?php echo date('M d, Y H:i'); ?></p>
                                <p><strong>Role:</strong> Super Administrator</p>
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
        function approveSchool(schoolId) {
            if (confirm('Are you sure you want to approve this school?')) {
                performSchoolAction('approve_school', schoolId);
            }
        }

        function rejectSchool(schoolId) {
            if (confirm('Are you sure you want to reject this school? This action cannot be undone.')) {
                performSchoolAction('reject_school', schoolId);
            }
        }

        function performSchoolAction(action, schoolId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('school_id', schoolId);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the school row
                    const row = document.getElementById('school-' + schoolId);
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                    
                    // Show success message
                    showAlert('success', data.message);
                    
                    // Refresh page after a short delay to update stats
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            });
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const main = document.querySelector('main');
            main.insertBefore(alertDiv, main.querySelector('.row'));
        }
    </script>
</body>
</html>