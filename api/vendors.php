<?php
// Vendors API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start output buffering to catch any unwanted output
ob_start();

// Suppress any HTML output from errors
ini_set('display_errors', 0);
error_reporting(0);

require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/logger.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Session not found']);
    ob_end_flush();
    exit;
}
?>

<?php
$db = null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log("Database connection error in vendors API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please check your configuration.']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single vendor
                $stmt = $db->query(
                    "SELECT * FROM vendors WHERE id = ?",
                    [$_GET['id']]
                );
                $vendor = $stmt->fetch();
                echo json_encode($vendor ?: ['error' => 'Vendor not found']);
            } else {
                // Get all vendors
                $vendors = $db->select(
                    "SELECT * FROM vendors ORDER BY company_name ASC"
                );
                echo json_encode($vendors);
            }
            break;

        case 'POST':
            // Create new vendor
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                // Handle form data
                $data = $_POST;
            }

            // Validate required fields
            if (empty($data['companyName']) || empty($data['contactPerson'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Generate vendor code
            $stmt = $db->query("SELECT COUNT(*) as count FROM vendors");
            $count = $stmt->fetch()['count'] + 1;
            $vendorCode = 'V' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $vendorId = $db->insert(
                "INSERT INTO vendors (vendor_code, company_name, contact_person, email, phone, address, payment_terms, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $vendorCode,
                    $data['companyName'],
                    $data['contactPerson'],
                    $data['vendorEmail'] ?? null,
                    $data['vendorPhone'] ?? null,
                    $data['vendorAddress'] ?? null,
                    $data['paymentTerms'] ?? 'Net 30',
                    $data['vendorStatus'] ?? 'active'
                ]
            );

            // Log the action (disabled temporarily)
            // Logger::getInstance()->logUserAction('Created vendor', 'vendors', $vendorId, null, $data);

            echo json_encode(['success' => true, 'id' => $vendorId, 'vendor_code' => $vendorCode]);
            break;

        case 'PUT':
            // Update vendor
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Vendor ID required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }

            // Get old values for audit
            $oldVendor = $db->select("SELECT * FROM vendors WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldVendor[0] ?? null;

            $fields = [];
            $params = [];

            if (isset($data['companyName'])) {
                $fields[] = "company_name = ?";
                $params[] = $data['companyName'];
            }
            if (isset($data['contactPerson'])) {
                $fields[] = "contact_person = ?";
                $params[] = $data['contactPerson'];
            }
            if (isset($data['vendorEmail'])) {
                $fields[] = "email = ?";
                $params[] = $data['vendorEmail'];
            }
            if (isset($data['vendorPhone'])) {
                $fields[] = "phone = ?";
                $params[] = $data['vendorPhone'];
            }
            if (isset($data['vendorAddress'])) {
                $fields[] = "address = ?";
                $params[] = $data['vendorAddress'];
            }
            if (isset($data['paymentTerms'])) {
                $fields[] = "payment_terms = ?";
                $params[] = $data['paymentTerms'];
            }
            if (isset($data['vendorStatus'])) {
                $fields[] = "status = ?";
                $params[] = $data['vendorStatus'];
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            $params[] = $_GET['id'];
            $sql = "UPDATE vendors SET " . implode(', ', $fields) . " WHERE id = ?";

            $affected = $db->execute($sql, $params);

            // Log the action (disabled temporarily)
            // Logger::getInstance()->logUserAction('Updated vendor', 'vendors', $_GET['id'], $oldValues, $data);

            echo json_encode(['success' => $affected > 0]);
            break;

        case 'DELETE':
            // Delete vendor
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Vendor ID required']);
                exit;
            }

            // Check if vendor has bills
            $stmt = $db->query("SELECT COUNT(*) as count FROM bills WHERE vendor_id = ?", [$_GET['id']]);
            if ($stmt->fetch()['count'] > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot delete vendor with existing bills']);
                exit;
            }

            // Get old values for audit
            $oldVendor = $db->select("SELECT * FROM vendors WHERE id = ?", [$_GET['id']]);
            $oldValues = $oldVendor[0] ?? null;

            $affected = $db->execute("DELETE FROM vendors WHERE id = ?", [$_GET['id']]);

            // Log the action (disabled temporarily)
            // Logger::getInstance()->logUserAction('Deleted vendor', 'vendors', $_GET['id'], $oldValues, null);

            echo json_encode(['success' => $affected > 0]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Vendor API operation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    ob_end_flush();
    exit;
}

ob_end_flush();
?>

