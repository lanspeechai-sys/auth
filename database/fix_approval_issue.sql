-- Fix for "Account Pending Approval" issue
-- This script will approve all school admin users for schools that are already approved

-- First, let's see what we're working with
-- Check current state of school admins for approved schools
SELECT 
    u.id, 
    u.name, 
    u.email, 
    u.approved as user_approved,
    u.role,
    s.name as school_name, 
    s.approved as school_approved
FROM users u 
JOIN schools s ON u.school_id = s.id 
WHERE u.role = 'school_admin' 
AND s.approved = 1;

-- Now approve all school admin users for approved schools
UPDATE users u
JOIN schools s ON u.school_id = s.id
SET u.approved = 1
WHERE u.role = 'school_admin' 
AND s.approved = 1 
AND u.approved = 0;

-- Verify the fix worked
SELECT 
    u.id, 
    u.name, 
    u.email, 
    u.approved as user_approved,
    u.role,
    s.name as school_name, 
    s.approved as school_approved
FROM users u 
JOIN schools s ON u.school_id = s.id 
WHERE u.role = 'school_admin' 
AND s.approved = 1;

-- Also check if there are any other students that might need approval
-- (This is optional - only run if you want to approve all students for approved schools)
-- UPDATE users u
-- JOIN schools s ON u.school_id = s.id
-- SET u.approved = 1
-- WHERE u.role = 'student' 
-- AND s.approved = 1 
-- AND u.approved = 0;