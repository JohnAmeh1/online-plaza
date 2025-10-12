<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'online_plaza');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create PDO instance
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Get current user info
function getCurrentUser()
{
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

// Generate CSRF token
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function createActivity($userId, $activityType, $referenceType = null, $referenceId = null, $actorUsername = null)
{
    global $pdo;

    if (!$actorUsername) {
        $currentUser = getCurrentUser();
        $actorUsername = $currentUser['username'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO activities (user_id, activity_type, reference_type, reference_id, actor_username) 
        VALUES (?, ?, ?, ?, ?)
    ");

    return $stmt->execute([$userId, $activityType, $referenceType, $referenceId, $actorUsername]);
}

/**
 * Get unread activity count for current user
 */
function getUnreadActivityCount()
{
    global $pdo;

    if (!isLoggedIn()) {
        return 0;
    }

    $currentUser = getCurrentUser();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$currentUser['id']]);

    return $stmt->fetchColumn();
}


