<?php
/**
 * Debug Session Info
 * Check what's in your session
 */

session_start();

echo "<!DOCTYPE html><html><head><title>Session Debug</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;} pre{background:#fff;padding:20px;border-radius:8px;border:1px solid #ddd;} h1{color:#1b2f73;}</style>";
echo "</head><body>";
echo "<h1>Session Debug Information</h1>";

echo "<h2>Session Status</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "\n";
echo "</pre>";

echo "<h2>$_SESSION Contents</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>User Info</h2>";
echo "<pre>";
if (isset($_SESSION['user'])) {
    echo "User is logged in: YES\n";
    echo "Username: " . ($_SESSION['user']['username'] ?? 'N/A') . "\n";
    echo "Role: " . ($_SESSION['user']['role'] ?? 'N/A') . "\n";
    echo "User ID: " . ($_SESSION['user']['id'] ?? 'N/A') . "\n";
} else {
    echo "User is logged in: NO\n";
}
echo "</pre>";

echo "<h2>Authentication Check Results</h2>";
echo "<pre>";

// Check what would happen with our auth checks
$canAccess = false;
$reason = '';

if (!isset($_SESSION['user'])) {
    $reason = "Session['user'] is not set - would redirect to ../index.php";
} elseif (!isset($_SESSION['user']['role'])) {
    $reason = "Session['user']['role'] is not set - would redirect to index.php";
} elseif ($_SESSION['user']['role'] !== 'admin') {
    $reason = "User role is '{$_SESSION['user']['role']}' (not admin) - would redirect to index.php";
} else {
    $canAccess = true;
    $reason = "All checks passed! User can access confidential_mode_settings.php";
}

echo "Can Access Confidential Settings: " . ($canAccess ? 'YES ✓' : 'NO ✗') . "\n";
echo "Reason: $reason\n";
echo "</pre>";

echo "<h2>Actions</h2>";
echo "<a href='confidential_mode_settings.php' style='display:inline-block;padding:15px 30px;background:#1b2f73;color:white;text-decoration:none;border-radius:8px;margin:10px;'>Try Accessing Settings</a>";
echo "<a href='index.php' style='display:inline-block;padding:15px 30px;background:#2342a6;color:white;text-decoration:none;border-radius:8px;margin:10px;'>Back to Dashboard</a>";

echo "</body></html>";
?>
