-- ATIERA Financial Management System - New Features Database Updates
-- This file contains all database schema changes for the new requirements

-- =============================================================================
-- 1. LOGIN SESSIONS TRACKING (for login time reports)
-- =============================================================================
CREATE TABLE IF NOT EXISTS login_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    login_time DATETIME NOT NULL,
    logout_time DATETIME NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_duration INT NULL COMMENT 'Duration in seconds',
    logout_type ENUM('manual', 'timeout', 'system') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_login (user_id, login_time),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. NOTIFICATIONS TABLE (for login/logout notifications)
-- =============================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('login', 'logout', 'info', 'warning', 'error', 'success') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    metadata JSON NULL COMMENT 'Additional data like IP address, login time, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. BUDGET LIQUIDATIONS TABLE (with receipt/proof tracking)
-- =============================================================================
CREATE TABLE IF NOT EXISTS budget_liquidations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_id INT NOT NULL,
    department_id INT NOT NULL,
    liquidation_number VARCHAR(50) UNIQUE NOT NULL,
    description TEXT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    submitted_by INT NOT NULL,
    approved_by INT NULL,
    submission_date DATETIME NOT NULL,
    approval_date DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_budget (budget_id),
    INDEX idx_department (department_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. LIQUIDATION RECEIPTS/PROOFS TABLE
-- =============================================================================
CREATE TABLE IF NOT EXISTS liquidation_receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    liquidation_id INT NOT NULL,
    receipt_number VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    receipt_date DATE NOT NULL,
    vendor_name VARCHAR(255),
    category VARCHAR(100),
    file_path VARCHAR(500) NULL COMMENT 'Path to scanned receipt/proof',
    file_name VARCHAR(255) NULL,
    file_type VARCHAR(50) NULL,
    uploaded_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (liquidation_id) REFERENCES budget_liquidations(id) ON DELETE CASCADE,
    INDEX idx_liquidation (liquidation_id),
    INDEX idx_receipt_date (receipt_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. BUDGET PROPOSAL FINANCIAL BREAKDOWN TABLE
-- =============================================================================
CREATE TABLE IF NOT EXISTS budget_proposal_breakdown (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(100),
    item_description TEXT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    justification TEXT,
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    line_number INT NOT NULL COMMENT 'For ordering items',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    INDEX idx_budget (budget_id),
    INDEX idx_category (category),
    INDEX idx_line_number (budget_id, line_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. DEPARTMENT LIQUIDATION REQUIREMENTS TABLE
-- =============================================================================
CREATE TABLE IF NOT EXISTS department_liquidation_requirements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    requires_liquidation BOOLEAN DEFAULT TRUE,
    min_liquidation_percentage DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Minimum % of previous budget that must be liquidated',
    grace_period_days INT DEFAULT 0 COMMENT 'Days before requirement is enforced',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. ADD COLUMNS TO EXISTING TABLES
-- =============================================================================

-- Add 2FA required flag to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS require_2fa BOOLEAN DEFAULT FALSE COMMENT 'Force user to enable 2FA',
ADD COLUMN IF NOT EXISTS last_activity DATETIME NULL COMMENT 'Track user activity for timeout';

-- Add financial modification permission tracking
ALTER TABLE audit_log
ADD COLUMN IF NOT EXISTS requires_admin_approval BOOLEAN DEFAULT FALSE COMMENT 'Flag financial modifications needing admin approval',
ADD COLUMN IF NOT EXISTS approved_by INT NULL COMMENT 'Admin who approved the change',
ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL COMMENT 'When the change was approved',
ADD FOREIGN KEY IF NOT EXISTS (approved_by) REFERENCES users(id);

-- Add liquidation tracking to budgets table
ALTER TABLE budgets
ADD COLUMN IF NOT EXISTS has_liquidation BOOLEAN DEFAULT FALSE COMMENT 'Does this budget have an approved liquidation?',
ADD COLUMN IF NOT EXISTS liquidation_status ENUM('none', 'partial', 'complete') DEFAULT 'none',
ADD COLUMN IF NOT EXISTS liquidated_amount DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS can_create_new_proposal BOOLEAN DEFAULT TRUE COMMENT 'Can create new proposal based on liquidation';

-- Add notification tracking columns
ALTER TABLE invoices
ADD COLUMN IF NOT EXISTS last_overdue_notification DATETIME NULL COMMENT 'Last time overdue notification was sent';

ALTER TABLE bills
ADD COLUMN IF NOT EXISTS last_overdue_notification DATETIME NULL COMMENT 'Last time overdue notification was sent';

-- =============================================================================
-- 8. CREATE VIEWS FOR REPORTING
-- =============================================================================

-- View for login activity reports
CREATE OR REPLACE VIEW v_login_activity AS
SELECT
    ls.id,
    ls.user_id,
    u.username,
    u.full_name,
    u.role,
    ls.login_time,
    ls.logout_time,
    ls.session_duration,
    ls.logout_type,
    ls.ip_address,
    DATE(ls.login_time) as login_date,
    TIME(ls.login_time) as login_time_only,
    CASE
        WHEN ls.logout_time IS NULL THEN 'Active'
        ELSE 'Ended'
    END as session_status
FROM login_sessions ls
JOIN users u ON ls.user_id = u.id
ORDER BY ls.login_time DESC;

-- View for budget liquidation status
CREATE OR REPLACE VIEW v_budget_liquidation_status AS
SELECT
    b.id as budget_id,
    b.department_id,
    d.name as department_name,
    b.fiscal_year,
    b.allocated_amount,
    COALESCE(SUM(bl.total_amount), 0) as total_liquidated,
    COALESCE(SUM(CASE WHEN bl.status = 'approved' THEN bl.total_amount ELSE 0 END), 0) as approved_liquidated,
    ROUND((COALESCE(SUM(CASE WHEN bl.status = 'approved' THEN bl.total_amount ELSE 0 END), 0) / b.allocated_amount * 100), 2) as liquidation_percentage,
    COUNT(bl.id) as liquidation_count,
    dlr.requires_liquidation,
    dlr.min_liquidation_percentage,
    CASE
        WHEN dlr.requires_liquidation = FALSE THEN TRUE
        WHEN COALESCE(SUM(CASE WHEN bl.status = 'approved' THEN bl.total_amount ELSE 0 END), 0) / b.allocated_amount * 100 >= dlr.min_liquidation_percentage THEN TRUE
        ELSE FALSE
    END as can_create_new_budget
FROM budgets b
JOIN departments d ON b.department_id = d.id
LEFT JOIN budget_liquidations bl ON b.id = bl.budget_id
LEFT JOIN department_liquidation_requirements dlr ON d.id = dlr.department_id
GROUP BY b.id, b.department_id, d.name, b.fiscal_year, b.allocated_amount, dlr.requires_liquidation, dlr.min_liquidation_percentage;

-- View for user activity log
CREATE OR REPLACE VIEW v_user_activity_log AS
SELECT
    al.id,
    al.user_id,
    u.username,
    u.full_name,
    al.action,
    al.table_name,
    al.record_id,
    al.ip_address,
    al.created_at,
    DATE(al.created_at) as activity_date,
    TIME(al.created_at) as activity_time
FROM audit_log al
LEFT JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC;

-- =============================================================================
-- 9. INSERT DEFAULT DATA
-- =============================================================================

-- Set default liquidation requirements for all departments
INSERT INTO department_liquidation_requirements (department_id, requires_liquidation, min_liquidation_percentage)
SELECT id, TRUE, 100.00
FROM departments
WHERE id NOT IN (SELECT department_id FROM department_liquidation_requirements)
ON DUPLICATE KEY UPDATE department_id = department_id;

-- =============================================================================
-- 10. CREATE STORED PROCEDURES FOR COMMON OPERATIONS
-- =============================================================================

DELIMITER //

-- Procedure to log login session
CREATE PROCEDURE IF NOT EXISTS sp_log_login_session(
    IN p_user_id INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    OUT p_session_id INT
)
BEGIN
    INSERT INTO login_sessions (user_id, login_time, ip_address, user_agent)
    VALUES (p_user_id, NOW(), p_ip_address, p_user_agent);

    SET p_session_id = LAST_INSERT_ID();

    -- Create notification
    INSERT INTO notifications (user_id, type, title, message, metadata)
    VALUES (
        p_user_id,
        'login',
        'New Login Detected',
        CONCAT('You logged in at ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')),
        JSON_OBJECT('ip_address', p_ip_address, 'login_time', NOW())
    );
END//

-- Procedure to log logout session
CREATE PROCEDURE IF NOT EXISTS sp_log_logout_session(
    IN p_user_id INT,
    IN p_logout_type VARCHAR(20)
)
BEGIN
    DECLARE v_session_id INT;
    DECLARE v_login_time DATETIME;

    -- Get the most recent active session
    SELECT id, login_time INTO v_session_id, v_login_time
    FROM login_sessions
    WHERE user_id = p_user_id AND logout_time IS NULL
    ORDER BY login_time DESC
    LIMIT 1;

    IF v_session_id IS NOT NULL THEN
        UPDATE login_sessions
        SET
            logout_time = NOW(),
            logout_type = p_logout_type,
            session_duration = TIMESTAMPDIFF(SECOND, login_time, NOW())
        WHERE id = v_session_id;

        -- Create notification
        INSERT INTO notifications (user_id, type, title, message, metadata)
        VALUES (
            p_user_id,
            'logout',
            'Logout Recorded',
            CONCAT('You logged out at ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')),
            JSON_OBJECT(
                'logout_time', NOW(),
                'logout_type', p_logout_type,
                'session_duration', TIMESTAMPDIFF(SECOND, v_login_time, NOW())
            )
        );
    END IF;
END//

-- Procedure to check if department can create new budget proposal
CREATE PROCEDURE IF NOT EXISTS sp_can_create_budget_proposal(
    IN p_department_id INT,
    OUT p_can_create BOOLEAN,
    OUT p_reason VARCHAR(255)
)
BEGIN
    DECLARE v_requires_liquidation BOOLEAN;
    DECLARE v_min_percentage DECIMAL(5,2);
    DECLARE v_current_percentage DECIMAL(5,2);

    -- Get requirements
    SELECT requires_liquidation, min_liquidation_percentage
    INTO v_requires_liquidation, v_min_percentage
    FROM department_liquidation_requirements
    WHERE department_id = p_department_id;

    -- If no requirements found, default to requiring liquidation
    IF v_requires_liquidation IS NULL THEN
        SET v_requires_liquidation = TRUE;
        SET v_min_percentage = 100.00;
    END IF;

    -- If liquidation not required, allow creation
    IF v_requires_liquidation = FALSE THEN
        SET p_can_create = TRUE;
        SET p_reason = 'No liquidation required for this department';
    ELSE
        -- Check liquidation percentage
        SELECT COALESCE(liquidation_percentage, 0)
        INTO v_current_percentage
        FROM v_budget_liquidation_status
        WHERE department_id = p_department_id
        ORDER BY fiscal_year DESC
        LIMIT 1;

        IF v_current_percentage >= v_min_percentage THEN
            SET p_can_create = TRUE;
            SET p_reason = CONCAT('Liquidation requirement met (', v_current_percentage, '%)');
        ELSE
            SET p_can_create = FALSE;
            SET p_reason = CONCAT('Insufficient liquidation. Required: ', v_min_percentage, '%, Current: ', v_current_percentage, '%');
        END IF;
    END IF;
END//

DELIMITER ;

-- =============================================================================
-- 11. CREATE TRIGGERS FOR AUTOMATIC UPDATES
-- =============================================================================

DELIMITER //

-- Trigger to update budget liquidation status
CREATE TRIGGER IF NOT EXISTS trg_update_budget_liquidation_status
AFTER INSERT ON budget_liquidations
FOR EACH ROW
BEGIN
    UPDATE budgets b
    SET
        has_liquidation = TRUE,
        liquidation_status = CASE
            WHEN (SELECT SUM(total_amount) FROM budget_liquidations WHERE budget_id = NEW.budget_id AND status = 'approved') >= b.allocated_amount THEN 'complete'
            WHEN (SELECT SUM(total_amount) FROM budget_liquidations WHERE budget_id = NEW.budget_id AND status = 'approved') > 0 THEN 'partial'
            ELSE 'none'
        END,
        liquidated_amount = COALESCE((SELECT SUM(total_amount) FROM budget_liquidations WHERE budget_id = NEW.budget_id AND status = 'approved'), 0)
    WHERE id = NEW.budget_id;
END//

-- Trigger to update user last activity on audit log
CREATE TRIGGER IF NOT EXISTS trg_update_user_activity
AFTER INSERT ON audit_log
FOR EACH ROW
BEGIN
    IF NEW.user_id IS NOT NULL THEN
        UPDATE users
        SET last_activity = NOW()
        WHERE id = NEW.user_id;
    END IF;
END//

DELIMITER ;

-- =============================================================================
-- 12. GRANT PERMISSIONS (if using specific database users)
-- =============================================================================
-- GRANT SELECT, INSERT, UPDATE ON login_sessions TO 'atiera_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON notifications TO 'atiera_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON budget_liquidations TO 'atiera_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON liquidation_receipts TO 'atiera_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON budget_proposal_breakdown TO 'atiera_app'@'localhost';

-- =============================================================================
-- END OF DATABASE UPDATES
-- =============================================================================

SELECT 'Database schema updated successfully!' as status;
