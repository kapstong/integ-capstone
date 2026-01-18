-- User Permissions Migration for Financial Management System
-- Adds user_permissions table to allow user-specific permissions beyond roles

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;

-- Create user_permissions table
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`),
  KEY `fk_user_permissions_user` (`user_id`),
  KEY `fk_user_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_user_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions for modules if they don't exist
INSERT IGNORE INTO permissions (name, description, module, created_at) VALUES
('general_ledger.view', 'View General Ledger', 'General Ledger', NOW()),
('accounts_payable.view', 'View Accounts Payable', 'Accounts Payable', NOW()),
('accounts_receivable.view', 'View Accounts Receivable', 'Accounts Receivable', NOW()),
('reports.view', 'View Reports', 'Reports', NOW()),
('budget_management.view', 'View Budget Management', 'Budget Management', NOW()),
('disbursements.view', 'View Disbursements', 'Disbursements', NOW()),
('audit.view', 'View Audit Logs', 'Audit', NOW()),
('settings.view', 'View Settings', 'Settings', NOW());

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
