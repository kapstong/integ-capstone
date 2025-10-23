<?php
// Generate correct password hash for staff123
$staffPassword = 'staff123';
$staffHash = password_hash($staffPassword, PASSWORD_DEFAULT);

echo "Staff password hash for 'staff123':\n";
echo $staffHash . "\n";
?>
