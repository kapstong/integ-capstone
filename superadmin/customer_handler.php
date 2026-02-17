<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is authenticated
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'read';
$id = $_GET['id'] ?? null;

// Handle different actions
switch ($action) {
    case 'read':
        if ($method === 'GET') {
            handleReadCustomers($db);
        }
        break;

    case 'create':
        if ($method === 'POST') {
            handleCreateCustomer($db);
        }
        break;

    case 'update':
        if ($method === 'POST') {
            handleUpdateCustomer($db, $id);
        }
        break;

    case 'delete':
        if ($method === 'DELETE' && $id) {
            handleDeleteCustomer($db, $id);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function handleReadCustomers($db) {
    try {
        if (isset($_GET['id'])) {
            // Get single customer
            $customers = $db->select("SELECT * FROM customers WHERE id = ?", [$_GET['id']]);
            $customer = $customers[0] ?? null;
            echo json_encode($customer);
        } else {
            // Get all customers
            $customers = $db->select("SELECT * FROM customers ORDER BY company_name ASC");
            echo json_encode(['success' => true, 'data' => $customers]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleCreateCustomer($db) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['company_name', 'contact_person', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ucfirst($field) . ' is required']);
                return;
            }
        }

        // Generate customer code if not provided
        if (empty($data['customer_code'])) {
            $data['customer_code'] = 'CUST-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Insert customer
        $customerId = $db->insert(
            "INSERT INTO customers (customer_code, company_name, contact_person, email, phone, address, credit_limit, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['customer_code'],
                $data['company_name'],
                $data['contact_person'],
                $data['email'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['credit_limit'] ?? 0,
                $data['status'] ?? 'active'
            ]
        );

        echo json_encode(['success' => true, 'id' => $customerId, 'message' => 'Customer created successfully']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleUpdateCustomer($db, $id) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['company_name', 'contact_person', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => ucfirst($field) . ' is required']);
                return;
            }
        }

        // Update customer
        $rows = $db->execute(
            "UPDATE customers SET company_name = ?, contact_person = ?, email = ?, phone = ?,
             address = ?, credit_limit = ?, status = ?, updated_at = NOW() WHERE id = ?",
            [
                $data['company_name'],
                $data['contact_person'],
                $data['email'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['credit_limit'] ?? 0,
                $data['status'] ?? 'active',
                $id
            ]
        );

        if ($rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleDeleteCustomer($db, $id) {
    try {
        // Check if customer has associated records
        $invoices = $db->select("SELECT COUNT(*) as count FROM invoices WHERE customer_id = ?", [$id]);
        $payments = $db->select("SELECT COUNT(*) as count FROM payments_received WHERE customer_id = ?", [$id]);

        if (($invoices[0]['count'] ?? 0) > 0 || ($payments[0]['count'] ?? 0) > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete customer with associated transactions']);
            return;
        }

        // Delete customer
        $rows = $db->execute("DELETE FROM customers WHERE id = ?", [$id]);

        if ($rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>




<?php include '../includes/csrf_auto_form.php'; ?>

