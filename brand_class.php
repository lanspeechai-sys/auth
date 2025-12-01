<?php
require_once 'config/database.php';

class Brand {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get all brands with category information (with school filtering)
     */
    public function getAllBrands($schoolId = null, $isGlobalAdmin = false) {
        try {
            if ($isGlobalAdmin) {
                // Super admin sees all brands
                $stmt = $this->db->prepare("
                    SELECT b.id, b.brand_name, b.category_id, c.category_name, 
                           b.created_at, b.updated_at, b.school_id, s.name as school_name
                    FROM brands b 
                    LEFT JOIN categories c ON b.category_id = c.id 
                    LEFT JOIN schools s ON b.school_id = s.id 
                    ORDER BY c.category_name, b.brand_name
                ");
                $stmt->execute();
            } else if ($schoolId) {
                // School admin sees only their brands and global brands
                $stmt = $this->db->prepare("
                    SELECT b.id, b.brand_name, b.category_id, c.category_name, 
                           b.created_at, b.updated_at, b.school_id
                    FROM brands b 
                    LEFT JOIN categories c ON b.category_id = c.id 
                    WHERE b.school_id = ? OR b.school_id IS NULL 
                    ORDER BY c.category_name, b.brand_name
                ");
                $stmt->execute([$schoolId]);
            } else {
                // No school context - return global brands only
                $stmt = $this->db->prepare("
                    SELECT b.id, b.brand_name, b.category_id, c.category_name, 
                           b.created_at, b.updated_at, b.school_id
                    FROM brands b 
                    LEFT JOIN categories c ON b.category_id = c.id 
                    WHERE b.school_id IS NULL 
                    ORDER BY c.category_name, b.brand_name
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching brands: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get brands grouped by category
     */
    public function getBrandsByCategory() {
        try {
            $brands = $this->getAllBrands();
            $grouped = [];
            
            foreach ($brands as $brand) {
                $category = $brand['category_name'] ?: 'Uncategorized';
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                $grouped[$category][] = $brand;
            }
            
            return $grouped;
        } catch (Exception $e) {
            error_log("Error grouping brands: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get single brand by ID
     */
    public function getBrandById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, c.category_name 
                FROM brands b 
                LEFT JOIN categories c ON b.category_id = c.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching brand by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if brand name + category combination exists in the same school context
     */
    public function brandExists($brandName, $categoryId, $schoolId = null, $excludeId = null) {
        try {
            $sql = "SELECT id FROM brands WHERE brand_name = ? AND category_id = ?";
            $params = [$brandName, $categoryId];
            
            // Add school context check
            if ($schoolId) {
                $sql .= " AND school_id = ?";
                $params[] = $schoolId;
            } else {
                $sql .= " AND school_id IS NULL";
            }
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Error checking brand existence: " . $e->getMessage());
            return true; // Fail safely
        }
    }
    
    /**
     * Add new brand
     */
    public function addBrand($brandName, $categoryId, $schoolId = null) {
        try {
            // Validate input
            if (empty($brandName) || $categoryId <= 0) {
                return ['success' => false, 'message' => 'All fields are required'];
            }
            
            // Check if brand already exists in this category and school context
            if ($this->brandExists($brandName, $categoryId, $schoolId)) {
                return ['success' => false, 'message' => 'Brand already exists in this category'];
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO brands (brand_name, category_id, school_id) 
                VALUES (?, ?, ?)
            ");
            
            if ($stmt->execute([$brandName, $categoryId, $schoolId])) {
                return [
                    'success' => true, 
                    'message' => 'Brand added successfully',
                    'brand_id' => $this->db->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to add brand'];
            }
        } catch (PDOException $e) {
            error_log("Error adding brand: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Update brand
     */
    public function updateBrand($id, $brandName, $categoryId) {
        try {
            // Validate input
            if (empty($brandName) || empty($categoryId) || empty($id)) {
                return ['success' => false, 'message' => 'All fields are required'];
            }
            
            // Check if brand exists with different ID
            if ($this->brandExists($brandName, $categoryId, $id)) {
                return ['success' => false, 'message' => 'Brand already exists in this category'];
            }
            
            $stmt = $this->db->prepare("
                UPDATE brands 
                SET brand_name = ?, category_id = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$brandName, $categoryId, $id])) {
                return ['success' => true, 'message' => 'Brand updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update brand'];
            }
        } catch (PDOException $e) {
            error_log("Error updating brand: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Delete brand
     */
    public function deleteBrand($id) {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'Brand ID is required'];
            }
            
            // Check if brand is used in products
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
            $stmt->execute([$id]);
            $productCount = $stmt->fetchColumn();
            
            if ($productCount > 0) {
                return [
                    'success' => false, 
                    'message' => "Cannot delete brand. It is used by {$productCount} product(s)"
                ];
            }
            
            $stmt = $this->db->prepare("DELETE FROM brands WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                return ['success' => true, 'message' => 'Brand deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete brand'];
            }
        } catch (PDOException $e) {
            error_log("Error deleting brand: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Get all categories for dropdown
     */
    public function getAllCategories() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, category_name 
                FROM categories 
                ORDER BY category_name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching categories: " . $e->getMessage());
            return [];
        }
    }
}
?>