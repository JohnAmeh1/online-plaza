<?php
require_once '../includes/config.php';

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = $_POST['company_id'] ?? null;
    $reference = $_POST['reference'] ?? null;
    
    if (!$company_id || !$reference) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    try {
        // Verify company belongs to user
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ? AND user_id = ?");
        $stmt->execute([$company_id, $currentUser['id']]);
        $company = $stmt->fetch();
        
        if (!$company) {
            throw new Exception("Company not found");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Calculate new expiry date
        $current_expiry = $company['subscription_expiry'];
        $new_expiry = null;
        
        if ($current_expiry && strtotime($current_expiry) > time()) {
            // Extend from current expiry date
            $new_expiry = date('Y-m-d H:i:s', strtotime($current_expiry . ' +1 month'));
        } else {
            // Start from now
            $new_expiry = date('Y-m-d H:i:s', strtotime('+1 month'));
        }
        
        // Insert renewal subscription record
        $stmt = $pdo->prepare("INSERT INTO vendor_subscriptions (user_id, company_id, paystack_reference, amount, status, start_date, expiry_date) VALUES (?, ?, ?, 25000, 'active', NOW(), ?)");
        $stmt->execute([$currentUser['id'], $company_id, $reference, $new_expiry]);
        
        // Update company subscription status and expiry
        $stmt = $pdo->prepare("UPDATE companies SET subscription_status = 'active', subscription_expiry = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_expiry, $company_id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Subscription renewed successfully!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>