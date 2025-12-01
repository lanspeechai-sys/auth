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

// Handle event operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        if (isset($_POST['ajax'])) {
            sendJSONResponse($response);
            exit;
        }
    }
    
    try {
        if (isset($_POST['action'])) {
            $event_id = intval($_POST['event_id'] ?? 0);
            
            switch ($_POST['action']) {
                case 'delete_event':
                    $stmt = $db->prepare("DELETE FROM events WHERE id = ? AND school_id = ?");
                    $stmt->execute([$event_id, $user['school_id']]);
                    $response['success'] = true;
                    $response['message'] = 'Event deleted successfully';
                    break;
                    
                case 'toggle_status':
                    $new_status = $_POST['status'] === 'active' ? 'cancelled' : 'active';
                    $stmt = $db->prepare("UPDATE events SET status = ? WHERE id = ? AND school_id = ?");
                    $stmt->execute([$new_status, $event_id, $user['school_id']]);
                    $response['success'] = true;
                    $response['message'] = 'Event status updated successfully';
                    break;
            }
            
            if (isset($_POST['ajax'])) {
                sendJSONResponse($response);
                exit;
            }
        } else {
            // Create or update event
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $event_type = $_POST['event_type'] ?? 'general';
            $event_date = $_POST['event_date'] ?? '';
            $event_time = $_POST['event_time'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $max_attendees = intval($_POST['max_attendees'] ?? 0);
            $registration_required = isset($_POST['registration_required']) ? 1 : 0;
            $event_id = intval($_POST['event_id'] ?? 0);
            
            if (empty($title) || empty($description) || empty($event_date)) {
                setFlashMessage('Please fill in all required fields', 'error');
            } else {
                $event_datetime = $event_date . ($event_time ? ' ' . $event_time : ' 00:00:00');
                
                if ($event_id > 0) {
                    // Update existing event
                    $stmt = $db->prepare("
                        UPDATE events SET 
                        title = ?, description = ?, event_type = ?, event_date = ?, 
                        location = ?, max_attendees = ?, registration_required = ?
                        WHERE id = ? AND school_id = ?
                    ");
                    $stmt->execute([
                        $title, $description, $event_type, $event_datetime, 
                        $location, $max_attendees ?: null, $registration_required,
                        $event_id, $user['school_id']
                    ]);
                    setFlashMessage('Event updated successfully', 'success');
                } else {
                    // Create new event
                    $stmt = $db->prepare("
                        INSERT INTO events (school_id, title, description, event_type, event_date, 
                                          location, max_attendees, registration_required, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $user['school_id'], $title, $description, $event_type, $event_datetime,
                        $location, $max_attendees ?: null, $registration_required, $user['id']
                    ]);
                    setFlashMessage('Event created successfully', 'success');
                }
                
                redirect('events.php');
            }
        }
    } catch (PDOException $e) {
        error_log("Event management error: " . $e->getMessage());
        $error_message = 'Database error occurred';
        if (isset($_POST['ajax'])) {
            $response['message'] = $error_message;
            sendJSONResponse($response);
            exit;
        }
        setFlashMessage($error_message, 'error');
    }
}

// Get events with pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_conditions = ["e.school_id = ?"];
$params = [$user['school_id']];

if ($filter === 'upcoming') {
    $where_conditions[] = "e.event_date >= NOW()";
} elseif ($filter === 'past') {
    $where_conditions[] = "e.event_date < NOW()";
} elseif ($filter === 'cancelled') {
    $where_conditions[] = "e.status = 'cancelled'";
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
            SELECT e.*, u.name as creator_name,
                   (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id = e.id AND er.status = 'attending') as attendee_count,
                   'event' as source_type, e.id as source_id
            FROM events e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE {$where_clause}
        ";
        
        $stmt = $db->prepare($events_query);
        $stmt->execute($params);
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
            return strtotime($b['event_date']) - strtotime($a['event_date']);
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

// Get event for editing if specified
$editing_event = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    try {
        $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND school_id = ?");
        $stmt->execute([$edit_id, $user['school_id']]);
        $editing_event = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching event for editing: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                            <a class="nav-link text-white-50" href="posts.php">
                                <i class="bi bi-megaphone"></i> Posts & Updates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="events.php">
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
                        <i class="bi bi-calendar-event"></i> Event Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal">
                                <i class="bi bi-plus-circle"></i> Create Event
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <?php echo getFlashMessage(); ?>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select name="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                                    <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                                    <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <?php if (!empty($search) || $filter !== 'all'): ?>
                                    <a href="events.php" class="btn btn-outline-secondary w-100">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Showing <?php echo count($events); ?> of <?php echo $total_events; ?> events
                        </span>
                    </div>
                </div>

                <!-- Events List -->
                <div class="row">
                    <?php if (empty($events)): ?>
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-body text-center py-5">
                                    <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 text-muted">No Events Found</h5>
                                    <p class="text-muted">
                                        <?php if (!empty($search) || $filter !== 'all'): ?>
                                            No events match your search criteria. Try adjusting your filters.
                                        <?php else: ?>
                                            Start creating events to engage with your alumni community.
                                        <?php endif; ?>
                                    </p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal">
                                        <i class="bi bi-plus-circle"></i> Create Your First Event
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow h-100" id="event-<?php echo $event['id']; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php
                                                $event_types = [
                                                    'reunion' => 'Reunion',
                                                    'networking' => 'Networking',
                                                    'career_fair' => 'Career Fair',
                                                    'webinar' => 'Webinar',
                                                    'fundraising' => 'Fundraising',
                                                    'general' => 'General'
                                                ];
                                                echo $event_types[$event['event_type']] ?? 'General';
                                                ?>
                                                <?php if ($event['source_type'] === 'post'): ?>
                                                    <span class="badge bg-info ms-1">From Post</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($event['source_type'] === 'event'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="events.php?edit=<?php echo $event['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit Event
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item" onclick="toggleEventStatus(<?php echo $event['id']; ?>, '<?php echo $event['status']; ?>')">
                                                            <?php if ($event['status'] === 'active'): ?>
                                                                <i class="bi bi-pause-circle"></i> Cancel Event
                                                            <?php else: ?>
                                                                <i class="bi bi-play-circle"></i> Reactivate Event
                                                            <?php endif; ?>
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <button class="dropdown-item text-danger" onclick="deleteEvent(<?php echo $event['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Delete Event
                                                        </button>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item" href="posts.php?edit=<?php echo $event['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit Post
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="posts.php">
                                                            <i class="bi bi-arrow-right"></i> Manage in Posts
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars(truncateText($event['description'], 150))); ?></p>
                                        
                                        <div class="row text-muted small mb-3">
                                            <div class="col-6">
                                                <i class="bi bi-calendar"></i> 
                                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                            </div>
                                            <div class="col-6">
                                                <i class="bi bi-clock"></i> 
                                                <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                                            </div>
                                            <?php if ($event['location']): ?>
                                                <div class="col-12 mt-1">
                                                    <i class="bi bi-geo-alt"></i> 
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($event['status'] === 'cancelled'): ?>
                                                    <span class="badge bg-danger">Cancelled</span>
                                                <?php elseif (strtotime($event['event_date']) < time()): ?>
                                                    <span class="badge bg-secondary">Past Event</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Upcoming</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($event['registration_required']): ?>
                                                    <span class="badge bg-info">RSVP Required</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="bi bi-people"></i> <?php echo $event['attendee_count']; ?> attending
                                                <?php if ($event['max_attendees']): ?>
                                                    / <?php echo $event['max_attendees']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer text-muted small">
                                        Created by <?php echo htmlspecialchars($event['creator_name']); ?> • 
                                        <?php echo timeAgo($event['created_at']); ?>
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
            </main>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">
                        <?php echo $editing_event ? 'Edit Event' : 'Create New Event'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php if ($editing_event): ?>
                            <input type="hidden" name="event_id" value="<?php echo $editing_event['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="title" class="form-label">Event Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($editing_event['title'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="event_type" class="form-label">Event Type</label>
                                <select class="form-select" id="event_type" name="event_type">
                                    <option value="general" <?php echo ($editing_event['event_type'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                                    <option value="reunion" <?php echo ($editing_event['event_type'] ?? '') === 'reunion' ? 'selected' : ''; ?>>Reunion</option>
                                    <option value="networking" <?php echo ($editing_event['event_type'] ?? '') === 'networking' ? 'selected' : ''; ?>>Networking</option>
                                    <option value="career_fair" <?php echo ($editing_event['event_type'] ?? '') === 'career_fair' ? 'selected' : ''; ?>>Career Fair</option>
                                    <option value="webinar" <?php echo ($editing_event['event_type'] ?? '') === 'webinar' ? 'selected' : ''; ?>>Webinar</option>
                                    <option value="fundraising" <?php echo ($editing_event['event_type'] ?? '') === 'fundraising' ? 'selected' : ''; ?>>Fundraising</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($editing_event['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_date" class="form-label">Event Date *</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                       value="<?php echo $editing_event ? date('Y-m-d', strtotime($editing_event['event_date'])) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="event_time" class="form-label">Event Time</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" 
                                       value="<?php echo $editing_event ? date('H:i', strtotime($editing_event['event_date'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($editing_event['location'] ?? ''); ?>" 
                                   placeholder="Event venue or online meeting link">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="max_attendees" class="form-label">Max Attendees</label>
                                <input type="number" class="form-control" id="max_attendees" name="max_attendees" 
                                       value="<?php echo $editing_event['max_attendees'] ?? ''; ?>" 
                                       placeholder="Leave empty for unlimited">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="registration_required" 
                                           name="registration_required" value="1"
                                           <?php echo ($editing_event['registration_required'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="registration_required">
                                        Require RSVP/Registration
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> <?php echo $editing_event ? 'Update Event' : 'Create Event'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        <?php if ($editing_event): ?>
        // Show modal if editing
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('eventModal')).show();
        });
        <?php endif; ?>

        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                performEventAction('delete_event', eventId);
            }
        }

        function toggleEventStatus(eventId, currentStatus) {
            const action = currentStatus === 'active' ? 'cancel' : 'reactivate';
            if (confirm(`Are you sure you want to ${action} this event?`)) {
                performEventAction('toggle_status', eventId, currentStatus);
            }
        }

        function performEventAction(action, eventId, status = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('event_id', eventId);
            formData.append('ajax', '1');
            if (status) {
                formData.append('status', status);
            }

            fetch('events.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    
                    if (action === 'delete_event') {
                        const eventCard = document.getElementById('event-' + eventId);
                        if (eventCard) {
                            eventCard.style.transition = 'opacity 0.3s';
                            eventCard.style.opacity = '0';
                            setTimeout(() => eventCard.remove(), 300);
                        }
                    } else {
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
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Set minimum date to today
        document.getElementById('event_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>