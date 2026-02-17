<?php
// Financial Records API
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
require_once '../includes/logger.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load required files: ' . $e->getMessage()]);
    ob_end_flush();
    exit(1);
}

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
$auth = new Auth();
ensure_api_auth($method, [
    'GET' => 'view_financial_records',
    'PUT' => 'edit_journal_entries',
    'DELETE' => 'delete_journal_entries',
    'POST' => 'create_journal_entries',
    'PATCH' => 'edit_journal_entries',
]);

}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Session not found']);
        ob_end_flush();
        exit;
    }

    // Check if user has permission to access financial records
    $auth = new Auth();
    if (
        !$auth->hasPermission('view_financial_records')
        && !$auth->hasRole('admin')
        && !$auth->hasRole('super_admin')
    ) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden - Insufficient privileges']);
        exit;
    }
}
?>

<?php
$db = null;
$userId = $_SESSION['user']['id'] ?? null;

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log("Database connection error in financial records API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please check your configuration.']);
    exit;
}

try {
    if ($method !== 'GET') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Financial records are read-only. Use source modules to post entries.']);
        exit;
    }
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single financial record (journal entry)
                $entry = $db->select(
                    "SELECT je.*, u.full_name as created_by_name
                     FROM journal_entries je
                     LEFT JOIN users u ON je.created_by = u.id
                     WHERE je.id = ?",
                    [$_GET['id']]
                );

                if (empty($entry)) {
                    echo json_encode(['error' => 'Financial record not found']);
                    exit;
                }

                $entry = $entry[0];

                // Get journal entry lines
                $lines = $db->select(
                    "SELECT jel.*, coa.account_name, coa.account_code, coa.account_type
                     FROM journal_entry_lines jel
                     JOIN chart_of_accounts coa ON jel.account_id = coa.id
                     WHERE jel.journal_entry_id = ?
                     ORDER BY jel.id ASC",
                    [$_GET['id']]
                );

                $entry['lines'] = $lines;
                $entry['record_type'] = 'journal_entry';
                echo json_encode($entry);
            } else if (isset($_GET['reference'])) {
                // Get single journal entry by reference (entry_number)
                $entry = $db->select(
                    "SELECT je.*, u.full_name as created_by_name
                     FROM journal_entries je
                     LEFT JOIN users u ON je.created_by = u.id
                     WHERE je.entry_number = ?",
                    [$_GET['reference']]
                );

                if (empty($entry)) {
                    echo json_encode(['success' => false, 'error' => 'Financial record not found']);
                    exit;
                }

                $entry = $entry[0];

                // Get journal entry lines
                $lines = $db->select(
                    "SELECT jel.*, coa.account_name, coa.account_code, coa.account_type
                     FROM journal_entry_lines jel
                     JOIN chart_of_accounts coa ON jel.account_id = coa.id
                     WHERE jel.journal_entry_id = ?
                     ORDER BY jel.id ASC",
                    [$entry['id']]
                );

                $result = array_merge($entry, ['lines' => $lines, 'record_type' => 'journal_entry']);
                echo json_encode(['success' => true, 'record' => $result]);
            } else {
                // Get all financial records with filters
                $recordType = $_GET['type'] ?? 'journal_entries'; // Default to journal entries

                if ($recordType === 'journal_entries' || $recordType === 'all') {
                    // Get journal entries with filters
                    $where = [];
                    $params = [];

                    if (isset($_GET['status'])) {
                        $where[] = "je.status = ?";
                        $params[] = $_GET['status'];
                    }

                    if (isset($_GET['date_from'])) {
                        $where[] = "je.entry_date >= ?";
                        $params[] = $_GET['date_from'];
                    }

                    if (isset($_GET['date_to'])) {
                        $where[] = "je.entry_date <= ?";
                        $params[] = $_GET['date_to'];
                    }

                    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                    $entries = $db->select(
                        "SELECT je.*, u.full_name as created_by_name,
                                COUNT(jel.id) as line_count,
                                SUM(jel.debit) as total_debit,
                                SUM(jel.credit) as total_credit,
                                'journal_entry' as record_type
                         FROM journal_entries je
                         LEFT JOIN users u ON je.created_by = u.id
                         LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
                         {$whereClause}
                         GROUP BY je.id
                         ORDER BY je.created_at DESC",
                        $params
                    );
                }

                // For now, only return journal entries. Can be extended for other record types
                $records = $entries ?? [];
                echo json_encode($records);
            }
            break;

        case 'POST':
            // Create new financial record
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                $data = $_POST;
            }

            $recordType = $data['record_type'] ?? 'journal_entry';

            if ($recordType === 'journal_entry') {
                // Create journal entry
                // Validate required fields
                if (empty($data['entry_date']) || empty($data['description']) || empty($data['lines'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing required fields: entry_date, description, lines']);
                    exit;
                }

                $lines = $data['lines'];
                if (!is_array($lines) || count($lines) < 2) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'At least 2 journal lines required']);
                    exit;
                }

                // Validate that debits equal credits
                $totalDebit = 0;
                $totalCredit = 0;
                foreach ($lines as $line) {
                    $debit = floatval($line['debit'] ?? 0);
                    $credit = floatval($line['credit'] ?? 0);

                    if ($debit > 0 && $credit > 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Line cannot have both debit and credit']);
                        exit;
                    }

                    if ($debit == 0 && $credit == 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Line must have either debit or credit amount']);
                        exit;
                    }

                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }

                if (abs($totalDebit - $totalCredit) > 0.01) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Debits must equal credits']);
                    exit;
                }

                // Check permission to create journal entries
                if (
                    !$auth->hasPermission('create_journal_entries')
                    && !$auth->hasRole('admin')
                    && !$auth->hasRole('super_admin')
                ) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Insufficient privileges to create journal entries']);
                    exit;
                }

                require_once __DIR__ . '/../includes/journal_entry_number.php';
                $primaryAccountId = $lines[0]['account_id'] ?? null;
                $entryNumber = generateJournalEntryNumber($db, $primaryAccountId, $data['entry_date'] ?? date('Y-m-d'));

                $db->beginTransaction();

                try {
                    $entryId = $db->insert(
                        "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by)
                         VALUES (?, ?, ?, ?, ?, 'draft', ?)",
                        [
                            $entryNumber,
                            $data['entry_date'],
                            $data['description'],
                            $totalDebit,
                            $totalCredit,
                            $userId
                        ]
                    );

                    // Insert journal entry lines
                    foreach ($lines as $line) {
                        $db->insert(
                            "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description)
                             VALUES (?, ?, ?, ?, ?)",
                            [
                                $entryId,
                                $line['account_id'],
                                floatval($line['debit'] ?? 0),
                                floatval($line['credit'] ?? 0),
                                $line['description'] ?? null
                            ]
                        );
                    }

                    $db->commit();

                    // Log the action (disabled temporarily)
                    // Logger::getInstance()->logUserAction('Created journal entry', 'journal_entries', $entryId, null, $data);

                    echo json_encode([
                        'success' => true,
                        'id' => $entryId,
                        'entry_number' => $entryNumber,
                        'record_type' => 'journal_entry'
                    ]);

                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unsupported record type']);
            }
            break;

        case 'PUT':
            // Update financial record
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Record ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $recordType = $data['record_type'] ?? 'journal_entry';

            if ($recordType === 'journal_entry') {
                // Check permission to update journal entries
                if (
                    !$auth->hasPermission('edit_journal_entries')
                    && !$auth->hasRole('admin')
                    && !$auth->hasRole('super_admin')
                ) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Insufficient privileges to update journal entries']);
                    exit;
                }

                // Get current entry
                $currentEntry = $db->select("SELECT * FROM journal_entries WHERE id = ?", [$_GET['id']]);
                if (empty($currentEntry)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Journal entry not found']);
                    exit;
                }

                $currentEntry = $currentEntry[0];

                // Only allow updates if status is draft
                if ($currentEntry['status'] !== 'draft') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Cannot update posted or approved entries']);
                    exit;
                }

                $db->beginTransaction();

                try {
                    $fields = [];
                    $params = [];

                    if (isset($data['entry_date'])) {
                        $fields[] = "entry_date = ?";
                        $params[] = $data['entry_date'];
                    }
                    if (isset($data['description'])) {
                        $fields[] = "description = ?";
                        $params[] = $data['description'];
                    }
                    if (isset($data['status']) && in_array($data['status'], ['draft', 'approved', 'posted'])) {
                        $fields[] = "status = ?";
                        $params[] = $data['status'];
                    }

                    // Update lines if provided
                    if (isset($data['lines'])) {
                        $lines = $data['lines'];

                        // Validate lines
                        $totalDebit = 0;
                        $totalCredit = 0;
                        foreach ($lines as $line) {
                            $debit = floatval($line['debit'] ?? 0);
                            $credit = floatval($line['credit'] ?? 0);

                            if ($debit > 0 && $credit > 0) {
                                throw new Exception('Line cannot have both debit and credit');
                            }

                            $totalDebit += $debit;
                            $totalCredit += $credit;
                        }

                        if (abs($totalDebit - $totalCredit) > 0.01) {
                            throw new Exception('Debits must equal credits');
                        }

                        // Delete existing lines
                        $db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$_GET['id']]);

                        // Insert new lines
                        foreach ($lines as $line) {
                            $db->insert(
                                "INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, description)
                                 VALUES (?, ?, ?, ?, ?)",
                                [
                                    $_GET['id'],
                                    $line['account_id'],
                                    floatval($line['debit'] ?? 0),
                                    floatval($line['credit'] ?? 0),
                                    $line['description'] ?? null
                                ]
                            );
                        }

                        $fields[] = "total_debit = ?";
                        $fields[] = "total_credit = ?";
                        $params[] = $totalDebit;
                        $params[] = $totalCredit;
                    }

                    if (!empty($fields)) {
                        $params[] = $_GET['id'];
                        $sql = "UPDATE journal_entries SET " . implode(', ', $fields) . " WHERE id = ?";
                        $db->execute($sql, $params);
                    }

                    $db->commit();

                    // Log the action (disabled temporarily)
                    // Logger::getInstance()->logUserAction('Updated journal entry', 'journal_entries', $_GET['id'], $currentEntry, $data);

                    echo json_encode(['success' => true, 'record_type' => 'journal_entry']);

                } catch (Exception $e) {
                    $db->rollback();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unsupported record type']);
            }
            break;

        case 'DELETE':
            // Delete financial record
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Record ID required']);
                exit;
            }

            $recordType = $_GET['type'] ?? 'journal_entry';

            if ($recordType === 'journal_entry') {
                // Check permission to delete journal entries
                if (
                    !$auth->hasPermission('delete_journal_entries')
                    && !$auth->hasRole('admin')
                    && !$auth->hasRole('super_admin')
                ) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Insufficient privileges to delete journal entries']);
                    exit;
                }

                // Get current entry
                $currentEntry = $db->select("SELECT * FROM journal_entries WHERE id = ?", [$_GET['id']]);
                if (empty($currentEntry)) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Journal entry not found']);
                    exit;
                }

                $currentEntry = $currentEntry[0];

                // Only allow deletion if status is draft
                if ($currentEntry['status'] !== 'draft') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Cannot delete posted or approved entries']);
                    exit;
                }

                $db->beginTransaction();

                try {
                    // Delete lines first
                    $db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$_GET['id']]);

                    // Delete entry
                    $affected = $db->execute("DELETE FROM journal_entries WHERE id = ?", [$_GET['id']]);

                    $db->commit();

                    // Log the action (disabled temporarily)
                    // Logger::getInstance()->logUserAction('Deleted journal entry', 'journal_entries', $_GET['id'], $currentEntry, null);

                    echo json_encode(['success' => $affected > 0, 'record_type' => 'journal_entry']);

                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unsupported record type']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Financial records API operation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}

ob_end_flush();
?>


