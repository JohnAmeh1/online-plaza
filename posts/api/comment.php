<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to comment']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$postId = (int)$_POST['post_id'];
$comment = trim($_POST['comment']);
$currentUser = getCurrentUser();
$userId = $currentUser['id'];

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit;
}

try {
    // Check if post exists
    $stmt = $pdo->prepare("SELECT id, company_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    // Insert comment
    $stmt = $pdo->prepare("INSERT INTO post_comments (post_id, user_id, comment) VALUES (?, ?, ?)");
    if ($stmt->execute([$postId, $userId, $comment])) {
        
        // Get user info for response
        $stmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        // Create activity for the post owner (company)
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ?");
        $stmt->execute([$post['company_id']]);
        $companyOwnerId = $stmt->fetchColumn();
        
        if ($companyOwnerId && $companyOwnerId != $userId) {
            createActivity($companyOwnerId, 'comment', 'post', $postId, $currentUser['username']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment' => [
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'comment' => $comment,
                'created_at' => 'Just now'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
    }
} catch (Exception $e) {
    error_log("Comment submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>