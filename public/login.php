<?php
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>
  <link rel="stylesheet" href="/auth/public/assets/css/styles.css" />
</head>
<body>
  <main class="container">
    <h1>Log in</h1>
    <form id="loginForm" novalidate>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" maxlength="50" required />
        <small class="error" data-for="email"></small>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" minlength="8" required />
        <small class="error" data-for="password"></small>
      </div>
      <button type="submit" id="loginBtn">Login</button>
      <div id="status" role="status" aria-live="polite"></div>
    </form>
    <p>No account? <a href="/auth/public/register.php">Register</a></p>
  </main>
  <script type="module" src="/auth/public/assets/js/login.js"></script>
</body>
</html>
