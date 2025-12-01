<?php
session_start();
require_once 'includes/auth.php';
require_once 'product_class.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$schoolId = $user['school_id'];

// Only students and school admins can access store
if (!in_array($user['role'], ['student', 'school_admin'])) {
    header('Location: index.php');
    exit();
}

$product = new Product();
$categories = $product->getAllCategoriesForFilter($schoolId);
$brands = $product->getAllBrandsForFilter($schoolId);

// Get initial products with pagination
$initialData = $product->view_all_products($schoolId, 1, 12);
$products = $initialData['products'] ?? [];
$totalPages = $initialData['totalPages'] ?? 1;
$totalProducts = $initialData['totalProducts'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Store - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .store-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .product-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            height: 100%;
        }
        
        .product-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .product-placeholder {
            height: 200px;
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
        
        .category-filter {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-btn {
            margin: 0.25rem;
        }
        
        .store-stats {
            background-color: rgba(255,255,255,0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
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
                    <?php if ($user['role'] === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="user/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="school-admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="store.php">
                            <i class="bi bi-shop"></i> Store
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="bi bi-cart3"></i> Cart
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                                  id="cartCount" style="display: none; font-size: 0.7em;">0</span>
                        </a>
                    </li>
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
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="store-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold">School Store</h1>
                    <p class="lead">Discover courses and educational materials from your school</p>
                </div>
                <div class="col-lg-4">
                    <div class="store-stats">
                        <h3 class="mb-0"><?php echo $totalProducts; ?></h3>
                        <p class="mb-0">Available Products</p>
                        <?php if ($totalPages > 1): ?>
                        <small class="opacity-75">Page 1 of <?php echo $totalPages; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Enhanced Filters Section -->
        <div class="category-filter">
            <div class="row align-items-center mb-3">
                <div class="col-md-6">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Products</h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-outline-secondary btn-sm" onclick="resetAllFilters()">
                        <i class="bi bi-arrow-clockwise"></i> Reset Filters
                    </button>
                </div>
            </div>
            
            <div class="row">
                <!-- Category Filters -->
                <?php if (!empty($categories)): ?>
                <div class="col-md-6 mb-3">
                    <h6 class="mb-2"><i class="bi bi-tags"></i> Categories:</h6>
                    <div class="d-flex flex-wrap">
                        <button class="btn btn-outline-primary filter-btn active btn-sm" onclick="filterByCategory('all')">
                            All Categories
                        </button>
                        <?php foreach ($categories as $category): ?>
                        <button class="btn btn-outline-primary filter-btn btn-sm" onclick="filterByCategory(<?php echo $category['id']; ?>)">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Brand Filters -->
                <?php if (!empty($brands)): ?>
                <div class="col-md-6 mb-3">
                    <h6 class="mb-2"><i class="bi bi-award"></i> Brands:</h6>
                    <div class="d-flex flex-wrap">
                        <button class="btn btn-outline-info filter-btn active btn-sm" onclick="filterByBrand('all')">
                            All Brands
                        </button>
                        <?php foreach ($brands as $brand): ?>
                        <button class="btn btn-outline-info filter-btn btn-sm" onclick="filterByBrand(<?php echo $brand['id']; ?>)">
                            <?php echo htmlspecialchars($brand['brand_name']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loading" class="text-center py-4" style="display: none;">
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
                    <i class="bi bi-shop" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                    <h4 class="mt-3 text-muted">No Products Available</h4>
                    <p class="text-muted">Your school hasn't added any products to the store yet.</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <div class="col-lg-4 col-md-6 col-sm-12 product-item" data-category="<?php echo $product['category_id']; ?>">
                    <div class="card product-card">
                        <?php if (!empty($product['image_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($product['image_path']); ?>" 
                                 class="product-image" alt="<?php echo htmlspecialchars($product['title']); ?>">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <i class="bi bi-box"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <div class="mb-2">
                                <span class="badge bg-light text-dark border me-1">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?>
                                </span>
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars($product['brand_name'] ?? 'No Brand'); ?>
                                </span>
                            </div>
                            
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($product['title']); ?></h5>
                            
                            <?php if (!empty($product['description'])): ?>
                            <p class="card-text text-muted flex-grow-1">
                                <?php 
                                $desc = htmlspecialchars($product['description']);
                                echo strlen($desc) > 150 ? substr($desc, 0, 150) . '...' : $desc;
                                ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($product['keywords'])): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-tags"></i> <?php echo htmlspecialchars($product['keywords']); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="price-tag">$<?php echo number_format($product['price'], 2); ?></span>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                                    </small>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="single_product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary flex-fill">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                    <button class="btn btn-success flex-fill" onclick="addToCart(<?php echo $product['id']; ?>)">
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
        <nav aria-label="Store pagination" class="mt-4">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be updated by JavaScript -->
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="productTitle">Product Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="productDetails">
                    <!-- Product details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="enrollBtn" style="display: none;">
                        <i class="bi bi-plus-circle"></i> Enroll Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store management variables
        let currentPage = 1;
        let currentFilter = null;
        let currentQuery = null;
        let isLoading = false;

        // Show/hide loading indicator
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
            isLoading = true;
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
            isLoading = false;
        }

        // Update products container
        function updateProductsContainer(products) {
            const container = document.getElementById('productsContainer');
            
            if (!products || products.length === 0) {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="bi bi-shop" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                            <h4 class="mt-3 text-muted">No Products Found</h4>
                            <p class="text-muted">Try adjusting your search or filter criteria.</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            let html = '';
            products.forEach(product => {
                const imageHtml = product.image_path 
                    ? `<img src="uploads/${product.image_path}" class="product-image" alt="${product.title}">`
                    : `<div class="product-placeholder"><i class="bi bi-box"></i></div>`;
                    
                const categoryBadge = product.category_name 
                    ? `<span class="badge bg-light text-dark border me-1">${product.category_name}</span>`
                    : '';
                    
                const brandBadge = product.brand_name 
                    ? `<span class="badge bg-secondary">${product.brand_name}</span>`
                    : '';
                    
                const description = product.description 
                    ? (product.description.length > 150 ? product.description.substring(0, 150) + '...' : product.description)
                    : '';
                    
                const keywords = product.keywords 
                    ? `<div class="mb-3"><small class="text-muted"><i class="bi bi-tags"></i> ${product.keywords}</small></div>`
                    : '';
                    
                html += `
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                        <div class="card product-card">
                            ${imageHtml}
                            
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    ${categoryBadge}
                                    ${brandBadge}
                                </div>
                                
                                <h5 class="card-title fw-bold">${product.title}</h5>
                                
                                ${description ? `<p class="card-text text-muted flex-grow-1">${description}</p>` : ''}
                                
                                ${keywords}
                                
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="price-tag">$${parseFloat(product.price).toFixed(2)}</span>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> 
                                            ${new Date(product.created_at).toLocaleDateString('en-US', { 
                                                month: 'short', 
                                                day: 'numeric', 
                                                year: 'numeric' 
                                            })}
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex">
                                        <a href="single_product.php?id=${product.id}" class="btn btn-outline-primary flex-fill">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                        <button class="btn btn-success flex-fill" onclick="addToCart(${product.id})">
                                            <i class="bi bi-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

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

        // Make AJAX request
        function makeRequest(url, callback) {
            if (isLoading) return;
            
            showLoading();
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        callback(data.data);
                    } else {
                        console.error('Request failed:', data.message);
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Request error:', error);
                    alert('An error occurred while loading products');
                });
        }

        // Load all products
        function loadAllProducts(page = 1) {
            currentPage = page;
            currentFilter = null;
            currentQuery = null;
            
            const url = `product_actions.php?action=get_all_products&page=${page}`;
            makeRequest(url, (data) => {
                updateProductsContainer(data.products);
                updatePagination(data.totalPages, data.currentPage);
            });
        }

        // Search products
        function performSearch(page = 1) {
            const searchInput = document.getElementById('searchInput');
            const query = searchInput.value.trim();
            
            if (!query) {
                loadAllProducts(page);
                return;
            }
            
            currentPage = page;
            currentQuery = query;
            currentFilter = null;
            
            const url = `product_actions.php?action=search_products&query=${encodeURIComponent(query)}&page=${page}`;
            makeRequest(url, (data) => {
                updateProductsContainer(data.products);
                updatePagination(data.totalPages, data.currentPage);
            });
        }

        // Filter by category
        function filterByCategory(categoryId, page = 1) {
            // Update button states
            document.querySelectorAll('.filter-btn').forEach(btn => {
                if (btn.onclick && btn.onclick.toString().includes('filterByCategory')) {
                    btn.classList.remove('active');
                }
            });
            event.target.classList.add('active');
            
            if (categoryId === 'all') {
                loadAllProducts(page);
                return;
            }
            
            currentPage = page;
            currentFilter = { type: 'category', id: categoryId };
            currentQuery = null;
            
            const url = `product_actions.php?action=filter_by_category&category_id=${categoryId}&page=${page}`;
            makeRequest(url, (data) => {
                updateProductsContainer(data.products);
                updatePagination(data.totalPages, data.currentPage);
            });
        }

        // Filter by brand
        function filterByBrand(brandId, page = 1) {
            // Update button states
            document.querySelectorAll('.filter-btn').forEach(btn => {
                if (btn.onclick && btn.onclick.toString().includes('filterByBrand')) {
                    btn.classList.remove('active');
                }
            });
            event.target.classList.add('active');
            
            if (brandId === 'all') {
                loadAllProducts(page);
                return;
            }
            
            currentPage = page;
            currentFilter = { type: 'brand', id: brandId };
            currentQuery = null;
            
            const url = `product_actions.php?action=filter_by_brand&brand_id=${brandId}&page=${page}`;
            makeRequest(url, (data) => {
                updateProductsContainer(data.products);
                updatePagination(data.totalPages, data.currentPage);
            });
        }

        // Reset all filters
        function resetAllFilters() {
            currentPage = 1;
            currentFilter = null;
            currentQuery = null;
            
            // Clear search input
            document.getElementById('searchInput').value = '';
            
            // Reset filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.onclick && (btn.onclick.toString().includes('all') || btn.textContent.trim().includes('All'))) {
                    btn.classList.add('active');
                }
            });
            
            loadAllProducts(1);
        }

        // Change page
        function changePage(page) {
            if (page < 1 || isLoading) return;
            
            if (currentQuery) {
                performSearch(page);
            } else if (currentFilter) {
                if (currentFilter.type === 'category') {
                    filterByCategory(currentFilter.id, page);
                } else if (currentFilter.type === 'brand') {
                    filterByBrand(currentFilter.id, page);
                }
            } else {
                loadAllProducts(page);
            }
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Add to cart functionality
        function addToCart(productId) {
            const button = event.target.closest('.btn-success');
            const originalHTML = button.innerHTML;
            
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';
            button.disabled = true;
            
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_to_cart&product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Added!';
                    showToast('Product added to cart!', 'success');
                    
                    // Update cart count in header
                    updateCartCount(data.cart_count);
                    
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }, 2000);
                } else {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                    showToast('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Cart Error:', error);
                button.innerHTML = originalHTML;
                button.disabled = false;
                showToast('Failed to add item to cart. Please try again.', 'danger');
            });
        }

        // Update cart count in header
        function updateCartCount(count) {
            const cartBadge = document.getElementById('cartCount');
            if (cartBadge) {
                cartBadge.textContent = count;
                if (count > 0) {
                    cartBadge.style.display = 'inline-block';
                } else {
                    cartBadge.style.display = 'none';
                }
            }
        }



        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed`;
            toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);';
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 3000);
        }

        // Load current cart count on page load
        function loadCartCount() {
            fetch('cart_actions.php?action=get_cart_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCartCount(data.count);
                    }
                })
                .catch(error => {
                    console.error('Error loading cart count:', error);
                });
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Search input event listener
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });
            
            // Initialize pagination
            updatePagination(<?php echo $totalPages; ?>, 1);
            
            // Load cart count
            loadCartCount();
        });
    </script>
</body>
</html>