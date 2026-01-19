<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');

$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $user = $_SESSION['user'];

    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db, $user);
            break;
        case 'DELETE':
            handleDelete($db, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleGet($db) {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_trash':
            try {
                $stmt = $db->prepare("
                    SELECT
                        di.id,
                        di.table_name,
                        di.record_id,
                        di.data,
                        di.deleted_at,
                        di.auto_delete_at,
                        u.username as deleted_by_name
                    FROM deleted_items di
                    LEFT JOIN users u ON di.deleted_by = u.id
                    WHERE di.auto_delete_at > NOW()
                    ORDER BY di.deleted_at DESC
                ");
                $stmt->execute();
                $items = $stmt->fetchAll();

                // Decode JSON data for each item
                foreach ($items as &$item) {
                    $item['data'] = json_decode($item['data'], true);
                }

                echo json_encode(['success' => true, 'items' => $items]);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Get trash items', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        case 'view_item':
            $itemId = $_GET['item_id'] ?? null;
            if (!$itemId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Item ID required']);
                return;
            }

            try {
                $stmt = $db->prepare("
                    SELECT
                        di.*,
                        u.username as deleted_by_name
                    FROM deleted_items di
                    LEFT JOIN users u ON di.deleted_by = u.id
                    WHERE di.id = ? AND di.auto_delete_at > NOW()
                ");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch();

                if ($item) {
                    $item['data'] = json_decode($item['data'], true);
                    echo json_encode(['success' => true, 'item' => $item]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Item not found']);
                }
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('View trash item', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePost($db, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'restore_item':
            $itemId = $data['item_id'] ?? null;

            if (!$itemId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Item ID required']);
                return;
            }

            try {
                // Get the deleted item
                $stmt = $db->prepare("
                    SELECT * FROM deleted_items
                    WHERE id = ? AND auto_delete_at > NOW()
                ");
                $stmt->execute([$itemId]);
                $deletedItem = $stmt->fetch();

                if (!$deletedItem) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Item not found or expired']);
                    return;
                }

                $itemData = json_decode($deletedItem['data'], true);
                $tableName = $deletedItem['table_name'];

                // Remove deleted_at from the data to restore it
                unset($itemData['deleted_at']);

                // Restore based on table type
                switch ($tableName) {
                    case 'users':
                        $stmt = $db->prepare("
                            UPDATE users SET
                                deleted_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$deletedItem['record_id']]);
                        break;

                    case 'customers':
                        $stmt = $db->prepare("
                            UPDATE customers SET
                                deleted_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$deletedItem['record_id']]);
                        break;

                    case 'vendors':
                        $stmt = $db->prepare("
                            UPDATE vendors SET
                                deleted_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$deletedItem['record_id']]);
                        break;

                    case 'invoices':
                        $stmt = $db->prepare("
                            UPDATE invoices SET
                                deleted_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$deletedItem['record_id']]);
                        break;

                    case 'bills':
                        $stmt = $db->prepare("
                            UPDATE bills SET
                                deleted_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$deletedItem['record_id']]);
                        break;

                    case 'tasks':
                        $stmt = $db->prepare("
                            UPDATE tasks SET
                                deleted_at = NULL,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$deletedItem['record_id']]);
                        break;

                    default:
                        // For other tables, try generic restore
                        if ($db->columnExists($tableName, 'deleted_at')) {
                            $stmt = $db->prepare("
                                UPDATE `$tableName` SET
                                    deleted_at = NULL
                                WHERE id = ?
                            ");
                            $stmt->execute([$deletedItem['record_id']]);
                        } else {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'error' => 'Cannot restore this item type']);
                            return;
                        }
                }

                // Remove from deleted_items table
                $stmt = $db->prepare("DELETE FROM deleted_items WHERE id = ?");
                $stmt->execute([$itemId]);

                Logger::getInstance()->logUserAction(
                    'Restored deleted item',
                    $tableName,
                    $deletedItem['record_id'],
                    null,
                    ['item_type' => $tableName]
                );

                echo json_encode(['success' => true, 'message' => 'Item restored successfully']);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Restore trash item', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handleDelete($db, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'permanent_delete':
            $itemId = $data['item_id'] ?? null;

            if (!$itemId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Item ID required']);
                return;
            }

            try {
                // Get the deleted item
                $stmt = $db->prepare("SELECT * FROM deleted_items WHERE id = ?");
                $stmt->execute([$itemId]);
                $deletedItem = $stmt->fetch();

                if (!$deletedItem) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Item not found']);
                    return;
                }

                // Permanently delete the record from the original table if it still exists
                $tableName = $deletedItem['table_name'];
                $recordId = $deletedItem['record_id'];

                // Check if the record still exists in the original table
                $stmt = $db->prepare("SELECT id FROM `$tableName` WHERE id = ?");
                $stmt->execute([$recordId]);
                if ($stmt->fetch()) {
                    // Record still exists, hard delete it
                    $stmt = $db->prepare("DELETE FROM `$tableName` WHERE id = ?");
                    $stmt->execute([$recordId]);
                }

                // Remove from deleted_items table
                $stmt = $db->prepare("DELETE FROM deleted_items WHERE id = ?");
                $stmt->execute([$itemId]);

                Logger::getInstance()->logUserAction(
                    'Permanently deleted item',
                    $tableName,
                    $recordId,
                    null,
                    ['item_type' => $tableName]
                );

                echo json_encode(['success' => true, 'message' => 'Item permanently deleted']);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Permanent delete trash item', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        case 'empty_trash':
            try {
                // Get all expired items
                $stmt = $db->prepare("SELECT * FROM deleted_items WHERE auto_delete_at <= NOW()");
                $stmt->execute();
                $expiredItems = $stmt->fetchAll();

                // Permanently delete expired items from original tables
                foreach ($expiredItems as $item) {
                    $tableName = $item['table_name'];
                    $recordId = $item['record_id'];

                    // Check if the record still exists and hard delete it
                    $stmt = $db->prepare("DELETE FROM `$tableName` WHERE id = ?");
                    $stmt->execute([$recordId]);
                }

                // Remove all expired items from deleted_items table
                $stmt = $db->prepare("DELETE FROM deleted_items WHERE auto_delete_at <= NOW()");
                $stmt->execute();

                $deletedCount = count($expiredItems);

                Logger::getInstance()->logUserAction(
                    'Emptied trash (auto-delete expired items)',
                    'deleted_items',
                    null,
                    null,
                    ['items_deleted' => $deletedCount]
                );

                echo json_encode(['success' => true, 'message' => "Trash emptied. $deletedCount expired items permanently deleted."]);
            } catch (Exception $e) {
                Logger::getInstance()->logDatabaseError('Empty trash', $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
?>

