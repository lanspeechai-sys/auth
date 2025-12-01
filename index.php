<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    switch ($user['role']) {
        case 'super_admin':
            header('Location: admin/dashboard.php');
            break;
        case 'school_admin':
            header('Location: school-admin/dashboard.php');
            break;
        case 'student':
            header('Location: user/dashboard.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchoolLink Africa - Connecting Schools Across Africa</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
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
                        <a class="nav-link" href="#home">
                            <i class="bi bi-house"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="all_products.php">
                            <i class="bi bi-grid-3x3-gap"></i> All Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                </ul>
                
                <!-- Search Bar -->
                <form class="d-flex me-3" action="product_search_result.php" method="GET" style="min-width: 300px;">
                    <div class="input-group">
                        <input class="form-control" type="search" name="q" placeholder="Search products..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bi bi-person-plus"></i> Register
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="login.php">
                                <i class="bi bi-mortarboard"></i> Student/Alumni Login
                            </a></li>
                            <li><a class="dropdown-item" href="login.php?type=school_admin">
                                <i class="bi bi-building"></i> School Admin Login
                            </a></li>
                            <li><a class="dropdown-item" href="login.php?type=super_admin">
                                <i class="bi bi-shield-check"></i> Super Admin Login
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Message Display -->
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?> alert-dismissible fade show m-0" role="alert" style="position: fixed; top: 76px; left: 0; right: 0; z-index: 1050; border-radius: 0;">
            <div class="container">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row min-vh-100 align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Reconnecting African Schools with Their Alumni
                    </h1>
                    <p class="lead text-white-50 mb-5">
                        SchoolLink Africa bridges the gap between schools and their former students, 
                        creating a vibrant community where education continues beyond graduation.
                    </p>
                    
                    <div class="d-grid gap-3 d-md-flex">
                        <a href="register-school.php" class="btn btn-warning btn-lg px-4 me-md-3">
                            <i class="bi bi-building"></i> Register Your School
                        </a>
                        <a href="register.php" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-person-plus"></i> Join as Alumni/Student
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-6 text-center">
                    <div class="hero-image">
                        <i class="bi bi-diagram-3 text-white" style="font-size: 15rem; opacity: 0.1;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">Our Mission</h2>
                    <p class="lead mb-5">
                        Many African schools lose contact with their students after graduation. 
                        SchoolLink Africa solves this problem by creating a digital platform where 
                        schools can maintain lifelong relationships with their alumni, fostering 
                        continued growth, mentorship, and community development.
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-people-fill text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h4>Stay Connected</h4>
                    <p class="text-muted">
                        Keep in touch with classmates and teachers long after graduation day.
                    </p>
                </div>
                
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-briefcase-fill text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h4>Career Opportunities</h4>
                    <p class="text-muted">
                        Discover job opportunities and career guidance from successful alumni.
                    </p>
                </div>
                
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-calendar-event text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h4>School Events</h4>
                    <p class="text-muted">
                        Stay informed about reunions, fundraisers, and special school events.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center mb-5">
                    <h2 class="display-5 fw-bold">Platform Features</h2>
                    <p class="lead text-muted">
                        Everything your school community needs to stay connected and engaged.
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-shield-check text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Verified Schools</h5>
                            <p class="card-text text-muted">
                                All schools are verified by our administrators to ensure authenticity and safety.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-chat-dots text-success" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Alumni Directory</h5>
                            <p class="card-text text-muted">
                                Browse and connect with alumni by graduation year, field of study, or location.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-megaphone text-warning" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">School Updates</h5>
                            <p class="card-text text-muted">
                                Get the latest news, achievements, and announcements from your alma mater.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-graph-up text-info" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Admin Dashboard</h5>
                            <p class="card-text text-muted">
                                Comprehensive tools for school administrators to manage their community.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-phone text-danger" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Mobile Friendly</h5>
                            <p class="card-text text-muted">
                                Access the platform seamlessly from any device, anywhere in Africa.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-award text-purple" style="font-size: 2.5rem;"></i>
                            </div>
                            <h5 class="card-title">Achievement Tracking</h5>
                            <p class="card-text text-muted">
                                Celebrate and showcase the success stories of your school's graduates.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
                    <p class="lead mb-5">
                        Join thousands of schools and alumni already connecting on SchoolLink Africa.
                    </p>
                    
                    <div class="d-grid gap-3 d-md-flex justify-content-center">
                        <a href="register-school.php" class="btn btn-warning btn-lg px-4">
                            Register Your School
                        </a>
                        <a href="register.php" class="btn btn-outline-light btn-lg px-4">
                            Join as Student/Alumni
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-mortarboard-fill"></i> SchoolLink Africa</h5>
                    <p class="text-muted">Connecting schools and alumni across Africa.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        &copy; <?php echo date('Y'); ?> SchoolLink Africa. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>