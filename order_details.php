<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$orderId = intval($_GET['id'] ?? 0);

// Map session data
$user = [
    'user_id' => $_SESSION['user_id'] ?? 0,
    'name' => $_SESSION['user_name'] ?? 'User'
];

// Fetch order details
$order = null;
$orderItems = [];

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get order
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$orderId, $user['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // Get order items
        $stmt = $pdo->prepare("
            SELECT oi.*, p.image_path
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log('Error fetching order details: ' . $e->getMessage());
}

if (!$order) {
    header('Location: my_orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?> - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
        }
        
        .item-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .item-placeholder {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6c757d;
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
            
            <div class="ms-auto">
                <a href="my_orders.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
    </nav>

    <!-- Order Header -->
    <div class="order-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></h1>
                    <p class="mb-0 opacity-75">Placed on <?php echo date('F d, Y \a\t h:i A', strtotime($order['order_date'])); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="status-badge" style="background: #fff; color: #667eea;">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    <span class="status-badge ms-2" style="background: #fff; color: #667eea;">
                        Payment: <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details -->
    <div class="container my-5">
        <div class="row">
            <!-- Order Items -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-box me-2"></i>Order Items
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="item-card">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="item-image" alt="Product">
                                        <?php else: ?>
                                            <div class="item-placeholder">
                                                <i class="bi bi-box"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-5">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['product_title']); ?></h6>
                                        <small class="text-muted">₦<?php echo number_format($item['price'], 2); ?> each</small>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <small class="text-muted">Quantity</small>
                                        <div class="fw-bold"><?php echo $item['quantity']; ?></div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <small class="text-muted">Subtotal</small>
                                        <div class="fw-bold text-success">₦<?php echo number_format($item['subtotal'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Customer Details -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-person me-2"></i>Customer Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                <?php if ($order['customer_phone']): ?>
                                    <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($order['delivery_address']): ?>
                                    <p class="mb-2"><strong>Delivery Address:</strong></p>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt me-2"></i>Order Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span>₦<?php echo number_format($order['order_total'], 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total</strong>
                            <strong class="text-success">₦<?php echo number_format($order['order_total'], 2); ?></strong>
                        </div>
                        
                        <?php if ($order['payment_reference']): ?>
                            <div class="alert alert-info mb-0">
                                <small><strong>Payment Reference:</strong><br>
                                <?php echo htmlspecialchars($order['payment_reference']); ?></small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order['payment_status'] === 'pending'): ?>
                            <a href="checkout.php?order_id=<?php echo $orderId; ?>" class="btn btn-success w-100 mt-3">
                                <i class="bi bi-credit-card"></i> Complete Payment
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>Order Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="mb-3">
                                <div class="fw-bold text-success">
                                    <i class="bi bi-check-circle"></i> Order Placed
                                </div>
                                <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></small>
                            </div>
                            
                            <?php if ($order['payment_status'] === 'paid'): ?>
                                <div class="mb-3">
                                    <div class="fw-bold text-success">
                                        <i class="bi bi-check-circle"></i> Payment Confirmed
                                    </div>
                                    <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($order['updated_at'])); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (in_array($order['status'], ['processing', 'shipped', 'delivered'])): ?>
                                <div class="mb-3">
                                    <div class="fw-bold <?php echo $order['status'] === 'processing' ? 'text-primary' : 'text-success'; ?>">
                                        <i class="bi bi-<?php echo $order['status'] === 'processing' ? 'hourglass-split' : 'check-circle'; ?>"></i> Processing
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (in_array($order['status'], ['shipped', 'delivered'])): ?>
                                <div class="mb-3">
                                    <div class="fw-bold <?php echo $order['status'] === 'shipped' ? 'text-primary' : 'text-success'; ?>">
                                        <i class="bi bi-<?php echo $order['status'] === 'shipped' ? 'truck' : 'check-circle'; ?>"></i> Shipped
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'delivered'): ?>
                                <div class="mb-3">
                                    <div class="fw-bold text-success">
                                        <i class="bi bi-check-circle"></i> Delivered
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>