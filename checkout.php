<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'classes/cart_class.php';
require_once 'classes/paystack_payment.php';
require_once 'config/database.php';

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
    error_log("Checkout error: " . $e->getMessage());
    header('Location: store.php?error=cart_unavailable');
    exit;
}

// Redirect if cart is empty
if (empty($cartSummary['items'])) {
    header('Location: cart.php');
    exit;
}

// Calculate order totals
$subtotal = $cartSummary['total'];
$tax = $subtotal * 0.075; // 7.5% VAT
$shipping = $subtotal > 50 ? 0 : 10; // Free shipping above $50
$total = $subtotal + $tax + $shipping;

// Handle form submission
$orderPlaced = false;
$orderError = '';
$paystackData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Process order placement
    $customerDetails = [
        'school_id' => $user['school_id'],
        'name' => trim($_POST['customer_name'] ?? ''),
        'email' => trim($_POST['customer_email'] ?? ''),
        'phone' => trim($_POST['customer_phone'] ?? ''),
        'address' => trim($_POST['delivery_address'] ?? '')
    ];
    
    $paymentMethod = $_POST['payment_method'] ?? 'paystack';
    
    // Create order first
    $result = $cart->createOrder($user['user_id'], $customerDetails);
    
    if ($result['success']) {
        $orderId = $result['order_id'];
        $orderTotal = $result['order_total'];
        
        // If payment method is Paystack, initialize payment
        if ($paymentMethod === 'paystack') {
            try {
                $paystack = new PaystackPayment();
                
                // Generate unique reference
                $reference = PaystackPayment::generateReference('ORDER');
                
                // Initialize payment
                $paymentResult = $paystack->initializePayment(
                    $customerDetails['email'],
                    $orderTotal,
                    $reference,
                    [
                        'order_id' => $orderId,
                        'customer_name' => $customerDetails['name'],
                        'school_id' => $user['school_id']
                    ]
                );
                
                if ($paymentResult && $paymentResult['status'] === true) {
                    // Store payment data for frontend redirect
                    $paystackData = [
                        'authorization_url' => $paymentResult['data']['authorization_url'],
                        'access_code' => $paymentResult['data']['access_code'],
                        'reference' => $paymentResult['data']['reference']
                    ];
                    
                    // Update order with payment reference
                    try {
                        $database = new Database();
                        $pdo = $database->getConnection();
                        $stmt = $pdo->prepare("UPDATE orders SET payment_reference = ? WHERE id = ?");
                        $stmt->execute([$reference, $orderId]);
                    } catch (Exception $e) {
                        error_log('Error updating payment reference: ' . $e->getMessage());
                    }
                } else {
                    $orderError = 'Payment initialization failed. Please try again.';
                }
            } catch (Exception $e) {
                error_log('Paystack error: ' . $e->getMessage());
                $orderError = 'Payment system error. Please try again later.';
            }
        } else {
            // Manual payment methods
            $orderPlaced = true;
        }
    } else {
        $orderError = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .checkout-steps {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        
        .checkout-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .order-summary {
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 2rem;
            position: sticky;
            top: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1em;
            border-top: 2px solid #dee2e6;
            padding-top: 1rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .payment-methods {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .payment-option {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .payment-option:hover, .payment-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .success-animation {
            animation: successPulse 2s ease-in-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .order-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 3rem 2rem;
            text-align: center;
            margin: 2rem 0;
        }
        
        @media (max-width: 768px) {
            .checkout-steps {
                padding: 1rem;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
                margin-bottom: 1.5rem;
            }
            
            .step-number {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">
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
                    <li class="nav-item">
                        <a class="nav-link text-white" href="store.php">
                            <i class="bi bi-shop"></i> Store
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="cart.php">
                            <i class="bi bi-cart3"></i> Cart
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

    <!-- Checkout Header -->
    <div class="checkout-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="bi bi-credit-card me-3"></i>Checkout
                    </h1>
                    <p class="mb-0 opacity-75">Complete your purchase securely</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="h5 mb-0">Order Total: $<?php echo number_format($total, 2); ?></div>
                    <small class="opacity-75"><?php echo $cartSummary['count']; ?> items in your order</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($orderPlaced): ?>
            <!-- Order Success -->
            <div class="order-success success-animation">
                <i class="bi bi-check-circle display-1 mb-4"></i>
                <h2 class="mb-3">Order Placed Successfully!</h2>
                <p class="lead mb-4">Thank you for your purchase. Your order #<?php echo $orderId; ?> has been received.</p>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="bg-white bg-opacity-10 rounded p-4 mb-4">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <h5>Order Number</h5>
                                    <p class="mb-0">#<?php echo $orderId; ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h5>Total Amount</h5>
                                    <p class="mb-0">$<?php echo number_format($orderTotal, 2); ?></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h5>Status</h5>
                                    <p class="mb-0">Processing</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="store.php" class="btn btn-light btn-lg">
                        <i class="bi bi-shop"></i> Continue Shopping
                    </a>
                    <a href="user/dashboard.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-speedometer2"></i> View Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Checkout Steps -->
            <div class="checkout-steps">
                <div class="row">
                    <div class="col-md-4">
                        <div class="step completed">
                            <div class="step-number">
                                <i class="bi bi-check"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Cart Review</h6>
                                <small class="text-muted">Items selected</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step active">
                            <div class="step-number">2</div>
                            <div>
                                <h6 class="mb-0">Checkout</h6>
                                <small class="text-muted">Delivery & payment</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step">
                            <div class="step-number">3</div>
                            <div>
                                <h6 class="mb-0">Confirmation</h6>
                                <small class="text-muted">Order complete</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($orderError): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($orderError); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Checkout Form -->
                <div class="col-lg-8">
                    <form method="POST" action="">
                        <!-- Customer Information -->
                        <div class="checkout-section">
                            <h4 class="mb-4">
                                <i class="bi bi-person-fill me-2"></i>Customer Information
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="customer_email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="customer_email" name="customer_email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="customer_phone" name="customer_phone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="delivery_address" class="form-label">Delivery Address</label>
                                    <input type="text" class="form-control" id="delivery_address" name="delivery_address" 
                                           placeholder="Enter your delivery address">
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="checkout-section">
                            <h4 class="mb-4">
                                <i class="bi bi-credit-card me-2"></i>Payment Method
                            </h4>
                            
                            <div class="payment-methods">
                                <div class="payment-option selected" data-method="paystack">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="payment_method" value="paystack" id="paystack" checked class="me-3">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Pay with Paystack</h6>
                                            <small class="text-muted">Secure payment with cards, bank transfer, USSD</small>
                                        </div>
                                        <div class="text-end">
                                            <i class="bi bi-shield-check text-success h5 mb-0"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="payment-option" data-method="bank">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="payment_method" value="bank" id="bank" class="me-3">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">Bank Transfer</h6>
                                            <small class="text-muted">Direct bank transfer (Manual verification required)</small>
                                        </div>
                                        <div class="text-end">
                                            <i class="bi bi-bank text-primary h5 mb-0"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Payment Integration:</strong> Paystack payment gateway will be integrated soon. 
                                For now, orders will be processed manually.
                            </div>
                        </div>

                        <!-- Place Order Button -->
                        <div class="checkout-section">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="cart.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Cart
                                </a>
                                
                                <button type="submit" name="place_order" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Place Order ($<?php echo number_format($total, 2); ?>)
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h5 class="mb-4">
                            <i class="bi bi-receipt me-2"></i>Order Summary
                        </h5>
                        
                        <!-- Order Items -->
                        <div class="mb-4">
                            <?php foreach ($cartSummary['items'] as $item): ?>
                                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                    <div class="me-3">
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                                 class="rounded" width="50" height="50" style="object-fit: cover;" 
                                                 alt="<?php echo htmlspecialchars($item['title']); ?>">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="bi bi-box text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 small"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <small class="text-muted">
                                            Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Totals -->
                        <div class="summary-item">
                            <span>Subtotal (<?php echo $cartSummary['count']; ?> items)</span>
                            <span>$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Tax (7.5%)</span>
                            <span>$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Shipping <?php echo $subtotal > 50 ? '(Free)' : ''; ?></span>
                            <span><?php echo $shipping > 0 ? '$' . number_format($shipping, 2) : 'Free'; ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Total</span>
                            <span>$<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <!-- Security Notice -->
                        <div class="text-center mt-4 pt-3 border-top">
                            <small class="text-muted d-flex align-items-center justify-content-center">
                                <i class="bi bi-shield-lock text-success me-2"></i>
                                Your payment information is secure
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
        // Payment method selection
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['customer_name', 'customer_email'];
            let hasErrors = false;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    hasErrors = true;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        <?php if ($paystackData): ?>
        // Redirect to Paystack payment page
        window.location.href = '<?php echo $paystackData['authorization_url']; ?>';
        <?php endif; ?>
    </script>
</body>
</html>