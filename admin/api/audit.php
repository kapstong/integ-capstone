<?php
// Audits API for Disbursements Module
if (isset($_GET['test'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'Audit API is working']);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
ob_clean();

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

function getAuditTrail($db, $filters = []) {
    try {
        $where = [];
        $params = [];

        // Filter by table
        if (isset($filters['table_name'])) {
            $where[] = "a.table_name = ?";
            $params[] = $filters['table_name'];
        }

        // Filter by user
        if (isset($filters['user_id'])) {
            $where[] = "a.user_id = ?";
            $params[] = $filters['user_id'];
        }

        // Filter by date range
        if (isset($filters['date_from'])) {
            $where[] = "a.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $where[] = "a.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        // Filter by record ID (for disbursements)
        if (isset($filters['record_id'])) {
            $where[] = "a.record_id = ?";
            $params[] = $filters['record_id'];
        }

        // No default filter - allow all tables unless specifically filtering

        $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $db->prepare("
            SELECT a.*,
                   u.username, u.full_name,
                   d.disbursement_number
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN disbursements d ON a.record_id = d.id AND a.table_name = 'disbursements'
            $whereClause
            ORDER BY a.created_at DESC
            LIMIT 1000
        ");
        $stmt->execute($params);

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the results
        foreach ($logs as &$log) {
            $log['formatted_date'] = date('M j, Y g:i A', strtotime($log['created_at']));
            $log['action_description'] = formatAction($log);
        }

        return $logs;

    } catch (Exception $e) {
        error_log("Error fetching audit trail: " . $e->getMessage());
        return [];
    }
}

function logDisbursementAction($db, $action, $recordId, $oldValues = null, $newValues = null, $userId = null) {
    try {
        $userId = $userId ?? $_SESSION['user']['id'] ?? 1;

        $stmt = $db->prepare("
            INSERT INTO audit_log (
                user_id, action, table_name, record_id, old_values, new_values,
                ip_address, user_agent
            ) VALUES (?, ?, 'disbursements', ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $action,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        return true;

    } catch (Exception $e) {
        error_log("Error logging disbursement action: " . $e->getMessage());
        return false;
    }
}

function formatAction($log) {
    $action = strtolower($log['action']);
    $table = $log['table_name'];
    $record = '';

    // Format record description based on table
    switch ($table) {
        case 'disbursements':
            $record = $log['disbursement_number'] ? "disbursement {$log['disbursement_number']}" : "disbursement ID {$log['record_id']}";
            break;
        case 'journal_entries':
            $record = "journal entry ID {$log['record_id']}";
            break;
        case 'chart_of_accounts':
            $record = "account ID {$log['record_id']}";
            break;
        default:
            $record = "record ID {$log['record_id']}";
    }

    switch ($action) {
        case 'created':
        case 'inserted':
            return "Created $record";
        case 'updated':
        case 'modified':
            return "Updated $record";
        case 'deleted':
        case 'removed':
        case 'deactivated':
            return "Deleted $record";
        case 'viewed':
            return "Viewed $record";
        case 'approved':
            return "Approved $record";
        case 'rejected':
            return "Rejected $record";
        case 'generated':
            return "Generated report";
        default:
            return ucfirst($action) . " $record";
    }
}

try {
    switch ($method) {
        case 'GET':
            $filters = [];

            // Apply filters from query parameters
            if (isset($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
            if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
            if (isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
            if (isset($_GET['record_id'])) $filters['record_id'] = $_GET['record_id'];
            if (isset($_GET['action'])) $filters['action'] = $_GET['action'];

            $auditTrail = getAuditTrail($db, $filters);
            echo json_encode($auditTrail);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Audit API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
