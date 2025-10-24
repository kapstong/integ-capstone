# ATIERA Financial Management System - Database Installation Guide

## Quick Installation

### Option 1: phpMyAdmin (Recommended)
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click the **Import** tab
3. Click **Choose File** and select `atiera_finance_master.sql`
4. Click **Go** button at the bottom
5. Wait for completion message (should take 5-10 seconds)

### Option 2: Command Line
```bash
mysql -u root -p < atiera_finance_master.sql
```

## What's Included

The master SQL file (`atiera_finance_master.sql`) contains everything in one file:

### Section 1: Core Database Schema (Lines 1-800)
- Users and authentication system
- Chart of accounts (basic + USALI format)
- Customers and vendors
- Invoices and bills (AR/AP)
- Payments and adjustments
- Journal entries
- Budgets
- Roles and permissions (RBAC)
- Audit logs and system tables

### Section 2: USALI Chart of Accounts (Lines 801-1100)
- 150+ hotel & restaurant specific accounts
- Revenue accounts (4000-4399)
  - Rooms Division (4001-4099)
  - Food & Beverage (4100-4199)
  - Other Operations (4200-4299)
  - Miscellaneous (4300-4399)
- Expense accounts (5100-5699)
  - Rooms expenses (5100-5199)
  - F&B expenses (5200-5299)
  - Administrative (5400-5449)
  - Sales & Marketing (5450-5499)
  - Property O&M (5500-5549)
  - Utilities (5550-5599)
  - Fixed charges (5600-5699)
- Asset accounts (1100-1699)
- Liability accounts (2100-2199)

### Section 3: Financial Modules (Lines 1101-end)
- **Departments**: Cost centers and revenue centers
- **Revenue Centers**: Detailed revenue tracking
- **Cashier/Collection Module**: Daily cash operations
- **Integration Framework**: Connect to external systems
  - Hotel PMS (Core 1)
  - Restaurant POS (Core 2)
  - Logistics System
  - HR System
- **Summary Tables**: Pre-calculated daily/monthly reports
- **Budget Tracking**: Department budgets
- **Report Configurations**: Saved USALI reports

## Default Credentials

After installation, login with:

- **Username:** `admin`
- **Password:** `admin123`

**Staff account** (for testing):
- **Username:** `staff`
- **Password:** `staff123`

## Verification

After import, verify the installation:

1. Check database created:
   ```sql
   SHOW DATABASES LIKE 'atiera_finance';
   ```

2. Check tables created (should be 55+ tables):
   ```sql
   USE atiera_finance;
   SHOW TABLES;
   ```

3. Check users created:
   ```sql
   SELECT username, role FROM users;
   ```

4. Check chart of accounts (should be 150+ accounts):
   ```sql
   SELECT COUNT(*) FROM chart_of_accounts;
   ```

5. Check departments created:
   ```sql
   SELECT dept_code, dept_name, dept_type FROM departments;
   ```

## Files Merged and Deleted

The following files were merged into `atiera_finance_master.sql` and removed:

✅ **Merged:**
- `database_schema.sql` → Core schema
- `hotel_restaurant_accounts.sql` → USALI accounts
- `financials_extension_schema.sql` → Financial modules

❌ **Removed (redundant/deprecated):**
- `alter_payments_table.sql` → Redundant (columns already in core schema)
- `hotel_restaurant_schema.sql` → Deprecated (operational modules out of scope)

## Troubleshooting

### Import fails with duplicate entry error
**Fixed in latest version!** If you downloaded an older version, get the latest `atiera_finance_master.sql` file which has all duplicates resolved.

### Import fails with "table already exists"
Drop the database first:
```sql
DROP DATABASE IF EXISTS atiera_finance;
```
Then re-import the master SQL file.

### Cannot login after installation
Clear your browser cookies/cache or use incognito mode.

### Database size
The database will be approximately:
- Empty: ~5 MB (structure only)
- With sample data: ~10-20 MB
- After 1 year of operations: ~50-100 MB

## Next Steps

After successful installation:

1. ✅ Login to the system at http://localhost/integ-capstone/
2. ✅ Navigate to **Financials → Departments**
3. ✅ Test the Department Management module (already working)
4. Configure integration mappings for external systems
5. Set up user roles and permissions
6. Review and customize USALI reports

## Documentation

For more information, see:
- `README_FINANCIALS.md` - System overview
- `FINANCIALS_SCOPE.md` - What's included/excluded
- `INTEGRATION_GUIDE.md` - How to integrate external systems
- `SYSTEM_READY.md` - Current development status

---

**Database Version:** 1.0.0
**Last Updated:** October 24, 2025
**Compatible with:** MySQL 5.7+, MariaDB 10.3+
