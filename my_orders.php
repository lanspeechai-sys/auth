<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

// Map session data
$user = [
    'user_id' => $_SESSION['user_id'] ?? 0,
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'student'
];

// Fetch user's orders
$orders = [];
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
               (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.order_date DESC
    ");
    
    $stmt->execute([$user['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Error fetching orders: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .orders-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .order-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .order-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .order-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            border-radius: 12px 12px 0 0;
        }
        
        .order-body {
            padding: 1.5rem;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-processing { background: #cfe2ff; color: #084298; }
        .badge-shipped { background: #d1e7dd; color: #0a3622; }
        .badge-delivered { background: #d1e7dd; color: #0f5132; }
        .badge-cancelled { background: #f8d7da; color: #842029; }
        
        .payment-badge-paid { background: #d1e7dd; color: #0f5132; }
        .payment-badge-pending { background: #fff3cd; color: #856404; }
        .payment-badge-failed { background: #f8d7da; color: #842029; }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #6c757d;
            opacity: 0.5;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>SchoolLink Africa
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo $user['role'] === 'student' ? 'user/dashboard.php' : 'school-admin/dashboard.php'; ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="store.php">
                            <i class="bi bi-shop"></i> Store
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="my_orders.php">
                            <i class="bi bi-receipt"></i> My Orders
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="user/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="orders-header">
        <div class="container">
            <h1 class="mb-0">
                <i class="bi bi-receipt me-3"></i>My Orders
            </h1>
            <p class="mb-0 opacity-75">View and track your order history</p>
        </div>
    </div>

    <!-- Orders List -->
    <div class="container my-5">
        <?php if (empty($orders)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="bi bi-cart-x"></i>
                <h3 class="mt-3">No Orders Yet</h3>
                <p class="text-muted">You haven't placed any orders. Start shopping to see your orders here!</p>
                <a href="store.php" class="btn btn-primary btn-lg mt-3">
                    <i class="bi bi-shop"></i> Browse Store
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <h4 class="mb-4">Order History (<?php echo count($orders); ?>)</h4>
                    
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <small class="text-muted">Order Number</small>
                                        <div class="fw-bold">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Order Date</small>
                                        <div class="fw-bold"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Total</small>
                                        <div class="fw-bold text-success">₦<?php echo number_format($order['order_total'], 2); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Status</small>
                                        <div>
                                            <span class="status-badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">Payment</small>
                                        <div>
                                            <span class="status-badge payment-badge-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6 class="mb-3">
                                            <i class="bi bi-box me-2"></i><?php echo $order['total_items']; ?> Item(s)
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?><br>
                                            <?php if ($order['customer_phone']): ?>
                                                <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($order['delivery_address']): ?>
                                                <strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?><br>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($order['payment_reference']): ?>
                                            <small class="text-muted">
                                                <i class="bi bi-receipt"></i> Reference: <?php echo htmlspecialchars($order['payment_reference']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4 text-md-end">
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                        
                                        <?php if ($order['payment_status'] === 'pending'): ?>
                                            <a href="checkout.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success mt-2">
                                                <i class="bi bi-credit-card"></i> Pay Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>