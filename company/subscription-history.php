<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Redirect if user is not a vendor
if ($currentUser['user_type'] !== 'vendor') {
    header('Location: /online-plaza/profile/index.php');
    exit;
}

// Get company details
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$company = $stmt->fetch();

// Get subscription history
$historyStmt = $pdo->prepare("
    SELECT * FROM vendor_subscriptions 
    WHERE company_id = ? 
    ORDER BY created_at DESC
");
$historyStmt->execute([$company['id']]);
$subscriptionHistory = $historyStmt->fetchAll();

// Get current subscription
$currentStmt = $pdo->prepare("SELECT * FROM vendor_subscriptions WHERE company_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
$currentStmt->execute([$company['id']]);
$currentSubscription = $currentStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription History - Martly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php require_once '../includes/header.php'; ?>
    
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2">Subscription History</h1>
            <p class="text-gray-600">Track your subscription payments and status</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                        <h2 class="text-xl font-semibold text-gray-800">Subscription Records</h2>
                        <div class="mt-4 sm:mt-0">
                            <span class="text-sm text-gray-600">
                                Total Records: <?php echo count($subscriptionHistory); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($subscriptionHistory): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Date</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Amount</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Period</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptionHistory as $subscription): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                            <td class="py-4 px-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo date('M j, Y', strtotime($subscription['created_at'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?php echo date('g:i A', strtotime($subscription['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span class="font-semibold text-gray-900">
                                                    ₦<?php echo number_format($subscription['amount'], 2); ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <?php 
                                                    $statusColors = [
                                                        'active' => 'bg-green-100 text-green-800',
                                                        'expired' => 'bg-red-100 text-red-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'failed' => 'bg-gray-100 text-gray-800'
                                                    ];
                                                    $color = $statusColors[$subscription['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $color; ?>">
                                                    <?php echo ucfirst($subscription['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo date('M j, Y', strtotime($subscription['start_date'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    to <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <span class="text-xs font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                                    <?php echo substr($subscription['paystack_reference'], 0, 12) . '...'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-500 mb-2">No Subscription History</h3>
                            <p class="text-gray-400 mb-6">You haven't made any subscription payments yet.</p>
                            <a href="/online-plaza/company/subscribe.php" 
                               class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-2 rounded-lg font-semibold hover:from-green-600 hover:to-blue-600 transition">
                                Get Started
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Current Subscription -->
                <?php if ($currentSubscription): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Subscription</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-600">Status</p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Expires</p>
                                <p class="font-semibold text-gray-800">
                                    <?php echo date('F j, Y', strtotime($currentSubscription['expiry_date'])); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Amount</p>
                                <p class="font-semibold text-gray-800">
                                    ₦<?php echo number_format($currentSubscription['amount'], 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="/online-plaza/company/dashboard.php" 
                           class="w-full flex items-center justify-center bg-gray-100 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-200 transition">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Dashboard
                        </a>
                        <?php if ($currentSubscription): ?>
                            <a href="/online-plaza/company/renew-subscription.php" 
                               class="w-full flex items-center justify-center bg-gradient-to-r from-green-500 to-blue-500 text-white py-2 px-4 rounded-lg font-medium hover:from-green-600 hover:to-blue-600 transition">
                                <i class="fas fa-sync-alt mr-2"></i>
                                Renew Subscription
                            </a>
                        <?php else: ?>
                            <a href="/online-plaza/company/subscribe.php" 
                               class="w-full flex items-center justify-center bg-gradient-to-r from-purple-500 to-blue-500 text-white py-2 px-4 rounded-lg font-medium hover:from-purple-600 hover:to-blue-600 transition">
                                <i class="fas fa-crown mr-2"></i>
                                Subscribe Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html>