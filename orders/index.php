<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.image_url, c.name as company_name,
           (SELECT balance FROM wallet WHERE user_id = c.user_id) as vendor_balance
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    JOIN companies c ON o.company_id = c.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$currentUser['id']]);
$orders = $stmt->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders</h1>

    <?php if ($orders): ?>
        <div class="space-y-6">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 <?php
                    echo $order['status'] === 'delivered' ? 'border-green-500' : 
                         ($order['status'] === 'paid' ? 'border-blue-500' : 'border-yellow-500');
                ?>">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-4">
                                <?php if ($order['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($order['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($order['product_name']); ?>" 
                                         class="w-16 h-16 object-cover rounded-lg mr-4">
                                <?php endif; ?>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($order['product_name']); ?></h3>
                                    <p class="text-gray-600">From: <?php echo htmlspecialchars($order['company_name']); ?></p>
                                    <p class="text-green-600 font-bold">₦<?php echo number_format($order['total_amount'], 2); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                                <span class="bg-gray-100 px-3 py-1 rounded-full">
                                    Order #<?php echo $order['id']; ?>
                                </span>
                                <span class="px-3 py-1 rounded-full <?php
                                    echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-800' : 
                                         ($order['status'] === 'paid' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800');
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                                <span class="px-3 py-1 rounded-full <?php
                                    echo $order['payment_status'] === 'released' ? 'bg-green-100 text-green-800' : 
                                         ($order['payment_status'] === 'paid' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                                ?>">
                                    Payment: <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                                <span class="text-gray-500">
                                    Ordered: <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 md:mt-0 md:ml-6">
                            <?php if ($order['status'] === 'paid' || $order['status'] === 'shipped'): ?>
                                <button onclick="markAsDelivered(<?php echo $order['id']; ?>)" 
                                        class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition font-semibold">
                                    <i class="fas fa-check mr-2"></i>Mark as Delivered
                                </button>
                            <?php elseif ($order['status'] === 'delivered'): ?>
                                <span class="text-green-600 font-semibold">
                                    <i class="fas fa-check-circle mr-2"></i>Delivered
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
            <p class="text-gray-500 mb-6">Start shopping to see your orders here.</p>
            <a href="/online-plaza/products/index.php" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-blue-600 transition font-semibold">
                Browse Products
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
async function markAsDelivered(orderId) {
    if (!confirm('Have you received this order? Marking as delivered will release payment to the vendor.')) {
        return;
    }

    try {
        const response = await fetch('/online-plaza/orders/mark_delivered.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_id: orderId })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Order marked as delivered! Payment has been released to the vendor.', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function showNotification(message, type = 'success') {
    // Use your existing notification function
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-semibold ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>