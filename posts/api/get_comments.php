<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid post ID'
    ]);
    exit;
}

$postId = (int)$_GET['post_id'];

try {
    // Check if post exists
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
        exit;
    }

    // Get comments for the post
    $stmt = $pdo->prepare("
        SELECT pc.*, u.username, u.first_name, u.last_name
        FROM post_comments pc 
        JOIN users u ON pc.user_id = u.id 
        WHERE pc.post_id = ? 
        ORDER BY pc.created_at DESC
    ");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);

} catch (Exception $e) {
    error_log("Error loading comments: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error loading comments'
    ]);
}
?>