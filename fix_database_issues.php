<?php
/**
 * Fix Database Issues - Manual Creation of Missing Components
 * Run this to create the views, procedures, and triggers that failed
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Fix Database Issues</title>\n";
echo "<style>body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; } ";
echo ".success { color: green; background: #e8f5e9; padding: 10px; margin: 5px 0; border-left: 4px solid green; } ";
echo ".error { color: red; background: #ffebee; padding: 10px; margin: 5px 0; border-left: 4px solid red; } ";
echo ".info { color: blue; background: #e3f2fd; padding: 10px; margin: 5px 0; border-left: 4px solid blue; } ";
echo "h1 { color: #1b2f73; } h2 { color: #2342a6; margin-top: 30px; } </style>\n</head>\n<body>";

echo "<h1>Fix Database Issues</h1>";
echo "<p>Creating missing views, procedures, and triggers...</p>";

try {
    $db = Database::getInstance()->getConnection();

    // First, check budgets table structure
    echo "<h2>Step 1: Check Budgets Table Structure</h2>";
    $stmt = $db->query("DESCRIBE budgets");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $hasDepartmentId = in_array('department_id', $columns);
    echo "<div class='info'>Budgets table has " . count($columns) . " columns</div>";
    echo "<div class='" . ($hasDepartmentId ? 'success' : 'error') . "'>department_id column: " .
         ($hasDepartmentId ? "EXISTS" : "MISSING - will add it") . "</div>";

    // Add department_id if missing
    if (!$hasDepartmentId) {
        echo "<div class='info'>Adding department_id column to budgets table...</div>";
        $db->exec("ALTER TABLE budgets ADD COLUMN department_id INT NULL AFTER id");
        $db->exec("ALTER TABLE budgets ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
        echo "<div class='success'>✓ Added department_id column</div>";
    }

    // Create view: v_budget_liquidation_status
    echo "<h2>Step 2: Create Budget Liquidation Status View</h2>";
    try {
        $db->exec("DROP VIEW IF EXISTS v_budget_liquidation_status");

        $viewSQL = "
        CREATE VIEW v_budget_liquidation_status AS
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
            COALESCE(dlr.requires_liquidation, TRUE) as requires_liquidation,
            COALESCE(dlr.min_liquidation_percentage, 100.00) as min_liquidation_percentage,
            CASE
                WHEN COALESCE(dlr.requires_liquidation, TRUE) = FALSE THEN TRUE
                WHEN COALESCE(SUM(CASE WHEN bl.status = 'approved' THEN bl.total_amount ELSE 0 END), 0) / b.allocated_amount * 100 >= COALESCE(dlr.min_liquidation_percentage, 100.00) THEN TRUE
                ELSE FALSE
            END as can_create_new_budget
        FROM budgets b
        LEFT JOIN departments d ON b.department_id = d.id
        LEFT JOIN budget_liquidations bl ON b.id = bl.budget_id
        LEFT JOIN department_liquidation_requirements dlr ON d.id = dlr.department_id
        GROUP BY b.id, b.department_id, d.name, b.fiscal_year, b.allocated_amount, dlr.requires_liquidation, dlr.min_liquidation_percentage
        ";

        $db->exec($viewSQL);
        echo "<div class='success'>✓ Created view: v_budget_liquidation_status</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error creating view: " . $e->getMessage() . "</div>";
    }

    // Create stored procedure: sp_log_login_session
    echo "<h2>Step 3: Create Stored Procedures</h2>";

    try {
        $db->exec("DROP PROCEDURE IF EXISTS sp_log_login_session");

        $procSQL = "
        CREATE PROCEDURE sp_log_login_session(
            IN p_user_id INT,
            IN p_ip_address VARCHAR(45),
            IN p_user_agent TEXT
        )
        BEGIN
            INSERT INTO login_sessions (user_id, login_time, ip_address, user_agent)
            VALUES (p_user_id, NOW(), p_ip_address, p_user_agent);

            INSERT INTO notifications (user_id, type, title, message, metadata)
            VALUES (
                p_user_id,
                'login',
                'New Login Detected',
                CONCAT('You logged in at ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')),
                JSON_OBJECT('ip_address', p_ip_address, 'login_time', NOW())
            );
        END
        ";

        $db->exec($procSQL);
        echo "<div class='success'>✓ Created procedure: sp_log_login_session</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error creating sp_log_login_session: " . $e->getMessage() . "</div>";
    }

    // Create stored procedure: sp_log_logout_session
    try {
        $db->exec("DROP PROCEDURE IF EXISTS sp_log_logout_session");

        $procSQL = "
        CREATE PROCEDURE sp_log_logout_session(
            IN p_user_id INT,
            IN p_logout_type VARCHAR(20)
        )
        BEGIN
            DECLARE v_session_id INT DEFAULT NULL;
            DECLARE v_login_time DATETIME DEFAULT NULL;

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
        END
        ";

        $db->exec($procSQL);
        echo "<div class='success'>✓ Created procedure: sp_log_logout_session</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error creating sp_log_logout_session: " . $e->getMessage() . "</div>";
    }

    // Create stored procedure: sp_can_create_budget_proposal
    try {
        $db->exec("DROP PROCEDURE IF EXISTS sp_can_create_budget_proposal");

        $procSQL = "
        CREATE PROCEDURE sp_can_create_budget_proposal(
            IN p_department_id INT,
            OUT p_can_create BOOLEAN,
            OUT p_reason VARCHAR(255)
        )
        BEGIN
            DECLARE v_requires_liquidation BOOLEAN DEFAULT TRUE;
            DECLARE v_min_percentage DECIMAL(5,2) DEFAULT 100.00;
            DECLARE v_current_percentage DECIMAL(5,2) DEFAULT 0.00;

            SELECT requires_liquidation, min_liquidation_percentage
            INTO v_requires_liquidation, v_min_percentage
            FROM department_liquidation_requirements
            WHERE department_id = p_department_id;

            IF v_requires_liquidation IS NULL THEN
                SET v_requires_liquidation = TRUE;
                SET v_min_percentage = 100.00;
            END IF;

            IF v_requires_liquidation = FALSE THEN
                SET p_can_create = TRUE;
                SET p_reason = 'No liquidation required for this department';
            ELSE
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
        END
        ";

        $db->exec($procSQL);
        echo "<div class='success'>✓ Created procedure: sp_can_create_budget_proposal</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error creating sp_can_create_budget_proposal: " . $e->getMessage() . "</div>";
    }

    // Create triggers
    echo "<h2>Step 4: Create Triggers</h2>";

    try {
        $db->exec("DROP TRIGGER IF EXISTS trg_update_budget_liquidation_status");

        $triggerSQL = "
        CREATE TRIGGER trg_update_budget_liquidation_status
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
        END
        ";

        $db->exec($triggerSQL);
        echo "<div class='success'>✓ Created trigger: trg_update_budget_liquidation_status</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error creating trg_update_budget_liquidation_status: " . $e->getMessage() . "</div>";
    }

    try {
        $db->exec("DROP TRIGGER IF EXISTS trg_update_user_activity");

        $triggerSQL = "
        CREATE TRIGGER trg_update_user_activity
        AFTER INSERT ON audit_log
        FOR EACH ROW
        BEGIN
            IF NEW.user_id IS NOT NULL THEN
                UPDATE users
                SET last_activity = NOW()
                WHERE id = NEW.user_id;
            END IF;
        END
        ";

        $db->exec($triggerSQL);
        echo "<div class='success'>✓ Created trigger: trg_update_user_activity</div>";
    } catch (Exception $e) {
        echo "<div class='error'>✗ Error creating trg_update_user_activity: " . $e->getMessage() . "</div>";
    }

    // Verification
    echo "<h2>Step 5: Final Verification</h2>";

    // Check views
    $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_NUM);
    echo "<div class='info'>Total views created: " . count($views) . "</div>";
    foreach ($views as $view) {
        echo "<div class='success'>✓ View: " . $view[0] . "</div>";
    }

    // Check procedures
    $stmt = $db->query("SHOW PROCEDURE STATUS WHERE Db = '" . getenv('DB_NAME') . "'");
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<div class='info'>Total procedures created: " . count($procedures) . "</div>";
    foreach ($procedures as $proc) {
        echo "<div class='success'>✓ Procedure: " . $proc['Name'] . "</div>";
    }

    // Check triggers
    $stmt = $db->query("SHOW TRIGGERS");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ourTriggers = array_filter($triggers, function($t) {
        return strpos($t['Trigger'], 'trg_update_') === 0;
    });
    echo "<div class='info'>Total triggers created: " . count($ourTriggers) . "</div>";
    foreach ($ourTriggers as $trigger) {
        echo "<div class='success'>✓ Trigger: " . $trigger['Trigger'] . "</div>";
    }

    echo "<h2>✅ Database Fix Completed Successfully!</h2>";
    echo "<div class='success'>";
    echo "<h3>All missing components have been created!</h3>";
    echo "<p>You can now use all the new features:</p>";
    echo "<ul>";
    echo "<li>Login/Logout tracking with notifications</li>";
    echo "<li>Budget liquidation status tracking</li>";
    echo "<li>Automatic liquidation checks</li>";
    echo "<li>User activity updates</li>";
    echo "</ul>";
    echo "</div>";

    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #1b2f73; color: white; text-decoration: none; border-radius: 5px;'>Return to Login</a></p>";

} catch (Exception $e) {
    echo "<div class='error'><h2>Fatal Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body>\n</html>";
?>
