<?php
/**
 * ATIERA Financial Management System - Audit API
 * Handles audit log operations and exports
 */

require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user']['id'];
$method = $_SERVER['REQUEST_METHOD'];

$auth = new Auth();
$logger = Logger::getInstance();

// Check if user has permission to view audit logs
if (!$auth->hasPermission('audit.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'details':
                    if (!isset($_GET['id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Log ID is required']);
                        exit;
                    }

                    // Get detailed log information
                    $stmt = $db->prepare("
                        SELECT al.*, u.username, u.full_name
                        FROM audit_log al
                        LEFT JOIN users u ON al.user_id = u.id
                        WHERE al.id = ?
                    ");
                    $stmt->execute([$_GET['id']]);
                    $log = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$log) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Audit log not found']);
                        exit;
                    }

                    echo json_encode(['success' => true, 'log' => $log]);
                    break;

                case 'stats':
                    $stats = $logger->getAuditStats();
                    echo json_encode(['success' => true, 'stats' => $stats]);
                    break;

                case 'export':
                    // Export audit logs as CSV
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');

                    $filters = [
                        'user_id' => $_GET['user_id'] ?? '',
                        'action' => $_GET['action'] ?? '',
                        'table_name' => $_GET['table_name'] ?? '',
                        'date_from' => $_GET['date_from'] ?? '',
                        'date_to' => $_GET['date_to'] ?? '',
                        'ip_address' => $_GET['ip_address'] ?? ''
                    ];

                    $logs = $logger->getAuditLogs($filters, 10000); // Export up to 10k records

                    // Output CSV headers
                    echo "ID,Timestamp,User,Action,Table,Record ID,IP Address,User Agent\n";

                    // Output CSV data
                    foreach ($logs as $log) {
                        $row = [
                            $log['id'],
                            $log['created_at'],
                            $log['username'] ?: 'System',
                            $log['action'],
                            $log['table_name'] ?: '',
                            $log['record_id'] ?: '',
                            $log['ip_address'],
                            str_replace('"', '""', $log['user_agent']) // Escape quotes in CSV
                        ];
                        echo '"' . implode('","', $row) . "\"\n";
                    }
                    exit;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'POST':
            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'cleanup':
                    // Check if user has manage permission for cleanup
                    if (!$auth->hasPermission('roles.manage')) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Access denied - manage permission required']);
                        exit;
                    }

                    $deletedCount = $logger->cleanupAuditLogs(365); // Delete logs older than 1 year
                    echo json_encode([
                        'success' => true,
                        'message' => 'Audit logs cleaned up successfully',
                        'deleted_count' => $deletedCount
                    ]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Audit API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
