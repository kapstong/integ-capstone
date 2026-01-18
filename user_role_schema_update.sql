-- User Role Schema Update for Financial Management System
-- Changes: Admin, Staff, User -> Super-admin, admin, staff
-- Super-admin: Highest level access (renamed from admin folder)
-- Admin: Financial manager role
-- Staff: Regular staff (formerly User role)

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 18, 2026 at 04:25 AM
-- Server version: 10.11.14-MariaDB-ubu2204
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Store current session variables (safely handle NULL values)
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Update the users table role enum to new hierarchy
-- Note: This will require dropping and recreating the enum to change values

-- First, backup existing data (optional - uncomment if needed)
/*
CREATE TABLE users_backup AS SELECT * FROM users;
*/

-- Step 1: Update existing roles to new values
-- 'admin' -> 'super_admin'
-- 'user' -> 'staff'
-- 'staff' -> 'staff' (unchanged)

UPDATE users SET role = 'super_admin' WHERE role = 'admin';
UPDATE users SET role = 'staff' WHERE role = 'user';
-- 'staff' role remains as 'staff'

-- Step 2: Alter the table to use new enum values
ALTER TABLE `users` CHANGE `role` `role` ENUM('super_admin','admin','staff') NOT NULL DEFAULT 'staff';

-- Step 3: Update any existing data that references old role names in related tables
-- Update approval_workflows that reference 'manager' role (may need to map to 'admin')
UPDATE approval_workflows SET level1_role = 'admin' WHERE level1_role = 'manager';
UPDATE approval_workflows SET level2_role = 'admin' WHERE level2_role = 'manager';

-- Update roles table to reflect new hierarchy
UPDATE roles SET name = 'Super Administrator', description = 'System super administrator with full access to all modules including superadmin folder' WHERE name = 'Administrator';

-- Insert new 'admin' role for financial managers
INSERT INTO roles (name, description, is_system) VALUES
('Financial Manager', 'Financial administrator with access to financial management modules', 0);

-- Update user_roles to reflect new role assignments
-- Users with old 'Administrator' role get 'Super Administrator'
-- You may need to manually assign 'Financial Manager' roles as appropriate

-- Update any workflow definitions that reference old roles
UPDATE workflows SET
definition = REPLACE(definition, '"manager"', '"admin"')
WHERE definition LIKE '%"manager"%';

-- Update system settings or configurations that reference old roles
-- (Add specific updates based on your system's configuration tables)

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Verification queries (run after update):
/*
-- Check role distribution
SELECT role, COUNT(*) as count FROM users GROUP BY role;

-- Verify enum values
SHOW COLUMNS FROM users WHERE Field = 'role';

-- Check related tables for consistency
SELECT level1_role, level2_role FROM approval_workflows;
*/
