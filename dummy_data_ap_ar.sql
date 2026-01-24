-- ============================================================================
-- ATIERA HOTEL & RESTAURANT - DETAILED AP & AR DEMO DATA
-- File: dummy_data_ap_ar.sql
-- Purpose: Standalone dataset covering Accounts Receivable and Accounts Payable
-- Includes: customers, vendors, invoices, invoice_items, payments_received,
--           bills, bill_items, payments_made, journal_entries and lines
-- Safe to re-run: cleanup blocks target entries created by this script
-- ============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET AUTOCOMMIT=0;
START TRANSACTION;

-- ============================================================================
-- CLEANUP: remove previous demo AP/AR data from earlier runs of this script
-- Patterns used: EXT markers to avoid touching core demo data
-- ============================================================================
DELETE FROM `journal_entry_lines` WHERE journal_entry_id IN (SELECT id FROM journal_entries WHERE entry_number LIKE 'JE-2025-EXT-%');
DELETE FROM `journal_entries` WHERE entry_number LIKE 'JE-2025-EXT-%';
DELETE FROM `payments_made` WHERE payment_number LIKE 'PAY-PAID-2025-EXT-%';
DELETE FROM `payments_received` WHERE payment_number LIKE 'PAY-RCV-2025-EXT-%';
DELETE FROM `invoice_items` WHERE invoice_id BETWEEN 200 AND 220;
DELETE FROM `invoices` WHERE invoice_number LIKE 'INV-2025-EXT-%' AND id BETWEEN 200 AND 220;
DELETE FROM `bill_items` WHERE bill_id BETWEEN 200 AND 220;
DELETE FROM `bills` WHERE bill_number LIKE 'BILL-25-EXT-%' AND id BETWEEN 200 AND 220;
DELETE FROM `customers` WHERE id BETWEEN 200 AND 220;
DELETE FROM `vendors` WHERE id BETWEEN 200 AND 220;

-- ============================================================================
-- CUSTOMERS (Accounts Receivable)
-- ============================================================================
INSERT INTO `customers` (`id`, `customer_code`, `company_name`, `contact_person`, `email`, `phone`, `address`, `credit_limit`, `current_balance`, `status`, `created_at`, `updated_at`) VALUES
(200, 'CORP-101', 'ACME Logistics, Inc.', 'John Reyes', 'john.reyes@acmelog.com', '555-2101', 'Pasig City, Metro Manila', 250000.00, 0.00, 'active', NOW(), NOW()),
(201, 'CORP-102', 'Metro Builders Co.', 'Anna Lim', 'anna.lim@metrobuilders.ph', '555-2102', 'Mandaluyong City, Metro Manila', 180000.00, 0.00, 'active', NOW(), NOW()),
(202, 'CORP-103', 'Sunrise Events Ltd.', 'Carlos Santos', 'c.santos@sunriseevents.ph', '555-2103', 'Makati City, Metro Manila', 150000.00, 0.00, 'active', NOW(), NOW()),
(203, 'CORP-104', 'Island Tours & Travel', 'Maria Lopez', 'm.lopez@islandtours.ph', '555-2104', 'Quezon City, Metro Manila', 120000.00, 0.00, 'active', NOW(), NOW());

-- ============================================================================
-- VENDORS (Accounts Payable)
-- ============================================================================
INSERT INTO `vendors` (`id`, `vendor_code`, `company_name`, `contact_person`, `email`, `phone`, `address`, `payment_terms`, `status`, `created_at`, `updated_at`) VALUES
(200, 'VEND-EXT-001', 'Ocean Fresh Seafood', 'Ruben Diaz', 'ruben@oceanfresh.ph', '555-2201', 'Navotas, Metro Manila', 'Net 15', 'active', NOW(), NOW()),
(201, 'VEND-EXT-002', 'Greenfield Produce', 'Lorraine Cruz', 'lorraine@greenfield.ph', '555-2202', 'Taguig City, Metro Manila', 'Net 30', 'active', NOW(), NOW()),
(202, 'VEND-EXT-003', 'CleanPro Laundry Services', 'Joseph Mendoza', 'joseph@cleanpro.ph', '555-2203', 'San Juan, Metro Manila', 'Net 15', 'active', NOW(), NOW()),
(203, 'VEND-EXT-004', 'Star Electric Co.', 'Francisco Reyes', 'francisco@starelectric.ph', '555-2204', 'Pasig City, Metro Manila', 'Due on Receipt', 'active', NOW(), NOW());

-- ============================================================================
-- INVOICES (AR) - realistic invoices that can be processed
-- IDs start at 200, invoice_number uses EXT tag to avoid collision
-- ============================================================================
INSERT INTO `invoices` (`id`, `invoice_number`, `customer_id`, `invoice_date`, `due_date`, `subtotal`, `tax_rate`, `tax_code_id`, `tax_amount`, `total_amount`, `paid_amount`, `balance`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(200, 'INV-2025-EXT-001', 200, '2025-06-15', '2025-07-15', 50000.00, 12.00, 1, 6000.00, 56000.00, 56000.00, 0.00, 'paid', 'Corporate Accommodation - ACME Logistics (June)', 1, NOW(), NOW()),
(201, 'INV-2025-EXT-002', 201, '2025-07-01', '2025-07-31', 75000.00, 12.00, 1, 9000.00, 84000.00, 42000.00, 42000.00, 'sent', 'Conference Venue & Catering - Metro Builders (partial payment received)', 1, NOW(), NOW()),
(202, 'INV-2025-EXT-003', 202, '2025-08-05', '2025-09-04', 32000.00, 12.00, 1, 3840.00, 35840.00, 0.00, 35840.00, 'sent', 'Banquet Services - Sunrise Events', 1, NOW(), NOW()),
(203, 'INV-2025-EXT-004', 203, '2025-09-10', '2025-10-10', 18000.00, 12.00, 1, 2160.00, 20160.00, 0.00, 20160.00, 'sent', 'Tour Package - Island Tours (group)', 1, NOW(), NOW());

-- INVOICE ITEMS
INSERT INTO `invoice_items` (`invoice_id`, `description`, `quantity`, `unit_price`, `line_total`, `account_id`, `created_at`) VALUES
(200, 'Triple Deluxe Rooms (5 nights, 5 rooms)', 25, 2000.00, 50000.00, 5, NOW()),
(201, 'Conference Hall Rental (2 days)', 1, 50000.00, 50000.00, 5, NOW()),
(201, 'Catering - Lunch & Coffee Breaks', 250, 100.00, 25000.00, 5, NOW()),
(202, 'Banquet Dinner (120 pax)', 120, 200.00, 24000.00, 5, NOW()),
(202, 'AV & Stage Setup', 1, 8000.00, 8000.00, 5, NOW()),
(203, 'Island Tour Package (per pax)', 60, 300.00, 18000.00, 5, NOW());

-- PAYMENTS RECEIVED (AR) - demonstrates full, partial, and pending payments
INSERT INTO `payments_received` (`id`, `payment_number`, `invoice_id`, `customer_id`, `payment_date`, `amount_paid`, `payment_method`, `reference_no`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(200, 'PAY-RCV-2025-EXT-001', 200, 200, '2025-06-20', 56000.00, 'bank_transfer', 'ACME-TRF-0620', 'Full payment for INV-2025-EXT-001', 1, NOW(), NOW()),
(201, 'PAY-RCV-2025-EXT-002', 201, 201, '2025-07-15', 42000.00, 'credit_card', 'MB-CC-0715', 'Partial payment for INV-2025-EXT-002', 1, NOW(), NOW());

-- ============================================================================
-- BILLS (AP) - realistic vendor invoices
-- ============================================================================
INSERT INTO `bills` (`id`, `bill_number`, `vendor_id`, `bill_date`, `due_date`, `subtotal`, `tax_rate`, `tax_code_id`, `tax_amount`, `total_amount`, `paid_amount`, `balance`, `status`, `notes`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(200, 'BILL-25-EXT-001', 200, '2025-06-10', '2025-06-25', 90000.00, 12.00, 1, 10800.00, 100800.00, 100800.00, 0.00, 'paid', 'Fresh seafood deliveries for banquet (Ocean Fresh)', 1, 2, NOW(), NOW()),
(201, 'BILL-25-EXT-002', 201, '2025-07-02', '2025-08-01', 45000.00, 12.00, 1, 5400.00, 50400.00, 0.00, 50400.00, 'approved', 'Monthly produce supply (Greenfield) (pending payment)', 1, 2, NOW(), NOW()),
(202, 'BILL-25-EXT-003', 202, '2025-07-20', '2025-08-04', 20000.00, 12.00, 1, 2400.00, 22400.00, 22400.00, 0.00, 'paid', 'Laundry services for linen (CleanPro)', 1, 2, NOW(), NOW()),
(203, 'BILL-25-EXT-004', 203, '2025-08-01', '2025-08-01', 120000.00, 0.00, NULL, 0.00, 120000.00, 0.00, 120000.00, 'overdue', 'Emergency electrical repair (Star Electric)', 1, 2, NOW(), NOW());

-- BILL ITEMS
INSERT INTO `bill_items` (`bill_id`, `description`, `quantity`, `unit_price`, `line_total`, `account_id`, `created_at`) VALUES
(200, 'Whole Fresh Tuna & Assorted Seafood', 1, 90000.00, 90000.00, 51, NOW()),
(201, 'Produce - Vegetables & Fruits', 1, 45000.00, 45000.00, 51, NOW()),
(202, 'Linen Washing & Pressing (July)', 1, 20000.00, 20000.00, 75, NOW()),
(203, 'Emergency Generator Repair & Parts', 1, 120000.00, 120000.00, 108, NOW());

-- PAYMENTS MADE (AP)
INSERT INTO `payments_made` (`id`, `payment_number`, `bill_id`, `vendor_id`, `payment_date`, `amount_paid`, `payment_method`, `reference_no`, `notes`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(200, 'PAY-PAID-2025-EXT01', 200, 200, '2025-06-20', 100800.00, 'bank_transfer', 'CHK-OCEAN-0620', 'Payment for BILL-25-EXT-001', 1, 2, NOW(), NOW()),
(201, 'PAY-PAID-2025-EXT02', 202, 202, '2025-07-25', 22400.00, 'bank_transfer', 'CHK-CLEANPRO-0725', 'Payment for BILL-25-EXT-003', 1, 2, NOW(), NOW());

-- ============================================================================
-- JOURNAL ENTRIES (AP & AR related) - posted entries to reflect AR/AP activity
-- IDs start at 200 to avoid collision with existing entries
-- Each entry is balanced: debits == credits
-- ============================================================================
INSERT INTO `journal_entries` (`id`, `entry_number`, `entry_date`, `description`, `reference`, `total_debit`, `total_credit`, `status`, `created_by`, `posted_by`, `created_at`, `posted_at`) VALUES
(200, 'JE-2025-EXT-AR-01', '2025-06-15', 'Invoice AR - ACME Logistics', 'INV-2025-EXT-001', 56000.00, 56000.00, 'posted', 1, 2, NOW(), NOW()),
(201, 'JE-2025-EXT-AR-02', '2025-07-01', 'Invoice AR - Metro Builders (partial)', 'INV-2025-EXT-002', 84000.00, 84000.00, 'posted', 1, 2, NOW(), NOW()),
(202, 'JE-2025-EXT-AR-03', '2025-08-05', 'Invoice AR - Sunrise Events', 'INV-2025-EXT-003', 35840.00, 35840.00, 'posted', 1, 2, NOW(), NOW()),
(203, 'JE-2025-EXT-AP-01', '2025-06-10', 'Bill AP - Ocean Fresh Seafood', 'BILL-25-EXT-001', 100800.00, 100800.00, 'posted', 1, 2, NOW(), NOW()),
(204, 'JE-2025-EXT-AP-02', '2025-07-02', 'Bill AP - Greenfield Produce', 'BILL-25-EXT-002', 50400.00, 50400.00, 'posted', 1, 2, NOW(), NOW()),
(205, 'JE-2025-EXT-PAY-AR', '2025-06-20', 'Payment Received - ACME (INV-2025-EXT-001)', 'PAY-RCV-2025-EXT-001', 56000.00, 56000.00, 'posted', 1, 2, NOW(), NOW()),
(206, 'JE-2025-EXT-PAY-AP', '2025-06-20', 'Payment Made - Ocean Fresh (BILL-25-EXT-001)', 'PAY-PAID-2025-EXT01', 100800.00, 100800.00, 'posted', 1, 2, NOW(), NOW());

-- JOURNAL ENTRY LINES
INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `description`, `created_at`) VALUES
-- JE-2025-EXT-AR-01 (Invoice recorded): Debit AR, Credit Revenue
(200, 2, 56000.00, 0.00, 'Accounts Receivable - INV-2025-EXT-001', NOW()),
(200, 5, 0.00, 56000.00, 'Room & Services Revenue - INV-2025-EXT-001', NOW()),
-- JE-2025-EXT-AR-02 (Invoice recorded for Metro Builders)
(201, 2, 84000.00, 0.00, 'Accounts Receivable - INV-2025-EXT-002', NOW()),
(201, 5, 0.00, 84000.00, 'Conference Revenue - INV-2025-EXT-002', NOW()),
-- JE-2025-EXT-AR-03 (Invoice recorded for Sunrise Events)
(202, 2, 35840.00, 0.00, 'Accounts Receivable - INV-2025-EXT-003', NOW()),
(202, 5, 0.00, 35840.00, 'Banquet Revenue - INV-2025-EXT-003', NOW()),
-- JE-2025-EXT-AP-01 (Bill recorded): Debit Expense, Credit AP
(203, 51, 100800.00, 0.00, 'F&B Purchases - BILL-25-EXT-001', NOW()),
(203, 3, 0.00, 100800.00, 'Accounts Payable - Ocean Fresh', NOW()),
-- JE-2025-EXT-AP-02 (Bill recorded Greenfield)
(204, 51, 50400.00, 0.00, 'Produce Purchases - BILL-25-EXT-002', NOW()),
(204, 3, 0.00, 50400.00, 'Accounts Payable - Greenfield', NOW()),
-- Payment Received JE: Cash (1) debit, AR (2) credit
(205, 1, 56000.00, 0.00, 'Bank Deposit - PAY-RCV-2025-EXT-001', NOW()),
(205, 2, 0.00, 56000.00, 'Clear AR - INV-2025-EXT-001', NOW()),
-- Payment Made JE: AP (3) debit, Cash (1) credit
(206, 3, 100800.00, 0.00, 'Clear AP - BILL-25-EXT-001', NOW()),
(206, 1, 0.00, 100800.00, 'Bank Transfer - PAY-PAID-2025-EXT-001', NOW());

-- ============================================================================
-- ADDITIONAL BILLS FOR AGING REPORT (Current / 1-30 / 31-60 / 61-90 / 90+)
-- IDs chosen within 200-220 range so cleanup block removes them on rerun
-- ============================================================================
-- Bills seeded to fall into different aging buckets as of 2026-01-24
INSERT INTO `bills` (`id`, `bill_number`, `vendor_id`, `bill_date`, `due_date`, `subtotal`, `tax_rate`, `tax_code_id`, `tax_amount`, `total_amount`, `paid_amount`, `balance`, `status`, `notes`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(204, 'BILL-25-EXT-005', 200, '2026-01-15', '2026-02-10', 25000.00, 12.00, 1, 3000.00, 28000.00, 0.00, 28000.00, 'approved', 'Produce replenishment - due in future (Current)', 1, 2, NOW(), NOW()),
(205, 'BILL-25-EXT-006', 201, '2025-12-25', '2026-01-20', 50000.00, 12.00, 1, 6000.00, 56000.00, 10000.00, 46000.00, 'approved', 'Partial payment made - falls in 1-30 days bucket', 1, 2, NOW(), NOW()),
(206, 'BILL-25-EXT-007', 202, '2025-11-05', '2025-12-15', 40000.00, 12.00, 1, 4800.00, 44800.00, 0.00, 44800.00, 'approved', 'No payment - falls in 31-60 days bucket', 1, 2, NOW(), NOW()),
(207, 'BILL-25-EXT-008', 203, '2025-09-15', '2025-11-10', 30000.00, 12.00, 1, 3600.00, 33600.00, 0.00, 33600.00, 'overdue', 'Late emergency repair - falls in 61-90 days bucket', 1, 2, NOW(), NOW()),
(208, 'BILL-25-EXT-009', 200, '2025-07-01', '2025-09-15', 15000.00, 0.00, NULL, 0.00, 15000.00, 0.00, 15000.00, 'overdue', 'Very old payable - falls in 90+ days bucket', 1, 2, NOW(), NOW());

-- Bill items for aging bills
INSERT INTO `bill_items` (`bill_id`, `description`, `quantity`, `unit_price`, `line_total`, `account_id`, `created_at`) VALUES
(204, 'Produce - Short Shelf', 1, 25000.00, 25000.00, 51, NOW()),
(205, 'Monthly Supply - Partial Paid', 1, 50000.00, 50000.00, 51, NOW()),
(206, 'Laundry Services - Older', 1, 40000.00, 40000.00, 75, NOW()),
(207, 'Repair Services - Past Due', 1, 30000.00, 30000.00, 108, NOW()),
(208, 'Small Equipment - Long Past Due', 1, 15000.00, 15000.00, 108, NOW());

-- Partial payment recorded for bill 205 (so balance remains)
INSERT INTO `payments_made` (`id`, `payment_number`, `bill_id`, `vendor_id`, `payment_date`, `amount_paid`, `payment_method`, `reference_no`, `notes`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(202, 'PAY-EXT-205-01', 205, 201, '2026-01-05', 10000.00, 'bank_transfer', 'TRF-GF-0105', 'Partial payment for BILL-25-EXT-006', 1, 2, NOW(), NOW());

-- Corresponding journal entries for the new bills and payment
INSERT INTO `journal_entries` (`id`, `entry_number`, `entry_date`, `description`, `reference`, `total_debit`, `total_credit`, `status`, `created_by`, `posted_by`, `created_at`, `posted_at`) VALUES
(207, 'JE-2025-EXT-AP-03', '2026-01-15', 'Bill AP - Produce (Current)', 'BILL-25-EXT-005', 28000.00, 28000.00, 'posted', 1, 2, NOW(), NOW()),
(208, 'JE-2025-EXT-AP-04', '2025-12-25', 'Bill AP - Greenfield (1-30)', 'BILL-25-EXT-006', 56000.00, 56000.00, 'posted', 1, 2, NOW(), NOW()),
(209, 'JE-2025-EXT-AP-05', '2025-11-05', 'Bill AP - CleanPro (31-60)', 'BILL-25-EXT-007', 44800.00, 44800.00, 'posted', 1, 2, NOW(), NOW()),
(210, 'JE-2025-EXT-AP-06', '2025-09-15', 'Bill AP - Star Electric (61-90)', 'BILL-25-EXT-008', 33600.00, 33600.00, 'posted', 1, 2, NOW(), NOW()),
(211, 'JE-2025-EXT-AP-07', '2025-07-01', 'Bill AP - Old Equipment (90+)', 'BILL-25-EXT-009', 15000.00, 15000.00, 'posted', 1, 2, NOW(), NOW()),
(212, 'JE-2025-EXT-PAY-AP-2', '2026-01-05', 'Partial Payment - Greenfield (BILL-25-EXT-006)', 'PAY-EXT-205-01', 10000.00, 10000.00, 'posted', 1, 2, NOW(), NOW());

-- Journal entry lines for bills (debit expense, credit AP)
INSERT INTO `journal_entry_lines` (`journal_entry_id`, `account_id`, `debit`, `credit`, `description`, `created_at`) VALUES
-- JE-2025-EXT-AP-03 (204)
(207, 51, 28000.00, 0.00, 'Produce Purchases - BILL-25-EXT-005', NOW()),
(207, 3, 0.00, 28000.00, 'Accounts Payable - BILL-25-EXT-005', NOW()),
-- JE-2025-EXT-AP-04 (205)
(208, 51, 56000.00, 0.00, 'Produce Purchases - BILL-25-EXT-006', NOW()),
(208, 3, 0.00, 56000.00, 'Accounts Payable - BILL-25-EXT-006', NOW()),
-- JE-2025-EXT-AP-05 (206)
(209, 75, 44800.00, 0.00, 'Laundry Services - BILL-25-EXT-007', NOW()),
(209, 3, 0.00, 44800.00, 'Accounts Payable - BILL-25-EXT-007', NOW()),
-- JE-2025-EXT-AP-06 (207)
(210, 108, 33600.00, 0.00, 'Repairs - BILL-25-EXT-008', NOW()),
(210, 3, 0.00, 33600.00, 'Accounts Payable - BILL-25-EXT-008', NOW()),
-- JE-2025-EXT-AP-07 (208)
(211, 108, 15000.00, 0.00, 'Equipment - BILL-25-EXT-009', NOW()),
(211, 3, 0.00, 15000.00, 'Accounts Payable - BILL-25-EXT-009', NOW()),
-- JE-2025-EXT-PAY-AP-02 (payment for 205)
(212, 3, 10000.00, 0.00, 'Clear AP - BILL-25-EXT-006 (Partial)', NOW()),
(212, 1, 0.00, 10000.00, 'Bank Transfer - PAY-EXT-205-01', NOW());
-- ============================================================================
-- COMMIT
-- ============================================================================
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

-- ============================================================================
-- VERIFICATION QUERIES (Run after import to verify AP/AR data)
-- (Run these one-by-one in your DB client)
-- ============================================================================
-- SELECT * FROM customers WHERE id BETWEEN 200 AND 203;
-- SELECT * FROM vendors WHERE id BETWEEN 200 AND 203;
-- SELECT * FROM invoices WHERE invoice_number LIKE 'INV-2025-EXT-%';
-- SELECT * FROM invoice_items WHERE invoice_id BETWEEN 200 AND 210;
-- SELECT * FROM payments_received WHERE payment_number LIKE 'PAY-RCV-2025-EXT-%';
-- SELECT * FROM bills WHERE bill_number LIKE 'BILL-25-EXT-%';
-- SELECT * FROM bill_items WHERE bill_id BETWEEN 200 AND 210;
-- SELECT * FROM payments_made WHERE payment_number LIKE 'PAY-PAID-2025-EXT-%';
-- SELECT je.entry_number, jel.account_id, jel.debit, jel.credit FROM journal_entries je JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id WHERE je.entry_number LIKE 'JE-2025-EXT-%' ORDER BY je.entry_date;

-- End of file
