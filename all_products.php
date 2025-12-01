<?php
session_start();
require_once 'includes/auth.php';
require_once 'product_class.php';

// Get user context for school filtering
$user = null;
$schoolId = null;
$isStudent = false;

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'student') {
        $schoolId = $user['school_id'];
        $isStudent = true;
    }
}

$product = new Product();
$categories = $product->getAllCategoriesForFilter($schoolId);
$brands = $product->getAllBrandsForFilter($schoolId);

// Get initial products
$initialData = $product->view_all_products($schoolId, 1, 10);
$products = $initialData['products'] ?? [];
$totalPages = $initialData['totalPages'] ?? 1;
$totalProducts = $initialData['totalProducts'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .product-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .product-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            transform: translateY(-5px);
        }
        
        .product-image {
            height: 220px;
            object-fit: cover;
            width: 100%;
        }
        
        .product-placeholder {
            height: 220px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .price-tag {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 700;
            font-size: 1.25rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .btn-filter {
            margin: 0.25rem;
            border-radius: 25px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .product-badges {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .add-to-cart-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-mortarboard-fill"></i> SchoolLink Africa
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="all_products.php">
                            <i class="bi bi-grid-3x3-gap"></i> All Products
                        </a>
                    </li>
                    <?php if ($user): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="store.php">
                                <i class="bi bi-shop"></i> Store
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Search Bar -->
                <form class="d-flex me-3" onsubmit="return false;" style="min-width: 300px;">
                    <div class="input-group">
                        <input class="form-control" type="search" id="searchInput" placeholder="Search products..." aria-label="Search">
                        <button class="btn btn-outline-light" type="button" onclick="performSearch()">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <ul class="navbar-nav">
                    <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bi bi-person-plus"></i> Register
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold">Discover Products</h1>
                    <p class="lead">
                        <?php if ($isStudent): ?>
                            Explore courses and materials from your school
                        <?php else: ?>
                            Browse educational products and courses from schools across Africa
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 12px;">
                        <h3 class="mb-0"><?php echo $totalProducts; ?></h3>
                        <p class="mb-0">Products Available</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filters Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-3 mb-md-0"><i class="bi bi-funnel"></i> Filter Products</h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-outline-primary btn-filter" onclick="resetFilters()">
                        <i class="bi bi-arrow-clockwise"></i> Show All
                    </button>
                </div>
            </div>
            
            <hr class="my-3">
            
            <div class="row">
                <?php if (!empty($categories)): ?>
                <div class="col-md-6">
                    <h6 class="mb-2">Categories:</h6>
                    <div class="filter-buttons">
                        <?php foreach ($categories as $category): ?>
                        <button class="btn btn-outline-secondary btn-filter btn-sm" 
                                onclick="filterByCategory(<?php echo $category['id']; ?>)">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($brands)): ?>
                <div class="col-md-6">
                    <h6 class="mb-2">Brands:</h6>
                    <div class="filter-buttons">
                        <?php foreach ($brands as $brand): ?>
                        <button class="btn btn-outline-info btn-filter btn-sm" 
                                onclick="filterByBrand(<?php echo $brand['id']; ?>)">
                            <?php echo htmlspecialchars($brand['brand_name']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loading" class="loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading products...</p>
        </div>

        <!-- Products Grid -->
        <div class="row" id="productsContainer">
            <?php if (empty($products)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-box" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                    <h4 class="mt-3 text-muted">No Products Found</h4>
                    <p class="text-muted">
                        <?php if ($isStudent): ?>
                            Your school hasn't added any products yet.
                        <?php else: ?>
                            No products are currently available.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($products as $productItem): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card product-card">
                        <?php if (!empty($productItem['image_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($productItem['image_path']); ?>" 
                                 class="product-image" alt="<?php echo htmlspecialchars($productItem['title']); ?>">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <i class="bi bi-box"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="product-badges">
                                <?php if ($productItem['category_name']): ?>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars($productItem['category_name']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($productItem['brand_name']): ?>
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars($productItem['brand_name']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="card-title fw-bold">
                                <a href="single_product.php?id=<?php echo $productItem['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($productItem['title']); ?>
                                </a>
                            </h5>
                            
                            <?php if (!empty($productItem['description'])): ?>
                            <p class="card-text text-muted flex-grow-1">
                                <?php 
                                $desc = htmlspecialchars($productItem['description']);
                                echo strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc;
                                ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
                                <div class="product-meta mb-3">
                                    <span class="price-tag">$<?php echo number_format($productItem['price'], 2); ?></span>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('M j, Y', strtotime($productItem['created_at'])); ?>
                                    </small>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="single_product.php?id=<?php echo $productItem['id']; ?>" 
                                       class="btn btn-outline-primary flex-fill">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                    <button class="btn add-to-cart-btn flex-fill" 
                                            onclick="addToCart(<?php echo $productItem['id']; ?>)">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Products pagination" class="mt-4">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be updated by JavaScript -->
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/products.js"></script>
    <script>
        // Initialize page
        let currentPage = 1;
        let currentFilter = null;
        let currentQuery = null;
        
        // Update pagination
        function updatePagination(totalPages, currentPageNum) {
            const pagination = document.getElementById('pagination');
            if (!pagination || totalPages <= 1) {
                if (pagination) pagination.style.display = 'none';
                return;
            }
            
            pagination.style.display = 'block';
            let paginationHTML = '';
            
            // Previous button
            paginationHTML += `
                <li class="page-item ${currentPageNum === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPageNum - 1}); return false;">Previous</a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                paginationHTML += `
                    <li class="page-item ${i === currentPageNum ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                    </li>
                `;
            }
            
            // Next button
            paginationHTML += `
                <li class="page-item ${currentPageNum === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${currentPageNum + 1}); return false;">Next</a>
                </li>
            `;
            
            pagination.innerHTML = paginationHTML;
        }
        
        // Add to cart
        function addToCart(productId) {
            const button = event.target.closest('.add-to-cart-btn');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';
            button.disabled = true;
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Added!';
                    
                    // Update cart count
                    const cartBadge = document.getElementById('cartCount');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count;
                        cartBadge.style.display = '';
                    }
                    
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }, 1500);
                } else {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                    alert(data.message || 'Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = originalHTML;
                button.disabled = false;
                alert('Error adding to cart. Please try again.');
            });
        }
        
        // Initialize pagination
        updatePagination(<?php echo $totalPages; ?>, 1);
    </script>
</body>
</html>