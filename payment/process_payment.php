<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to make a purchase']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;

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
    $walletStmt->execute([$_SESSION['user_id']]);
    $wallet = $walletStmt->fetch();
    $userBalance = $wallet ? $wallet['balance'] : 0;

    $totalAmount = $product['price'] * $quantity;

    if ($userBalance < $totalAmount) {
        throw new Exception('Insufficient wallet balance. Please fund your wallet.');
    }

    // Deduct from user's wallet
    $updateWallet = $pdo->prepare("UPDATE wallet SET balance = balance - ? WHERE user_id = ?");
    $updateWallet->execute([$totalAmount, $_SESSION['user_id']]);

    // Record debit transaction
    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, amount, type, description, status) 
        VALUES (?, ?, 'debit', 'Payment for product purchase', 'completed')
    ");
    $transactionStmt->execute([$_SESSION['user_id'], $totalAmount]);

    // Create order
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (user_id, company_id, product_id, quantity, total_amount, status, payment_status) 
        VALUES (?, ?, ?, ?, ?, 'paid', 'paid')
    ");
    $orderStmt->execute([
        $_SESSION['user_id'],
        $product['company_id'],
        $productId,
        $quantity,
        $totalAmount
    ]);
    $orderId = $pdo->lastInsertId();

    // Update product stock
    $updateStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $updateStock->execute([$quantity, $productId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment successful! Order has been placed.',
        'order_id' => $orderId,
        'total_amount' => $totalAmount
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>