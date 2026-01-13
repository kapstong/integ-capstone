-- ATIERA Hotel & Restaurant Financial Management System - Hospitality Extensions
-- Run this after the base schema and database_updates.sql are applied

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. REVENUE CENTERS (if not already present)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `revenue_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `center_code` varchar(20) NOT NULL,
  `center_name` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `revenue_account_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `center_code` (`center_code`),
  KEY `department_id` (`department_id`),
  KEY `revenue_account_id` (`revenue_account_id`),
  CONSTRAINT `fk_revenue_centers_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_revenue_centers_account` FOREIGN KEY (`revenue_account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 2. OUTLETS (ROOMS, RESTAURANT, BAR, BANQUETS, SPA, ETC.)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `outlets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `outlet_code` varchar(20) NOT NULL,
  `outlet_name` varchar(120) NOT NULL,
  `outlet_type` enum('rooms','restaurant','bar','banquet','spa','other') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `revenue_center_id` int(11) DEFAULT NULL,
  `revenue_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_code` (`outlet_code`),
  KEY `department_id` (`department_id`),
  KEY `revenue_center_id` (`revenue_center_id`),
  KEY `revenue_account_id` (`revenue_account_id`),
  CONSTRAINT `fk_outlets_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_outlets_revenue_center` FOREIGN KEY (`revenue_center_id`) REFERENCES `revenue_centers` (`id`),
  CONSTRAINT `fk_outlets_revenue_account` FOREIGN KEY (`revenue_account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 3. DAILY OUTLET SALES (ROOM REVENUE, F&B, EVENTS, ETC.)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `outlet_daily_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_date` date NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `gross_sales` decimal(15,2) DEFAULT 0.00,
  `discounts` decimal(15,2) DEFAULT 0.00,
  `service_charge` decimal(15,2) DEFAULT 0.00,
  `taxes` decimal(15,2) DEFAULT 0.00,
  `net_sales` decimal(15,2) DEFAULT 0.00,
  `covers` int(11) DEFAULT NULL COMMENT 'Restaurant covers (guests served)',
  `room_nights` int(11) DEFAULT NULL COMMENT 'Room nights sold for rooms outlet',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_daily_unique` (`business_date`,`outlet_id`),
  KEY `outlet_id` (`outlet_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_outlet_daily_sales_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  CONSTRAINT `fk_outlet_daily_sales_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 4. DAILY REVENUE SUMMARY (DEPARTMENTAL SUMMARY)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `daily_revenue_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_date` date NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `revenue_center_id` int(11) DEFAULT NULL,
  `source_system` varchar(50) DEFAULT 'MANUAL',
  `total_transactions` int(11) DEFAULT 0,
  `gross_revenue` decimal(15,2) DEFAULT 0.00,
  `discounts` decimal(15,2) DEFAULT 0.00,
  `service_charge` decimal(15,2) DEFAULT 0.00,
  `taxes` decimal(15,2) DEFAULT 0.00,
  `net_revenue` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_revenue_unique` (`business_date`,`department_id`,`revenue_center_id`,`source_system`),
  KEY `department_id` (`department_id`),
  KEY `revenue_center_id` (`revenue_center_id`),
  CONSTRAINT `fk_daily_revenue_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_daily_revenue_center` FOREIGN KEY (`revenue_center_id`) REFERENCES `revenue_centers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 5. DAILY EXPENSE SUMMARY (USED BY HR/LOGISTICS INTEGRATIONS)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `daily_expense_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_date` date NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `expense_category` varchar(50) NOT NULL,
  `source_system` varchar(50) DEFAULT 'MANUAL',
  `total_transactions` int(11) DEFAULT 0,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_expense_unique` (`business_date`,`department_id`,`expense_category`,`source_system`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_daily_expense_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 6. CASHIER SHIFTS (FRONT DESK / RESTAURANT CASHIERING)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `cashier_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_date` date NOT NULL,
  `outlet_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) NOT NULL,
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `opening_cash` decimal(15,2) DEFAULT 0.00,
  `closing_cash` decimal(15,2) DEFAULT NULL,
  `expected_cash` decimal(15,2) DEFAULT NULL,
  `variance` decimal(15,2) DEFAULT NULL,
  `status` enum('open','closed','reconciled') DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cashier_id` (`cashier_id`),
  KEY `outlet_id` (`outlet_id`),
  CONSTRAINT `fk_cashier_shifts_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_cashier_shifts_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 7. POS BATCH SETTLEMENTS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `pos_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_date` date NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `gross_sales` decimal(15,2) DEFAULT 0.00,
  `discounts` decimal(15,2) DEFAULT 0.00,
  `service_charge` decimal(15,2) DEFAULT 0.00,
  `taxes` decimal(15,2) DEFAULT 0.00,
  `net_sales` decimal(15,2) DEFAULT 0.00,
  `payments_total` decimal(15,2) DEFAULT 0.00,
  `variance` decimal(15,2) DEFAULT 0.00,
  `status` enum('open','closed','posted') DEFAULT 'open',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `outlet_id` (`outlet_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_pos_batches_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  CONSTRAINT `fk_pos_batches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- INDEXES
-- =============================================================================

CREATE INDEX idx_outlet_sales_date ON outlet_daily_sales(business_date);
CREATE INDEX idx_revenue_summary_date ON daily_revenue_summary(business_date);
CREATE INDEX idx_expense_summary_date ON daily_expense_summary(business_date);
CREATE INDEX idx_cashier_shifts_date ON cashier_shifts(shift_date);
CREATE INDEX idx_pos_batches_date ON pos_batches(batch_date);

-- =============================================================================
-- PERMISSIONS FOR HOSPITALITY FINANCE MODULES
-- =============================================================================

INSERT INTO permissions (name, description, created_at)
SELECT 'departments.view', 'View departments and revenue centers', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'departments.view');

INSERT INTO permissions (name, description, created_at)
SELECT 'departments.manage', 'Manage departments and revenue centers', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'departments.manage');

INSERT INTO permissions (name, description, created_at)
SELECT 'cashier.operate', 'Open and close cashier shifts', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'cashier.operate');

INSERT INTO permissions (name, description, created_at)
SELECT 'cashier.view_all', 'View cashier shifts', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'cashier.view_all');

INSERT INTO permissions (name, description, created_at)
SELECT 'integrations.view', 'View integration status', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'integrations.view');

INSERT INTO permissions (name, description, created_at)
SELECT 'reports.usali', 'View hospitality financial reports', NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'reports.usali');

-- Assign new permissions to admin role
INSERT IGNORE INTO role_permissions (role_id, permission_id, assigned_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.name IN (
    'departments.view',
    'departments.manage',
    'cashier.operate',
    'cashier.view_all',
    'integrations.view',
    'reports.usali'
)
WHERE r.name = 'admin';

-- Assign view permissions to manager/accountant roles
INSERT IGNORE INTO role_permissions (role_id, permission_id, assigned_at)
SELECT r.id, p.id, NOW()
FROM roles r
JOIN permissions p ON p.name IN (
    'departments.view',
    'cashier.view_all',
    'integrations.view',
    'reports.usali'
)
WHERE r.name IN ('manager', 'accountant');
