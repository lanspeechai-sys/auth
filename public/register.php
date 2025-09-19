<?php
// public/register.php
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register</title>
  <link rel="stylesheet" href="./assets/css/styles.css" />
</head>
<body>
  <main class="container">
    <h1>Create your account</h1>
    <form id="registerForm" novalidate>
      <div class="field">
        <label>Full name</label>
        <input type="text" name="full_name" id="full_name" maxlength="100" required />
        <small class="error" data-for="full_name"></small>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" id="email" maxlength="150" required />
        <small class="error" data-for="email"></small>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" id="password" minlength="8" required />
        <small class="hint">At least 8 characters.</small>
        <small class="error" data-for="password"></small>
      </div>
      <div class="field">
        <label>Country</label>
        <input type="text" name="country" id="country" maxlength="60" required />
        <small class="error" data-for="country"></small>
      </div>
      <div class="field">
        <label>City</label>
        <input type="text" name="city" id="city" maxlength="60" required />
        <small class="error" data-for="city"></small>
      </div>
      <div class="field">
        <label>Contact Number</label>
        <input type="tel" name="contact_number" id="contact_number" maxlength="30" required />
        <small class="hint">Digits, +, spaces, and dashes allowed.</small>
        <small class="error" data-for="contact_number"></small>
      </div>

      <!-- Image: ignored at signup (DB default NULL) -->
      <!-- User role: set at DB level default; not shown in form -->

      <button type="submit" id="submitBtn">Register</button>
      <div id="status" role="status" aria-live="polite"></div>
    </form>
    <p>Already have an account? <a href="./login.php">Log in</a></p>
  </main>
  <script type="module" src="./assets/js/register.js"></script>
</body>
</html>
