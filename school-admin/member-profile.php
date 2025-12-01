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

// Get member ID from URL
$member_id = $_GET['id'] ?? 0;
$member = null;
$member_posts = [];

if ($db && $member_id) {
    try {
        // Get member details (only from same school)
        $stmt = $db->prepare("
            SELECT u.*, DATE(u.created_at) as join_date,
                   (SELECT COUNT(*) FROM posts WHERE author_id = u.id) as post_count,
                   (SELECT COUNT(*) FROM comments c 
                    JOIN posts p ON c.post_id = p.id 
                    WHERE c.user_id = u.id AND p.school_id = ?) as comment_count,
                   (SELECT MAX(created_at) FROM comments c 
                    JOIN posts p ON c.post_id = p.id 
                    WHERE c.user_id = u.id AND p.school_id = ?) as last_activity
            FROM users u 
            WHERE u.id = ? AND u.school_id = ? AND u.approved = 1 AND u.role = 'student'
        ");
        $stmt->execute([$user['school_id'], $user['school_id'], $member_id, $user['school_id']]);
        $member = $stmt->fetch();
        
        if ($member) {
            // Get member's recent posts
            $stmt = $db->prepare("
                SELECT p.*, u.name as author_name, u.profile_photo as author_photo,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                FROM posts p
                JOIN users u ON p.author_id = u.id
                WHERE p.author_id = ? AND p.school_id = ?
                ORDER BY p.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$member_id, $user['school_id']]);
            $member_posts = $stmt->fetchAll();
        }
        
    } catch (PDOException $e) {
        error_log("Member fetch error: " . $e->getMessage());
    }
}

if (!$member) {
    redirect('members.php', 'Member not found or access denied.', 'error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['name']); ?> - Members Directory</title>
    
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
                        <a href="members.php" class="text-decoration-none text-muted">
                            <i class="bi bi-people"></i> Members
                        </a>
                        <span class="text-muted">/</span>
                        <?php echo htmlspecialchars($member['name']); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="members.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Members
                            </a>
                            <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-envelope"></i> Send Email
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Member Profile Card -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <?php if ($member['profile_photo']): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($member['profile_photo']); ?>" 
                                             alt="Profile" class="rounded-circle mb-3" 
                                             style="width: 120px; height: 120px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                             style="width: 120px; height: 120px; font-size: 3rem;">
                                            <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <h4 class="card-title"><?php echo htmlspecialchars($member['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($member['email']); ?></p>
                                
                                <div class="mb-3">
                                    <?php
                                    $statusColors = [
                                        'Graduated' => 'success',
                                        'Current Student' => 'primary',
                                        'Left' => 'warning'
                                    ];
                                    $color = $statusColors[$member['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> fs-6">
                                        <?php echo htmlspecialchars($member['status']); ?>
                                    </span>
                                </div>
                                
                                <hr>
                                
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h5 class="text-primary"><?php echo $member['post_count']; ?></h5>
                                        <small class="text-muted">Posts</small>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="text-success"><?php echo $member['comment_count']; ?></h5>
                                        <small class="text-muted">Comments</small>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="text-info">
                                            <?php echo $member['last_activity'] ? formatDate($member['last_activity']) : 'None'; ?>
                                        </h5>
                                        <small class="text-muted">Last Activity</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Details -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Member Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-muted">Full Name</label>
                                        <p class="mb-0"><?php echo htmlspecialchars($member['name']); ?></p>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-muted">Email Address</label>
                                        <p class="mb-0">
                                            <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>">
                                                <?php echo htmlspecialchars($member['email']); ?>
                                            </a>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-muted">Phone Number</label>
                                        <p class="mb-0">
                                            <?php if ($member['phone']): ?>
                                                <?php echo htmlspecialchars($member['phone']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not provided</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-muted">Year Group</label>
                                        <p class="mb-0">
                                            <span class="badge bg-info"><?php echo htmlspecialchars($member['year_group']); ?></span>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-muted">Student Status</label>
                                        <p class="mb-0">
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo htmlspecialchars($member['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-muted">Join Date</label>
                                        <p class="mb-0"><?php echo formatDate($member['join_date']); ?></p>
                                    </div>
                                    
                                    <?php if ($member['bio']): ?>
                                        <div class="col-12 mb-3">
                                            <label class="form-label fw-bold text-muted">Biography</label>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($member['bio'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($member['linkedin_profile']): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold text-muted">LinkedIn Profile</label>
                                            <p class="mb-0">
                                                <a href="<?php echo htmlspecialchars($member['linkedin_profile']); ?>" 
                                                   target="_blank" rel="noopener">
                                                    <i class="bi bi-linkedin"></i> View Profile
                                                </a>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($member['current_occupation']): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold text-muted">Current Occupation</label>
                                            <p class="mb-0"><?php echo htmlspecialchars($member['current_occupation']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Posts -->
                <?php if (!empty($member_posts)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-megaphone"></i> Recent Posts by <?php echo htmlspecialchars($member['name']); ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($member_posts as $post): ?>
                                        <div class="border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <span class="badge bg-<?php 
                                                            echo $post['type'] == 'event' ? 'warning' : 
                                                                ($post['type'] == 'opportunity' ? 'info' : 'primary'); 
                                                        ?>">
                                                            <?php echo ucfirst($post['type']); ?>
                                                        </span>
                                                        <?php echo htmlspecialchars($post['title']); ?>
                                                    </h6>
                                                    
                                                    <p class="mb-2 text-muted">
                                                        <?php echo htmlspecialchars(substr($post['content'], 0, 200)); ?>
                                                        <?php if (strlen($post['content']) > 200): ?>...<?php endif; ?>
                                                    </p>
                                                    
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> <?php echo formatDateTime($post['created_at']); ?>
                                                        <span class="ms-3">
                                                            <i class="bi bi-chat"></i> <?php echo $post['comment_count']; ?> comments
                                                        </span>
                                                    </small>
                                                </div>
                                                
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../post-details.php?id=<?php echo $post['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" target="_blank">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-center">
                                        <a href="posts.php?author=<?php echo $member['id']; ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="bi bi-megaphone"></i> View All Posts by <?php echo htmlspecialchars($member['name']); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Member Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-gear"></i> Member Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="d-grid">
                                            <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" 
                                               class="btn btn-primary">
                                                <i class="bi bi-envelope"></i> Send Email
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-info" 
                                                    onclick="generateMemberReport(<?php echo $member['id']; ?>)">
                                                <i class="bi bi-file-earmark-text"></i> Generate Report
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="confirmAction('suspend', <?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>')">
                                                <i class="bi bi-person-x"></i> Suspend Member
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Note:</strong> Member suspension and reporting features would be implemented 
                                    based on your specific requirements and policies.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Action Confirmation Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="actionModalBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        function generateMemberReport(memberId) {
            showSuccessToast('Member report generation would be implemented here');
            console.log('Generate report for member:', memberId);
        }

        function confirmAction(action, memberId, memberName) {
            const modal = new bootstrap.Modal(document.getElementById('actionModal'));
            const modalBody = document.getElementById('actionModalBody');
            const confirmBtn = document.getElementById('confirmActionBtn');
            
            if (action === 'suspend') {
                modalBody.innerHTML = `
                    <p>Are you sure you want to suspend <strong>${memberName}</strong>?</p>
                    <p>This action will:</p>
                    <ul>
                        <li>Prevent them from logging in</li>
                        <li>Hide their posts and comments</li>
                        <li>Remove them from the members directory</li>
                    </ul>
                    <p class="text-danger">This action can be reversed later.</p>
                `;
                
                confirmBtn.onclick = function() {
                    showWarningToast('Member suspension feature would be implemented here');
                    modal.hide();
                };
            }
            
            modal.show();
        }
    </script>
</body>
</html>