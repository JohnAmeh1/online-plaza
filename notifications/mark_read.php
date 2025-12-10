<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

$currentUser = getCurrentUser();

if ($currentUser['user_type'] !== 'vendor') {
    http_response_code(403);
    exit;
}

try {
    // Mark all notifications as read for this vendor
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$currentUser['id']]);
    
    // Return success
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>