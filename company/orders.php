<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

if ($currentUser['user_type'] !== 'vendor') {
    header('Location: /online-plaza/profile/index.php');
    exit;
}

// Get company
$stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$company = $stmt->fetch();

// Get orders for this company
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, u.username, u.first_name, u.last_name,
           (SELECT balance FROM wallet WHERE user_id = ?) as company_balance
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN users u ON o.user_id = u.id 
    WHERE o.company_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$currentUser['id'], $company['id']]);
$orders = $stmt->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Customer Orders</h1>

    <?php if ($orders): ?>
        <div class="space-y-6">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 <?php
                    echo $order['payment_status'] === 'released' ? 'border-green-500' : 
                         ($order['status'] === 'delivered' ? 'border-blue-500' : 'border-yellow-500');
                ?>">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($order['product_name']); ?></h3>
                            <p class="text-gray-600 mb-2">Customer: <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?> (@<?php echo htmlspecialchars($order['username']); ?>)</p>
                            
                            <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-2">
                                <span class="bg-gray-100 px-3 py-1 rounded-full">
                                    Order #<?php echo $order['id']; ?>
                                </span>
                                <span class="px-3 py-1 rounded-full <?php
                                    echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-800' : 
                                         ($order['status'] === 'paid' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800');
                                ?>">
                                    Status: <?php echo ucfirst($order['status']); ?>
                                </span>
                                <span class="px-3 py-1 rounded-full <?php
                                    echo $order['payment_status'] === 'released' ? 'bg-green-100 text-green-800' : 
                                         ($order['payment_status'] === 'paid' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                                ?>">
                                    Payment: <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                                <span class="text-green-600 font-bold">
                                    ₦<?php echo number_format($order['total_amount'], 2); ?>
                                </span>
                            </div>
                            
                            <p class="text-gray-500 text-sm">
                                Ordered: <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div class="mt-4 md:mt-0 md:ml-6 text-right">
                            <?php if ($order['payment_status'] === 'released'): ?>
                                <span class="text-green-600 font-semibold">
                                    <i class="fas fa-check-circle mr-2"></i>Payment Released
                                </span>
                            <?php else: ?>
                                <span class="text-blue-600 font-semibold">
                                    <i class="fas fa-clock mr-2"></i>Awaiting Delivery
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-12">
            <i class="fas fa-shopping-bag text-4xl text-gray-300 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">No Orders Yet</h3>
            <p class="text-gray-500">Orders from customers will appear here.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>