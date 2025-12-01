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

// Handle connection requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    try {
        if ($_POST['action'] === 'connect') {
            $target_user_id = intval($_POST['user_id'] ?? 0);
            
            if ($target_user_id === $user['id']) {
                $response['message'] = 'You cannot connect with yourself';
                sendJSONResponse($response);
                exit;
            }
            
            // Check if connection already exists
            $stmt = $db->prepare("
                SELECT id FROM connections 
                WHERE (user_id = ? AND connected_user_id = ?) 
                   OR (user_id = ? AND connected_user_id = ?)
            ");
            $stmt->execute([$user['id'], $target_user_id, $target_user_id, $user['id']]);
            
            if ($stmt->fetch()) {
                $response['message'] = 'Connection already exists or pending';
            } else {
                // Create connection request
                $stmt = $db->prepare("
                    INSERT INTO connections (user_id, connected_user_id, status) 
                    VALUES (?, ?, 'pending')
                ");
                $stmt->execute([$user['id'], $target_user_id]);
                $response['success'] = true;
                $response['message'] = 'Connection request sent successfully';
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error occurred';
        error_log("Connection request error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? '';
$search_query = $_GET['search'] ?? '';
$industry_filter = $_GET['industry'] ?? '';
$location_filter = $_GET['location'] ?? '';

// Get all approved members for this school (excluding current user)
$members = [];
$total_members = 0;

if ($db) {
    try {
        // Build query with filters
        $where_conditions = ["u.school_id = ?", "u.approved = 1", "u.role = 'student'", "u.id != ?"];
        $params = [$user['school_id'], $user['id']];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "u.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($year_filter)) {
            $where_conditions[] = "u.year_group LIKE ?";
            $params[] = "%$year_filter%";
        }
        
        if (!empty($search_query)) {
            $where_conditions[] = "(u.name LIKE ? OR u.current_occupation LIKE ? OR u.industry LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        if (!empty($industry_filter)) {
            $where_conditions[] = "u.industry LIKE ?";
            $params[] = "%$industry_filter%";
        }
        
        if (!empty($location_filter)) {
            $where_conditions[] = "u.location LIKE ?";
            $params[] = "%$location_filter%";
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($params);
        $total_members = $stmt->fetch()['total'];
        
        // Get members with pagination and connection status
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = 12;
        $offset = ($page - 1) * $per_page;
        
        $sql = "
            SELECT u.id, u.name, u.email, u.profile_photo, u.year_group, u.status,
                   u.current_occupation, u.industry, u.location, u.bio, u.linkedin_profile,
                   u.skills, u.interests, DATE(u.created_at) as join_date,
                   COALESCE(c1.status, c2.status) as connection_status,
                   CASE 
                       WHEN c1.user_id = ? THEN 'outgoing'
                       WHEN c2.user_id = u.id THEN 'incoming'
                       ELSE NULL
                   END as connection_direction
            FROM users u 
            LEFT JOIN connections c1 ON (c1.user_id = ? AND c1.connected_user_id = u.id)
            LEFT JOIN connections c2 ON (c2.user_id = u.id AND c2.connected_user_id = ?)
            WHERE $where_clause 
            ORDER BY u.name ASC 
            LIMIT $per_page OFFSET $offset
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$user['id'], $user['id'], $user['id']], $params));
        $members = $stmt->fetchAll();
        
        $total_pages = ceil($total_members / $per_page);
        
    } catch (PDOException $e) {
        error_log("Members fetch error: " . $e->getMessage());
    }
}

// Get unique filters data
$year_groups = [];
$industries = [];
$locations = [];

if ($db) {
    try {
        // Get year groups
        $stmt = $db->prepare("
            SELECT DISTINCT year_group 
            FROM users 
            WHERE school_id = ? AND approved = 1 AND role = 'student' AND year_group IS NOT NULL
            ORDER BY year_group ASC
        ");
        $stmt->execute([$user['school_id']]);
        $year_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get industries
        $stmt = $db->prepare("
            SELECT DISTINCT industry 
            FROM users 
            WHERE school_id = ? AND approved = 1 AND role = 'student' AND industry IS NOT NULL AND industry != ''
            ORDER BY industry ASC
        ");
        $stmt->execute([$user['school_id']]);
        $industries = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get locations
        $stmt = $db->prepare("
            SELECT DISTINCT location 
            FROM users 
            WHERE school_id = ? AND approved = 1 AND role = 'student' AND location IS NOT NULL AND location != ''
            ORDER BY location ASC
        ");
        $stmt->execute([$user['school_id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        error_log("Filter data fetch error: " . $e->getMessage());
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
            WHERE school_id = ? AND approved = 1 AND role = 'student' AND id != ?
            GROUP BY status
        ");
        $stmt->execute([$user['school_id'], $user['id']]);
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
    <title>Alumni Directory - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <?php if ($school['logo']): ?>
                    <img src="../uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
                         alt="Logo" class="me-2 rounded-circle" 
                         style="width: 40px; height: 40px; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-building me-2"></i>
                <?php endif; ?>
                SchoolLink Africa
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="directory.php">
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

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <h1 class="display-5 fw-bold text-primary">
                        <i class="bi bi-people"></i> Alumni Directory
                    </h1>
                    <p class="lead text-muted">
                        Connect with fellow alumni from <?php echo htmlspecialchars($school['name']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                        <h4 class="mt-2 text-primary"><?php echo $stats['total']; ?></h4>
                        <p class="text-muted mb-0">Alumni Connected</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-success h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-mortarboard text-success" style="font-size: 2rem;"></i>
                        <h4 class="mt-2 text-success"><?php echo $stats['graduated']; ?></h4>
                        <p class="text-muted mb-0">Graduates</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-info h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-book text-info" style="font-size: 2rem;"></i>
                        <h4 class="mt-2 text-info"><?php echo $stats['current']; ?></h4>
                        <p class="text-muted mb-0">Current Students</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card border-warning h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-briefcase text-warning" style="font-size: 2rem;"></i>
                        <h4 class="mt-2 text-warning"><?php echo count(array_filter($members, function($m) { return !empty($m['current_occupation']); })); ?></h4>
                        <p class="text-muted mb-0">With Careers Listed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Search and Filter -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Find Alumni</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label for="search" class="form-label">Search Alumni</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by name, occupation, industry, or skills..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        
                        <div class="col-lg-2">
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
                        
                        <div class="col-lg-2">
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
                        
                        <div class="col-lg-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Filters Row -->
                    <div class="row g-3 mt-2">
                        <div class="col-lg-4">
                            <label for="industry" class="form-label">Industry</label>
                            <select class="form-select" id="industry" name="industry">
                                <option value="">All Industries</option>
                                <?php foreach ($industries as $industry): ?>
                                    <option value="<?php echo htmlspecialchars($industry); ?>" 
                                            <?php echo $industry_filter == $industry ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($industry); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-4">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location); ?>" 
                                            <?php echo $location_filter == $location ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-4">
                            <label class="form-label">Quick Actions</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-outline-info" onclick="showNetworkingTips()">
                                    <i class="bi bi-lightbulb"></i> Networking Tips
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($search_query) || !empty($status_filter) || !empty($year_filter) || !empty($industry_filter) || !empty($location_filter)): ?>
                        <div class="mt-3">
                            <a href="directory.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x"></i> Clear All Filters
                            </a>
                            <span class="ms-2 text-muted small">
                                <?php echo $total_members; ?> alumni found
                            </span>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Alumni Grid -->
        <div class="row">
            <?php if (empty($members)): ?>
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3 text-muted">No Alumni Found</h4>
                            <p class="text-muted">
                                <?php if (!empty($search_query) || !empty($status_filter) || !empty($year_filter)): ?>
                                    No alumni match your search criteria. Try adjusting your filters.
                                <?php else: ?>
                                    There are no other alumni registered yet. Be the first to connect!
                                <?php endif; ?>
                            </p>
                            <?php if (empty($search_query) && empty($status_filter) && empty($year_filter)): ?>
                                <a href="../register.php" class="btn btn-primary mt-2">
                                    <i class="bi bi-person-plus"></i> Invite Alumni
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($members as $member): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm alumni-card">
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php if (!empty($member['profile_photo'])): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                             alt="Profile" class="rounded-circle mb-2" 
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                             style="width: 80px; height: 80px; font-size: 2rem;">
                                            <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($member['name']); ?></h5>
                                    
                                    <?php
                                    $statusColors = [
                                        'Graduated' => 'success',
                                        'Current Student' => 'primary',
                                        'Left' => 'warning'
                                    ];
                                    $color = $statusColors[$member['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> mb-2">
                                        <?php echo htmlspecialchars($member['status']); ?>
                                    </span>
                                    
                                    <!-- Connection Status Badge -->
                                    <?php if ($member['connection_status']): ?>
                                        <span class="badge bg-info ms-1">
                                            <?php if ($member['connection_status'] === 'accepted'): ?>
                                                <i class="bi bi-check-circle"></i> Connected
                                            <?php elseif ($member['connection_status'] === 'pending' && $member['connection_direction'] === 'outgoing'): ?>
                                                <i class="bi bi-clock"></i> Request Sent
                                            <?php elseif ($member['connection_status'] === 'pending' && $member['connection_direction'] === 'incoming'): ?>
                                                <i class="bi bi-exclamation-circle"></i> Pending Response
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> Class of <?php echo htmlspecialchars($member['year_group'] ?? 'N/A'); ?>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <?php if (!empty($member['current_occupation'])): ?>
                                        <div class="mb-1">
                                            <small class="text-muted">
                                                <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($member['current_occupation']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($member['industry'])): ?>
                                        <div class="mb-1">
                                            <small class="text-muted">
                                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($member['industry']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($member['location'])): ?>
                                        <div class="mb-1">
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($member['location']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($member['skills']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">Skills:</small>
                                        <div>
                                            <?php 
                                            $skills = explode(',', $member['skills']);
                                            $displaySkills = array_slice($skills, 0, 3);
                                            foreach ($displaySkills as $skill): 
                                            ?>
                                                <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($skills) > 3): ?>
                                                <span class="small text-muted">+<?php echo count($skills) - 3; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($member['bio'])): ?>
                                    <p class="text-muted small mb-2">
                                        <?php echo htmlspecialchars(substr($member['bio'], 0, 80)); ?>
                                        <?php if (strlen($member['bio']) > 80): ?>...<?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="viewAlumni(<?php echo $member['id']; ?>)"
                                            data-bs-toggle="modal" data-bs-target="#alumniModal">
                                        <i class="bi bi-eye"></i> View Full Profile
                                    </button>
                                    
                                    <div class="btn-group" role="group">
                                        <?php if (!$member['connection_status'] || $member['connection_status'] !== 'accepted'): ?>
                                            <?php if (!$member['connection_status']): ?>
                                                <button class="btn btn-success btn-sm connect-btn" 
                                                        data-user-id="<?php echo $member['id']; ?>">
                                                    <i class="bi bi-person-plus"></i> Connect
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" 
                                           class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-envelope"></i> Email
                                        </a>
                                        
                                        <?php if (!empty($member['linkedin_profile'])): ?>
                                            <a href="<?php echo htmlspecialchars($member['linkedin_profile']); ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="bi bi-linkedin"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav>
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
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alumni Profile Modal -->
    <div class="modal fade" id="alumniModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Alumni Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="alumniDetails">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>SchoolLink Africa</h5>
                    <p>Connecting alumni across African schools and building stronger communities.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 SchoolLink Africa. All rights reserved.</p>
                    <p>
                        <small class="text-muted">
                            Alumni of <?php echo htmlspecialchars($school['name']); ?>
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Networking Tips Modal -->
    <div class="modal fade" id="networkingTipsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lightbulb"></i> Networking Tips</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-handshake text-primary"></i> Making Connections</h6>
                            <ul class="small">
                                <li>Personalize your connection requests</li>
                                <li>Mention shared experiences or interests</li>
                                <li>Be genuine and authentic in your approach</li>
                                <li>Follow up respectfully after connecting</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-chat-dots text-success"></i> Starting Conversations</h6>
                            <ul class="small">
                                <li>Ask about their career journey</li>
                                <li>Share updates about your own path</li>
                                <li>Discuss school memories and experiences</li>
                                <li>Seek advice or mentorship opportunities</li>
                            </ul>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-briefcase text-warning"></i> Professional Networking</h6>
                            <ul class="small">
                                <li>Share job opportunities with classmates</li>
                                <li>Offer referrals and recommendations</li>
                                <li>Collaborate on professional projects</li>
                                <li>Create industry-specific groups</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-heart text-danger"></i> Building Community</h6>
                            <ul class="small">
                                <li>Organize reunion events and meetups</li>
                                <li>Support school fundraising efforts</li>
                                <li>Mentor current students</li>
                                <li>Share your success stories</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        Got it, thanks!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Connection functionality
        document.querySelectorAll('.connect-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.dataset.userId;
                sendConnectionRequest(userId, this);
            });
        });

        function sendConnectionRequest(userId, button) {
            const formData = new FormData();
            formData.append('action', 'connect');
            formData.append('user_id', userId);

            fetch('directory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<i class="bi bi-clock"></i> Request Sent';
                    button.disabled = true;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-info');
                    showAlert('success', data.message);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while sending the connection request.');
            });
        }

        function viewAlumni(alumniId) {
            // This would be implemented to fetch and display detailed alumni profile
            const modalContent = document.getElementById('alumniDetails');
            modalContent.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-person-circle text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Detailed Profile View</h5>
                    <p class="text-muted">Enhanced profile view with full biography, career history, achievements, and connection options would be displayed here.</p>
                    <div class="mt-3">
                        <button class="btn btn-primary me-2">
                            <i class="bi bi-person-plus"></i> Send Connection Request
                        </button>
                        <button class="btn btn-outline-info">
                            <i class="bi bi-envelope"></i> Send Message
                        </button>
                    </div>
                </div>
            `;
        }

        function showNetworkingTips() {
            const modal = new bootstrap.Modal(document.getElementById('networkingTipsModal'));
            modal.show();
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Enhanced card interactions
        document.querySelectorAll('.alumni-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.transition = 'transform 0.2s ease-in-out';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Auto-submit form on filter changes
        document.querySelectorAll('#status, #year, #industry, #location').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Advanced search functionality
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length > 2 || this.value.length === 0) {
                    this.form.submit();
                }
            }, 500);
        });
    </script>
</body>
</html>