<?php
echo "Current directory: " . __DIR__ . "\n";
echo "File exists: " . (file_exists('../../includes/database.php') ? 'yes' : 'no') . "\n";
echo "File exists logger: " . (file_exists('../../includes/logger.php') ? 'yes' : 'no') . "\n";
?>
