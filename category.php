<?php
session_start();
require_once 'includes/auth.php';
require_once 'category_class.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .category-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .category-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .category-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .stats-badge {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            font-size: 0.875rem;
            color: #495057;
        }
        
        .stats-badge strong {
            color: #212529;
            font-size: 1.1rem;
        }
        
        .stats-badge small {
            color: #6c757d;
            font-weight: 500;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        .loading-spinner {
            display: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-mortarboard-fill"></i> SchoolLink Africa
            </a>
            <div class="navbar-nav ms-auto">
                <?php if (isSuperAdmin()): ?>
                <a class="nav-link" href="admin/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Admin Dashboard
                </a>
                <?php else: ?>
                <a class="nav-link" href="school-admin/dashboard.php">
                    <i class="bi bi-speedometer2"></i> School Dashboard
                </a>
                <?php endif; ?>
                <a class="nav-link active" href="category.php">
                    <i class="bi bi-grid-3x3-gap"></i> Categories
                </a>
                <a class="nav-link" href="brand.php">
                    <i class="bi bi-tags"></i> Brands
                </a>
                <a class="nav-link" href="product.php">
                    <i class="bi bi-box"></i> Products
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="fw-bold mb-1">Category Management</h2>
                        <p class="text-muted mb-0">Organize your products and brands into categories</p>
                    </div>
                    <button class="btn btn-gradient btn-lg" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="bi bi-plus-circle"></i> Add Category
                    </button>
                </div>

                <!-- Alerts -->
                <div id="alertContainer"></div>

                <!-- Loading Spinner -->
                <div class="text-center loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading categories...</p>
                </div>

                <!-- Categories Grid -->
                <div class="row" id="categoriesContainer">
                    <!-- Categories will be loaded here -->
                </div>

                <!-- Empty State -->
                <div class="empty-state" id="emptyState" style="display: none;">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <h4>No Categories Found</h4>
                    <p>Create categories to organize your products and brands effectively.</p>
                    <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="bi bi-plus-circle"></i> Add First Category
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-plus-circle"></i> Add New Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="categoryForm">
                    <div class="modal-body">
                        <input type="hidden" id="categoryId" name="category_id">
                        
                        <div class="mb-3">
                            <label for="categoryName" class="form-label fw-semibold">
                                Category Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="categoryName" name="category_name" 
                                   placeholder="Enter category name" required maxlength="255">
                            <div class="invalid-feedback"></div>
                            <div class="form-text">Choose a descriptive name for your category (e.g., "Academic Courses", "Professional Development")</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-gradient" id="submitBtn">
                            <i class="bi bi-check-circle"></i> <span id="submitText">Add Category</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this category?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i> 
                        This action cannot be undone. Categories with existing brands cannot be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Category
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/category.js"></script>
    <script>
        // Initialize Category Manager
        const categoryManager = new CategoryManager();
        categoryManager.init();
    </script>
</body>
</html>