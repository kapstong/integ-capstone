# ATIERA FINANCIALS System - Scope Document

## System Scope: FINANCIALS Department Only

This system is specifically for the **FINANCIALS department** as shown in your organizational chart. It does NOT include operational modules from other departments.

---

## ✅ IN SCOPE (FINANCIALS Department)

### Core Financial Modules
1. **Budget Management** ✅
   - Annual budgeting by department
   - Monthly budget allocation
   - Budget vs actual tracking

2. **Collection** ✅
   - Daily cashier sessions
   - Cash reconciliation
   - Payment collection tracking
   - Deposit management

3. **General Ledger** ✅
   - Chart of accounts (USALI format)
   - Journal entries
   - Account balances
   - Trial balance

4. **Accounts Payable / Accounts Receivable** ✅
   - Vendor management
   - Bill processing and payment
   - Customer management
   - Invoice generation and tracking
   - Payment tracking

5. **Billing and Payment Module** ✅
   - Invoice creation
   - Bill creation
   - Payment processing
   - Payment allocation

### Financial Management Features
6. **Department/Cost Center Tracking** ✅
   - Financial department definitions
   - Revenue centers
   - Cost centers
   - Department P&L

7. **Financial Reporting** ✅
   - USALI Income Statement
   - Department P&L reports
   - Budget variance reports
   - Cash flow reports
   - Balance sheet

8. **System Integration** ✅
   - Receive transactions from Hotel system
   - Receive transactions from Restaurant system
   - Receive transactions from Logistics system
   - Receive data from HR system
   - Consolidated financial reporting

---

## ❌ OUT OF SCOPE (Belongs to Other Systems)

### HOTEL CORE 1 System
- ❌ Room inventory management
- ❌ Reservations and booking
- ❌ Front desk operations
- ❌ Guest relationship management (CRM)
- ❌ Housekeeping task management
- ❌ Event/conference booking operations
- ❌ Loyalty programs
- ❌ Channel management

### RESTAURANT CORE 2 System
- ❌ Menu management
- ❌ Recipe and ingredient tracking
- ❌ Table management
- ❌ Reservation/seating
- ❌ Order taking (POS operations)
- ❌ Kitchen order tickets (KOT)
- ❌ Wait staff management
- ❌ Table turnover tracking

### LOGISTICS 1 System
- ❌ Inventory item master
- ❌ Stock management
- ❌ Purchase order creation and approval
- ❌ Procurement workflows
- ❌ Asset lifecycle management
- ❌ Fleet management
- ❌ Vehicle dispatch

### HR RESOURCE Systems
- ❌ Recruitment and hiring
- ❌ Performance management
- ❌ Training management
- ❌ Shift scheduling
- ❌ Time and attendance
- ❌ Payroll processing
- ❌ Commission calculation
- ❌ Compensation planning
- ❌ HR analytics
- ❌ Benefits administration

### ADMINISTRATIVE System
- ❌ Document management
- ❌ Visitor management
- ❌ Smart warehouse system

---

## Integration Model

```
FINANCIALS receives data from:

┌─────────────────────────────────────┐
│     OPERATIONAL SYSTEMS             │
│  (Manage their own operations)      │
├─────────────────────────────────────┤
│  • Hotel Core 1 (Room sales)        │
│  • Restaurant Core 2 (F&B sales)    │
│  • Logistics 1 (Purchases, assets)  │
│  • HR Systems (Payroll, expenses)   │
└──────────────┬──────────────────────┘
               │
               ↓ Send transaction summaries
┌──────────────────────────────────────┐
│         FINANCIALS SYSTEM            │
│   (Consolidates & Reports)           │
├──────────────────────────────────────┤
│  • Records all transactions          │
│  • Posts to General Ledger           │
│  • Tracks by department/cost center  │
│  • Generates financial reports       │
│  • Budget vs actual analysis         │
└──────────────────────────────────────┘
```

---

## Database Structure

### Financial-Only Tables (14 tables)

1. **departments** - Financial cost/revenue centers
2. **revenue_centers** - Revenue tracking points
3. **cashier_sessions** - Collection tracking
4. **cashier_transactions** - Collection details
5. **system_integrations** - Connected systems registry
6. **integration_mappings** - External code → GL mappings
7. **imported_transactions** - Transaction import queue
8. **integration_sync_logs** - Integration history
9. **daily_revenue_summary** - Daily revenue by dept
10. **daily_expense_summary** - Daily expenses by dept
11. **monthly_department_performance** - Monthly P&L
12. **department_budgets** - Annual budgets
13. **saved_financial_reports** - Report templates
14. Plus existing tables: chart_of_accounts, journal_entries, invoices, bills, customers, vendors, payments, etc.

### What Was Removed

- ❌ 26+ operational tables:
  - rooms, room_types, room_reservations, daily_occupancy
  - inventory_items, inventory_transactions, purchase_orders
  - menu_items, menu_categories, menu_item_ingredients
  - pos_terminals, pos_sales, pos_sale_items
  - staff_commissions, commission_rules, commission_transactions
  - event_bookings, event_venues, event_types
  - housekeeping_tasks

---

## Key Features

### 1. Department Financial Tracking
- Revenue centers: Rooms, F&B, Spa, Events
- Cost centers: Admin, Maintenance, Marketing
- Hierarchical department structure
- Department-level P&L

### 2. Collection Module
- Multi-shift cashier sessions
- Cash/card/check tracking
- Variance calculation
- Supervisor reconciliation
- Bank deposit tracking

### 3. Integration Framework
- Receive transactions from 4+ systems
- Automatic GL account mapping
- Transaction validation
- Posting automation
- Reconciliation reports

### 4. Financial Reporting
- USALI-format reports
- Department P&L statements
- Budget vs actual variance
- Cash flow analysis
- Consolidated financials

### 5. USALI Chart of Accounts
- 150+ accounts for hotel/restaurant
- Revenue: 4000-4399
- Expenses: 5000-5699
- Assets: 1000-1999
- Liabilities: 2000-2999

---

## Workflow Examples

### Example 1: Hotel Room Sale

**Hotel System (CORE 1):**
1. Guest checks out
2. Folio closed with room charges
3. **Sends to FINANCIALS:** Room charge transaction

**FINANCIALS System:**
1. Receives transaction
2. Maps to GL account 4001 (Room Sales)
3. Posts to General Ledger
4. Updates revenue summary for Rooms department
5. Includes in daily/monthly reports

### Example 2: Restaurant Sales

**Restaurant System (CORE 2):**
1. Day's POS sales completed
2. End-of-day summary generated
3. **Sends to FINANCIALS:** Daily sales breakdown

**FINANCIALS System:**
1. Receives food/beverage sales
2. Maps to GL accounts 4101/4102
3. Posts to General Ledger
4. Updates F&B department revenue
5. Calculates food cost % (when inventory data received)

### Example 3: Vendor Payment

**Logistics System:**
1. Purchase order received
2. Invoice from vendor
3. **Sends to FINANCIALS:** Purchase amount

**FINANCIALS System:**
1. Records in Accounts Payable
2. Creates bill
3. Schedules payment
4. **Processes payment** (FINANCIALS owns this)
5. Updates vendor balance
6. Posts expense to correct department

### Example 4: Payroll

**HR System:**
1. Calculates monthly payroll
2. Breaks down by department
3. **Sends to FINANCIALS:** Payroll by department

**FINANCIALS System:**
1. Records payroll expense
2. Allocates to departments
3. Posts to GL (salary expense accounts)
4. Updates department P&L
5. Includes in financial statements

---

## Permissions

### Financial-Specific Permissions (14 permissions)

**Department Management:**
- `departments.view` - View departments
- `departments.manage` - Manage departments

**Collection/Cashier:**
- `cashier.operate` - Operate cashier
- `cashier.reconcile` - Reconcile sessions
- `cashier.view_all` - View all sessions

**Integration:**
- `integrations.view` - View integrations
- `integrations.manage` - Manage integrations
- `integrations.import` - Import transactions
- `integrations.post` - Post to GL

**Budgets:**
- `budgets.view` - View budgets
- `budgets.create` - Create budgets
- `budgets.approve` - Approve budgets

**Reports:**
- `reports.usali` - USALI reports
- `reports.department` - Department reports
- `reports.budget_variance` - Variance reports

---

## Files Structure

```
integ-capstone/
├── financials_extension_schema.sql    ✅ Financial tables only
├── hotel_restaurant_accounts.sql      ✅ USALI chart of accounts
├── setup_financials_extension.php     ✅ Setup script
├── INTEGRATION_GUIDE.md               ✅ How systems integrate
├── FINANCIALS_SCOPE.md                ✅ This document
└── responsive.css                     ✅ UI responsive styles
```

---

## Installation

```bash
# Run setup (creates tables, permissions, sample data)
php setup_financials_extension.php

# OR via browser:
http://localhost/integ-capstone/setup_financials_extension.php
```

---

## Next Steps

### For FINANCIALS System
1. ✅ Install financial extension
2. ✅ Configure departments
3. 🚧 Build department management UI
4. 🚧 Build cashier/collection UI
5. 🚧 Build integration management UI
6. 🚧 Build USALI reporting UI

### For Other Systems
1. Implement their operational modules
2. Send transaction summaries to FINANCIALS
3. Use FINANCIALS for financial reporting
4. Query FINANCIALS for budget/variance data

---

## Summary

**FINANCIALS System Purpose:**
- Central financial consolidation point
- Receives data from all operational systems
- Provides enterprise-wide financial reporting
- Manages budgets and forecasts
- Handles all accounting functions

**What it does NOT do:**
- Operational management (rooms, tables, inventory, staff)
- Those are handled by specialized systems
- FINANCIALS only tracks the financial impact

**Integration is key:**
- Other systems run operations
- FINANCIALS records the financial transactions
- Everyone benefits from consolidated reporting
