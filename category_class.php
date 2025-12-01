<?php
require_once 'config/database.php';

class Category {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get all categories (with school filtering for school admins)
     */
    public function getAllCategories($schoolId = null, $isGlobalAdmin = false) {
        try {
            if ($isGlobalAdmin) {
                // Super admin sees all categories
                $stmt = $this->db->prepare("
                    SELECT c.*, s.name as school_name 
                    FROM categories c 
                    LEFT JOIN schools s ON c.school_id = s.id 
                    ORDER BY c.category_name ASC
                ");
                $stmt->execute();
            } else if ($schoolId) {
                // School admin sees only their categories
                $stmt = $this->db->prepare("
                    SELECT * FROM categories 
                    WHERE school_id = ? OR school_id IS NULL 
                    ORDER BY category_name ASC
                ");
                $stmt->execute([$schoolId]);
            } else {
                // No school context - return global categories only
                $stmt = $this->db->prepare("
                    SELECT * FROM categories 
                    WHERE school_id IS NULL 
                    ORDER BY category_name ASC
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching categories: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get single category by ID
     */
    public function getCategoryById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching category by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add new category
     */
    public function addCategory($categoryName, $schoolId = null) {
        try {
            if (empty(trim($categoryName))) {
                return ['success' => false, 'message' => 'Category name is required'];
            }
            
            // Check if category already exists within the same school context
            if ($schoolId) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM categories 
                    WHERE LOWER(category_name) = LOWER(?) AND school_id = ?
                ");
                $stmt->execute([trim($categoryName), $schoolId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM categories 
                    WHERE LOWER(category_name) = LOWER(?) AND school_id IS NULL
                ");
                $stmt->execute([trim($categoryName)]);
            }
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Category already exists in this school'];
            }
            
            $stmt = $this->db->prepare("INSERT INTO categories (category_name, school_id) VALUES (?, ?)");
            
            if ($stmt->execute([trim($categoryName), $schoolId])) {
                return [
                    'success' => true, 
                    'message' => 'Category added successfully',
                    'category_id' => $this->db->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to add category'];
            }
        } catch (PDOException $e) {
            error_log("Error adding category: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Update category
     */
    public function updateCategory($id, $categoryName, $schoolId = null, $isGlobalAdmin = false) {
        try {
            if (empty(trim($categoryName))) {
                return ['success' => false, 'message' => 'Category name is required'];
            }
            
            // Check permissions - school admin can only edit their own categories
            if (!$isGlobalAdmin && $schoolId) {
                $stmt = $this->db->prepare("SELECT school_id FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $categorySchoolId = $stmt->fetchColumn();
                
                if ($categorySchoolId != $schoolId && $categorySchoolId !== null) {
                    return ['success' => false, 'message' => 'Access denied - cannot edit this category'];
                }
            }
            
            // Check if category name already exists in same school context (excluding current category)
            if ($schoolId && !$isGlobalAdmin) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM categories 
                    WHERE LOWER(category_name) = LOWER(?) AND id != ? AND school_id = ?
                ");
                $stmt->execute([trim($categoryName), $id, $schoolId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM categories 
                    WHERE LOWER(category_name) = LOWER(?) AND id != ?
                ");
                $stmt->execute([trim($categoryName), $id]);
            }
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Category name already exists'];
            }
            
            $stmt = $this->db->prepare("UPDATE categories SET category_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            
            if ($stmt->execute([trim($categoryName), $id])) {
                return ['success' => true, 'message' => 'Category updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update category'];
            }
        } catch (PDOException $e) {
            error_log("Error updating category: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Delete category
     */
    public function deleteCategory($id, $schoolId = null, $isGlobalAdmin = false) {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'Category ID is required'];
            }
            
            // Check permissions - school admin can only delete their own categories
            if (!$isGlobalAdmin && $schoolId) {
                $stmt = $this->db->prepare("SELECT school_id FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $categorySchoolId = $stmt->fetchColumn();
                
                if ($categorySchoolId != $schoolId && $categorySchoolId !== null) {
                    return ['success' => false, 'message' => 'Access denied - cannot delete this category'];
                }
            }
            
            // Check if category has associated brands
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM brands WHERE category_id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Cannot delete category that has associated brands'];
            }
            
            $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                return ['success' => true, 'message' => 'Category deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete category'];
            }
        } catch (PDOException $e) {
            error_log("Error deleting category: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Get category statistics
     */
    public function getCategoryStats($id) {
        try {
            $stats = [
                'brand_count' => 0,
                'product_count' => 0
            ];
            
            // Count brands
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM brands WHERE category_id = ?");
            $stmt->execute([$id]);
            $stats['brand_count'] = $stmt->fetchColumn();
            
            // Count products
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM products p 
                JOIN brands b ON p.brand_id = b.id 
                WHERE b.category_id = ?
            ");
            $stmt->execute([$id]);
            $stats['product_count'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting category stats: " . $e->getMessage());
            return ['brand_count' => 0, 'product_count' => 0];
        }
    }
}
?>