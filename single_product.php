<?php
session_start();
require_once 'includes/auth.php';
require_once 'product_class.php';

// Get product ID from URL
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
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

$productClass = new Product();
$product = $productClass->view_single_product($productId, $schoolId);

if (!$product) {
    header('Location: all_products.php?error=product_not_found');
    exit();
}

// Get related products from the same category
$relatedProducts = [];
if ($product['category_id']) {
    $relatedData = $productClass->filter_products_by_category($product['category_id'], $schoolId, 1, 4);
    if ($relatedData && isset($relatedData['products'])) {
        $relatedProducts = array_filter($relatedData['products'], function($p) use ($productId) {
            return $p['id'] != $productId;
        });
        $relatedProducts = array_slice($relatedProducts, 0, 3);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> - SchoolLink Africa</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 160)); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .product-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .product-image-container {
            position: relative;
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .product-main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        
        .product-placeholder-large {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 5rem;
        }
        
        .product-info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .price-display {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .add-to-cart-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .add-to-cart-btn:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        
        .product-meta-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .related-product-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .related-product-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            transform: translateY(-5px);
        }
        
        .related-product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .related-product-placeholder {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .breadcrumb {
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            padding: 0.5rem 1rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.7);
        }
        
        .keywords-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .keyword-tag {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
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

    <!-- Product Hero Section -->
    <div class="product-hero">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="all_products.php" class="text-white text-decoration-none">Products</a>
                    </li>
                    <?php if ($product['category_name']): ?>
                    <li class="breadcrumb-item">
                        <a href="all_products.php#category-<?php echo $product['category_id']; ?>" class="text-white text-decoration-none">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active text-white" aria-current="page">
                        <?php echo htmlspecialchars($product['title']); ?>
                    </li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($product['title']); ?></h1>
                    <p class="lead mb-0">Product ID: #<?php echo $product['id']; ?></p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="d-inline-flex gap-2">
                        <?php if ($product['category_name']): ?>
                        <span class="badge product-meta-badge">
                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($product['brand_name']): ?>
                        <span class="badge product-meta-badge">
                            <i class="bi bi-award"></i> <?php echo htmlspecialchars($product['brand_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Product Image -->
            <div class="col-lg-6 mb-4">
                <div class="product-image-container">
                    <?php if (!empty($product['image_path'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($product['image_path']); ?>" 
                             class="product-main-image" alt="<?php echo htmlspecialchars($product['title']); ?>">
                    <?php else: ?>
                        <div class="product-placeholder-large">
                            <i class="bi bi-box"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Information -->
            <div class="col-lg-6 mb-4">
                <div class="product-info-card">
                    <div class="mb-4">
                        <span class="price-display">$<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    
                    <?php if (!empty($product['description'])): ?>
                    <div class="mb-4">
                        <h5 class="fw-bold mb-3">Description</h5>
                        <p class="text-muted lh-lg"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Product Details -->
                    <div class="mb-4">
                        <h5 class="fw-bold mb-3">Product Details</h5>
                        <div class="row">
                            <div class="col-6">
                                <strong>Category:</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                            </div>
                            <div class="col-6">
                                <strong>Brand:</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($product['brand_name'] ?? 'No Brand'); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($product['school_name']): ?>
                        <div class="row mt-3">
                            <div class="col-6">
                                <strong>School:</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($product['school_name']); ?></span>
                            </div>
                            <div class="col-6">
                                <strong>Added:</strong><br>
                                <span class="text-muted"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Keywords -->
                    <?php if (!empty($product['keywords'])): ?>
                    <div class="mb-4">
                        <h5 class="fw-bold mb-3">Keywords</h5>
                        <div class="keywords-tags">
                            <?php 
                            $keywords = explode(',', $product['keywords']);
                            foreach ($keywords as $keyword): 
                                $keyword = trim($keyword);
                                if (!empty($keyword)):
                            ?>
                                <span class="keyword-tag"><?php echo htmlspecialchars($keyword); ?></span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart Section -->
                    <div class="d-grid gap-2 d-md-flex">
                        <button class="btn add-to-cart-btn flex-fill" onclick="addToCart(<?php echo $product['id']; ?>)">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                        <button class="btn btn-outline-secondary" onclick="shareProduct()">
                            <i class="bi bi-share"></i> Share
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="mt-5">
            <h3 class="fw-bold mb-4">Related Products</h3>
            <div class="row">
                <?php foreach ($relatedProducts as $relatedProduct): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card related-product-card">
                        <?php if (!empty($relatedProduct['image_path'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($relatedProduct['image_path']); ?>" 
                                 class="related-product-image" alt="<?php echo htmlspecialchars($relatedProduct['title']); ?>">
                        <?php else: ?>
                            <div class="related-product-placeholder">
                                <i class="bi bi-box"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h6 class="card-title fw-bold">
                                <a href="single_product.php?id=<?php echo $relatedProduct['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($relatedProduct['title']); ?>
                                </a>
                            </h6>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary">$<?php echo number_format($relatedProduct['price'], 2); ?></span>
                                <a href="single_product.php?id=<?php echo $relatedProduct['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add to cart function
        function addToCart(productId) {
            const button = event.target.closest('.add-to-cart-btn');
            const originalHTML = button.innerHTML;
            const quantityInput = document.getElementById('quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
            
            // Show loading state
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding to Cart...';
            button.disabled = true;
            
            // Make actual API call to cart_actions.php
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_to_cart&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Added to Cart!';
                    showToast('Product added to cart successfully!', 'success');
                    
                    // Update cart count if function exists
                    if (typeof updateCartCount === 'function') {
                        updateCartCount(data.cart_count);
                    }
                    
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
        
        // Share product function
        function shareProduct() {
            const url = window.location.href;
            const title = document.title;
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                }).catch(console.error);
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Product link copied to clipboard!', 'info');
                }).catch(() => {
                    showToast('Unable to copy link', 'error');
                });
            }
        }
        
        // Toast notification function
        function showToast(message, type = 'info') {
            const toastColors = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'info': 'alert-info'
            };
            
            const toastIcons = {
                'success': 'check-circle',
                'error': 'exclamation-triangle',
                'info': 'info-circle'
            };
            
            const toast = document.createElement('div');
            toast.className = `alert ${toastColors[type]} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);';
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${toastIcons[type]} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>