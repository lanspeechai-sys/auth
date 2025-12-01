<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('school_admin');
requireApproval();

$user = getCurrentUser();
$db = getDB();

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('../logout.php', 'School information not found.', 'error');
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? '';
$search_query = $_GET['search'] ?? '';

// Get all approved members for this school
$members = [];
$total_members = 0;

if ($db) {
    try {
        // Build query with filters
        $where_conditions = ["u.school_id = ?", "u.approved = 1", "u.role = 'student'"];
        $params = [$user['school_id']];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "u.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($year_filter)) {
            $where_conditions[] = "u.year_group LIKE ?";
            $params[] = "%$year_filter%";
        }
        
        if (!empty($search_query)) {
            $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total_members = $stmt->fetch()['total'];
        
        // Get members with pagination
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = 12;
        $offset = ($page - 1) * $per_page;
        
        $sql = "
            SELECT u.*, 
                   DATE(u.created_at) as join_date,
                   (SELECT COUNT(*) FROM comments c 
                    JOIN posts p ON c.post_id = p.id 
                    WHERE c.user_id = u.id AND p.school_id = ?) as comment_count
            FROM users u 
            WHERE $where_clause 
            ORDER BY u.name ASC 
            LIMIT $per_page OFFSET $offset
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$user['school_id']], $params));
        $members = $stmt->fetchAll();
        
        $total_pages = ceil($total_members / $per_page);
        
    } catch (PDOException $e) {
        error_log("Members fetch error: " . $e->getMessage());
    }
}

// Get unique year groups for filter
$year_groups = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT year_group 
            FROM users 
            WHERE school_id = ? AND approved = 1 AND role = 'student' AND year_group IS NOT NULL
            ORDER BY year_group ASC
        ");
        $stmt->execute([$user['school_id']]);
        $year_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Year groups fetch error: " . $e->getMessage());
    }
}

// Get member statistics
$stats = [
    'total' => 0,
    'graduated' => 0,
    'current' => 0,
    'left' => 0
];

if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT status, COUNT(*) as count 
            FROM users 
            WHERE school_id = ? AND approved = 1 AND role = 'student' 
            GROUP BY status
        ");
        $stmt->execute([$user['school_id']]);
        while ($row = $stmt->fetch()) {
            $stats['total'] += $row['count'];
            switch ($row['status']) {
                case 'Graduated':
                    $stats['graduated'] = $row['count'];
                    break;
                case 'Current Student':
                    $stats['current'] = $row['count'];
                    break;
                case 'Left':
                    $stats['left'] = $row['count'];
                    break;
            }
        }
    } catch (PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Directory - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                            <a class="nav-link text-white-50" href="join-requests.php">
                                <i class="bi bi-person-plus"></i> Join Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="members.php">
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
                        <i class="bi bi-people"></i> Members Directory
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportMembers()">
                                <i class="bi bi-download"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 text-primary"><?php echo $stats['total']; ?></h4>
                                <p class="text-muted mb-0">Total Members</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="bi bi-mortarboard text-success" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 text-success"><?php echo $stats['graduated']; ?></h4>
                                <p class="text-muted mb-0">Graduated</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="bi bi-book text-info" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 text-info"><?php echo $stats['current']; ?></h4>
                                <p class="text-muted mb-0">Current Students</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-person-dash text-warning" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 text-warning"><?php echo $stats['left']; ?></h4>
                                <p class="text-muted mb-0">Left School</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search Members</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search by name or email..." 
                                           value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="Graduated" <?php echo $status_filter == 'Graduated' ? 'selected' : ''; ?>>
                                            Graduated
                                        </option>
                                        <option value="Current Student" <?php echo $status_filter == 'Current Student' ? 'selected' : ''; ?>>
                                            Current Student
                                        </option>
                                        <option value="Left" <?php echo $status_filter == 'Left' ? 'selected' : ''; ?>>
                                            Left School
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="year" class="form-label">Year Group</label>
                                    <select class="form-select" id="year" name="year">
                                        <option value="">All Years</option>
                                        <?php foreach ($year_groups as $year): ?>
                                            <option value="<?php echo htmlspecialchars($year); ?>" 
                                                    <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($search_query) || !empty($status_filter) || !empty($year_filter)): ?>
                                <div class="mt-3">
                                    <a href="members.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x"></i> Clear Filters
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Members Grid -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Members 
                            <?php if ($total_members != $stats['total']): ?>
                                (<?php echo $total_members; ?> of <?php echo $stats['total']; ?> shown)
                            <?php else: ?>
                                (<?php echo $total_members; ?>)
                            <?php endif; ?>
                        </h5>
                        
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="gridView">
                                <i class="bi bi-grid"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="listView">
                                <i class="bi bi-list"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-people" style="font-size: 4rem;"></i>
                                <p class="mt-3">
                                    <?php if (!empty($search_query) || !empty($status_filter) || !empty($year_filter)): ?>
                                        No members found matching your criteria
                                    <?php else: ?>
                                        No members found
                                    <?php endif; ?>
                                </p>
                                <?php if (empty($search_query) && empty($status_filter) && empty($year_filter)): ?>
                                    <a href="join-requests.php" class="btn btn-primary">
                                        <i class="bi bi-person-plus"></i> Review Join Requests
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Grid View (Default) -->
                            <div class="row" id="gridContainer">
                                <?php foreach ($members as $member): ?>
                                    <div class="col-lg-4 col-md-6 mb-4">
                                        <div class="card h-100 member-card">
                                            <div class="card-body text-center">
                                                <div class="mb-3">
                                                    <?php if ($member['profile_photo']): ?>
                                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                                             alt="Profile" class="rounded-circle" 
                                                             style="width: 80px; height: 80px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                                             style="width: 80px; height: 80px; font-size: 2rem;">
                                                            <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <h6 class="card-title"><?php echo htmlspecialchars($member['name']); ?></h6>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($member['email']); ?></p>
                                                
                                                <div class="mb-2">
                                                    <?php
                                                    $statusColors = [
                                                        'Graduated' => 'success',
                                                        'Current Student' => 'primary',
                                                        'Left' => 'warning'
                                                    ];
                                                    $color = $statusColors[$member['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo htmlspecialchars($member['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="row text-center small text-muted">
                                                    <div class="col-6">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($member['year_group']); ?></div>
                                                        <small>Year Group</small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="fw-bold"><?php echo formatDate($member['join_date']); ?></div>
                                                        <small>Joined</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-3">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                onclick="viewMember(<?php echo $member['id']; ?>)">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" 
                                                           class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-envelope"></i> Email
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- List View (Hidden by default) -->
                            <div class="table-responsive" id="listContainer" style="display: none;">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>Year Group</th>
                                            <th>Status</th>
                                            <th>Join Date</th>
                                            <th>Activity</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <?php if ($member['profile_photo']): ?>
                                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                                                     alt="Profile" class="rounded-circle" 
                                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                                     style="width: 40px; height: 40px;">
                                                                    <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($member['name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($member['year_group']); ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $color = $statusColors[$member['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo htmlspecialchars($member['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDate($member['join_date']); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $member['comment_count']; ?> comments
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                onclick="viewMember(<?php echo $member['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" 
                                                           class="btn btn-outline-success btn-sm">
                                                            <i class="bi bi-envelope"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if (isset($total_pages) && $total_pages > 1): ?>
                                <nav class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i == ($page ?? 1) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                                    echo !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                                                    echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : '';
                                                    echo !empty($year_filter) ? '&year=' . urlencode($year_filter) : '';
                                                ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div class="modal fade" id="memberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Member Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="memberDetails">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Toggle between grid and list view
        document.getElementById('gridView').addEventListener('click', function() {
            document.getElementById('gridContainer').style.display = 'block';
            document.getElementById('listContainer').style.display = 'none';
            this.classList.add('active');
            document.getElementById('listView').classList.remove('active');
        });

        document.getElementById('listView').addEventListener('click', function() {
            document.getElementById('gridContainer').style.display = 'none';
            document.getElementById('listContainer').style.display = 'block';
            this.classList.add('active');
            document.getElementById('gridView').classList.remove('active');
        });

        // View member details
        function viewMember(memberId) {
            const modal = new bootstrap.Modal(document.getElementById('memberModal'));
            document.getElementById('memberDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            modal.show();
            
            // In a real application, you would fetch member details via AJAX
            setTimeout(() => {
                document.getElementById('memberDetails').innerHTML = `
                    <div class="text-center">
                        <p>Member details would be loaded here via AJAX.</p>
                        <p>Member ID: ${memberId}</p>
                    </div>
                `;
            }, 1000);
        }

        // Export members function
        function exportMembers() {
            showSuccessToast('Export functionality would be implemented here');
        }

        // Set initial view button state
        document.getElementById('gridView').classList.add('active');
    </script>
</body>
</html>