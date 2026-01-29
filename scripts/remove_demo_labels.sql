-- Remove "(Demo)" suffixes from master data labels
UPDATE chart_of_accounts
SET account_name = TRIM(REPLACE(account_name, ' (Demo)', ''))
WHERE account_name LIKE '%(Demo)%';

UPDATE departments
SET department_name = TRIM(REPLACE(department_name, ' (Demo)', ''))
WHERE department_name LIKE '%(Demo)%';

UPDATE vendors
SET company_name = TRIM(REPLACE(company_name, ' (Demo)', ''))
WHERE company_name LIKE '%(Demo)%';

UPDATE customers
SET company_name = TRIM(REPLACE(company_name, ' (Demo)', ''))
WHERE company_name LIKE '%(Demo)%';

