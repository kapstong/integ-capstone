# ATIERA Hotel & Restaurant Management System

## Overview

The ATIERA Financial Management System has been enhanced with comprehensive hotel and restaurant-specific features, following industry-standard practices and the USALI (Uniform System of Accounts for the Lodging Industry) format. This document outlines all the features, setup instructions, and integration capabilities.

---

## Table of Contents

1. [Features Overview](#features-overview)
2. [Installation & Setup](#installation--setup)
3. [Database Schema](#database-schema)
4. [Module Descriptions](#module-descriptions)
5. [Chart of Accounts (USALI)](#chart-of-accounts-usali)
6. [Role-Based Access Control](#role-based-access-control)
7. [API Integration](#api-integration)
8. [Responsive Design](#responsive-design)
9. [Usage Instructions](#usage-instructions)
10. [Future Enhancements](#future-enhancements)

---

## Features Overview

### Core Hotel/Restaurant Features

✅ **Department Management**
- Revenue Centers (Rooms, F&B, Events, Spa, etc.)
- Cost Centers (Maintenance, Administration)
- Department-level reporting and budgeting

✅ **Room Management**
- Room inventory tracking
- Room types and rate management
- Reservations system (PMS integration ready)
- Daily occupancy tracking
- ADR (Average Daily Rate) calculation
- RevPAR (Revenue Per Available Room) calculation
- Occupancy percentage tracking

✅ **Food & Beverage Management**
- Inventory management for F&B items
- Recipe costing with ingredient tracking
- Menu management by department
- Cost of goods sold (COGS) tracking
- Food cost percentage calculation
- Multiple F&B outlets support (Restaurant, Bar, Banquet, Room Service)

✅ **Inventory System**
- Multi-category inventory (Food, Beverage, Supplies, Linen, Amenities)
- Purchase order management
- Stock level tracking and alerts
- Inventory valuation
- Transaction history (purchases, usage, adjustments, transfers, waste)

✅ **Daily Cashier Operations**
- Cashier session management
- Multi-shift support (morning, afternoon, night, full day)
- Opening/closing balance reconciliation
- Variance tracking
- Payment method breakdown (cash, card, checks, etc.)
- Department-specific cashier tracking

✅ **Commission Tracking**
- Staff commission rules (percentage, fixed, tiered)
- Automated commission calculation
- Period-based commission tracking
- Commission approval workflow
- Payroll integration points

✅ **Event & Banquet Management**
- Event booking and management
- Venue management
- Event type categorization
- Catering integration
- Equipment and service charges
- Deposit and balance tracking

✅ **POS Integration**
- POS terminal management
- Sales transaction import
- Multiple sale types (dine-in, takeout, delivery, room service)
- Automatic accounting synchronization
- Server/staff tracking

✅ **Housekeeping Management**
- Room cleaning task assignment
- Inspection scheduling
- Task priority and status tracking
- Staff assignment
- Maintenance request integration

✅ **Revenue Management**
- Real-time dashboard metrics
- ADR, RevPAR, and occupancy tracking
- Revenue by department
- Comparative analysis tools

✅ **USALI-Compliant Reporting**
- Standard hotel/restaurant chart of accounts
- Department-level P&L statements
- Revenue center reporting
- Cost center expense tracking
- Industry-standard financial statements

---

## Installation & Setup

### Prerequisites

- WAMP/XAMPP/LAMP server with PHP 7.4+ and MySQL 5.7+
- Existing ATIERA Financial Management System installation
- Admin access to the system

### Setup Steps

1. **Apply Hotel/Restaurant Database Schema**

   Run the setup script from your browser or command line:

   ```bash
   # Via Browser (requires admin login):
   http://localhost/integ-capstone/setup_hotel_restaurant.php

   # Via Command Line:
   php setup_hotel_restaurant.php
   ```

   This script will:
   - Create all hotel/restaurant specific tables
   - Add USALI-compliant chart of accounts
   - Create hotel/restaurant permissions
   - Assign permissions to admin role
   - Insert sample department data

2. **Verify Installation**

   After running the setup script, verify that:
   - New tables appear in the `atiera_finance` database
   - Chart of accounts includes hotel/restaurant specific accounts (4000-5699 range)
   - New permissions are available in the roles management section

3. **Configure Departments**

   Log in as admin and configure your departments:
   - Navigate to Department Management
   - Review default departments (Rooms, F&B, Events, Spa, etc.)
   - Customize as needed for your property
   - Assign department managers

---

## Database Schema

### New Tables (40+ tables)

#### Department & Revenue Management
- `departments` - Revenue and cost center definitions
- `revenue_centers` - Detailed revenue tracking points

#### Room Operations
- `room_types` - Room type definitions with rates
- `rooms` - Physical room inventory
- `room_reservations` - Reservation tracking (PMS integration)
- `daily_occupancy` - Daily occupancy and revenue metrics

#### Inventory Management
- `inventory_categories` - Item categorization
- `inventory_items` - Master item list
- `inventory_transactions` - All inventory movements
- `purchase_orders` - PO management
- `purchase_order_items` - PO line items

#### Cashier Operations
- `cashier_sessions` - Daily cashier sessions
- `cashier_transactions` - All cashier transactions

#### Commission System
- `commission_rules` - Commission calculation rules
- `staff_commissions` - Commission records
- `commission_transactions` - Detailed commission breakdown

#### POS Integration
- `pos_terminals` - POS device registry
- `pos_sales` - Imported POS sales
- `pos_sale_items` - POS transaction details

#### Menu Management
- `menu_categories` - F&B menu categories
- `menu_items` - Menu item master
- `menu_item_ingredients` - Recipe costing

#### Events & Banquets
- `event_types` - Event classifications
- `event_venues` - Venue inventory
- `event_bookings` - Event reservations

#### Housekeeping
- `housekeeping_tasks` - Cleaning and maintenance tasks

---

## Module Descriptions

### 1. Department Management

**Purpose:** Organize operations into revenue and cost centers for better financial tracking.

**Key Features:**
- Create custom departments
- Assign department managers
- Link GL accounts to departments
- Track revenue and expenses by department

**Default Departments:**
- ROOMS - Rooms Division
- FB-REST - Restaurant
- FB-BAR - Bar
- FB-BANQ - Banquet & Events
- SPA - Spa & Wellness
- LAUNDRY - Laundry Services
- MAINT - Maintenance (cost center)
- ADMIN - Administration (cost center)

### 2. Room Management

**Purpose:** Track room inventory, rates, and occupancy.

**Key Features:**
- Define room types with base rates
- Maintain room inventory
- Track room status (available, occupied, cleaning, maintenance)
- Integration points for PMS systems

**Key Metrics:**
- **ADR (Average Daily Rate):** Total room revenue / Number of rooms sold
- **RevPAR (Revenue Per Available Room):** Total room revenue / Total available rooms
- **Occupancy %:** (Rooms occupied / Total available rooms) × 100

### 3. F&B Inventory Management

**Purpose:** Control food and beverage costs through detailed inventory tracking.

**Key Features:**
- Multi-category inventory support
- Automated reorder points
- Purchase order workflow
- Recipe costing with ingredients
- COGS calculation
- Waste and transfer tracking

**Inventory Categories:**
- Food
- Beverage
- Supplies
- Linen
- Amenities
- Other

### 4. Daily Cashier Reconciliation

**Purpose:** Ensure accurate cash handling and end-of-day reconciliation.

**Key Features:**
- Multi-shift support
- Opening/closing balance tracking
- Payment method breakdown
- Variance calculation and reporting
- Supervisor reconciliation workflow

**Workflow:**
1. Cashier opens session with opening balance
2. Transactions recorded throughout shift
3. Cashier closes session with closing balance
4. System calculates variance
5. Supervisor reviews and reconciles

### 5. Commission Tracking

**Purpose:** Automate staff commission calculation and payment.

**Commission Types:**
- **Percentage:** Fixed % of sales
- **Fixed:** Fixed amount per transaction/period
- **Tiered:** Different rates based on sales volume

**Features:**
- Flexible commission rules by department
- Automated calculation
- Approval workflow
- Payroll integration ready

### 6. Event & Banquet Management

**Purpose:** Manage event bookings, venues, and catering.

**Key Features:**
- Multiple venue management
- Event type categorization
- Capacity tracking
- Pricing (hourly/daily rates)
- Catering and equipment charges
- Deposit and final payment tracking

**Event Status Flow:**
Inquiry → Tentative → Confirmed → In Progress → Completed/Cancelled

### 7. POS Integration

**Purpose:** Import sales data from POS systems for accounting.

**Supported Sale Types:**
- Dine-in
- Takeout
- Delivery
- Room Service
- Event/Banquet

**Integration Flow:**
1. POS sales exported from POS system
2. Imported into `pos_sales` table
3. Automatically synced to accounting
4. Revenue posted to appropriate GL accounts
5. Inventory updated (if items are linked)

---

## Chart of Accounts (USALI)

### Revenue Accounts (4000-4399)

**Rooms Division (4001-4009)**
- 4001: Room Sales
- 4002: Room Service Revenue
- 4003: Mini Bar Revenue
- 4004: Internet/WiFi Revenue
- 4005-4009: Other room revenue

**Food & Beverage (4101-4110)**
- 4101: Restaurant Food Sales
- 4102: Restaurant Beverage Sales
- 4103: Bar Sales
- 4104: Banquet Food Sales
- 4105: Banquet Beverage Sales
- 4106-4107: Room Service F&B
- 4108: Catering Revenue
- 4109: Service Charges
- 4110: Cover Charges

**Other Operated Departments (4201-4209)**
- 4201: Spa Revenue
- 4202: Laundry Revenue
- 4203: Event Venue Rental
- 4204: A/V Equipment Rental
- 4205-4209: Other department revenue

**Miscellaneous Revenue (4301-4309)**
- 4301-4309: Other revenue sources

### Expense Accounts (5100-5699)

**Rooms Division Expenses (5101-5109)**
- Salaries, supplies, linen, commissions, etc.

**F&B Cost of Sales (5201-5208)**
- Cost of food and beverages by outlet

**F&B Expenses (5251-5262)**
- Salaries, supplies, licenses, uniforms, etc.

**Other Department Expenses (5301-5308)**
- Spa, laundry, events, business center

**Undistributed Operating Expenses**
- Admin & General (5401-5413)
- Sales & Marketing (5451-5461)
- Property Operations & Maintenance (5501-5512)
- Utilities (5551-5556)
- Fixed Charges (5601-5607)

### Asset Accounts (1101-1511)

**Inventory Assets (1101-1110)**
- Food, beverage, supplies, linen, spa products, etc.

**Fixed Assets (1501-1511)**
- Land, building, FF&E, kitchen equipment, vehicles, etc.

### Liability Accounts (2101-2109)

**Current Liabilities**
- Guest deposits, event deposits, service charges payable, tips payable, commission payable, etc.

---

## Role-Based Access Control

### New Permissions

The system includes 25+ hotel/restaurant specific permissions:

**Department Management**
- `departments.view` - View departments
- `departments.create` - Create departments
- `departments.edit` - Edit departments
- `departments.delete` - Delete departments

**Room Management**
- `rooms.view` - View room inventory
- `rooms.manage` - Manage rooms
- `reservations.view` - View reservations
- `reservations.create` - Create reservations
- `reservations.edit` - Edit reservations

**Inventory Management**
- `inventory.view` - View inventory
- `inventory.manage` - Manage inventory items
- `inventory.adjust` - Make adjustments
- `inventory.transfer` - Transfer inventory
- `purchase_orders.view` - View POs
- `purchase_orders.create` - Create POs
- `purchase_orders.approve` - Approve POs

**Cashier Operations**
- `cashier.operate` - Operate cashier terminal
- `cashier.reconcile` - Reconcile sessions
- `cashier.view_all` - View all sessions

**Commission Management**
- `commissions.view` - View commissions
- `commissions.calculate` - Calculate commissions
- `commissions.approve` - Approve payments

**Event Management**
- `events.view` - View events
- `events.create` - Create events
- `events.manage` - Manage events

**POS Operations**
- `pos.view` - View POS sales
- `pos.manage` - Manage POS settings

**Revenue Management**
- `revenue.view` - View revenue reports
- `revenue.analysis` - Access analysis tools

**Housekeeping**
- `housekeeping.view` - View tasks
- `housekeeping.manage` - Manage tasks

### Role Assignment

By default, all hotel/restaurant permissions are assigned to the **admin** role. To assign permissions to other roles:

1. Navigate to **Admin Panel → Roles & Permissions**
2. Select the role to modify
3. Add/remove permissions as needed
4. Save changes

**Recommended Role Setup:**

**Hotel Manager:**
- All view permissions
- Department, room, event, revenue permissions
- Commission approval
- Cashier reconciliation

**Accountant/Bookkeeper:**
- All view permissions
- Inventory management
- Purchase order creation
- Cashier reconciliation
- Commission calculation

**Front Desk Staff:**
- Rooms view
- Reservations create/edit
- Cashier operate

**Restaurant Manager:**
- F&B inventory permissions
- Menu management
- POS view
- Commission view

**Housekeeping Supervisor:**
- Housekeeping manage
- Room status update

---

## API Integration

### Integration Capabilities

The system is designed to integrate with third-party systems:

#### 1. Property Management System (PMS) Integration

**Integration Points:**
- Room reservations (`room_reservations` table)
- Guest folios
- Room status updates
- Rate management

**Recommended Approach:**
- Use webhooks to receive reservation updates from PMS
- API endpoints to sync room availability and rates
- Nightly batch import for reconciliation

#### 2. Point of Sale (POS) Integration

**Integration Points:**
- Sales transaction import (`pos_sales` table)
- Menu synchronization
- Inventory depletion

**Implementation:**
- Export sales from POS (CSV, JSON, XML)
- Import via batch script or API
- Automatic GL posting and inventory updates

**Example Integration Flow:**
```
POS System → Export Sales Data → Import to ATIERA →
Sync to Accounting → Update Inventory → Generate Reports
```

#### 3. Online Booking Engine Integration

**Integration Points:**
- Create reservations via API
- Update room availability
- Process payments

#### 4. Revenue Management System

**Integration Points:**
- Export ADR, RevPAR, occupancy data
- Rate recommendations
- Forecasting data

#### 5. Housekeeping System

**Integration Points:**
- Room status updates
- Task assignment
- Cleaning schedules

### API Endpoints (To Be Implemented)

```
POST   /api/v1/departments          - Create department
GET    /api/v1/departments/{id}     - Get department details
PUT    /api/v1/departments/{id}     - Update department
DELETE /api/v1/departments/{id}     - Delete department

POST   /api/v1/reservations         - Create reservation
GET    /api/v1/reservations/{id}    - Get reservation
PUT    /api/v1/reservations/{id}    - Update reservation
DELETE /api/v1/reservations/{id}    - Cancel reservation

POST   /api/v1/inventory/items      - Create inventory item
GET    /api/v1/inventory/items      - List inventory items
POST   /api/v1/inventory/adjust     - Adjust inventory
POST   /api/v1/inventory/transfer   - Transfer inventory

POST   /api/v1/pos/sales            - Import POS sales
GET    /api/v1/pos/sales            - List POS sales

POST   /api/v1/events               - Create event booking
GET    /api/v1/events/{id}          - Get event details
PUT    /api/v1/events/{id}          - Update event

GET    /api/v1/revenue/dashboard    - Get revenue metrics
GET    /api/v1/revenue/occupancy    - Get occupancy data
GET    /api/v1/revenue/adr          - Get ADR metrics
GET    /api/v1/revenue/revpar       - Get RevPAR metrics
```

### Webhook Support (To Be Implemented)

The system will support webhooks for real-time event notifications:

**Supported Events:**
- `reservation.created`
- `reservation.updated`
- `reservation.cancelled`
- `room.status_changed`
- `invoice.created`
- `payment.received`
- `inventory.low_stock`
- `cashier.session_closed`

---

## Responsive Design

### Mobile-First Approach

The system is fully responsive across all devices:

**Breakpoints:**
- Mobile: < 576px
- Tablet: 576px - 768px
- Desktop: 768px - 992px
- Large Desktop: > 992px

### Key Responsive Features:

✅ **Adaptive Sidebar**
- Collapsible on mobile
- Touch-friendly navigation
- Swipe gestures supported

✅ **Responsive Tables**
- Horizontal scrolling on mobile
- Stacking layout for very small screens
- Data labels on mobile view

✅ **Mobile-Optimized Forms**
- Full-width inputs on mobile
- Larger tap targets (44x44px minimum)
- Stacked buttons
- Simplified layouts

✅ **Dashboard Adaptations**
- Single column metrics on mobile
- Two columns on tablets
- Full grid on desktop
- Responsive charts

✅ **Touch-Friendly Interactions**
- Minimum 44px tap targets
- Swipe support for navigation
- Mobile-optimized dropdowns

### Utility Classes

Use these responsive utility classes in your HTML:

```html
<!-- Hide on mobile -->
<div class="d-mobile-none">Desktop only content</div>

<!-- Show only on mobile -->
<div class="responsive-show-mobile">Mobile only content</div>

<!-- Full width on mobile -->
<button class="btn w-mobile-100">Full Width Button</button>

<!-- Stack table on mobile -->
<table class="table table-mobile-stack">...</table>

<!-- Flex column on mobile -->
<div class="flex-mobile-column">...</div>
```

---

## Usage Instructions

### Getting Started

1. **Initial Setup**
   - Run `setup_hotel_restaurant.php`
   - Log in as admin
   - Navigate to Dashboard

2. **Configure Departments**
   - Go to Department Management
   - Review default departments
   - Add custom departments as needed
   - Assign managers

3. **Set Up Inventory**
   - Create inventory categories
   - Add inventory items
   - Set reorder points
   - Configure COGS accounts

4. **Configure Rooms**
   - Define room types with rates
   - Add room inventory
   - Set initial room status

5. **Set Up Menus**
   - Create menu categories
   - Add menu items
   - Link ingredients for recipe costing

6. **Configure Commissions**
   - Create commission rules
   - Assign rules to departments/staff
   - Set calculation parameters

7. **Set Up POS Terminals**
   - Register POS devices
   - Assign to departments
   - Configure sync settings

### Daily Operations

**Front Desk:**
- Check-in/check-out guests
- Create room charges
- Process payments

**Restaurant/Bar:**
- Process POS sales
- Update inventory
- Track daily revenue

**Cashier:**
- Open cashier session
- Record transactions
- Close and reconcile session

**Housekeeping:**
- Receive room cleaning assignments
- Update room status
- Report maintenance issues

**Management:**
- Review daily dashboard
- Monitor KPIs (ADR, RevPAR, occupancy)
- Approve purchase orders
- Review variances

### End-of-Day Procedures

1. Close all cashier sessions
2. Reconcile cash and card transactions
3. Import POS sales (if not real-time)
4. Update room occupancy
5. Review daily revenue by department
6. Generate daily flash report

### End-of-Month Procedures

1. Complete inventory count
2. Calculate inventory variance
3. Calculate and approve staff commissions
4. Generate P&L by department (USALI format)
5. Review budget vs. actual
6. Close accounting period

---

## Future Enhancements

### Planned Features

1. **Full Module UI Implementation**
   - Department management UI
   - Inventory management UI
   - Cashier reconciliation UI
   - Commission tracking UI
   - Revenue dashboard UI

2. **Enhanced Reporting**
   - USALI-compliant financial statements
   - Department P&L statements
   - Labor cost analysis
   - Food cost reports
   - Daily flash reports

3. **Advanced Integrations**
   - Direct PMS integration (Opera, Protel, etc.)
   - POS integration (Micros, Aloha, etc.)
   - Online booking engines
   - Channel managers
   - Payment gateways

4. **Analytics & Business Intelligence**
   - Predictive occupancy forecasting
   - Dynamic pricing recommendations
   - Food cost trend analysis
   - Labor optimization
   - Guest behavior analytics

5. **Mobile Apps**
   - Manager mobile dashboard
   - Staff clock-in/out
   - Housekeeping task management
   - Inventory counting app

6. **Additional Modules**
   - Maintenance management
   - Asset management
   - Time & attendance
   - Payroll integration
   - Guest loyalty program

---

## Support & Documentation

### System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.0+)
- Apache/Nginx web server
- 100MB minimum disk space for database

### Browser Compatibility

- Chrome (recommended)
- Firefox
- Safari
- Edge
- Mobile browsers (iOS Safari, Chrome Mobile)

### Getting Help

For issues or questions:
1. Review this documentation
2. Check the main [README.md](README.md)
3. Review API documentation in [API_README.md](API_README.md)
4. Check database schema in `hotel_restaurant_schema.sql`

### Best Practices

1. **Regular Backups**
   - Enable automated database backups
   - Store backups off-site
   - Test restore procedures

2. **Security**
   - Use HTTPS in production
   - Change default admin password
   - Implement strong password policies
   - Enable two-factor authentication
   - Regular security audits

3. **Performance**
   - Optimize database indexes
   - Monitor query performance
   - Cache frequently accessed data
   - Regular database maintenance

4. **Training**
   - Train staff on their specific modules
   - Document custom workflows
   - Regular refresher training

5. **Data Quality**
   - Validate data entry
   - Regular reconciliations
   - Monthly audits
   - Clean up old data periodically

---

## License

MIT License - See [README.md](README.md) for details

---

## Changelog

### Version 1.1.0 - Hotel & Restaurant Edition

**Added:**
- 40+ new database tables for hotel/restaurant operations
- USALI-compliant chart of accounts (150+ new accounts)
- Department management system
- Room management and occupancy tracking
- F&B inventory management
- POS integration framework
- Cashier reconciliation system
- Commission tracking system
- Event & banquet management
- Housekeeping management
- 25+ new permissions for role-based access
- Comprehensive responsive CSS
- Mobile-first design
- Setup automation script
- Complete documentation

**Enhanced:**
- Existing financial modules for multi-department support
- Reporting framework for department-level P&L
- API infrastructure for third-party integrations
- User interface responsiveness
- Security and access control

---

## Credits

ATIERA Financial Management System - Hotel & Restaurant Edition
Developed for capstone project - BSIT 4101 Cluster 1

**Contributors:**
- System Architecture & Database Design
- Backend Development
- Frontend Development & UI/UX
- Documentation

**Special Thanks:**
- Hospitality industry advisors
- USALI standards committee
- Open-source community

---

*Last Updated: 2025*
