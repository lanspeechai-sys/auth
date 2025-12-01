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

// Handle opportunity interactions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if (!$db) {
        $response['message'] = 'Database connection failed';
        sendJSONResponse($response);
        exit;
    }
    
    try {
        if ($_POST['action'] === 'apply_interest') {
            $opportunity_id = intval($_POST['opportunity_id'] ?? 0);
            
            // Check if already expressed interest
            $stmt = $db->prepare("SELECT id FROM opportunity_interests WHERE opportunity_id = ? AND user_id = ?");
            $stmt->execute([$opportunity_id, $user['id']]);
            
            if ($stmt->fetch()) {
                $response['message'] = 'You have already expressed interest in this opportunity';
            } else {
                // Record interest
                $stmt = $db->prepare("INSERT INTO opportunity_interests (opportunity_id, user_id) VALUES (?, ?)");
                $stmt->execute([$opportunity_id, $user['id']]);
                $response['success'] = true;
                $response['message'] = 'Interest recorded successfully';
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error occurred';
        error_log("Opportunity interaction error: " . $e->getMessage());
    }
    
    sendJSONResponse($response);
    exit;
}

// Get opportunities with filtering
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$location_filter = $_GET['location'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where_conditions = ["o.school_id = ?", "o.status = 'active'"];
$params = [$user['school_id']];

if ($filter === 'internships') {
    $where_conditions[] = "o.opportunity_type = 'internship'";
} elseif ($filter === 'jobs') {
    $where_conditions[] = "(o.opportunity_type = 'full-time' OR o.opportunity_type = 'part-time')";
} elseif ($filter === 'scholarships') {
    $where_conditions[] = "o.opportunity_type = 'scholarship'";
} elseif ($filter === 'mentorship') {
    $where_conditions[] = "o.opportunity_type = 'mentorship'";
}

if (!empty($search)) {
    $where_conditions[] = "(o.title LIKE ? OR o.description LIKE ? OR o.company_name LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($location_filter)) {
    $where_conditions[] = "o.location LIKE ?";
    $params[] = "%{$location_filter}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get opportunities (from both opportunities table and posts with type='opportunity')
$opportunities = [];
$total_opportunities = 0;

if ($db) {
    try {
        // Get dedicated opportunities first
        $opportunities_from_table = [];
        $posts_as_opportunities = [];
        
        // Query 1: Get from opportunities table
        $opp_query = "
            SELECT 
                o.id,
                o.title,
                o.description,
                o.company_name,
                o.opportunity_type,
                o.location,
                o.salary_range,
                o.requirements,
                o.application_process,
                o.contact_email,
                o.deadline,
                o.created_at,
                u.name as poster_name,
                'opportunity' as source_type,
                o.id as source_id,
                (SELECT COUNT(*) FROM opportunity_interests oi WHERE oi.opportunity_id = o.id) as interest_count,
                (SELECT COUNT(*) FROM opportunity_interests oi WHERE oi.opportunity_id = o.id AND oi.user_id = ?) as user_interested
            FROM opportunities o
            LEFT JOIN users u ON o.posted_by = u.id
            WHERE {$where_clause}
        ";
        
        $stmt = $db->prepare($opp_query);
        $stmt->execute(array_merge([$user['id']], $params));
        $opportunities_from_table = $stmt->fetchAll();
        
        // Query 2: Get posts marked as opportunities
        $posts_query = "
            SELECT 
                p.id,
                p.title,
                p.content as description,
                '' as company_name,
                'full-time' as opportunity_type,
                '' as location,
                '' as salary_range,
                '' as requirements,
                '' as application_process,
                '' as contact_email,
                NULL as deadline,
                p.created_at,
                u.name as poster_name,
                'post' as source_type,
                p.id as source_id,
                0 as interest_count,
                0 as user_interested
            FROM posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.school_id = ? AND p.post_type = 'opportunity'
        ";
        
        $stmt = $db->prepare($posts_query);
        $stmt->execute([$user['school_id']]);
        $posts_as_opportunities = $stmt->fetchAll();
        
        // Combine both arrays and sort by created_at
        $opportunities = array_merge($opportunities_from_table, $posts_as_opportunities);
        
        // Sort by created_at (most recent first)
        usort($opportunities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Apply pagination
        $total_opportunities = count($opportunities);
        $opportunities = array_slice($opportunities, $offset, $per_page);
        
    } catch (PDOException $e) {
        error_log("Error fetching opportunities: " . $e->getMessage());
    }
}

$total_pages = ceil($total_opportunities / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opportunities - <?php echo htmlspecialchars($school['name']); ?></title>
    
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
                        <a class="nav-link" href="events.php">
                            <i class="bi bi-calendar-event"></i> Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="opportunities.php">
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
    <div class="container my-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <h1 class="display-6 fw-bold text-primary">
                        <i class="bi bi-briefcase"></i> Career Opportunities
                    </h1>
                    <p class="lead text-muted">
                        Explore job opportunities, internships, and career development resources
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="filter" class="form-label small">Opportunity Type</label>
                                <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Opportunities</option>
                                    <option value="jobs" <?php echo $filter === 'jobs' ? 'selected' : ''; ?>>Full-time Jobs</option>
                                    <option value="internships" <?php echo $filter === 'internships' ? 'selected' : ''; ?>>Internships</option>
                                    <option value="scholarships" <?php echo $filter === 'scholarships' ? 'selected' : ''; ?>>Scholarships</option>
                                    <option value="mentorship" <?php echo $filter === 'mentorship' ? 'selected' : ''; ?>>Mentorship</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="location" class="form-label small">Location</label>
                                <input type="text" name="location" id="location" class="form-control" 
                                       placeholder="City or Remote" value="<?php echo htmlspecialchars($location_filter); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label small">Search</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Job title, company, or keyword..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">&nbsp;</label>
                                <div class="d-grid">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if (!empty($search) || $filter !== 'all' || !empty($location_filter)): ?>
                            <div class="mt-3 text-center">
                                <a href="opportunities.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x"></i> Clear Filters
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Opportunities Grid -->
        <div class="row">
            <?php if (empty($opportunities)): ?>
                <div class="col-12">
                    <div class="card shadow-sm text-center py-5">
                        <div class="card-body">
                            <i class="bi bi-briefcase text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No Opportunities Found</h5>
                            <p class="text-muted">
                                <?php if (!empty($search) || $filter !== 'all' || !empty($location_filter)): ?>
                                    No opportunities match your search criteria. Try adjusting your filters.
                                <?php else: ?>
                                    No opportunities are available at the moment. Check back soon!
                                <?php endif; ?>
                            </p>
                            <p class="small text-muted">
                                Have an opportunity to share? Contact your school admin to post it.
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($opportunities as $opportunity): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100 opportunity-card">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div>
                                    <?php
                                    $opportunity_types = [
                                        'full-time' => ['icon' => 'bi-briefcase', 'color' => 'primary'],
                                        'part-time' => ['icon' => 'bi-briefcase-fill', 'color' => 'secondary'],
                                        'internship' => ['icon' => 'bi-person-workspace', 'color' => 'success'],
                                        'scholarship' => ['icon' => 'bi-mortarboard', 'color' => 'warning'],
                                        'mentorship' => ['icon' => 'bi-people', 'color' => 'info']
                                    ];
                                    
                                    $type_info = $opportunity_types[$opportunity['opportunity_type']] ?? $opportunity_types['full-time'];
                                    ?>
                                    <span class="badge bg-<?php echo $type_info['color']; ?>">
                                        <i class="<?php echo $type_info['icon']; ?>"></i> 
                                        <?php echo ucfirst($opportunity['opportunity_type']); ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo timeAgo($opportunity['created_at']); ?>
                                </small>
                            </div>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($opportunity['title']); ?></h5>
                                
                                <?php if (!empty($opportunity['company_name'])): ?>
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-building"></i> <?php echo htmlspecialchars($opportunity['company_name']); ?>
                                    </h6>
                                <?php else: ?>
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-person-badge"></i> Posted by <?php echo htmlspecialchars($opportunity['poster_name']); ?>
                                    </h6>
                                <?php endif; ?>
                                
                                <p class="card-text text-muted">
                                    <?php echo nl2br(htmlspecialchars(truncateText($opportunity['description'], 150))); ?>
                                </p>
                                
                                <div class="opportunity-details mb-3">
                                    <?php if ($opportunity['location']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-geo-alt text-primary me-2"></i>
                                            <span><?php echo htmlspecialchars($opportunity['location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($opportunity['salary_range']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-currency-dollar text-success me-2"></i>
                                            <span><?php echo htmlspecialchars($opportunity['salary_range']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($opportunity['requirements']): ?>
                                        <div class="mb-2">
                                            <i class="bi bi-list-check text-info me-2"></i>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(truncateText($opportunity['requirements'], 80)); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($opportunity['deadline']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-clock text-warning me-2"></i>
                                            <span class="small">
                                                Deadline: <?php echo date('M d, Y', strtotime($opportunity['deadline'])); ?>
                                                <?php if (strtotime($opportunity['deadline']) < time()): ?>
                                                    <span class="badge bg-danger ms-1">Expired</span>
                                                <?php elseif (strtotime($opportunity['deadline']) < strtotime('+7 days')): ?>
                                                    <span class="badge bg-warning ms-1">Ending Soon</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-eye"></i> <?php echo $opportunity['interest_count']; ?> interested
                                    </small>
                                    <small class="text-muted">
                                        Posted by <?php echo htmlspecialchars($opportunity['poster_name']); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <?php if ($opportunity['source_type'] === 'opportunity'): ?>
                                        <?php if ($opportunity['user_interested']): ?>
                                            <span class="badge bg-success me-2">
                                                <i class="bi bi-check"></i> Interested
                                            </span>
                                        <?php else: ?>
                                            <button class="btn btn-outline-primary btn-sm interest-btn" 
                                                    data-opportunity-id="<?php echo $opportunity['source_id']; ?>">
                                                <i class="bi bi-star"></i> Express Interest
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-info me-2">
                                            <i class="bi bi-info-circle"></i> School Post
                                        </span>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-primary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#opportunityModal"
                                            data-opportunity='<?php echo htmlspecialchars(json_encode($opportunity)); ?>'>
                                        <i class="bi bi-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="col-12">
                        <nav aria-label="Opportunities pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location_filter); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location_filter); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location_filter); ?>">
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

    <!-- Opportunity Details Modal -->
    <div class="modal fade" id="opportunityModal" tabindex="-1" aria-labelledby="opportunityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="opportunityModalLabel">Opportunity Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <div id="modalActions">
                        <!-- Action buttons will be added here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Express Interest functionality
        document.querySelectorAll('.interest-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const opportunityId = this.dataset.opportunityId;
                expressInterest(opportunityId, this);
            });
        });

        // Opportunity Modal handling
        const opportunityModal = document.getElementById('opportunityModal');
        opportunityModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const opportunity = JSON.parse(button.getAttribute('data-opportunity'));
            
            displayOpportunityDetails(opportunity);
        });

        function expressInterest(opportunityId, button) {
            const formData = new FormData();
            formData.append('action', 'apply_interest');
            formData.append('opportunity_id', opportunityId);

            fetch('opportunities.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.outerHTML = '<span class="badge bg-success"><i class="bi bi-check"></i> Interested</span>';
                    showAlert('success', data.message);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while processing your request.');
            });
        }

        function displayOpportunityDetails(opportunity) {
            const modalContent = document.getElementById('modalContent');
            const modalActions = document.getElementById('modalActions');
            
            modalContent.innerHTML = `
                <div class="mb-3">
                    <h4>${opportunity.title}</h4>
                    <h6 class="text-primary"><i class="bi bi-building"></i> ${opportunity.company_name}</h6>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Type:</strong> ${opportunity.opportunity_type.charAt(0).toUpperCase() + opportunity.opportunity_type.slice(1)}
                    </div>
                    <div class="col-md-6">
                        <strong>Location:</strong> ${opportunity.location || 'Not specified'}
                    </div>
                </div>
                
                ${opportunity.salary_range ? `
                    <div class="mb-3">
                        <strong>Salary/Compensation:</strong> ${opportunity.salary_range}
                    </div>
                ` : ''}
                
                <div class="mb-3">
                    <strong>Description:</strong>
                    <p class="mt-2">${opportunity.description.replace(/\n/g, '<br>')}</p>
                </div>
                
                ${opportunity.requirements ? `
                    <div class="mb-3">
                        <strong>Requirements:</strong>
                        <p class="mt-2">${opportunity.requirements.replace(/\n/g, '<br>')}</p>
                    </div>
                ` : ''}
                
                ${opportunity.application_process ? `
                    <div class="mb-3">
                        <strong>How to Apply:</strong>
                        <p class="mt-2">${opportunity.application_process.replace(/\n/g, '<br>')}</p>
                    </div>
                ` : ''}
                
                ${opportunity.deadline ? `
                    <div class="mb-3">
                        <strong>Application Deadline:</strong> 
                        <span class="badge bg-warning">${new Date(opportunity.deadline).toLocaleDateString()}</span>
                    </div>
                ` : ''}
                
                ${opportunity.contact_email ? `
                    <div class="mb-3">
                        <strong>Contact:</strong> 
                        <a href="mailto:${opportunity.contact_email}">${opportunity.contact_email}</a>
                    </div>
                ` : ''}
                
                <hr>
                <small class="text-muted">
                    Posted by ${opportunity.poster_name} • ${timeAgo(opportunity.created_at)}
                </small>
            `;
            
            // Add action buttons
            modalActions.innerHTML = opportunity.user_interested ? 
                '<span class="badge bg-success"><i class="bi bi-check"></i> You\'ve expressed interest</span>' :
                `<button class="btn btn-primary" onclick="expressInterest(${opportunity.id}, this)">
                    <i class="bi bi-star"></i> Express Interest
                </button>`;
        }

        function timeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
            return `${Math.floor(diffInSeconds / 86400)} days ago`;
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