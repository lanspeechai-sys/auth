<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$orderId = $_GET['order_id'] ?? 0;
$reference = $_GET['reference'] ?? '';

// Fetch order details
$order = null;
if ($orderId > 0) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            WHERE o.id = ? AND o.user_id = ?
        ");
        
        $stmt->execute([$orderId, $_SESSION['user_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log('Error fetching order: ' . $e->getMessage());
    }
}

// Map session data
$user = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }
        
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: scaleIn 0.5s ease-out 0.3s both;
        }
        
        .success-icon i {
            font-size: 3rem;
            color: white;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #212529;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            
            <h1 class="mb-3">Payment Successful!</h1>
            <p class="text-muted mb-4">Thank you for your purchase. Your order has been confirmed and is being processed.</p>
            
            <?php if ($order): ?>
                <div class="order-details">
                    <h5 class="mb-3">
                        <i class="bi bi-receipt me-2"></i>Order Details
                    </h5>
                    
                    <div class="detail-row">
                        <span class="detail-label">Order Number</span>
                        <span class="detail-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Payment Reference</span>
                        <span class="detail-value"><?php echo htmlspecialchars($reference); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Total Amount</span>
                        <span class="detail-value text-success">₦<?php echo number_format($order['order_total'], 2); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Items</span>
                        <span class="detail-value"><?php echo $order['item_count']; ?> item(s)</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <span class="badge bg-success">Paid</span>
                            <span class="badge bg-info">Processing</span>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Customer</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    A confirmation email has been sent to <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong>
                </div>
            <?php endif; ?>
            
            <div class="d-flex gap-3 justify-content-center mt-4">
                <a href="my_orders.php" class="btn btn-outline-primary">
                    <i class="bi bi-list-check me-2"></i>View Orders
                </a>
                <a href="store.php" class="btn btn-primary">
                    <i class="bi bi-shop me-2"></i>Continue Shopping
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>