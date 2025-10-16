<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Only authenticated users can access this
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

$currentUser = getCurrentUser();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

try {
    // Search for users by email, phone, or name (excluding current user)
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.email,
            u.phone,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            w.id as wallet_id
        FROM users u 
        LEFT JOIN wallet w ON u.id = w.user_id 
        WHERE (u.email LIKE ? OR u.phone LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)
          AND u.id != ?
          AND u.is_active = 1
        LIMIT 10
    ");
    
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $currentUser['id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter out users without wallets
    $validUsers = array_filter($users, function($user) {
        return !empty($user['wallet_id']);
    });
    
    echo json_encode([
        'success' => true,
        'users' => array_values($validUsers) // Reindex array
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>