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
$auth = new Auth();
ensure_api_auth($method, [
    'GET' => 'bank_accounts.view',
    'POST' => 'bank_accounts.create',
    'PUT' => 'bank_accounts.edit',
    'PATCH' => 'bank_accounts.edit',
    'DELETE' => 'bank_accounts.delete',
]);

$userId = $_SESSION['user']['id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single bank account with currency info
                $account = $db->select(
                    "SELECT ba.*, c.currency_code, c.currency_name, c.symbol
                     FROM bank_accounts ba
                     JOIN currencies c ON ba.currency_id = c.id
                     WHERE ba.id = ?",
                    [$_GET['id']]
                );
                if (empty($account)) {
                    echo json_encode(['error' => 'Bank account not found']);
                    exit;
                }
                echo json_encode($account[0]);
            } else {
                // Get all bank accounts with balances
                $accounts = $db->select(
                    "SELECT ba.*, c.currency_code, c.currency_name, c.symbol,
                            (ba.current_balance - ba.opening_balance) as balance_change
                     FROM bank_accounts ba
                     JOIN currencies c ON ba.currency_id = c.id
                     WHERE ba.is_active = 1
                     ORDER BY ba.account_name ASC"
                );
                echo json_encode($accounts);
            }
            break;

        case 'POST':
            // Create new bank account
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            // Validate required fields
            if (empty($data['account_number']) || empty($data['account_name']) || empty($data['bank_name']) || empty($data['account_type'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            $accountId = $db->insert(
                "INSERT INTO bank_accounts (account_number, account_name, bank_name, branch_name, account_type,
                                          currency_id, opening_balance, current_balance, reconciliation_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['account_number'],
                    $data['account_name'],
                    $data['bank_name'],
                    $data['branch_name'] ?? null,
                    $data['account_type'],
                    $data['currency_id'] ?? 1,
                    $data['opening_balance'] ?? 0.00,
                    $data['current_balance'] ?? $data['opening_balance'] ?? 0.00,
                    $data['reconciliation_date'] ?? null,
                    $userId
                ]
            );

            Logger::getInstance()->logUserAction('Created bank account', 'bank_accounts', $accountId, null, $data);
            echo json_encode(['success' => true, 'id' => $accountId]);

            break;

        case 'PUT':
            // Update bank account
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bank account ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            $oldAccount = $db->select("SELECT * FROM bank_accounts WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldAccount[0] ?? null;

            $fields = [];
            $params = [];

            if (isset($data['account_name'])) {
                $fields[] = "account_name = ?";
                $params[] = $data['account_name'];
            }
            if (isset($data['bank_name'])) {
                $fields[] = "bank_name = ?";
                $params[] = $data['bank_name'];
            }
            if (isset($data['branch_name'])) {
                $fields[] = "branch_name = ?";
                $params[] = $data['branch_name'];
            }
            if (isset($data['account_type'])) {
                $fields[] = "account_type = ?";
                $params[] = $data['account_type'];
            }
            if (isset($data['currency_id'])) {
                $fields[] = "currency_id = ?";
                $params[] = $data['currency_id'];
            }
            if (isset($data['current_balance'])) {
                $fields[] = "current_balance = ?";
                $params[] = $data['current_balance'];
            }
            if (isset($data['reconciliation_date'])) {
                $fields[] = "reconciliation_date = ?";
                $params[] = $data['reconciliation_date'];
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
            $sql = "UPDATE bank_accounts SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            Logger::getInstance()->logUserAction('Updated bank account', 'bank_accounts', $_GET['id'], $oldValues, $data);
            echo json_encode(['success' => $affected > 0]);

            break;

        case 'DELETE':
            // Soft delete - set inactive
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Bank account ID required']);
                exit;
            }

            // Check if account has transactions
            $transactionCount = $db->select(
                "SELECT (SELECT COUNT(*) FROM payments_made WHERE bank_account_id = ?) +
                        (SELECT COUNT(*) FROM payments_received WHERE bank_account_id = ?) +
                        (SELECT COUNT(*) FROM disbursements WHERE bank_account_id = ?) as total",
                [$_GET['id'], $_GET['id'], $_GET['id']]
            );

            if ($transactionCount[0]['total'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete bank account with existing transactions']);
                exit;
            }

            $oldAccount = $db->select("SELECT * FROM bank_accounts WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldAccount[0] ?? null;

            $affected = $db->execute("UPDATE bank_accounts SET is_active = 0 WHERE id = ?", [$_GET['id']]);

            Logger::getInstance()->logUserAction('Deactivated bank account', 'bank_accounts', $_GET['id'], $oldValues, ['is_active' => 0]);
            echo json_encode(['success' => $affected > 0]);

            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Bank Account API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>

