<?php
/**
 * Automatic Trash Cleanup Script
 * This script should be run periodically (e.g., daily) to clean up expired deleted items
 */

require_once '../includes/database.php';
require_once '../includes/logger.php';

$db = Database::getInstance()->getConnection();

try {
    // Get expired items that are past their auto-delete date
    $stmt = $db->prepare("
        SELECT * FROM deleted_items
        WHERE auto_delete_at <= NOW()
    ");
    $stmt->execute();
    $expiredItems = $stmt->fetchAll();

    $deletedCount = 0;

    // Permanently delete each expired item from its original table
    foreach ($expiredItems as $item) {
        $tableName = $item['table_name'];
        $recordId = $item['record_id'];

        try {
            // Check if the record still exists in the original table
            $stmt = $db->prepare("SELECT id FROM `$tableName` WHERE id = ?");
            $stmt->execute([$recordId]);

            if ($stmt->fetch()) {
                // Record still exists, hard delete it
                $stmt = $db->prepare("DELETE FROM `$tableName` WHERE id = ?");
                $stmt->execute([$recordId]);
            }

            // Remove from deleted_items table
            $stmt = $db->prepare("DELETE FROM deleted_items WHERE id = ?");
            $stmt->execute([$item['id']]);

            $deletedCount++;
        } catch (Exception $e) {
            // Log error but continue with other items
            Logger::getInstance()->logDatabaseError(
                'Auto-delete expired trash item',
                $e->getMessage() . " (Table: $tableName, ID: $recordId)"
            );
        }
    }

    // Log the cleanup operation
    if ($deletedCount > 0) {
        Logger::getInstance()->logSystemEvent(
            'Automatic trash cleanup completed',
            ['items_deleted' => $deletedCount]
        );

        echo "Trash cleanup completed. $deletedCount expired items permanently deleted.\n";
    } else {
        echo "Trash cleanup completed. No expired items to delete.\n";
    }

} catch (Exception $e) {
    Logger::getInstance()->logDatabaseError('Trash cleanup script', $e->getMessage());
    echo "Error during trash cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Script executed successfully at " . date('Y-m-d H:i:s') . "\n";
?>

