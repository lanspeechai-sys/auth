<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('super_admin');

$user = getCurrentUser();
$db = getDB();

// Get comprehensive platform statistics
$stats = [
    'total_schools' => 0,
    'approved_schools' => 0,
    'pending_schools' => 0,
    'suspended_schools' => 0,
    'total_users' => 0,
    'approved_users' => 0,
    'pending_users' => 0,
    'suspended_users' => 0,
    'school_admins' => 0,
    'students' => 0,
    'recent_registrations' => [],
    'top_schools' => [],
    'monthly_growth' => []
];

if ($db) {
    try {
        // School statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
            FROM schools
        ");
        $stmt->execute();
        $school_stats = $stmt->fetch();
        
        $stats['total_schools'] = $school_stats['total'];
        $stats['approved_schools'] = $school_stats['approved'];
        $stats['pending_schools'] = $school_stats['pending'];
        $stats['suspended_schools'] = $school_stats['suspended'];
        
        // User statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN role = 'school_admin' THEN 1 ELSE 0 END) as school_admins,
                SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students
            FROM users
        ");
        $stmt->execute();
        $user_stats = $stmt->fetch();
        
        $stats['total_users'] = $user_stats['total'];
        $stats['approved_users'] = $user_stats['approved'];
        $stats['pending_users'] = $user_stats['pending'];
        $stats['suspended_users'] = $user_stats['suspended'];
        $stats['school_admins'] = $user_stats['school_admins'];
        $stats['students'] = $user_stats['students'];
        
        // Recent registrations (last 30 days)
        $stmt = $db->prepare("
            SELECT 'school' as type, name, created_at, location as info
            FROM schools 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT 'user' as type, name, created_at, email as info
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $stats['recent_registrations'] = $stmt->fetchAll();
        
        // Top schools by user count
        $stmt = $db->prepare("
            SELECT s.name, s.location, COUNT(u.id) as user_count
            FROM schools s
            LEFT JOIN users u ON s.id = u.school_id
            WHERE s.approved = 1
            GROUP BY s.id, s.name, s.location
            ORDER BY user_count DESC
            LIMIT 10
        ");
        $stmt->execute();
        $stats['top_schools'] = $stmt->fetchAll();
        
        // Monthly growth data (last 12 months)
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count,
                'schools' as type
            FROM schools 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            UNION ALL
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count,
                'users' as type
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute();
        $growth_data = $stmt->fetchAll();
        
        // Process growth data
        $monthly_data = [];
        foreach ($growth_data as $row) {
            if (!isset($monthly_data[$row['month']])) {
                $monthly_data[$row['month']] = ['schools' => 0, 'users' => 0];
            }
            $monthly_data[$row['month']][$row['type']] = $row['count'];
        }
        $stats['monthly_growth'] = array_slice(array_reverse($monthly_data, true), 0, 6, true);
        
    } catch (PDOException $e) {
        error_log("Reports error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - SchoolLink Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-2">Super Admin</h5>
                        <small class="text-muted"><?php echo htmlspecialchars($user['name']); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="schools.php">
                                <i class="bi bi-building"></i> Manage Schools
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white-50" href="users.php">
                                <i class="bi bi-people"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="reports.php">
                                <i class="bi bi-graph-up"></i> Reports
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
                    <h1 class="h2">Reports & Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Report
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="bi bi-download"></i> Export Data
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Overview Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 border-start border-primary border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Schools
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_schools']; ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?php echo $stats['approved_schools']; ?> approved, 
                                            <?php echo $stats['pending_schools']; ?> pending
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-building text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 border-start border-success border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_users']; ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?php echo $stats['approved_users']; ?> approved, 
                                            <?php echo $stats['pending_users']; ?> pending
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 border-start border-info border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            School Admins
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['school_admins']; ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            Managing platform schools
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-gear text-info" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 border-start border-warning border-4">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Students/Alumni
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['students']; ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            Active community members
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-mortarboard text-warning" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-graph-up"></i> Platform Growth (Last 6 Months)
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="growthChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-pie-chart"></i> User Distribution
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="userDistributionChart" width="300" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Row -->
                <div class="row">
                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-trophy"></i> Top Schools by User Count
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($stats['top_schools'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-building" style="font-size: 2rem;"></i>
                                        <p class="mt-2">No school data available</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>School</th>
                                                    <th>Users</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats['top_schools'] as $index => $school): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($index === 0): ?>
                                                                <i class="bi bi-trophy-fill text-warning"></i>
                                                            <?php elseif ($index === 1): ?>
                                                                <i class="bi bi-award-fill text-secondary"></i>
                                                            <?php elseif ($index === 2): ?>
                                                                <i class="bi bi-award text-success"></i>
                                                            <?php else: ?>
                                                                <?php echo $index + 1; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($school['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($school['location']); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $school['user_count']; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-clock-history"></i> Recent Registrations (Last 30 Days)
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($stats['recent_registrations'])): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                                        <p class="mt-2">No recent registrations</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($stats['recent_registrations'] as $registration): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <?php if ($registration['type'] === 'school'): ?>
                                                            <i class="bi bi-building text-primary me-2"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-person text-success me-2"></i>
                                                        <?php endif; ?>
                                                        <strong><?php echo htmlspecialchars($registration['name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($registration['info']); ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($registration['created_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="bi bi-gear"></i> System Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-success mb-2">
                                                <i class="bi bi-check-circle-fill" style="font-size: 2rem;"></i>
                                            </div>
                                            <h6>Database Status</h6>
                                            <p class="text-success">Connected</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-info mb-2">
                                                <i class="bi bi-server" style="font-size: 2rem;"></i>
                                            </div>
                                            <h6>Platform Version</h6>
                                            <p class="text-muted">v1.0.0</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-warning mb-2">
                                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                                            </div>
                                            <h6>Active Schools</h6>
                                            <p class="text-muted"><?php echo $stats['approved_schools']; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="text-primary mb-2">
                                                <i class="bi bi-graph-up-arrow" style="font-size: 2rem;"></i>
                                            </div>
                                            <h6>Growth Rate</h6>
                                            <p class="text-muted">Stable</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Growth Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthData = <?php echo json_encode($stats['monthly_growth']); ?>;
        
        const months = Object.keys(growthData).reverse();
        const schoolsData = months.map(month => growthData[month].schools || 0);
        const usersData = months.map(month => growthData[month].users || 0);
        const labels = months.map(month => {
            const date = new Date(month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Schools',
                    data: schoolsData,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
                }, {
                    label: 'New Users',
                    data: usersData,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // User Distribution Chart
        const distributionCtx = document.getElementById('userDistributionChart').getContext('2d');
        
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Students/Alumni', 'School Admins', 'Pending Users'],
                datasets: [{
                    data: [
                        <?php echo $stats['students']; ?>,
                        <?php echo $stats['school_admins']; ?>,
                        <?php echo $stats['pending_users']; ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(13, 202, 240, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function exportData() {
            // Export current report data as CSV
            const table = document.querySelector('table');
            if (!table) {
                alert('No data to export');
                return;
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let row of rows) {
                const cols = row.querySelectorAll('td, th');
                const rowData = Array.from(cols).map(col => {
                    let text = col.innerText.replace(/"/g, '""');
                    return `"${text}"`;
                });
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'report_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>