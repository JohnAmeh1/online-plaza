<?php
// like.php - Ensure this is working
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to like posts']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$postId = (int)$_POST['post_id'];

try {
    // Check if like exists
    $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$userId, $postId]);
    $existingLike = $stmt->fetch();

    if ($existingLike) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM post_likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$userId, $postId]);
        $liked = false;
    } else {
        // Like
        $stmt = $pdo->prepare("INSERT INTO post_likes (user_id, post_id) VALUES (?, ?)");
        $stmt->execute([$userId, $postId]);
        $liked = true;
    }

    // Get updated count
    $stmt = $pdo->prepare("SELECT COUNT(*) as like_count FROM post_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $likeCount = (int)$stmt->fetch()['like_count'];

    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => $likeCount,
        'message' => $liked ? 'Post liked!' : 'Post unliked!'
    ]);

} catch (Exception $e) {
    error_log("Like error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>