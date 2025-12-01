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

$action = $_GET['action'] ?? 'list';
$post_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'create' || $action == 'edit') {
        // CSRF Protection
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid security token. Please try again.';
        } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = sanitizeInput($_POST['content'] ?? '');
        $post_type = sanitizeInput($_POST['post_type'] ?? 'update');
        $event_date = $_POST['event_date'] ?? null;
        
        // Validation
        if (empty($title) || empty($content)) {
            $error = 'Please fill in all required fields';
        } elseif ($post_type == 'event' && empty($event_date)) {
            $error = 'Event date is required for event posts';
        } elseif ($post_type == 'event' && strtotime($event_date) < time()) {
            $error = 'Event date must be in the future';
        } else {
            try {
                if ($action == 'create') {
                    // Create new post
                    $stmt = $db->prepare("
                        INSERT INTO posts (school_id, author_id, title, content, post_type, event_date) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user['school_id'], 
                        $user['id'], 
                        $title, 
                        $content, 
                        $post_type,
                        $event_date ?: null
                    ]);
                    $success = 'Post created successfully!';
                    $action = 'list';
                } elseif ($action == 'edit' && $post_id) {
                    // Update existing post
                    $stmt = $db->prepare("
                        UPDATE posts 
                        SET title = ?, content = ?, post_type = ?, event_date = ?
                        WHERE id = ? AND school_id = ?
                    ");
                    $stmt->execute([
                        $title, 
                        $content, 
                        $post_type,
                        $event_date ?: null,
                        $post_id, 
                        $user['school_id']
                    ]);
                    $success = 'Post updated successfully!';
                    $action = 'list';
                }
            } catch (PDOException $e) {
                error_log("Post management error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}   // Close CSRF protection block
}

// Handle post deletion
if ($action == 'delete' && $post_id && $_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $db->prepare("DELETE FROM posts WHERE id = ? AND school_id = ?");
        $stmt->execute([$post_id, $user['school_id']]);
        $success = 'Post deleted successfully!';
        $action = 'list';
    } catch (PDOException $e) {
        error_log("Post deletion error: " . $e->getMessage());
        $error = 'Failed to delete post.';
    }
}

// Get existing post for editing
$existing_post = null;
if ($action == 'edit' && $post_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM posts WHERE id = ? AND school_id = ?");
        $stmt->execute([$post_id, $user['school_id']]);
        $existing_post = $stmt->fetch();
        
        if (!$existing_post) {
            $error = 'Post not found';
            $action = 'list';
        }
    } catch (PDOException $e) {
        error_log("Post fetch error: " . $e->getMessage());
        $error = 'Failed to load post';
        $action = 'list';
    }
}

// Get all posts for this school
$posts = [];
if ($action == 'list') {
    try {
        $stmt = $db->prepare("
            SELECT p.*, u.name as author_name,
                   (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
            FROM posts p 
            JOIN users u ON p.author_id = u.id 
            WHERE p.school_id = ? 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user['school_id']]);
        $posts = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Posts fetch error: " . $e->getMessage());
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts & Updates - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                            <a class="nav-link text-white-50" href="members.php">
                                <i class="bi bi-people"></i> Members
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="posts.php">
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
                        <i class="bi bi-megaphone"></i> 
                        <?php 
                        echo $action == 'create' ? 'Create New Post' : 
                             ($action == 'edit' ? 'Edit Post' : 'Posts & Updates Management');
                        ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($action == 'list'): ?>
                            <div class="btn-group me-2">
                                <a href="posts.php?action=create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Create New Post
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        <?php else: ?>
                            <a href="posts.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Posts
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($action == 'create' || $action == 'edit'): ?>
                    <!-- Create/Edit Post Form -->
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php echo $action == 'create' ? 'Create New Post' : 'Edit Post'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="title" class="form-label">Post Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($existing_post['title'] ?? ''); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="post_type" class="form-label">Post Type *</label>
                                        <select class="form-select" id="post_type" name="post_type" required>
                                            <option value="update" <?php echo ($existing_post['post_type'] ?? '') == 'update' ? 'selected' : ''; ?>>
                                                General Update
                                            </option>
                                            <option value="event" <?php echo ($existing_post['post_type'] ?? '') == 'event' ? 'selected' : ''; ?>>
                                                Event
                                            </option>
                                            <option value="opportunity" <?php echo ($existing_post['post_type'] ?? '') == 'opportunity' ? 'selected' : ''; ?>>
                                                Opportunity
                                            </option>
                                        </select>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle"></i> 
                                            Posts marked as "Opportunity" will appear in the student Opportunities section.
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3" id="event_date_section" style="display: none;">
                                    <label for="event_date" class="form-label">Event Date</label>
                                    <input type="datetime-local" class="form-control" id="event_date" name="event_date" 
                                           value="<?php echo $existing_post['event_date'] ? date('Y-m-d\TH:i', strtotime($existing_post['event_date'])) : ''; ?>">
                                    <div class="form-text">Required for event posts</div>
                                </div>

                                <div class="mb-4">
                                    <label for="content" class="form-label">Post Content *</label>
                                    <textarea class="form-control" id="content" name="content" rows="8" 
                                              placeholder="Write your post content here..." required><?php echo htmlspecialchars($existing_post['content'] ?? ''); ?></textarea>
                                    <div class="form-text">You can use line breaks to format your content</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> 
                                        <?php echo $action == 'create' ? 'Create Post' : 'Update Post'; ?>
                                    </button>
                                    <a href="posts.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Posts List -->
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Posts (<?php echo count($posts); ?>)</h5>
                            <div class="btn-group btn-group-sm">
                                <input type="text" class="form-control form-control-sm" placeholder="Search posts..." id="searchInput">
                                <select class="form-select form-select-sm" id="filterType">
                                    <option value="">All Types</option>
                                    <option value="update">Updates</option>
                                    <option value="event">Events</option>
                                    <option value="opportunity">Opportunities</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($posts)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-megaphone" style="font-size: 4rem;"></i>
                                    <p class="mt-3">No posts created yet</p>
                                    <a href="posts.php?action=create" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create Your First Post
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="row" id="postsContainer">
                                    <?php foreach ($posts as $post): ?>
                                        <div class="col-12 mb-4 post-item" 
                                             data-type="<?php echo $post['post_type']; ?>"
                                             data-title="<?php echo strtolower(htmlspecialchars($post['title'])); ?>"
                                             data-content="<?php echo strtolower(htmlspecialchars($post['content'])); ?>">
                                            <div class="card border-start border-4 <?php 
                                                echo $post['post_type'] == 'event' ? 'border-success' : 
                                                    ($post['post_type'] == 'opportunity' ? 'border-warning' : 'border-primary'); 
                                            ?>">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div class="flex-grow-1">
                                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($post['title']); ?></h5>
                                                            <div class="d-flex gap-2 align-items-center">
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
                                                                    <i class="bi bi-<?php echo $icon; ?>"></i> 
                                                                    <?php echo ucfirst($post['post_type']); ?>
                                                                </span>
                                                                
                                                                <?php if ($post['event_date']): ?>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-calendar"></i>
                                                                        <?php echo formatDate($post['event_date'], 'M d, Y H:i'); ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                                    type="button" data-bs-toggle="dropdown">
                                                                Actions
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item" href="posts.php?action=edit&id=<?php echo $post['id']; ?>">
                                                                        <i class="bi bi-pencil"></i> Edit
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger" href="#" 
                                                                       onclick="deletePost(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title']); ?>')">
                                                                        <i class="bi bi-trash"></i> Delete
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    
                                                    <p class="card-text"><?php echo nl2br(htmlspecialchars(truncateText($post['content'], 200))); ?></p>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center text-muted small">
                                                        <div>
                                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($post['author_name']); ?>
                                                            • <?php echo timeAgo($post['created_at']); ?>
                                                        </div>
                                                        <div>
                                                            <i class="bi bi-chat-dots"></i> <?php echo $post['comment_count']; ?> comments
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this post?</p>
                    <p class="fw-bold" id="postTitle"></p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="deleteForm">
                        <button type="submit" class="btn btn-danger">Delete Post</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        // Toggle event date field based on post type
        document.getElementById('post_type').addEventListener('change', function() {
            const eventDateSection = document.getElementById('event_date_section');
            const eventDateInput = document.getElementById('event_date');
            
            if (this.value === 'event') {
                eventDateSection.style.display = 'block';
                eventDateInput.required = true;
            } else {
                eventDateSection.style.display = 'none';
                eventDateInput.required = false;
                eventDateInput.value = '';
            }
        });

        // Initialize event date field visibility
        document.addEventListener('DOMContentLoaded', function() {
            const postType = document.getElementById('post_type').value;
            if (postType === 'event') {
                document.getElementById('event_date_section').style.display = 'block';
                document.getElementById('event_date').required = true;
            }
        });

        // Search and filter functionality
        const searchInput = document.getElementById('searchInput');
        const filterType = document.getElementById('filterType');
        
        if (searchInput && filterType) {
            function filterPosts() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedType = filterType.value;
                const posts = document.querySelectorAll('.post-item');
                
                posts.forEach(post => {
                    const title = post.dataset.title;
                    const content = post.dataset.content;
                    const type = post.dataset.type;
                    
                    const matchesSearch = !searchTerm || title.includes(searchTerm) || content.includes(searchTerm);
                    const matchesType = !selectedType || type === selectedType;
                    
                    if (matchesSearch && matchesType) {
                        post.style.display = 'block';
                    } else {
                        post.style.display = 'none';
                    }
                });
            }
            
            searchInput.addEventListener('input', filterPosts);
            filterType.addEventListener('change', filterPosts);
        }

        // Delete post function
        function deletePost(postId, postTitle) {
            document.getElementById('postTitle').textContent = postTitle;
            document.getElementById('deleteForm').action = `posts.php?action=delete&id=${postId}`;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>