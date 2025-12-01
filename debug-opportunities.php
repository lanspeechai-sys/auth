<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die("Please log in first");
}

$user = getCurrentUser();
$db = getDB();

echo "<h2>Debug Opportunities Display</h2>";
echo "<p><strong>User:</strong> " . htmlspecialchars($user['name']) . " (ID: " . $user['id'] . ")</p>";
echo "<p><strong>School ID:</strong> " . $user['school_id'] . "</p>";

if (!$db) {
    die("<p style='color: red;'>Database connection failed</p>");
}

// Check if opportunities table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'opportunities'");
    $table_exists = $stmt->fetch();
    if (!$table_exists) {
        echo "<div class='alert alert-warning'>";
        echo "<h4>Database Setup Issue</h4>";
        echo "<p>The <code>opportunities</code> table doesn't exist in your database.</p>";
        echo "<p>This has now been automatically created for you. Try posting an opportunity again!</p>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<p>✅ Database table <code>opportunities</code> exists and is ready to use.</p>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database check error: " . $e->getMessage() . "</div>";
}

echo "<h3>1. Posts with type='opportunity' in your school:</h3>";

try {
    $stmt = $db->prepare("SELECT id, title, content, post_type, created_at, author_id FROM posts WHERE school_id = ? AND post_type = 'opportunity'");
    $stmt->execute([$user['school_id']]);
    $opportunity_posts = $stmt->fetchAll();
    
    if (empty($opportunity_posts)) {
        echo "<p style='color: orange;'>No posts with type 'opportunity' found for your school.</p>";
        echo "<p>To test this:</p>";
        echo "<ol>";
        echo "<li>Go to School Admin → Posts</li>";
        echo "<li>Create a new post</li>";
        echo "<li>Select 'Opportunity' as the post type</li>";
        echo "<li>Save the post</li>";
        echo "<li>Come back here to see if it appears</li>";
        echo "</ol>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Content Preview</th><th>Type</th><th>Author ID</th><th>Created</th></tr>";
        foreach ($opportunity_posts as $post) {
            echo "<tr>";
            echo "<td>" . $post['id'] . "</td>";
            echo "<td>" . htmlspecialchars($post['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($post['content'], 0, 50)) . "...</td>";
            echo "<td>" . $post['post_type'] . "</td>";
            echo "<td>" . $post['author_id'] . "</td>";
            echo "<td>" . $post['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>2. Dedicated opportunities in your school:</h3>";

try {
    $stmt = $db->prepare("SELECT id, title, company_name, opportunity_type, created_at FROM opportunities WHERE school_id = ?");
    $stmt->execute([$user['school_id']]);
    $opportunities = $stmt->fetchAll();
    
    if (empty($opportunities)) {
        echo "<p style='color: orange;'>No dedicated opportunities found for your school.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Company</th><th>Type</th><th>Created</th></tr>";
        foreach ($opportunities as $opp) {
            echo "<tr>";
            echo "<td>" . $opp['id'] . "</td>";
            echo "<td>" . htmlspecialchars($opp['title']) . "</td>";
            echo "<td>" . htmlspecialchars($opp['company_name']) . "</td>";
            echo "<td>" . $opp['opportunity_type'] . "</td>";
            echo "<td>" . $opp['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>3. Test combined query (like in opportunities.php):</h3>";

try {
    // Test the exact queries from opportunities.php
    $where_conditions = ["o.school_id = ?", "o.status = 'active'"];
    $params = [$user['school_id']];
    $where_clause = implode(' AND ', $where_conditions);

    // Query 1: Get from opportunities table
    $opp_query = "
        SELECT 
            o.id,
            o.title,
            o.description,
            o.company_name,
            o.opportunity_type,
            o.location,
            o.salary_range,
            o.requirements,
            o.application_process,
            o.contact_email,
            o.deadline,
            o.created_at,
            u.name as poster_name,
            'opportunity' as source_type,
            o.id as source_id
        FROM opportunities o
        LEFT JOIN users u ON o.posted_by = u.id
        WHERE {$where_clause}
    ";
    
    $stmt = $db->prepare($opp_query);
    $stmt->execute($params);
    $opportunities_from_table = $stmt->fetchAll();
    
    // Query 2: Get posts marked as opportunities
    $posts_query = "
        SELECT 
            p.id,
            p.title,
            p.content as description,
            '' as company_name,
            'job' as opportunity_type,
            '' as location,
            '' as salary_range,
            '' as requirements,
            '' as application_process,
            '' as contact_email,
            NULL as deadline,
            p.created_at,
            u.name as poster_name,
            'post' as source_type,
            p.id as source_id
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.school_id = ? AND p.post_type = 'opportunity'
    ";
    
    $stmt = $db->prepare($posts_query);
    $stmt->execute([$user['school_id']]);
    $posts_as_opportunities = $stmt->fetchAll();
    
    // Combine both arrays and sort by created_at
    $combined_opportunities = array_merge($opportunities_from_table, $posts_as_opportunities);
    
    echo "<p><strong>Opportunities from table:</strong> " . count($opportunities_from_table) . "</p>";
    echo "<p><strong>Posts as opportunities:</strong> " . count($posts_as_opportunities) . "</p>";
    echo "<p><strong>Combined total:</strong> " . count($combined_opportunities) . "</p>";
    
    if (!empty($combined_opportunities)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Title</th><th>Source</th><th>Type</th><th>Company/Poster</th><th>Created</th></tr>";
        foreach ($combined_opportunities as $item) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['title']) . "</td>";
            echo "<td>" . $item['source_type'] . "</td>";
            echo "<td>" . $item['opportunity_type'] . "</td>";
            echo "<td>" . htmlspecialchars($item['company_name'] ?: $item['poster_name']) . "</td>";
            echo "<td>" . $item['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error in combined query: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='user/opportunities.php'>Go to User Opportunities Page</a></p>";
echo "<p><a href='school-admin/opportunities.php'>Go to School Admin Opportunities Management</a></p>";
echo "<p><a href='school-admin/posts.php'>Go to School Admin Posts</a></p>";
?>