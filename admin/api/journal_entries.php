<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');
session_start();

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
                // Get single journal entry with lines
                $entry = $db->select(
                    "SELECT je.*, u.full_name as created_by_name, ua.full_name as approved_by_name,
                            ub.full_name as posted_by_name
                     FROM journal_entries je
                     LEFT JOIN users u ON je.created_by = u.id
                     LEFT JOIN users ua ON je.approved_by = ua.id
                     LEFT JOIN users ub ON je.posted_by = ub.id
                     WHERE je.id = ?",
                    [$_GET['id']]
                );

                if (empty($entry)) {
                    echo json_encode(['error' => 'Journal entry not found']);
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
                echo json_encode($entry);
            } else {
                // Get all journal entries with filters
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
                            SUM(jel.credit) as total_credit
                     FROM journal_entries je
                     LEFT JOIN users u ON je.created_by = u.id
                     LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
                     {$whereClause}
                     GROUP BY je.id
                     ORDER BY je.created_at DESC",
                    $params
                );

                echo json_encode($entries);
            }
            break;

        case 'POST':
            // Create new journal entry
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                $data = $_POST;
            }

            // Validate required fields
            if (empty($data['entry_date']) || empty($data['description']) || empty($data['lines'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
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

            // Generate entry number
            $stmt = $db->query("SELECT COUNT(*) as count FROM journal_entries WHERE YEAR(created_at) = YEAR(CURDATE())");
            $count = $stmt->fetch()['count'] + 1;
            $entryNumber = 'JE-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

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

                // Log the action
                Logger::getInstance()->logUserAction('Created journal entry', 'journal_entries', $entryId, null, $data);

                echo json_encode([
                    'success' => true,
                    'id' => $entryId,
                    'entry_number' => $entryNumber
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        case 'PUT':
            // Update journal entry
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Journal entry ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
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

                    if ($data['status'] === 'posted') {
                        $fields[] = "posted_by = ?";
                        $fields[] = "posted_at = NOW()";
                        $params[] = $userId;
                    } elseif ($data['status'] === 'approved') {
                        $fields[] = "approved_by = ?";
                        $params[] = $userId;
                    }
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

                // Log the action
                Logger::getInstance()->logUserAction('Updated journal entry', 'journal_entries', $_GET['id'], $currentEntry, $data);

                echo json_encode(['success' => true]);

            } catch (Exception $e) {
                $db->rollback();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            break;

        case 'DELETE':
            // Delete journal entry
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Journal entry ID required']);
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

                // Log the action
                Logger::getInstance()->logUserAction('Deleted journal entry', 'journal_entries', $_GET['id'], $currentEntry, null);

                echo json_encode(['success' => $affected > 0]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Journal Entry API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
