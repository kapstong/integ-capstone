<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/csrf.php';

header('Content-Type: application/json');
session_start();

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get tasks
            if (isset($_GET['id'])) {
                // Get single task
                $stmt = $db->prepare("
                    SELECT t.*, u.username as assigned_by_name, u2.username as assigned_to_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_by = u.id
                    LEFT JOIN users u2 ON t.assigned_to = u2.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$task) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Task not found']);
                    exit;
                }

                echo json_encode(['success' => true, 'task' => $task]);
            } else {
                // Get all tasks (for admin) or user tasks
                $user_id = $_SESSION['user']['id'];
                $role = $_SESSION['user']['role'] ?? 'staff';

                if (in_array($role, ['admin', 'super_admin'], true)) {
                    $stmt = $db->prepare("
                        SELECT t.*, u.username as assigned_by_name, u2.username as assigned_to_name
                        FROM tasks t
                        LEFT JOIN users u ON t.assigned_by = u.id
                        LEFT JOIN users u2 ON t.assigned_to = u2.id
                        ORDER BY t.created_at DESC
                    ");
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare("
                        SELECT t.*, u.username as assigned_by_name, u2.username as assigned_to_name
                        FROM tasks t
                        LEFT JOIN users u ON t.assigned_by = u.id
                        LEFT JOIN users u2 ON t.assigned_to = u2.id
                        WHERE t.assigned_to = ? OR t.created_by = ?
                        ORDER BY t.created_at DESC
                    ");
                    $stmt->execute([$user_id, $user_id]);
                }

                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'tasks' => $tasks]);
            }
            break;

        case 'POST':
            // Create new task
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Task title is required']);
                exit;
            }

            $user_id = $_SESSION['user']['id'];

            $stmt = $db->prepare("
                INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, assigned_by, created_by, category, created_at, updated_at)
                VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $data['priority'] ?? 'medium',
                $data['due_date'] ?? null,
                $data['assigned_to'] ?? $user_id,
                $user_id, // assigned_by
                $user_id, // created_by
                $data['category'] ?? 'general'
            ]);

            $task_id = $db->lastInsertId();

            // Log the action
            error_log("Task created: ID {$task_id} by user {$user_id}");

            echo json_encode([
                'success' => true,
                'message' => 'Task created successfully',
                'task_id' => $task_id
            ]);
            break;

        case 'PUT':
            // Update task
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID is required']);
                exit;
            }

            $task_id = $data['id'];
            $user_id = $_SESSION['user']['id'];
            $role = $_SESSION['user']['role'] ?? 'staff';

            // Check if user can update this task
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
                exit;
            }

            // Allow update if user is admin, task creator, or assigned to the task
            if (!in_array($role, ['admin', 'super_admin'], true) && $task['created_by'] != $user_id && $task['assigned_to'] != $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to update this task']);
                exit;
            }

            $updateFields = [];
            $updateValues = [];

            if (isset($data['title'])) {
                $updateFields[] = "title = ?";
                $updateValues[] = $data['title'];
            }
            if (isset($data['description'])) {
                $updateFields[] = "description = ?";
                $updateValues[] = $data['description'];
            }
            if (isset($data['priority'])) {
                $updateFields[] = "priority = ?";
                $updateValues[] = $data['priority'];
            }
            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $updateValues[] = $data['status'];
            }
            if (isset($data['due_date'])) {
                $updateFields[] = "due_date = ?";
                $updateValues[] = $data['due_date'];
            }
            if (isset($data['category'])) {
                $updateFields[] = "category = ?";
                $updateValues[] = $data['category'];
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }

            $updateFields[] = "updated_at = NOW()";
            $updateValues[] = $task_id;

            $stmt = $db->prepare("UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $stmt->execute($updateValues);

            // Log the action
            error_log("Task updated: ID {$task_id} by user {$user_id}");

            echo json_encode([
                'success' => true,
                'message' => 'Task updated successfully'
            ]);
            break;

        case 'DELETE':
            // Delete task
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID is required']);
                exit;
            }

            $task_id = $_GET['id'];
            $user_id = $_SESSION['user']['id'];
            $role = $_SESSION['user']['role'] ?? 'staff';

            // Check if user can delete this task
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
                exit;
            }

            // Allow delete if user is admin or task creator
            if (!in_array($role, ['admin', 'super_admin'], true) && $task['created_by'] != $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to delete this task']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);

            // Log the action
            error_log("Task deleted: ID {$task_id} by user {$user_id}");

            echo json_encode([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>

