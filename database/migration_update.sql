-- Database Migration Script for SchoolLink Africa
-- Run these commands to update your existing database to match the new schema

-- First, check what columns already exist in your tables
-- You can run: DESCRIBE users; and DESCRIBE schools; to see current structure

-- Update users table - Add missing columns only if they don't exist
-- Check each column first before adding

-- Add user profile columns (run each ALTER TABLE separately and ignore errors for existing columns)
ALTER TABLE users ADD COLUMN graduation_year VARCHAR(4) NULL;
ALTER TABLE users ADD COLUMN current_occupation VARCHAR(200) NULL;
ALTER TABLE users ADD COLUMN industry VARCHAR(100) NULL;
ALTER TABLE users ADD COLUMN location VARCHAR(100) NULL;
ALTER TABLE users ADD COLUMN bio TEXT NULL;
ALTER TABLE users ADD COLUMN linkedin_profile VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN website VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL;
ALTER TABLE users ADD COLUMN skills TEXT NULL;
ALTER TABLE users ADD COLUMN interests TEXT NULL;

-- Add indexes for users table
ALTER TABLE users ADD INDEX idx_approved (approved);

-- Update schools table - Add missing columns only if they don't exist
-- The website column already exists, so skip it
-- ALTER TABLE schools ADD COLUMN website VARCHAR(255) NULL; -- SKIP THIS ONE
ALTER TABLE schools ADD COLUMN status ENUM('active', 'suspended') DEFAULT 'active';

-- Add indexes for schools table
ALTER TABLE schools ADD INDEX idx_status (status);

-- Update existing schools to have active status
UPDATE schools SET status = 'active' WHERE status IS NULL;

-- Verify your tables now have all required columns
-- Run these to check:
-- DESCRIBE users;
-- DESCRIBE schools;

-- If you get any "Duplicate column name" errors, that means the column already exists
-- which is fine - just skip that particular ALTER TABLE command