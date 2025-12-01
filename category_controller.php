<?php
session_start();
require_once 'includes/auth.php';
require_once 'category_class.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

class CategoryController {
    private $category;
    
    public function __construct() {
        $this->category = new Category();
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        if (empty($action)) {
            echo json_encode(['success' => false, 'message' => 'No action specified']);
            return;
        }
        
        switch ($action) {
            case 'fetch':
                $this->fetchCategories();
                break;
            case 'add':
                $this->addCategory();
                break;
            case 'update':
                $this->updateCategory();
                break;
            case 'delete':
                $this->deleteCategory();
                break;
            case 'get':
                $this->getCategory();
                break;
            case 'stats':
                $this->getCategoryStats();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
    
    private function fetchCategories() {
        try {
            $schoolId = $_SESSION['school_id'] ?? null;
            $isGlobalAdmin = isSuperAdmin();
            
            $categories = $this->category->getAllCategories($schoolId, $isGlobalAdmin);
            
            if ($categories !== false) {
                // Add stats for each category
                foreach ($categories as &$category) {
                    $stats = $this->category->getCategoryStats($category['id']);
                    $category['brand_count'] = $stats['brand_count'];
                    $category['product_count'] = $stats['product_count'];
                }
                
                echo json_encode(['success' => true, 'data' => $categories]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch categories']);
            }
        } catch (Exception $e) {
            error_log("Error in fetchCategories: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function addCategory() {
        try {
            $categoryName = trim($_POST['category_name'] ?? '');
            
            if (empty($categoryName)) {
                echo json_encode(['success' => false, 'message' => 'Category name is required']);
                return;
            }
            
            // School admins create categories for their school, super admins can create global categories
            $schoolId = isSchoolAdmin() ? $_SESSION['school_id'] : null;
            
            $result = $this->category->addCategory($categoryName, $schoolId);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error in addCategory: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function updateCategory() {
        try {
            $categoryId = $_POST['category_id'] ?? '';
            $categoryName = trim($_POST['category_name'] ?? '');
            
            if (empty($categoryId)) {
                echo json_encode(['success' => false, 'message' => 'Category ID is required']);
                return;
            }
            
            if (empty($categoryName)) {
                echo json_encode(['success' => false, 'message' => 'Category name is required']);
                return;
            }
            
            $schoolId = $_SESSION['school_id'] ?? null;
            $isGlobalAdmin = isSuperAdmin();
            
            $result = $this->category->updateCategory($categoryId, $categoryName, $schoolId, $isGlobalAdmin);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error in updateCategory: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function deleteCategory() {
        try {
            $categoryId = $_POST['category_id'] ?? '';
            
            if (empty($categoryId)) {
                echo json_encode(['success' => false, 'message' => 'Category ID is required']);
                return;
            }
            
            $schoolId = $_SESSION['school_id'] ?? null;
            $isGlobalAdmin = isSuperAdmin();
            
            $result = $this->category->deleteCategory($categoryId, $schoolId, $isGlobalAdmin);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error in deleteCategory: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function getCategory() {
        try {
            $categoryId = $_GET['category_id'] ?? $_POST['category_id'] ?? '';
            
            if (empty($categoryId)) {
                echo json_encode(['success' => false, 'message' => 'Category ID is required']);
                return;
            }
            
            $category = $this->category->getCategoryById($categoryId);
            
            if ($category) {
                echo json_encode(['success' => true, 'data' => $category]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Category not found']);
            }
        } catch (Exception $e) {
            error_log("Error in getCategory: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
    
    private function getCategoryStats() {
        try {
            $categoryId = $_GET['category_id'] ?? '';
            
            if (empty($categoryId)) {
                echo json_encode(['success' => false, 'message' => 'Category ID is required']);
                return;
            }
            
            $stats = $this->category->getCategoryStats($categoryId);
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            error_log("Error in getCategoryStats: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    }
}

// Instantiate and handle request
$controller = new CategoryController();
$controller->handleRequest();
?>