<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$reviewText = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$actorUsername = $currentUser['username'];

// Validation
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid rating (1-5 stars)']);
    exit;
}

if (empty($reviewText)) {
    echo json_encode(['success' => false, 'message' => 'Please write a review']);
    exit;
}

try {
    // Check if product exists and get product owner
    $stmt = $pdo->prepare("SELECT id, company_id, name FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Check if user already reviewed this product
    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$productId, $userId]);
    $existingReview = $stmt->fetch();

    if ($existingReview) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
        exit;
    }

    // Insert review
    $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$productId, $userId, $rating, $reviewText])) {

        // Get company owner's user_id
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ?");
        $stmt->execute([$product['company_id']]);
        $companyOwnerId = $stmt->fetchColumn();

        // Create activity if not reviewing own product
        if ($companyOwnerId && $companyOwnerId != $userId) {
            createActivity($companyOwnerId, 'review', 'product', $productId, $actorUsername);
        }

        // Get updated review stats
        $stmt = $pdo->prepare("
            SELECT 
                AVG(rating) as average_rating,
                COUNT(*) as review_count
            FROM product_reviews 
            WHERE product_id = ?
        ");
        $stmt->execute([$productId]);
        $stats = $stmt->fetch();

        // Get user info for response
        $stmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'average_rating' => round($stats['average_rating'], 1),
            'review_count' => (int)$stats['review_count'],
            'review' => [
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'rating' => $rating,
                'review_text' => $reviewText,
                'created_at' => date('F j, Y \a\t g:i A')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
    }
} catch (Exception $e) {
    error_log("Review submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
