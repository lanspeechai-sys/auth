<?php
/**
 * Paystack Payment Callback Handler
 * Handles payment verification after user completes payment
 */

session_start();

require_once 'classes/paystack_payment.php';
require_once 'classes/cart_class.php';
require_once 'config/database.php';

// Get payment reference from URL
$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    header('Location: checkout.php?error=invalid_reference');
    exit;
}

// Initialize Paystack
$paystack = new PaystackPayment();

// Verify payment
$verification = $paystack->verifyPayment($reference);

if (!$verification || !isset($verification['status'])) {
    header('Location: checkout.php?error=verification_failed');
    exit;
}

if ($verification['status'] === false) {
    $errorMessage = $verification['message'] ?? 'Payment verification failed';
    header('Location: checkout.php?error=' . urlencode($errorMessage));
    exit;
}

// Check if payment was successful
if ($verification['data']['status'] !== 'success') {
    header('Location: checkout.php?error=payment_failed');
    exit;
}

// Payment successful - Update order in database
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get order ID from metadata
    $orderId = $verification['data']['metadata']['order_id'] ?? 0;
    
    if ($orderId > 0) {
        // Update order payment status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                payment_method = 'paystack',
                payment_reference = ?,
                status = 'processing',
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$reference, $orderId]);
        
        // Clear cart after successful payment
        if (isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Redirect to success page
        header('Location: payment_success.php?order_id=' . $orderId . '&reference=' . $reference);
        exit;
    } else {
        // No order ID found
        header('Location: checkout.php?error=order_not_found');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Payment callback error: ' . $e->getMessage());
    header('Location: checkout.php?error=database_error');
    exit;
}
?>