<?php
require_once '../includes/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

header('Location: admin-profile-settings.php');
exit;
