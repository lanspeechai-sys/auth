<?php
session_start();
require_once 'includes/auth.php';
require_once 'brand_class.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

class BrandController {
    private $brand;
    
    public function __construct() {
        $this->brand = new Brand();
    }
    
    /**
     * Handle different brand actions
     */
    public function handleRequest() {
        header('Content-Type: application/json');
        
        // Allow both GET and POST requests
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        if (empty($action)) {
            echo json_encode(['success' => false, 'message' => 'No action specified']);
            return;
        }
        
        switch ($action) {
            case 'fetch_brands':
                $this->fetchBrands();
                break;
            case 'add_brand':
                $this->addBrand();
                break;
            case 'update_brand':
                $this->updateBrand();
                break;
            case 'delete_brand':
                $this->deleteBrand();
                break;
            case 'get_brand':
                $this->getBrand();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
    
    /**
     * Fetch all brands grouped by category
     */
    private function fetchBrands() {
        try {
            // Get user information
            $user = getCurrentUser();
            $schoolId = null;
            $isGlobalAdmin = false;
            
            if ($user) {
                if (isSuperAdmin()) {
                    $isGlobalAdmin = true;
                } else if (isSchoolAdmin()) {
                    $schoolId = $user['school_id'];
                }
            }
            
            $brands = $this->brand->getAllBrands($schoolId, $isGlobalAdmin);
            
            if ($brands !== false) {
                // Group brands by category for frontend display
                $groupedBrands = [];
                foreach ($brands as $brand) {
                    $categoryName = $brand['category_name'] ?? 'Uncategorized';
                    if (!isset($groupedBrands[$categoryName])) {
                        $groupedBrands[$categoryName] = [];
                    }
                    $groupedBrands[$categoryName][] = $brand;
                }
                
                echo json_encode([
                    'success' => true, 
                    'brands' => $groupedBrands,
                    'data' => $brands
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch brands']);
            }
        } catch (Exception $e) {
            error_log("Error in fetchBrands: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Server error occurred', 
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Add new brand
     */
    private function addBrand() {
        try {
            $brandName = trim($_POST['brand_name'] ?? '');
            $categoryId = intval($_POST['category_id'] ?? 0);
            
            // Validate inputs on controller level
            if (empty($brandName)) {
                echo json_encode(['success' => false, 'message' => 'Brand name is required']);
                return;
            }
            
            if ($categoryId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a category']);
                return;
            }
            
            // Get user information for school context
            $user = getCurrentUser();
            $schoolId = null;
            
            if ($user && isSchoolAdmin()) {
                $schoolId = $user['school_id'];
            }
            
            $result = $this->brand->addBrand($brandName, $categoryId, $schoolId);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error in addBrand: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Error adding brand', 
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update existing brand
     */
    private function updateBrand() {
        $id = intval($_POST['brand_id'] ?? 0);
        $brandName = trim($_POST['brand_name'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        
        $result = $this->brand->updateBrand($id, $brandName, $categoryId);
        echo json_encode($result);
    }
    
    /**
     * Delete brand
     */
    private function deleteBrand() {
        $id = intval($_POST['brand_id'] ?? 0);
        
        $result = $this->brand->deleteBrand($id);
        echo json_encode($result);
    }
    
    /**
     * Get single brand for editing
     */
    private function getBrand() {
        $id = intval($_POST['brand_id'] ?? 0);
        
        $brand = $this->brand->getBrandById($id);
        if ($brand) {
            echo json_encode(['success' => true, 'brand' => $brand]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Brand not found']);
        }
    }
}

// Instantiate and handle request
$controller = new BrandController();
$controller->handleRequest();
?>