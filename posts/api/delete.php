<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to delete posts']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$postId = (int)$_POST['post_id'];
$userId = $_SESSION['user_id'];
$currentUser = getCurrentUser();

// Check if user is a vendor and owns the post
if ($currentUser['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Only vendors can delete posts']);
    exit;
}

// Get company ID for the user
$stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
$stmt->execute([$userId]);
$company = $stmt->fetch();

if (!$company) {
    echo json_encode(['success' => false, 'message' => 'Company not found']);
    exit;
}

// Check if post belongs to user's company
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND company_id = ?");
$stmt->execute([$postId, $company['id']]);
$post = $stmt->fetch();

if (!$post) {
    echo json_encode(['success' => false, 'message' => 'Post not found or access denied']);
    exit;
}

// Delete post (cascade will handle related records)
$stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
if ($stmt->execute([$postId])) {
    echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete post']);
}
?>