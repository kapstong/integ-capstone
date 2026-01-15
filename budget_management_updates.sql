-- Budget management schema and seed data
-- Apply after core schema and database_updates.sql

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `budgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_year` int(4) NOT NULL,
  `budget_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `total_budgeted` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','pending','approved','active','archived','completed') DEFAULT 'draft',
  `department_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_budgets_year` (`budget_year`),
  KEY `idx_budgets_department` (`department_id`),
  KEY `idx_budgets_vendor` (`vendor_id`),
  KEY `idx_budgets_created_by` (`created_by`),
  KEY `idx_budgets_approved_by` (`approved_by`),
  CONSTRAINT `fk_budgets_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_budgets_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `fk_budgets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_budgets_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `budget_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(150) NOT NULL,
  `category_type` enum('revenue','expense','other') NOT NULL DEFAULT 'expense',
  `department_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_budget_categories_department` (`department_id`),
  CONSTRAINT `fk_budget_categories_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `budget_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `budgeted_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `actual_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_budget_items_budget` (`budget_id`),
  KEY `idx_budget_items_category` (`category_id`),
  KEY `idx_budget_items_department` (`department_id`),
  KEY `idx_budget_items_account` (`account_id`),
  KEY `idx_budget_items_vendor` (`vendor_id`),
  CONSTRAINT `fk_budget_items_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_items_category` FOREIGN KEY (`category_id`) REFERENCES `budget_categories` (`id`),
  CONSTRAINT `fk_budget_items_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_budget_items_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `fk_budget_items_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `budget_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `adjustment_type` enum('increase','decrease','transfer') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_budget_adjustments_budget` (`budget_id`),
  KEY `idx_budget_adjustments_department` (`department_id`),
  KEY `idx_budget_adjustments_vendor` (`vendor_id`),
  KEY `idx_budget_adjustments_requested_by` (`requested_by`),
  KEY `idx_budget_adjustments_status` (`status`),
  CONSTRAINT `fk_budget_adjustments_budget` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_budget_adjustments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_budget_adjustments_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `fk_budget_adjustments_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_budget_adjustments_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

ALTER TABLE `budgets`
  ADD COLUMN IF NOT EXISTS `department_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `start_date` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `end_date` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();

ALTER TABLE `budget_items`
  ADD COLUMN IF NOT EXISTS `actual_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `department_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `account_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL;

ALTER TABLE `budget_categories`
  ADD COLUMN IF NOT EXISTS `department_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1;

ALTER TABLE `budget_adjustments`
  ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `effective_date` date DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Rooms Revenue', 'revenue', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Rooms Revenue');

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Food & Beverage Revenue', 'revenue', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Food & Beverage Revenue');

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Events & Catering Revenue', 'revenue', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Events & Catering Revenue');

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Payroll & Benefits', 'expense', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Payroll & Benefits');

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Supplies & Inventory', 'expense', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Supplies & Inventory');

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Utilities & Maintenance', 'expense', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Utilities & Maintenance');

INSERT INTO budget_categories (category_name, category_type, department_id, is_active)
SELECT 'Marketing & Promotions', 'expense', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM budget_categories WHERE category_name = 'Marketing & Promotions');
