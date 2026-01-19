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
                // Get single tax code
                $taxCode = $db->select("SELECT * FROM tax_codes WHERE id = ?", [$_GET['id']]);
                if (empty($taxCode)) {
                    echo json_encode(['error' => 'Tax code not found']);
                    exit;
                }
                echo json_encode($taxCode[0]);
            } else {
                // Get all tax codes
                $taxCodes = $db->select("SELECT * FROM tax_codes WHERE is_active = 1 ORDER BY tax_code ASC");
                echo json_encode($taxCodes);
            }
            break;

        case 'POST':
            // Create new tax code
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            // Validate required fields
            if (empty($data['tax_code']) || empty($data['tax_name']) || empty($data['tax_type']) || !isset($data['rate'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            $taxId = $db->insert(
                "INSERT INTO tax_codes (tax_code, tax_name, tax_type, rate, is_active, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['tax_code'],
                    $data['tax_name'],
                    $data['tax_type'],
                    $data['rate'],
                    $data['is_active'] ?? 1,
                    $data['description'] ?? null,
                    $userId
                ]
            );

            Logger::getInstance()->logUserAction('Created tax code', 'tax_codes', $taxId, null, $data);
            echo json_encode(['success' => true, 'id' => $taxId]);

            break;

        case 'PUT':
            // Update tax code
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Tax code ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            $oldTaxCode = $db->select("SELECT * FROM tax_codes WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldTaxCode[0] ?? null;

            $fields = [];
            $params = [];

            if (isset($data['tax_name'])) {
                $fields[] = "tax_name = ?";
                $params[] = $data['tax_name'];
            }
            if (isset($data['tax_type'])) {
                $fields[] = "tax_type = ?";
                $params[] = $data['tax_type'];
            }
            if (isset($data['rate'])) {
                $fields[] = "rate = ?";
                $params[] = $data['rate'];
            }
            if (isset($data['description'])) {
                $fields[] = "description = ?";
                $params[] = $data['description'];
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
            $sql = "UPDATE tax_codes SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            Logger::getInstance()->logUserAction('Updated tax code', 'tax_codes', $_GET['id'], $oldValues, $data);
            echo json_encode(['success' => $affected > 0]);

            break;

        case 'DELETE':
            // Delete tax code (only if not referenced)
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Tax code ID required']);
                exit;
            }

            // Check if tax code is being used
            $usageCount = $db->select(
                "SELECT (SELECT COUNT(*) FROM invoices WHERE tax_code_id = ?) +
                        (SELECT COUNT(*) FROM bills WHERE tax_code_id = ?) as total",
                [$_GET['id'], $_GET['id']]
            );

            if ($usageCount[0]['total'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete tax code that is being used by invoices or bills']);
                exit;
            }

            $oldTaxCode = $db->select("SELECT * FROM tax_codes WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldTaxCode[0] ?? null;

            $affected = $db->execute("DELETE FROM tax_codes WHERE id = ?", [$_GET['id']]);

            Logger::getInstance()->logUserAction('Deleted tax code', 'tax_codes', $_GET['id'], $oldValues, null);
            echo json_encode(['success' => $affected > 0]);

            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Tax Code API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
