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

// Handle post interactions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    try {
        switch ($_POST['action']) {
            case 'like_post':
                $post_id = intval($_POST['post_id'] ?? 0);
                
                // Check if already liked
                $stmt = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$post_id, $user['id']]);
                
                if ($stmt->fetch()) {
                    // Unlike
                    $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
                    $stmt->execute([$post_id, $user['id']]);
                    $response['action'] = 'unliked';
                } else {
                    // Like
                    $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$post_id, $user['id']]);
                    $response['action'] = 'liked';
                }
                
                // Get updated like count
                $stmt = $db->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
                $stmt->execute([$post_id]);
                $response['like_count'] = $stmt->fetchColumn();
                $response['success'] = true;
                break;
                
            case 'add_comment':
                $post_id = intval($_POST['post_id'] ?? 0);
                $content = trim($_POST['content'] ?? '');
                
                if (empty($content)) {
                    $response['message'] = 'Comment cannot be empty';
                } else {
                    $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
                    $stmt->execute([$post_id, $user['id'], $content]);
                    $response['success'] = true;
                    $response['message'] = 'Comment added successfully';
                }
                break;
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error occurred';
        error_log("Post interaction error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get posts with pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_conditions = ["p.school_id = ?"];
$params = [$user['school_id']];

if ($filter === 'events') {
    $where_conditions[] = "p.post_type = 'event'";
} elseif ($filter === 'opportunities') {
    $where_conditions[] = "p.post_type = 'opportunity'";
} elseif ($filter === 'updates') {
    $where_conditions[] = "p.post_type = 'update'";
}

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get posts
$posts = [];
$total_posts = 0;

if ($db) {
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM posts p WHERE {$where_clause}";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_posts = $stmt->fetchColumn();
        
        // Get posts for current page
        $query = "
            SELECT p.*, u.name as author_name, u.profile_photo as author_photo,
                   (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count,
                   (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked
            FROM posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE {$where_clause}
            ORDER BY p.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute(array_merge([$user['id']], $params));
        $posts = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching posts: " . $e->getMessage());
    }
}

$total_pages = ceil($total_posts / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Feed - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($school['name']); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="directory.php">
                            <i class="bi bi-people"></i> Alumni Directory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="posts.php">
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
    <div class="container my-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <h1 class="display-6 fw-bold text-primary">
                        <i class="bi bi-megaphone"></i> School Feed
                    </h1>
                    <p class="lead text-muted">
                        Stay updated with the latest news, events, and opportunities from <?php echo htmlspecialchars($school['name']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="filter" class="form-label small">Filter by Type</label>
                                <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Posts</option>
                                    <option value="updates" <?php echo $filter === 'updates' ? 'selected' : ''; ?>>Updates</option>
                                    <option value="events" <?php echo $filter === 'events' ? 'selected' : ''; ?>>Events</option>
                                    <option value="opportunities" <?php echo $filter === 'opportunities' ? 'selected' : ''; ?>>Opportunities</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label small">Search Posts</label>
                                <div class="input-group">
                                    <input type="text" name="search" id="search" class="form-control" 
                                           placeholder="Search by title or content..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <a href="posts.php" class="btn btn-outline-secondary w-100">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posts Feed -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php if (empty($posts)): ?>
                    <div class="card shadow-sm text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-chat-square-text text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No Posts Found</h5>
                            <p class="text-muted">
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    No posts match your search criteria. Try adjusting your filters.
                                <?php else: ?>
                                    Be the first to share something with your school community!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="card shadow-sm mb-4" id="post-<?php echo $post['id']; ?>">
                            <!-- Post Header -->
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <?php if ($post['author_photo']): ?>
                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($post['author_photo']); ?>" 
                                             alt="Author Photo" class="rounded-circle me-3" 
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                             style="width: 40px; height: 40px;">
                                            <i class="bi bi-person text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($post['author_name']); ?></h6>
                                        <small class="text-muted"><?php echo timeAgo($post['created_at']); ?></small>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $post_type_badges = [
                                        'update' => 'bg-primary',
                                        'event' => 'bg-success',
                                        'opportunity' => 'bg-warning'
                                    ];
                                    $post_type_names = [
                                        'update' => 'Update',
                                        'event' => 'Event',
                                        'opportunity' => 'Opportunity'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $post_type_badges[$post['post_type']] ?? 'bg-secondary'; ?>">
                                        <?php echo $post_type_names[$post['post_type']] ?? 'Post'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Post Content -->
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                
                                <?php if ($post['event_date'] && $post['post_type'] === 'event'): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-calendar-event"></i> 
                                        <strong>Event Date:</strong> <?php echo date('M d, Y g:i A', strtotime($post['event_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Post Actions -->
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn <?php echo $post['user_liked'] ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm like-btn" 
                                                data-post-id="<?php echo $post['id']; ?>">
                                            <i class="bi bi-heart<?php echo $post['user_liked'] ? '-fill' : ''; ?>"></i>
                                            <span class="like-count"><?php echo $post['like_count']; ?></span>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                data-bs-toggle="collapse" data-bs-target="#comments-<?php echo $post['id']; ?>">
                                            <i class="bi bi-chat"></i> <?php echo $post['comment_count']; ?> Comments
                                        </button>
                                    </div>
                                </div>

                                <!-- Comments Section -->
                                <div class="collapse mt-3" id="comments-<?php echo $post['id']; ?>">
                                    <div class="border-top pt-3">
                                        <!-- Add Comment Form -->
                                        <form class="comment-form mb-3" data-post-id="<?php echo $post['id']; ?>">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="content" 
                                                       placeholder="Write a comment..." required>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-send"></i>
                                                </button>
                                            </div>
                                        </form>

                                        <!-- Existing Comments -->
                                        <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                                            <?php
                                            // Get comments for this post
                                            try {
                                                $stmt = $db->prepare("
                                                    SELECT c.*, u.name as commenter_name, u.profile_photo as commenter_photo
                                                    FROM comments c
                                                    LEFT JOIN users u ON c.user_id = u.id
                                                    WHERE c.post_id = ?
                                                    ORDER BY c.created_at ASC
                                                ");
                                                $stmt->execute([$post['id']]);
                                                $comments = $stmt->fetchAll();
                                                
                                                foreach ($comments as $comment):
                                            ?>
                                                <div class="d-flex mb-2">
                                                    <?php if ($comment['commenter_photo']): ?>
                                                        <img src="../uploads/profiles/<?php echo htmlspecialchars($comment['commenter_photo']); ?>" 
                                                             alt="Commenter Photo" class="rounded-circle me-2" 
                                                             style="width: 30px; height: 30px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                             style="width: 30px; height: 30px;">
                                                            <i class="bi bi-person text-white small"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex-grow-1">
                                                        <div class="bg-light rounded p-2">
                                                            <strong class="small"><?php echo htmlspecialchars($comment['commenter_name']); ?></strong>
                                                            <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                                        </div>
                                                        <small class="text-muted"><?php echo timeAgo($comment['created_at']); ?></small>
                                                    </div>
                                                </div>
                                            <?php 
                                                endforeach;
                                            } catch (PDOException $e) {
                                                error_log("Error fetching comments: " . $e->getMessage());
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Posts pagination">
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
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Like/Unlike functionality
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                likePost(postId, this);
            });
        });

        // Comment form submission
        document.querySelectorAll('.comment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const postId = this.dataset.postId;
                const content = this.querySelector('input[name="content"]').value.trim();
                
                if (content) {
                    addComment(postId, content, this);
                }
            });
        });

        function likePost(postId, button) {
            const formData = new FormData();
            formData.append('action', 'like_post');
            formData.append('post_id', postId);

            fetch('posts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const heartIcon = button.querySelector('i');
                    const likeCount = button.querySelector('.like-count');
                    
                    if (data.action === 'liked') {
                        button.classList.remove('btn-outline-primary');
                        button.classList.add('btn-primary');
                        heartIcon.classList.remove('bi-heart');
                        heartIcon.classList.add('bi-heart-fill');
                    } else {
                        button.classList.remove('btn-primary');
                        button.classList.add('btn-outline-primary');
                        heartIcon.classList.remove('bi-heart-fill');
                        heartIcon.classList.add('bi-heart');
                    }
                    
                    likeCount.textContent = data.like_count;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while processing your request.');
            });
        }

        function addComment(postId, content, form) {
            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('post_id', postId);
            formData.append('content', content);

            fetch('posts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    form.reset();
                    showAlert('success', data.message);
                    // Reload the page to show new comment
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while adding your comment.');
            });
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
    </script>
</body>
</html>