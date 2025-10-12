<?php
// Additional utility functions for the platform

// Get user by ID
function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Get company by user ID
function getCompanyByUserId($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Check if user can manage a post
function canManagePost($postId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.id 
        FROM posts p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$postId, $userId]);
    return (bool)$stmt->fetch();
}

// Check if user can manage a product
function canManageProduct($productId, $userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.id 
        FROM products p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$productId, $userId]);
    return (bool)$stmt->fetch();
}

// Get user's unread activity count
// function getUnreadActivityCount($userId) {
//     global $pdo;
//     $stmt = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE user_id = ? AND is_read = FALSE");
//     $stmt->execute([$userId]);
//     return $stmt->fetchColumn();
// }

// Format number with K/M suffix
function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

// Sanitize output
function sanitizeOutput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Validate image URL
function isValidImageUrl($url) {
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200')) {
        $imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        foreach ($headers as $header) {
            if (strpos($header, 'Content-Type:') !== false) {
                $contentType = trim(substr($header, 13));
                return in_array($contentType, $imageTypes);
            }
        }
    }
    return false;
}

// Log activity
function logActivity($userId, $activityType, $referenceId, $referenceType) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO activities (user_id, activity_type, reference_id, reference_type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $activityType, $referenceId, $referenceType]);
}

// Get trending posts
function getTrendingPosts($limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as company_name,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
               (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count
        FROM posts p 
        JOIN companies c ON p.company_id = c.id 
        ORDER BY (like_count + comment_count) DESC, p.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Get popular products
function getPopularProducts($limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as company_name,
               (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
               (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
        FROM products p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.stock_quantity > 0
        ORDER BY (avg_rating * review_count) DESC, p.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
?>