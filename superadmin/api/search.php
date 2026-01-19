<?php
/**
 * ATIERA Financial Management System - Search API
 * Handles advanced search operations and saved searches
 */

require_once '../../includes/auth.php';
require_once '../../includes/search.php';

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user']['id'];
$method = $_SERVER['REQUEST_METHOD'];

$auth = new Auth();
$searchEngine = SearchEngine::getInstance();

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'quick':
                    // Quick search for global search functionality
                    $query = $_GET['q'] ?? '';
                    $results = $searchEngine->quickSearch($query);
                    echo json_encode(['success' => true, 'results' => $results]);
                    break;

                case 'load':
                    // Load a saved search
                    if (!isset($_GET['id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Search ID required']);
                        exit;
                    }

                    $savedSearches = $searchEngine->getSavedSearches();
                    $search = null;

                    foreach ($savedSearches as $savedSearch) {
                        if ($savedSearch['id'] == $_GET['id']) {
                            $search = $savedSearch;
                            break;
                        }
                    }

                    if (!$search) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Saved search not found']);
                        exit;
                    }

                    echo json_encode(['success' => true, 'search' => $search]);
                    break;

                case 'analytics':
                    // Get search analytics
                    $days = (int)($_GET['days'] ?? 30);
                    $analytics = $searchEngine->getSearchAnalytics($days);
                    $popular = $searchEngine->getPopularSearches(10, $days);

                    echo json_encode([
                        'success' => true,
                        'analytics' => $analytics,
                        'popular' => $popular
                    ]);
                    break;

                case 'export':
                    // Export search results as CSV
                    $query = $_GET['q'] ?? '';
                    $filters = [];
                    $selectedTables = isset($_GET['tables']) ? (array)$_GET['tables'] : null;

                    // Parse filters (same as in search.php)
                    $filterKeys = ['status', 'customer_id', 'vendor_id', 'assigned_to', 'created_by', 'priority', 'total_amount_min', 'total_amount_max', 'date_from', 'date_to'];
                    foreach ($filterKeys as $key) {
                        if (!empty($_GET[$key])) {
                            if (strpos($key, '_min') !== false || strpos($key, '_max') !== false) {
                                $baseKey = str_replace(['_min', '_max'], '', $key);
                                $type = strpos($key, '_min') !== false ? 'min' : 'max';
                                $filters[$baseKey][$type] = $_GET[$key];
                            } elseif ($key === 'date_from' || $key === 'date_to') {
                                $baseKey = 'created_at';
                                $type = $key === 'date_from' ? 'from' : 'to';
                                $filters[$baseKey][$type] = $_GET[$key];
                            } else {
                                $filters[$key] = $_GET[$key];
                            }
                        }
                    }

                    $results = $searchEngine->search($query, $filters, $selectedTables, 10000); // Export up to 10k results

                    // Generate CSV
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="search_results_' . date('Y-m-d_H-i-s') . '.csv"');

                    if (!empty($results['results'])) {
                        // Output headers
                        echo "Table,Field,Value\n";

                        // Output data
                        foreach ($results['results'] as $table => $tableResults) {
                            foreach ($tableResults as $result) {
                                foreach ($result as $key => $value) {
                                    if (!is_numeric($key) && $key !== 'id') {
                                        // Format values
                                        if (is_array($value) || is_object($value)) {
                                            $value = json_encode($value);
                                        }
                                        echo '"' . addslashes($table) . '","' . addslashes($key) . '","' . addslashes($value) . "\"\n";
                                    }
                                }
                                echo "\n"; // Empty line between records
                            }
                        }
                    }
                    exit;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'POST':
            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'search':
                    // Perform advanced search
                    $query = $_POST['query'] ?? '';
                    $filters = isset($_POST['filters']) ? json_decode($_POST['filters'], true) : [];
                    $tables = isset($_POST['tables']) ? json_decode($_POST['tables'], true) : null;
                    $limit = (int)($_POST['limit'] ?? 50);
                    $offset = (int)($_POST['offset'] ?? 0);

                    $results = $searchEngine->search($query, $filters, $tables, $limit, $offset);
                    echo json_encode(['success' => true, 'data' => $results]);
                    break;

                case 'save':
                    // Save a search
                    if (empty($_POST['name'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Search name is required']);
                        exit;
                    }

                    $query = $_POST['query'] ?? '';
                    $filters = isset($_POST['filters']) ? json_decode($_POST['filters'], true) : [];
                    $tables = isset($_POST['tables']) ? json_decode($_POST['tables'], true) : [];

                    $result = $searchEngine->saveSearch($_POST['name'], $query, $filters, $tables);
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Saved search query',
                            'saved_searches',
                            $result['id'],
                            null,
                            ['name' => $_POST['name']]
                        );
                    }
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'DELETE':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'delete':
                    // Delete a saved search
                    if (!isset($_GET['id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Search ID required']);
                        exit;
                    }

                    $result = $searchEngine->deleteSavedSearch($_GET['id']);
                    if ($result['success']) {
                        Logger::getInstance()->logUserAction(
                            'Deleted saved search',
                            'saved_searches',
                            $_GET['id'],
                            null,
                            null
                        );
                    }
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Search API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>

