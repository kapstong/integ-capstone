# Deprecated Files - Out of Scope for FINANCIALS System

The following files contained operational modules that belong to other systems (Hotel, Restaurant, Logistics, HR). They are kept for reference but should NOT be used.

---

## ❌ DO NOT USE THESE FILES

### 1. hotel_restaurant_schema.sql
**Reason:** Contains 40+ operational tables (rooms, inventory, POS, menus, etc.)
**Replaced by:** `financials_extension_schema.sql` (14 financial-only tables)

**Out-of-scope tables included:**
- room_types, rooms, room_reservations, daily_occupancy
- inventory_categories, inventory_items, inventory_transactions
- purchase_orders, purchase_order_items
- commission_rules, staff_commissions, commission_transactions
- pos_terminals, pos_sales, pos_sale_items
- menu_categories, menu_items, menu_item_ingredients
- housekeeping_tasks
- event_types, event_venues, event_bookings

---

### 2. setup_hotel_restaurant.php
**Reason:** Installs operational modules
**Replaced by:** `setup_financials_extension.php` (financial modules only)

---

### 3. HOTEL_RESTAURANT_FEATURES.md
**Reason:** Documents operational features outside FINANCIALS scope
**Replaced by:** `FINANCIALS_SCOPE.md` + `INTEGRATION_GUIDE.md`

**Out-of-scope features documented:**
- Room management operations
- Inventory/stock management
- POS terminal operations
- Menu and recipe management
- Housekeeping operations
- Event booking operations
- HR commission tracking

---

### 4. PROJECT_STATUS_SUMMARY.md
**Reason:** References out-of-scope operational modules
**Will be replaced by:** New project status for FINANCIALS scope

---

### 5. IMPLEMENTATION_GUIDE.md
**Reason:** Includes guides for operational modules
**Partially valid:** GL, AP, AR, budgeting sections still apply
**Ignore:** Sections on inventory, POS, rooms, commissions, events

---

## ✅ USE THESE FILES INSTEAD

### Core Financial System
1. **financials_extension_schema.sql** - Financial tables only (14 tables)
2. **hotel_restaurant_accounts.sql** - USALI chart of accounts (KEEP - still valid)
3. **setup_financials_extension.php** - Setup script for financial modules
4. **responsive.css** - UI styles (KEEP - still valid)

### Documentation
5. **FINANCIALS_SCOPE.md** - What's in scope for FINANCIALS
6. **INTEGRATION_GUIDE.md** - How other systems integrate
7. **DEPRECATED_FILES.md** - This file

### Original Core Files (Still Valid)
8. **database_schema.sql** - Original financial system schema
9. **config.php** - Configuration
10. **includes/** - All core libraries
11. **admin/** - Admin panel
12. **api/** - API endpoints

---

## Migration Path

If you already ran `setup_hotel_restaurant.php`:

### Option 1: Clean Install (Recommended)
```sql
-- Drop the out-of-scope tables
DROP TABLE IF EXISTS room_types, rooms, room_reservations, daily_occupancy;
DROP TABLE IF EXISTS inventory_categories, inventory_items, inventory_transactions;
DROP TABLE IF EXISTS purchase_orders, purchase_order_items;
DROP TABLE IF EXISTS commission_rules, staff_commissions, commission_transactions;
DROP TABLE IF EXISTS pos_terminals, pos_sales, pos_sale_items;
DROP TABLE IF EXISTS menu_categories, menu_items, menu_item_ingredients;
DROP TABLE IF EXISTS housekeeping_tasks;
DROP TABLE IF EXISTS event_types, event_venues, event_bookings;

-- Then run:
php setup_financials_extension.php
```

### Option 2: Keep for Reference
- Leave the extra tables in database (they won't hurt)
- Just don't use them
- Use only the FINANCIALS-scoped features

---

## Why These Files Are Deprecated

Based on your organizational chart:

- **HOTEL CORE 1** manages rooms, reservations, housekeeping, events
- **RESTAURANT CORE 2** manages menus, POS, tables, orders
- **LOGISTICS 1** manages inventory, procurement, assets
- **HR SYSTEMS** manage payroll, commissions, staff

- **FINANCIALS** receives transaction summaries from all systems and provides consolidated financial reporting

---

## What To Do

1. **Delete or ignore** deprecated files
2. **Run** `setup_financials_extension.php`
3. **Read** `FINANCIALS_SCOPE.md` to understand what's included
4. **Read** `INTEGRATION_GUIDE.md` to learn how other systems send data
5. **Build** only financial modules (department mgmt, cashier, reporting)

---

## Questions?

Review:
- `FINANCIALS_SCOPE.md` - What FINANCIALS does
- `INTEGRATION_GUIDE.md` - How systems connect
- `financials_extension_schema.sql` - Database structure
