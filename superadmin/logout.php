<?php
// Proxy logout requests from /admin to the root logout handler.
// Preserve the reason so timeout redirects land on the login page.

$reason = isset($_GET['reason']) ? urlencode($_GET['reason']) : null;
$target = '../logout.php' . ($reason ? '?reason=' . $reason : '');

header('Location: ' . $target);
exit;
?>
