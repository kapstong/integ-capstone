    # FINANCIALS System - Integration Guide

## Overview

The **FINANCIALS system** is the central financial hub that **receives** transaction data from operational systems and provides consolidated financial reporting.

**Integration Philosophy:**
- Operational systems (Hotel, Restaurant, Logistics, HR) manage their own operations
- FINANCIALS receives transaction summaries for accounting
- FINANCIALS provides financial reports back to all systems

---

## System Integration Map

```
┌─────────────────┐
│  HOTEL CORE 1   │ ──┐
│     (PMS)       │   │
└─────────────────┘   │
                      │
┌─────────────────┐   │    ┌──────────────────┐
│RESTAURANT CORE2 │ ──┼───→│   FINANCIALS     │
│     (POS)       │   │    │   (Central Hub)  │
└─────────────────┘   │    └──────────────────┘
                      │              │
┌─────────────────┐   │              ↓
│  LOGISTICS 1    │ ──┤      ┌──────────────┐
│  (Procurement)  │   │      │  Financial   │
└─────────────────┘   │      │  Reports     │
                      │      └──────────────┘
┌─────────────────┐   │
│   HR SYSTEMS    │ ──┘
│ (Payroll, etc)  │
└─────────────────┘
```

---

## Integration Tables

### 1. System Registration

Register your system in `system_integrations`:

```sql
INSERT INTO system_integrations
(system_code, system_name, system_type, sync_frequency)
VALUES
('HOTEL_CORE1', 'Hotel Management System', 'hotel_pms', 'realtime');
```

Pre-configured systems:
- `HOTEL_CORE1` - Hotel PMS
- `RESTAURANT_CORE2` - Restaurant POS
- `LOGISTICS1` - Logistics/Procurement
- `HR_SYSTEM` - HR/Payroll

### 2. Account Mapping

Map your transaction codes to GL accounts:

```sql
INSERT INTO integration_mappings
(system_code, external_code, external_name, mapping_type, gl_account_id, department_id)
VALUES
('HOTEL_CORE1', 'ROOM_DELUXE', 'Deluxe Room Sale', 'revenue', 4001, 1),
('RESTAURANT_CORE2', 'FOOD_MAIN', 'Restaurant Food', 'revenue', 4101, 2);
```

### 3. Send Transactions

Insert transactions into `imported_transactions`:

```sql
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, department_id, amount, raw_data)
VALUES
('BATCH_20250124_001', 'HOTEL_CORE1', NOW(), 'room_sale',
 'INV-12345', 1, 5000.00,
 '{"room_number":"101","guest":"John Doe","nights":2}');
```

---

## Integration Methods

### Method 1: Real-Time API (Recommended)

Create API endpoint in FINANCIALS:

```
POST /api/v1/transactions/import
Authorization: Bearer {api_key}
Content-Type: application/json

{
  "source_system": "HOTEL_CORE1",
  "transactions": [
    {
      "external_id": "INV-12345",
      "transaction_date": "2025-01-24 14:30:00",
      "transaction_type": "room_sale",
      "department_code": "ROOMS",
      "amount": 5000.00,
      "customer_name": "John Doe",
      "description": "Room 101 - 2 nights",
      "payment_method": "credit_card",
      "external_code": "ROOM_DELUXE"
    }
  ]
}
```

### Method 2: Batch Import (Daily/Hourly)

Export CSV from your system:

```csv
transaction_date,transaction_type,external_id,department,amount,external_code,description
2025-01-24 10:00:00,sale,POS-001,FB-REST,250.00,FOOD_MAIN,Table 5
2025-01-24 10:15:00,sale,POS-002,FB-BAR,180.00,BEV_ALC,Bar Sale
```

Import via FINANCIALS interface or script.

### Method 3: Database Direct Insert

For systems on same database server, insert directly to `imported_transactions` table.

---

## Data Flow Examples

### Hotel System → FINANCIALS

**Scenario:** Guest checks out, room charges need to be recorded

```sql
-- Hotel PMS executes:
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, external_reference, department_id, amount, customer_name,
 description, raw_data)
VALUES
('CHECKOUT_20250124', 'HOTEL_CORE1', '2025-01-24 11:00:00', 'room_charge',
 'FOLIO-456', 'INV-12345', 1, 10000.00, 'Jane Smith',
 'Room 201 - 5 nights @ 2000/night',
 '{"room_number":"201","rate":2000,"nights":5,"room_type":"SUITE"}');
```

FINANCIALS will:
1. Read transaction
2. Map `external_code` to GL account (via `integration_mappings`)
3. Create journal entry
4. Update revenue summary
5. Mark as `posted`

### Restaurant POS → FINANCIALS

**Scenario:** End of day sales summary

```sql
-- Restaurant POS sends daily summary:
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, department_id, amount, description, raw_data)
VALUES
('EOD_20250123', 'RESTAURANT_CORE2', '2025-01-23 23:59:59', 'daily_sales',
 'EOD-REST-20250123', 2, 45000.00, 'Daily restaurant sales',
 '{"food_sales":30000,"beverage_sales":15000,"transactions":127}');
```

### Logistics → FINANCIALS

**Scenario:** Purchase order received

```sql
-- Logistics sends expense transaction:
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, external_reference, department_id, amount, description)
VALUES
('PO_RECEIVED_001', 'LOGISTICS1', '2025-01-24 09:00:00', 'inventory_purchase',
 'PO-789', 'VENDOR-INV-456', 2, 25000.00, 'Food supplies - monthly order');
```

### HR System → FINANCIALS

**Scenario:** Monthly payroll

```sql
-- HR sends payroll expenses:
INSERT INTO imported_transactions
(import_batch, source_system, transaction_date, transaction_type,
 external_id, department_id, amount, description)
VALUES
('PAYROLL_JAN2025', 'HR_SYSTEM', '2025-01-31 23:59:59', 'payroll_expense',
 'PAYROLL-012025', 1, 150000.00, 'January 2025 payroll - Rooms division');
```

---

## Transaction Status Flow

```
pending → posted → (generates journal entry)
   ↓
rejected (if mapping fails or duplicate)
   ↓
duplicate (if external_id already exists)
```

---

## Best Practices

### 1. Use Batch Identifiers
- Group related transactions: `BATCH_YYYYMMDD_NNN`
- Easier reconciliation and troubleshooting

### 2. Include External References
- Always send your system's transaction ID
- Include invoice numbers, receipt numbers, etc.

### 3. Send Raw Data
- Store original transaction data in `raw_data` JSON field
- Enables audit trail and troubleshooting

### 4. Check for Duplicates
- FINANCIALS enforces unique constraint: `(source_system, external_id)`
- Handle duplicate errors gracefully

### 5. Department Mapping
- Always specify department/cost center
- Enables proper P&L reporting by department

---

## Reconciliation

### Daily Reconciliation Process

1. **Source System** sends transactions
2. **FINANCIALS** imports and validates
3. **FINANCIALS** posts to GL
4. **Source System** queries for posted status
5. **Compare totals** between systems

### Reconciliation Query

```sql
-- Check imported vs posted
SELECT
    source_system,
    DATE(transaction_date) as business_date,
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status='posted' THEN 1 ELSE 0 END) as posted_count,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(amount) as total_amount
FROM imported_transactions
WHERE transaction_date >= '2025-01-24'
GROUP BY source_system, DATE(transaction_date);
```

---

## Error Handling

### Common Errors

**1. Missing Account Mapping**
```
Error: No GL account mapping found for external_code 'XYZ'
Solution: Add mapping in integration_mappings table
```

**2. Invalid Department**
```
Error: Department code 'ABC' not found
Solution: Use valid department codes from departments table
```

**3. Duplicate Transaction**
```
Error: Duplicate entry for source_system='X', external_id='Y'
Solution: Check if already imported, handle accordingly
```

---

## Reporting Back to Source Systems

FINANCIALS can provide financial data back to operational systems:

### Query Financial Summary

```sql
-- Get department revenue for date range
SELECT
    d.dept_code,
    d.dept_name,
    SUM(drs.net_revenue) as total_revenue
FROM daily_revenue_summary drs
JOIN departments d ON drs.department_id = d.id
WHERE drs.business_date BETWEEN '2025-01-01' AND '2025-01-31'
  AND drs.source_system = 'HOTEL_CORE1'
GROUP BY d.dept_code, d.dept_name;
```

### Budget vs Actual

```sql
-- Get budget comparison
SELECT
    d.dept_name,
    mdp.total_revenue,
    mdp.budget_revenue,
    mdp.revenue_variance
FROM monthly_department_performance mdp
JOIN departments d ON mdp.department_id = d.id
WHERE mdp.year = 2025 AND mdp.month = 1;
```

---

## API Endpoints (To Be Implemented)

```
POST   /api/v1/transactions/import     - Import transactions
GET    /api/v1/transactions/{id}       - Get transaction status
GET    /api/v1/summary/daily           - Get daily summary
GET    /api/v1/summary/department      - Get department summary
GET    /api/v1/budget/variance         - Get budget variance
POST   /api/v1/mappings                - Create account mapping
GET    /api/v1/mappings                - List mappings
```

---

## Sample Integration Code

### PHP Example (Hotel System)

```php
<?php
// Send room charge to FINANCIALS
function postRoomCharge($folioId, $roomNumber, $amount, $guestName) {
    $db = getFinancialsDB(); // Connection to FINANCIALS DB

    $stmt = $db->prepare("
        INSERT INTO imported_transactions
        (import_batch, source_system, transaction_date, transaction_type,
         external_id, department_id, amount, customer_name, description, raw_data)
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        'BATCH_' . date('Ymd_His'),
        'HOTEL_CORE1',
        'room_charge',
        "FOLIO-{$folioId}",
        1, // Rooms department
        $amount,
        $guestName,
        "Room {$roomNumber} charge",
        json_encode(['room' => $roomNumber, 'folio' => $folioId])
    ]);

    return $db->lastInsertId();
}
?>
```

### JavaScript Example (Restaurant POS)

```javascript
// Send end-of-day sales to FINANCIALS
async function sendDailySales(date, foodSales, beverageSales) {
    const transaction = {
        import_batch: `EOD_${date.replace(/-/g, '')}`,
        source_system: 'RESTAURANT_CORE2',
        transaction_date: `${date} 23:59:59`,
        transaction_type: 'daily_sales',
        external_id: `EOD-REST-${date}`,
        department_id: 2, // Restaurant department
        amount: foodSales + beverageSales,
        description: 'Daily restaurant sales',
        raw_data: JSON.stringify({
            food_sales: foodSales,
            beverage_sales: beverageSales,
            date: date
        })
    };

    // Insert to FINANCIALS database
    await insertImportedTransaction(transaction);
}
```

---

## Summary

**Integration Pattern:**
1. Register your system → `system_integrations`
2. Map your codes → `integration_mappings`
3. Send transactions → `imported_transactions`
4. FINANCIALS posts to GL automatically
5. Query summaries for reporting

**Key Tables:**
- `system_integrations` - System registry
- `integration_mappings` - Code to GL mappings
- `imported_transactions` - Transaction import queue
- `daily_revenue_summary` - Aggregated revenue
- `daily_expense_summary` - Aggregated expenses

**Support:** Review `financials_extension_schema.sql` for complete table structure
