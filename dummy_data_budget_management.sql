-- Dummy Data for Budget Management Module
-- This file contains comprehensive dummy data for all budget management pages
-- including budgets, allocations, tracking data, adjustments, and audit trails

-- Clear existing data first
DELETE FROM budget_adjustments WHERE id > 0;
DELETE FROM budget_items WHERE id > 0;
DELETE FROM budget_categories WHERE id > 0;
DELETE FROM budgets WHERE id > 0;

-- Insert Budget Categories
INSERT INTO budget_categories (category_code, category_name, category_type, department_id, is_active) VALUES
('PAYROLL', 'Payroll & Benefits', 'expense', NULL, 1),
('SUPPLIES', 'Supplies & Inventory', 'expense', NULL, 1),
('UTILITIES', 'Utilities & Maintenance', 'expense', NULL, 1),
('MARKETING', 'Marketing & Promotions', 'expense', NULL, 1),
('ROOMS', 'Rooms & Suites', 'revenue', NULL, 1),
('DINING', 'Dining & Beverage', 'revenue', NULL, 1),
('EVENTS', 'Events & Catering', 'revenue', NULL, 1),
('ADMIN', 'Administrative', 'expense', NULL, 1),
('TECH', 'Technology', 'expense', NULL, 1),
('TRAINING', 'Training & Development', 'expense', NULL, 1);

-- Insert Budgets (explicit ids to ensure FK consistency)
INSERT INTO budgets (id, budget_year, budget_name, description, total_budgeted, status, created_by, department_id, vendor_id, start_date, end_date) VALUES
(1, 2025, 'FY 2025 Annual Budget', 'Main annual budget for fiscal year 2025', 15000000.00, '1', 1, 1, NULL, '2025-01-01', '2025-12-31'),
(2, 2025, 'Q1 2025 Marketing Campaign', 'Q1 marketing and promotional activities', 500000.00, '1', 1, 3, 1, '2025-01-01', '2025-03-31'),
(3, 2025, 'Q2 2025 Technology Upgrade', 'IT infrastructure upgrades and software licenses', 750000.00, '2', 1, 4, 2, '2025-04-01', '2025-06-30'),
(4, 2025, 'Q3 2025 Staff Training', 'Employee training and development programs', 300000.00, '3', 1, 2, NULL, '2025-07-01', '2025-09-30'),
(5, 2025, 'Q4 2025 Holiday Events', 'Holiday season events and catering', 400000.00, '3', 1, 5, 3, '2025-10-01', '2025-12-31'),
(6, 2025, 'Housekeeping Supplies 2025', 'Housekeeping supplies and cleaning materials', 200000.00, '1', 1, 6, 4, '2025-01-01', '2025-12-31'),
(7, 2025, 'Front Desk Operations 2025', 'Front desk operational expenses', 350000.00, '1', 1, 7, 5, '2025-01-01', '2025-12-31'),
(8, 2025, 'Food & Beverage Inventory 2025', 'Restaurant and bar inventory costs', 1200000.00, '1', 1, 8, 6, '2025-01-01', '2025-12-31'),
(9, 2025, 'Events & Banquets 2025', 'Banquet and event space management', 800000.00, '1', 1, 9, 7, '2025-01-01', '2025-12-31'),
(10, 2025, 'Maintenance & Repairs 2025', 'Facility maintenance and repair costs', 600000.00, '1', 1, 10, 8, '2025-01-01', '2025-12-31');

-- Insert Budget Items (Allocations)
INSERT INTO budget_items (budget_id, category_id, department_id, account_id, vendor_id, budgeted_amount, notes, created_at, updated_at) VALUES
-- FY 2025 Annual Budget allocations
(1, 1, 1, 101, NULL, 8000000.00, 'Annual payroll budget for all departments', NOW(), NOW()),
(1, 2, 1, 102, 4, 500000.00, 'Office supplies and materials', NOW(), NOW()),
(1, 3, 1, 103, 8, 600000.00, 'Utilities and facility maintenance', NOW(), NOW()),
(1, 4, 3, 104, 1, 1000000.00, 'Marketing and promotional activities', NOW(), NOW()),
(1, 5, 5, 105, NULL, 12000000.00, 'Expected room and suite revenue', NOW(), NOW()),
(1, 6, 8, 106, 6, 4000000.00, 'Dining and beverage revenue', NOW(), NOW()),
(1, 7, 9, 107, 7, 3000000.00, 'Events and catering revenue', NOW(), NOW()),
(1, 8, 1, 108, NULL, 400000.00, 'Administrative expenses', NOW(), NOW()),
(1, 9, 4, 109, 2, 750000.00, 'Technology and IT costs', NOW(), NOW()),
(1, 10, 2, 110, NULL, 300000.00, 'Training and development programs', NOW(), NOW()),

-- Q1 2025 Marketing Campaign allocations
(2, 4, 3, 104, 1, 300000.00, 'Digital marketing campaigns', NOW(), NOW()),
(2, 4, 3, 104, 1, 150000.00, 'Print and outdoor advertising', NOW(), NOW()),
(2, 8, 3, 108, NULL, 50000.00, 'Marketing administrative costs', NOW(), NOW()),

-- Q2 2025 Technology Upgrade allocations
(3, 9, 4, 109, 2, 500000.00, 'Server upgrades and new hardware', NOW(), NOW()),
(3, 9, 4, 109, 2, 200000.00, 'Software licenses and subscriptions', NOW(), NOW()),
(3, 8, 4, 108, NULL, 50000.00, 'IT administrative costs', NOW(), NOW()),

-- Q3 2025 Staff Training allocations
(4, 10, 2, 110, NULL, 200000.00, 'External training programs', NOW(), NOW()),
(4, 10, 2, 110, NULL, 80000.00, 'Training materials and resources', NOW(), NOW()),
(4, 8, 2, 108, NULL, 20000.00, 'Training administrative costs', NOW(), NOW()),

-- Q4 2025 Holiday Events allocations
(5, 7, 9, 107, 7, 250000.00, 'Holiday event planning and execution', NOW(), NOW()),
(5, 6, 8, 106, 6, 100000.00, 'Holiday catering and food services', NOW(), NOW()),
(5, 4, 9, 104, 7, 50000.00, 'Holiday decorations and promotions', NOW(), NOW()),

-- Department-specific budgets
(6, 2, 6, 102, 4, 200000.00, 'Housekeeping supplies budget', NOW(), NOW()),
(7, 8, 7, 108, 5, 350000.00, 'Front desk operational budget', NOW(), NOW()),
(8, 6, 8, 106, 6, 1200000.00, 'Food and beverage inventory budget', NOW(), NOW()),
(9, 7, 9, 107, 7, 800000.00, 'Events and banquets budget', NOW(), NOW()),
(10, 3, 10, 103, 8, 600000.00, 'Maintenance and repairs budget', NOW(), NOW());

-- Insert Budget Adjustments
-- Note: use `requested_by` to match schema (no `created_by` column)
INSERT INTO budget_adjustments (budget_id, adjustment_type, amount, department_id, vendor_id, reason, status, effective_date, requested_by, created_at, updated_at) VALUES
-- Approved adjustments
(1, 'increase', 500000.00, 1, NULL, 'Unexpected salary increases due to market adjustments', 'approved', '2025-03-01', 1, NOW(), NOW()),
(2, 'increase', 100000.00, 3, 1, 'Additional digital marketing spend for Q1 campaign', 'approved', '2025-02-15', 2, NOW(), NOW()),
(3, 'decrease', 50000.00, 4, 2, 'Software license costs reduced due to negotiation', 'approved', '2025-04-10', 3, NOW(), NOW()),
(4, 'transfer', 50000.00, 2, NULL, 'Transfer from training to administrative budget', 'approved', '2025-07-15', 4, NOW(), NOW()),
(5, 'increase', 75000.00, 9, 7, 'Additional holiday event expenses', 'approved', '2025-11-01', 5, NOW(), NOW()),

-- Pending adjustments
(1, 'increase', 200000.00, 8, 6, 'Increased food costs due to inflation', 'pending', '2025-06-01', 8, NOW(), NOW()),
(3, 'transfer', 100000.00, 4, NULL, 'Transfer from technology to maintenance budget', 'pending', '2025-05-15', 10, NOW(), NOW()),
(6, 'increase', 25000.00, 6, 4, 'Additional cleaning supplies needed', 'pending', '2025-08-01', 6, NOW(), NOW()),

-- Rejected adjustments
(2, 'increase', 75000.00, 3, 9, 'Additional advertising budget not justified', 'rejected', '2025-03-20', 2, NOW(), NOW()),
(7, 'decrease', 30000.00, 7, 5, 'Budget cut due to performance issues', 'rejected', '2025-09-01', 7, NOW(), NOW());

-- Insert Sample Tracking Data (Actual vs Budget)
-- Note: This would typically be populated by actual transactions
-- For demo purposes, we'll create some sample tracking records

-- Create a view or temporary table for tracking data simulation
-- This represents actual spending vs budgeted amounts by category and department

-- Sample tracking summary data (would normally be calculated from transactions)
-- We'll create this as insertable data for the tracking functionality

-- Insert some sample audit trail entries for budget management
INSERT INTO audit_logs (table_name, record_id, action, action_description, user_id, username, full_name, ip_address, created_at, new_values, old_values) VALUES
('budgets', 1, 'create', 'Created FY 2025 Annual Budget', 1, 'admin', 'System Administrator', '127.0.0.1', NOW(), '{"name":"FY 2025 Annual Budget","total_amount":15000000}', NULL),
('budgets', 1, 'update', 'Updated FY 2025 Annual Budget status to active', 1, 'admin', 'System Administrator', '127.0.0.1', NOW(), '{"status":"active"}', '{"status":"draft"}'),
('budget_items', 1, 'create', 'Created payroll allocation for FY 2025', 1, 'admin', 'System Administrator', '127.0.0.1', NOW(), '{"budget_id":1,"category_id":1,"budgeted_amount":8000000}', NULL),
('budget_adjustments', 1, 'create', 'Created payroll increase adjustment', 1, 'admin', 'System Administrator', '127.0.0.1', NOW(), '{"budget_id":1,"adjustment_type":"increase","amount":500000}', NULL),
('budget_adjustments', 1, 'update', 'Approved payroll increase adjustment', 1, 'admin', 'System Administrator', '127.0.0.1', NOW(), '{"status":"approved"}', '{"status":"pending"}'),
('budgets', 2, 'create', 'Created Q1 2025 Marketing Campaign budget', 2, 'marketing_manager', 'Marketing Manager', '127.0.0.1', NOW(), '{"name":"Q1 2025 Marketing Campaign","total_amount":500000}', NULL);

-- Create sample HR3 Claims data structure (if the integration exists)
-- This would be populated by the HR3 integration system
-- For demo purposes, we'll create a sample structure

-- Note: The actual HR3 claims data would come from the HR3 system integration
-- This is just to show the expected data structure for the budget management module

-- Sample department data (if not already in the system)
-- This would typically be in a separate departments table

-- Sample vendor data (if not already in the system)
-- This would typically be in a separate vendors table

-- Create sample tracking summary data for the dashboard cards
-- This represents the summary calculations shown in the tracking section

-- Note: The actual tracking data would be calculated from:
-- 1. Budget allocations (budget_items)
-- 2. Actual transactions (from various modules like AP, AR, payroll, etc.)
-- 3. HR3 claims data (from the HR3 integration)

-- For the demo, we'll create some sample calculated values that would appear in the tracking cards:
-- Total Budget: Sum of all active budget allocations
-- Actual Spent: Sum of actual transactions against those budgets
-- Variance: Difference between budgeted and actual
-- Remaining: Budget minus actual spent

-- These values would be calculated dynamically by the application
-- based on the current state of the system
-- Actual vs Budget: actuals table, sample actuals, summary view, alerts, evaluator, and adjustments

-- 1) Actuals table
CREATE TABLE IF NOT EXISTS budget_actuals (
	id INT AUTO_INCREMENT PRIMARY KEY,
	budget_id INT NOT NULL,
	category_id INT DEFAULT NULL,
	department_id INT DEFAULT NULL,
	account_id INT DEFAULT NULL,
	transaction_date DATE NOT NULL,
	amount DECIMAL(18,2) NOT NULL,
	description TEXT,
	created_at DATETIME DEFAULT NOW()
);

-- Sample actual transactions
INSERT INTO budget_actuals (budget_id, category_id, department_id, account_id, transaction_date, amount, description, created_at) VALUES
(1, 1, 1, 101, '2025-01-15', 1200000.00, 'January payroll payouts (partial)', NOW()),
(1, 2, 1, 102, '2025-01-20', 45000.00, 'Office supplies purchase', NOW()),
(1, 3, 1, 103, '2025-02-10', 80000.00, 'Utility bills Jan-Feb', NOW()),
(2, 4, 3, 104, '2025-01-25', 220000.00, 'Paid digital campaign vendor', NOW()),
(2, 4, 3, 104, '2025-02-10', 95000.00, 'Print advertising invoices', NOW()),
(3, 9, 4, 109, '2025-04-20', 300000.00, 'Servers procurement part 1', NOW()),
(3, 9, 4, 109, '2025-05-05', 420000.00, 'Software license bulk', NOW()),
(4, 10, 2, 110, '2025-07-15', 90000.00, 'Tchange the budgets INSERTs to include explicit id values to guarantee consistency.raining vendor fees', NOW()),
(5, 7, 9, 107, '2025-11-22', 275000.00, 'Holiday event deposit', NOW()),
(8, 6, 8, 106, '2025-03-03', 400000.00, 'Food & beverage inventory restock', NOW());

-- 2) Budget tracking summary view (correct variance and variance%)
-- Variance = actual - budgeted
-- Variance% = (actual - budgeted) / budgeted * 100  (safe when budgeted = 0 => 0%)
DROP VIEW IF EXISTS budget_tracking_summary;
CREATE VIEW budget_tracking_summary AS
SELECT
	b.id                            AS budget_id,
	b.budget_name,
	COALESCE(SUM(bi.budgeted_amount),0) AS total_budgeted,
	COALESCE(SUM(ba.amount),0)         AS actual_spent,
	(COALESCE(SUM(ba.amount),0) - COALESCE(SUM(bi.budgeted_amount),0)) AS variance,
	CASE WHEN COALESCE(SUM(bi.budgeted_amount),0) = 0 THEN 0
			 ELSE ROUND((COALESCE(SUM(ba.amount),0) - COALESCE(SUM(bi.budgeted_amount),0)) / COALESCE(SUM(bi.budgeted_amount),0) * 100,2)
	END AS variance_percent,
	(COALESCE(SUM(bi.budgeted_amount),0) - COALESCE(SUM(ba.amount),0)) AS remaining
FROM budgets b
LEFT JOIN budget_items bi ON bi.budget_id = b.id
LEFT JOIN budget_actuals ba ON ba.budget_id = b.id
GROUP BY b.id, b.budget_name;

-- 3) Budget alerts table and seeds
CREATE TABLE IF NOT EXISTS budget_alerts (
	id INT AUTO_INCREMENT PRIMARY KEY,
	budget_id INT NOT NULL,
	alert_type VARCHAR(50) NOT NULL,
	threshold_percent DECIMAL(5,2) NOT NULL,
	triggered TINYINT(1) DEFAULT 0,
	triggered_at DATETIME DEFAULT NULL,
	notes TEXT,
	created_at DATETIME DEFAULT NOW()
);

INSERT INTO budget_alerts (budget_id, alert_type, threshold_percent, triggered, triggered_at, notes, created_at) VALUES
(1, 'spend_threshold', 90.00, 0, NULL, 'Alert when FY budget reaches 90% spent', NOW()),
(2, 'spend_threshold', 75.00, 1, NOW(), 'Q1 marketing exceeded 75% spend (demo)', NOW()),
(3, 'spend_threshold', 80.00, 0, NULL, 'Q2 tech upgrades watchlist', NOW()),
(5, 'spend_threshold', 95.00, 0, NULL, 'Holiday events high-risk spend', NOW());

-- 4) Evaluator snippet to auto-trigger alerts (run periodically)
-- Marks alerts triggered where (actual / budgeted) * 100 >= threshold_percent and budgeted > 0
UPDATE budget_alerts a
JOIN (
	SELECT b.id AS budget_id,
				 COALESCE(SUM(bi.budgeted_amount),0) AS budgeted,
				 COALESCE(SUM(ba.amount),0) AS actual
	FROM budgets b
	LEFT JOIN budget_items bi ON bi.budget_id = b.id
	LEFT JOIN budget_actuals ba ON ba.budget_id = b.id
	GROUP BY b.id
) s ON s.budget_id = a.budget_id
SET a.triggered = 1,
		a.triggered_at = NOW(),
		a.notes = CONCAT('Auto-triggered: actual=', s.actual, ' budgeted=', s.budgeted)
WHERE s.budgeted > 0
	AND (s.actual / s.budgeted) * 100 >= a.threshold_percent
	AND a.triggered = 0;

-- 5) Helper view to inspect alerts and percent spent
DROP VIEW IF EXISTS budget_alerts_evaluation;
CREATE VIEW budget_alerts_evaluation AS
SELECT a.id AS alert_id,
			 a.budget_id,
			 b.budget_name,
			 a.alert_type,
			 a.threshold_percent,
			 s.budgeted,
			 s.actual,
			 CASE WHEN s.budgeted = 0 THEN 0 ELSE ROUND((s.actual / s.budgeted) * 100,2) END AS percent_spent,
			 a.triggered,
			 a.triggered_at,
			 a.notes
FROM budget_alerts a
LEFT JOIN budgets b ON b.id = a.budget_id
LEFT JOIN (
	SELECT b.id AS budget_id,
				 COALESCE(SUM(bi.budgeted_amount),0) AS budgeted,
				 COALESCE(SUM(ba.amount),0) AS actual
	FROM budgets b
	LEFT JOIN budget_items bi ON bi.budget_id = b.id
	LEFT JOIN budget_actuals ba ON ba.budget_id = b.id
	GROUP BY b.id
) s ON s.budget_id = a.budget_id;

-- 6) Additional realistic budget_adjustments seeds (demo)
INSERT INTO budget_adjustments (budget_id, adjustment_type, amount, department_id, vendor_id, reason, status, effective_date, requested_by, created_at, updated_at) VALUES
(1, 'increase', 300000.00, 1, NULL, 'Temporary overtime approvals', 'approved', '2025-04-01', 1, NOW(), NOW()),
(5, 'increase', 50000.00, 9, 7, 'Additional venue costs for holiday event', 'pending', '2025-10-15', 5, NOW(), NOW());