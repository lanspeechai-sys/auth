<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'classes/cart_class.php';

try {
    $cart = new Cart();
    $cartSummary = $cart->getCartSummary();
    
    // Map session data to user array with correct keys
    $user = [
        'user_id' => $_SESSION['user_id'] ?? 0,
        'id' => $_SESSION['user_id'] ?? 0,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'student',
        'school_id' => $_SESSION['school_id'] ?? 0
    ];
} catch (Exception $e) {
    // If there's an error with cart functionality, redirect to store
    error_log("Cart error: " . $e->getMessage());
    header('Location: store.php?error=cart_unavailable');
    exit;
}

// Calculate cart statistics
$subtotal = $cartSummary['total'];
$tax = $subtotal * 0.075; // 7.5% VAT (typical for Nigeria)
$shipping = $subtotal > 50 ? 0 : 10; // Free shipping above $50
$total = $subtotal + $tax + $shipping;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .cart-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .cart-item {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1rem;
        }
        
        .cart-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .product-placeholder {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6c757d;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .quantity-btn {
            background: #f8f9fa;
            border: none;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .quantity-btn:hover {
            background: #e9ecef;
        }
        
        .quantity-input {
            border: none;
            width: 60px;
            text-align: center;
            outline: none;
        }
        
        .order-summary {
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 2rem;
            position: sticky;
            top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
        }
        
        .summary-row.total {
            border-top: 2px solid #dee2e6;
            padding-top: 1rem;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .btn-remove {
            border: none;
            background: transparent;
            color: #dc3545;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .btn-remove:hover {
            background: #dc3545;
            color: white;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .product-image, .product-placeholder {
                width: 80px;
                height: 80px;
            }
            
            .cart-item .row > div {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div>Processing...</div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>SchoolLink Africa
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($user['role'] === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="user/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="school-admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="store.php">
                            <i class="bi bi-shop"></i> Store
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="cart.php">
                            <i class="bi bi-cart3"></i> Cart <span class="badge bg-light text-dark ms-1" id="cartBadge"><?php echo $cartSummary['count']; ?></span>
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="user/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Cart Header -->
    <div class="cart-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="bi bi-cart3 me-3"></i>Shopping Cart
                    </h1>
                    <p class="mb-0 opacity-75">Review your items and proceed to checkout</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <div class="me-4">
                            <div class="h5 mb-0"><?php echo $cartSummary['count']; ?> Items</div>
                            <small class="opacity-75">in your cart</small>
                        </div>
                        <div>
                            <div class="h5 mb-0">$<?php echo number_format($total, 2); ?></div>
                            <small class="opacity-75">total amount</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($cartSummary['items'])): ?>
            <!-- Empty Cart -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="empty-cart">
                        <i class="bi bi-cart-x"></i>
                        <h3>Your cart is empty</h3>
                        <p>Start shopping to add items to your cart</p>
                        <a href="store.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-shop"></i> Browse Store
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart Items and Summary -->
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Cart Items (<?php echo $cartSummary['count']; ?>)</h4>
                        <button class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="bi bi-trash"></i> Clear Cart
                        </button>
                    </div>
                    
                    <div id="cartItemsContainer">
                        <?php foreach ($cartSummary['items'] as $item): ?>
                            <div class="cart-item p-4" data-product-id="<?php echo $item['product_id']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-2 col-sm-3">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                                 class="product-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                        <?php else: ?>
                                            <div class="product-placeholder">
                                                <i class="bi bi-box"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4 col-sm-9">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <p class="text-muted mb-0">$<?php echo number_format($item['price'], 2); ?> each</p>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> Added <?php echo date('M j, Y', strtotime($item['added_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="col-md-3 col-sm-6">
                                        <div class="quantity-controls">
                                            <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, <?php echo $item['quantity'] - 1; ?>)">
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <input type="number" class="quantity-input" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="99"
                                                   onchange="updateQuantity(<?php echo $item['product_id']; ?>, this.value)">
                                            <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 col-sm-4">
                                        <div class="text-end">
                                            <div class="fw-bold h6 mb-2">
                                                $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </div>
                                            <button class="btn-remove" onclick="removeFromCart(<?php echo $item['product_id']; ?>)" 
                                                    title="Remove item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Continue Shopping -->
                    <div class="mt-4">
                        <a href="store.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h5 class="mb-4">
                            <i class="bi bi-receipt me-2"></i>Order Summary
                        </h5>
                        
                        <div id="summaryDetails">
                            <div class="summary-row">
                                <span>Subtotal (<?php echo $cartSummary['count']; ?> items)</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax (7.5%)</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Shipping <?php echo $subtotal > 50 ? '(Free)' : ''; ?></span>
                                <span><?php echo $shipping > 0 ? '$' . number_format($shipping, 2) : 'Free'; ?></span>
                            </div>
                            
                            <div class="summary-row total">
                                <span>Total</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="checkout.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                            </a>
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer me-2"></i>Print Cart
                            </button>
                        </div>
                        
                        <!-- Security Badge -->
                        <div class="text-center mt-4 pt-3 border-top">
                            <small class="text-muted d-flex align-items-center justify-content-center">
                                <i class="bi bi-shield-check text-success me-2"></i>
                                Secure checkout with SSL encryption
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer spacer -->
    <div style="height: 100px;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cart management functions
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function updateQuantity(productId, quantity) {
            if (quantity < 1) {
                if (confirm('Remove this item from cart?')) {
                    removeFromCart(productId);
                }
                return;
            }
            
            showLoading();
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    location.reload(); // Refresh page to update totals
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('An error occurred while updating quantity');
            });
        }
        
        function removeFromCart(productId) {
            if (!confirm('Are you sure you want to remove this item?')) {
                return;
            }
            
            showLoading();
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_from_cart&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('An error occurred while removing item');
            });
        }
        
        function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            showLoading();
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_cart'
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('An error occurred while clearing cart');
            });
        }
        
        // Auto-save quantity changes with debounce
        let quantityTimeout;
        function debounceQuantityUpdate(productId, quantity) {
            clearTimeout(quantityTimeout);
            quantityTimeout = setTimeout(() => {
                updateQuantity(productId, quantity);
            }, 1000);
        }
    </script>
</body>
</html>