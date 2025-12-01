<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('student');
requireApproval();

$user = getCurrentUser();
$db = getDB();

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('../logout.php', 'School information not found.', 'error');
}

// Get recent posts from the school
$recent_posts = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT p.*, u.name as author_name 
            FROM posts p 
            JOIN users u ON p.author_id = u.id 
            WHERE p.school_id = ? 
            ORDER BY p.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user['school_id']]);
        $recent_posts = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching posts: " . $e->getMessage());
    }
}

// Get school statistics
$stats = [
    'total_members' => 0,
    'total_posts' => 0,
    'upcoming_events' => 0
];

if ($db) {
    try {
        // Total members
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE school_id = ? AND approved = 1 AND role = 'student'");
        $stmt->execute([$user['school_id']]);
        $result = $stmt->fetch();
        $stats['total_members'] = $result['count'];
        
        // Total posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE school_id = ?");
        $stmt->execute([$user['school_id']]);
        $result = $stmt->fetch();
        $stats['total_posts'] = $result['count'];
        
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
    <title><?php echo htmlspecialchars($school['name']); ?> - SchoolLink Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="bi bi-mortarboard-fill"></i> SchoolLink Africa
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="directory.php">
                            <i class="bi bi-people"></i> Alumni Directory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="posts.php">
                            <i class="bi bi-megaphone"></i> Posts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="bi bi-calendar-event"></i> Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="opportunities.php">
                            <i class="bi bi-briefcase"></i> Opportunities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-dots"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../store.php">
                            <i class="bi bi-shop"></i> Store
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                           id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile" class="rounded-circle me-2" 
                                     style="width: 30px; height: 30px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person"></i> Edit Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo getFlashMessage(); ?>
        
        <!-- School Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-lg bg-primary text-white">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php if ($school['logo']): ?>
                                    <img src="../uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
                                         alt="School Logo" class="img-fluid rounded-circle" 
                                         style="width: 100px; height: 100px; object-fit: cover; border: 4px solid white;">
                                <?php else: ?>
                                    <i class="bi bi-building text-white" style="font-size: 5rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-10">
                                <h1 class="display-6 fw-bold"><?php echo htmlspecialchars($school['name']); ?></h1>
                                <p class="lead mb-2">
                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($school['location']); ?>
                                </p>
                                <?php if ($school['description']): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($school['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <i class="bi bi-people text-primary" style="font-size: 2.5rem;"></i>
                        <h4 class="mt-2 text-primary"><?php echo $stats['total_members']; ?></h4>
                        <p class="text-muted mb-0">Alumni & Students</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <i class="bi bi-megaphone text-success" style="font-size: 2.5rem;"></i>
                        <h4 class="mt-2 text-success"><?php echo $stats['total_posts']; ?></h4>
                        <p class="text-muted mb-0">Posts & Updates</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-event text-warning" style="font-size: 2.5rem;"></i>
                        <h4 class="mt-2 text-warning"><?php echo $stats['upcoming_events']; ?></h4>
                        <p class="text-muted mb-0">Upcoming Events</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Posts -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-megaphone text-primary"></i> Recent Updates
                        </h5>
                        <a href="posts.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_posts)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-megaphone" style="font-size: 3rem;"></i>
                                <p class="mt-3">No updates available</p>
                                <p class="text-muted">Check back later for news and announcements from your school.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_posts as $post): ?>
                                <div class="border-bottom py-3 <?php echo $post !== end($recent_posts) ? 'mb-3' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($post['title']); ?></h6>
                                        <small class="text-muted"><?php echo timeAgo($post['created_at']); ?></small>
                                    </div>
                                    <p class="text-muted mb-2"><?php echo truncateText(htmlspecialchars($post['content']), 150); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($post['author_name']); ?>
                                        </small>
                                        <?php
                                        $typeIcons = [
                                            'update' => 'megaphone',
                                            'event' => 'calendar-event', 
                                            'opportunity' => 'briefcase'
                                        ];
                                        $typeColors = [
                                            'update' => 'primary',
                                            'event' => 'success',
                                            'opportunity' => 'warning'
                                        ];
                                        $icon = $typeIcons[$post['post_type']] ?? 'megaphone';
                                        $color = $typeColors[$post['post_type']] ?? 'primary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <i class="bi bi-<?php echo $icon; ?>"></i> <?php echo ucfirst($post['post_type']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning text-warning"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="members.php" class="btn btn-outline-primary">
                                <i class="bi bi-people"></i> Browse Alumni Directory
                            </a>
                            <a href="profile.php" class="btn btn-outline-success">
                                <i class="bi bi-person"></i> Update My Profile
                            </a>
                            <a href="events.php" class="btn btn-outline-info">
                                <i class="bi bi-calendar-event"></i> View Events
                            </a>
                        </div>
                    </div>
                </div>

                <!-- My Profile Summary -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-person-badge text-primary"></i> My Profile
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile" class="profile-img-lg">
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px; font-size: 2rem;">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="text-center"><?php echo htmlspecialchars($user['name']); ?></h6>
                        
                        <div class="text-center text-muted mb-3">
                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="text-muted">Year Group</small>
                                <div class="fw-bold">
                                    <?php
                                    // Get user's year group from database
                                    $user_details = null;
                                    if ($db) {
                                        try {
                                            $stmt = $db->prepare("SELECT year_group, status FROM users WHERE id = ?");
                                            $stmt->execute([$user['id']]);
                                            $user_details = $stmt->fetch();
                                        } catch (PDOException $e) {
                                            error_log("Error fetching user details: " . $e->getMessage());
                                        }
                                    }
                                    echo $user_details ? htmlspecialchars($user_details['year_group']) : '-';
                                    ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Status</small>
                                <div>
                                    <?php 
                                    $status = $user_details['status'] ?? 'Student';
                                    $statusColors = [
                                        'Graduated' => 'success',
                                        'Left' => 'warning',
                                        'Current Student' => 'primary'
                                    ];
                                    $color = $statusColors[$status] ?? 'primary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-3">
                            <a href="profile.php" class="btn btn-sm btn-outline-primary">
                                Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>