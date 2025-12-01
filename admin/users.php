<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('super_admin');

$user = getCurrentUser();
$db = getDB();

// Handle AJAX requests for user management
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    try {
        switch ($_POST['action']) {
            case 'approve_user':
                $stmt = $db->prepare("UPDATE users SET approved = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $response['message'] = 'User approved successfully';
                $response['success'] = true;
                break;
                
            case 'suspend_user':
                $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$user_id]);
                $response['message'] = 'User suspended successfully';
                $response['success'] = true;
                break;
                
            case 'activate_user':
                $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $stmt->execute([$user_id]);
                $response['message'] = 'User activated successfully';
                $response['success'] = true;
                break;
                
            case 'delete_user':
                $db->beginTransaction();
                
                // Delete user-related data
                $stmt = $db->prepare("DELETE FROM posts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM post_likes WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM post_comments WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM event_rsvps WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM opportunity_interests WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $stmt = $db->prepare("DELETE FROM connections WHERE user_id = ? OR connected_user_id = ?");
                $stmt->execute([$user_id, $user_id]);
                
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $db->commit();
                $response['message'] = 'User and all associated data deleted successfully';
                $response['success'] = true;
                break;
                
            case 'add_user':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'student';
                $school_id = intval($_POST['school_id'] ?? 0);
                $year_group = trim($_POST['year_group'] ?? '');
                $status = $_POST['status'] ?? 'Current Student';
                
                if (empty($name) || empty($email) || empty($school_id)) {
                    $response['message'] = 'Name, email, and school are required';
                    break;
                }
                
                // Check if user already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $response['message'] = 'A user with this email already exists';
                    break;
                }
                
                // Create default password
                $default_password = 'SchoolLink2024';
                $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO users (name, email, password, role, school_id, year_group, status, approved) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$name, $email, $hashed_password, $role, $school_id, $year_group, $status]);
                
                $response['message'] = "User added successfully. Default password: {$default_password}";
                $response['success'] = true;
                break;
                
            case 'get_user_details':
                $stmt = $db->prepare("
                    SELECT u.*, s.name as school_name,
                           (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as posts_count,
                           (SELECT COUNT(*) FROM connections WHERE user_id = u.id OR connected_user_id = u.id) as connections_count
                    FROM users u
                    LEFT JOIN schools s ON u.school_id = s.id
                    WHERE u.id = ?
                ");
                $stmt->execute([$user_id]);
                $user_details = $stmt->fetch();
                
                if ($user_details) {
                    $response['success'] = true;
                    $response['user'] = $user_details;
                } else {
                    $response['message'] = 'User not found';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error occurred';
        error_log("User management error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get filter and search parameters
$filter = $_GET['filter'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';
$school_filter = $_GET['school'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query based on filters
$where_conditions = [];
$params = [];

if ($filter === 'pending') {
    $where_conditions[] = "u.approved = 0";
} elseif ($filter === 'approved') {
    $where_conditions[] = "u.approved = 1";
} elseif ($filter === 'suspended') {
    $where_conditions[] = "u.status = 'suspended'";
}

if ($role_filter !== 'all') {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($school_filter !== 'all') {
    $where_conditions[] = "u.school_id = ?";
    $params[] = intval($school_filter);
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with pagination
$users = [];
$total_users = 0;
$schools_list = [];

if ($db) {
    try {
        // Get schools for filter dropdown
        $stmt = $db->prepare("SELECT id, name FROM schools WHERE approved = 1 ORDER BY name");
        $stmt->execute();
        $schools_list = $stmt->fetchAll();
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM users u LEFT JOIN schools s ON u.school_id = s.id {$where_clause}";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_users = $stmt->fetchColumn();
        
        // Get users for current page
        $query = "SELECT u.*, s.name as school_name
                  FROM users u 
                  LEFT JOIN schools s ON u.school_id = s.id 
                  {$where_clause}
                  ORDER BY u.created_at DESC 
                  LIMIT {$per_page} OFFSET {$offset}";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
    }
}

$total_pages = ceil($total_users / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - SchoolLink Africa</title>
    
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
                            <a class="nav-link active text-white" href="users.php">
                                <i class="bi bi-people"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="reports.php">
                                <i class="bi bi-graph-up"></i> Reports
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
                    <h1 class="h2">Manage Users</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" 
                                    data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="bi bi-person-plus"></i> Add User
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-12">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="suspended" <?php echo $filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students/Alumni</option>
                                    <option value="school_admin" <?php echo $role_filter === 'school_admin' ? 'selected' : ''; ?>>School Admins</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="school" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="all" <?php echo $school_filter === 'all' ? 'selected' : ''; ?>>All Schools</option>
                                    <?php foreach ($schools_list as $school): ?>
                                        <option value="<?php echo $school['id']; ?>" 
                                                <?php echo $school_filter == $school['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($school['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <?php if (!empty($search) || $filter !== 'all' || $role_filter !== 'all' || $school_filter !== 'all'): ?>
                                    <a href="users.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12 text-end">
                        <span class="text-muted">
                            Showing <?php echo count($users); ?> of <?php echo $total_users; ?> users
                        </span>
                    </div>
                </div>

                <?php echo getFlashMessage(); ?>

                <!-- Users Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-people"></i> Users List
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-people" style="font-size: 3rem;"></i>
                                <p class="mt-3">No users found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User Details</th>
                                            <th>School</th>
                                            <th>Role</th>
                                            <th>Contact</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user_item): ?>
                                            <tr id="user-<?php echo $user_item['id']; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($user_item['profile_photo']): ?>
                                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($user_item['profile_photo']); ?>" 
                                                                 alt="Profile Photo" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="bi bi-person text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user_item['name']); ?></strong>
                                                            <?php if ($user_item['graduation_year']): ?>
                                                                <br><small class="text-muted">Class of <?php echo htmlspecialchars($user_item['graduation_year']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($user_item['school_name']): ?>
                                                        <?php echo htmlspecialchars($user_item['school_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No School</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $role_badges = [
                                                        'student' => 'bg-info',
                                                        'school_admin' => 'bg-warning',
                                                        'super_admin' => 'bg-danger'
                                                    ];
                                                    $role_names = [
                                                        'student' => 'Student/Alumni',
                                                        'school_admin' => 'School Admin',
                                                        'super_admin' => 'Super Admin'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $role_badges[$user_item['role']] ?? 'bg-secondary'; ?>">
                                                        <?php echo $role_names[$user_item['role']] ?? ucfirst($user_item['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <a href="mailto:<?php echo htmlspecialchars($user_item['email']); ?>">
                                                            <?php echo htmlspecialchars($user_item['email']); ?>
                                                        </a>
                                                    </div>
                                                    <?php if ($user_item['phone']): ?>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user_item['phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$user_item['approved']): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($user_item['status'] === 'suspended'): ?>
                                                        <span class="badge bg-danger">Suspended</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($user_item['created_at']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($user_item['role'] !== 'super_admin'): ?>
                                                        <div class="btn-group" role="group">
                                                            <?php if (!$user_item['approved']): ?>
                                                                <button type="button" class="btn btn-sm btn-success" 
                                                                        onclick="approveUser(<?php echo $user_item['id']; ?>)"
                                                                        title="Approve User">
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <?php if ($user_item['status'] !== 'suspended'): ?>
                                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                                            onclick="suspendUser(<?php echo $user_item['id']; ?>)"
                                                                            title="Suspend User">
                                                                        <i class="bi bi-pause-circle"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-sm btn-success" 
                                                                            onclick="activateUser(<?php echo $user_item['id']; ?>)"
                                                                            title="Activate User">
                                                                        <i class="bi bi-play-circle"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-sm btn-info" 
                                                                    onclick="viewUser(<?php echo $user_item['id']; ?>)"
                                                                    title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="deleteUser(<?php echo $user_item['id']; ?>)"
                                                                    title="Delete User">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Protected</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Users pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&filter=<?php echo urlencode($filter); ?>&role=<?php echo urlencode($role_filter); ?>&school=<?php echo urlencode($school_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&role=<?php echo urlencode($role_filter); ?>&school=<?php echo urlencode($school_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>&filter=<?php echo urlencode($filter); ?>&role=<?php echo urlencode($role_filter); ?>&school=<?php echo urlencode($school_filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function approveUser(userId) {
            if (confirm('Are you sure you want to approve this user?')) {
                performUserAction('approve_user', userId);
            }
        }

        function suspendUser(userId) {
            if (confirm('Are you sure you want to suspend this user? They will not be able to access their account.')) {
                performUserAction('suspend_user', userId);
            }
        }

        function activateUser(userId) {
            if (confirm('Are you sure you want to activate this user?')) {
                performUserAction('activate_user', userId);
            }
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                performUserAction('delete_user', userId);
            }
        }

        function performUserAction(action, userId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('user_id', userId);

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    
                    if (action === 'delete_user') {
                        // Remove the user row
                        const row = document.getElementById('user-' + userId);
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                    } else {
                        // Reload page to update status
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            });
        }

        function viewUser(userId) {
            const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
            document.getElementById('userDetailsContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            modal.show();
            
            // Fetch user details
            const formData = new FormData();
            formData.append('action', 'get_user_details');
            formData.append('user_id', userId);

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUserDetails(data.user);
                } else {
                    document.getElementById('userDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('userDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Failed to load user details.
                    </div>
                `;
            });
        }

        function displayUserDetails(user) {
            const statusBadge = user.approved === '1' 
                ? (user.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Suspended</span>')
                : '<span class="badge bg-warning">Pending Approval</span>';
                
            const roleBadges = {
                'student': '<span class="badge bg-info">Student/Alumni</span>',
                'school_admin': '<span class="badge bg-warning">School Admin</span>',
                'super_admin': '<span class="badge bg-danger">Super Admin</span>'
            };
                
            document.getElementById('userDetailsContent').innerHTML = `
                <div class="row">
                    <div class="col-md-4 text-center">
                        ${user.profile_photo 
                            ? `<img src="../uploads/profiles/${user.profile_photo}" alt="Profile Photo" class="img-fluid rounded-circle mb-3" style="max-width: 150px;">`
                            : '<div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 150px; height: 150px;"><i class="bi bi-person text-white" style="font-size: 3rem;"></i></div>'
                        }
                        <h5>${user.name}</h5>
                        ${roleBadges[user.role] || '<span class="badge bg-secondary">' + user.role + '</span>'}
                        <br>
                        ${statusBadge}
                    </div>
                    <div class="col-md-8">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">Email:</th>
                                <td><a href="mailto:${user.email}">${user.email}</a></td>
                            </tr>
                            ${user.phone ? `
                            <tr>
                                <th>Phone:</th>
                                <td>${user.phone}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th>School:</th>
                                <td>${user.school_name || 'No School'}</td>
                            </tr>
                            ${user.year_group ? `
                            <tr>
                                <th>Year Group:</th>
                                <td>${user.year_group}</td>
                            </tr>
                            ` : ''}
                            ${user.graduation_year ? `
                            <tr>
                                <th>Graduation Year:</th>
                                <td>${user.graduation_year}</td>
                            </tr>
                            ` : ''}
                            ${user.current_occupation ? `
                            <tr>
                                <th>Occupation:</th>
                                <td>${user.current_occupation}</td>
                            </tr>
                            ` : ''}
                            ${user.industry ? `
                            <tr>
                                <th>Industry:</th>
                                <td>${user.industry}</td>
                            </tr>
                            ` : ''}
                            ${user.location ? `
                            <tr>
                                <th>Location:</th>
                                <td>${user.location}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th>Posts:</th>
                                <td><span class="badge bg-info">${user.posts_count} posts</span></td>
                            </tr>
                            <tr>
                                <th>Connections:</th>
                                <td><span class="badge bg-primary">${user.connections_count} connections</span></td>
                            </tr>
                            <tr>
                                <th>Joined:</th>
                                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                            </tr>
                        </table>
                        
                        ${user.bio ? `
                        <div class="mt-3">
                            <h6>Bio:</h6>
                            <p class="text-muted">${user.bio}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
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

        // Add User Form Submission
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_user');
            
            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                    this.reset();
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            });
        });

        // Load schools for add user form
        fetch('../includes/get_schools.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const schoolSelect = document.getElementById('userSchool');
                    data.schools.forEach(school => {
                        const option = document.createElement('option');
                        option.value = school.id;
                        option.textContent = school.name;
                        schoolSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading schools:', error));
    </script>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="bi bi-person-plus"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addUserForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="userName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="userName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userEmail" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="userEmail" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="userRole" class="form-label">Role *</label>
                                <select class="form-select" id="userRole" name="role" required>
                                    <option value="student">Student/Alumni</option>
                                    <option value="school_admin">School Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="userSchool" class="form-label">School *</label>
                                <select class="form-select" id="userSchool" name="school_id" required>
                                    <option value="">Select School</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="userYearGroup" class="form-label">Year Group</label>
                                <input type="text" class="form-control" id="userYearGroup" name="year_group" 
                                       placeholder="e.g., 2020-2023">
                            </div>
                            <div class="col-md-6">
                                <label for="userStatus" class="form-label">Status</label>
                                <select class="form-select" id="userStatus" name="status">
                                    <option value="Current Student">Current Student</option>
                                    <option value="Graduated">Graduated</option>
                                    <option value="Left">Left School</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                <strong>Default Password:</strong> SchoolLink2024<br>
                                <small>The user will be able to change this after their first login.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userDetailsModalLabel">
                        <i class="bi bi-person"></i> User Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>