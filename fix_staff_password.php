<?php
require_once 'includes/database.php';

try {
    $db = Database::getInstance();

    // Update staff user password to correct hash for 'staff123'
    $staffHash = '$2y$12$jTfU.T/XvbvjgG0OQ.2/quXAtFLiyFdwz0qURlac9J/69SfdIs9MG';

    $affected = $db->execute(
        "UPDATE users SET password_hash = ? WHERE username = ?",
        [$staffHash, 'staff']
    );

    if ($affected > 0) {
        echo "Staff user password updated successfully!\n";
        echo "Staff login: staff / staff123\n";
    } else {
        echo "No staff user found to update.\n";
    }

} catch (Exception $e) {
    echo "Error updating staff password: " . $e->getMessage() . "\n";
}
?>
