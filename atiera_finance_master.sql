-- ========================================================================================================
-- ATIERA FINANCIAL MANAGEMENT SYSTEM - MASTER INSTALLATION SCRIPT
-- Complete Hotel & Restaurant Financial Management System
-- BSIT 4101 Cluster 1 - Capstone Project
-- ========================================================================================================
--
-- SCOPE: Financial tracking, reporting, and consolidation for hotel & restaurant operations
-- This system RECEIVES data from operational systems and provides centralized financial management
--
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin
-- 2. Click "Import" tab
-- 3. Choose this file (atiera_finance_master.sql)
-- 4. Click "Go" to execute
-- 5. Default login: username=admin, password=admin123
--
-- ========================================================================================================

-- ========================================================================================================
-- SECTION 1: CORE DATABASE SCHEMA
-- Base tables for users, authentication, chart of accounts, AR/AP, and system features
-- ========================================================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS atiera_finance;
USE atiera_finance;

-- Users table (replaces hardcoded authentication)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    department VARCHAR(100),
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'staff') NOT NULL DEFAULT 'staff',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    profile_visibility ENUM('public', 'private', 'team') DEFAULT 'private',
    activity_visibility ENUM('public', 'private', 'team') DEFAULT 'private',
    data_sharing BOOLEAN DEFAULT FALSE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password_hash, email, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@atiera.com', 'System Administrator', 'admin');

-- Insert default staff user (password: staff123)
INSERT INTO users (username, password_hash, email, full_name, role) VALUES
('staff', '$2y$12$jTfU.T/XvbvjgG0OQ.2/quXAtFLiyFdwz0qURlac9J/69SfdIs9MG', 'staff@atiera.com', 'Staff Member', 'staff');

-- User Preferences table
CREATE TABLE user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'Asia/Manila',
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    dashboard_layout VARCHAR(20) DEFAULT 'default',
    items_per_page INT DEFAULT 10,
    date_format VARCHAR(20) DEFAULT 'M j, Y',
    currency VARCHAR(10) DEFAULT 'PHP',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tasks table
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    due_date DATE,
    assigned_to INT,
    assigned_by INT,
    created_by INT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Chart of Accounts
CREATE TABLE chart_of_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_code VARCHAR(20) UNIQUE NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    category VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert basic chart of accounts (USALI-specific accounts added in Section 2)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('1001', 'Cash', 'asset', 'Current Assets', 'Cash on hand and in bank'),
('1002', 'Accounts Receivable', 'asset', 'Current Assets', 'Money owed by customers'),
('2001', 'Accounts Payable', 'liability', 'Current Liabilities', 'Money owed to vendors'),
('3001', 'Owner''s Equity', 'equity', 'Equity', 'Owner''s investment in business');

-- Customers table (for Accounts Receivable)
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_code VARCHAR(20) UNIQUE NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    credit_limit DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vendors table (for Accounts Payable)
CREATE TABLE vendors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_code VARCHAR(20) UNIQUE NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    payment_terms VARCHAR(50) DEFAULT 'Net 30',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Invoices table (Accounts Receivable)
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    balance DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Invoice items
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    line_total DECIMAL(15,2) NOT NULL,
    account_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- Bills (Accounts Payable)
CREATE TABLE bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_number VARCHAR(20) UNIQUE NOT NULL,
    vendor_id INT NOT NULL,
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    balance DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft', 'approved', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    created_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Bill items
CREATE TABLE bill_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    line_total DECIMAL(15,2) NOT NULL,
    account_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- Payments received (Collections - Accounts Receivable & Supplier Refunds/Credits)
CREATE TABLE payments_received (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NULL,
    vendor_id INT NULL,
    invoice_id INT NULL,
    bill_id INT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'refund', 'credit', 'other') NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Payments made (Disbursements - Accounts Payable)
CREATE TABLE payments_made (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    vendor_id INT NOT NULL,
    bill_id INT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    approved_by INT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Adjustments (Credit Memos, Debit Memos, etc.)
CREATE TABLE adjustments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    adjustment_number VARCHAR(20) UNIQUE NOT NULL,
    adjustment_type ENUM('credit_memo', 'debit_memo', 'write_off', 'discount') NOT NULL,
    customer_id INT NULL,
    vendor_id INT NULL,
    invoice_id INT NULL,
    bill_id INT NULL,
    amount DECIMAL(15,2) NOT NULL,
    reason TEXT NOT NULL,
    adjustment_date DATE NOT NULL,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Journal Entries (General Ledger)
CREATE TABLE journal_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entry_number VARCHAR(20) UNIQUE NOT NULL,
    entry_date DATE NOT NULL,
    description TEXT NOT NULL,
    reference VARCHAR(100),
    total_debit DECIMAL(15,2) DEFAULT 0,
    total_credit DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft', 'posted', 'voided') DEFAULT 'draft',
    created_by INT,
    posted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    posted_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (posted_by) REFERENCES users(id)
);

-- Journal Entry Lines
CREATE TABLE journal_entry_lines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0,
    credit DECIMAL(15,2) DEFAULT 0,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- Budget Categories
CREATE TABLE budget_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_code VARCHAR(20) UNIQUE NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    category_type ENUM('revenue', 'expense') NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Budgets
CREATE TABLE budgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_year YEAR NOT NULL,
    budget_name VARCHAR(100) NOT NULL,
    description TEXT,
    total_budgeted DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft', 'approved', 'active', 'closed') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Budget Items
CREATE TABLE budget_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_id INT NOT NULL,
    category_id INT NOT NULL,
    account_id INT NULL,
    budgeted_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    actual_amount DECIMAL(15,2) DEFAULT 0,
    variance DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id),
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- Disbursements (Cash/Bank payments)
CREATE TABLE disbursements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disbursement_number VARCHAR(20) UNIQUE NOT NULL,
    disbursement_date DATE NOT NULL,
    payee VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'other') NOT NULL,
    reference_number VARCHAR(100),
    purpose TEXT NOT NULL,
    account_id INT NOT NULL,
    approved_by INT,
    recorded_by INT,
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Reports table (for saved/custom reports)
CREATE TABLE saved_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_name VARCHAR(100) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    parameters JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Notification Log
CREATE TABLE notification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('email', 'sms') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Uploaded Files
CREATE TABLE uploaded_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'documents',
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Roles and Permissions (RBAC)
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    module VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_user_role (user_id, role_id)
);

CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);

-- Audit Log
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Backup System
CREATE TABLE backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('database', 'filesystem', 'full') NOT NULL,
    name VARCHAR(100) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    size_bytes BIGINT NOT NULL,
    status ENUM('completed', 'failed', 'in_progress') DEFAULT 'completed',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE backup_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('database', 'filesystem', 'full') NOT NULL,
    frequency INT NOT NULL COMMENT 'Frequency in minutes',
    scheduled_time TIME,
    is_active BOOLEAN DEFAULT TRUE,
    last_run TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Advanced Search System
CREATE TABLE saved_searches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    query VARCHAR(255),
    filters JSON,
    tables JSON,
    user_id INT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE search_queries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    query VARCHAR(255),
    filters JSON,
    tables JSON,
    result_count INT DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- External API Integrations
CREATE TABLE api_integrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE integration_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    integration_name VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    status ENUM('success', 'error', 'warning') DEFAULT 'success',
    message TEXT,
    request_data JSON,
    response_data JSON,
    executed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (executed_by) REFERENCES users(id)
);

-- Two-Factor Authentication
CREATE TABLE user_2fa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    method ENUM('totp', 'sms', 'backup_code') NOT NULL,
    secret VARCHAR(255), -- For TOTP
    backup_codes JSON, -- Encrypted backup codes
    phone_number VARCHAR(20), -- For SMS
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    disabled_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE sms_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_code (user_id, code)
);

CREATE TABLE twofa_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    method ENUM('totp', 'sms', 'backup_code') NOT NULL,
    success BOOLEAN NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Dashboard Customization
CREATE TABLE user_dashboards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    layout_config JSON NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE user_bookmarks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    icon VARCHAR(50),
    category VARCHAR(50) DEFAULT 'general',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_bookmark (user_id, url(249))
);

-- Workflow Automation
CREATE TABLE workflows (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    definition JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE workflow_instances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workflow_id INT NOT NULL,
    trigger_data JSON,
    status ENUM('running', 'completed', 'failed', 'cancelled') DEFAULT 'running',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE
);

CREATE TABLE workflow_steps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instance_id INT NOT NULL,
    step_index INT NOT NULL,
    step_name VARCHAR(100) NOT NULL,
    step_type ENUM('approval', 'action', 'notification', 'delay') NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed', 'timed_out', 'scheduled') DEFAULT 'pending',
    related_task_id INT NULL,
    scheduled_at TIMESTAMP NULL,
    timeout_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_id) REFERENCES workflow_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (related_task_id) REFERENCES tasks(id)
);

-- Insert predefined workflows
INSERT INTO workflows (name, description, definition, created_by) VALUES
('Invoice Approval Workflow', 'Multi-step approval process for invoices over threshold', '{
  "trigger": "invoice.created",
  "conditions": [{"field": "total_amount", "operator": ">", "value": 50000}],
  "steps": [
    {
      "name": "Manager Approval",
      "type": "approval",
      "assignee_role": "manager",
      "timeout_hours": 24,
      "actions": {
        "approve": {"status": "approved", "notification": "Invoice approved by manager"},
        "reject": {"status": "rejected", "notification": "Invoice rejected by manager"}
      }
    },
    {
      "name": "Finance Review",
      "type": "approval",
      "assignee_role": "finance",
      "timeout_hours": 48,
      "actions": {
        "approve": {"status": "approved", "notification": "Invoice approved by finance"},
        "reject": {"status": "rejected", "notification": "Invoice rejected by finance"}
      }
    }
  ]
}', 1),

('Bill Payment Workflow', 'Automated bill payment processing', '{
  "trigger": "bill.approved",
  "conditions": [],
  "steps": [
    {
      "name": "Schedule Payment",
      "type": "action",
      "action": "schedule_payment",
      "delay_days": 0,
      "conditions": [{"field": "payment_terms", "operator": "==", "value": "Net 30"}]
    },
    {
      "name": "Payment Reminder",
      "type": "notification",
      "template": "payment_reminder",
      "delay_days": 25,
      "recipients": ["finance_team"]
    }
  ]
}', 1),

('Overdue Invoice Management', 'Automated handling of overdue invoices', '{
  "trigger": "invoice.overdue",
  "conditions": [],
  "steps": [
    {
      "name": "Send Overdue Notice",
      "type": "notification",
      "template": "overdue_notice",
      "recipients": ["customer"]
    },
    {
      "name": "Escalate to Collections",
      "type": "action",
      "action": "create_task",
      "delay_days": 7,
      "assignee_role": "collections",
      "task_title": "Follow up on overdue invoice",
      "task_priority": "high"
    }
  ]
}', 1);

-- Indexes for better performance
CREATE INDEX idx_invoices_customer_id ON invoices(customer_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);
CREATE INDEX idx_payments_received_customer_id ON payments_received(customer_id);
CREATE INDEX idx_payments_received_invoice_id ON payments_received(invoice_id);
CREATE INDEX idx_bills_vendor_id ON bills(vendor_id);
CREATE INDEX idx_bills_status ON bills(status);
CREATE INDEX idx_bills_due_date ON bills(due_date);
CREATE INDEX idx_payments_made_vendor_id ON payments_made(vendor_id);
CREATE INDEX idx_payments_made_bill_id ON payments_made(bill_id);
CREATE INDEX idx_journal_entries_entry_date ON journal_entries(entry_date);
CREATE INDEX idx_journal_entries_status ON journal_entries(status);
CREATE INDEX idx_journal_entry_lines_account_id ON journal_entry_lines(account_id);
CREATE INDEX idx_adjustments_customer_id ON adjustments(customer_id);
CREATE INDEX idx_adjustments_vendor_id ON adjustments(vendor_id);

-- ========================================================================================================
-- SECTION 2: HOTEL & RESTAURANT CHART OF ACCOUNTS (USALI)
-- Uniform System of Accounts for the Lodging Industry - 150+ specialized accounts
-- ========================================================================================================

-- REVENUE ACCOUNTS

-- ROOMS DIVISION REVENUE (4000-4099)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('4001', 'Room Sales', 'revenue', 'Rooms Division', 'Revenue from room rentals'),
('4002', 'Room Service Revenue', 'revenue', 'Rooms Division', 'Revenue from in-room dining'),
('4003', 'Mini Bar Revenue', 'revenue', 'Rooms Division', 'Revenue from mini bar sales'),
('4004', 'Internet/WiFi Revenue', 'revenue', 'Rooms Division', 'Revenue from internet services'),
('4005', 'Other Room Revenue', 'revenue', 'Rooms Division', 'Other miscellaneous room revenue'),
('4006', 'Parking Revenue', 'revenue', 'Rooms Division', 'Revenue from parking services'),
('4007', 'Early Check-in Fees', 'revenue', 'Rooms Division', 'Revenue from early check-in charges'),
('4008', 'Late Check-out Fees', 'revenue', 'Rooms Division', 'Revenue from late check-out charges'),
('4009', 'Cancellation Fees', 'revenue', 'Rooms Division', 'Revenue from reservation cancellations');

-- FOOD & BEVERAGE REVENUE (4100-4199)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('4101', 'Restaurant Food Sales', 'revenue', 'Food & Beverage', 'Revenue from restaurant food sales'),
('4102', 'Restaurant Beverage Sales', 'revenue', 'Food & Beverage', 'Revenue from restaurant beverage sales'),
('4103', 'Bar Sales', 'revenue', 'Food & Beverage', 'Revenue from bar operations'),
('4104', 'Banquet Food Sales', 'revenue', 'Food & Beverage', 'Revenue from banquet food'),
('4105', 'Banquet Beverage Sales', 'revenue', 'Food & Beverage', 'Revenue from banquet beverages'),
('4106', 'Room Service Food', 'revenue', 'Food & Beverage', 'Revenue from room service food'),
('4107', 'Room Service Beverage', 'revenue', 'Food & Beverage', 'Revenue from room service beverages'),
('4108', 'Catering Revenue', 'revenue', 'Food & Beverage', 'Revenue from catering services'),
('4109', 'Service Charges - F&B', 'revenue', 'Food & Beverage', 'Service charges from F&B operations'),
('4110', 'Cover Charges', 'revenue', 'Food & Beverage', 'Cover charges from F&B outlets');

-- OTHER OPERATING DEPARTMENTS REVENUE (4200-4299)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('4201', 'Spa Revenue', 'revenue', 'Other Operated Departments', 'Revenue from spa services'),
('4202', 'Laundry Revenue', 'revenue', 'Other Operated Departments', 'Revenue from guest laundry services'),
('4203', 'Event Venue Rental', 'revenue', 'Other Operated Departments', 'Revenue from event venue rentals'),
('4204', 'Audio/Visual Equipment Rental', 'revenue', 'Other Operated Departments', 'Revenue from A/V equipment rentals'),
('4205', 'Business Center Revenue', 'revenue', 'Other Operated Departments', 'Revenue from business center services'),
('4206', 'Fitness Center Revenue', 'revenue', 'Other Operated Departments', 'Revenue from fitness center'),
('4207', 'Gift Shop Revenue', 'revenue', 'Other Operated Departments', 'Revenue from gift shop sales'),
('4208', 'Transportation Revenue', 'revenue', 'Other Operated Departments', 'Revenue from transportation services'),
('4209', 'Event Coordination Fees', 'revenue', 'Other Operated Departments', 'Revenue from event planning services');

-- MISCELLANEOUS REVENUE (4300-4399)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('4301', 'Telephone Revenue', 'revenue', 'Miscellaneous Revenue', 'Revenue from telephone services'),
('4302', 'Fax/Photocopy Revenue', 'revenue', 'Miscellaneous Revenue', 'Revenue from fax and photocopy services'),
('4303', 'Safe Deposit Box Fees', 'revenue', 'Miscellaneous Revenue', 'Revenue from safe deposit boxes'),
('4304', 'Pet Fees', 'revenue', 'Miscellaneous Revenue', 'Revenue from pet charges'),
('4305', 'Damage/Breakage Charges', 'revenue', 'Miscellaneous Revenue', 'Revenue from damage charges'),
('4306', 'Lost Key Charges', 'revenue', 'Miscellaneous Revenue', 'Revenue from lost key charges'),
('4307', 'No-Show Charges', 'revenue', 'Miscellaneous Revenue', 'Revenue from no-show penalties'),
('4308', 'Commission Income', 'revenue', 'Miscellaneous Revenue', 'Commission earned from third parties'),
('4309', 'Other Miscellaneous Revenue', 'revenue', 'Miscellaneous Revenue', 'Other miscellaneous revenue');

-- COST OF SALES / DIRECT EXPENSES

-- ROOMS DIVISION EXPENSES (5100-5199)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5101', 'Rooms - Salaries & Wages', 'expense', 'Rooms Division Expenses', 'Salaries and wages for rooms division staff'),
('5102', 'Rooms - Payroll Taxes & Benefits', 'expense', 'Rooms Division Expenses', 'Payroll taxes and employee benefits'),
('5103', 'Rooms - Guest Supplies', 'expense', 'Rooms Division Expenses', 'Cost of guest room supplies and amenities'),
('5104', 'Rooms - Linen & Terry', 'expense', 'Rooms Division Expenses', 'Cost of linens, towels, and robes'),
('5105', 'Rooms - Laundry & Dry Cleaning', 'expense', 'Rooms Division Expenses', 'Cost of laundry services for rooms'),
('5106', 'Rooms - Reservation System', 'expense', 'Rooms Division Expenses', 'Reservation system fees and costs'),
('5107', 'Rooms - Commissions', 'expense', 'Rooms Division Expenses', 'Travel agent and OTA commissions'),
('5108', 'Rooms - Uniforms', 'expense', 'Rooms Division Expenses', 'Cost of staff uniforms'),
('5109', 'Rooms - Other Expenses', 'expense', 'Rooms Division Expenses', 'Other rooms division expenses');

-- FOOD & BEVERAGE COST OF SALES (5200-5249)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5201', 'Cost of Food - Restaurant', 'expense', 'F&B Cost of Sales', 'Cost of food sold in restaurant'),
('5202', 'Cost of Beverage - Restaurant', 'expense', 'F&B Cost of Sales', 'Cost of beverages sold in restaurant'),
('5203', 'Cost of Food - Banquet', 'expense', 'F&B Cost of Sales', 'Cost of food for banquets'),
('5204', 'Cost of Beverage - Banquet', 'expense', 'F&B Cost of Sales', 'Cost of beverages for banquets'),
('5205', 'Cost of Food - Room Service', 'expense', 'F&B Cost of Sales', 'Cost of food for room service'),
('5206', 'Cost of Beverage - Room Service', 'expense', 'F&B Cost of Sales', 'Cost of beverages for room service'),
('5207', 'Cost of Food - Bar', 'expense', 'F&B Cost of Sales', 'Cost of bar food'),
('5208', 'Cost of Beverage - Bar', 'expense', 'F&B Cost of Sales', 'Cost of bar beverages');

-- FOOD & BEVERAGE EXPENSES (5250-5299)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5251', 'F&B - Salaries & Wages', 'expense', 'F&B Expenses', 'Salaries and wages for F&B staff'),
('5252', 'F&B - Payroll Taxes & Benefits', 'expense', 'F&B Expenses', 'Payroll taxes and employee benefits'),
('5253', 'F&B - Kitchen Supplies', 'expense', 'F&B Expenses', 'Kitchen supplies and small equipment'),
('5254', 'F&B - China, Glassware, Silverware', 'expense', 'F&B Expenses', 'Replacement of serviceware'),
('5255', 'F&B - Kitchen Fuel', 'expense', 'F&B Expenses', 'Gas and fuel for cooking'),
('5256', 'F&B - Laundry & Dry Cleaning', 'expense', 'F&B Expenses', 'Laundry for F&B linens'),
('5257', 'F&B - Licenses & Permits', 'expense', 'F&B Expenses', 'Food service and liquor licenses'),
('5258', 'F&B - Menus & Beverage Lists', 'expense', 'F&B Expenses', 'Printing of menus and wine lists'),
('5259', 'F&B - Uniforms', 'expense', 'F&B Expenses', 'Cost of F&B staff uniforms'),
('5260', 'F&B - Contract Services', 'expense', 'F&B Expenses', 'Contracted F&B services'),
('5261', 'F&B - Music & Entertainment', 'expense', 'F&B Expenses', 'Entertainment expenses'),
('5262', 'F&B - Other Expenses', 'expense', 'F&B Expenses', 'Other F&B expenses');

-- OTHER DEPARTMENT EXPENSES (5300-5399)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5301', 'Spa - Salaries & Wages', 'expense', 'Other Dept Expenses', 'Spa staff salaries'),
('5302', 'Spa - Supplies & Products', 'expense', 'Other Dept Expenses', 'Spa supplies and products'),
('5303', 'Spa - Commissions', 'expense', 'Other Dept Expenses', 'Spa staff commissions'),
('5304', 'Laundry - Salaries & Wages', 'expense', 'Other Dept Expenses', 'Laundry staff salaries'),
('5305', 'Laundry - Supplies', 'expense', 'Other Dept Expenses', 'Laundry detergents and supplies'),
('5306', 'Event Services - Salaries', 'expense', 'Other Dept Expenses', 'Event staff salaries'),
('5307', 'Event Services - Supplies', 'expense', 'Other Dept Expenses', 'Event supplies and decorations'),
('5308', 'Business Center - Supplies', 'expense', 'Other Dept Expenses', 'Business center supplies');

-- UNDISTRIBUTED OPERATING EXPENSES

-- ADMINISTRATIVE & GENERAL (5400-5449)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5401', 'Administrative Salaries', 'expense', 'Administrative & General', 'Management and admin salaries'),
('5402', 'Accounting Salaries', 'expense', 'Administrative & General', 'Accounting department salaries'),
('5403', 'Office Supplies', 'expense', 'Administrative & General', 'General office supplies'),
('5404', 'Postage & Shipping', 'expense', 'Administrative & General', 'Postage and courier expenses'),
('5405', 'Printing & Stationery', 'expense', 'Administrative & General', 'Printing and stationery costs'),
('5406', 'Professional Fees', 'expense', 'Administrative & General', 'Legal, audit, and consulting fees'),
('5407', 'Bank Charges', 'expense', 'Administrative & General', 'Bank service charges'),
('5408', 'Credit Card Fees', 'expense', 'Administrative & General', 'Credit card processing fees'),
('5409', 'Bad Debt Expense', 'expense', 'Administrative & General', 'Uncollectible accounts'),
('5410', 'Security Services', 'expense', 'Administrative & General', 'Security guard services'),
('5411', 'Insurance - General', 'expense', 'Administrative & General', 'General liability insurance'),
('5412', 'Software & Licenses', 'expense', 'Administrative & General', 'Software licensing fees'),
('5413', 'Training & Development', 'expense', 'Administrative & General', 'Employee training costs');

-- SALES & MARKETING (5450-5499)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5451', 'Marketing Salaries', 'expense', 'Sales & Marketing', 'Marketing department salaries'),
('5452', 'Advertising - Print', 'expense', 'Sales & Marketing', 'Print advertising costs'),
('5453', 'Advertising - Digital', 'expense', 'Sales & Marketing', 'Online and digital advertising'),
('5454', 'Advertising - Radio/TV', 'expense', 'Sales & Marketing', 'Radio and television advertising'),
('5455', 'Website Maintenance', 'expense', 'Sales & Marketing', 'Website hosting and maintenance'),
('5456', 'Online Booking Fees', 'expense', 'Sales & Marketing', 'OTA and booking platform fees'),
('5457', 'Promotional Materials', 'expense', 'Sales & Marketing', 'Brochures, flyers, promotional items'),
('5458', 'Public Relations', 'expense', 'Sales & Marketing', 'PR services and events'),
('5459', 'Travel Agent Commissions', 'expense', 'Sales & Marketing', 'Commissions to travel agents'),
('5460', 'Trade Shows & Events', 'expense', 'Sales & Marketing', 'Participation in trade shows'),
('5461', 'Photography & Videography', 'expense', 'Sales & Marketing', 'Professional photo/video services');

-- PROPERTY OPERATIONS & MAINTENANCE (5500-5549)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5501', 'Engineering Salaries', 'expense', 'Property O&M', 'Engineering and maintenance salaries'),
('5502', 'Repairs & Maintenance - Building', 'expense', 'Property O&M', 'Building repair costs'),
('5503', 'Repairs & Maintenance - HVAC', 'expense', 'Property O&M', 'HVAC system maintenance'),
('5504', 'Repairs & Maintenance - Plumbing', 'expense', 'Property O&M', 'Plumbing repairs'),
('5505', 'Repairs & Maintenance - Electrical', 'expense', 'Property O&M', 'Electrical repairs'),
('5506', 'Repairs & Maintenance - Equipment', 'expense', 'Property O&M', 'Equipment maintenance'),
('5507', 'Repairs & Maintenance - Furniture', 'expense', 'Property O&M', 'Furniture repairs'),
('5508', 'Landscaping & Grounds', 'expense', 'Property O&M', 'Landscape maintenance'),
('5509', 'Swimming Pool Maintenance', 'expense', 'Property O&M', 'Pool maintenance and chemicals'),
('5510', 'Pest Control', 'expense', 'Property O&M', 'Pest control services'),
('5511', 'Elevator Maintenance', 'expense', 'Property O&M', 'Elevator service contracts'),
('5512', 'Fire Safety Systems', 'expense', 'Property O&M', 'Fire alarm and sprinkler maintenance');

-- UTILITIES (5550-5599)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5551', 'Electricity', 'expense', 'Utilities', 'Electrical power costs'),
('5552', 'Water & Sewer', 'expense', 'Utilities', 'Water and sewage charges'),
('5553', 'Gas', 'expense', 'Utilities', 'Natural gas expenses'),
('5554', 'Telephone & Internet', 'expense', 'Utilities', 'Communication services'),
('5555', 'Cable/Satellite TV', 'expense', 'Utilities', 'Guest television services'),
('5556', 'Waste Removal', 'expense', 'Utilities', 'Garbage and waste disposal');

-- FIXED CHARGES (5600-5699)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('5601', 'Property Taxes', 'expense', 'Fixed Charges', 'Real estate taxes'),
('5602', 'Business License Fees', 'expense', 'Fixed Charges', 'Business operating licenses'),
('5603', 'Insurance - Property', 'expense', 'Fixed Charges', 'Property insurance'),
('5604', 'Insurance - Liability', 'expense', 'Fixed Charges', 'Liability insurance'),
('5605', 'Insurance - Workers Comp', 'expense', 'Fixed Charges', 'Workers compensation insurance'),
('5606', 'Rent Expense', 'expense', 'Fixed Charges', 'Rent for leased property'),
('5607', 'Depreciation', 'expense', 'Fixed Charges', 'Depreciation expense');

-- ASSET ACCOUNTS (Hotel/Restaurant Specific)

-- CURRENT ASSETS (1100-1199)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('1101', 'Food Inventory', 'asset', 'Current Assets', 'Value of food inventory on hand'),
('1102', 'Beverage Inventory', 'asset', 'Current Assets', 'Value of beverage inventory'),
('1103', 'Guest Supplies Inventory', 'asset', 'Current Assets', 'Value of guest room supplies'),
('1104', 'Linen Inventory', 'asset', 'Current Assets', 'Value of linens and towels'),
('1105', 'China & Glassware Inventory', 'asset', 'Current Assets', 'Value of serviceware inventory'),
('1106', 'Cleaning Supplies Inventory', 'asset', 'Current Assets', 'Value of cleaning supplies'),
('1107', 'Spa Products Inventory', 'asset', 'Current Assets', 'Value of spa products'),
('1108', 'Gift Shop Inventory', 'asset', 'Current Assets', 'Value of gift shop merchandise'),
('1109', 'Prepaid Insurance', 'asset', 'Current Assets', 'Prepaid insurance premiums'),
('1110', 'Prepaid Licenses', 'asset', 'Current Assets', 'Prepaid licenses and permits');

-- FIXED ASSETS (1500-1699)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('1501', 'Land', 'asset', 'Fixed Assets', 'Land and property'),
('1502', 'Building', 'asset', 'Fixed Assets', 'Building and structures'),
('1503', 'Accumulated Depreciation - Building', 'asset', 'Fixed Assets', 'Accumulated building depreciation'),
('1504', 'Furniture & Fixtures', 'asset', 'Fixed Assets', 'Furniture and fixtures'),
('1505', 'Accumulated Depreciation - FF&E', 'asset', 'Fixed Assets', 'Accumulated FF&E depreciation'),
('1506', 'Kitchen Equipment', 'asset', 'Fixed Assets', 'Kitchen and food service equipment'),
('1507', 'HVAC Equipment', 'asset', 'Fixed Assets', 'Heating and cooling systems'),
('1508', 'Computer Equipment', 'asset', 'Fixed Assets', 'Computer hardware and systems'),
('1509', 'POS Equipment', 'asset', 'Fixed Assets', 'Point of sale systems'),
('1510', 'Vehicles', 'asset', 'Fixed Assets', 'Company vehicles'),
('1511', 'Leasehold Improvements', 'asset', 'Fixed Assets', 'Improvements to leased property');

-- LIABILITY ACCOUNTS (Hotel/Restaurant Specific)

-- CURRENT LIABILITIES (2100-2199)
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('2101', 'Guest Deposits', 'liability', 'Current Liabilities', 'Advance deposits from guests'),
('2102', 'Event Deposits', 'liability', 'Current Liabilities', 'Advance deposits for events'),
('2103', 'Gift Certificates Outstanding', 'liability', 'Current Liabilities', 'Unredeemed gift certificates'),
('2104', 'Service Charge Payable', 'liability', 'Current Liabilities', 'Service charges to be distributed'),
('2105', 'Tips Payable', 'liability', 'Current Liabilities', 'Tips to be distributed to staff'),
('2106', 'Commission Payable', 'liability', 'Current Liabilities', 'Commissions owed to staff'),
('2107', 'Payroll Taxes Payable', 'liability', 'Current Liabilities', 'Payroll taxes withheld'),
('2108', 'Sales Tax Payable', 'liability', 'Current Liabilities', 'Sales tax collected'),
('2109', 'Occupancy Tax Payable', 'liability', 'Current Liabilities', 'Hotel occupancy tax');

-- Update basic accounts with hotel/restaurant context
UPDATE chart_of_accounts SET description = 'Cash on hand (Front Desk, Cashier, Petty Cash)' WHERE account_code = '1001';
UPDATE chart_of_accounts SET description = 'Amounts owed by hotel guests and customers' WHERE account_code = '1002';
UPDATE chart_of_accounts SET description = 'Amounts owed to food/beverage suppliers and vendors' WHERE account_code = '2001';

-- ========================================================================================================
-- SECTION 3: FINANCIAL MODULES (FINANCIALS DEPARTMENT SCOPE)
-- Integration framework, cashier/collection, department tracking, and reporting
-- ========================================================================================================

-- FINANCIAL ORGANIZATION STRUCTURE

-- Departments (Financial Cost Centers / Revenue Centers)
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dept_code VARCHAR(20) UNIQUE NOT NULL,
    dept_name VARCHAR(100) NOT NULL,
    dept_type ENUM('revenue_center', 'cost_center', 'support') NOT NULL,
    category ENUM('rooms', 'food_beverage', 'events', 'spa', 'other_revenue', 'admin', 'maintenance', 'marketing', 'other_expense') NOT NULL,
    description TEXT,
    parent_dept_id INT NULL COMMENT 'For hierarchical department structure',
    revenue_account_id INT NULL COMMENT 'Default revenue GL account',
    expense_account_id INT NULL COMMENT 'Default expense GL account',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_dept_id) REFERENCES departments(id),
    FOREIGN KEY (revenue_account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (expense_account_id) REFERENCES chart_of_accounts(id)
);

-- Insert default financial departments/cost centers
INSERT INTO departments (dept_code, dept_name, dept_type, category, description) VALUES
('ROOMS', 'Rooms Division', 'revenue_center', 'rooms', 'Room revenue tracking'),
('FB-REST', 'Restaurant', 'revenue_center', 'food_beverage', 'Restaurant revenue tracking'),
('FB-BAR', 'Bar & Lounge', 'revenue_center', 'food_beverage', 'Bar revenue tracking'),
('FB-BANQ', 'Banquet & Events', 'revenue_center', 'events', 'Event revenue tracking'),
('SPA', 'Spa & Wellness', 'revenue_center', 'spa', 'Spa revenue tracking'),
('OTHER-REV', 'Other Revenue', 'revenue_center', 'other_revenue', 'Miscellaneous revenue'),
('ADMIN', 'Administration', 'cost_center', 'admin', 'Administrative expenses'),
('MAINT', 'Maintenance', 'cost_center', 'maintenance', 'Property maintenance expenses'),
('MARKETING', 'Sales & Marketing', 'cost_center', 'marketing', 'Marketing and sales expenses');

-- Revenue Centers (Detailed revenue tracking points within departments)
CREATE TABLE IF NOT EXISTS revenue_centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    center_code VARCHAR(20) UNIQUE NOT NULL,
    center_name VARCHAR(100) NOT NULL,
    department_id INT NOT NULL,
    revenue_account_id INT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (revenue_account_id) REFERENCES chart_of_accounts(id)
);

-- CASHIER / COLLECTION MODULE

-- Cashier Sessions (Daily cash collection tracking)
CREATE TABLE IF NOT EXISTS cashier_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_number VARCHAR(20) UNIQUE NOT NULL,
    cashier_id INT NOT NULL,
    department_id INT NULL COMMENT 'Which department is this cashier assigned to',
    session_date DATE NOT NULL,
    shift ENUM('morning', 'afternoon', 'night', 'full_day') NOT NULL,
    opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    closing_balance DECIMAL(15,2) DEFAULT 0,
    expected_balance DECIMAL(15,2) DEFAULT 0 COMMENT 'Calculated expected cash',
    variance DECIMAL(15,2) DEFAULT 0 COMMENT 'Difference between expected and actual',
    total_collections DECIMAL(15,2) DEFAULT 0,
    total_cash DECIMAL(15,2) DEFAULT 0,
    total_card DECIMAL(15,2) DEFAULT 0,
    total_checks DECIMAL(15,2) DEFAULT 0,
    total_bank_transfer DECIMAL(15,2) DEFAULT 0,
    total_other DECIMAL(15,2) DEFAULT 0,
    status ENUM('open', 'closed', 'reconciled', 'deposited') DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    reconciled_by INT NULL,
    reconciled_at TIMESTAMP NULL,
    deposited_by INT NULL,
    deposited_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (cashier_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (reconciled_by) REFERENCES users(id),
    FOREIGN KEY (deposited_by) REFERENCES users(id)
);

-- Cashier Transactions (Individual collection records)
CREATE TABLE IF NOT EXISTS cashier_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    transaction_number VARCHAR(20) UNIQUE NOT NULL,
    transaction_type ENUM('collection', 'payment', 'deposit', 'withdrawal', 'adjustment') NOT NULL,
    transaction_date DATETIME NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'check', 'bank_transfer', 'other') NOT NULL,
    reference_number VARCHAR(100) COMMENT 'External reference (invoice #, receipt #, etc)',
    customer_name VARCHAR(100),
    department_id INT NULL,
    account_id INT NULL COMMENT 'GL account to post to',
    external_system VARCHAR(50) COMMENT 'Source system (hotel_system, pos_system, etc)',
    external_id VARCHAR(100) COMMENT 'ID in source system',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES cashier_sessions(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- SYSTEM INTEGRATION FRAMEWORK

-- System Integrations (Track connected systems)
CREATE TABLE IF NOT EXISTS system_integrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    system_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'hotel_core1, restaurant_core2, logistics1, hr1, etc',
    system_name VARCHAR(100) NOT NULL,
    system_type ENUM('hotel_pms', 'restaurant_pos', 'logistics', 'hr', 'other') NOT NULL,
    api_endpoint VARCHAR(255) COMMENT 'API URL for this system',
    api_key VARCHAR(255) COMMENT 'Authentication key',
    sync_frequency ENUM('realtime', 'hourly', 'daily', 'manual') DEFAULT 'daily',
    last_sync_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    configuration JSON COMMENT 'System-specific configuration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system integrations
INSERT INTO system_integrations (system_code, system_name, system_type, sync_frequency) VALUES
('HOTEL_CORE1', 'Hotel Management System (Core 1)', 'hotel_pms', 'realtime'),
('RESTAURANT_CORE2', 'Restaurant POS System (Core 2)', 'restaurant_pos', 'realtime'),
('LOGISTICS1', 'Logistics & Procurement System', 'logistics', 'daily'),
('HR_SYSTEM', 'Human Resources System', 'hr', 'daily');

-- Integration Mappings (Map external codes to GL accounts)
CREATE TABLE IF NOT EXISTS integration_mappings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    system_code VARCHAR(50) NOT NULL,
    external_code VARCHAR(100) NOT NULL COMMENT 'Code in source system (room type, menu item, etc)',
    external_name VARCHAR(150) NOT NULL,
    mapping_type ENUM('revenue', 'expense', 'asset', 'liability') NOT NULL,
    gl_account_id INT NOT NULL COMMENT 'Which GL account to post to',
    department_id INT NULL COMMENT 'Which department this belongs to',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (system_code) REFERENCES system_integrations(system_code),
    FOREIGN KEY (gl_account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    UNIQUE KEY unique_mapping (system_code, external_code)
);

-- Imported Transactions (Receive transactions from all systems)
CREATE TABLE IF NOT EXISTS imported_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    import_batch VARCHAR(50) NOT NULL COMMENT 'Batch identifier for bulk imports',
    source_system VARCHAR(50) NOT NULL COMMENT 'Which system sent this',
    transaction_date DATETIME NOT NULL,
    transaction_type VARCHAR(50) NOT NULL COMMENT 'sale, room_charge, expense, etc',
    external_id VARCHAR(100) NOT NULL COMMENT 'ID in source system',
    external_reference VARCHAR(100) COMMENT 'Reference number in source system',
    department_id INT NULL,
    revenue_center_id INT NULL,
    customer_name VARCHAR(100),
    description TEXT,
    amount DECIMAL(15,2) NOT NULL,
    gl_account_id INT NULL COMMENT 'GL account (from mapping)',
    payment_method VARCHAR(50),
    status ENUM('pending', 'posted', 'rejected', 'duplicate') DEFAULT 'pending',
    journal_entry_id INT NULL COMMENT 'Link to posted journal entry',
    invoice_id INT NULL COMMENT 'Link to created invoice (if applicable)',
    error_message TEXT,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    posted_at TIMESTAMP NULL,
    posted_by INT NULL,
    raw_data JSON COMMENT 'Original data from source system',
    FOREIGN KEY (source_system) REFERENCES system_integrations(system_code),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (revenue_center_id) REFERENCES revenue_centers(id),
    FOREIGN KEY (gl_account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (posted_by) REFERENCES users(id),
    UNIQUE KEY unique_transaction (source_system, external_id)
);

-- Integration Logs (Track all integration activity)
CREATE TABLE IF NOT EXISTS integration_sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    system_code VARCHAR(50) NOT NULL,
    sync_type ENUM('import', 'export', 'reconciliation') NOT NULL,
    sync_date DATETIME NOT NULL,
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    error_summary TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    executed_by INT NULL,
    FOREIGN KEY (system_code) REFERENCES system_integrations(system_code),
    FOREIGN KEY (executed_by) REFERENCES users(id)
);

-- FINANCIAL SUMMARY TABLES

-- Daily Revenue Summary (By department/source)
CREATE TABLE IF NOT EXISTS daily_revenue_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_date DATE NOT NULL,
    department_id INT NOT NULL,
    revenue_center_id INT NULL,
    source_system VARCHAR(50) COMMENT 'Which system the revenue came from',
    revenue_category VARCHAR(50) COMMENT 'rooms, food, beverage, etc',
    total_transactions INT DEFAULT 0,
    gross_revenue DECIMAL(15,2) DEFAULT 0,
    discounts DECIMAL(15,2) DEFAULT 0,
    service_charges DECIMAL(15,2) DEFAULT 0,
    taxes DECIMAL(15,2) DEFAULT 0,
    net_revenue DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (revenue_center_id) REFERENCES revenue_centers(id),
    UNIQUE KEY unique_summary (business_date, department_id, revenue_center_id, source_system, revenue_category)
);

-- Daily Expense Summary (By department)
CREATE TABLE IF NOT EXISTS daily_expense_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_date DATE NOT NULL,
    department_id INT NOT NULL,
    expense_category VARCHAR(50) COMMENT 'labor, supplies, utilities, etc',
    source_system VARCHAR(50),
    total_transactions INT DEFAULT 0,
    total_amount DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    UNIQUE KEY unique_expense_summary (business_date, department_id, expense_category, source_system)
);

-- Monthly Department Performance (Rolled-up metrics)
CREATE TABLE IF NOT EXISTS monthly_department_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    month INT NOT NULL,
    department_id INT NOT NULL,
    total_revenue DECIMAL(15,2) DEFAULT 0,
    total_expenses DECIMAL(15,2) DEFAULT 0,
    net_income DECIMAL(15,2) DEFAULT 0,
    budget_revenue DECIMAL(15,2) DEFAULT 0 COMMENT 'From budget tables',
    budget_expenses DECIMAL(15,2) DEFAULT 0,
    revenue_variance DECIMAL(15,2) DEFAULT 0,
    expense_variance DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    UNIQUE KEY unique_month_dept (year, month, department_id)
);

-- BUDGET TRACKING

-- Department Budgets (Annual budget by department)
CREATE TABLE IF NOT EXISTS department_budgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_year YEAR NOT NULL,
    department_id INT NOT NULL,
    budget_type ENUM('revenue', 'expense') NOT NULL,
    jan_amount DECIMAL(15,2) DEFAULT 0,
    feb_amount DECIMAL(15,2) DEFAULT 0,
    mar_amount DECIMAL(15,2) DEFAULT 0,
    apr_amount DECIMAL(15,2) DEFAULT 0,
    may_amount DECIMAL(15,2) DEFAULT 0,
    jun_amount DECIMAL(15,2) DEFAULT 0,
    jul_amount DECIMAL(15,2) DEFAULT 0,
    aug_amount DECIMAL(15,2) DEFAULT 0,
    sep_amount DECIMAL(15,2) DEFAULT 0,
    oct_amount DECIMAL(15,2) DEFAULT 0,
    nov_amount DECIMAL(15,2) DEFAULT 0,
    dec_amount DECIMAL(15,2) DEFAULT 0,
    annual_total DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    status ENUM('draft', 'submitted', 'approved', 'active') DEFAULT 'draft',
    created_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    UNIQUE KEY unique_dept_budget (budget_year, department_id, budget_type)
);

-- FINANCIAL REPORTING METADATA

-- Saved Report Configurations
CREATE TABLE IF NOT EXISTS saved_financial_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_code VARCHAR(50) UNIQUE NOT NULL,
    report_name VARCHAR(150) NOT NULL,
    report_type ENUM('usali_pl', 'dept_pl', 'budget_variance', 'cashflow', 'balance_sheet', 'custom') NOT NULL,
    report_category VARCHAR(50) COMMENT 'usali, management, statutory, etc',
    parameters JSON COMMENT 'Report configuration and filters',
    is_system_report BOOLEAN DEFAULT FALSE COMMENT 'Built-in vs user-created',
    is_public BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert default USALI reports
INSERT INTO saved_financial_reports (report_code, report_name, report_type, report_category, is_system_report, parameters) VALUES
('USALI_PL_SUMMARY', 'USALI Income Statement - Summary', 'usali_pl', 'usali', TRUE, '{"format":"summary","include_departments":true}'),
('USALI_PL_DETAIL', 'USALI Income Statement - Detailed', 'usali_pl', 'usali', TRUE, '{"format":"detailed","include_departments":true}'),
('DEPT_PL_ROOMS', 'Rooms Division P&L', 'dept_pl', 'management', TRUE, '{"department":"ROOMS"}'),
('DEPT_PL_FB', 'Food & Beverage P&L', 'dept_pl', 'management', TRUE, '{"departments":["FB-REST","FB-BAR","FB-BANQ"]}'),
('BUDGET_VARIANCE', 'Budget vs Actual - All Departments', 'budget_variance', 'management', TRUE, '{"include_variance_percent":true}'),
('CASHFLOW_DAILY', 'Daily Cash Flow Report', 'cashflow', 'management', TRUE, '{"period":"daily"}');

-- INDEXES FOR PERFORMANCE

CREATE INDEX idx_departments_dept_type ON departments(dept_type);
CREATE INDEX idx_departments_category ON departments(category);
CREATE INDEX idx_revenue_centers_department_id ON revenue_centers(department_id);

CREATE INDEX idx_cashier_sessions_cashier_id ON cashier_sessions(cashier_id);
CREATE INDEX idx_cashier_sessions_session_date ON cashier_sessions(session_date);
CREATE INDEX idx_cashier_sessions_status ON cashier_sessions(status);
CREATE INDEX idx_cashier_transactions_session_id ON cashier_transactions(session_id);
CREATE INDEX idx_cashier_transactions_transaction_date ON cashier_transactions(transaction_date);

CREATE INDEX idx_imported_transactions_source_system ON imported_transactions(source_system);
CREATE INDEX idx_imported_transactions_transaction_date ON imported_transactions(transaction_date);
CREATE INDEX idx_imported_transactions_status ON imported_transactions(status);
CREATE INDEX idx_imported_transactions_department_id ON imported_transactions(department_id);

CREATE INDEX idx_daily_revenue_summary_business_date ON daily_revenue_summary(business_date);
CREATE INDEX idx_daily_revenue_summary_department_id ON daily_revenue_summary(department_id);
CREATE INDEX idx_daily_expense_summary_business_date ON daily_expense_summary(business_date);
CREATE INDEX idx_daily_expense_summary_department_id ON daily_expense_summary(department_id);

CREATE INDEX idx_monthly_dept_performance_year_month ON monthly_department_performance(year, month);
CREATE INDEX idx_monthly_dept_performance_department_id ON monthly_department_performance(department_id);

CREATE INDEX idx_integration_sync_logs_system_code ON integration_sync_logs(system_code);
CREATE INDEX idx_integration_sync_logs_sync_date ON integration_sync_logs(sync_date);

-- ========================================================================================================
-- ========================================================================================================
-- APPROVAL WORKFLOW SYSTEM (Added for Disbursements Module)
-- Multi-level approval for financial transactions and disbursements
-- ========================================================================================================

-- Approval Workflows (Define approval rules by amount/department)
CREATE TABLE IF NOT EXISTS approval_workflows (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workflow_name VARCHAR(100) NOT NULL,
    workflow_type ENUM('disbursement', 'purchase_order', 'invoice') DEFAULT 'disbursement',
    department_id INT NULL COMMENT 'Specific department, NULL for all',
    min_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Minimum amount requiring approval',
    max_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Maximum amount covered by this rule',
    currency VARCHAR(10) DEFAULT 'PHP',
    requires_approval BOOLEAN DEFAULT TRUE,
    approval_levels INT DEFAULT 1 COMMENT 'Number of approval levels required',
    is_active BOOLEAN DEFAULT TRUE,

    -- Level 1 approvers
    level1_role VARCHAR(50) NULL COMMENT 'Role required for level 1 approval',
    level1_department_id INT NULL COMMENT 'Department required for level 1',

    -- Level 2 approvers (if needed)
    level2_role VARCHAR(50) NULL COMMENT 'Role required for level 2 approval',
    level2_department_id INT NULL COMMENT 'Department required for level 2',

    -- Timeout settings
    approval_timeout_hours INT DEFAULT 48 COMMENT 'Hours before approval request times out',

    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (level1_department_id) REFERENCES departments(id),
    FOREIGN KEY (level2_department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Disbursement Approvals (Track individual approval instances)
CREATE TABLE IF NOT EXISTS disbursement_approvals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disbursement_id INT NOT NULL,
    workflow_id INT NOT NULL,
    current_level INT DEFAULT 1 COMMENT 'Current approval level needed',
    total_levels INT DEFAULT 1 COMMENT 'Total levels required',

    -- Status tracking
    status ENUM('pending', 'approved', 'rejected', 'timed_out', 'cancelled') DEFAULT 'pending',
    submit_reason TEXT COMMENT 'Reason for submission',
    rejection_reason TEXT COMMENT 'Reason for rejection if rejected',

    -- Current approver
    current_approver_id INT NULL,
    assigned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    due_by TIMESTAMP NULL COMMENT 'When approval is due',

    -- Final approval info
    final_approved_at TIMESTAMP NULL,
    final_approved_by INT NULL,

    -- Audit fields
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (disbursement_id) REFERENCES disbursements(id),
    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id),
    FOREIGN KEY (current_approver_id) REFERENCES users(id),
    FOREIGN KEY (final_approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),

    UNIQUE KEY unique_disbursement_approval (disbursement_id)
);

-- Approval History (Track all approval/rejection actions)
CREATE TABLE IF NOT EXISTS approval_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    disbursement_approval_id INT NOT NULL,
    approval_level INT NOT NULL,
    action ENUM('submitted', 'approved', 'rejected', 'timed_out', 'escalated') NOT NULL,
    action_by INT NOT NULL,
    action_reason TEXT,
    action_notes TEXT,
    action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (disbursement_approval_id) REFERENCES disbursement_approvals(id),
    FOREIGN KEY (action_by) REFERENCES users(id)
);

-- Insert default approval workflows
INSERT INTO approval_workflows (
    workflow_name, workflow_type, min_amount, max_amount,
    approval_levels, level1_role, level1_department_id,
    level2_role, level2_department_id, created_by
) VALUES
-- Small disbursements (auto-approve under ₱5,000)
('Small Disbursements Auto-Approval', 'disbursement', 0, 5000,
 0, NULL, NULL, NULL, NULL, 1),

-- Medium disbursements (₱5,001 - ₱25,000: Dept Manager approval)
('Medium Disbursements - Manager Approval', 'disbursement', 5000.01, 25000,
 1, 'manager', NULL, NULL, NULL, 1),

-- Large disbursements (₱25,001 - ₱100,000: Dept Manager + Finance)
('Large Disbursements - Double Approval', 'disbursement', 25000.01, 100000,
 2, 'manager', NULL, 'finance_head', NULL, 1),

-- Very Large disbursements (over ₱100,000: Triple approval)
('Executive Disbursements - Triple Approval', 'disbursement', 100000.01, 9999999,
 3, 'manager', NULL, 'finance_head', NULL, 1);

-- Index approval tables for performance
CREATE INDEX idx_approval_workflows_department ON approval_workflows(department_id);
CREATE INDEX idx_approval_workflows_active ON approval_workflows(is_active);
CREATE INDEX idx_disbursement_approvals_disbursement ON disbursement_approvals(disbursement_id);
CREATE INDEX idx_disbursement_approvals_status ON disbursement_approvals(status);
CREATE INDEX idx_disbursement_approvals_current_approver ON disbursement_approvals(current_approver_id);
CREATE INDEX idx_approval_history_approval ON approval_history(disbursement_approval_id);

-- Add approval_needed status to disbursements table
ALTER TABLE disbursements
ADD COLUMN needs_approval BOOLEAN DEFAULT FALSE,
ADD COLUMN approval_id INT NULL,
ADD COLUMN approval_status ENUM('not_required', 'pending', 'approved', 'rejected') DEFAULT 'not_required',
ADD INDEX idx_disbursements_approval_status (approval_status),
ADD FOREIGN KEY (approval_id) REFERENCES disbursement_approvals(id);

-- ========================================================================================================
-- INSTALLATION COMPLETE
-- ========================================================================================================
-- The ATIERA Financial Management System database has been successfully created!
--
-- New Features Added:
-- ✅ Approval Workflow System for Disbursements
-- ✅ Multi-level approvals based on amount thresholds
-- ✅ Department-based approval routing
--
-- Next Steps:
-- 1. Login with default credentials: username=admin, password=admin123
-- 2. Navigate to Financials > Disbursements > Approval Workflow tab
-- 3. Set up integration mappings for external systems (Hotel PMS, Restaurant POS, etc.)
-- 4. Configure user roles and permissions
--
-- For documentation, see:
-- - README_FINANCIALS.md
-- - FINANCIALS_SCOPE.md
-- - INTEGRATION_GUIDE.md
-- ========================================================================================================
