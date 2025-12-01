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

// Handle RSVP actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    try {
        if ($_POST['action'] === 'rsvp') {
            $event_id = intval($_POST['event_id'] ?? 0);
            $rsvp_status = $_POST['rsvp_status'] ?? 'attending';
            $additional_info = trim($_POST['additional_info'] ?? '');
            
            // Check if event exists and is active
            $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND school_id = ? AND status = 'active'");
            $stmt->execute([$event_id, $user['school_id']]);
            $event = $stmt->fetch();
            
            if (!$event) {
                $response['message'] = 'Event not found or not available for RSVP';
            } else {
                // Check if user already has an RSVP
                $stmt = $db->prepare("SELECT id FROM event_rsvps WHERE event_id = ? AND user_id = ?");
                $stmt->execute([$event_id, $user['id']]);
                
                if ($stmt->fetch()) {
                    // Update existing RSVP
                    $stmt = $db->prepare("UPDATE event_rsvps SET status = ?, additional_info = ? WHERE event_id = ? AND user_id = ?");
                    $stmt->execute([$rsvp_status, $additional_info, $event_id, $user['id']]);
                    $response['message'] = 'RSVP updated successfully';
                } else {
                    // Create new RSVP
                    $stmt = $db->prepare("INSERT INTO event_rsvps (event_id, user_id, status, additional_info) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$event_id, $user['id'], $rsvp_status, $additional_info]);
                    $response['message'] = 'RSVP submitted successfully';
                }
                
                $response['success'] = true;
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error occurred';
        error_log("RSVP error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get events with filtering
$filter = $_GET['filter'] ?? 'upcoming';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where_conditions = ["e.school_id = ?", "e.status = 'active'"];
$params = [$user['school_id']];

if ($filter === 'upcoming') {
    $where_conditions[] = "e.event_date >= NOW()";
} elseif ($filter === 'past') {
    $where_conditions[] = "e.event_date < NOW()";
} elseif ($filter === 'my_events') {
    $where_conditions[] = "er.user_id = ? AND er.status = 'attending'";
    $params[] = $user['id'];
}

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get events
$events = [];
$total_events = 0;

if ($db) {
    try {
        // Query 1: Get dedicated events from events table
        $events_query = "
            SELECT DISTINCT e.*, u.name as creator_name,
                   (SELECT COUNT(*) FROM event_rsvps er2 WHERE er2.event_id = e.id AND er2.status = 'attending') as attendee_count,
                   (SELECT status FROM event_rsvps er3 WHERE er3.event_id = e.id AND er3.user_id = ?) as user_rsvp_status,
                   'event' as source_type, e.id as source_id
            FROM events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN event_rsvps er ON e.id = er.event_id
            WHERE {$where_clause}
        ";
        
        $stmt = $db->prepare($events_query);
        $stmt->execute(array_merge([$user['id']], $params));
        $events_from_table = $stmt->fetchAll();
        
        // Query 2: Get posts marked as events
        $posts_query = "
            SELECT 
                p.id,
                p.title,
                p.content as description,
                'general' as event_type,
                p.event_date,
                '' as location,
                NULL as max_attendees,
                0 as registration_required,
                'active' as status,
                p.author_id as created_by,
                p.created_at,
                p.created_at as updated_at,
                u.name as creator_name,
                0 as attendee_count,
                NULL as user_rsvp_status,
                'post' as source_type,
                p.id as source_id
            FROM posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.school_id = ? AND p.post_type = 'event'
        ";
        
        $stmt = $db->prepare($posts_query);
        $stmt->execute([$user['school_id']]);
        $posts_as_events = $stmt->fetchAll();
        
        // Combine both arrays and sort by event_date
        $combined_events = array_merge($events_from_table, $posts_as_events);
        usort($combined_events, function($a, $b) {
            return strtotime($a['event_date']) - strtotime($b['event_date']);
        });
        
        // Pagination on combined results
        $total_events = count($combined_events);
        $events = array_slice($combined_events, $offset, $per_page);
        
    } catch (PDOException $e) {
        error_log("Error fetching events: " . $e->getMessage());
        $events = [];
        $total_events = 0;
    }
}

$total_pages = ceil($total_events / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                        <a class="nav-link" href="posts.php">
                            <i class="bi bi-megaphone"></i> Posts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="events.php">
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
                        <i class="bi bi-calendar-event"></i> School Events
                    </h1>
                    <p class="lead text-muted">
                        Discover and join events happening at <?php echo htmlspecialchars($school['name']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="filter" class="form-label small">Filter Events</label>
                                <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                                    <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                                    <option value="my_events" <?php echo $filter === 'my_events' ? 'selected' : ''; ?>>My Events</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label for="search" class="form-label small">Search Events</label>
                                <div class="input-group">
                                    <input type="text" name="search" id="search" class="form-control" 
                                           placeholder="Search by title, description, or location..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($search) || $filter !== 'upcoming'): ?>
                                    <a href="events.php" class="btn btn-outline-secondary w-100">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="row">
            <?php if (empty($events)): ?>
                <div class="col-12">
                    <div class="card shadow-sm text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No Events Found</h5>
                            <p class="text-muted">
                                <?php if (!empty($search) || $filter !== 'upcoming'): ?>
                                    No events match your search criteria. Try adjusting your filters.
                                <?php else: ?>
                                    There are no upcoming events at the moment. Check back soon!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card shadow-sm h-100 event-card">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div>
                                    <?php
                                    $event_types = [
                                        'reunion' => ['icon' => 'bi-people', 'color' => 'primary'],
                                        'networking' => ['icon' => 'bi-diagram-3', 'color' => 'success'],
                                        'career_fair' => ['icon' => 'bi-briefcase', 'color' => 'warning'],
                                        'webinar' => ['icon' => 'bi-camera-video', 'color' => 'info'],
                                        'fundraising' => ['icon' => 'bi-heart', 'color' => 'danger'],
                                        'general' => ['icon' => 'bi-calendar-event', 'color' => 'secondary']
                                    ];
                                    
                                    $type_info = $event_types[$event['event_type']] ?? $event_types['general'];
                                    ?>
                                    <span class="badge bg-<?php echo $type_info['color']; ?>">
                                        <i class="<?php echo $type_info['icon']; ?>"></i> 
                                        <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                                    </span>
                                    <?php if ($event['source_type'] === 'post'): ?>
                                        <span class="badge bg-info ms-1">Post</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php if (strtotime($event['event_date']) < time()): ?>
                                        <span class="badge bg-secondary">Past Event</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Upcoming</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="card-text text-muted">
                                    <?php echo nl2br(htmlspecialchars(truncateText($event['description'], 120))); ?>
                                </p>
                                
                                <div class="event-details mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-calendar text-primary me-2"></i>
                                        <span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-clock text-primary me-2"></i>
                                        <span><?php echo date('g:i A', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <?php if ($event['location']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-geo-alt text-primary me-2"></i>
                                            <span class="small"><?php echo htmlspecialchars(truncateText($event['location'], 40)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-people"></i> <?php echo $event['attendee_count']; ?> attending
                                        <?php if ($event['max_attendees']): ?>
                                            / <?php echo $event['max_attendees']; ?>
                                        <?php endif; ?>
                                    </small>
                                    <small class="text-muted">
                                        by <?php echo htmlspecialchars($event['creator_name']); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <?php if (strtotime($event['event_date']) >= time()): ?>
                                    <?php if ($event['user_rsvp_status']): ?>
                                        <?php if ($event['source_type'] === 'event'): ?>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check"></i> 
                                                    <?php echo ucfirst($event['user_rsvp_status']); ?>
                                                </span>
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rsvpModal" 
                                                        data-event-id="<?php echo $event['id']; ?>"
                                                        data-event-title="<?php echo htmlspecialchars($event['title']); ?>"
                                                        data-current-rsvp="<?php echo $event['user_rsvp_status']; ?>">
                                                    Change RSVP
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small">
                                                <i class="bi bi-info-circle"></i> This is a post marked as an event
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($event['source_type'] === 'event'): ?>
                                            <?php if ($event['registration_required']): ?>
                                                <button class="btn btn-primary w-100" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rsvpModal" 
                                                        data-event-id="<?php echo $event['id']; ?>"
                                                        data-event-title="<?php echo htmlspecialchars($event['title']); ?>"
                                                        data-current-rsvp="">
                                                    <i class="bi bi-calendar-plus"></i> RSVP Now
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-primary w-100" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rsvpModal" 
                                                        data-event-id="<?php echo $event['id']; ?>"
                                                        data-event-title="<?php echo htmlspecialchars($event['title']); ?>"
                                                        data-current-rsvp="">
                                                    <i class="bi bi-info-circle"></i> View Details
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-muted small text-center">
                                                <i class="bi bi-megaphone"></i> Event Post - No RSVP Required
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="bi bi-calendar-x"></i> Event Ended
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="col-12">
                        <nav aria-label="Events pagination">
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
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RSVP Modal -->
    <div class="modal fade" id="rsvpModal" tabindex="-1" aria-labelledby="rsvpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rsvpModalLabel">Event RSVP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rsvpForm">
                    <div class="modal-body">
                        <input type="hidden" id="event_id" name="event_id">
                        
                        <div class="mb-3">
                            <h6 id="event_title" class="text-primary"></h6>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Response *</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rsvp_status" 
                                           id="attending" value="attending" checked>
                                    <label class="form-check-label text-success" for="attending">
                                        <i class="bi bi-check-circle"></i> I will attend
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rsvp_status" 
                                           id="maybe" value="maybe">
                                    <label class="form-check-label text-warning" for="maybe">
                                        <i class="bi bi-question-circle"></i> Maybe
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rsvp_status" 
                                           id="not_attending" value="not_attending">
                                    <label class="form-check-label text-danger" for="not_attending">
                                        <i class="bi bi-x-circle"></i> Cannot attend
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="additional_info" class="form-label">Additional Information (Optional)</label>
                            <textarea class="form-control" id="additional_info" name="additional_info" 
                                      rows="3" placeholder="Any additional comments or questions..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> Submit RSVP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // RSVP Modal handling
        const rsvpModal = document.getElementById('rsvpModal');
        rsvpModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const eventTitle = button.getAttribute('data-event-title');
            const currentRsvp = button.getAttribute('data-current-rsvp');
            
            document.getElementById('event_id').value = eventId;
            document.getElementById('event_title').textContent = eventTitle;
            
            // Set current RSVP status if exists
            if (currentRsvp) {
                const radioButton = document.querySelector(`input[name="rsvp_status"][value="${currentRsvp}"]`);
                if (radioButton) {
                    radioButton.checked = true;
                }
            }
        });

        // RSVP form submission
        document.getElementById('rsvpForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'rsvp');
            
            fetch('events.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(rsvpModal).hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while processing your RSVP.');
            });
        });

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