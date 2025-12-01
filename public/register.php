<?php
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register</title>
  <link rel="stylesheet" href="/auth/public/assets/css/styles.css" />
</head>
<body>
  <main class="container">
    <h1>Create your account</h1>
    <form id="registerForm" novalidate>
      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" name="full_name" id="full_name" maxlength="100" required />
        <small class="error" data-for="full_name"></small>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" maxlength="50" required />
        <small class="error" data-for="email"></small>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" minlength="8" required />
        <small class="hint">At least 8 characters.</small>
        <small class="error" data-for="password"></small>
      </div>
      <div class="field">
        <label for="country">Country</label>
        <input type="text" name="country" id="country" maxlength="30" required />
        <small class="error" data-for="country"></small>
      </div>
      <div class="field">
        <label for="city">City</label>
        <input type="text" name="city" id="city" maxlength="30" required />
        <small class="error" data-for="city"></small>
      </div>
      <div class="field">
        <label for="contact_number">Contact Number</label>
        <input type="tel" name="contact_number" id="contact_number" maxlength="15" required />
        <small class="hint">Digits, +, spaces, and dashes allowed.</small>
        <small class="error" data-for="contact_number"></small>
      </div>

      <button type="submit" id="submitBtn">Register</button>
      <div id="status" role="status" aria-live="polite"></div>
    </form>
    <p>Already have an account? <a href="/auth/public/login.php">Log in</a></p>
  </main>
  <script type="module" src="/auth/public/assets/js/register.js"></script>
</body>
</html>
