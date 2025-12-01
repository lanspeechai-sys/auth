<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
requireApproval();

$user = getCurrentUser();
$db = getDB();

// Get school information
$school = getSchoolInfo($user['school_id']);
if (!$school) {
    redirect('logout.php', 'School information not found.', 'error');
}

// Get post ID from URL
$post_id = $_GET['id'] ?? 0;
$post = null;
$comments = [];

if ($db && $post_id) {
    try {
        // Get post details (only from same school)
        $stmt = $db->prepare("
            SELECT p.*, u.name as author_name, u.profile_photo as author_photo,
                   (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                   (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as user_liked
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.id = ? AND p.school_id = ?
        ");
        $stmt->execute([$user['id'], $post_id, $user['school_id']]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Get comments for this post
            $stmt = $db->prepare("
                SELECT c.*, u.name as commenter_name, u.profile_photo as commenter_photo
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$post_id]);
            $comments = $stmt->fetchAll();
        }
        
    } catch (PDOException $e) {
        error_log("Post fetch error: " . $e->getMessage());
    }
}

if (!$post) {
    redirect('index.php', 'Post not found or access denied.', 'error');
}

$errors = [];
$success_message = '';

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $comment_content = trim($_POST['comment_content'] ?? '');
        
        if (empty($comment_content)) {
            $errors[] = 'Comment content is required.';
        }
        
        if (empty($errors) && $db) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO comments (post_id, user_id, content, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                
                if ($stmt->execute([$post_id, $user['id'], $comment_content])) {
                    $success_message = 'Comment added successfully!';
                    // Refresh comments
                    $stmt = $db->prepare("
                        SELECT c.*, u.name as commenter_name, u.profile_photo as commenter_photo
                        FROM comments c
                        JOIN users u ON c.user_id = u.id
                        WHERE c.post_id = ?
                        ORDER BY c.created_at ASC
                    ");
                    $stmt->execute([$post_id]);
                    $comments = $stmt->fetchAll();
                } else {
                    $errors[] = 'Failed to add comment. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Comment add error: " . $e->getMessage());
                $errors[] = 'Database error occurred. Please try again.';
            }
        }
    }
}

// Handle like/unlike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    if ($db) {
        try {
            // Check if user already liked this post
            $stmt = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user['id']]);
            $existing_like = $stmt->fetch();
            
            if ($existing_like) {
                // Unlike the post
                $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$post_id, $user['id']]);
                $success_message = 'Post unliked!';
            } else {
                // Like the post
                $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$post_id, $user['id']]);
                $success_message = 'Post liked!';
            }
            
            // Refresh post data to get updated like count
            $stmt = $db->prepare("
                SELECT p.*, u.name as author_name, u.profile_photo as author_photo,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = ?) as user_liked
                FROM posts p
                JOIN users u ON p.author_id = u.id
                WHERE p.id = ? AND p.school_id = ?
            ");
            $stmt->execute([$user['id'], $post_id, $user['school_id']]);
            $post = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Like toggle error: " . $e->getMessage());
            $errors[] = 'Failed to update like status. Please try again.';
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - SchoolLink Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <?php if ($school['logo']): ?>
                    <img src="uploads/logos/<?php echo htmlspecialchars($school['logo']); ?>" 
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
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user/directory.php">
                            <i class="bi bi-people"></i> Alumni Directory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#posts">
                            <i class="bi bi-megaphone"></i> Posts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user/events.php">
                            <i class="bi bi-calendar-event"></i> Events
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                           id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if ($user['profile_photo']): ?>
                                <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Profile" class="rounded-circle me-2" 
                                     style="width: 30px; height: 30px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-1"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="user/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a></li>
                            <li><a class="dropdown-item" href="user/profile.php">
                                <i class="bi bi-person"></i> Edit Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Posts</li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo htmlspecialchars(substr($post['title'], 0, 30)); ?>...
                </li>
            </ol>
        </nav>

        <!-- Alert Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Post Content -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center">
                                <?php if ($post['author_photo']): ?>
                                    <img src="uploads/profiles/<?php echo htmlspecialchars($post['author_photo']); ?>" 
                                         alt="Author" class="rounded-circle me-3" 
                                         style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                         style="width: 50px; height: 50px;">
                                        <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($post['author_name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> <?php echo formatDateTime($post['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div>
                                <?php
                                $typeColors = [
                                    'update' => 'primary',
                                    'event' => 'warning',
                                    'opportunity' => 'info'
                                ];
                                $color = $typeColors[$post['type']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($post['type']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                        
                        <?php if ($post['type'] == 'event' && $post['event_date']): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-calendar-event"></i>
                                <strong>Event Date:</strong> <?php echo formatDate($post['event_date']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        
                        <?php if ($post['image']): ?>
                            <div class="mt-3">
                                <img src="uploads/posts/<?php echo htmlspecialchars($post['image']); ?>" 
                                     alt="Post Image" class="img-fluid rounded" 
                                     style="max-height: 400px; width: auto;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-3">
                                <!-- Like Button -->
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" name="toggle_like" 
                                            class="btn btn-sm <?php echo $post['user_liked'] ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                        <i class="bi bi-heart<?php echo $post['user_liked'] ? '-fill' : ''; ?>"></i>
                                        <?php echo $post['like_count']; ?> Like<?php echo $post['like_count'] != 1 ? 's' : ''; ?>
                                    </button>
                                </form>
                                
                                <!-- Comment Count -->
                                <span class="text-muted">
                                    <i class="bi bi-chat"></i>
                                    <?php echo $post['comment_count']; ?> Comment<?php echo $post['comment_count'] != 1 ? 's' : ''; ?>
                                </span>
                                
                                <!-- Share Button -->
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="sharePost()">
                                    <i class="bi bi-share"></i> Share
                                </button>
                            </div>
                            
                            <?php if ($post['author_id'] == $user['id'] || $user['role'] == 'school_admin'): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($post['author_id'] == $user['id']): ?>
                                            <li><a class="dropdown-item" href="#" onclick="editPost(<?php echo $post['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Edit Post
                                            </a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deletePost(<?php echo $post['id']; ?>)">
                                            <i class="bi bi-trash"></i> Delete Post
                                        </a></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div class="card shadow mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-chat-dots"></i> Comments (<?php echo count($comments); ?>)
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <!-- Add Comment Form -->
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="d-flex">
                                <div class="me-3">
                                    <?php if ($user['profile_photo']): ?>
                                        <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                             alt="Your Photo" class="rounded-circle" 
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-grow-1">
                                    <textarea class="form-control mb-2" name="comment_content" rows="3" 
                                              placeholder="Write a comment..." required></textarea>
                                    <button type="submit" name="add_comment" class="btn btn-primary btn-sm">
                                        <i class="bi bi-send"></i> Post Comment
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Comments List -->
                        <?php if (empty($comments)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-chat" style="font-size: 2rem;"></i>
                                <p class="mt-2">No comments yet. Be the first to comment!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="d-flex mb-3 comment-item">
                                    <div class="me-3">
                                        <?php if ($comment['commenter_photo']): ?>
                                            <img src="uploads/profiles/<?php echo htmlspecialchars($comment['commenter_photo']); ?>" 
                                                 alt="Commenter" class="rounded-circle" 
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <?php echo strtoupper(substr($comment['commenter_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="bg-light rounded p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($comment['commenter_name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo formatDateTime($comment['created_at']); ?>
                                                </small>
                                            </div>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-link btn-sm text-muted p-0 me-3" 
                                                    onclick="likeComment(<?php echo $comment['id']; ?>)">
                                                <i class="bi bi-heart"></i> Like
                                            </button>
                                            
                                            <?php if ($comment['user_id'] == $user['id'] || $user['role'] == 'school_admin'): ?>
                                                <button type="button" class="btn btn-link btn-sm text-danger p-0" 
                                                        onclick="deleteComment(<?php echo $comment['id']; ?>)">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Related Posts -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-collection"></i> Related Posts
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted">
                            <i class="bi bi-megaphone"></i>
                            <p class="mt-2">Related posts functionality would be implemented here</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-activity"></i> Recent Activity
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted">
                            <i class="bi bi-graph-up"></i>
                            <p class="mt-2">Recent activity feed would be implemented here</p>
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
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        function sharePost() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($post['title']); ?>',
                    text: 'Check out this post from SchoolLink Africa',
                    url: window.location.href
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    showSuccessToast('Post link copied to clipboard!');
                }).catch(() => {
                    showErrorToast('Unable to copy link. Please copy manually from the address bar.');
                });
            }
        }

        function editPost(postId) {
            showInfoToast('Post editing functionality would be implemented here');
        }

        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                showWarningToast('Post deletion functionality would be implemented here');
            }
        }

        function likeComment(commentId) {
            showSuccessToast('Comment liking functionality would be implemented here');
        }

        function deleteComment(commentId) {
            if (confirm('Are you sure you want to delete this comment?')) {
                showWarningToast('Comment deletion functionality would be implemented here');
            }
        }

        // Auto-expand textarea
        document.querySelector('textarea[name="comment_content"]').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Smooth scroll to comments when coming from external links
        if (window.location.hash === '#comments') {
            document.querySelector('.card:nth-of-type(2)').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }
    </script>
</body>
</html>