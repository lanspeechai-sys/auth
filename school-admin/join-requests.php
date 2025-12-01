<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('school_admin');

$user = getCurrentUser();
$db = getDB();

// Check if user is approved
if (!$user['approved']) {
    redirect('../pending-approval.php');
}

// Handle AJAX requests for join request approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (($_POST['action'] == 'approve_request' || $_POST['action'] == 'reject_request') && $db) {
        $request_id = intval($_POST['request_id'] ?? 0);
        $action = $_POST['action'];
        
        try {
            // Get the join request details
            $stmt = $db->prepare("
                SELECT jr.*, u.id as user_id 
                FROM join_requests jr 
                JOIN users u ON jr.user_id = u.id 
                WHERE jr.id = ? AND jr.school_id = ? AND jr.status = 'pending'
            ");
            $stmt->execute([$request_id, $user['school_id']]);
            $join_request = $stmt->fetch();
            
            if ($join_request) {
                $db->beginTransaction();
                
                if ($action == 'approve_request') {
                    // Approve the request
                    $stmt = $db->prepare("UPDATE join_requests SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$request_id]);
                    
                    // Approve the user
                    $stmt = $db->prepare("UPDATE users SET approved = 1 WHERE id = ?");
                    $stmt->execute([$join_request['user_id']]);
                    
                    $response['message'] = 'Join request approved successfully';
                } else {
                    // Reject the request
                    $stmt = $db->prepare("UPDATE join_requests SET status = 'rejected' WHERE id = ?");
                    $stmt->execute([$request_id]);
                    
                    $response['message'] = 'Join request rejected';
                }
                
                $db->commit();
                $response['success'] = true;
            } else {
                $response['message'] = 'Join request not found or already processed';
            }
            
        } catch (PDOException $e) {
            $db->rollback();
            $response['message'] = 'Database error occurred';
            error_log("Join request processing error: " . $e->getMessage());
        }
    } else {
        $response['message'] = 'Invalid action';
    }
    
    sendJSONResponse($response);
}

// Get school information
$school = getSchoolInfo($user['school_id']);

// Get all join requests for this school
$join_requests = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT jr.*, u.name, u.email, u.year_group, u.status as user_status, u.student_id
            FROM join_requests jr 
            JOIN users u ON jr.user_id = u.id 
            WHERE jr.school_id = ? 
            ORDER BY 
                CASE jr.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'approved' THEN 2 
                    WHEN 'rejected' THEN 3 
                END, 
                jr.created_at DESC
        ");
        $stmt->execute([$user['school_id']]);
        $join_requests = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching join requests: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Join Requests - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                        <?php if ($school['logo']): ?>
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
                            <a class="nav-link active text-white" href="join-requests.php">
                                <i class="bi bi-person-plus"></i> Join Requests
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
                            <a class="nav-link text-white-50" href="profile.php">
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
                        <i class="bi bi-person-plus"></i> Join Requests Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <?php echo getFlashMessage(); ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <?php
                    $pending_count = count(array_filter($join_requests, function($r) { return $r['status'] == 'pending'; }));
                    $approved_count = count(array_filter($join_requests, function($r) { return $r['status'] == 'approved'; }));
                    $rejected_count = count(array_filter($join_requests, function($r) { return $r['status'] == 'rejected'; }));
                    ?>
                    
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h5 class="card-title text-warning"><?php echo $pending_count; ?></h5>
                                <p class="card-text">Pending Requests</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h5 class="card-title text-success"><?php echo $approved_count; ?></h5>
                                <p class="card-text">Approved</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h5 class="card-title text-danger"><?php echo $rejected_count; ?></h5>
                                <p class="card-text">Rejected</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary"><?php echo count($join_requests); ?></h5>
                                <p class="card-text">Total Requests</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Join Requests Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">All Join Requests</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($join_requests)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-person-plus" style="font-size: 4rem;"></i>
                                <p class="mt-3">No join requests found</p>
                                <p class="text-muted">Students and alumni will appear here when they request to join your school.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Year Group</th>
                                            <th>Status</th>
                                            <th>Student ID</th>
                                            <th>Requested</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($join_requests as $request): ?>
                                            <tr id="request-<?php echo $request['id']; ?>" class="request-row">
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                                                        <br>
                                                        <small class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($request['user_status']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($request['year_group']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'pending' => 'warning',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    $statusIcons = [
                                                        'pending' => 'hourglass-split',
                                                        'approved' => 'check-circle',
                                                        'rejected' => 'x-circle'
                                                    ];
                                                    $color = $statusColors[$request['status']] ?? 'secondary';
                                                    $icon = $statusIcons[$request['status']] ?? 'question-circle';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?> status-badge">
                                                        <i class="bi bi-<?php echo $icon; ?>"></i>
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($request['student_id']): ?>
                                                        <code><?php echo htmlspecialchars($request['student_id']); ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($request['created_at']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <div class="btn-group action-buttons" role="group">
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    onclick="processRequest(<?php echo $request['id']; ?>, 'approve')">
                                                                <i class="bi bi-check"></i> Approve
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="processRequest(<?php echo $request['id']; ?>, 'reject')">
                                                                <i class="bi bi-x"></i> Reject
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php echo ucfirst($request['status']); ?> 
                                                            <?php echo formatDate($request['updated_at']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        function processRequest(requestId, action) {
            const actionText = action === 'approve' ? 'approve' : 'reject';
            const confirmText = `Are you sure you want to ${actionText} this join request?`;
            
            if (confirm(confirmText)) {
                const row = document.getElementById('request-' + requestId);
                const actionButtons = row.querySelector('.action-buttons');
                const statusBadge = row.querySelector('.status-badge');
                
                // Show loading state
                if (actionButtons) {
                    actionButtons.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div>';
                }
                
                const formData = new FormData();
                formData.append('action', action + '_request');
                formData.append('request_id', requestId);

                fetch('join-requests.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the UI
                        if (action === 'approve') {
                            statusBadge.className = 'badge bg-success status-badge';
                            statusBadge.innerHTML = '<i class="bi bi-check-circle"></i> Approved';
                        } else {
                            statusBadge.className = 'badge bg-danger status-badge';
                            statusBadge.innerHTML = '<i class="bi bi-x-circle"></i> Rejected';
                        }
                        
                        // Replace action buttons with status text
                        actionButtons.innerHTML = `
                            <small class="text-muted">
                                ${action === 'approve' ? 'Approved' : 'Rejected'} just now
                            </small>
                        `;
                        
                        // Show success message
                        showSuccessToast(data.message);
                        
                        // Animate the row
                        row.style.transition = 'background-color 0.5s';
                        row.style.backgroundColor = action === 'approve' ? '#d1edff' : '#ffe6e6';
                        setTimeout(() => {
                            row.style.backgroundColor = '';
                        }, 2000);
                        
                    } else {
                        showErrorToast(data.message);
                        
                        // Restore action buttons
                        if (actionButtons) {
                            actionButtons.innerHTML = `
                                <button type="button" class="btn btn-sm btn-success" 
                                        onclick="processRequest(${requestId}, 'approve')">
                                    <i class="bi bi-check"></i> Approve
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="processRequest(${requestId}, 'reject')">
                                    <i class="bi bi-x"></i> Reject
                                </button>
                            `;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorToast('An error occurred. Please try again.');
                    
                    // Restore action buttons
                    if (actionButtons) {
                        actionButtons.innerHTML = `
                            <button type="button" class="btn btn-sm btn-success" 
                                    onclick="processRequest(${requestId}, 'approve')">
                                <i class="bi bi-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="processRequest(${requestId}, 'reject')">
                                <i class="bi bi-x"></i> Reject
                            </button>
                        `;
                    }
                });
            }
        }
    </script>
</body>
</html>