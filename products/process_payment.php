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

try {
    $pdo->beginTransaction();

    // Get product details
    $stmt = $pdo->prepare("
        SELECT p.*, c.id as company_id, c.user_id as vendor_id 
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

    // Create order
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (user_id, company_id, product_id, quantity, total_amount, status, payment_status) 
        VALUES (?, ?, ?, 1, ?, 'pending', 'paid')
    ");
    $orderStmt->execute([
        $currentUser['id'],
        $product['company_id'],
        $productId,
        $product['price']
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

    // Add transaction record - SIMPLIFIED VERSION without reference_id
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
