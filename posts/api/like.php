<?php
// like.php - Fixed version with proper state verification
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to like posts']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

// Enhanced debug logging
error_log("=== LIKE REQUEST START ===");
error_log("User ID: $userId, Post ID: $postId");

// Validate post ID
if ($postId <= 0) {
    error_log("Invalid post ID: $postId");
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Verify post exists and get company info
    $stmt = $pdo->prepare("SELECT p.*, c.id as company_id, c.user_id as company_owner_id FROM posts p JOIN companies c ON p.company_id = c.id WHERE p.id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        $pdo->rollBack();
        error_log("Post not found: $postId");
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    // Get current like count BEFORE any changes
    $stmt = $pdo->prepare("SELECT COUNT(*) as like_count FROM post_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $initialCountResult = $stmt->fetch();
    $initialLikeCount = (int)$initialCountResult['like_count'];
    
    error_log("Initial like count: $initialLikeCount");

    // Check if like exists - MORE ROBUST CHECK
    $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$userId, $postId]);
    $existingLike = $stmt->fetch();
    $likeExists = !empty($existingLike);

    error_log("Like exists check - Result: " . ($likeExists ? 'YES' : 'NO'));

    $liked = false;
    $activityMessage = '';

    if ($likeExists) {
        // Unlike - remove the like
        error_log("UNLIKE: Removing like ID {$existingLike['id']} for user $userId on post $postId");
        
        $stmt = $pdo->prepare("DELETE FROM post_likes WHERE id = ?");
        $stmt->execute([$existingLike['id']]);
        
        $rowsDeleted = $stmt->rowCount();
        error_log("Rows deleted: $rowsDeleted");
        
        $liked = false;
        $activityMessage = 'Post unliked!';

    } else {
        // Like - add the like
        error_log("LIKE: Adding like for user $userId on post $postId");
        
        $stmt = $pdo->prepare("INSERT INTO post_likes (user_id, post_id, created_at) VALUES (?, ?, NOW())");
        $result = $stmt->execute([$userId, $postId]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Insert error: " . print_r($errorInfo, true));
            throw new Exception("Failed to insert like: " . $errorInfo[2]);
        }
        
        $insertId = $pdo->lastInsertId();
        error_log("Like inserted with ID: $insertId");
        
        $liked = true;
        $activityMessage = 'Post liked!';

        // Create activity for the post owner (company) when someone likes their post
        $companyOwnerId = $post['company_owner_id'];
        if ($companyOwnerId && $companyOwnerId != $userId) {
            createActivity($companyOwnerId, 'like', 'post', $postId, $currentUser['username']);
            error_log("Activity created for company owner: $companyOwnerId");
        }
    }

    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as like_count FROM post_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $likeCountResult = $stmt->fetch();
    $likeCount = (int)$likeCountResult['like_count'];
    
    error_log("Final like count: $likeCount");

    // Final verification - check current user's like status
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM post_likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$userId, $postId]);
    $finalCheck = $stmt->fetch();
    $finalLiked = ($finalCheck['count'] > 0);
    
    error_log("Final verification - Liked: " . ($finalLiked ? 'YES' : 'NO'));

    // Commit transaction
    $pdo->commit();
    error_log("Transaction committed successfully");

    // Return success response - USE FINAL VERIFIED STATE
    $response = [
        'success' => true,
        'liked' => $finalLiked, // Use the verified final state
        'like_count' => $likeCount,
        'message' => $activityMessage,
        'post_id' => $postId,
        'debug' => [
            'user_id' => $userId,
            'like_existed_before' => $likeExists,
            'action_performed' => $liked ? 'LIKED' : 'UNLIKED',
            'initial_like_count' => $initialLikeCount,
            'final_like_count' => $likeCount,
            'final_like_status' => $finalLiked ? 'LIKED' : 'NOT LIKED'
        ]
    ];
    
    error_log("Final response: " . json_encode($response));
    error_log("=== LIKE REQUEST END ===");
    
    echo json_encode($response);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transaction rolled back due to error");
    }
    
    error_log("Like error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}
?>