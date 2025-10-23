<?php
echo "Current directory: " . __DIR__ . "\n";
echo "File exists: " . (file_exists('includes/database.php') ? 'yes' : 'no') . "\n";
echo "File exists relative: " . (file_exists('../../includes/database.php') ? 'yes' : 'no') . "\n";
?>
