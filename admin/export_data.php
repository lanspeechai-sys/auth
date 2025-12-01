<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$type = $_GET['type'] ?? '';

if (!in_array($type, ['users', 'schools'])) {
    die('Invalid export type');
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'users') {
        // Export users
        $stmt = $pdo->query("
            SELECT u.id, u.name, u.email, u.role, u.graduation_year, 
                   u.occupation, u.industry, u.created_at, s.name as school_name
            FROM users u
            LEFT JOIN schools s ON u.school_id = s.id
            ORDER BY u.created_at DESC
        ");
        
        // CSV Header
        fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Graduation Year', 'Occupation', 'Industry', 'School', 'Joined Date']);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['role'],
                $row['graduation_year'] ?? '',
                $row['occupation'] ?? '',
                $row['industry'] ?? '',
                $row['school_name'] ?? '',
                date('Y-m-d H:i:s', strtotime($row['created_at']))
            ]);
        }
        
    } elseif ($type === 'schools') {
        // Export schools
        $stmt = $pdo->query("
            SELECT s.id, s.name, s.email, s.location, s.website, s.status, 
                   s.approval_date, s.created_at,
                   COUNT(DISTINCT u.id) as student_count
            FROM schools s
            LEFT JOIN users u ON s.id = u.school_id AND u.role = 'student'
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ");
        
        // CSV Header
        fputcsv($output, ['ID', 'School Name', 'Email', 'Location', 'Website', 'Status', 'Approval Date', 'Created Date', 'Student Count']);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['location'] ?? '',
                $row['website'] ?? '',
                $row['status'],
                $row['approval_date'] ? date('Y-m-d', strtotime($row['approval_date'])) : '',
                date('Y-m-d H:i:s', strtotime($row['created_at'])),
                $row['student_count']
            ]);
        }
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    die('Export failed: ' . $e->getMessage());
}
?>