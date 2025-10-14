<?php
// submit_review.php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Debug logging
error_log("=== REVIEW SUBMISSION STARTED ===");
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));

// Ensure user logged in
if (!isset($_SESSION['user_id']) || !isLoggedIn()) {
    $_SESSION['error'] = "You must be logged in to submit a review.";
    error_log("User not logged in");
    $back = isset($_POST['company_id']) && is_numeric($_POST['company_id']) ? '/online-plaza/company/index.php?id=' . (int)$_POST['company_id'] : '/online-plaza/index.php';
    header("Location: $back");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /online-plaza/index.php');
    exit;
}

$company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
$user_id = (int)$_SESSION['user_id'];
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

error_log("Processing review - Company: $company_id, User: $user_id, Rating: $rating, Comment: $comment");

$backUrl = '/online-plaza/company/index.php?id=' . $company_id;

// Basic validations
if ($company_id <= 0) {
    $_SESSION['error'] = 'Invalid company specified.';
    error_log("Invalid company ID: $company_id");
    header("Location: $backUrl");
    exit;
}

if ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'Please select a valid rating between 1 and 5.';
    error_log("Invalid rating: $rating");
    header("Location: $backUrl");
    exit;
}

if (empty($comment)) {
    $_SESSION['error'] = 'Please enter a comment for your review.';
    error_log("Empty comment");
    header("Location: $backUrl");
    exit;
}

try {
    // Check if the company exists
    $cstmt = $pdo->prepare("SELECT id FROM companies WHERE id = ? LIMIT 1");
    $cstmt->execute([$company_id]);
    if ($cstmt->rowCount() === 0) {
        $_SESSION['error'] = 'Company not found.';
        error_log("Company not found: $company_id");
        header("Location: $backUrl");
        exit;
    }

    // Check if user already reviewed this company
    $checkStmt = $pdo->prepare("SELECT id FROM company_reviews WHERE company_id = ? AND user_id = ?");
    $checkStmt->execute([$company_id, $user_id]);
    $existingReview = $checkStmt->fetch();

    if ($existingReview) {
        // Update existing review
        $stmt = $pdo->prepare("UPDATE company_reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$rating, $comment, $existingReview['id']]);
        error_log("Updating existing review - Result: " . ($result ? 'Success' : 'Failed'));
        
        if ($result) {
            $_SESSION['success'] = 'Review updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update review. Please try again.';
        }
    } else {
        // Insert new review
        $stmt = $pdo->prepare("
            INSERT INTO company_reviews (company_id, user_id, rating, comment, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $result = $stmt->execute([$company_id, $user_id, $rating, $comment]);
        error_log("Inserting new review - Result: " . ($result ? 'Success' : 'Failed'));
        
        if ($result) {
            $_SESSION['success'] = 'Review submitted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to submit review. Please try again.';
            // Get detailed error info
            $errorInfo = $stmt->errorInfo();
            error_log("PDO Error Info: " . print_r($errorInfo, true));
        }
    }

} catch (PDOException $e) {
    // Log error for debugging
    error_log("Review submission error: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while saving your review. Please try again later.';
}

error_log("Redirecting to: $backUrl");
header("Location: $backUrl");
exit;