-- ATIERA Financial Management System - Database Updates
-- This file contains additional tables to enhance the financial management system
-- Run this after the main schema has been imported

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. CURRENCY MANAGEMENT
-- =============================================================================

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `currency_code` varchar(3) NOT NULL COMMENT 'ISO 4217 currency code (USD, EUR, PHP, etc.)',
  `currency_name` varchar(50) NOT NULL COMMENT 'Full currency name',
  `symbol` varchar(10) NOT NULL COMMENT 'Currency symbol',
  `decimal_places` int(11) DEFAULT 2 COMMENT 'Number of decimal places',
  `is_active` tinyint(1) DEFAULT 1,
  `exchange_rate` decimal(15,8) DEFAULT 1.00000000 COMMENT 'Exchange rate to base currency',
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `currency_code` (`currency_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Insert default currencies
INSERT INTO `currencies` (`currency_code`, `currency_name`, `symbol`, `decimal_places`, `is_active`, `exchange_rate`) VALUES
('PHP', 'Philippine Peso', '₱', 2, 1, 1.00000000),
('USD', 'US Dollar', '$', 2, 1, 56.50000000),
('EUR', 'Euro', '€', 2, 1, 61.20000000);

-- =============================================================================
-- 2. BANK ACCOUNTS MANAGEMENT
-- =============================================================================

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `branch_name` varchar(100) DEFAULT NULL,
  `account_type` enum('checking','savings','credit_card','loan') NOT NULL,
  `currency_id` int(11) DEFAULT 1 COMMENT 'Reference to currencies table',
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `reconciliation_date` date DEFAULT NULL COMMENT 'Last reconciliation date',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_number` (`account_number`),
  KEY `currency_id` (`currency_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_bank_accounts_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_bank_accounts_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 3. TAX CODES MANAGEMENT
-- =============================================================================

CREATE TABLE `tax_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_code` varchar(20) NOT NULL,
  `tax_name` varchar(100) NOT NULL,
  `tax_type` enum('vat','income_tax','withholding','service_charge','other') NOT NULL,
  `rate` decimal(5,2) NOT NULL COMMENT 'Tax rate as percentage',
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_code` (`tax_code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_tax_codes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Insert default tax codes
INSERT INTO `tax_codes` (`tax_code`, `tax_name`, `tax_type`, `rate`, `is_active`, `description`) VALUES
('VAT12', 'Value Added Tax 12%', 'vat', 12.00, 1, 'Standard VAT rate'),
('VAT0', 'Value Added Tax 0%', 'vat', 0.00, 1, 'Zero-rated VAT'),
('WT15', 'Withholding Tax 15%', 'withholding', 15.00, 1, 'Expanded withholding tax'),
('WT10', 'Withholding Tax 10%', 'withholding', 10.00, 1, 'Percentage tax'),
('SC10', 'Service Charge 10%', 'service_charge', 10.00, 1, 'Standard service charge');

-- =============================================================================
-- 4. FIXED ASSETS REGISTER
-- =============================================================================

CREATE TABLE `fixed_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(20) NOT NULL,
  `asset_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `asset_category` varchar(50) DEFAULT NULL COMMENT 'Equipment, Vehicles, Buildings, etc.',
  `purchase_date` date NOT NULL,
  `purchase_cost` decimal(15,2) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `depreciation_method` enum('straight_line','declining_balance','units_of_production') DEFAULT 'straight_line',
  `useful_life_years` int(11) NOT NULL,
  `salvage_value` decimal(15,2) DEFAULT 0.00,
  `accumulated_depreciation` decimal(15,2) DEFAULT 0.00,
  `current_value` decimal(15,2) DEFAULT 0.00,
  `depreciation_account_id` int(11) DEFAULT NULL COMMENT 'Accumulated depreciation account',
  `asset_account_id` int(11) DEFAULT NULL COMMENT 'Fixed asset account',
  `status` enum('active','disposed','sold','lost') DEFAULT 'active',
  `disposal_date` date DEFAULT NULL,
  `disposal_value` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `supplier_id` (`supplier_id`),
  KEY `department_id` (`department_id`),
  KEY `depreciation_account_id` (`depreciation_account_id`),
  KEY `asset_account_id` (`asset_account_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_assets_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `fk_assets_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_assets_depr_account` FOREIGN KEY (`depreciation_account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `fk_assets_account` FOREIGN KEY (`asset_account_id`) REFERENCES `chart_of_accounts` (`id`),
  CONSTRAINT `fk_assets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Table for depreciation schedule
CREATE TABLE `asset_depreciation_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `depreciation_date` date NOT NULL,
  `depreciation_amount` decimal(15,2) NOT NULL,
  `accumulated_depreciation` decimal(15,2) NOT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `fk_depr_schedule_asset` FOREIGN KEY (`asset_id`) REFERENCES `fixed_assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_depr_schedule_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 5. RECURRING TRANSACTIONS
-- =============================================================================

CREATE TABLE `recurring_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('journal_entry','invoice','bill','payment') NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `frequency` enum('daily','weekly','monthly','quarterly','yearly') NOT NULL,
  `frequency_value` int(11) DEFAULT 1 COMMENT 'Every X days/weeks/months',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_run_date` date NOT NULL,
  `last_run_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON template for the transaction',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `next_run_date` (`next_run_date`),
  KEY `is_active` (`is_active`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_recurring_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- 6. EMAIL TEMPLATES
-- =============================================================================

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_code` varchar(50) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_text` text DEFAULT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of available variables',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_code` (`template_code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_email_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Insert default email templates
INSERT INTO `email_templates` (`template_code`, `template_name`, `subject`, `body_html`, `body_text`, `variables`) VALUES
('invoice_overdue', 'Invoice Overdue Notice', 'Payment Overdue for Invoice {invoice_number}', '<p>Dear {customer_name},</p><p>Your invoice {invoice_number} for {total_amount} is now overdue. Please arrange payment immediately.</p><p>Best regards,<br>ATIERA Finance Team</p>', 'Dear {customer_name},\n\nYour invoice {invoice_number} for {total_amount} is now overdue. Please arrange payment immediately.\n\nBest regards,\nATIERA Finance Team', '["customer_name","invoice_number","total_amount","due_date"]'),
('payment_reminder', 'Payment Reminder', 'Payment Reminder for Invoice {invoice_number}', '<p>Dear {customer_name},</p><p>This is a friendly reminder that payment for invoice {invoice_number} is due on {due_date}.</p><p>Best regards,<br>ATIERA Finance Team</p>', 'Dear {customer_name},\n\nThis is a friendly reminder that payment for invoice {invoice_number} is due on {due_date}.\n\nBest regards,\nATIERA Finance Team', '["customer_name","invoice_number","due_date","total_amount"]'),
('welcome_user', 'Welcome New User', 'Welcome to ATIERA Finance', '<p>Dear {user_name},</p><p>Welcome to ATIERA Financial Management System. Your account has been created successfully.</p><p>Username: {username}</p><p>Best regards,<br>ATIERA Team</p>', 'Dear {user_name},\n\nWelcome to ATIERA Financial Management System. Your account has been created successfully.\n\nUsername: {username}\n\nBest regards,\nATIERA Team', '["user_name","username","login_url"]');

-- =============================================================================
-- 7. COMPANY/BRANCH MANAGEMENT
-- =============================================================================

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_code` varchar(20) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `legal_name` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `default_currency_id` int(11) DEFAULT 1,
  `fiscal_year_start` date DEFAULT '2024-01-01',
  `fiscal_year_end` date DEFAULT '2024-12-31',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `default_currency_id` (`default_currency_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_companies_currency` FOREIGN KEY (`default_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_companies_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Insert default company
INSERT INTO `companies` (`company_code`, `company_name`, `legal_name`, `address`, `phone`, `email`) VALUES
('ATIERA', 'ATIERA Hotel & Restaurant', 'ATIERA Hospitality Inc.', 'Sample Address, Philippines', '+63-123-456-7890', 'info@atiera.com');

CREATE TABLE `company_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_code` (`branch_code`),
  KEY `company_id` (`company_id`),
  KEY `manager_id` (`manager_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_branches_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_branches_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_branches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =============================================================================
-- UPDATE EXISTING TABLES TO SUPPORT NEW FEATURES
-- =============================================================================

-- Add currency support to existing tables
ALTER TABLE `approval_workflows`
ADD COLUMN `currency_id` int(11) DEFAULT 1 AFTER `currency`,
ADD CONSTRAINT `fk_approval_workflows_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`);

-- Add tax code references
ALTER TABLE `bills`
ADD COLUMN `tax_code_id` int(11) DEFAULT NULL AFTER `tax_rate`,
ADD CONSTRAINT `fk_bills_tax_code` FOREIGN KEY (`tax_code_id`) REFERENCES `tax_codes` (`id`);

ALTER TABLE `invoices`
ADD COLUMN `tax_code_id` int(11) DEFAULT NULL AFTER `tax_rate`,
ADD CONSTRAINT `fk_invoices_tax_code` FOREIGN KEY (`tax_code_id`) REFERENCES `tax_codes` (`id`);

-- Add bank account references to payments
ALTER TABLE `payments_made`
ADD COLUMN `bank_account_id` int(11) DEFAULT NULL AFTER `reference_number`,
ADD CONSTRAINT `fk_payments_made_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`);

ALTER TABLE `payments_received`
ADD COLUMN `bank_account_id` int(11) DEFAULT NULL AFTER `reference_number`,
ADD CONSTRAINT `fk_payments_received_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`);

ALTER TABLE `disbursements`
ADD COLUMN `bank_account_id` int(11) DEFAULT NULL AFTER `reference_number`,
ADD CONSTRAINT `fk_disbursements_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`);

-- Add company references (for multi-company support)
ALTER TABLE `users`
ADD COLUMN `company_id` int(11) DEFAULT 1 AFTER `department`,
ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

ALTER TABLE `departments`
ADD COLUMN `company_id` int(11) DEFAULT 1 AFTER `is_active`,
ADD CONSTRAINT `fk_departments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

-- Update existing records to use default company
UPDATE `users` SET `company_id` = 1 WHERE `company_id` IS NULL;
UPDATE `departments` SET `company_id` = 1 WHERE `company_id` IS NULL;

-- =============================================================================
-- CREATE USEFUL VIEWS FOR THE NEW TABLES
-- =============================================================================

-- View for asset depreciation summary
CREATE VIEW `v_asset_depreciation_summary` AS
SELECT
    fa.id,
    fa.asset_code,
    fa.asset_name,
    fa.purchase_cost,
    fa.accumulated_depreciation,
    (fa.purchase_cost - fa.accumulated_depreciation) as current_value,
    fa.useful_life_years,
    fa.status,
    d.dept_name as department_name,
    COALESCE(SUM(ads.depreciation_amount), 0) as total_depreciated_this_year
FROM fixed_assets fa
LEFT JOIN departments d ON fa.department_id = d.id
LEFT JOIN asset_depreciation_schedule ads ON fa.id = ads.asset_id
    AND YEAR(ads.depreciation_date) = YEAR(CURDATE())
GROUP BY fa.id, fa.asset_code, fa.asset_name, fa.purchase_cost,
         fa.accumulated_depreciation, fa.useful_life_years, fa.status, d.dept_name;

-- View for bank account balances
CREATE VIEW `v_bank_account_balances` AS
SELECT
    ba.id,
    ba.account_number,
    ba.account_name,
    ba.bank_name,
    c.currency_code,
    c.symbol,
    ba.opening_balance,
    ba.current_balance,
    (ba.current_balance - ba.opening_balance) as balance_change,
    ba.reconciliation_date,
    ba.is_active
FROM bank_accounts ba
JOIN currencies c ON ba.currency_id = c.id;

-- View for recurring transactions summary
CREATE VIEW `v_recurring_transactions_summary` AS
SELECT
    rt.id,
    rt.name,
    rt.transaction_type,
    rt.frequency,
    rt.next_run_date,
    rt.last_run_date,
    rt.is_active,
    u.full_name as created_by_name,
    CASE
        WHEN rt.next_run_date < CURDATE() THEN 'Overdue'
        WHEN rt.next_run_date = CURDATE() THEN 'Due Today'
        WHEN rt.next_run_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Due Soon'
        ELSE 'Scheduled'
    END as status
FROM recurring_transactions rt
LEFT JOIN users u ON rt.created_by = u.id;

-- =============================================================================
-- CREATE TRIGGERS FOR AUTOMATIC UPDATES
-- =============================================================================

-- Trigger to update asset current value when depreciation is added
DELIMITER $$
CREATE TRIGGER `trg_update_asset_value` AFTER INSERT ON `asset_depreciation_schedule`
FOR EACH ROW
BEGIN
    UPDATE fixed_assets
    SET accumulated_depreciation = accumulated_depreciation + NEW.depreciation_amount,
        current_value = purchase_cost - (accumulated_depreciation + NEW.depreciation_amount),
        updated_at = NOW()
    WHERE id = NEW.asset_id;
END$$
DELIMITER ;

-- Trigger to update bank account balance from payments
DELIMITER $$
CREATE TRIGGER `trg_update_bank_balance_payment_made` AFTER INSERT ON `payments_made`
FOR EACH ROW
BEGIN
    IF NEW.bank_account_id IS NOT NULL THEN
        UPDATE bank_accounts
        SET current_balance = current_balance - NEW.amount,
            updated_at = NOW()
        WHERE id = NEW.bank_account_id;
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `trg_update_bank_balance_payment_received` AFTER INSERT ON `payments_received`
FOR EACH ROW
BEGIN
    IF NEW.bank_account_id IS NOT NULL THEN
        UPDATE bank_accounts
        SET current_balance = current_balance + NEW.amount,
            updated_at = NOW()
        WHERE id = NEW.bank_account_id;
    END IF;
END$$
DELIMITER ;

-- =============================================================================
-- INDEXES FOR PERFORMANCE
-- =============================================================================

-- Add indexes for commonly queried fields
CREATE INDEX idx_currencies_active ON currencies(is_active);
CREATE INDEX idx_bank_accounts_active ON bank_accounts(is_active);
CREATE INDEX idx_tax_codes_active ON tax_codes(is_active);
CREATE INDEX idx_fixed_assets_status ON fixed_assets(status);
CREATE INDEX idx_fixed_assets_department ON fixed_assets(department_id);
CREATE INDEX idx_recurring_active_next_run ON recurring_transactions(is_active, next_run_date);
CREATE INDEX idx_email_templates_active ON email_templates(is_active);
CREATE INDEX idx_companies_active ON companies(is_active);
CREATE INDEX idx_branches_active ON company_branches(is_active);

-- =============================================================================
-- FINAL NOTES
-- =============================================================================

/*
This update file adds the following enhancements to the ATIERA Financial Management System:

1. **Currency Management**: Support for multiple currencies with exchange rates
2. **Bank Account Management**: Track bank accounts and balances
3. **Tax Code Management**: Flexible tax code configuration
4. **Fixed Assets Register**: Complete asset tracking with depreciation
5. **Recurring Transactions**: Automated recurring entries
6. **Email Templates**: Customizable email notifications
7. **Multi-Company Support**: Support for multiple companies/branches

To apply these updates:
1. Backup your database
2. Run this SQL file
3. Update your application code to use the new tables
4. Test thoroughly in a development environment first

Note: Some existing tables have been modified to add foreign key relationships.
The changes are backward compatible, but verify your application works correctly.
*/