<?php
// Get cart count for logged-in users
$cartCount = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    try {
        // Try different possible paths for cart_class.php
        $possiblePaths = [
            __DIR__ . '/../classes/cart_class.php',
            dirname(__DIR__) . '/classes/cart_class.php'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $cart = new Cart();
                $cartCount = $cart->getCartCount();
                break;
            }
        }
    } catch (Exception $e) {
        // Silently fail if cart functionality is not available
        $cartCount = 0;
        error_log("Cart functionality error: " . $e->getMessage());
    }
}
?>

<!-- Enhanced Navigation -->
<nav class="navbar navbar-expand-lg shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 70px;">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="index.php" style="font-size: 1.4rem;">
            <i class="bi bi-mortarboard-fill me-2" style="font-size: 1.6rem;"></i>
            <span>SchoolLink Africa</span>
        </a>
        
        <!-- Mobile toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Navigation -->
            <ul class="navbar-nav me-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center" href="user/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center" href="store.php">
                                <i class="bi bi-shop me-1"></i> Store
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'school_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center" href="school-admin/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-shop me-1"></i> E-Commerce
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                <li><a class="dropdown-item d-flex align-items-center" href="category.php">
                                    <i class="bi bi-tags me-2 text-primary"></i> Categories
                                </a></li>
                                <li><a class="dropdown-item d-flex align-items-center" href="brand.php">
                                    <i class="bi bi-award me-2 text-primary"></i> Brands
                                </a></li>
                                <li><a class="dropdown-item d-flex align-items-center" href="product.php">
                                    <i class="bi bi-box me-2 text-primary"></i> Products
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item d-flex align-items-center" href="store.php">
                                    <i class="bi bi-eye me-2 text-success"></i> View Store
                                </a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Cart (for students) -->
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center position-relative" href="cart.php">
                                <i class="bi bi-cart3 me-1"></i> Cart
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                                      id="cartCount" style="<?php echo $cartCount > 0 ? '' : 'display: none;'; ?> font-size: 0.65em; margin-left: -8px;">
                                    <?php echo $cartCount; ?>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <!-- Search Bar (for store pages) -->
            <?php if (basename($_SERVER['PHP_SELF']) === 'store.php'): ?>
                <form class="d-flex me-3" onsubmit="return false;" style="min-width: 280px;">
                    <div class="input-group">
                        <input class="form-control" type="search" id="searchInput" 
                               placeholder="Search products..." aria-label="Search">
                        <button class="btn btn-outline-light" type="button" onclick="performSearch()">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- User Menu -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> 
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li>
                                <div class="dropdown-header d-flex align-items-center">
                                    <i class="bi bi-person-badge me-2"></i>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                                        <small class="text-muted text-capitalize"><?php echo str_replace('_', ' ', $_SESSION['role']); ?></small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="user/profile.php">
                                <i class="bi bi-person me-2 text-primary"></i> Profile
                            </a></li>
                            <?php if ($_SESSION['role'] === 'student'): ?>
                                <li><a class="dropdown-item d-flex align-items-center" href="user/directory.php">
                                    <i class="bi bi-people me-2 text-primary"></i> Directory
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <!-- Guest Navigation -->
                <div class="navbar-nav">
                    <a class="nav-link text-white d-flex align-items-center me-2" href="login.php">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                    <a class="btn btn-outline-light btn-sm d-flex align-items-center" href="register.php">
                        <i class="bi bi-person-plus me-1"></i> Register
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Cart count update script -->
<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
<script>
    // Function to update cart count
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

    // Load cart count when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        loadCartCount();
    });
</script>
<?php endif; ?>

<style>
    /* Enhanced navbar styles */
    .navbar-brand:hover {
        transform: scale(1.05);
        transition: transform 0.2s ease;
    }
    
    .nav-link {
        transition: all 0.2s ease;
        border-radius: 6px;
        margin: 0 2px;
        padding: 0.5rem 0.75rem !important;
    }
    
    .nav-link:hover {
        background: rgba(255,255,255,0.1);
        transform: translateY(-1px);
    }
    
    .dropdown-menu {
        border-radius: 12px;
        padding: 0.5rem 0;
        margin-top: 0.5rem;
        min-width: 200px;
    }
    
    .dropdown-item {
        padding: 0.5rem 1rem;
        transition: all 0.2s ease;
    }
    
    .dropdown-item:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: translateX(5px);
    }
    
    .dropdown-header {
        padding: 0.75rem 1rem 0.5rem;
        color: #495057;
    }
    
    .navbar-toggler {
        padding: 0.25rem 0.5rem;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    .navbar-toggler:focus {
        box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.25);
    }
    
    /* Badge animation */
    .badge {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .navbar-brand span {
            font-size: 1.1rem;
        }
        
        .nav-link {
            padding: 0.75rem 1rem !important;
            margin: 2px 0;
        }
        
        .dropdown-menu {
            margin-top: 0;
            border-radius: 8px;
        }
        
        .input-group {
            min-width: 200px !important;
        }
    }
</style>