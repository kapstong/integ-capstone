// Start output buffering to catch any unwanted output
ob_start();

// Suppress any HTML output from errors - override config.php settings
ini_set('display_errors', 0);
error_reporting(0);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Include required files
    require_once '../includes/auth.php';
    require_once '../includes/database.php';

    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
} catch (Exception $e) {
    // Catch any errors from includes or database connection
    error_log("API initialization error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error occurred. Please check the logs.']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single customer
                $stmt = $db->query(
                    "SELECT * FROM customers WHERE id = ?",
                    [$_GET['id']]
                );
                $customer = $stmt->fetch();
                echo json_encode($customer ?: ['error' => 'Customer not found']);
            } else {
                // Get all customers
                $customers = $db->select(
                    "SELECT * FROM customers ORDER BY company_name ASC"
                );
                echo json_encode($customers);
            }
            break;

        case 'POST':
            // Create new customer - either forward to central customer service or enforce role restriction
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                // Handle form data
                $data = $_POST;
            }

            // Validate required fields
            if (empty($data['customerName']) || empty($data['contactPerson']) || empty($data['customerEmail'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // If integration endpoint configured, forward the creation request
            // Add optional config constants in config.php: CUSTOMER_SERVICE_URL and CUSTOMER_SERVICE_API_KEY
            if (defined('CUSTOMER_SERVICE_URL') && CUSTOMER_SERVICE_URL) {
                $url = rtrim(CUSTOMER_SERVICE_URL, '/') . '/api/customers.php';

                $ch = curl_init($url);
                $payload = json_encode([
                    'customerName' => $data['customerName'],
                    'contactPerson' => $data['contactPerson'],
                    'customerEmail' => $data['customerEmail'],
                    'customerPhone' => $data['customerPhone'] ?? null,
                    'customerAddress' => $data['customerAddress'] ?? null,
                    'creditLimit' => $data['creditLimit'] ?? 0,
                    'customerStatus' => $data['customerStatus'] ?? 'active'
                ]);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload)
                ]);

                // Optionally include API key
                if (defined('CUSTOMER_SERVICE_API_KEY') && CUSTOMER_SERVICE_API_KEY) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($payload)
                    ], ['X-API-KEY: ' . CUSTOMER_SERVICE_API_KEY]));
                }

                $response = curl_exec($ch);
                $err = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response === false || $httpCode >= 400) {
                    http_response_code(502);
                    echo json_encode(['success' => false, 'error' => 'Failed to forward to central customer service', 'detail' => $err, 'response' => $response]);
                    exit;
                }

                // Return central service response to caller
                header('Content-Type: application/json');
                echo $response;
                exit;
            }

            // No central service configured: enforce role/department restriction
            $allowedRoles = ['core1', 'core2', 'customer_admin'];
            $userRole = $_SESSION['user']['role'] ?? null;
            $userDept = $_SESSION['user']['department'] ?? null;

            if (!in_array($userRole, $allowedRoles) && !in_array($userDept, ['core1', 'core2'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Customer creation must be performed by Core1/Core2 department or an admin.']);
                exit;
            }

            // Fallback: create locally if authorized
            // Generate customer code
            $stmt = $db->query("SELECT COUNT(*) as count FROM customers");
            $count = $stmt->fetch()['count'] + 1;
            $customerCode = 'C' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $customerId = Database::getInstance()->insert(
                "INSERT INTO customers (customer_code, company_name, contact_person, email, phone, address, credit_limit, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $customerCode,
                    $data['customerName'],
                    $data['contactPerson'],
                    $data['customerEmail'],
                    $data['customerPhone'] ?? null,
                    $data['customerAddress'] ?? null,
                    $data['creditLimit'] ?? 0,
                    $data['customerStatus'] ?? 'active'
                ]
            );

            echo json_encode(['success' => true, 'id' => $customerId, 'customer_code' => $customerCode]);
            break;

        case 'PUT':
            // Update customer
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Customer ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            $fields = [];
            $params = [];

            if (isset($data['customerName'])) {
                $fields[] = "company_name = ?";
                $params[] = $data['customerName'];
            }
            if (isset($data['contactPerson'])) {
                $fields[] = "contact_person = ?";
                $params[] = $data['contactPerson'];
            }
            if (isset($data['customerEmail'])) {
                $fields[] = "email = ?";
                $params[] = $data['customerEmail'];
            }
            if (isset($data['customerPhone'])) {
                $fields[] = "phone = ?";
                $params[] = $data['customerPhone'];
            }
            if (isset($data['customerAddress'])) {
                $fields[] = "address = ?";
                $params[] = $data['customerAddress'];
            }
            if (isset($data['creditLimit'])) {
                $fields[] = "credit_limit = ?";
                $params[] = $data['creditLimit'];
            }
            if (isset($data['customerStatus'])) {
                $fields[] = "status = ?";
                $params[] = $data['customerStatus'];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            $params[] = $_GET['id'];
            $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);
            echo json_encode(['success' => $affected > 0]);
            break;

        case 'DELETE':
            // Delete customer
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Customer ID required']);
                exit;
            }

            // Check if customer has invoices
            $stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE customer_id = ?", [$_GET['id']]);
            if ($stmt->fetch()['count'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete customer with existing invoices']);
                exit;
            }

            $affected = $db->execute("DELETE FROM customers WHERE id = ?", [$_GET['id']]);
            echo json_encode(['success' => $affected > 0]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Customer API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>

