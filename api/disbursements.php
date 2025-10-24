<?php
/**
 * ATIERA Financial Management System - Disbursements API Endpoint
 * Handles disbursement CRUD operations via API requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // Check if user is authenticated
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    // Handle different HTTP methods
    switch ($method) {
        case 'GET':
            // List disbursements or get single disbursement
            $id = $_GET['id'] ?? null;

            if ($id) {
                // Get single disbursement
                $stmt = $db->prepare("SELECT * FROM disbursements WHERE id = ?");
                $stmt->execute([$id]);
                $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$disbursement) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Disbursement not found']);
                    exit;
                }

                echo json_encode($disbursement);
            } else {
                // List all disbursements with filters
                $whereConditions = [];
                $params = [];

                if (!empty($_GET['status'])) {
                    $whereConditions[] = "status = ?";
                    $params[] = $_GET['status'];
                }

                if (!empty($_GET['date_from'])) {
                    $whereConditions[] = "disbursement_date >= ?";
                    $params[] = $_GET['date_from'];
                }

                if (!empty($_GET['date_to'])) {
                    $whereConditions[] = "disbursement_date <= ?";
                    $params[] = $_GET['date_to'];
                }

                $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";
                $orderClause = "ORDER BY disbursement_date DESC, created_at DESC";

                $stmt = $db->prepare("SELECT * FROM disbursements $whereClause $orderClause");
                $stmt->execute($params);
                $disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($disbursements);
            }
            break;

        case 'POST':
            // Create new disbursement
            $input = json_decode(file_get_contents('php://input'), true);

            // Handle both JSON and form-encoded data
            if (!$input) {
                $input = $_POST;
            }

            $disbursement_date = $input['disbursement_date'] ?? '';
            $amount = floatval($input['amount'] ?? 0);
            $payment_method = $input['payment_method'] ?? '';
            $reference_number = $input['reference_number'] ?? '';
            $payee = $input['payee'] ?? '';
            $purpose = $input['purpose'] ?? $input['description'] ?? '';
            $notes = $input['notes'] ?? '';

            // Validate required fields
            if (!$disbursement_date || !$amount || !$payment_method || !$payee || !$purpose) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            // Generate disbursement number (unique identifier)
            $disbursement_number = 'DISB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Get default account_id (Cash account)
            $account_id = 1; // Default to account '1001' (Cash)

            // Insert disbursement record
            $stmt = $db->prepare("
                INSERT INTO disbursements (
                    disbursement_number,
                    disbursement_date,
                    payee,
                    amount,
                    payment_method,
                    reference_number,
                    purpose,
                    account_id,
                    status,
                    recorded_by,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, NOW(), NOW())
            ");

            $result = $stmt->execute([
                $disbursement_number,
                $disbursement_date,
                $payee,
                $amount,
                $payment_method,
                $reference_number ?: null,
                $purpose,
                $account_id,
                $_SESSION['user']['id']
            ]);

            if ($result) {
                // Get the auto-generated ID
                $disbursement_id = $db->lastInsertId();

                // Log disbursement creation to audit trail
                Logger::getInstance()->logUserAction(
                    'created',
                    'disbursements',
                    $disbursement_id,
                    null,
                    [
                        'disbursement_number' => $disbursement_number,
                        'payee' => $payee,
                        'amount' => $amount,
                        'payment_method' => $payment_method,
                        'purpose' => $purpose,
                        'reference_number' => $reference_number
                    ]
                );

                echo json_encode([
                    'success' => true,
                    'disbursement_id' => $disbursement_id,
                    'disbursement_number' => $disbursement_number,
                    'amount' => $amount,
                    'payee' => $payee,
                    'message' => 'Disbursement created successfully'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create disbursement']);
            }
            break;

        case 'PUT':
            // Update existing disbursement
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Disbursement ID required']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            // Build update query dynamically
            $updateFields = [];
            $params = [];

            $allowedFields = ['payee', 'amount', 'payment_method', 'reference_number', 'purpose', 'disbursement_date'];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            // Always update the updated_at timestamp
            $updateFields[] = "updated_at = NOW()";

            if (empty($updateFields)) {
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                exit;
            }

            // Check if disbursement exists and capture old values
            $stmt = $db->prepare("SELECT * FROM disbursements WHERE id = ?");
            $stmt->execute([$id]);
            $existingDisbursement = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existingDisbursement) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Disbursement not found']);
                exit;
            }

            $params[] = $id; // Add ID for WHERE clause
            $stmt = $db->prepare("UPDATE disbursements SET " . implode(", ", $updateFields) . " WHERE id = ?");

            if ($stmt->execute($params)) {
                // Log disbursement update to audit trail
                Logger::getInstance()->logUserAction(
                    'updated',
                    'disbursements',
                    $id,
                    [
                        'payee' => $existingDisbursement['payee'] ?? null,
                        'amount' => $existingDisbursement['amount'] ?? null,
                        'payment_method' => $existingDisbursement['payment_method'] ?? null,
                        'reference_number' => $existingDisbursement['reference_number'] ?? null,
                        'purpose' => $existingDisbursement['purpose'] ?? null,
                        'disbursement_date' => $existingDisbursement['disbursement_date'] ?? null
                    ],
                    $input
                );

                echo json_encode(['success' => true, 'message' => 'Disbursement updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update disbursement']);
            }
            break;

        case 'DELETE':
            // Delete disbursement
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Disbursement ID required']);
                exit;
            }

            // Check if disbursement exists and get its details for audit trail
            $stmt = $db->prepare("SELECT * FROM disbursements WHERE id = ?");
            $stmt->execute([$id]);
            $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$disbursement) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Disbursement not found']);
                exit;
            }

            // Delete the disbursement
            $stmt = $db->prepare("DELETE FROM disbursements WHERE id = ?");
            if ($stmt->execute([$id])) {
                // Log disbursement deletion to audit trail
                Logger::getInstance()->logUserAction(
                    'deleted',
                    'disbursements',
                    $id,
                    [
                        'disbursement_number' => $disbursement['disbursement_number'],
                        'payee' => $disbursement['payee'],
                        'amount' => $disbursement['amount'],
                        'payment_method' => $disbursement['payment_method'],
                        'purpose' => $disbursement['purpose'],
                        'reference_number' => $disbursement['reference_number']
                    ],
                    null
                );

                echo json_encode([
                    'success' => true,
                    'message' => 'Disbursement deleted successfully',
                    'disbursement_number' => $disbursement['disbursement_number']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete disbursement']);
            }
            break;

        case 'GET':
            // Handle approval workflow operations
            $action = $_GET['action'] ?? '';
            if ($action === 'get_approvals') {
                // Get pending approvals for current user based on their role/department
                $currentUserId = $_SESSION['user']['id'];
                $userRole = $_SESSION['user']['role'] ?? 'user';
                $userDepartment = $_SESSION['user']['department'] ?? null;

                // Build complex query to find approvals where current user is the designated approver
                $whereConditions = [];
                $params = [];

                // If user is admin, they can see all approvals
                if ($userRole === 'admin') {
                    // Admins can see all pending approvals
                } else {
                    // Filter by approver role/department
                    $approverConditions = [];

                    if ($userDepartment) {
                        $approverConditions[] = "(w.level1_department_id = ? OR w.level2_department_id = ?)";
                        $params[] = $userDepartment;
                        $params[] = $userDepartment;
                    }

                    // Add role-based conditions
                    $approverConditions[] = "(w.level1_role = ? OR w.level2_role = ?)";

                    $params[] = $userRole;
                    $params[] = $userRole;

                    $whereConditions[] = "(" . implode(" OR ", $approverConditions) . ")";
                }

                // Always filter by pending status and current approver assignment
                $whereConditions[] = "da.status = 'pending'";
                $whereConditions[] = "(da.current_approver_id = ? OR da.current_approver_id IS NULL)";

                $params[] = $currentUserId;

                $whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

                $stmt = $db->prepare("
                    SELECT
                        da.*,
                        d.disbursement_number,
                        d.payee,
                        d.amount,
                        d.purpose,
                        d.disbursement_date,
                        d.payment_method,
                        w.workflow_name,
                        w.approval_levels,
                        w.level1_role,
                        w.level1_department_id,
                        u.full_name as submitter_name,
                        ua.full_name as approver_name
                    FROM disbursement_approvals da
                    INNER JOIN disbursements d ON da.disbursement_id = d.id
                    INNER JOIN approval_workflows w ON da.workflow_id = w.id
                    INNER JOIN users u ON da.created_by = u.id
                    LEFT JOIN users ua ON da.current_approver_id = ua.id
                    $whereClause
                    ORDER BY da.created_at DESC
                ");

                $stmt->execute($params);
                $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($approvals as &$approval) {
                    $approval['amount_formatted'] = '₱' . number_format($approval['amount'], 2);
                    $approval['due_by_formatted'] = $approval['due_by'] ? date('M j, Y H:i', strtotime($approval['due_by'])) : null;
                    $approval['created_at_formatted'] = date('M j, Y', strtotime($approval['created_at']));
                }

                echo json_encode($approvals);
                exit;
            } elseif ($action === 'get_vouchers') {
                $disbursementId = $_GET['disbursement_id'] ?? null;

                // If specific disbursement ID is provided, show vouchers for that disbursement
                // Otherwise, show vouchers for disbursements created by current user in last 30 days
                $dateLimit = date('Y-m-d', strtotime('-30 days'));

                if ($disbursementId) {
                    $stmt = $db->prepare("
                        SELECT v.*,
                               d.disbursement_number,
                               u.username as uploaded_by
                        FROM disbursement_vouchers v
                        LEFT JOIN disbursements d ON v.disbursement_id = d.id
                        LEFT JOIN users u ON v.uploaded_by = u.id
                        WHERE v.disbursement_id = ? AND v.deleted_at IS NULL
                        ORDER BY v.uploaded_at DESC
                    ");
                    $stmt->execute([$disbursementId]);
                } else {
                    $stmt = $db->prepare("
                        SELECT v.*,
                               d.disbursement_number,
                               u.username as uploaded_by
                        FROM disbursement_vouchers v
                        LEFT JOIN disbursements d ON v.disbursement_id = d.id
                        LEFT JOIN users u ON v.uploaded_by = u.id
                        WHERE d.disbursement_date >= ? AND v.deleted_at IS NULL
                        ORDER BY v.uploaded_at DESC
                        LIMIT 50
                    ");
                    $stmt->execute([$dateLimit]);
                }

                $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($vouchers);
                exit;
            }
            break;

        case 'POST':
            // Handle approval actions
            $action = $_GET['action'] ?? '';
            if ($action === 'approve' || $action === 'reject') {
                $approvalId = $_GET['id'] ?? null;
                $input = json_decode(file_get_contents('php://input'), true);
                $comments = $input['comments'] ?? '';

                if (!$approvalId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Approval ID required']);
                    exit;
                }

                // Get approval details
                $stmt = $db->prepare("
                    SELECT
                        da.*,
                        d.disbursement_number,
                        d.payee,
                        d.amount,
                        w.approval_levels,
                        w.level1_role,
                        w.level2_role,
                        d.recorded_by as disbursement_creator
                    FROM disbursement_approvals da
                    INNER JOIN disbursements d ON da.disbursement_id = d.id
                    INNER JOIN approval_workflows w ON da.workflow_id = w.id
                    WHERE da.id = ?
                ");
                $stmt->execute([$approvalId]);
                $approval = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$approval) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Approval not found']);
                    exit;
                }

                // Verify user has permission to act on this approval
                $currentUserId = $_SESSION['user']['id'];
                $userRole = $_SESSION['user']['role'] ?? 'user';
                $userDepartment = $_SESSION['user']['department'] ?? null;

                $canApprove = false;

                // Admin can approve everything
                if ($userRole === 'admin') {
                    $canApprove = true;
                } elseif ($approval['current_approver_id'] == $currentUserId) {
                    $canApprove = true;
                } elseif ($approval['status'] === 'pending' && $approval['current_approver_id'] === null) {
                    // Check if user matches the required level criteria
                    $currentLevel = $approval['current_level'];
                    $levelRole = $approval["level{$currentLevel}_role"];
                    $levelDept = $approval["level{$currentLevel}_department_id"];

                    if ($levelRole && $levelRole === $userRole) {
                        $canApprove = true;
                    } elseif ($levelDept && $levelDept == $userDepartment) {
                        $canApprove = true;
                    }
                }

                if (!$canApprove) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to act on this approval']);
                    exit;
                }

                // Determine next steps based on action
                if ($action === 'approve') {
                    if ($approval['current_level'] < $approval['total_levels']) {
                        // Move to next approval level
                        $nextLevel = $approval['current_level'] + 1;
                        $updateData = [
                            'current_level' => $nextLevel,
                            'current_approver_id' => null, // Next approver needs to be assigned
                            'assigned_at' => date('Y-m-d H:i:s'),
                            'due_by' => date('Y-m-d H:i:s', strtotime('+48 hours'))
                        ];
                        $message = 'Approval escalated to next level';
                    } else {
                        // Final approval - mark as approved and update disbursement
                        $updateData = [
                            'status' => 'approved',
                            'final_approved_at' => date('Y-m-d H:i:s'),
                            'final_approved_by' => $currentUserId
                        ];

                        // Update disbursement status to approved
                        $stmt = $db->prepare("
                            UPDATE disbursements
                            SET approval_status = 'approved', needs_approval = FALSE
                            WHERE id = ?
                        ");
                        $stmt->execute([$approval['disbursement_id']]);

                        $message = 'Disbursement fully approved and ready for payment';
                    }
                } else { // reject
                    $updateData = [
                        'status' => 'rejected',
                        'rejection_reason' => $comments
                    ];

                    // Update disbursement status to rejected
                    $stmt = $db->prepare("
                        UPDATE disbursements
                        SET approval_status = 'rejected', needs_approval = FALSE
                        WHERE id = ?
                    ");
                    $stmt->execute([$approval['disbursement_id']]);

                    $message = 'Disbursement rejected';
                }

                // Update approval record
                $fields = array_keys($updateData);
                $placeholders = array_map(function($field) { return $field . ' = ?'; }, $fields);
                $stmt = $db->prepare("
                    UPDATE disbursement_approvals
                    SET " . implode(', ', $placeholders) . ", updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute(array_merge(array_values($updateData), [$approvalId]));

                // Log approval action to history
                $stmt = $db->prepare("
                    INSERT INTO approval_history (
                        disbursement_approval_id, approval_level, action, action_by,
                        action_reason, action_notes, action_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $approvalId,
                    $approval['current_level'],
                    $action,
                    $currentUserId,
                    $comments,
                    $message
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'action' => $action,
                    'next_level' => $action === 'approve' && $approval['current_level'] < $approval['total_levels'] ? $nextLevel : null
                ]);
                exit;
            } elseif (isset($_GET['action']) && $_GET['action'] === 'upload_voucher') {
                $disbursementId = $_POST['disbursement_id'] ?? 0;
                $voucherType = $_POST['voucher_type'] ?? 'receipt';

                // Check if disbursement exists
                $stmt = $db->prepare("SELECT id, disbursement_number FROM disbursements WHERE id = ?");
                $stmt->execute([$disbursementId]);
                $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$disbursement) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Disbursement not found']);
                    exit;
                }

                // Handle file upload
                if (!isset($_FILES['voucher_file']) || $_FILES['voucher_file']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                    exit;
                }

                $file = $_FILES['voucher_file'];
                $fileName = $file['name'];
                $fileTmp = $file['tmp_name'];
                $fileSize = $file['size'];

                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                $fileType = mime_content_type($fileTmp);
                if (!in_array($fileType, $allowedTypes)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images and PDF allowed.']);
                    exit;
                }

                // Validate file size (max 5MB)
                if ($fileSize > 5 * 1024 * 1024) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
                    exit;
                }

                // Create uploads directory if it doesn't exist
                $uploadDir = '../uploads/disbursements/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Generate unique filename
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueName = 'voucher_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $filePath = $uploadDir . $uniqueName;

                if (move_uploaded_file($fileTmp, $filePath)) {
                    // Insert voucher record into database
                    $stmt = $db->prepare("
                        INSERT INTO disbursement_vouchers (
                            disbursement_id,
                            file_name,
                            original_name,
                            file_path,
                            file_size,
                            file_type,
                            voucher_type,
                            uploaded_by,
                            uploaded_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $stmt->execute([
                        $disbursementId,
                        $uniqueName,
                        $fileName,
                        $filePath,
                        $fileSize,
                        $fileType,
                        $voucherType,
                        $_SESSION['user']['id']
                    ]);

                    $voucherId = $db->lastInsertId();

                    // Log voucher upload to audit trail
                    Logger::getInstance()->logUserAction(
                        'uploaded_voucher',
                        'disbursement_vouchers',
                        $voucherId,
                        null,
                        [
                            'disbursement_number' => $disbursement['disbursement_number'],
                            'file_name' => $uniqueName,
                            'original_name' => $fileName,
                            'voucher_type' => $voucherType
                        ]
                    );

                    echo json_encode([
                        'success' => true,
                        'voucher_id' => $voucherId,
                        'message' => 'Voucher uploaded successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
                }
                exit;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'System Error: ' . $e->getMessage()
    ]);
}
?>
