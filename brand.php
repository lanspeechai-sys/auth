<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'brand_class.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$brandObj = new Brand();

// Get categories for dropdown with school context
require_once 'category_class.php';
$categoryObj = new Category();

if (isSuperAdmin()) {
    $categories = $categoryObj->getAllCategories(null, true);
} else {
    $schoolId = $user['school_id'];
    $categories = $categoryObj->getAllCategories($schoolId, false);
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Management - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Improve text contrast and readability */
        .text-muted {
            color: #6c757d !important;
            font-weight: 500;
        }
        
        .card-text {
            color: #495057 !important;
        }
        
        .card-footer small {
            color: #495057 !important;
            font-weight: 500;
        }
        
        .badge {
            font-weight: 600;
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
        
        /* Improve button text contrast */
        .btn-outline-primary, .btn-outline-danger, .btn-outline-secondary {
            font-weight: 500;
        }
        
        /* Card improvements */
        .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        
        .card-title {
            color: #212529 !important;
            font-weight: 600;
        }
        
        .card-subtitle {
            color: #6c757d !important;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Brand Management</h1>
                        <p class="text-muted">Manage product brands organized by categories</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#brandModal">
                        <i class="bi bi-plus-circle"></i> Add New Brand
                    </button>
                </div>

                <!-- Alert Messages -->
                <div id="alertContainer"></div>

                <!-- Brands Display -->
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-award"></i> Brands by Category
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="brandsContainer">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading brands...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Brand Modal -->
    <div class="modal fade" id="brandModal" tabindex="-1" aria-labelledby="brandModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="brandModalLabel">Add New Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="brandForm">
                    <div class="modal-body">
                        <input type="hidden" id="brandId" name="brand_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="brandName" class="form-label">Brand Name *</label>
                            <input type="text" class="form-control" id="brandName" name="brand_name" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoryId" class="form-label">Category *</label>
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span id="submitText">Add Brand</span>
                            <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the brand "<strong id="deleteBrandName"></strong>"?</p>
                    <p class="text-muted small">This action cannot be undone. The brand will only be deleted if it's not used by any products.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <span id="deleteText">Delete Brand</span>
                        <span id="deleteSpinner" class="spinner-border spinner-border-sm d-none" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/brand.js"></script>
</body>
</html>