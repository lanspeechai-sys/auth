<?php
require_once 'includes/auth.php';
require_once 'brand_controller.php';

// Check if user is logged in and is admin
requireRole(['super_admin', 'school_admin']);

// Set action for controller
$_POST['action'] = 'update_brand';

$controller = new BrandController();
$controller->handleRequest();
?>