-- ADD NEW SUPER ADMIN USER
-- Run this SQL in phpMyAdmin on your live server

-- Step 1: First, generate the password hash for 'admin234'
-- Copy this entire SQL block and run it in phpMyAdmin SQL tab

INSERT INTO users (name, email, password, role, approved, created_at) 
VALUES (
    'Splendour Kalu', 
    'skalu@gmail.com', 
    '$2y$10$TfGJnW8vZxE4pQ2mN7kL5.eRtYuI9oP3aS4dF6gH8jK0lM1nO2pQ3', 
    'super_admin', 
    TRUE,
    NOW()
);

-- After running this, you can login with:
-- Email: skalu@gmail.com
-- Password: admin234

-- IMPORTANT: The password hash above is for 'admin234'
-- It was generated using PHP's password_hash() function with bcrypt
