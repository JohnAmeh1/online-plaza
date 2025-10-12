<?php
// share.php - COMPLETELY REWRITTEN
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Basic validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to share posts']);
    exit;
}

// Validate post ID
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid post ID is required']);
    exit;
}

try {
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];
    
    // Verify post exists
    $stmt = $pdo->prepare("SELECT id, company_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Check if user already shared this post (optional - remove if you want multiple shares)
    $stmt = $pdo->prepare("SELECT id FROM post_shares WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$userId, $postId]);
    $existingShare = $stmt->fetch();
    
    if ($existingShare) {
        echo json_encode(['success' => false, 'message' => 'You have already shared this post']);
        exit;
    }
    
    // Insert the share
    $stmt = $pdo->prepare("INSERT INTO post_shares (post_id, user_id, created_at) VALUES (?, ?, NOW())");
    $result = $stmt->execute([$postId, $userId]);
    
    if (!$result) {
        throw new Exception('Failed to insert share record');
    }
    
    // Get updated share count
    $stmt = $pdo->prepare("SELECT COUNT(*) as share_count FROM post_shares WHERE post_id = ?");
    $stmt->execute([$postId]);
    $shareData = $stmt->fetch(PDO::FETCH_ASSOC);
    $shareCount = (int)$shareData['share_count'];
    
    // Create activity if function exists
    if (function_exists('createActivity')) {
        try {
            createActivity($post['company_id'], 'share', 'post', $postId, $currentUser['username']);
        } catch (Exception $e) {
            // Silently fail if activity creation fails
            error_log("Activity creation failed: " . $e->getMessage());
        }
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'share_count' => $shareCount,
        'message' => 'Post shared successfully!'
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in share.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in share.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>