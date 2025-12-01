<?php
session_start();
require_once 'includes/auth.php';
require_once 'product_class.php';

// Get search query from URL
$searchQuery = trim($_GET['q'] ?? $_GET['query'] ?? '');

if (empty($searchQuery)) {
    header('Location: all_products.php');
    exit();
}

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

// Get search results
$page = intval($_GET['page'] ?? 1);
$searchResults = $product->search_products($searchQuery, $schoolId, $page, 10);
$products = $searchResults['products'] ?? [];
$totalPages = $searchResults['totalPages'] ?? 1;
$totalProducts = $searchResults['totalProducts'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results: "<?php echo htmlspecialchars($searchQuery); ?>" - SchoolLink Africa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .search-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
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
        
        .btn-filter {
            margin: 0.25rem;
            border-radius: 25px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .search-stats {
            background-color: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
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
        
        .highlight {
            background-color: #fff3cd;
            padding: 0.1rem 0.2rem;
            border-radius: 3px;
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
                        <a class="nav-link" href="all_products.php">
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
                <form class="d-flex me-3" action="product_search_result.php" method="GET" style="min-width: 300px;">
                    <div class="input-group">
                        <input class="form-control" type="search" name="q" 
                               value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               placeholder="Search products..." aria-label="Search" required>
                        <button class="btn btn-outline-light" type="submit">
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

    <!-- Search Results Hero -->
    <div class="search-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold">Search Results</h1>
                    <p class="lead mb-2">
                        Showing results for "<span class="highlight"><?php echo htmlspecialchars($searchQuery); ?></span>"
                    </p>
                    <?php if ($isStudent): ?>
                    <p class="mb-0 opacity-75">Searching within your school's products</p>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4">
                    <div class="search-stats">
                        <h3 class="mb-0"><?php echo $totalProducts; ?></h3>
                        <p class="mb-0">Products Found</p>
                        <?php if ($totalPages > 1): ?>
                        <small class="opacity-75">Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <a href="all_products.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Browse All Products
                    </a>
                    <button class="btn btn-outline-secondary" onclick="clearSearch()">
                        <i class="bi bi-x"></i> Clear Search
                    </button>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="sortOptions" id="sortNewest" checked>
                    <label class="btn btn-outline-secondary" for="sortNewest">
                        <i class="bi bi-clock"></i> Newest First
                    </label>
                    
                    <input type="radio" class="btn-check" name="sortOptions" id="sortPrice">
                    <label class="btn btn-outline-secondary" for="sortPrice">
                        <i class="bi bi-currency-dollar"></i> Price
                    </label>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <?php if (!empty($categories) || !empty($brands)): ?>
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-3 mb-md-0"><i class="bi bi-funnel"></i> Refine Results</h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-outline-primary btn-filter" onclick="resetFilters()">
                        <i class="bi bi-arrow-clockwise"></i> Reset Filters
                    </button>
                </div>
            </div>
            
            <hr class="my-3">
            
            <div class="row">
                <?php if (!empty($categories)): ?>
                <div class="col-md-6">
                    <h6 class="mb-2">Filter by Category:</h6>
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
                    <h6 class="mb-2">Filter by Brand:</h6>
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
        <?php endif; ?>

        <!-- Loading Indicator -->
        <div id="loading" class="loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Refining search results...</p>
        </div>

        <!-- Search Results -->
        <div class="row" id="resultsContainer">
            <?php if (empty($products)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-search" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                    <h4 class="mt-3 text-muted">No Results Found</h4>
                    <p class="text-muted">
                        We couldn't find any products matching "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                        <?php if ($isStudent): ?>
                        in your school's catalog
                        <?php endif; ?>.
                    </p>
                    <div class="mt-4">
                        <h6>Try:</h6>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-arrow-right text-primary"></i> Different keywords</li>
                            <li><i class="bi bi-arrow-right text-primary"></i> Removing filters</li>
                            <li><i class="bi bi-arrow-right text-primary"></i> Browsing <a href="all_products.php">all products</a></li>
                        </ul>
                    </div>
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
                            <div class="product-badges mb-2">
                                <?php if ($productItem['category_name']): ?>
                                <span class="badge bg-light text-dark border me-1">
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
                                    <?php 
                                    $title = htmlspecialchars($productItem['title']);
                                    // Highlight search terms in title
                                    $highlightedTitle = str_ireplace($searchQuery, '<mark>' . $searchQuery . '</mark>', $title);
                                    echo $highlightedTitle;
                                    ?>
                                </a>
                            </h5>
                            
                            <?php if (!empty($productItem['description'])): ?>
                            <p class="card-text text-muted flex-grow-1">
                                <?php 
                                $desc = htmlspecialchars($productItem['description']);
                                $desc = strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc;
                                // Highlight search terms in description
                                $highlightedDesc = str_ireplace($searchQuery, '<mark>' . $searchQuery . '</mark>', $desc);
                                echo $highlightedDesc;
                                ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
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
        <nav aria-label="Search results pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page - 1; ?>">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?q=<?php echo urlencode($searchQuery); ?>&page=<?php echo $page + 1; ?>">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentQuery = "<?php echo addslashes($searchQuery); ?>";
        
        // Clear search and redirect to all products
        function clearSearch() {
            window.location.href = 'all_products.php';
        }
        
        // Filter functions that preserve search query
        function filterByCategory(categoryId) {
            showLoading();
            window.location.href = `product_search_result.php?q=${encodeURIComponent(currentQuery)}&category=${categoryId}`;
        }
        
        function filterByBrand(brandId) {
            showLoading();
            window.location.href = `product_search_result.php?q=${encodeURIComponent(currentQuery)}&brand=${brandId}`;
        }
        
        function resetFilters() {
            window.location.href = `product_search_result.php?q=${encodeURIComponent(currentQuery)}`;
        }
        
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }
        
        // Add to cart function
        function addToCart(productId) {
            const button = event.target.closest('.add-to-cart-btn');
            const originalHTML = button.innerHTML;
            
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = '<i class="bi bi-check-circle"></i> Added!';
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }, 1500);
                
                showToast('Product added to cart!', 'success');
            }, 1000);
        }
        
        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
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
    </script>
</body>
</html>