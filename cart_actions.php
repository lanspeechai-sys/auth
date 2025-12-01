<?php
/**
 * Cart Actions Handler
 * Handles AJAX requests for cart operations
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

require_once 'classes/cart_class.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

try {
    $cart = new Cart();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Get user's school context
    $user = $_SESSION;
    $schoolId = $user['school_id'] ?? null;
    
    switch ($action) {
        case 'add_to_cart':
            $productId = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? $_GET['quantity'] ?? 1);
            
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                break;
            }
            
            if ($quantity <= 0) {
                $quantity = 1;
            }
            
            $result = $cart->addToCart($productId, $quantity, $schoolId);
            echo json_encode($result);
            break;
            
        case 'remove_from_cart':
            $productId = intval($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
            
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                break;
            }
            
            $result = $cart->removeFromCart($productId);
            echo json_encode($result);
            break;
            
        case 'update_quantity':
            $productId = intval($_POST['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 0);
            
            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                break;
            }
            
            $result = $cart->updateQuantity($productId, $quantity);
            echo json_encode($result);
            break;
            
        case 'get_cart':
            $summary = $cart->getCartSummary();
            echo json_encode([
                'success' => true,
                'data' => $summary
            ]);
            break;
            
        case 'get_cart_count':
            $count = $cart->getCartCount();
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        case 'clear_cart':
            $result = $cart->clearCart();
            echo json_encode($result);
            break;
            
        case 'create_order':
            $userId = $_SESSION['user_id'];
            
            // Get customer details from POST data
            $customerDetails = [
                'school_id' => $schoolId,
                'name' => trim($_POST['customer_name'] ?? ''),
                'email' => trim($_POST['customer_email'] ?? ''),
                'phone' => trim($_POST['customer_phone'] ?? ''),
                'address' => trim($_POST['delivery_address'] ?? '')
            ];
            
            // Validate required fields
            if (empty($customerDetails['name']) || empty($customerDetails['email'])) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Customer name and email are required'
                ]);
                break;
            }
            
            // Validate email format
            if (!filter_var($customerDetails['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Invalid email format'
                ]);
                break;
            }
            
            $result = $cart->createOrder($userId, $customerDetails);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Cart Action Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your request'
    ]);
}
?>