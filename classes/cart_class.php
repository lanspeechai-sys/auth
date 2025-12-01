<?php
/**
 * Cart Management Class
 * Handles shopping cart functionality with session storage and database persistence
 */

require_once dirname(__DIR__) . '/config/database.php';

class Cart {
    private $pdo;
    
    public function __construct() {
        try {
            $database = new Database();
            $this->pdo = $database->getConnection();
            
            if (!$this->pdo) {
                throw new Exception("Failed to connect to database");
            }
        } catch (Exception $e) {
            error_log("Database connection error in Cart class: " . $e->getMessage());
            throw new Exception("Cart functionality is currently unavailable");
        }
        
        // Initialize session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize cart session if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }
    
    /**
     * Add item to cart
     */
    public function addToCart($productId, $quantity = 1, $schoolId = null) {
        try {
            // Validate product exists and belongs to school
            $product = $this->getProductDetails($productId, $schoolId);
            if (!$product) {
                return ['success' => false, 'message' => 'Product not found or unavailable'];
            }
            
            // Check if item already in cart
            $existingKey = $this->findCartItem($productId);
            
            if ($existingKey !== false) {
                // Update quantity
                $_SESSION['cart'][$existingKey]['quantity'] += $quantity;
            } else {
                // Add new item
                $_SESSION['cart'][] = [
                    'product_id' => $productId,
                    'title' => $product['title'],
                    'price' => $product['price'],
                    'quantity' => $quantity,
                    'image_path' => $product['image_path'],
                    'school_id' => $product['school_id'],
                    'added_at' => date('Y-m-d H:i:s')
                ];
            }
            
            return [
                'success' => true, 
                'message' => 'Item added to cart successfully',
                'cart_count' => $this->getCartCount(),
                'cart_total' => $this->getCartTotal()
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error adding item to cart: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove item from cart
     */
    public function removeFromCart($productId) {
        try {
            $key = $this->findCartItem($productId);
            
            if ($key !== false) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
                
                return [
                    'success' => true, 
                    'message' => 'Item removed from cart',
                    'cart_count' => $this->getCartCount(),
                    'cart_total' => $this->getCartTotal()
                ];
            }
            
            return ['success' => false, 'message' => 'Item not found in cart'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error removing item: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update item quantity in cart
     */
    public function updateQuantity($productId, $quantity) {
        try {
            if ($quantity <= 0) {
                return $this->removeFromCart($productId);
            }
            
            $key = $this->findCartItem($productId);
            
            if ($key !== false) {
                $_SESSION['cart'][$key]['quantity'] = $quantity;
                
                return [
                    'success' => true, 
                    'message' => 'Quantity updated',
                    'cart_count' => $this->getCartCount(),
                    'cart_total' => $this->getCartTotal()
                ];
            }
            
            return ['success' => false, 'message' => 'Item not found in cart'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error updating quantity: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all cart items
     */
    public function getCartItems() {
        return $_SESSION['cart'] ?? [];
    }
    
    /**
     * Get cart item count
     */
    public function getCartCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }
    
    /**
     * Get cart total amount
     */
    public function getCartTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += ($item['price'] * $item['quantity']);
        }
        return $total;
    }
    
    /**
     * Clear entire cart
     */
    public function clearCart() {
        $_SESSION['cart'] = [];
        return ['success' => true, 'message' => 'Cart cleared'];
    }
    
    /**
     * Get cart summary
     */
    public function getCartSummary() {
        $items = $this->getCartItems();
        $count = $this->getCartCount();
        $total = $this->getCartTotal();
        
        return [
            'items' => $items,
            'count' => $count,
            'total' => $total,
            'formatted_total' => number_format($total, 2)
        ];
    }
    
    /**
     * Create order from cart (for checkout)
     */
    public function createOrder($userId, $customerDetails) {
        try {
            if (empty($_SESSION['cart'])) {
                return ['success' => false, 'message' => 'Cart is empty'];
            }
            
            $this->pdo->beginTransaction();
            
            // Create order record
            $orderTotal = $this->getCartTotal();
            $orderData = [
                'user_id' => $userId,
                'school_id' => $customerDetails['school_id'],
                'customer_name' => $customerDetails['name'],
                'customer_email' => $customerDetails['email'],
                'customer_phone' => $customerDetails['phone'] ?? null,
                'delivery_address' => $customerDetails['address'] ?? null,
                'order_total' => $orderTotal,
                'status' => 'pending',
                'order_date' => date('Y-m-d H:i:s')
            ];
            
            $sql = "INSERT INTO orders (user_id, school_id, customer_name, customer_email, customer_phone, delivery_address, order_total, status, order_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($orderData));
            $orderId = $this->pdo->lastInsertId();
            
            // Create order items
            foreach ($_SESSION['cart'] as $item) {
                $itemSql = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                           VALUES (?, ?, ?, ?, ?)";
                
                $subtotal = $item['price'] * $item['quantity'];
                $itemStmt = $this->pdo->prepare($itemSql);
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $subtotal
                ]);
            }
            
            $this->pdo->commit();
            
            // Clear cart after successful order
            $this->clearCart();
            
            return [
                'success' => true, 
                'message' => 'Order created successfully',
                'order_id' => $orderId,
                'order_total' => $orderTotal
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            return ['success' => false, 'message' => 'Error creating order: ' . $e->getMessage()];
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function findCartItem($productId) {
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $productId) {
                return $key;
            }
        }
        return false;
    }
    
    private function getProductDetails($productId, $schoolId = null) {
        try {
            $sql = "SELECT id, title, price, image_path, school_id FROM products WHERE id = ?";
            $params = [$productId];
            
            if ($schoolId) {
                $sql .= " AND school_id = ?";
                $params[] = $schoolId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return false;
        }
    }
}
?>