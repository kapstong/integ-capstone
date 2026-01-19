<?php
/**
 * ATIERA Financial Management System - File Upload API
 * Handles secure file uploads with validation and storage
 */

require_once '../../includes/auth.php';
require_once '../../includes/file_upload.php';
require_once '../../includes/logger.php';

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

try {
    switch ($method) {
        case 'POST':
            // Handle file upload
            if (empty($_FILES)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No files uploaded']);
                exit;
            }

            $category = $_POST['category'] ?? 'documents';
            $referenceId = isset($_POST['reference_id']) ? (int)$_POST['reference_id'] : null;
            $referenceType = $_POST['reference_type'] ?? null;

            // Validate category
            $allowedCategories = ['documents', 'invoices', 'bills', 'receipts'];
            if (!in_array($category, $allowedCategories)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid category']);
                exit;
            }

            $fileManager = FileUploadManager::getInstance();

            // Handle single file upload
            if (isset($_FILES['file'])) {
                $result = $fileManager->uploadFile($_FILES['file'], $category, $referenceId, $referenceType);

                if ($result['success']) {
                    // Log the action
                    Logger::getInstance()->logUserAction(
                        'Uploaded file',
                        'uploaded_files',
                        $result['file_id'],
                        null,
                        ['file_name' => $result['file_name'], 'category' => $category]
                    );

                    echo json_encode([
                        'success' => true,
                        'file' => $result,
                        'message' => 'File uploaded successfully'
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => $result['error']]);
                }
            }
            // Handle multiple file uploads
            elseif (isset($_FILES['files'])) {
                $files = $_FILES['files'];

                // Reorganize files array for multiple uploads
                $reorganizedFiles = [];
                foreach ($files['name'] as $key => $name) {
                    $reorganizedFiles[] = [
                        'name' => $files['name'][$key],
                        'type' => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error' => $files['error'][$key],
                        'size' => $files['size'][$key]
                    ];
                }

                $result = $fileManager->uploadMultipleFiles($reorganizedFiles, $category, $referenceId, $referenceType);

                if ($result['success']) {
                    // Log the action
                    Logger::getInstance()->logUserAction(
                        'Uploaded multiple files',
                        'uploaded_files',
                        null,
                        null,
                        ['count' => $result['total_uploaded'], 'category' => $category]
                    );

                    echo json_encode([
                        'success' => true,
                        'files' => $result['uploaded'],
                        'message' => "Successfully uploaded {$result['total_uploaded']} file(s)"
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Upload failed',
                        'details' => $result
                    ]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No valid file data received']);
            }
            break;

        case 'GET':
            // Get files by reference or all files
            $fileManager = FileUploadManager::getInstance();

            if (isset($_GET['reference_id']) && isset($_GET['reference_type'])) {
                $referenceId = (int)$_GET['reference_id'];
                $referenceType = $_GET['reference_type'];

                $files = $fileManager->getFilesByReference($referenceId, $referenceType);
                echo json_encode(['success' => true, 'files' => $files]);
            } elseif (isset($_GET['file_id'])) {
                $fileId = (int)$_GET['file_id'];
                $file = $fileManager->getFile($fileId);

                if ($file) {
                    echo json_encode(['success' => true, 'file' => $file]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'File not found']);
                }
            } elseif (isset($_GET['stats'])) {
                // Get storage statistics
                $stats = $fileManager->getStorageStats();
                echo json_encode(['success' => true, 'stats' => $stats]);
            } else {
                // Get all files (admin only)
                if (!in_array($_SESSION['user']['role'] ?? 'staff', ['admin', 'super_admin'], true)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    exit;
                }

                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("
                    SELECT uf.*, u.username as uploaded_by_name
                    FROM uploaded_files uf
                    LEFT JOIN users u ON uf.uploaded_by = u.id
                    ORDER BY uf.uploaded_at DESC
                    LIMIT 100
                ");
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'files' => $files]);
            }
            break;

        case 'DELETE':
            // Delete file
            if (!isset($_GET['file_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'File ID required']);
                exit;
            }

            $fileId = (int)$_GET['file_id'];
            $fileManager = FileUploadManager::getInstance();

            // Get file info for logging
            $file = $fileManager->getFile($fileId);
            if (!$file) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'File not found']);
                exit;
            }

            // Check permissions (user can only delete their own files, admin can delete any)
            if (!in_array($_SESSION['user']['role'] ?? 'staff', ['admin', 'super_admin'], true) && $file['uploaded_by'] != $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }

            $result = $fileManager->deleteFile($fileId);

            if ($result['success']) {
                // Log the action
                Logger::getInstance()->logUserAction(
                    'Deleted file',
                    'uploaded_files',
                    $fileId,
                    $file,
                    null
                );

                echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to delete file']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('File upload API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>

