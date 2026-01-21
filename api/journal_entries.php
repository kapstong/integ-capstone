<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';

header('Content-Type: application/json');

// Only start session if not already started (handles AJAX calls)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    if ($method !== 'GET') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Journal entries are read-only. Use source modules to post entries.']);
        exit;
    }
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single journal entry with lines
                $entry = $db->select(
                    "SELECT je.*, u.full_name as created_by_name
                     FROM journal_entries je
                     LEFT JOIN users u ON je.created_by = u.id
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
                    echo json_encode(['success' => false, 'error' => 'Journal entry not found']);
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

                echo json_encode(['success' => true, 'journal_entry' => array_merge($entry, ['lines' => $lines])]);
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
        case 'PUT':
        case 'DELETE':
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Journal entries are read-only. Use source modules to post entries.']);
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

