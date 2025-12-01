<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get error log file path (PHP error log)
$phpErrorLog = ini_get('error_log');
$customLogPath = '../logs/system.log';

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0755, true);
}

// Get log entries
$logEntries = [];
$logSource = 'custom';

// Try custom log first
if (file_exists($customLogPath)) {
    $logContent = file_get_contents($customLogPath);
    $logEntries = array_filter(explode("\n", $logContent));
    $logEntries = array_reverse($logEntries); // Most recent first
    $logEntries = array_slice($logEntries, 0, 500); // Limit to 500 entries
} elseif ($phpErrorLog && file_exists($phpErrorLog)) {
    $logContent = file_get_contents($phpErrorLog);
    $logEntries = array_filter(explode("\n", $logContent));
    $logEntries = array_reverse($logEntries);
    $logEntries = array_slice($logEntries, 0, 500);
    $logSource = 'php';
}

// Parse log level
function getLogLevel($entry) {
    if (stripos($entry, 'ERROR') !== false || stripos($entry, 'FATAL') !== false) {
        return 'danger';
    } elseif (stripos($entry, 'WARNING') !== false || stripos($entry, 'WARN') !== false) {
        return 'warning';
    } elseif (stripos($entry, 'INFO') !== false) {
        return 'info';
    } elseif (stripos($entry, 'DEBUG') !== false) {
        return 'secondary';
    }
    return 'light';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - SchoolLink Africa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            padding: 0.5rem;
            border-left: 3px solid;
            margin-bottom: 0.5rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .log-entry.alert-danger {
            border-left-color: #dc3545;
        }
        
        .log-entry.alert-warning {
            border-left-color: #ffc107;
        }
        
        .log-entry.alert-info {
            border-left-color: #0dcaf0;
        }
        
        .log-entry.alert-secondary {
            border-left-color: #6c757d;
        }
        
        .log-entry.alert-light {
            border-left-color: #dee2e6;
        }
        
        .filter-badges {
            position: sticky;
            top: 0;
            background: white;
            z-index: 100;
            padding: 1rem 0;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3">
                    <i class="bi bi-file-earmark-text"></i> System Logs
                </h1>
                <p class="text-muted mb-0">
                    Viewing <?php echo $logSource === 'custom' ? 'custom application logs' : 'PHP error logs'; ?>
                    (Last 500 entries)
                </p>
            </div>
            <div class="col-auto">
                <button onclick="window.close()" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
        </div>
        
        <div class="filter-badges">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="logFilter" id="filterAll" value="all" checked>
                <label class="btn btn-outline-secondary" for="filterAll">All Logs</label>
                
                <input type="radio" class="btn-check" name="logFilter" id="filterError" value="danger">
                <label class="btn btn-outline-danger" for="filterError">Errors</label>
                
                <input type="radio" class="btn-check" name="logFilter" id="filterWarning" value="warning">
                <label class="btn btn-outline-warning" for="filterWarning">Warnings</label>
                
                <input type="radio" class="btn-check" name="logFilter" id="filterInfo" value="info">
                <label class="btn btn-outline-info" for="filterInfo">Info</label>
            </div>
            
            <input type="text" id="searchLogs" class="form-control d-inline-block w-auto ms-3" placeholder="Search logs...">
        </div>
        
        <div id="logContainer" class="mt-3">
            <?php if (empty($logEntries)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No log entries found.
                    Log file: <code><?php echo htmlspecialchars($logSource === 'custom' ? $customLogPath : $phpErrorLog); ?></code>
                </div>
            <?php else: ?>
                <?php foreach ($logEntries as $index => $entry): ?>
                    <?php if (trim($entry)): ?>
                        <div class="log-entry alert alert-<?php echo getLogLevel($entry); ?>" data-level="<?php echo getLogLevel($entry); ?>">
                            <?php echo htmlspecialchars($entry); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filter logs by level
        document.querySelectorAll('input[name="logFilter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const filterValue = this.value;
                const logEntries = document.querySelectorAll('.log-entry');
                
                logEntries.forEach(entry => {
                    if (filterValue === 'all') {
                        entry.style.display = '';
                    } else {
                        entry.style.display = entry.dataset.level === filterValue ? '' : 'none';
                    }
                });
            });
        });
        
        // Search logs
        document.getElementById('searchLogs').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const logEntries = document.querySelectorAll('.log-entry');
            
            logEntries.forEach(entry => {
                const text = entry.textContent.toLowerCase();
                entry.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>