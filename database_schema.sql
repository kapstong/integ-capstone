-- ATIERA Financial Management System Database Schema
-- Created for Capstone Project - BSIT 4101 Cluster 1

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

-- Insert default chart of accounts
INSERT INTO chart_of_accounts (account_code, account_name, account_type, category, description) VALUES
('1001', 'Cash', 'asset', 'Current Assets', 'Cash on hand and in bank'),
('1002', 'Accounts Receivable', 'asset', 'Current Assets', 'Money owed by customers'),
('2001', 'Accounts Payable', 'liability', 'Current Liabilities', 'Money owed to vendors'),
('3001', 'Owner''s Equity', 'equity', 'Equity', 'Owner''s investment in business'),
('4001', 'Sales Revenue', 'revenue', 'Revenue', 'Income from hotel and restaurant services'),
('5001', 'Cost of Goods Sold', 'expense', 'Cost of Sales', 'Direct costs of providing services'),
('5002', 'Operating Expenses', 'expense', 'Operating Expenses', 'General business expenses'),
('5003', 'Salaries and Wages', 'expense', 'Operating Expenses', 'Employee compensation');

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

-- Payments received (Collections - Accounts Receivable)
CREATE TABLE payments_received (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    invoice_id INT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'other') NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
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
