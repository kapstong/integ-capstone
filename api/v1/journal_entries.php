<?php
/**
 * ATIERA External API - Journal Entries Endpoint
 * Public API for journal entry operations
 * For use with Administrative module and external integrations
 */

require_once '../../includes/database.php';
require_once '../../includes/api_auth.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = Database::getInstance();
$apiAuth = APIAuth::getInstance();

// Authenticate API request
try {
    $client = $apiAuth->authenticate();
} catch (Exception $e) {
    // Authentication errors are handled in the authenticate method
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single journal entry
                getJournalEntry($db, $_GET['id']);
            } else if (isset($_GET['reference'])) {
                // Get journal entry by entry_number
                getJournalEntryByReference($db, $_GET['reference']);
            } else if (isset($_GET['action']) && $_GET['action'] === 'summary') {
                // Get journal entries summary for administrative reporting
                getJournalEntriesSummary($db);
            } else {
                // Get all journal entries with filters
                getJournalEntries($db);
            }
            break;

        case 'POST':
            // Create new journal entry
            createJournalEntry($db, $client);
            break;

        case 'PUT':
            // Update journal entry
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Journal entry ID required for updates'
                ]);
                exit;
            }
            updateJournalEntry($db, $_GET['id'], $client);
            break;

        case 'DELETE':
            // Delete journal entry
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Journal entry ID required for deletion'
                ]);
                exit;
            }
            deleteJournalEntry($db, $_GET['id'], $client);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->error("External API error: " . $e->getMessage(), [
        'endpoint' => 'journal_entries',
        'method' => $method,
        'client_id' => $client['id']
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}

/**
 * Get all journal entries with filters
 */
function getJournalEntries($db) {
    $where = [];
    $params = [];

    // Filter by status
    if (isset($_GET['status'])) {
        $where[] = "je.status = ?";
        $params[] = $_GET['status'];
    }

    // Filter by date range
    if (isset($_GET['date_from'])) {
        $where[] = "je.entry_date >= ?";
        $params[] = $_GET['date_from'];
    }

    if (isset($_GET['date_to'])) {
        $where[] = "je.entry_date <= ?";
        $params[] = $_GET['date_to'];
    }

    // Filter by account (in journal entry lines)
    if (isset($_GET['account_id'])) {
        $where[] = "EXISTS (SELECT 1 FROM journal_entry_lines jel WHERE jel.journal_entry_id = je.id AND jel.account_id = ?)";
        $params[] = $_GET['account_id'];
    }

    // Filter by entry number (partial match)
    if (isset($_GET['entry_number'])) {
        $where[] = "je.entry_number LIKE ?";
        $params[] = '%' . $_GET['entry_number'] . '%';
    }

    // Filter by amount range
    if (isset($_GET['min_amount'])) {
        $where[] = "je.total_debit >= ?";
        $params[] = $_GET['min_amount'];
    }

    if (isset($_GET['max_amount'])) {
        $where[] = "je.total_debit <= ?";
        $params[] = $_GET['max_amount'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Pagination
    $limit = min((int)($_GET['limit'] ?? 50), 200); // Max 200 per request
    $offset = (int)($_GET['offset'] ?? 0);

    // Include lines in response?
    $includeLines = isset($_GET['include_lines']) && $_GET['include_lines'] === 'true';

    $sql = "
        SELECT
            je.*,
            u.full_name as created_by_name,
            pb.full_name as posted_by_name,
            COUNT(jel.id) as line_count
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        LEFT JOIN users pb ON je.posted_by = pb.id
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        {$whereClause}
        GROUP BY je.id
        ORDER BY je.entry_date DESC, je.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $entries = $db->select($sql, $params);

    // Get journal entry lines for each entry if requested
    if ($includeLines) {
        foreach ($entries as &$entry) {
            $lines = $db->select(
                "SELECT jel.*, coa.account_name, coa.account_code, coa.account_type
                 FROM journal_entry_lines jel
                 JOIN chart_of_accounts coa ON jel.account_id = coa.id
                 WHERE jel.journal_entry_id = ?
                 ORDER BY jel.id ASC",
                [$entry['id']]
            );
            $entry['lines'] = $lines;
        }
    }

    // Get total count for pagination
    $countSql = "
        SELECT COUNT(DISTINCT je.id) as total
        FROM journal_entries je
        LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        {$whereClause}
    ";
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $countResult = $db->select($countSql, $countParams);
    $totalCount = $countResult[0]['total'];

    echo json_encode([
        'success' => true,
        'data' => $entries,
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ]
    ]);
}

/**
 * Get single journal entry by ID
 */
function getJournalEntry($db, $entryId) {
    $entry = $db->select(
        "SELECT
            je.*,
            u.full_name as created_by_name,
            u.email as created_by_email,
            pb.full_name as posted_by_name,
            pb.email as posted_by_email
         FROM journal_entries je
         LEFT JOIN users u ON je.created_by = u.id
         LEFT JOIN users pb ON je.posted_by = pb.id
         WHERE je.id = ?",
        [$entryId]
    );

    if (empty($entry)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Journal entry not found'
        ]);
        return;
    }

    $entry = $entry[0];

    // Get journal entry lines with account details
    $lines = $db->select(
        "SELECT jel.*,
                coa.account_name,
                coa.account_code,
                coa.account_type,
                coa.normal_balance
         FROM journal_entry_lines jel
         JOIN chart_of_accounts coa ON jel.account_id = coa.id
         WHERE jel.journal_entry_id = ?
         ORDER BY jel.id ASC",
        [$entryId]
    );

    $entry['lines'] = $lines;

    // Calculate summary statistics
    $entry['statistics'] = [
        'total_lines' => count($lines),
        'balanced' => abs(floatval($entry['total_debit']) - floatval($entry['total_credit'])) < 0.01
    ];

    echo json_encode([
        'success' => true,
        'data' => $entry
    ]);
}

/**
 * Get journal entry by reference/entry number
 */
function getJournalEntryByReference($db, $reference) {
    $entry = $db->select(
        "SELECT je.*, u.full_name as created_by_name, pb.full_name as posted_by_name
         FROM journal_entries je
         LEFT JOIN users u ON je.created_by = u.id
         LEFT JOIN users pb ON je.posted_by = pb.id
         WHERE je.entry_number = ?",
        [$reference]
    );

    if (empty($entry)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Journal entry not found'
        ]);
        return;
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

    $entry['lines'] = $lines;

    echo json_encode([
        'success' => true,
        'data' => $entry
    ]);
}

/**
 * Get journal entries summary for administrative reporting
 */
function getJournalEntriesSummary($db) {
    // Get summary by status
    $statusSummary = $db->select(
        "SELECT
            status,
            COUNT(*) as count,
            SUM(total_debit) as total_debit,
            SUM(total_credit) as total_credit
         FROM journal_entries
         GROUP BY status"
    );

    // Get summary by month (last 12 months)
    $monthlySummary = $db->select(
        "SELECT
            DATE_FORMAT(entry_date, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(total_debit) as total_debit,
            SUM(total_credit) as total_credit
         FROM journal_entries
         WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
         ORDER BY month DESC"
    );

    // Get most active accounts
    $topAccounts = $db->select(
        "SELECT
            coa.account_code,
            coa.account_name,
            coa.account_type,
            COUNT(DISTINCT jel.journal_entry_id) as entry_count,
            SUM(jel.debit) as total_debit,
            SUM(jel.credit) as total_credit
         FROM journal_entry_lines jel
         JOIN chart_of_accounts coa ON jel.account_id = coa.id
         GROUP BY jel.account_id, coa.account_code, coa.account_name, coa.account_type
         ORDER BY entry_count DESC
         LIMIT 20"
    );

    // Get overall statistics
    $stats = $db->select(
        "SELECT
            COUNT(*) as total_entries,
            SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted_count,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(total_debit) as total_debit,
            SUM(total_credit) as total_credit,
            MIN(entry_date) as earliest_entry,
            MAX(entry_date) as latest_entry
         FROM journal_entries"
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'overall_statistics' => $stats[0],
            'status_summary' => $statusSummary,
            'monthly_summary' => $monthlySummary,
            'top_accounts' => $topAccounts
        ]
    ]);
}

/**
 * Create new journal entry
 */
function createJournalEntry($db, $client) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON data'
        ]);
        return;
    }

    // Validate required fields
    $required = ['entry_date', 'description', 'lines'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => "Missing required field: {$field}"
            ]);
            return;
        }
    }

    $lines = $data['lines'];
    if (!is_array($lines) || count($lines) < 2) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'At least 2 journal entry lines required for double-entry accounting'
        ]);
        return;
    }

    // Validate that debits equal credits
    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($lines as $line) {
        if (!isset($line['account_id'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Each line must have an account_id'
            ]);
            return;
        }

        $debit = floatval($line['debit'] ?? 0);
        $credit = floatval($line['credit'] ?? 0);

        if ($debit > 0 && $credit > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Line cannot have both debit and credit'
            ]);
            return;
        }

        if ($debit == 0 && $credit == 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Line must have either debit or credit amount'
            ]);
            return;
        }

        $totalDebit += $debit;
        $totalCredit += $credit;
    }

    // Check if debits equal credits (allow 0.01 tolerance for rounding)
    if (abs($totalDebit - $totalCredit) > 0.01) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Debits must equal credits. Debit: {$totalDebit}, Credit: {$totalCredit}"
        ]);
        return;
    }

    $db->beginTransaction();

    try {
        // Generate entry number
        $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
        $count = $stmt->fetch()['count'] + 1;
        $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Allow custom entry number if provided
        if (isset($data['entry_number']) && !empty($data['entry_number'])) {
            // Check if entry number already exists
            $existing = $db->select("SELECT id FROM journal_entries WHERE entry_number = ?", [$data['entry_number']]);
            if (!empty($existing)) {
                throw new Exception("Entry number {$data['entry_number']} already exists");
            }
            $entryNumber = $data['entry_number'];
        }

        // Insert journal entry
        $entryId = $db->insert(
            "INSERT INTO journal_entries (entry_number, entry_date, description, total_debit, total_credit, status, created_by, created_via_api, api_client_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)",
            [
                $entryNumber,
                $data['entry_date'],
                $data['description'],
                $totalDebit,
                $totalCredit,
                $data['status'] ?? 'draft',
                1, // System user for API
                $client['id']
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

        // Log the action
        Logger::getInstance()->logUserAction(
            'Journal entry created via API',
            'journal_entries',
            $entryId,
            null,
            array_merge($data, ['api_client_id' => $client['id']])
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $entryId,
                'entry_number' => $entryNumber,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $data['status'] ?? 'draft'
            ]
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update journal entry
 */
function updateJournalEntry($db, $entryId, $client) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON data'
        ]);
        return;
    }

    // Check if entry exists
    $existing = $db->select("SELECT * FROM journal_entries WHERE id = ?", [$entryId]);
    if (empty($existing)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Journal entry not found'
        ]);
        return;
    }

    $currentEntry = $existing[0];

    // Only allow updates if status is draft (unless status change is allowed)
    if ($currentEntry['status'] !== 'draft' && !isset($data['force_update'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot update posted or approved entries. Use force_update=true to override.'
        ]);
        return;
    }

    $db->beginTransaction();

    try {
        $fields = [];
        $params = [];

        // Update allowed fields
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
                throw new Exception("Debits must equal credits. Debit: {$totalDebit}, Credit: {$totalCredit}");
            }

            // Delete existing lines
            $db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$entryId]);

            // Insert new lines
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

            $fields[] = "total_debit = ?";
            $fields[] = "total_credit = ?";
            $params[] = $totalDebit;
            $params[] = $totalCredit;
        }

        if (!empty($fields)) {
            $params[] = $entryId;
            $sql = "UPDATE journal_entries SET " . implode(', ', $fields) . " WHERE id = ?";
            $db->execute($sql, $params);
        }

        $db->commit();

        // Log the action
        Logger::getInstance()->logUserAction(
            'Journal entry updated via API',
            'journal_entries',
            $entryId,
            $currentEntry,
            array_merge($data, ['api_client_id' => $client['id']])
        );

        echo json_encode([
            'success' => true,
            'message' => 'Journal entry updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Delete journal entry
 */
function deleteJournalEntry($db, $entryId, $client) {
    // Check if entry exists
    $existing = $db->select("SELECT * FROM journal_entries WHERE id = ?", [$entryId]);
    if (empty($existing)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Journal entry not found'
        ]);
        return;
    }

    $currentEntry = $existing[0];

    // Only allow deletion if status is draft
    if ($currentEntry['status'] !== 'draft') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Cannot delete posted or approved entries'
        ]);
        return;
    }

    $db->beginTransaction();

    try {
        // Delete lines first
        $db->execute("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$entryId]);

        // Delete entry
        $affected = $db->execute("DELETE FROM journal_entries WHERE id = ?", [$entryId]);

        $db->commit();

        // Log the action
        Logger::getInstance()->logUserAction(
            'Journal entry deleted via API',
            'journal_entries',
            $entryId,
            $currentEntry,
            ['api_client_id' => $client['id']]
        );

        echo json_encode([
            'success' => $affected > 0,
            'message' => 'Journal entry deleted successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>

