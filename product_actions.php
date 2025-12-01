<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'product_class.php';
require_once 'includes/auth.php';

// Create product instance
$product = new Product();

// Get request parameters
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$page = intval($_GET['page'] ?? $_POST['page'] ?? 1);
$limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 10);

// Get school context for students only
$schoolId = null;
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'student') {
        $schoolId = $user['school_id'];
    }
}

try {
    switch ($action) {
        case 'get_all_products':
            $result = $product->view_all_products($schoolId, $page, $limit);
            if ($result === false) {
                throw new Exception('Error fetching products');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'search_products':
            $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
            
            if (empty($query)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Search query is required'
                ]);
                break;
            }
            
            $result = $product->search_products($query, $schoolId, $page, $limit);
            if ($result === false) {
                throw new Exception('Error searching products');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'filter_by_category':
            $categoryId = intval($_GET['category_id'] ?? $_POST['category_id'] ?? 0);
            
            if ($categoryId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Valid category ID is required'
                ]);
                break;
            }
            
            $result = $product->filter_products_by_category($categoryId, $schoolId, $page, $limit);
            if ($result === false) {
                throw new Exception('Error filtering products by category');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'filter_by_brand':
            $brandId = intval($_GET['brand_id'] ?? $_POST['brand_id'] ?? 0);
            
            if ($brandId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Valid brand ID is required'
                ]);
                break;
            }
            
            $result = $product->filter_products_by_brand($brandId, $schoolId, $page, $limit);
            if ($result === false) {
                throw new Exception('Error filtering products by brand');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'get_single_product':
            $productId = intval($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
            
            if ($productId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Valid product ID is required'
                ]);
                break;
            }
            
            $result = $product->view_single_product($productId, $schoolId);
            if ($result === false || empty($result)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Product not found'
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'get_categories':
            $result = $product->getAllCategoriesForFilter($schoolId);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'get_brands':
            $result = $product->getAllBrandsForFilter($schoolId);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'get_filter_options':
            $categories = $product->getAllCategoriesForFilter($schoolId);
            $brands = $product->getAllBrandsForFilter($schoolId);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'brands' => $brands
                ]
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Product actions error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}
?>