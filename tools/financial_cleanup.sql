-- Financial Cleanup & Audit (MySQL/MariaDB)
-- Run on a backup first. Use audit queries to review before applying optional fixes.

START TRANSACTION;

-- 1) Audit: Vendor “collections” stored in payments_made (should not be used)
SELECT
    pm.id,
    pm.payment_number,
    pm.vendor_id,
    pm.amount,
    pm.payment_date,
    pm.reference_number,
    pm.notes
FROM payments_made pm
WHERE (pm.reference_number LIKE 'COLL-%' OR pm.notes LIKE 'Collection:%');

-- 2) Audit: payments_received that are linked to vendor/bill (should be customer/invoice only)
SELECT
    pr.id,
    pr.payment_number,
    pr.vendor_id,
    pr.bill_id,
    pr.customer_id,
    pr.invoice_id,
    pr.amount,
    pr.payment_date,
    pr.reference_number,
    pr.notes
FROM payments_received pr
WHERE pr.vendor_id IS NOT NULL OR pr.bill_id IS NOT NULL;

-- 3) Audit: Disbursement journal entries with wrong polarity
--    Wrong pattern: Cash (1001) debited AND AP (2001) credited on DISB-* reference
SELECT
    je.id AS journal_entry_id,
    je.entry_number,
    je.entry_date,
    je.reference,
    je.description
FROM journal_entries je
JOIN journal_entry_lines jel_cash ON jel_cash.journal_entry_id = je.id
JOIN chart_of_accounts coa_cash ON coa_cash.id = jel_cash.account_id
JOIN journal_entry_lines jel_ap ON jel_ap.journal_entry_id = je.id
JOIN chart_of_accounts coa_ap ON coa_ap.id = jel_ap.account_id
WHERE je.reference LIKE 'DISB-%'
  AND coa_cash.account_code = '1001' AND jel_cash.debit > 0
  AND coa_ap.account_code = '2001' AND jel_ap.credit > 0;

-- 4) Audit: Adjustments with no JE
SELECT
    a.id AS adjustment_id,
    a.adjustment_number,
    a.adjustment_type,
    a.amount,
    a.adjustment_date
FROM adjustments a
LEFT JOIN journal_entries je ON je.reference = CONCAT('ADJ-', a.id)
WHERE je.id IS NULL;

-- 5) Audit: Invoices with balance < 0 (over-applied)
SELECT
    i.id,
    i.invoice_number,
    i.customer_id,
    i.balance
FROM invoices i
WHERE i.balance < 0;

-- 6) Audit: Bills with balance < 0 (over-applied)
SELECT
    b.id,
    b.bill_number,
    b.vendor_id,
    b.balance
FROM bills b
WHERE b.balance < 0;

-- =========================
-- OPTIONAL FIXES (COMMENTED)
-- Review audit results before enabling.
-- =========================

-- A) Archive vendor “collections” from payments_made, then delete
-- CREATE TABLE IF NOT EXISTS payments_made_legacy_collections AS
-- SELECT * FROM payments_made WHERE 1=0;
-- INSERT INTO payments_made_legacy_collections
-- SELECT * FROM payments_made
-- WHERE (reference_number LIKE 'COLL-%' OR notes LIKE 'Collection:%');
-- DELETE FROM payments_made
-- WHERE (reference_number LIKE 'COLL-%' OR notes LIKE 'Collection:%');

-- B) Flip wrong disbursement JE polarity (cash/AP) for DISB-* entries
-- NOTE: This assumes each affected JE has exactly one cash line and one AP line.
-- UPDATE journal_entry_lines jel
-- JOIN journal_entries je ON je.id = jel.journal_entry_id
-- JOIN chart_of_accounts coa ON coa.id = jel.account_id
-- SET
--   jel.debit = CASE WHEN coa.account_code IN ('1001','2001') THEN jel.credit ELSE jel.debit END,
--   jel.credit = CASE WHEN coa.account_code IN ('1001','2001') THEN jel.debit ELSE jel.credit END
-- WHERE je.reference LIKE 'DISB-%'
--   AND coa.account_code IN ('1001','2001');

-- C) Recalculate journal entry totals for DISB-* after flipping
-- UPDATE journal_entries je
-- JOIN (
--   SELECT journal_entry_id,
--          SUM(debit) AS total_debit,
--          SUM(credit) AS total_credit
--   FROM journal_entry_lines
--   GROUP BY journal_entry_id
-- ) t ON t.journal_entry_id = je.id
-- SET je.total_debit = t.total_debit,
--     je.total_credit = t.total_credit
-- WHERE je.reference LIKE 'DISB-%';

-- D) Manually re-post missing adjustment JEs
-- Best done via the app/API by editing/saving each adjustment,
-- or create entries manually using your accounting rules.

ROLLBACK;
-- Replace ROLLBACK with COMMIT after reviewing audit output and enabling fixes.
