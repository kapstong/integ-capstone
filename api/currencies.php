<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user']['id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single currency
                $currency = $db->select("SELECT * FROM currencies WHERE id = ?", [$_GET['id']]);
                if (empty($currency)) {
                    echo json_encode(['error' => 'Currency not found']);
                    exit;
                }
                echo json_encode($currency[0]);
            } else {
                // Get all currencies
                $currencies = $db->select("SELECT * FROM currencies ORDER BY currency_code ASC");
                echo json_encode($currencies);
            }
            break;

        case 'POST':
            // Create new currency
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            // Validate required fields
            if (empty($data['currency_code']) || empty($data['currency_name']) || empty($data['symbol'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            $currencyId = $db->insert(
                "INSERT INTO currencies (currency_code, currency_name, symbol, decimal_places, exchange_rate, is_active, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['currency_code'],
                    $data['currency_name'],
                    $data['symbol'],
                    $data['decimal_places'] ?? 2,
                    $data['exchange_rate'] ?? 1.00,
                    $data['is_active'] ?? 1,
                    $userId
                ]
            );

            Logger::getInstance()->logUserAction('Created currency', 'currencies', $currencyId, null, $data);
            echo json_encode(['success' => true, 'id' => $currencyId]);

            break;

        case 'PUT':
            // Update currency
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Currency ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            $oldCurrency = $db->select("SELECT * FROM currencies WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldCurrency[0] ?? null;

            $fields = [];
            $params = [];

            if (isset($data['currency_name'])) {
                $fields[] = "currency_name = ?";
                $params[] = $data['currency_name'];
            }
            if (isset($data['symbol'])) {
                $fields[] = "symbol = ?";
                $params[] = $data['symbol'];
            }
            if (isset($data['decimal_places'])) {
                $fields[] = "decimal_places = ?";
                $params[] = $data['decimal_places'];
            }
            if (isset($data['exchange_rate'])) {
                $fields[] = "exchange_rate = ?";
                $params[] = $data['exchange_rate'];
            }
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            $params[] = $_GET['id'];
            $sql = "UPDATE currencies SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            Logger::getInstance()->logUserAction('Updated currency', 'currencies', $_GET['id'], $oldValues, $data);
            echo json_encode(['success' => $affected > 0]);

            break;

        case 'DELETE':
            // Delete currency (only if not referenced)
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Currency ID required']);
                exit;
            }

            // Check if currency is being used
            $usageCount = $db->select("SELECT COUNT(*) as count FROM bank_accounts WHERE currency_id = ?", [$_GET['id']]);
            if ($usageCount[0]['count'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete currency that is being used by bank accounts']);
                exit;
            }

            $oldCurrency = $db->select("SELECT * FROM currencies WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldCurrency[0] ?? null;

            $affected = $db->execute("DELETE FROM currencies WHERE id = ?", [$_GET['id']]);

            Logger::getInstance()->logUserAction('Deleted currency', 'currencies', $_GET['id'], $oldValues, null);
            echo json_encode(['success' => $affected > 0]);

            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Currency API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
