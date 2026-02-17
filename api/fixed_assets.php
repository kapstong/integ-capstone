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
    'GET' => 'fixed_assets.view',
    'PUT' => 'fixed_assets.edit',
    'DELETE' => 'fixed_assets.delete',
    'POST' => 'fixed_assets.create',
    'PATCH' => 'fixed_assets.edit',
]);

$userId = $_SESSION['user']['id'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['depreciation'])) {
                // Generate depreciation entries
                generateDepreciationEntries($db);
            } elseif (isset($_GET['id'])) {
                // Get single asset with related info
                $asset = $db->select(
                    "SELECT fa.*, d.dept_name as department_name, v.company_name as supplier_name,
                            coa1.account_name as asset_account_name, coa2.account_name as depreciation_account_name
                     FROM fixed_assets fa
                     LEFT JOIN departments d ON fa.department_id = d.id
                     LEFT JOIN vendors v ON fa.supplier_id = v.id
                     LEFT JOIN chart_of_accounts coa1 ON fa.asset_account_id = coa1.id
                     LEFT JOIN chart_of_accounts coa2 ON fa.depreciation_account_id = coa2.id
                     WHERE fa.id = ?",
                    [$_GET['id']]
                );

                if (empty($asset)) {
                    echo json_encode(['error' => 'Fixed asset not found']);
                    exit;
                }

                // Get depreciation schedule
                $depreciation = $db->select(
                    "SELECT * FROM asset_depreciation_schedule
                     WHERE asset_id = ?
                     ORDER BY depreciation_date DESC",
                    [$_GET['id']]
                );

                $asset[0]['depreciation_schedule'] = $depreciation;
                echo json_encode($asset[0]);
            } else {
                // Get all assets with summary info
                $assets = $db->select(
                    "SELECT fa.*, d.dept_name as department_name,
                            (fa.purchase_cost - fa.accumulated_depreciation) as current_value
                     FROM fixed_assets fa
                     LEFT JOIN departments d ON fa.department_id = d.id
                     WHERE fa.status = 'active'
                     ORDER BY fa.asset_name ASC"
                );
                echo json_encode($assets);
            }
            break;

        case 'POST':
            // Create new fixed asset
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            // Validate required fields
            if (empty($data['asset_code']) || empty($data['asset_name']) || empty($data['purchase_date']) ||
                empty($data['purchase_cost']) || empty($data['useful_life_years'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            $db->beginTransaction();

            try {
                $assetId = $db->insert(
                    "INSERT INTO fixed_assets (asset_code, asset_name, description, asset_category, purchase_date,
                                             purchase_cost, supplier_id, location, department_id, depreciation_method,
                                             useful_life_years, salvage_value, current_value, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['asset_code'],
                        $data['asset_name'],
                        $data['description'] ?? null,
                        $data['asset_category'] ?? null,
                        $data['purchase_date'],
                        $data['purchase_cost'],
                        $data['supplier_id'] ?? null,
                        $data['location'] ?? null,
                        $data['department_id'] ?? null,
                        $data['depreciation_method'] ?? 'straight_line',
                        $data['useful_life_years'],
                        $data['salvage_value'] ?? 0.00,
                        $data['purchase_cost'], // Initial current value
                        $userId
                    ]
                );

                // Auto-create depreciation schedule if requested
                if (isset($data['auto_create_schedule']) && $data['auto_create_schedule']) {
                    createDepreciationSchedule($db, $assetId, $data['purchase_date'],
                                             $data['purchase_cost'], $data['salvage_value'] ?? 0.00,
                                             $data['useful_life_years'], $data['depreciation_method'] ?? 'straight_line');
                }

                $db->commit();

                Logger::getInstance()->logUserAction('Created fixed asset', 'fixed_assets', $assetId, null, $data);
                echo json_encode(['success' => true, 'id' => $assetId]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }

            break;

        case 'PUT':
            // Update fixed asset
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Asset ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            $oldAsset = $db->select("SELECT * FROM fixed_assets WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldAsset[0] ?? null;

            $fields = [];
            $params = [];

            $updatableFields = ['asset_name', 'description', 'asset_category', 'location', 'department_id',
                              'supplier_id', 'depreciation_method', 'useful_life_years', 'salvage_value', 'status'];

            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            $params[] = $_GET['id'];
            $sql = "UPDATE fixed_assets SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            Logger::getInstance()->logUserAction('Updated fixed asset', 'fixed_assets', $_GET['id'], $oldValues, $data);
            echo json_encode(['success' => $affected > 0]);

            break;

        case 'DELETE':
            // Mark asset as disposed
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Asset ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;

            $oldAsset = $db->select("SELECT * FROM fixed_assets WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldAsset[0] ?? null;

            $db->execute(
                "UPDATE fixed_assets SET status = 'disposed', disposal_date = ?, disposal_value = ? WHERE id = ?",
                [$data['disposal_date'] ?? date('Y-m-d'), $data['disposal_value'] ?? 0.00, $_GET['id']]
            );

            Logger::getInstance()->logUserAction('Disposed fixed asset', 'fixed_assets', $_GET['id'], $oldValues, $data);
            echo json_encode(['success' => true]);

            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Fixed Asset API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

/**
 * Create depreciation schedule for an asset
 */
function createDepreciationSchedule($db, $assetId, $purchaseDate, $cost, $salvage, $life, $method = 'straight_line') {
    $depreciableAmount = $cost - $salvage;
    $monthlyDepreciation = $depreciableAmount / ($life * 12);
    $accumulatedDepreciation = 0;

    $currentDate = new DateTime($purchaseDate);

    for ($year = 1; $year <= $life; $year++) {
        for ($month = 1; $month <= 12; $month++) {
            if ($accumulatedDepreciation >= $depreciableAmount) break;

            $deprAmount = min($monthlyDepreciation, $depreciableAmount - $accumulatedDepreciation);
            $accumulatedDepreciation += $deprAmount;

            $db->insert(
                "INSERT INTO asset_depreciation_schedule (asset_id, depreciation_date, depreciation_amount, accumulated_depreciation)
                 VALUES (?, ?, ?, ?)",
                [$assetId, $currentDate->format('Y-m-d'), $deprAmount, $accumulatedDepreciation]
            );

            $currentDate->modify('+1 month');
        }
    }
}

/**
 * Generate depreciation entries for all active assets
 */
function generateDepreciationEntries($db) {
    try {
        $currentMonth = date('Y-m');
        $assets = $db->select(
            "SELECT * FROM fixed_assets
             WHERE status = 'active' AND useful_life_years > 0"
        );

        $entriesGenerated = 0;

        foreach ($assets as $asset) {
            // Check if depreciation entry already exists for current month
            $existing = $db->select(
                "SELECT id FROM asset_depreciation_schedule
                 WHERE asset_id = ? AND DATE_FORMAT(depreciation_date, '%Y-%m') = ?",
                [$asset['id'], $currentMonth]
            );

            if (empty($existing)) {
                // Calculate monthly depreciation
                $depreciableAmount = $asset['purchase_cost'] - $asset['salvage_value'];
                $monthlyDepreciation = $depreciableAmount / ($asset['useful_life_years'] * 12);

                if ($asset['accumulated_depreciation'] < $depreciableAmount) {
                    $deprAmount = min($monthlyDepreciation, $depreciableAmount - $asset['accumulated_depreciation']);
                    $newAccumulated = $asset['accumulated_depreciation'] + $deprAmount;

                    $db->insert(
                        "INSERT INTO asset_depreciation_schedule (asset_id, depreciation_date, depreciation_amount, accumulated_depreciation)
                         VALUES (?, ?, ?, ?)",
                        [$asset['id'], date('Y-m-d'), $deprAmount, $newAccumulated]
                    );

                    $entriesGenerated++;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Generated {$entriesGenerated} depreciation entries for " . date('F Y'),
            'entries_generated' => $entriesGenerated
        ]);

    } catch (Exception $e) {
        Logger::getInstance()->logDatabaseError('Depreciation Generation', $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate depreciation entries']);
    }
}
?>



