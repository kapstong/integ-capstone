# ATIERA FINANCIALS System
## Hotel & Restaurant Financial Management (Revised Scope)

---

## 🎯 Quick Start

```bash
# Install financial modules
php setup_financials_extension.php

# Login
URL: http://localhost/integ-capstone/
Username: admin
Password: admin123
```

---

## 📋 What This System Does

**FINANCIALS System = Central Financial Hub**

✅ Receives transaction data from:
- Hotel Core 1 (room sales, charges)
- Restaurant Core 2 (POS sales)
- Logistics 1 (purchases, expenses)
- HR Systems (payroll, benefits)

✅ Provides:
- General Ledger
- Accounts Payable / Receivable
- Budget Management
- Collection (cashier sessions)
- Financial Reporting (USALI format)
- Department P&L tracking

❌ Does NOT do:
- Room operations (belongs to Hotel Core 1)
- Menu/POS operations (belongs to Restaurant Core 2)
- Inventory management (belongs to Logistics 1)
- HR/Payroll processing (belongs to HR Systems)

---

## 📁 File Guide

### ✅ USE THESE FILES

**Installation:**
- `setup_financials_extension.php` - Run this to install
- `financials_extension_schema.sql` - Financial tables (14 tables)
- `hotel_restaurant_accounts.sql` - USALI chart of accounts

**Documentation:**
- `FINANCIALS_SCOPE.md` - What's included
- `INTEGRATION_GUIDE.md` - How systems integrate
- `README_FINANCIALS.md` - This file

**System Files:**
- `responsive.css` - UI styles
- `config.php` - Configuration
- All files in `includes/`, `admin/`, `api/`

### ❌ IGNORE THESE FILES (Out of Scope)

- ~~`hotel_restaurant_schema.sql`~~ - Contains operational modules
- ~~`setup_hotel_restaurant.php`~~ - Installs operational modules
- ~~`HOTEL_RESTAURANT_FEATURES.md`~~ - Documents operational features
- See `DEPRECATED_FILES.md` for complete list

---

## 🗄️ Database Structure

### Financial Tables (14 tables)

**Core Financial:**
1. `departments` - Financial cost/revenue centers
2. `revenue_centers` - Revenue tracking points
3. `department_budgets` - Annual budgets

**Collection:**
4. `cashier_sessions` - Daily cash collection
5. `cashier_transactions` - Collection details

**Integration:**
6. `system_integrations` - Connected systems
7. `integration_mappings` - Code → GL mappings
8. `imported_transactions` - Transaction queue
9. `integration_sync_logs` - Sync history

**Summaries:**
10. `daily_revenue_summary` - Daily revenue
11. `daily_expense_summary` - Daily expenses
12. `monthly_department_performance` - Monthly P&L

**Reporting:**
13. `saved_financial_reports` - Report templates

Plus: `chart_of_accounts`, `journal_entries`, `invoices`, `bills`, `customers`, `vendors`, `payments` (from core system)

---

## 🔌 Integration Flow

```
┌──────────────┐
│ Hotel Core 1 │──┐
└──────────────┘  │
                  │
┌──────────────┐  │     ┌────────────┐
│Restaurant C2 │──┼────→│ FINANCIALS │
└──────────────┘  │     └────────────┘
                  │           │
┌──────────────┐  │           ↓
│ Logistics 1  │──┤    ┌─────────────┐
└──────────────┘  │    │ GL, Reports │
                  │    └─────────────┘
┌──────────────┐  │
│ HR Systems   │──┘
└──────────────┘
```

**How it works:**
1. Operational systems send transactions
2. FINANCIALS imports and validates
3. FINANCIALS posts to General Ledger
4. FINANCIALS generates consolidated reports
5. All systems query FINANCIALS for financial data

---

## 📊 Key Features

### 1. Department Financial Tracking
- Revenue centers: Rooms, F&B, Spa, Events
- Cost centers: Admin, Maintenance, Marketing
- Department-level P&L reports

### 2. Collection Module
- Cashier session management
- Multi-shift support
- Cash reconciliation
- Variance tracking

### 3. Integration Framework
- Receive data from 4+ systems
- Auto-map to GL accounts
- Transaction validation
- Posting automation

### 4. USALI Reporting
- Income Statement (P&L)
- Department P&L
- Budget vs Actual
- Cash Flow
- Balance Sheet

### 5. Budget Management
- Annual budgets by department
- Monthly allocation
- Variance analysis

---

## 🔐 Permissions

**Financial Permissions (14):**
- `departments.view`, `departments.manage`
- `cashier.operate`, `cashier.reconcile`, `cashier.view_all`
- `integrations.view`, `integrations.manage`, `integrations.import`, `integrations.post`
- `budgets.view`, `budgets.create`, `budgets.approve`
- `reports.usali`, `reports.department`, `reports.budget_variance`

Plus existing: `invoices.*`, `bills.*`, `payments.*`, `journal.*`, `reports.*`

---

## 🚀 What Needs To Be Built

### Priority 1 - Financial Core UI (40-60 hours)

1. **Department Management** (8-12 hrs)
   - List/create/edit departments
   - Assign GL accounts
   - Department hierarchy

2. **Cashier/Collection Module** (12-16 hrs)
   - Open/close sessions
   - Record collections
   - Reconciliation interface
   - Deposit tracking

3. **Integration Management** (16-20 hrs)
   - System configuration
   - Account mappings
   - Import transactions
   - Post to GL
   - Sync logs

4. **Financial Reporting** (20-30 hrs)
   - USALI P&L generator
   - Department P&L
   - Budget variance reports
   - Export to Excel/PDF

### Priority 2 - Budget Module (12-16 hours)

5. **Budget Management**
   - Create annual budgets
   - Monthly allocation
   - Budget approval workflow
   - Variance tracking

---

## 📖 Documentation

**Read in this order:**
1. `README_FINANCIALS.md` (this file) - Overview
2. `FINANCIALS_SCOPE.md` - Detailed scope
3. `INTEGRATION_GUIDE.md` - How to integrate
4. `financials_extension_schema.sql` - Database structure

---

## ⚙️ Installation

### Step 1: Run Setup

```bash
cd C:\wamp64\www\integ-capstone
php setup_financials_extension.php
```

**OR** via browser:
```
http://localhost/integ-capstone/setup_financials_extension.php
```

### Step 2: Verify

Login and check:
- Roles & Permissions → Should see 14 financial permissions
- Chart of Accounts → Should see accounts 4000-5699
- Database → Should have 14 new tables

### Step 3: Configure

1. Initialize default roles: **Roles & Permissions → Initialize Defaults**
2. Review departments: Check `departments` table
3. Set up system integrations (when ready)

---

## 🧪 Testing Integration

### Test Hotel System → FINANCIALS

```sql
-- Simulate hotel sending room charge
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, department_id, amount, customer_name, description)
VALUES
('TEST_001', 'HOTEL_CORE1', NOW(), 'room_charge',
 'TEST-FOLIO-001', 1, 5000.00, 'Test Guest', 'Room 101 - Test');

-- Check import
SELECT * FROM imported_transactions WHERE import_batch = 'TEST_001';
```

### Test Restaurant System → FINANCIALS

```sql
-- Simulate restaurant sending daily sales
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, department_id, amount, description)
VALUES
('TEST_002', 'RESTAURANT_CORE2', NOW(), 'daily_sales',
 'EOD-TEST-001', 2, 12000.00, 'Test restaurant sales');

-- Check import
SELECT * FROM imported_transactions WHERE import_batch = 'TEST_002';
```

---

## 🏗️ Development Guide

### Building Department Management Module

**Files to create:**
- `/admin/financials/departments.php` - Main UI
- `/admin/api/financials/departments.php` - API

**Example API endpoint:**
```php
<?php
// GET /admin/api/financials/departments.php
require_once '../../../includes/auth.php';
require_once '../../../includes/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('departments.view');

$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM departments WHERE is_active = 1");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'departments' => $departments]);
?>
```

---

## 📞 Support

**Questions about scope?**
→ Read `FINANCIALS_SCOPE.md`

**Need integration help?**
→ Read `INTEGRATION_GUIDE.md`

**Want to see database structure?**
→ Read `financials_extension_schema.sql`

**Out-of-scope features?**
→ Read `DEPRECATED_FILES.md`

---

## ✅ System Status

**Foundation:** 100% Complete
- ✅ Database schema (14 tables)
- ✅ USALI chart of accounts (150+ accounts)
- ✅ Integration framework
- ✅ Permissions system
- ✅ Responsive UI framework
- ✅ Complete documentation

**Module UIs:** 0% Complete
- 🚧 Department management
- 🚧 Cashier/collection
- 🚧 Integration management
- 🚧 Budget management
- 🚧 Financial reporting

**Estimated development:** 60-90 hours for complete system

---

## 🎯 Next Steps

1. ✅ Run `setup_financials_extension.php`
2. ✅ Read documentation
3. 🚧 Build department management UI
4. 🚧 Build cashier/collection UI
5. 🚧 Build integration UI
6. 🚧 Build reporting UI
7. 🚧 Connect other systems

---

**Version:** 1.1.0 - FINANCIALS Scope Revision
**Last Updated:** 2025-01-24
**License:** MIT
