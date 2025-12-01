<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('super_admin');

$user = getCurrentUser();
$db = getDB();

// Handle AJAX requests for school management
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    $school_id = intval($_POST['school_id'] ?? 0);
    
    try {
        switch ($_POST['action']) {
            case 'approve_school':
                $db->beginTransaction();
                
                // Approve the school
                $stmt = $db->prepare("UPDATE schools SET approved = 1 WHERE id = ?");
                $stmt->execute([$school_id]);
                
                // Approve all school admin users for this school
                $stmt = $db->prepare("UPDATE users SET approved = 1 WHERE school_id = ? AND role = 'school_admin'");
                $stmt->execute([$school_id]);
                
                $db->commit();
                $response['message'] = 'School and school admin approved successfully';
                $response['success'] = true;
                break;
                
            case 'reject_school':
                // Delete school and associated users
                $db->beginTransaction();
                
                // Delete users associated with this school
                $stmt = $db->prepare("DELETE FROM users WHERE school_id = ?");
                $stmt->execute([$school_id]);
                
                // Delete school
                $stmt = $db->prepare("DELETE FROM schools WHERE id = ?");
                $stmt->execute([$school_id]);
                
                $db->commit();
                $response['message'] = 'School rejected and removed along with associated users';
                $response['success'] = true;
                break;
                
            case 'suspend_school':
                $stmt = $db->prepare("UPDATE schools SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$school_id]);
                $response['message'] = 'School suspended successfully';
                $response['success'] = true;
                break;
                
            case 'activate_school':
                $stmt = $db->prepare("UPDATE schools SET status = 'active' WHERE id = ?");
                $stmt->execute([$school_id]);
                $response['message'] = 'School activated successfully';
                $response['success'] = true;
                break;
                
            case 'delete_school':
                $db->beginTransaction();
                
                // Delete all associated data
                $stmt = $db->prepare("DELETE FROM posts WHERE user_id IN (SELECT id FROM users WHERE school_id = ?)");
                $stmt->execute([$school_id]);
                
                $stmt = $db->prepare("DELETE FROM events WHERE school_id = ?");
                $stmt->execute([$school_id]);
                
                $stmt = $db->prepare("DELETE FROM opportunities WHERE school_id = ?");
                $stmt->execute([$school_id]);
                
                $stmt = $db->prepare("DELETE FROM users WHERE school_id = ?");
                $stmt->execute([$school_id]);
                
                $stmt = $db->prepare("DELETE FROM schools WHERE id = ?");
                $stmt->execute([$school_id]);
                
                $db->commit();
                $response['message'] = 'School and all associated data deleted successfully';
                $response['success'] = true;
                break;
                
            case 'add_school':
                $name = trim($_POST['name'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name) || empty($location) || empty($contact_email)) {
                    $response['message'] = 'Name, location, and contact email are required';
                    break;
                }
                
                // Check if school already exists
                $stmt = $db->prepare("SELECT id FROM schools WHERE name = ? OR contact_email = ?");
                $stmt->execute([$name, $contact_email]);
                if ($stmt->fetch()) {
                    $response['message'] = 'A school with this name or email already exists';
                    break;
                }
                
                $stmt = $db->prepare("
                    INSERT INTO schools (name, location, contact_email, website, description, approved, status) 
                    VALUES (?, ?, ?, ?, ?, 1, 'active')
                ");
                $stmt->execute([$name, $location, $contact_email, $website, $description]);
                
                $response['message'] = 'School added successfully';
                $response['success'] = true;
                break;
                
            case 'get_school_details':
                $stmt = $db->prepare("
                    SELECT s.*, COUNT(u.id) as user_count,
                           (SELECT COUNT(*) FROM users WHERE school_id = s.id AND role = 'school_admin') as admin_count
                    FROM schools s
                    LEFT JOIN users u ON s.id = u.school_id
                    WHERE s.id = ?
                    GROUP BY s.id
                ");
                $stmt->execute([$school_id]);
                $school_details = $stmt->fetch();
                
                if ($school_details) {
                    $response['success'] = true;
                    $response['school'] = $school_details;
                } else {
                    $response['message'] = 'School not found';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $response['message'] = 'Database error occurred';
        error_log("School management error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get filter and search parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query based on filters
$where_conditions = [];
$params = [];

if ($filter === 'pending') {
    $where_conditions[] = "approved = 0";
} elseif ($filter === 'approved') {
    $where_conditions[] = "approved = 1";
} elseif ($filter === 'suspended') {
    $where_conditions[] = "status = 'suspended'";
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR location LIKE ? OR contact_email LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get schools with pagination
$schools = [];
$total_schools = 0;

if ($db) {
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM schools {$where_clause}";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_schools = $stmt->fetchColumn();
        
        // Get schools for current page
        $query = "SELECT s.*, 
                         (SELECT COUNT(*) FROM users WHERE school_id = s.id) as user_count
                  FROM schools s 
                  {$where_clause}
                  ORDER BY s.created_at DESC 
                  LIMIT {$per_page} OFFSET {$offset}";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $schools = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching schools: " . $e->getMessage());
    }
}

$total_pages = ceil($total_schools / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools - SchoolLink Africa</title>
    
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
                            <a class="nav-link active text-white" href="schools.php">
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
                    <h1 class="h2">Manage Schools</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" 
                                    data-bs-toggle="modal" data-bs-target="#addSchoolModal">
                                <i class="bi bi-plus-circle"></i> Add School
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select name="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Schools</option>
                                    <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                    <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="suspended" <?php echo $filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search schools..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <a href="schools.php" class="btn btn-outline-secondary w-100">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Showing <?php echo count($schools); ?> of <?php echo $total_schools; ?> schools
                        </span>
                    </div>
                </div>

                <?php echo getFlashMessage(); ?>

                <!-- Schools Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-building"></i> Schools List
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schools)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-building" style="font-size: 3rem;"></i>
                                <p class="mt-3">No schools found</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>School Details</th>
                                            <th>Location</th>
                                            <th>Contact</th>
                                            <th>Users</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schools as $school): ?>
                                            <tr id="school-<?php echo $school['id']; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($school['logo']): ?>
                                                            <img src="../uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
                                                                 alt="School Logo" class="rounded me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-secondary rounded me-3 d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="bi bi-building text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($school['name']); ?></strong>
                                                            <?php if ($school['description']): ?>
                                                                <br><small class="text-muted">
                                                                    <?php echo truncateText(htmlspecialchars($school['description']), 80); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($school['location']); ?></td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($school['contact_email']); ?>">
                                                        <?php echo htmlspecialchars($school['contact_email']); ?>
                                                    </a>
                                                    <?php if ($school['website']): ?>
                                                        <br><a href="<?php echo htmlspecialchars($school['website']); ?>" 
                                                               target="_blank" class="text-muted small">
                                                            <i class="bi bi-globe"></i> Website
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $school['user_count']; ?> users</span>
                                                </td>
                                                <td>
                                                    <?php if (!$school['approved']): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($school['status'] === 'suspended'): ?>
                                                        <span class="badge bg-danger">Suspended</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($school['created_at']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if (!$school['approved']): ?>
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    onclick="approveSchool(<?php echo $school['id']; ?>)"
                                                                    title="Approve School">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="rejectSchool(<?php echo $school['id']; ?>)"
                                                                    title="Reject School">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <?php if ($school['status'] !== 'suspended'): ?>
                                                                <button type="button" class="btn btn-sm btn-warning" 
                                                                        onclick="suspendSchool(<?php echo $school['id']; ?>)"
                                                                        title="Suspend School">
                                                                    <i class="bi bi-pause-circle"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-sm btn-success" 
                                                                        onclick="activateSchool(<?php echo $school['id']; ?>)"
                                                                        title="Activate School">
                                                                    <i class="bi bi-play-circle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                onclick="viewSchool(<?php echo $school['id']; ?>)"
                                                                title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="deleteSchool(<?php echo $school['id']; ?>)"
                                                                title="Delete School">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Schools pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
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

    <!-- School Details Modal -->
    <div class="modal fade" id="schoolModal" tabindex="-1" aria-labelledby="schoolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schoolModalLabel">School Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="schoolModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
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
            if (confirm('Are you sure you want to reject this school? This will delete the school and all associated users. This action cannot be undone.')) {
                performSchoolAction('reject_school', schoolId);
            }
        }

        function suspendSchool(schoolId) {
            if (confirm('Are you sure you want to suspend this school? Users will not be able to access their accounts.')) {
                performSchoolAction('suspend_school', schoolId);
            }
        }

        function activateSchool(schoolId) {
            if (confirm('Are you sure you want to activate this school?')) {
                performSchoolAction('activate_school', schoolId);
            }
        }

        function performSchoolAction(action, schoolId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('school_id', schoolId);

            fetch('schools.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    
                    if (action === 'reject_school') {
                        // Remove the school row
                        const row = document.getElementById('school-' + schoolId);
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

        function deleteSchool(schoolId) {
            if (confirm('Are you sure you want to delete this school? This will permanently remove all associated data including users, posts, and events. This action cannot be undone.')) {
                performSchoolAction('delete_school', schoolId);
            }
        }

        function viewSchool(schoolId) {
            const modal = new bootstrap.Modal(document.getElementById('schoolDetailsModal'));
            document.getElementById('schoolDetailsContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            modal.show();
            
            // Fetch school details
            const formData = new FormData();
            formData.append('action', 'get_school_details');
            formData.append('school_id', schoolId);

            fetch('schools.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySchoolDetails(data.school);
                } else {
                    document.getElementById('schoolDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('schoolDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Failed to load school details.
                    </div>
                `;
            });
        }

        function displaySchoolDetails(school) {
            const statusBadge = school.approved === '1' 
                ? (school.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Suspended</span>')
                : '<span class="badge bg-warning">Pending Approval</span>';
                
            document.getElementById('schoolDetailsContent').innerHTML = `
                <div class="row">
                    <div class="col-md-4 text-center">
                        ${school.logo 
                            ? `<img src="../uploads/logos/${school.logo}" alt="School Logo" class="img-fluid rounded mb-3" style="max-width: 150px;">`
                            : '<div class="bg-secondary rounded d-inline-flex align-items-center justify-content-center mb-3" style="width: 150px; height: 150px;"><i class="bi bi-building text-white" style="font-size: 3rem;"></i></div>'
                        }
                        <h5>${school.name}</h5>
                        ${statusBadge}
                    </div>
                    <div class="col-md-8">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">Location:</th>
                                <td>${school.location}</td>
                            </tr>
                            <tr>
                                <th>Contact Email:</th>
                                <td><a href="mailto:${school.contact_email}">${school.contact_email}</a></td>
                            </tr>
                            ${school.website ? `
                            <tr>
                                <th>Website:</th>
                                <td><a href="${school.website}" target="_blank">${school.website}</a></td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th>Total Users:</th>
                                <td><span class="badge bg-info">${school.user_count} users</span></td>
                            </tr>
                            <tr>
                                <th>School Admins:</th>
                                <td><span class="badge bg-primary">${school.admin_count} admins</span></td>
                            </tr>
                            <tr>
                                <th>Registered:</th>
                                <td>${new Date(school.created_at).toLocaleDateString()}</td>
                            </tr>
                        </table>
                        
                        ${school.description ? `
                        <div class="mt-3">
                            <h6>Description:</h6>
                            <p class="text-muted">${school.description}</p>
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

        // Add School Form Submission
        document.getElementById('addSchoolForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_school');
            
            fetch('schools.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('addSchoolModal')).hide();
                    this.reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            });
        });
    </script>

    <!-- Add School Modal -->
    <div class="modal fade" id="addSchoolModal" tabindex="-1" aria-labelledby="addSchoolModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSchoolModalLabel">
                        <i class="bi bi-plus-circle"></i> Add New School
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addSchoolForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="schoolName" class="form-label">School Name *</label>
                                <input type="text" class="form-control" id="schoolName" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="schoolLocation" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="schoolLocation" name="location" required>
                            </div>
                            <div class="col-md-6">
                                <label for="schoolEmail" class="form-label">Contact Email *</label>
                                <input type="email" class="form-control" id="schoolEmail" name="contact_email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="schoolWebsite" class="form-label">Website</label>
                                <input type="url" class="form-control" id="schoolWebsite" name="website" placeholder="https://">
                            </div>
                            <div class="col-12">
                                <label for="schoolDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="schoolDescription" name="description" rows="3" 
                                          placeholder="Brief description of the school..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Add School
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- School Details Modal -->
    <div class="modal fade" id="schoolDetailsModal" tabindex="-1" aria-labelledby="schoolDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schoolDetailsModalLabel">
                        <i class="bi bi-building"></i> School Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="schoolDetailsContent">
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