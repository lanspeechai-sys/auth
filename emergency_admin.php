<?php
/**
 * EMERGENCY Super Admin Creator
 * Simple standalone script - no dependencies
 * DELETE THIS FILE after use!
 */

// ===== CONFIGURATION - UPDATE THESE =====
$adminName = 'Splendour Kalu';
$adminEmail = 'skalu@gmail.com';
$adminPassword = 'admin234';

// Database credentials - UPDATE WITH YOUR LIVE SERVER DETAILS
$db_host = 'localhost';
$db_name = 'ecommerce_2025A_splendour_kalu';
$db_user = 'root';  // Your database username
$db_pass = '';      // Your database password
// =========================================

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    try {
        // Connect to database
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if user exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$adminEmail]);
        
        if ($check->rowCount() > 0) {
            $error = "User already exists! Go to phpMyAdmin and run: DELETE FROM users WHERE email = '$adminEmail'; then try again.";
        } else {
            // Hash password and insert
            $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, approved, created_at) 
                VALUES (?, ?, ?, 'super_admin', 1, NOW())
            ");
            
            $stmt->execute([$adminName, $adminEmail, $hashedPassword]);
            $success = true;
        }
        
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Super Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        button {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #218838; }
        table { width: 100%; margin: 20px 0; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        td:first-child { font-weight: bold; width: 30%; }
        .badge { background: #dc3545; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🔐 Emergency Super Admin Creator</h2>
        
        <?php if ($success): ?>
            <div class="success">
                <h3>✅ Success!</h3>
                <p>Super admin account created successfully!</p>
                <table>
                    <tr><td>Email:</td><td><strong><?php echo htmlspecialchars($adminEmail); ?></strong></td></tr>
                    <tr><td>Password:</td><td><strong><?php echo htmlspecialchars($adminPassword); ?></strong></td></tr>
                </table>
                <p><a href="admin/login.php" style="display:block; text-align:center; padding:15px; background:#007bff; color:white; border-radius:5px;">Go to Admin Login →</a></p>
                <div class="error" style="margin-top: 20px;">
                    <strong>⚠️ CRITICAL:</strong> DELETE this file immediately!
                </div>
            </div>
        
        <?php elseif ($error): ?>
            <div class="error">
                <h3>❌ Error</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                
                <?php if (strpos($error, 'already exists') !== false): ?>
                    <h4>Solutions:</h4>
                    <ol>
                        <li><strong>Delete existing user:</strong> Go to phpMyAdmin → SQL tab → Run:
                            <pre>DELETE FROM users WHERE email = '<?php echo $adminEmail; ?>';</pre>
                            Then refresh this page and click the button again.
                        </li>
                        <li><strong>Or login with default admin:</strong><br>
                            Email: admin@schoollink.africa<br>
                            Password: admin123
                        </li>
                    </ol>
                <?php endif; ?>
                
                <p><a href="">← Try Again</a></p>
            </div>
        
        <?php else: ?>
            <div class="warning">
                <strong>⚠️ Warning:</strong> This will create a super admin account. DELETE this file after use!
            </div>
            
            <h3>Account to Create:</h3>
            <table>
                <tr><td>Name:</td><td><?php echo htmlspecialchars($adminName); ?></td></tr>
                <tr><td>Email:</td><td><?php echo htmlspecialchars($adminEmail); ?></td></tr>
                <tr><td>Password:</td><td><?php echo htmlspecialchars($adminPassword); ?></td></tr>
                <tr><td>Role:</td><td><span class="badge">SUPER ADMIN</span></td></tr>
            </table>
            
            <form method="POST">
                <button type="submit" name="create_admin">Create Super Admin Account</button>
            </form>
            
            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                <strong>Note:</strong> If you need different credentials, edit this PHP file and change the variables at the top.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
