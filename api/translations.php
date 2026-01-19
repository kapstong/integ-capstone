<?php
/**
 * ATIERA Financial Management System - Translation API
 * Handles translation operations and exports
 */

require_once '../../includes/auth.php';
require_once '../../includes/i18n.php';

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
$i18n = I18n::getInstance();

// Check if user has permission to manage translations
if (!$auth->hasPermission('settings.edit')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';

            switch ($action) {
                case 'stats':
                    $stats = $i18n->getTranslationStats();
                    echo json_encode(['success' => true, 'stats' => $stats]);
                    break;

                case 'missing':
                    if (!isset($_GET['language'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Language parameter required']);
                        exit;
                    }

                    $missing = $i18n->getMissingTranslations($_GET['language']);
                    echo json_encode(['success' => true, 'missing' => $missing]);
                    break;

                case 'export':
                    if (!isset($_GET['language'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Language parameter required']);
                        exit;
                    }

                    $jsonData = $i18n->exportTranslations($_GET['language']);
                    if ($jsonData !== false) {
                        header('Content-Type: application/json');
                        header('Content-Disposition: attachment; filename="translations_' . $_GET['language'] . '.json"');
                        echo $jsonData;
                        exit;
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Translation file not found']);
                        exit;
                    }
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
                    exit;
            }
            break;

        case 'POST':
            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'set_language':
                    if (!isset($_POST['language'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Language parameter required']);
                        exit;
                    }

                    if ($i18n->setLanguage($_POST['language'])) {
                        echo json_encode(['success' => true, 'message' => 'Language set successfully']);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid language']);
                    }
                    break;

                case 'add_translation':
                    if (!isset($_POST['key']) || !isset($_POST['value'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Key and value parameters required']);
                        exit;
                    }

                    $language = $_POST['language'] ?? $i18n->getCurrentLanguage();
                    if ($i18n->setTranslation($_POST['key'], $_POST['value'], $language)) {
                        Logger::getInstance()->logUserAction(
                            'Added translation via API',
                            'translations',
                            null,
                            null,
                            ['key' => $_POST['key'], 'language' => $language]
                        );
                        echo json_encode(['success' => true, 'message' => 'Translation added successfully']);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to add translation']);
                    }
                    break;

                case 'import':
                    if (!isset($_POST['language']) || !isset($_POST['data'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Language and data parameters required']);
                        exit;
                    }

                    if ($i18n->importTranslations($_POST['language'], $_POST['data'])) {
                        Logger::getInstance()->logUserAction(
                            'Imported translations via API',
                            'translations',
                            null,
                            null,
                            ['language' => $_POST['language']]
                        );
                        echo json_encode(['success' => true, 'message' => 'Translations imported successfully']);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Failed to import translations']);
                    }
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
                    if (!isset($_GET['key']) || !isset($_GET['language'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Key and language parameters required']);
                        exit;
                    }

                    // For deletion, we need to load and modify the file directly
                    $file = '../../languages/' . $_GET['language'] . '.php';
                    if (file_exists($file)) {
                        $translations = include $file;
                        if (is_array($translations) && isset($translations[$_GET['key']])) {
                            unset($translations[$_GET['key']]);
                            $content = "<?php\nreturn " . var_export($translations, true) . ";\n?>";

                            if (file_put_contents($file, $content) !== false) {
                                Logger::getInstance()->logUserAction(
                                    'Deleted translation via API',
                                    'translations',
                                    null,
                                    null,
                                    ['key' => $_GET['key'], 'language' => $_GET['language']]
                                );
                                echo json_encode(['success' => true, 'message' => 'Translation deleted successfully']);
                            } else {
                                http_response_code(500);
                                echo json_encode(['error' => 'Failed to save changes']);
                            }
                        } else {
                            http_response_code(404);
                            echo json_encode(['error' => 'Translation not found']);
                        }
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Language file not found']);
                    }
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
    Logger::getInstance()->logDatabaseError('Translation API operation', $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
