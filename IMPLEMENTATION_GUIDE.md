# ATIERA Hotel & Restaurant System - Implementation Guide

## Quick Start Guide

This guide will help you get your enhanced ATIERA Hotel & Restaurant Financial Management System up and running.

---

## What's Been Completed ✅

### 1. Database Schema & Extensions
✅ **40+ new database tables** for hotel/restaurant operations
- Department management
- Room inventory and reservations
- F&B inventory system
- POS integration tables
- Cashier operations
- Commission tracking
- Event management
- Housekeeping
- Menu management

✅ **USALI-compliant chart of accounts** (150+ accounts)
- Complete revenue account structure (4000-4399)
- Comprehensive expense accounts (5000-5699)
- Hotel/restaurant specific asset accounts
- Liability accounts for deposits, service charges, etc.

✅ **Automated setup script** (`setup_hotel_restaurant.php`)
- One-click installation of all extensions
- Automatic permission creation
- Sample data insertion

### 2. Role-Based Access Control (RBAC)
✅ **25+ new permissions** for hotel/restaurant modules
- Department management permissions
- Room and reservation permissions
- Inventory management permissions
- Cashier operation permissions
- Commission management permissions
- Event management permissions
- POS and revenue management permissions

✅ **Permission management system** fully functional
- View all roles and permissions
- Assign/remove roles from users
- Assign/remove permissions from roles
- Create custom roles
- Working API endpoints

### 3. Responsive Design
✅ **Comprehensive responsive CSS** (`responsive.css`)
- Mobile-first design approach
- Breakpoints for all device sizes
- Responsive tables with mobile stacking
- Mobile-optimized forms and buttons
- Sidebar navigation for mobile
- Touch-friendly interfaces
- Print-friendly styles

### 4. Documentation
✅ **Complete documentation package**
- `HOTEL_RESTAURANT_FEATURES.md` - Full feature documentation
- `IMPLEMENTATION_GUIDE.md` - This guide
- Database schema documentation
- API integration guidelines
- Role-based access documentation

### 5. Integration Framework
✅ **API-ready architecture**
- Database structure supports PMS integration
- POS integration tables ready
- Webhook-ready event structure
- Standard API response formats

---

## What Needs To Be Implemented 🚧

### Priority 1: Core Module UIs (Required for full functionality)

#### 1.1 Department Management UI
**Files to create:**
- `/admin/departments.php` - Department CRUD interface
- `/admin/api/departments.php` - Department API endpoint

**Features needed:**
- List all departments
- Create new department
- Edit department details
- Assign department manager
- Link GL accounts

#### 1.2 Revenue Management Dashboard
**Files to create:**
- `/admin/revenue_dashboard.php` - Revenue KPI dashboard
- `/admin/api/revenue.php` - Revenue metrics API

**Features needed:**
- Display ADR, RevPAR, occupancy %
- Department revenue breakdown
- Daily/weekly/monthly trends
- Charts and visualizations

#### 1.3 Inventory Management UI
**Files to create:**
- `/admin/inventory.php` - Inventory list and management
- `/admin/inventory_items.php` - Item master management
- `/admin/purchase_orders.php` - PO management
- `/admin/api/inventory.php` - Inventory API
- `/admin/api/purchase_orders.php` - PO API

**Features needed:**
- Browse inventory items
- Add/edit/delete items
- Record inventory transactions
- Create and approve purchase orders
- Stock level alerts

#### 1.4 Cashier Reconciliation UI
**Files to create:**
- `/admin/cashier_sessions.php` - Session management
- `/user/cashier.php` - Staff cashier interface
- `/admin/api/cashier.php` - Cashier API

**Features needed:**
- Open/close cashier session
- Record transactions
- End-of-day reconciliation
- Variance reports
- Supervisor approval

#### 1.5 Commission Tracking UI
**Files to create:**
- `/admin/commissions.php` - Commission management
- `/admin/commission_rules.php` - Rules configuration
- `/admin/api/commissions.php` - Commission API

**Features needed:**
- Configure commission rules
- Calculate staff commissions
- Approve commissions
- Commission reports

### Priority 2: Integration Modules

#### 2.1 POS Integration Module
**Files to create:**
- `/admin/pos_integration.php` - POS settings and import
- `/admin/api/pos.php` - POS API
- `/cron/pos_sync.php` - Automated sync script

**Features needed:**
- Import POS sales
- Map POS items to inventory
- Auto-post to accounting
- Sync logs and error handling

#### 2.2 PMS Integration API
**Files to create:**
- `/api/v1/reservations.php` - Reservation API
- `/api/v1/rooms.php` - Room status API
- `/api/v1/webhooks.php` - Webhook handler

**Features needed:**
- Receive reservations from PMS
- Update room status
- Post room charges
- Webhook notifications

### Priority 3: Enhanced Reporting

#### 3.1 USALI Reports
**Files to create:**
- `/admin/usali_reports.php` - USALI report generator
- `/admin/api/usali.php` - USALI report API

**Features needed:**
- Department P&L statements
- Revenue center reports
- Cost center expense reports
- Consolidated financial statements

### Priority 4: Additional Features

#### 4.1 Event Management UI
- Event booking interface
- Venue calendar
- Catering management
- Invoice generation

#### 4.2 Housekeeping Module
- Task assignment
- Room status updates
- Maintenance requests

---

## Step-by-Step Implementation

### Step 1: Initial Setup (15 minutes)

1. **Run the Hotel/Restaurant Setup Script**

   Open your browser and navigate to:
   ```
   http://localhost/integ-capstone/setup_hotel_restaurant.php
   ```

   OR run via command line:
   ```bash
   cd C:\wamp64\www\integ-capstone
   php setup_hotel_restaurant.php
   ```

   **Expected Output:**
   ```
   ✓ Created 40+ hotel/restaurant tables
   ✓ Added USALI chart of accounts
   ✓ Created 25+ hotel/restaurant permissions
   ✓ Assigned permissions to admin role
   ✓ Sample data verified
   ```

2. **Verify Database Changes**

   Open phpMyAdmin or your MySQL client:
   ```
   http://localhost/phpmyadmin
   ```

   Check that these tables exist:
   - departments
   - room_types
   - rooms
   - inventory_items
   - cashier_sessions
   - pos_sales
   - event_bookings
   - (and 30+ more)

3. **Log In as Admin**

   ```
   URL: http://localhost/integ-capstone/
   Username: admin
   Password: admin123
   ```

   **IMPORTANT:** Change the default password immediately!

4. **Verify Permissions**

   - Navigate to **Admin Panel → Roles & Permissions**
   - Check that new hotel/restaurant permissions are listed
   - Verify admin role has all permissions

### Step 2: Configure Basic Data (30 minutes)

1. **Initialize Default Roles and Permissions**

   - Go to **Roles & Permissions**
   - Click "Initialize Defaults" button
   - This creates default roles: admin, manager, accountant, staff, user

2. **Review Default Departments**

   The following departments are pre-loaded:
   - ROOMS - Rooms Division
   - FB-REST - Restaurant
   - FB-BAR - Bar
   - FB-BANQ - Banquet & Events
   - SPA - Spa & Wellness
   - LAUNDRY - Laundry Services
   - MAINT - Maintenance
   - ADMIN - Administration

   You can add more departments as needed (once the UI is built).

3. **Review Chart of Accounts**

   - Navigate to **General Ledger → Chart of Accounts**
   - Verify that hotel/restaurant accounts are present:
     - 4001-4399: Revenue accounts
     - 5001-5699: Expense accounts
     - 1101-1110: Inventory assets
     - 2101-2109: Current liabilities

### Step 3: Test Responsive Design (10 minutes)

1. **Test on Desktop**
   - Resize browser window
   - Verify sidebar collapses appropriately
   - Check table responsiveness

2. **Test on Tablet**
   - Open site on tablet or use browser dev tools (F12)
   - Set viewport to tablet size (768px wide)
   - Verify layout adapts

3. **Test on Mobile**
   - Set viewport to mobile size (375px wide)
   - Verify:
     - Sidebar becomes mobile menu
     - Tables scroll horizontally or stack
     - Buttons are full-width
     - Forms are easy to use

### Step 4: Test Existing Features (15 minutes)

1. **Test Invoice Creation**
   - Navigate to **Accounts Receivable → Invoices**
   - Create a new invoice
   - Add line items
   - Save and verify

2. **Test Bill Creation**
   - Navigate to **Accounts Payable → Bills**
   - Create a new bill
   - Add line items
   - Save and verify

3. **Test Role Management**
   - Navigate to **Roles & Permissions**
   - Create a test role (e.g., "Front Desk")
   - Assign some permissions
   - Create a test user
   - Assign the role to the user

4. **Test Reports**
   - Navigate to **Reports**
   - Generate a simple report
   - Verify data displays correctly

---

## Building the Remaining Modules

### Example: Department Management Module

Here's how to build the Department Management UI:

1. **Create the Main Page** (`/admin/departments.php`)

```php
<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('departments.view');

$db = Database::getInstance()->getConnection();

// Fetch all departments
$stmt = $db->query("
    SELECT d.*, u.full_name as manager_name,
           ra.account_name as revenue_account,
           ea.account_name as expense_account
    FROM departments d
    LEFT JOIN users u ON d.manager_id = u.id
    LEFT JOIN chart_of_accounts ra ON d.revenue_account_id = ra.id
    LEFT JOIN chart_of_accounts ea ON d.expense_account_id = ea.id
    ORDER BY d.dept_code
");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Department Management';
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-building"></i> Department Management</h2>
                <?php if ($auth->hasPermission('departments.create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus"></i> Add Department
                </button>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Code</th>
                                    <th>Department Name</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Manager</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dept['dept_code']) ?></td>
                                    <td><?= htmlspecialchars($dept['dept_name']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $dept['dept_type'])) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $dept['category'])) ?></td>
                                    <td><?= htmlspecialchars($dept['manager_name'] ?? 'Not assigned') ?></td>
                                    <td>
                                        <?php if ($dept['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewDepartment(<?= $dept['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($auth->hasPermission('departments.edit')): ?>
                                        <button class="btn btn-sm btn-warning" onclick="editDepartment(<?= $dept['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<!-- ... modal HTML here ... -->

<script>
function viewDepartment(id) {
    // Implement view functionality
}

function editDepartment(id) {
    // Implement edit functionality
}
</script>

<?php include 'footer.php'; ?>
```

2. **Create the API Endpoint** (`/admin/api/departments.php`)

```php
<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';

header('Content-Type: application/json');
session_start();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

try {
    switch ($method) {
        case 'GET':
            // Get department(s)
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $dept = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'department' => $dept]);
            } else {
                $stmt = $db->query("SELECT * FROM departments ORDER BY dept_code");
                $depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'departments' => $depts]);
            }
            break;

        case 'POST':
            // Create new department
            if (!$auth->hasPermission('departments.create')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $db->prepare("
                INSERT INTO departments (dept_code, dept_name, dept_type, category, description, manager_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['dept_code'],
                $data['dept_name'],
                $data['dept_type'],
                $data['category'],
                $data['description'] ?? '',
                $data['manager_id'] ?? null
            ]);

            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;

        case 'PUT':
            // Update department
            if (!$auth->hasPermission('departments.edit')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            parse_str(file_get_contents('php://input'), $data);

            $stmt = $db->prepare("
                UPDATE departments
                SET dept_name = ?, description = ?, manager_id = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['dept_name'],
                $data['description'],
                $data['manager_id'],
                $data['is_active'],
                $data['id']
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Delete/deactivate department
            if (!$auth->hasPermission('departments.delete')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID required']);
                exit;
            }

            // Soft delete - set inactive instead of deleting
            $stmt = $db->prepare("UPDATE departments SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
```

3. **Add Navigation Menu Item**

Edit `/admin/header.php` and add:

```php
<?php if ($auth->hasPermission('departments.view')): ?>
<li class="nav-item">
    <a href="/admin/departments.php" class="nav-link">
        <i class="fas fa-building"></i> Departments
    </a>
</li>
<?php endif; ?>
```

Repeat this process for each module!

---

## Testing Checklist

### Functional Testing

- [ ] All database tables created successfully
- [ ] All chart of accounts entries inserted
- [ ] All permissions created and assigned
- [ ] Login works for admin user
- [ ] Role management UI functional
- [ ] Permission assignment works
- [ ] Invoice creation works
- [ ] Bill creation works
- [ ] Report generation works

### Responsive Testing

- [ ] Desktop (1920x1080) - Full layout
- [ ] Laptop (1366x768) - Optimized layout
- [ ] Tablet Portrait (768x1024) - Adapted layout
- [ ] Tablet Landscape (1024x768) - Adapted layout
- [ ] Mobile (375x667) - Mobile layout
- [ ] Mobile (320x568) - Small mobile layout

### Permission Testing

- [ ] Admin can access all features
- [ ] Manager role has appropriate access
- [ ] Staff role has limited access
- [ ] User role has minimal access
- [ ] Unauthorized access is blocked

### Integration Testing

- [ ] Test POS sales import (if implemented)
- [ ] Test PMS reservation sync (if implemented)
- [ ] Test webhook notifications (if implemented)
- [ ] Test API endpoints with Postman/curl

---

## Deployment to Production

When ready to deploy:

1. **Update Configuration**
   - Edit `config.php`
   - Set `app.env` to `production`
   - Update database credentials
   - Set proper `app.url`

2. **Security Hardening**
   - Change all default passwords
   - Enable HTTPS/SSL
   - Set proper file permissions
   - Enable firewall rules
   - Configure rate limiting

3. **Database Migration**
   - Export development database
   - Import to production database
   - Run `setup_hotel_restaurant.php` on production

4. **Testing**
   - Test all critical functions
   - Verify role-based access
   - Test on multiple devices
   - Performance testing

5. **Backup Strategy**
   - Set up automated backups
   - Test restore procedure
   - Store backups off-site

---

## Support Resources

- **Main Documentation:** `README.md`
- **Hotel/Restaurant Features:** `HOTEL_RESTAURANT_FEATURES.md`
- **API Documentation:** `API_README.md`
- **Database Schema:** `hotel_restaurant_schema.sql`, `database_schema.sql`

---

## Next Steps

1. ✅ Complete initial setup (this guide, Step 1-3)
2. 🚧 Build Department Management UI
3. 🚧 Build Inventory Management UI
4. 🚧 Build Cashier Reconciliation UI
5. 🚧 Build Revenue Dashboard
6. 🚧 Build Commission Tracking UI
7. 🚧 Implement POS Integration
8. 🚧 Implement PMS Integration API
9. 🚧 Build USALI Reports
10. 🚧 Build Event Management UI

---

**Good luck with your implementation!** 🚀

If you need help with specific modules, refer to the example code above and the comprehensive documentation provided.
