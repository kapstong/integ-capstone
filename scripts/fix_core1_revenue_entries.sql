-- Reclassify Core 1 payment journal entries:
-- Debit Cash/Bank remains; Credit Accounts Receivable -> Revenue

UPDATE journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN chart_of_accounts coa_ar ON jel.account_id = coa_ar.id
JOIN chart_of_accounts coa_rev ON coa_rev.account_code = '4001'
SET jel.account_id = coa_rev.id
WHERE je.description LIKE 'Core 1 payment%'
  AND jel.credit > 0
  AND coa_ar.account_code = '1002';

