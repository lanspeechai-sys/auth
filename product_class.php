<?php
require_once 'config/database.php';

class Product {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get all products with category and brand information
     */
    public function getAllProducts($schoolId = null) {
        try {
            $sql = "
                SELECT p.*, c.category_name, b.brand_name, u.name as creator_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN users u ON p.created_by = u.id
            ";
            
            $params = [];
            if ($schoolId) {
                $sql .= " WHERE p.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching products: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get single product by ID
     */
    public function getProductById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.category_name, b.brand_name, u.name as creator_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching product by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add new product
     */
    public function addProduct($data) {
        try {
            // Validate required fields
            $required = ['category_id', 'brand_id', 'title', 'price', 'created_by', 'school_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
                }
            }
            
            // Validate price
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return ['success' => false, 'message' => 'Price must be a valid positive number'];
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO products (category_id, brand_id, title, price, description, keywords, 
                                    image_path, created_by, school_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['category_id'],
                $data['brand_id'],
                $data['title'],
                $data['price'],
                $data['description'] ?? '',
                $data['keywords'] ?? '',
                $data['image_path'] ?? null,
                $data['created_by'],
                $data['school_id']
            ]);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Product added successfully',
                    'product_id' => $this->db->lastInsertId()
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to add product'];
            }
        } catch (PDOException $e) {
            error_log("Error adding product: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Update product
     */
    public function updateProduct($id, $data) {
        try {
            // Validate required fields
            $required = ['category_id', 'brand_id', 'title', 'price'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
                }
            }
            
            // Validate price
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return ['success' => false, 'message' => 'Price must be a valid positive number'];
            }
            
            // Build update query dynamically
            $fields = ['category_id', 'brand_id', 'title', 'price', 'description', 'keywords'];
            $updates = [];
            $params = [];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Add image path if provided
            if (isset($data['image_path'])) {
                $updates[] = "image_path = ?";
                $params[] = $data['image_path'];
            }
            
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            
            if ($stmt->execute($params)) {
                return ['success' => true, 'message' => 'Product updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update product'];
            }
        } catch (PDOException $e) {
            error_log("Error updating product: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Delete product
     */
    public function deleteProduct($id) {
        try {
            if (empty($id)) {
                return ['success' => false, 'message' => 'Product ID is required'];
            }
            
            // Get product image path before deletion
            $product = $this->getProductById($id);
            
            $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                // Delete image file if exists
                if ($product && !empty($product['image_path'])) {
                    $imagePath = 'uploads/' . $product['image_path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                return ['success' => true, 'message' => 'Product deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete product'];
            }
        } catch (PDOException $e) {
            error_log("Error deleting product: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Get categories for dropdown
     */
    public function getCategories($schoolId = null) {
        try {
            if ($schoolId) {
                $stmt = $this->db->prepare("SELECT id, category_name FROM categories WHERE school_id = ? ORDER BY category_name");
                $stmt->execute([$schoolId]);
            } else {
                $stmt = $this->db->prepare("SELECT id, category_name FROM categories ORDER BY category_name");
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get brands by category for dropdown
     */
    public function getBrandsByCategory($categoryId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, brand_name 
                FROM brands 
                WHERE category_id = ? 
                ORDER BY brand_name
            ");
            $stmt->execute([$categoryId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching brands by category: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Handle product image upload
     */
    public function handleImageUpload($file, $userId, $productId) {
        try {
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'No file uploaded or upload error'];
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                return ['success' => false, 'message' => 'Only JPEG, PNG, and GIF images are allowed'];
            }
            
            // Validate file size (max 5MB)
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxSize) {
                return ['success' => false, 'message' => 'Image must be less than 5MB'];
            }
            
            // Create directory structure: uploads/u{user_id}/p{product_id}/
            $uploadDir = "uploads/u{$userId}/p{$productId}/";
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    return ['success' => false, 'message' => 'Failed to create upload directory'];
                }
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'image_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Return relative path for database storage
                $relativePath = "u{$userId}/p{$productId}/" . $filename;
                return [
                    'success' => true, 
                    'message' => 'Image uploaded successfully',
                    'image_path' => $relativePath
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to move uploaded file'];
            }
        } catch (Exception $e) {
            error_log("Error handling image upload: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload error occurred'];
        }
    }

    /**
     * View all products for public display (students)
     */
    public function view_all_products($schoolId = null, $page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT p.*, c.category_name, b.brand_name, s.name as school_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN schools s ON p.school_id = s.id
            ";
            
            $params = [];
            if ($schoolId) {
                $sql .= " WHERE p.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) FROM products p";
            if ($schoolId) {
                $countSql .= " WHERE p.school_id = ?";
                $countStmt = $this->db->prepare($countSql);
                $countStmt->execute([$schoolId]);
            } else {
                $countStmt = $this->db->prepare($countSql);
                $countStmt->execute();
            }
            
            $totalProducts = $countStmt->fetchColumn();
            $totalPages = ceil($totalProducts / $limit);
            
            return [
                'products' => $products,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'totalProducts' => $totalProducts
            ];
        } catch (PDOException $e) {
            error_log("Error fetching all products: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search products by title, description, or keywords
     */
    public function search_products($query, $schoolId = null, $page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            $searchTerm = "%{$query}%";
            
            $sql = "
                SELECT p.*, c.category_name, b.brand_name, s.name as school_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN schools s ON p.school_id = s.id
                WHERE (p.title LIKE ? OR p.description LIKE ? OR p.keywords LIKE ?)
            ";
            
            $params = [$searchTerm, $searchTerm, $searchTerm];
            
            if ($schoolId) {
                $sql .= " AND p.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countSql = "
                SELECT COUNT(*) FROM products p 
                WHERE (p.title LIKE ? OR p.description LIKE ? OR p.keywords LIKE ?)
            ";
            $countParams = [$searchTerm, $searchTerm, $searchTerm];
            
            if ($schoolId) {
                $countSql .= " AND p.school_id = ?";
                $countParams[] = $schoolId;
            }
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($countParams);
            $totalProducts = $countStmt->fetchColumn();
            $totalPages = ceil($totalProducts / $limit);
            
            return [
                'products' => $products,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'totalProducts' => $totalProducts,
                'query' => $query
            ];
        } catch (PDOException $e) {
            error_log("Error searching products: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Filter products by category
     */
    public function filter_products_by_category($catId, $schoolId = null, $page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT p.*, c.category_name, b.brand_name, s.name as school_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN schools s ON p.school_id = s.id
                WHERE p.category_id = ?
            ";
            
            $params = [$catId];
            
            if ($schoolId) {
                $sql .= " AND p.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) FROM products p WHERE p.category_id = ?";
            $countParams = [$catId];
            
            if ($schoolId) {
                $countSql .= " AND p.school_id = ?";
                $countParams[] = $schoolId;
            }
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($countParams);
            $totalProducts = $countStmt->fetchColumn();
            $totalPages = ceil($totalProducts / $limit);
            
            return [
                'products' => $products,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'totalProducts' => $totalProducts,
                'categoryId' => $catId
            ];
        } catch (PDOException $e) {
            error_log("Error filtering products by category: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Filter products by brand
     */
    public function filter_products_by_brand($brandId, $schoolId = null, $page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT p.*, c.category_name, b.brand_name, s.name as school_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN schools s ON p.school_id = s.id
                WHERE p.brand_id = ?
            ";
            
            $params = [$brandId];
            
            if ($schoolId) {
                $sql .= " AND p.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) FROM products p WHERE p.brand_id = ?";
            $countParams = [$brandId];
            
            if ($schoolId) {
                $countSql .= " AND p.school_id = ?";
                $countParams[] = $schoolId;
            }
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($countParams);
            $totalProducts = $countStmt->fetchColumn();
            $totalPages = ceil($totalProducts / $limit);
            
            return [
                'products' => $products,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'totalProducts' => $totalProducts,
                'brandId' => $brandId
            ];
        } catch (PDOException $e) {
            error_log("Error filtering products by brand: " . $e->getMessage());
            return false;
        }
    }

    /**
     * View single product details
     */
    public function view_single_product($id, $schoolId = null) {
        try {
            $sql = "
                SELECT p.*, c.category_name, b.brand_name, s.name as school_name, u.name as creator_name
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                LEFT JOIN schools s ON p.school_id = s.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ";
            
            $params = [$id];
            
            if ($schoolId) {
                $sql .= " AND p.school_id = ?";
                $params[] = $schoolId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching single product: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all brands for filters (public)
     */
    public function getAllBrandsForFilter($schoolId = null) {
        try {
            $sql = "SELECT DISTINCT b.id, b.brand_name FROM brands b 
                   INNER JOIN products p ON b.id = p.brand_id";
            
            $params = [];
            if ($schoolId) {
                $sql .= " WHERE b.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY b.brand_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching brands for filter: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all categories for filters (public)
     */
    public function getAllCategoriesForFilter($schoolId = null) {
        try {
            $sql = "SELECT DISTINCT c.id, c.category_name FROM categories c 
                   INNER JOIN products p ON c.id = p.category_id";
            
            $params = [];
            if ($schoolId) {
                $sql .= " WHERE c.school_id = ?";
                $params[] = $schoolId;
            }
            
            $sql .= " ORDER BY c.category_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching categories for filter: " . $e->getMessage());
            return [];
        }
    }
}
?>