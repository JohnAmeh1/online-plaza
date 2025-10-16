<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: /auth/login.php");
    exit;
}

// Get current user info using your existing function
$currentUser = getCurrentUser();

if ($currentUser && isset($currentUser['user_type'])) {
    switch ($currentUser['user_type']) {
        case 'customer':
            header("Location: /online-plaza/profile/index.php");
            break;
        case 'admin':
            header("Location: /online-plaza/admin/index.php");
            break;
        case 'vendor':
            header("Location: /online-plaza/company/dashboard.php");
            break;
        default:
            header("Location: /auth/login.php");
            break;
    }
    exit;
} else {
    header("Location: /auth/login.php");
    exit;
}
?>