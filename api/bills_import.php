<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/logger.php';
    require_once __DIR__ . '/../includes/coa_validation.php';

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $auth = new Auth();
    if (!$auth->hasPermission('bills.create')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: insufficient permissions']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    if (empty($_FILES['bills_file']) || $_FILES['bills_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['bills_file']['tmp_name'];
    $mime = mime_content_type($file);

    // Only process CSV files
    $allowed = ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'text/comma-separated-values'];
    if (!in_array($mime, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported file type: expected CSV']);
        exit;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $handle = fopen($file, 'r');
    if ($handle === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to open uploaded file']);
        exit;
    }

    // Expect header row with columns: vendor_id,bill_date,due_date,amount,description,account_id(optional)
    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Empty CSV file']);
        exit;
    }

    $cols = array_map('trim', $header);
    $requiredCols = ['vendor_id', 'bill_date', 'due_date', 'amount'];
    foreach ($requiredCols as $rc) {
        if (!in_array($rc, $cols)) {
            fclose($handle);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required column: $rc"]);
            exit;
        }
    }

    $colIndex = array_flip($cols);
    $created = 0;
    $errors = [];
    $lineNum = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        if (count($row) < count($cols)) {
            $errors[] = "Line $lineNum: column count mismatch";
            continue;
        }

        $data = [];
        foreach ($cols as $i => $colName) {
            $data[$colName] = trim($row[$i]);
        }

        // Basic validation
        if (empty($data['vendor_id']) || empty($data['bill_date']) || empty($data['due_date']) || empty($data['amount'])) {
            $errors[] = "Line $lineNum: missing required fields";
            continue;
        }

        // Account requirement: frontend/back-end Bills API requires account_id for simple amount creation
        $accountId = $data['account_id'] ?? null;
        if (empty($accountId)) {
            $errors[] = "Line $lineNum: account_id required for simple bill creation";
            continue;
        }

        // Validate account
        $invalidAccounts = findInvalidChartOfAccountsIds($conn, [$accountId]);
        if (!empty($invalidAccounts)) {
            $errors[] = "Line $lineNum: invalid account id $accountId";
            continue;
        }

        // Parse amounts and dates
        $amount = floatval(str_replace([',',' '], ['', ''], $data['amount']));
        $billDate = date('Y-m-d', strtotime($data['bill_date']));
        $dueDate = date('Y-m-d', strtotime($data['due_date']));

        // Generate bill number
        $stmt = $conn->query("SELECT COUNT(*) as count FROM bills WHERE YEAR(created_at) = YEAR(CURDATE())");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
        $billNumber = 'BILL-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Prepare bill and item
        $taxRate = 12.00;
        $totalAmount = $amount;
        $subtotal = $totalAmount / (1 + ($taxRate / 100));
        $taxAmount = $totalAmount - $subtotal;

        try {
            $db->beginTransaction();

            $billId = $db->insert(
                "INSERT INTO bills (bill_number, vendor_id, bill_date, due_date, subtotal, tax_rate, tax_amount, total_amount, balance, status, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $billNumber,
                    $data['vendor_id'],
                    $billDate,
                    $dueDate,
                    $subtotal,
                    $taxRate,
                    $taxAmount,
                    $totalAmount,
                    $totalAmount,
                    'draft',
                    $data['description'] ?? null,
                    $_SESSION['user']['id']
                ]
            );

            $db->insert(
                "INSERT INTO bill_items (bill_id, description, quantity, unit_price, line_total, account_id)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $billId,
                    $data['description'] ?? 'Imported bill',
                    1,
                    $subtotal,
                    $subtotal,
                    $accountId
                ]
            );

            $db->commit();
            $created++;
            Logger::getInstance()->logUserAction('Imported bill via CSV', 'bills', $billId, null, $data);
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = "Line $lineNum: " . $e->getMessage();
        }
    }

    fclose($handle);

    echo json_encode(['success' => true, 'created' => $created, 'errors' => $errors]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}




