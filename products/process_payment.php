<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to make a purchase']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$currentUser = getCurrentUser();
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// Validate delivery address fields
$requiredFields = ['full_name', 'phone_number', 'email_address', 'street_address', 'city', 'state'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required address fields']);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // Get product details
    $stmt = $pdo->prepare("
        SELECT p.*, c.id as company_id, c.user_id as vendor_id, c.name as company_name
        FROM products p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.id = ? AND p.stock_quantity > 0
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not available or out of stock');
    }

    // Check user wallet balance
    $walletStmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $walletStmt->execute([$currentUser['id']]);
    $wallet = $walletStmt->fetch();

    if (!$wallet || $wallet['balance'] < $product['price']) {
        throw new Exception('Insufficient wallet balance. Please top up your wallet.');
    }

    // Deduct amount from user's wallet
    $newBalance = $wallet['balance'] - $product['price'];
    $updateWalletStmt = $pdo->prepare("UPDATE wallet SET balance = ? WHERE user_id = ?");
    $updateWalletStmt->execute([$newBalance, $currentUser['id']]);

    // Create order with delivery address details
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, 
            company_id, 
            product_id, 
            quantity, 
            unit_price,
            total_amount, 
            status, 
            payment_status,
            full_name,
            phone_number,
            email_address,
            street_address,
            city,
            state,
            postal_code,
            delivery_instructions
        ) VALUES (
            ?,
            ?,
            ?,
            1,
            ?,
            ?,
            'pending',
            'paid',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");
    
    $orderStmt->execute([
        $currentUser['id'],
        $product['company_id'],
        $productId,
        $product['price'],
        $product['price'],
        sanitizeInput($_POST['full_name']),
        sanitizeInput($_POST['phone_number']),
        sanitizeInput($_POST['email_address']),
        sanitizeInput($_POST['street_address']),
        sanitizeInput($_POST['city']),
        sanitizeInput($_POST['state']),
        sanitizeInput($_POST['postal_code'] ?? ''),
        sanitizeInput($_POST['delivery_instructions'] ?? '')
    ]);
    
    $orderId = $pdo->lastInsertId();

    // Add to escrow (hold funds until delivery)
    $escrowStmt = $pdo->prepare("
        INSERT INTO wallet_escrow (user_id, company_id, order_id, amount, status) 
        VALUES (?, ?, ?, ?, 'held')
    ");
    $escrowStmt->execute([$currentUser['id'], $product['company_id'], $orderId, $product['price']]);

    // Update product stock
    $updateStockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - 1 WHERE id = ?");
    $updateStockStmt->execute([$productId]);

    // Add transaction record
    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, description) 
        VALUES (?, 'debit', ?, ?)
    ");
    $transactionDescription = 'Purchase: ' . $product['name'] . ' (Order #' . $orderId . ')';
    $transactionStmt->execute([
        $currentUser['id'],
        $product['price'],
        $transactionDescription
    ]);

    // Create notification for the buyer (user)
    $buyerNotificationStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, reference_id) 
        VALUES (?, ?, ?, 'order_created', 0, ?)
    ");
    $buyerNotificationStmt->execute([
        $currentUser['id'],
        'Order Placed Successfully',
        'Your order #' . $orderId . ' for ' . $product['name'] . ' has been placed successfully. Total: ₦' . number_format($product['price'], 2),
        $orderId
    ]);

    // Create notification for the vendor (seller)
    $vendorNotificationStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, reference_id) 
        VALUES (?, ?, ?, 'order_created', 0, ?)
    ");
    $vendorNotificationStmt->execute([
        $product['vendor_id'],
        'New Order Received',
        'You have received a new order #' . $orderId . ' for ' . $product['name'] . ' from ' . $currentUser['first_name'] . ' ' . $currentUser['last_name'] . '. Amount: ₦' . number_format($product['price'], 2),
        $orderId
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'order_id' => $orderId,
        'new_balance' => $newBalance
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper function to sanitize input
function sanitizeInput($input) {
    if (is_null($input)) {
        return null;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
