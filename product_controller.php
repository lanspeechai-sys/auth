<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'product_class.php';

// Ensure user is authenticated and is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

class ProductController {
    private $product;
    
    public function __construct() {
        $this->product = new Product();
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'fetch':
                $this->fetchProducts();
                break;
            case 'add':
                $this->addProduct();
                break;
            case 'update':
                $this->updateProduct();
                break;
            case 'delete':
                $this->deleteProduct();
                break;
            case 'get_brands':
                $this->getBrandsByCategory();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
    
    private function fetchProducts() {
        $schoolId = $_SESSION['school_id'] ?? null;
        $products = $this->product->getAllProducts($schoolId);
        
        if ($products !== false) {
            echo json_encode(['success' => true, 'data' => $products]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch products']);
        }
    }
    
    private function addProduct() {
        try {
            $data = [
                'category_id' => $_POST['category_id'] ?? '',
                'brand_id' => $_POST['brand_id'] ?? '',
                'title' => trim($_POST['title'] ?? ''),
                'price' => $_POST['price'] ?? '',
                'description' => trim($_POST['description'] ?? ''),
                'keywords' => trim($_POST['keywords'] ?? ''),
                'created_by' => $_SESSION['user_id'],
                'school_id' => $_SESSION['school_id']
            ];
            
            // Add product first to get product ID
            $result = $this->product->addProduct($data);
            
            if ($result['success']) {
                $productId = $result['product_id'];
                
                // Handle image upload if provided
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $imageResult = $this->product->handleImageUpload($_FILES['image'], $_SESSION['user_id'], $productId);
                    
                    if ($imageResult['success']) {
                        // Update product with image path
                        $updateResult = $this->product->updateProduct($productId, [
                            'category_id' => $data['category_id'],
                            'brand_id' => $data['brand_id'],
                            'title' => $data['title'],
                            'price' => $data['price'],
                            'description' => $data['description'],
                            'keywords' => $data['keywords'],
                            'image_path' => $imageResult['image_path']
                        ]);
                        
                        if (!$updateResult['success']) {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'Product added but image update failed'
                            ]);
                            return;
                        }
                    } else {
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Product added but image upload failed: ' . $imageResult['message']
                        ]);
                        return;
                    }
                }
                
                echo json_encode($result);
            } else {
                echo json_encode($result);
            }
        } catch (Exception $e) {
            error_log("Error in addProduct: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function updateProduct() {
        try {
            $productId = $_POST['product_id'] ?? '';
            
            if (empty($productId)) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                return;
            }
            
            $data = [
                'category_id' => $_POST['category_id'] ?? '',
                'brand_id' => $_POST['brand_id'] ?? '',
                'title' => trim($_POST['title'] ?? ''),
                'price' => $_POST['price'] ?? '',
                'description' => trim($_POST['description'] ?? ''),
                'keywords' => trim($_POST['keywords'] ?? '')
            ];
            
            // Handle image upload if provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageResult = $this->product->handleImageUpload($_FILES['image'], $_SESSION['user_id'], $productId);
                
                if ($imageResult['success']) {
                    $data['image_path'] = $imageResult['image_path'];
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Image upload failed: ' . $imageResult['message']
                    ]);
                    return;
                }
            }
            
            $result = $this->product->updateProduct($productId, $data);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error in updateProduct: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function deleteProduct() {
        try {
            $productId = $_POST['product_id'] ?? '';
            
            if (empty($productId)) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                return;
            }
            
            $result = $this->product->deleteProduct($productId);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error in deleteProduct: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function getBrandsByCategory() {
        try {
            $categoryId = $_GET['category_id'] ?? '';
            
            if (empty($categoryId)) {
                echo json_encode(['success' => false, 'message' => 'Category ID is required']);
                return;
            }
            
            $brands = $this->product->getBrandsByCategory($categoryId);
            echo json_encode(['success' => true, 'data' => $brands]);
        } catch (Exception $e) {
            error_log("Error in getBrandsByCategory: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }

    // PUBLIC METHODS FOR FRONTEND (No authentication required)
    
    /**
     * Get all products for public display
     */
    public function getAllProductsPublic($schoolId = null, $page = 1, $limit = 10) {
        try {
            $result = $this->product->view_all_products($schoolId, $page, $limit);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'Error fetching products',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Controller error in getAllProductsPublic: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while fetching products',
                'data' => []
            ];
        }
    }
    
    /**
     * Search products (public)
     */
    public function searchProductsPublic($query, $schoolId = null, $page = 1, $limit = 10) {
        try {
            if (empty(trim($query))) {
                return [
                    'success' => false,
                    'message' => 'Search query cannot be empty',
                    'data' => []
                ];
            }
            
            $result = $this->product->search_products($query, $schoolId, $page, $limit);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'Error searching products',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Search completed successfully',
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Controller error in searchProductsPublic: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during search',
                'data' => []
            ];
        }
    }
    
    /**
     * Filter products by category (public)
     */
    public function filterByCategoryPublic($categoryId, $schoolId = null, $page = 1, $limit = 10) {
        try {
            if (empty($categoryId) || !is_numeric($categoryId)) {
                return [
                    'success' => false,
                    'message' => 'Invalid category ID',
                    'data' => []
                ];
            }
            
            $result = $this->product->filter_products_by_category($categoryId, $schoolId, $page, $limit);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'Error filtering products by category',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Products filtered successfully',
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Controller error in filterByCategoryPublic: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while filtering products',
                'data' => []
            ];
        }
    }
    
    /**
     * Filter products by brand (public)
     */
    public function filterByBrandPublic($brandId, $schoolId = null, $page = 1, $limit = 10) {
        try {
            if (empty($brandId) || !is_numeric($brandId)) {
                return [
                    'success' => false,
                    'message' => 'Invalid brand ID',
                    'data' => []
                ];
            }
            
            $result = $this->product->filter_products_by_brand($brandId, $schoolId, $page, $limit);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'Error filtering products by brand',
                    'data' => []
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Products filtered successfully',
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Controller error in filterByBrandPublic: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while filtering products',
                'data' => []
            ];
        }
    }
    
    /**
     * Get single product (public)
     */
    public function getSingleProductPublic($productId, $schoolId = null) {
        try {
            if (empty($productId) || !is_numeric($productId)) {
                return [
                    'success' => false,
                    'message' => 'Invalid product ID',
                    'data' => null
                ];
            }
            
            $result = $this->product->view_single_product($productId, $schoolId);
            
            if ($result === false || empty($result)) {
                return [
                    'success' => false,
                    'message' => 'Product not found',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Controller error in getSingleProductPublic: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while fetching product',
                'data' => null
            ];
        }
    }
}

$controller = new ProductController();
$controller->handleRequest();
?>