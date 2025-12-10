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

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-green-50 py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Customer Orders</h1>
            <p class="text-gray-600">Manage and track all customer orders and deliveries</p>
        </div>

        <?php if ($orders): ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-3xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden border-l-4 <?php
                        echo $order['payment_status'] === 'released' ? 'border-green-500' : 
                             ($order['status'] === 'delivered' ? 'border-blue-500' : 'border-yellow-500');
                    ?>">
                        <!-- Order Header Section -->
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div class="flex-1">
                                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($order['product_name']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm md:text-base mb-2">
                                        <i class="fas fa-user mr-2 text-blue-500"></i>
                                        <span class="font-semibold"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                                        <span class="text-gray-500">(@<?php echo htmlspecialchars($order['username']); ?>)</span>
                                    </p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                    <span class="bg-gray-100 text-gray-700 px-3 py-2 rounded-full text-sm font-semibold text-center">
                                        Order #<?php echo $order['id']; ?>
                                    </span>
                                    <span class="px-3 py-2 rounded-full text-sm font-semibold text-center <?php
                                        echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-800' : 
                                             ($order['status'] === 'paid' ? 'bg-blue-100 text-blue-800' : 
                                              ($order['status'] === 'shipped' ? 'bg-purple-100 text-purple-800' : 'bg-yellow-100 text-yellow-800'));
                                    ?>">
                                        <i class="fas mr-1 <?php
                                            echo $order['status'] === 'delivered' ? 'fa-check-circle' : 
                                                 ($order['status'] === 'shipped' ? 'fa-truck' : 'fa-clock');
                                        ?>"></i>
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                    <span class="px-3 py-2 rounded-full text-sm font-semibold text-center <?php
                                        echo $order['payment_status'] === 'released' ? 'bg-green-100 text-green-800' : 
                                             ($order['payment_status'] === 'paid' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                                    ?>">
                                        <i class="fas mr-1 <?php
                                            echo $order['payment_status'] === 'released' ? 'fa-money-bill-wave' : 'fa-hourglass-end';
                                        ?>"></i>
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Order Amount and Date -->
                            <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-gray-100">
                                <p class="text-gray-600 text-sm md:text-base">
                                    <i class="fas fa-calendar mr-2 text-green-500"></i>
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?> at 
                                    <?php echo date('g:i A', strtotime($order['created_at'])); ?>
                                </p>
                                <p class="text-2xl md:text-3xl font-bold text-green-600">
                                    ₦<?php echo number_format($order['total_amount'], 2); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Delivery Address Section -->
                        <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-green-50">
                            <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                Delivery Address
                            </h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- Full Name -->
                                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:border-blue-300 transition-all">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Full Name</p>
                                    <p class="text-base md:text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($order['full_name'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <!-- Phone Number -->
                                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:border-green-300 transition-all">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Phone Number</p>
                                    <p class="text-base md:text-lg font-semibold text-gray-900">
                                        <a href="tel:<?php echo htmlspecialchars($order['phone_number'] ?? ''); ?>" class="text-green-600 hover:text-green-800 transition-colors">
                                            <?php echo htmlspecialchars($order['phone_number'] ?? 'N/A'); ?>
                                        </a>
                                    </p>
                                </div>

                                <!-- Email Address -->
                                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:border-blue-300 transition-all">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email Address</p>
                                    <p class="text-base md:text-lg font-semibold text-gray-900 break-all">
                                        <a href="mailto:<?php echo htmlspecialchars($order['email_address'] ?? ''); ?>" class="text-blue-600 hover:text-blue-800 transition-colors">
                                            <?php echo htmlspecialchars($order['email_address'] ?? 'N/A'); ?>
                                        </a>
                                    </p>
                                </div>

                                <!-- Street Address -->
                                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:border-purple-300 transition-all md:col-span-2 lg:col-span-1">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Street Address</p>
                                    <p class="text-base md:text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($order['street_address'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <!-- City -->
                                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:border-orange-300 transition-all">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">City / Town</p>
                                    <p class="text-base md:text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($order['city'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <!-- State -->
                                <div class="bg-white p-4 rounded-xl border border-gray-200 hover:border-indigo-300 transition-all">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">State / Province</p>
                                    <p class="text-base md:text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($order['state'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Delivery Instructions (if available) -->
                            <?php if (!empty($order['delivery_instructions'])): ?>
                                <div class="mt-6 bg-white p-4 rounded-xl border-2 border-yellow-200">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center">
                                        <i class="fas fa-sticky-note text-yellow-500 mr-2"></i>
                                        Delivery Instructions
                                    </p>
                                    <p class="text-sm md:text-base text-gray-700 leading-relaxed">
                                        <?php echo nl2br(htmlspecialchars($order['delivery_instructions'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Order Footer -->
                        <div class="p-6 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <?php if ($order['payment_status'] === 'released'): ?>
                                <span class="text-green-600 font-semibold text-base md:text-lg flex items-center justify-center sm:justify-start">
                                    <i class="fas fa-check-circle mr-2 text-2xl"></i>
                                    Payment Released
                                </span>
                            <?php else: ?>
                                <span class="text-blue-600 font-semibold text-base md:text-lg flex items-center justify-center sm:justify-start">
                                    <i class="fas fa-truck mr-2 text-2xl"></i>
                                    Awaiting Delivery Confirmation
                                </span>
                            <?php endif; ?>

                            <button onclick="printOrder(<?php echo $order['id']; ?>)" class="w-full sm:w-auto bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300 font-semibold flex items-center justify-center">
                                <i class="fas fa-print mr-2"></i>
                                Print Address
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl shadow-lg p-12 text-center">
                <div class="inline-block mb-4 p-6 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full">
                    <i class="fas fa-shopping-bag text-5xl text-gray-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-600 mb-2">No Orders Yet</h3>
                <p class="text-gray-500 text-lg">Orders from customers will appear here once they make purchases.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function printOrder(orderId) {
        const orderElement = document.querySelector(`[data-order-id="${orderId}"]`) || event.target.closest('div');
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Order #' + orderId + '</title>');
        printWindow.document.write('<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; background: white; }');
        printWindow.document.write('.header { border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 20px; }');
        printWindow.document.write('.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }');
        printWindow.document.write('.label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }');
        printWindow.document.write('.value { font-size: 16px; color: #000; }');
        printWindow.document.write('@media print { body { margin: 0; padding: 10px; } }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<div class="header"><h1>Order #' + orderId + ' - Delivery Address</h1></div>');
        printWindow.document.write(orderElement.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
</script>

<?php require_once '../includes/footer.php'; ?>