<?php
session_start();
require_once 'includes/auth.php';
require_once 'product_class.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

$product = new Product();
$categories = $product->getCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .product-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .product-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .product-placeholder {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .category-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .brand-badge {
            background-color: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #ce93d8;
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
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 0.5rem;
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #667eea;
            background-color: #f8f9fa;
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background-color: #e3f2fd;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #495057;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.6;
            color: #6c757d;
        }
        
        .empty-state h4 {
            color: #343a40;
            font-weight: 600;
        }
        
        .empty-state p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        /* Improve text contrast throughout */
        .card-text {
            color: #495057 !important;
        }
        
        .badge {
            font-weight: 600;
        }
        
        .text-muted {
            color: #6c757d !important;
            font-weight: 500;
        }
        
        .card-footer small {
            color: #495057 !important;
            font-weight: 500;
        }
        
        .card-title {
            color: #212529 !important;
            font-weight: 600;
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
                <a class="nav-link" href="school-admin/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link" href="brand.php">
                    <i class="bi bi-tags"></i> Brands
                </a>
                <a class="nav-link active" href="product.php">
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
                        <h2 class="fw-bold mb-1">Product Management</h2>
                        <p class="text-muted mb-0">Manage your course products and educational materials</p>
                    </div>
                    <button class="btn btn-gradient btn-lg" data-bs-toggle="modal" data-bs-target="#productModal">
                        <i class="bi bi-plus-circle"></i> Add Product
                    </button>
                </div>

                <!-- Alerts -->
                <div id="alertContainer"></div>

                <!-- Loading Spinner -->
                <div class="text-center loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading products...</p>
                </div>

                <!-- Products Grid -->
                <div class="row" id="productsContainer">
                    <!-- Products will be loaded here -->
                </div>

                <!-- Empty State -->
                <div class="empty-state" id="emptyState" style="display: none;">
                    <i class="bi bi-box"></i>
                    <h4>No Products Found</h4>
                    <p>Start by adding your first course product to the system.</p>
                    <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#productModal">
                        <i class="bi bi-plus-circle"></i> Add First Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-plus-circle"></i> Add New Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm" enctype="multipart/form-data">
                        <input type="hidden" id="productId" name="product_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="categoryId" class="form-label fw-semibold">
                                        Category <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="categoryId" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="brandId" class="form-label fw-semibold">
                                        Brand <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="brandId" name="brand_id" required disabled>
                                        <option value="">Select Brand</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="productTitle" class="form-label fw-semibold">
                                Product Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="productTitle" name="title" 
                                   placeholder="Enter product title" required maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="productPrice" class="form-label fw-semibold">
                                        Price <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="productPrice" name="price" 
                                               placeholder="0.00" step="0.01" min="0" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="productKeywords" class="form-label fw-semibold">Keywords</label>
                                    <input type="text" class="form-control" id="productKeywords" name="keywords" 
                                           placeholder="course, education, online" maxlength="255">
                                    <div class="form-text">Separate keywords with commas</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="productDescription" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="productDescription" name="description" 
                                      rows="4" placeholder="Enter product description..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="productImage" class="form-label fw-semibold">Product Image</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="bi bi-cloud-upload display-4 text-muted"></i>
                                <h5 class="mt-2">Drop image here or click to browse</h5>
                                <p class="text-muted mb-0">Supported formats: JPEG, PNG, GIF (Max 5MB)</p>
                                <input type="file" class="form-control d-none" id="productImage" name="image" 
                                       accept="image/jpeg,image/png,image/gif">
                            </div>
                            <div id="imagePreview" class="mt-2"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-gradient" form="productForm" id="submitBtn">
                        <i class="bi bi-check-circle"></i> <span id="submitText">Add Product</span>
                    </button>
                </div>
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
                    <p>Are you sure you want to delete this product?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i> This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/product.js"></script>
    <script>
        // Initialize Product Manager
        const productManager = new ProductManager();
        productManager.init();
    </script>
</body>
</html>