<?php
/**
 * Users API - List Users (Authenticated)
 * 
 * @method GET
 * @response JSON array of users
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(json_encode(['status' => 'success']));
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only GET requests are supported.'
    ]));
}

try {
    require_once(__DIR__ . '/../includes/auth.php');
    require_once(__DIR__ . '/../includes/database.php');

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized'
        ]);
        exit;
    }

    $auth = new Auth();
    if (!$auth->hasRole('admin') && !$auth->hasRole('super_admin') && !$auth->hasPermission('users.view')) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Forbidden'
        ]);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    
    // Optional: Get query parameters for filtering/pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Validate pagination parameters
    $limit = min($limit, 100); // Max 100 results per page
    $limit = max($limit, 1);
    $page = max($page, 1);
    $offset = ($page - 1) * $limit;
    
    // Build query
    $query = "SELECT id, username, email, first_name, last_name, company_name, status, created_at 
              FROM users WHERE status = 'active'";
    
    $countQuery = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    
    // Add search filter if provided
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $query .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ?)";
        $countQuery .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ?)";
    }
    
    // Add pagination and sorting
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    
    // Get total count
    $countStmt = $db->prepare($countQuery);
    if (!empty($search)) {
        $countStmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $countStmt->execute();
    }
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];
    
    // Get paginated results
    $stmt = $db->prepare($query);
    if (!empty($search)) {
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    } else {
        $stmt->execute([$limit, $offset]);
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while retrieving users',
        'error_code' => 'INTERNAL_ERROR'
    ]);
    error_log('Public Users API Error: ' . $e->getMessage());
}
?>



