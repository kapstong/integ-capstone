<?php
// For API endpoints, we don't want to redirect on auth failure
// So we'll handle authentication differently
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = Database::getInstance()->getConnection();
$logger = Logger::getInstance();

// Check authentication for API calls
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $logger);
            break;
        case 'POST':
            handlePost($db, $logger);
            break;
        case 'PUT':
            handlePut($db, $logger);
            break;
        case 'DELETE':
            handleDelete($db, $logger);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    $logger->log("API Error in budgets.php: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($db, $logger) {
    try {
        $action = isset($_GET['action']) ? $_GET['action'] : null;

        switch ($action) {
            case 'allocations':
                getAllocations($db);
                break;
            case 'tracking':
                getTrackingData($db);
                break;
            case 'alerts':
                getAlerts($db);
                break;
            default:
                getBudgets($db);
        }
    } catch (Exception $e) {
        $logger->log("Error in handleGet budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch budget data']);
    }
}

function getBudgets($db) {
    // Get all budgets
    $stmt = $db->prepare("
        SELECT b.*,
               u1.full_name as created_by_name,
               u2.full_name as approved_by_name
        FROM budgets b
        LEFT JOIN users u1 ON b.created_by = u1.id
        LEFT JOIN users u2 ON b.approved_by = u2.id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each budget, get the items and calculate totals
    foreach ($budgets as &$budget) {
        $budget['start_date'] = $budget['budget_year'] . '-01-01';
        $budget['end_date'] = $budget['budget_year'] . '-12-31';
        $budget['department'] = 'All Departments'; // Default, could be enhanced
        $budget['name'] = $budget['budget_name'];
        $budget['total_amount'] = $budget['total_budgeted'];
    }

    echo json_encode(['budgets' => $budgets]);
}

function getAllocations($db) {
    // Get budget allocations by category/department
    // Since departments aren't directly in budget tables, we'll group by budget categories
    $stmt = $db->prepare("
        SELECT
            bc.category_name as department,
            SUM(bi.budgeted_amount) as total_amount,
            SUM(bi.actual_amount) as utilized_amount,
            bc.category_type
        FROM budget_items bi
        JOIN budget_categories bc ON bi.category_id = bc.id
        JOIN budgets b ON bi.budget_id = b.id
        WHERE b.status = 'active'
        GROUP BY bc.id, bc.category_name, bc.category_type
        ORDER BY bc.category_name
    ");
    $stmt->execute();
    $rawAllocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allocations = [];
    foreach ($rawAllocations as $alloc) {
        $remaining = $alloc['total_amount'] - $alloc['utilized_amount'];
        $progressPercent = $alloc['total_amount'] > 0 ? ($alloc['utilized_amount'] / $alloc['total_amount']) * 100 : 0;

        $allocations[] = [
            'id' => count($allocations) + 1, // Simple ID for frontend
            'department' => $alloc['department'],
            'total_amount' => (float)$alloc['total_amount'],
            'utilized_amount' => (float)$alloc['utilized_amount'],
            'remaining' => (float)$remaining
        ];
    }

    echo json_encode(['allocations' => $allocations]);
}

function getTrackingData($db) {
    $period = isset($_GET['period']) ? $_GET['period'] : 'year_to_date';
    $query = "
        SELECT
            bc.category_name as category,
            SUM(bi.budgeted_amount) as budget_amount,
            SUM(bi.actual_amount) as actual_amount,
            bc.category_type
        FROM budget_items bi
        JOIN budget_categories bc ON bi.category_id = bc.id
        JOIN budgets b ON bi.budget_id = b.id
        WHERE b.status = 'active'
    ";
    $params = [];

    if ($period === 'year_to_date') {
        $query .= " AND b.budget_year = YEAR(CURDATE())";
    }

    $query .= "
        GROUP BY bc.id, bc.category_name, bc.category_type
        ORDER BY bc.category_name
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $trackingData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $totalBudget = 0;
    $totalActual = 0;
    foreach ($trackingData as $item) {
        $totalBudget += $item['budget_amount'];
        $totalActual += $item['actual_amount'];
    }

    $variance = $totalActual - $totalBudget;
    $variancePercent = $totalBudget > 0 ? ($variance / $totalBudget) * 100 : 0;

    $summary = [
        'total_budget' => (float)$totalBudget,
        'actual_spent' => (float)$totalActual,
        'variance_percent' => (float)$variancePercent,
        'remaining' => (float)($totalBudget - $totalActual)
    ];

    echo json_encode([
        'tracking' => $trackingData,
        'summary' => $summary
    ]);
}

function getAlerts($db) {
    // Get departments/categories that are over budget
    $stmt = $db->prepare("
        SELECT
            bc.category_name as department,
            SUM(bi.budgeted_amount) as budgeted_amount,
            SUM(bi.actual_amount) as actual_amount,
            bc.category_type,
            b.budget_year
        FROM budget_items bi
        JOIN budget_categories bc ON bi.category_id = bc.id
        JOIN budgets b ON bi.budget_id = b.id
        WHERE b.status = 'active'
        GROUP BY bc.id, bc.category_name, bc.category_type, b.budget_year
        HAVING SUM(bi.actual_amount) > SUM(bi.budgeted_amount)
        ORDER BY (SUM(bi.actual_amount) - SUM(bi.budgeted_amount)) DESC
    ");
    $stmt->execute();
    $overBudgetItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts = [];
    foreach ($overBudgetItems as $item) {
        $overAmount = $item['actual_amount'] - $item['budgeted_amount'];
        $overPercent = $item['budgeted_amount'] > 0 ? ($overAmount / $item['budgeted_amount']) * 100 : 0;

        $alerts[] = [
            'id' => count($alerts) + 1,
            'department' => $item['department'],
            'budget_year' => $item['budget_year'],
            'budgeted_amount' => (float)$item['budgeted_amount'],
            'actual_amount' => (float)$item['actual_amount'],
            'over_amount' => (float)$overAmount,
            'over_percent' => (float)$overPercent,
            'severity' => $overPercent > 50 ? 'critical' : ($overPercent > 20 ? 'high' : 'medium'),
            'alert_date' => date('Y-m-d H:i:s')
        ];
    }

    echo json_encode(['alerts' => $alerts]);
}

function handlePost($db, $logger) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        // Validate required fields
        $required = ['name', 'start_date', 'end_date', 'total_amount'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                return;
            }
        }

        $db->beginTransaction();

        // Extract year from start_date
        $budgetYear = date('Y', strtotime($data['start_date']));

        // Insert budget
        $stmt = $db->prepare("
            INSERT INTO budgets (
                budget_year, budget_name, description, total_budgeted,
                status, created_by
            ) VALUES (?, ?, ?, ?, 'draft', ?)
        ");

        $stmt->execute([
            $budgetYear,
            $data['name'],
            $data['description'] ?? '',
            $data['total_amount'],
            $_SESSION['user']['id'] ?? 1
        ]);

        $budgetId = $db->lastInsertId();

        $db->commit();

        $logger->log("Budget created: {$data['name']}", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Budget created successfully',
            'budget_id' => $budgetId
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handlePost budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create budget']);
    }
}

function handlePut($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Budget ID is required']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        $db->beginTransaction();

        // Update budget
        $stmt = $db->prepare("
            UPDATE budgets SET
                budget_name = ?,
                description = ?,
                total_budgeted = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['total_amount'],
            $id
        ]);

        $db->commit();

        $logger->log("Budget updated: $id", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Budget updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handlePut budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update budget']);
    }
}

function handleDelete($db, $logger) {
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Budget ID is required']);
            return;
        }

        $db->beginTransaction();

        // Delete budget (cascade will delete items)
        $stmt = $db->prepare("DELETE FROM budgets WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();

        $logger->log("Budget deleted: $id", 'INFO');

        echo json_encode([
            'success' => true,
            'message' => 'Budget deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        $logger->log("Error in handleDelete budgets: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete budget']);
    }
}
?>
