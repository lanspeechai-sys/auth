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

$success_message = '';
$error_message = '';
$action = $_GET['action'] ?? 'list';
$opportunity_id = intval($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $db) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $opportunity_type = $_POST['opportunity_type'] ?? 'full-time';
    $location = sanitizeInput($_POST['location'] ?? '');
    $salary_range = sanitizeInput($_POST['salary_range'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $application_process = trim($_POST['application_process'] ?? '');
    $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
    $deadline = $_POST['deadline'] ?? '';

    // Validation
    if (empty($title) || empty($description) || empty($company_name)) {
        $error_message = 'Please fill in all required fields (Title, Description, Company/Organization)';
    } elseif (!empty($contact_email) && !isValidEmail($contact_email)) {
        $error_message = 'Please enter a valid contact email';
    } elseif (!empty($deadline) && strtotime($deadline) < time()) {
        $error_message = 'Deadline must be in the future';
    } else {
        try {
            if ($action == 'create') {
                // Create new opportunity
                $stmt = $db->prepare("
                    INSERT INTO opportunities (title, description, company_name, opportunity_type, location, 
                                             salary_range, requirements, application_process, contact_email, 
                                             deadline, school_id, posted_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $description, $company_name, $opportunity_type, $location,
                    $salary_range, $requirements, $application_process, $contact_email,
                    $deadline ?: null, $user['school_id'], $user['id']
                ]);
                $success_message = 'Opportunity created successfully!';
                $action = 'list';
            } elseif ($action == 'edit' && $opportunity_id) {
                // Update existing opportunity
                $stmt = $db->prepare("
                    UPDATE opportunities 
                    SET title = ?, description = ?, company_name = ?, opportunity_type = ?, location = ?,
                        salary_range = ?, requirements = ?, application_process = ?, contact_email = ?, deadline = ?
                    WHERE id = ? AND school_id = ?
                ");
                $stmt->execute([
                    $title, $description, $company_name, $opportunity_type, $location,
                    $salary_range, $requirements, $application_process, $contact_email,
                    $deadline ?: null, $opportunity_id, $user['school_id']
                ]);
                $success_message = 'Opportunity updated successfully!';
                $action = 'list';
            }
        } catch (PDOException $e) {
            error_log("Error managing opportunity: " . $e->getMessage());
            $error_message = 'Database error occurred. Please try again.';
        }
    }
}

// Handle delete action
if ($action == 'delete' && $opportunity_id && $db) {
    try {
        $stmt = $db->prepare("DELETE FROM opportunities WHERE id = ? AND school_id = ?");
        $stmt->execute([$opportunity_id, $user['school_id']]);
        $success_message = 'Opportunity deleted successfully!';
        $action = 'list';
    } catch (PDOException $e) {
        error_log("Error deleting opportunity: " . $e->getMessage());
        $error_message = 'Error deleting opportunity.';
    }
}

// Get opportunity for editing
$existing_opportunity = null;
if ($action == 'edit' && $opportunity_id && $db) {
    try {
        $stmt = $db->prepare("SELECT * FROM opportunities WHERE id = ? AND school_id = ?");
        $stmt->execute([$opportunity_id, $user['school_id']]);
        $existing_opportunity = $stmt->fetch();
        if (!$existing_opportunity) {
            $error_message = 'Opportunity not found.';
            $action = 'list';
        }
    } catch (PDOException $e) {
        error_log("Error fetching opportunity: " . $e->getMessage());
        $error_message = 'Error loading opportunity.';
        $action = 'list';
    }
}

// Get opportunities for listing
$opportunities = [];
if ($action == 'list' && $db) {
    try {
        $stmt = $db->prepare("
            SELECT o.*, COUNT(oi.id) as interest_count 
            FROM opportunities o 
            LEFT JOIN opportunity_interests oi ON o.id = oi.opportunity_id 
            WHERE o.school_id = ? 
            GROUP BY o.id 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$user['school_id']]);
        $opportunities = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching opportunities: " . $e->getMessage());
        $opportunities = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opportunities Management - <?php echo htmlspecialchars($school['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill"></i> <?php echo htmlspecialchars($school['name']); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="posts.php">
                            <i class="bi bi-newspaper"></i> Posts
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
                    <li class="nav-item">
                        <a class="nav-link" href="join-requests.php">
                            <i class="bi bi-person-plus"></i> Join Requests
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white-50" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-briefcase text-primary"></i> 
                        <?php 
                        echo $action == 'create' ? 'Create New Opportunity' : 
                             ($action == 'edit' ? 'Edit Opportunity' : 'Opportunities Management'); 
                        ?>
                    </h1>
                    <?php if ($action == 'list'): ?>
                        <a href="opportunities.php?action=create" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> Create New Opportunity
                        </a>
                    <?php else: ?>
                        <a href="opportunities.php" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to List
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action == 'create' || $action == 'edit'): ?>
                    <!-- Create/Edit Opportunity Form -->
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-briefcase"></i>
                                        <?php echo $action == 'create' ? 'Create New Opportunity' : 'Edit Opportunity'; ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-8 mb-3">
                                                <label for="title" class="form-label">Opportunity Title *</label>
                                                <input type="text" class="form-control" id="title" name="title" 
                                                       value="<?php echo htmlspecialchars($existing_opportunity['title'] ?? ''); ?>" 
                                                       placeholder="e.g., Software Developer Position" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="opportunity_type" class="form-label">Type *</label>
                                                <select class="form-select" id="opportunity_type" name="opportunity_type" required>
                                                    <option value="full-time" <?php echo ($existing_opportunity['opportunity_type'] ?? '') == 'full-time' ? 'selected' : ''; ?>>
                                                        Full-time Job
                                                    </option>
                                                    <option value="part-time" <?php echo ($existing_opportunity['opportunity_type'] ?? '') == 'part-time' ? 'selected' : ''; ?>>
                                                        Part-time Job
                                                    </option>
                                                    <option value="internship" <?php echo ($existing_opportunity['opportunity_type'] ?? '') == 'internship' ? 'selected' : ''; ?>>
                                                        Internship
                                                    </option>
                                                    <option value="scholarship" <?php echo ($existing_opportunity['opportunity_type'] ?? '') == 'scholarship' ? 'selected' : ''; ?>>
                                                        Scholarship
                                                    </option>
                                                    <option value="mentorship" <?php echo ($existing_opportunity['opportunity_type'] ?? '') == 'mentorship' ? 'selected' : ''; ?>>
                                                        Mentorship
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="company_name" class="form-label">Company/Organization *</label>
                                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                                       value="<?php echo htmlspecialchars($existing_opportunity['company_name'] ?? ''); ?>" 
                                                       placeholder="e.g., ABC Corporation" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="location" class="form-label">Location</label>
                                                <input type="text" class="form-control" id="location" name="location" 
                                                       value="<?php echo htmlspecialchars($existing_opportunity['location'] ?? ''); ?>" 
                                                       placeholder="e.g., Lagos, Nigeria or Remote">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description *</label>
                                            <textarea class="form-control" id="description" name="description" rows="4" 
                                                      placeholder="Detailed description of the opportunity, requirements, and what's expected..." required><?php echo htmlspecialchars($existing_opportunity['description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="salary_range" class="form-label">Salary/Compensation</label>
                                                <input type="text" class="form-control" id="salary_range" name="salary_range" 
                                                       value="<?php echo htmlspecialchars($existing_opportunity['salary_range'] ?? ''); ?>" 
                                                       placeholder="e.g., $50,000 - $70,000 or Competitive">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="deadline" class="form-label">Application Deadline</label>
                                                <input type="date" class="form-control" id="deadline" name="deadline" 
                                                       value="<?php echo $existing_opportunity['deadline'] ?? ''; ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="requirements" class="form-label">Requirements</label>
                                            <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                                      placeholder="List the key requirements, qualifications, skills needed..."><?php echo htmlspecialchars($existing_opportunity['requirements'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="application_process" class="form-label">Application Process</label>
                                            <textarea class="form-control" id="application_process" name="application_process" rows="3" 
                                                      placeholder="How to apply, what documents to submit, application steps..."><?php echo htmlspecialchars($existing_opportunity['application_process'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-4">
                                            <label for="contact_email" class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($existing_opportunity['contact_email'] ?? ''); ?>" 
                                                   placeholder="Contact email for inquiries">
                                        </div>

                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <a href="opportunities.php" class="btn btn-secondary me-md-2">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> 
                                                <?php echo $action == 'create' ? 'Create Opportunity' : 'Update Opportunity'; ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Opportunities List -->
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php if (empty($opportunities)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-briefcase text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="text-muted mt-3">No opportunities created yet</h5>
                                    <p class="text-muted">Create your first opportunity to help students find great opportunities!</p>
                                    <a href="opportunities.php?action=create" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Create Your First Opportunity
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Company</th>
                                                <th>Location</th>
                                                <th>Deadline</th>
                                                <th>Interest</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($opportunities as $opportunity): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($opportunity['title']); ?></strong>
                                                        <br><small class="text-muted">
                                                            <?php echo htmlspecialchars(truncateText($opportunity['description'], 60)); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $type_badges = [
                                                            'full-time' => 'primary',
                                                            'part-time' => 'secondary',
                                                            'internship' => 'success',
                                                            'scholarship' => 'warning',
                                                            'mentorship' => 'info'
                                                        ];
                                                        $badge_color = $type_badges[$opportunity['opportunity_type']] ?? 'primary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                                            <?php echo ucfirst($opportunity['opportunity_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($opportunity['company_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($opportunity['location'] ?: 'Not specified'); ?></td>
                                                    <td>
                                                        <?php if ($opportunity['deadline']): ?>
                                                            <?php 
                                                            $deadline_class = strtotime($opportunity['deadline']) < time() ? 'text-danger' : 
                                                                            (strtotime($opportunity['deadline']) < strtotime('+7 days') ? 'text-warning' : 'text-success');
                                                            ?>
                                                            <small class="<?php echo $deadline_class; ?>">
                                                                <?php echo date('M d, Y', strtotime($opportunity['deadline'])); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No deadline</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-eye"></i> <?php echo $opportunity['interest_count']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($opportunity['status'] === 'active'): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary"><?php echo ucfirst($opportunity['status']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="opportunities.php?action=edit&id=<?php echo $opportunity['id']; ?>" 
                                                               class="btn btn-outline-primary btn-sm" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="opportunities.php?action=delete&id=<?php echo $opportunity['id']; ?>" 
                                                               class="btn btn-outline-danger btn-sm" 
                                                               onclick="return confirm('Are you sure you want to delete this opportunity?')"
                                                               title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-resize textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
    </script>
</body>
</html>