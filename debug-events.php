<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die("Please log in first");
}

$user = getCurrentUser();
$db = getDB();

echo "<h2>Debug Events Display</h2>";
echo "<p><strong>User:</strong> " . htmlspecialchars($user['name']) . " (ID: " . $user['id'] . ")</p>";
echo "<p><strong>School ID:</strong> " . $user['school_id'] . "</p>";

if (!$db) {
    die("<p style='color: red;'>Database connection failed</p>");
}

// Check if events table exists
try {
    $stmt = $db->query("SHOW TABLES LIKE 'events'");
    $table_exists = $stmt->fetch();
    if (!$table_exists) {
        echo "<div class='alert alert-warning'>";
        echo "<h4>Database Setup Issue</h4>";
        echo "<p>The <code>events</code> table doesn't exist in your database.</p>";
        echo "<p>Please run the database setup script to create the events table.</p>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<p>✅ Database table <code>events</code> exists and is ready to use.</p>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database check error: " . $e->getMessage() . "</div>";
}

echo "<h3>1. Posts with type='event' in your school:</h3>";

try {
    $stmt = $db->prepare("SELECT id, title, content, post_type, event_date, created_at, author_id FROM posts WHERE school_id = ? AND post_type = 'event'");
    $stmt->execute([$user['school_id']]);
    $event_posts = $stmt->fetchAll();
    
    if (empty($event_posts)) {
        echo "<p style='color: orange;'>No posts with type 'event' found for your school.</p>";
        echo "<p>To test this:</p>";
        echo "<ol>";
        echo "<li>Go to School Admin → Posts</li>";
        echo "<li>Create a new post</li>";
        echo "<li>Select 'Event' as the post type</li>";
        echo "<li>Add an event date</li>";
        echo "<li>Save the post</li>";
        echo "<li>Come back here to see if it appears</li>";
        echo "</ol>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Content Preview</th><th>Type</th><th>Event Date</th><th>Author ID</th><th>Created</th></tr>";
        foreach ($event_posts as $post) {
            echo "<tr>";
            echo "<td>" . $post['id'] . "</td>";
            echo "<td>" . htmlspecialchars($post['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($post['content'], 0, 50)) . "...</td>";
            echo "<td>" . $post['post_type'] . "</td>";
            echo "<td>" . ($post['event_date'] ?: 'Not set') . "</td>";
            echo "<td>" . $post['author_id'] . "</td>";
            echo "<td>" . $post['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>2. Dedicated events in your school:</h3>";

try {
    $stmt = $db->prepare("SELECT id, title, description, event_type, event_date, location, created_by, status, created_at FROM events WHERE school_id = ?");
    $stmt->execute([$user['school_id']]);
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        echo "<p style='color: orange;'>No dedicated events found for your school.</p>";
        echo "<p>You can create events using School Admin → Events → Create New Event</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Event Date</th><th>Location</th><th>Status</th><th>Created By</th><th>Created</th></tr>";
        foreach ($events as $event) {
            echo "<tr>";
            echo "<td>" . $event['id'] . "</td>";
            echo "<td>" . htmlspecialchars($event['title']) . "</td>";
            echo "<td>" . $event['event_type'] . "</td>";
            echo "<td>" . $event['event_date'] . "</td>";
            echo "<td>" . htmlspecialchars($event['location'] ?: 'Not specified') . "</td>";
            echo "<td>" . $event['status'] . "</td>";
            echo "<td>" . $event['created_by'] . "</td>";
            echo "<td>" . $event['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>3. Test combined query (like in events.php):</h3>";

try {
    // Test the queries from events.php
    $where_conditions = ["e.school_id = ?", "e.status = 'active'"];
    $params = [$user['school_id']];
    $where_clause = implode(' AND ', $where_conditions);

    // Query 1: Get dedicated events from events table
    $events_query = "
        SELECT e.*, u.name as creator_name,
               'event' as source_type, e.id as source_id
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE {$where_clause}
    ";
    
    $stmt = $db->prepare($events_query);
    $stmt->execute($params);
    $events_from_table = $stmt->fetchAll();
    
    // Query 2: Get posts marked as events
    $posts_query = "
        SELECT 
            p.id,
            p.title,
            p.content as description,
            'general' as event_type,
            p.event_date,
            '' as location,
            NULL as max_attendees,
            0 as registration_required,
            'active' as status,
            p.author_id as created_by,
            p.created_at,
            p.created_at as updated_at,
            u.name as creator_name,
            'post' as source_type,
            p.id as source_id
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.school_id = ? AND p.post_type = 'event'
    ";
    
    $stmt = $db->prepare($posts_query);
    $stmt->execute([$user['school_id']]);
    $posts_as_events = $stmt->fetchAll();
    
    // Combine both arrays and sort by event_date
    $combined_events = array_merge($events_from_table, $posts_as_events);
    
    echo "<p><strong>Events from events table:</strong> " . count($events_from_table) . "</p>";
    echo "<p><strong>Posts as events:</strong> " . count($posts_as_events) . "</p>";
    echo "<p><strong>Combined total:</strong> " . count($combined_events) . "</p>";
    
    if (!empty($combined_events)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Title</th><th>Source</th><th>Type</th><th>Event Date</th><th>Creator</th><th>Created</th></tr>";
        foreach ($combined_events as $item) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['title']) . "</td>";
            echo "<td>" . $item['source_type'] . "</td>";
            echo "<td>" . $item['event_type'] . "</td>";
            echo "<td>" . ($item['event_date'] ?: 'Not set') . "</td>";
            echo "<td>" . htmlspecialchars($item['creator_name']) . "</td>";
            echo "<td>" . $item['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error in combined query: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='user/events.php'>Go to User Events Page</a></p>";
echo "<p><a href='school-admin/events.php'>Go to School Admin Events Management</a></p>";
echo "<p><a href='school-admin/posts.php'>Go to School Admin Posts</a></p>";
?>