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

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('../logout.php', 'School information not found.', 'error');
}

// Get statistics for the school
$stats = [
    'total_members' => 0,
    'pending_requests' => 0,
    'total_posts' => 0,
    'recent_members' => 0
];

if ($db) {
    try {
        // Total approved members
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE school_id = ? AND approved = 1 AND role = 'student'");
        $stmt->execute([$user['school_id']]);
        $result = $stmt->fetch();
        $stats['total_members'] = $result['count'];
        
        // Pending join requests
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM join_requests WHERE school_id = ? AND status = 'pending'");
        $stmt->execute([$user['school_id']]);
        $result = $stmt->fetch();
        $stats['pending_requests'] = $result['count'];
        
        // Total posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE school_id = ?");
        $stmt->execute([$user['school_id']]);
        $result = $stmt->fetch();
        $stats['total_posts'] = $result['count'];
        
        // Members joined in last 30 days
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM users 
            WHERE school_id = ? AND approved = 1 AND role = 'student' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$user['school_id']]);
        $result = $stmt->fetch();
        $stats['recent_members'] = $result['count'];
        
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
    <title>School Admin Dashboard - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                            <a class="nav-link active text-white" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="join-requests.php">
                                <i class="bi bi-person-plus"></i> Join Requests
                                <?php if ($stats['pending_requests'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $stats['pending_requests']; ?></span>
                                <?php endif; ?>
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
                        
                        <!-- E-commerce Section -->
                        <li class="nav-item mt-3">
                            <h6 class="sidebar-heading text-muted px-3 mt-4 mb-1 text-uppercase">
                                <span>E-Commerce</span>
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
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="../store.php">
                                <i class="bi bi-shop"></i> View Store
                            </a>
                        </li>
                        
                        <li class="nav-item mt-3">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="posts.php?action=create" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Create Post
                            </a>
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
                                            Total Members
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_members']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
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
                                            Pending Requests
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pending_requests']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-plus text-warning" style="font-size: 2rem;"></i>
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
                                            Total Posts
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_posts']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-megaphone text-success" style="font-size: 2rem;"></i>
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
                                            New Members (30d)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['recent_members']; ?>
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

                <div class="row">
                    <!-- Recent Join Requests -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-person-plus"></i> Recent Join Requests
                                </h6>
                                <a href="join-requests.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get recent join requests
                                $recent_requests = [];
                                if ($db) {
                                    try {
                                        $stmt = $db->prepare("
                                            SELECT jr.*, u.name, u.email, u.year_group, u.status as user_status
                                            FROM join_requests jr 
                                            JOIN users u ON jr.user_id = u.id 
                                            WHERE jr.school_id = ? AND jr.status = 'pending'
                                            ORDER BY jr.created_at DESC 
                                            LIMIT 5
                                        ");
                                        $stmt->execute([$user['school_id']]);
                                        $recent_requests = $stmt->fetchAll();
                                    } catch (PDOException $e) {
                                        error_log("Error fetching recent requests: " . $e->getMessage());
                                    }
                                }
                                ?>
                                
                                <?php if (empty($recent_requests)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                                        <p class="mt-2 mb-0">No pending requests</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_requests as $request): ?>
                                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                            <div>
                                                <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($request['year_group']); ?> • 
                                                    <?php echo htmlspecialchars($request['user_status']); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <small class="text-muted"><?php echo timeAgo($request['created_at']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="posts.php?action=create" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create New Post
                                    </a>
                                    <a href="events.php?action=create" class="btn btn-success">
                                        <i class="bi bi-calendar-plus"></i> Add Event
                                    </a>
                                    <a href="join-requests.php" class="btn btn-warning">
                                        <i class="bi bi-person-plus"></i> Review Join Requests
                                    </a>
                                    <a href="members.php" class="btn btn-info">
                                        <i class="bi bi-people"></i> Manage Members
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-activity"></i> Recent Activity
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get recent activity (posts, member joins, etc.)
                                $recent_activity = [];
                                if ($db) {
                                    try {
                                        // Get recent posts
                                        $stmt = $db->prepare("
                                            SELECT 'post' as activity_type, p.title, p.created_at, u.name as author_name
                                            FROM posts p 
                                            JOIN users u ON p.author_id = u.id 
                                            WHERE p.school_id = ? 
                                            ORDER BY p.created_at DESC 
                                            LIMIT 5
                                        ");
                                        $stmt->execute([$user['school_id']]);
                                        $posts = $stmt->fetchAll();
                                        
                                        // Get recent member approvals
                                        $stmt = $db->prepare("
                                            SELECT 'member_join' as activity_type, u.name, u.updated_at as created_at
                                            FROM users u 
                                            WHERE u.school_id = ? AND u.approved = 1 AND u.role = 'student'
                                            ORDER BY u.updated_at DESC 
                                            LIMIT 5
                                        ");
                                        $stmt->execute([$user['school_id']]);
                                        $members = $stmt->fetchAll();
                                        
                                        // Combine and sort by date
                                        $recent_activity = array_merge($posts, $members);
                                        usort($recent_activity, function($a, $b) {
                                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                                        });
                                        
                                        $recent_activity = array_slice($recent_activity, 0, 8);
                                        
                                    } catch (PDOException $e) {
                                        error_log("Error fetching recent activity: " . $e->getMessage());
                                    }
                                }
                                ?>
                                
                                <?php if (empty($recent_activity)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No recent activity to show</p>
                                        <a href="posts.php?action=create" class="btn btn-primary">Create Your First Post</a>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <div class="timeline-item d-flex align-items-center py-2 border-bottom">
                                                <div class="timeline-icon me-3">
                                                    <?php if ($activity['activity_type'] == 'post'): ?>
                                                        <i class="bi bi-megaphone text-primary"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-person-plus text-success"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <?php if ($activity['activity_type'] == 'post'): ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($activity['author_name']); ?></strong> 
                                                            created a post: "<?php echo htmlspecialchars(truncateText($activity['title'], 50)); ?>"
                                                        </div>
                                                    <?php else: ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($activity['name']); ?></strong> 
                                                            joined the school community
                                                        </div>
                                                    <?php endif; ?>
                                                    <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>