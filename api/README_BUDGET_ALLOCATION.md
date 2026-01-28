# Budget Allocation API – Integration Guide

This document describes how each department can integrate with the Budget Allocation API and how alerts are computed. It is written for department developers who will consume the API.

## Scope
This API provides department budget allocations and alert status. It supports multiple budget periods and rolls over unused budget based on the parent budget configuration.

## Access & Authorization
### Admin / Super Admin (UI)
- Required roles: Admin or Super Admin.
- If staff users are allowed to submit budget actions, the actions must be approved by Admin/Super Admin before becoming effective.

### External Department Integrations (OAuth)
- Use OAuth Client Credentials to obtain a Bearer token.
- Department tokens are scoped to a single department.

## Base URL
- `/api/budgets.php`
- Single gateway endpoint:
  - `GET|POST /api/budget_exchange.php`
- Integration endpoints:
  - `POST /api/oauth/token.php`
  - `POST /api/integrations/budget_allocation.php`

## Supported Periods
- Monthly
- Quarterly
- Semi-Annually
- Annually
- Yearly

Departments should pass their period for display and internal filtering; the server-side data currently aggregates by budget entries and their date ranges.

## Currency
- Philippine Peso (PHP)

## Alert Thresholds
Departments must display the status based on utilization percentage:
- 70% = Yellow
- 80% = Light Orange
- 90% = Orange
- 100% = Red

Utilization is based on **both committed and actual** usage when applicable:

```
utilized_total = committed_amount + actual_amount
utilization_pct = (utilized_total / allocated_amount) * 100
```

If your department only tracks one spend source, treat the other as 0.

## Endpoints

### 0) Single gateway endpoint (recommended for departments)
Use this one endpoint for both retrieving and pushing allocations.

- **GET** `/api/budget_exchange.php?action=allocations` (OAuth bearer required)
- **POST** `/api/budget_exchange.php` (OAuth bearer required)
```
{
  "action": "allocate",
  "department_id": 3,
  "budget_name": "External Allocation 2026",
  "allocated_amount": 1250000,
  "period": "Yearly",
  "start_date": "2026-01-01",
  "end_date": "2026-12-31",
  "description": "Department-provided allocation"
}
```

### 1) Get budget allocations
**GET** `/api/budgets.php?action=allocations`

Returns allocation totals per department.

Sample response:
```
{
  "allocations": [
    {
      "id": 1,
      "department": "Human Resource 1",
      "department_id": 2,
      "total_amount": 1000000,
      "utilized_amount": 450000,
      "reserved_amount": 50000,
      "remaining": 500000
    }
  ]
}
```

Recommended client-side fields:
- `allocated_amount`: `total_amount`
- `committed_amount`: `reserved_amount`
- `actual_amount`: `utilized_amount`
- `remaining`: `remaining`

### 2) Get alerts (over-budget only)
**GET** `/api/budgets.php?action=alerts`

Returns departments that have exceeded their budgets.

Sample response:
```
{
  "alerts": [
    {
      "id": 1,
      "department": "Logistics 1",
      "budget_year": 2026,
      "budgeted_amount": 500000,
      "actual_amount": 650000,
      "over_amount": 150000,
      "over_percent": 30,
      "severity": "high",
      "alert_date": "2026-01-28 14:00:00"
    }
  ]
}
```

For threshold alerts (70/80/90/100), compute client-side using allocations.

### 3) Get categories (optional reference)
**GET** `/api/budgets.php?action=categories`

Returns active budget categories mapped to departments.

### 4) Create a budget (Admin/Super Admin only)
**POST** `/api/budgets.php`

Request body:
```
{
  "name": "HR1 Budget FY2026",
  "description": "Annual budget for HR1",
  "start_date": "2026-01-01",
  "end_date": "2026-12-31",
  "total_amount": 1200000,
  "department_id": 2
}
```

### 5) Allocate funds to a department/category
**POST** `/api/budgets.php`

Request body:
```
{
  "action": "item",
  "budget_id": 10,
  "category_id": 7,
  "department_id": 2,
  "account_id": 45,
  "vendor_id": null,
  "budgeted_amount": 250000,
  "notes": "Recruitment and onboarding"
}
```

### 6) Request a budget adjustment (Admin/Super Admin approval required)
**POST** `/api/budgets.php`

Request body:
```
{
  "action": "adjustment",
  "budget_id": 10,
  "department_id": 2,
  "adjustment_type": "increase",
  "amount": 50000,
  "reason": "Extra hires",
  "effective_date": "2026-03-01"
}
```

### 7) Approve or reject adjustment
**POST** `/api/budgets.php`

Request body:
```
{
  "action": "adjustment_status",
  "adjustment_id": 123,
  "status": "approved"
}
```

## Department Mapping
Use these department buckets when tagging allocations:
- HR1
- HR2
- HR3
- HR4
- CORE1
- CORE2
- LOGISTICS1
- LOGISTICS2
- ADMINISTRATIVE

Departments should map their internal module-level spend to one of the above.

## Spend Sources
Spend sources vary by department. Each department can calculate `committed_amount` and `actual_amount` from its own module(s). The API will accept the totals for reporting. If only a single source is available, submit one and set the other to 0.

## Enforcement
- At 100% or above: block the spend submission and require Admin/Super Admin approval.
- At 70/80/90%: allow but warn (yellow/light orange/orange).
If a transaction exceeds the available budget, the system creates an approval task for Admin/Super Admin and blocks the submission.

## Alert Delivery
- In-app alert at the relevant page.
- Email to the department’s configured email address.

### Email Recipients
The system sends budget threshold alerts to:
- `departments.department_email` (if available), and
- users whose `users.department` matches the department name or code.

If your database does not yet have `departments.department_email`, users mapped by department will still receive emails.

### Cron Job (Email Alerts)
Run the budget alerts cron job periodically (every 10–15 minutes recommended):
```
php cron/budget_alerts.php
```

### Optional Schema Update (Department Email)
If you want a dedicated department email, add this column:
```
ALTER TABLE departments
  ADD COLUMN department_email VARCHAR(255) NULL;
```

## OAuth Integration Flow (External Departments)

### 1) Register a Department Client (Admin/Super Admin)
**POST** `/api/integrations/budget_allocation.php`
```
{
  "action": "register",
  "department_id": 3
}
```
Response:
```
{
  "success": true,
  "client_id": "...",
  "client_secret": "..."
}
```

### 2) Obtain Access Token (Client Credentials)
**POST** `/api/oauth/token.php`
```
{
  "grant_type": "client_credentials",
  "client_id": "...",
  "client_secret": "..."
}
```
Response:
```
{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### 3) Push Budget Allocation (Department System)
**POST** `/api/integrations/budget_allocation.php` or `/api/budget_exchange.php`
Header:
```
Authorization: Bearer <access_token>
```
Body:
```
{
  "action": "allocate",
  "department_id": 3,
  "budget_name": "External Allocation 2026",
  "allocated_amount": 1250000,
  "period": "Yearly",
  "start_date": "2026-01-01",
  "end_date": "2026-12-31",
  "description": "Department-provided allocation"
}
```

## Required Data per Department (Minimum)
- Department ID / Code
- Period (Monthly/Quarterly/Semi-Annually/Annually/Yearly)
- Allocated Budget
- Committed Amount
- Actual Amount

## Example: Client-side status computation
```
const utilized = committedAmount + actualAmount;
const pct = allocatedAmount > 0 ? (utilized / allocatedAmount) * 100 : 0;
let status = 'OK';
let color = 'green';
if (pct >= 100) { status = 'OVER'; color = 'red'; }
else if (pct >= 90) { status = 'AT_LIMIT'; color = 'orange'; }
else if (pct >= 80) { status = 'WARNING'; color = 'light-orange'; }
else if (pct >= 70) { status = 'NOTICE'; color = 'yellow'; }
```

## Notes / Caveats
- This API currently uses session auth; token-based auth can be added if required for cross-system access.
- Allocation and alerts are stored at the budget level; module-level spend is expected to be aggregated by each department before sending/consuming.

## Support
For schema or endpoint changes, coordinate with Finance/IT Admin.
