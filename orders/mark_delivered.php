<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify order belongs to user and is deliverable
    $stmt = $pdo->prepare("
        SELECT o.*, c.user_id as vendor_id 
        FROM orders o 
        JOIN companies c ON o.company_id = c.id 
        WHERE o.id = ? AND o.user_id = ? AND o.status IN ('pending', 'paid', 'shipped')
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found or cannot be marked as delivered');
    }

    // Update order status
    $updateOrder = $pdo->prepare("UPDATE orders SET status = 'delivered', payment_status = 'released' WHERE id = ?");
    $updateOrder->execute([$orderId]);

    // Release payment to vendor
    $creditVendor = $pdo->prepare("
        UPDATE wallet SET balance = balance + ? WHERE user_id = ?
    ");
    $creditVendor->execute([$order['total_amount'], $order['vendor_id']]);

    // Record credit transaction for vendor - SIMPLIFIED without reference_id
    $transactionStmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, description) 
        VALUES (?, 'credit', ?, ?)
    ");
    $transactionDescription = 'Payment released for delivered order #' . $orderId;
    $transactionStmt->execute([$order['vendor_id'], $order['total_amount'], $transactionDescription]);

    // Update escrow status
    $updateEscrow = $pdo->prepare("UPDATE wallet_escrow SET status = 'released', released_at = NOW() WHERE order_id = ?");
    $updateEscrow->execute([$orderId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order marked as delivered. Payment has been released to the vendor.'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Mark delivered error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
