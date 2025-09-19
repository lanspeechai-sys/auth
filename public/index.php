<?php
session_start();
$logged_in = isset($_SESSION['user_id']);
$name = $logged_in ? htmlspecialchars($_SESSION['user_name']) : null;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Home</title>
  <link rel="stylesheet" href="/auth/public/assets/css/styles.css" />
</head>
<body>
  <nav class="nav">
    <a href="/auth/public/index.php" class="brand">E-Comm Lab</a>
    <div class="spacer"></div>
    <?php if ($logged_in): ?>
      <span>Hi, <?= $name ?>!</span>
      <a class="btn" href="/auth/actions/logout.php">Logout</a>
    <?php else: ?>
      <a class="btn" href="/auth/public/register.php">Register</a>
      <a class="btn" href="/auth/public/login.php">Login</a>
    <?php endif; ?>
  </nav>
  <main class="container">
    <h1>Welcome</h1>
    <p><?= $logged_in ? 'You are logged in.' : 'Please register or log in.' ?></p>
  </main>
</body>
</html>
