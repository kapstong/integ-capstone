<?php
// Trash API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start output buffering to catch any unwanted output
ob_start();

// Suppress any HTML output from errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set up error handler to catch and output errors as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $errstr]);
    ob_end_flush();
    exit(1);
}, E_ALL);

// Set up exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception: ' . $exception->getMessage()]);
    ob_end_flush();
    exit(1);
});

try {
require_once '../includes/auth.php';
require_once '../includes/database.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load required files: ' . $e->getMessage()]);
    ob_end_flush();
    exit(1);
}
require_once '../includes/logger.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    // Check if user is logged in and has admin privileges
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Session not found']);
        ob_end_flush();
        exit;
    }

    // Check if user has admin role or permission to manage trash
    $auth = new Auth();
    if (!$auth->hasRole('admin') && !$auth->hasRole('super_admin') && !$auth->hasPermission('settings.edit')) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - Insufficient privileges']);
        exit;
    }
}

$db = null;

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log("Database connection error in trash API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please check your configuration.']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'get_trash':
                    // Get all trash items
                    $stmt = $db->query("
                        SELECT t.id, t.table_name, t.record_id, t.deleted_by, t.deleted_at, t.auto_delete_at,
                               u.full_name as deleted_by_name
                        FROM trash t
                        LEFT JOIN users u ON t.deleted_by = u.id
                        ORDER BY t.deleted_at DESC
                    ");
                    $items = $stmt->fetchAll();

                    echo json_encode(['success' => true, 'items' => $items]);
                    break;

                case 'view_item':
                    $itemId = $_GET['item_id'] ?? null;
                    if (!$itemId) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Item ID required']);
                        exit;
                    }

                    $stmt = $db->query("SELECT * FROM trash WHERE id = ?", [$itemId]);
                    $item = $stmt->fetch();

                    if (!$item) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Item not found']);
                        exit;
                    }

                    echo json_encode(['success' => true, 'item' => $item]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    break;
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $action = $data['action'] ?? '';

            switch ($action) {
                case 'restore_item':
                    $itemId = $data['item_id'] ?? null;
                    if (!$itemId) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Item ID required']);
                        exit;
                    }

                    // Get trash item
                    $stmt = $db->query("SELECT * FROM trash WHERE id = ?", [$itemId]);
                    $trashItem = $stmt->fetch();

                    if (!$trashItem) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Trash item not found']);
                        exit;
                    }

                    // Note: Restore functionality would require storing the original data
                    // For now, we'll just remove from trash
                    // In a full implementation, you'd restore the data to the original table

                    $affected = $db->execute("DELETE FROM trash WHERE id = ?", [$itemId]);

                    // Log the action
                    Logger::getInstance()->logUserAction('Restored item from trash', 'trash', $itemId, $trashItem, null);

                    echo json_encode(['success' => $affected > 0]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    break;
            }
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $action = $data['action'] ?? '';

            switch ($action) {
                case 'permanent_delete':
                    $itemId = $data['item_id'] ?? null;
                    if (!$itemId) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Item ID required']);
                        exit;
                    }

                    // Get trash item for logging
                    $stmt = $db->query("SELECT * FROM trash WHERE id = ?", [$itemId]);
                    $trashItem = $stmt->fetch();

                    if (!$trashItem) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Trash item not found']);
                        exit;
                    }

                    $affected = $db->execute("DELETE FROM trash WHERE id = ?", [$itemId]);

                    // Log the action
                    Logger::getInstance()->logUserAction('Permanently deleted item from trash', 'trash', $itemId, $trashItem, null);

                    echo json_encode(['success' => $affected > 0]);
                    break;

                case 'empty_trash':
                    // Get all items for logging
                    $stmt = $db->query("SELECT COUNT(*) as count FROM trash");
                    $count = $stmt->fetch()['count'];

                    $affected = $db->execute("DELETE FROM trash");

                    // Log the action
                    Logger::getInstance()->logUserAction('Emptied trash', 'trash', null, ['items_deleted' => $count], null);

                    echo json_encode(['success' => true, 'items_deleted' => $count]);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    break;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Trash API operation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

ob_end_flush();
?>
