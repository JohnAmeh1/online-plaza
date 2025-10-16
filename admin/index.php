<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is admin using your current user system
$currentUser = getCurrentUser();
if (!isLoggedIn() || !$currentUser || $currentUser['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Initialize variables with default values
$totalUsers = 0;
$totalVendors = 0;
$totalProducts = 0;
$totalOrders = 0;
$recentActivities = [];
$error = '';

// Get dashboard stats
try {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalUsers = $result ? $result['total_users'] : 0;

    // Total vendors
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_vendors FROM companies");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalVendors = $result ? $result['total_vendors'] : 0;

    // Total products
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $result ? $result['total_products'] : 0;

    // Total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE status != 'cancelled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalOrders = $result ? $result['total_orders'] : 0;

    // Recent activities
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM activities a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
    // Log the error but don't break the page
    error_log("Admin Dashboard Error: " . $e->getMessage());
}

$pageTitle = "Admin Dashboard - Martly";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="../assets/martly.svg">
    <script src="/online-plaza/assets/js/auto-refresh.js"></script>
</head>
<body>
<div class="min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-0 lg:ml-64">
        <!-- Top Bar -->
        <?php include 'includes/topbar.php'; ?>

        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars($currentUser['first_name']); ?>! 👋</h1>
                <p class="text-gray-600 mt-2">Here's what's happening with your store today.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Users</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($totalUsers); ?></h3>
                            <p class="text-green-600 text-sm mt-1 flex items-center">
                                <i class="fas fa-arrow-up mr-1"></i>
                                12% increase
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Vendors -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Vendors</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($totalVendors); ?></h3>
                            <p class="text-green-600 text-sm mt-1 flex items-center">
                                <i class="fas fa-arrow-up mr-1"></i>
                                8% increase
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-blue-500 rounded-xl flex items-center justify-center">
                            <i class="fas fa-store text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Products -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Products</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($totalProducts); ?></h3>
                            <p class="text-green-600 text-sm mt-1 flex items-center">
                                <i class="fas fa-arrow-up mr-1"></i>
                                15% increase
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-box text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Orders</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2"><?php echo number_format($totalOrders); ?></h3>
                            <p class="text-green-600 text-sm mt-1 flex items-center">
                                <i class="fas fa-arrow-up mr-1"></i>
                                23% increase
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-red-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Activities -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
                        <a href="activities.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                            View All
                        </a>
                    </div>
                    <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                        <?php if (empty($recentActivities)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-history text-gray-300 text-4xl mb-3"></i>
                                <p class="text-gray-500">No activities yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-bell text-white text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-gray-900 text-sm font-medium">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        </p>
                                        <p class="text-gray-600 text-xs">
                                            <?php echo htmlspecialchars($activity['activity_type']); ?>
                                        </p>
                                        <p class="text-gray-500 text-xs">
                                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="users.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-4 rounded-xl text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-users text-2xl mb-2"></i>
                            <p class="font-medium">Manage Users</p>
                        </a>
                        <a href="vendors.php" class="bg-gradient-to-r from-green-500 to-blue-500 text-white p-4 rounded-xl text-center hover:from-green-600 hover:to-blue-600 transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-store text-2xl mb-2"></i>
                            <p class="font-medium">Manage Vendors</p>
                        </a>
                        <a href="products.php" class="bg-gradient-to-r from-purple-500 to-pink-600 text-white p-4 rounded-xl text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-box text-2xl mb-2"></i>
                            <p class="font-medium">Manage Products</p>
                        </a>
                        <a href="orders.php" class="bg-gradient-to-r from-orange-500 to-red-600 text-white p-4 rounded-xl text-center hover:from-orange-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-shopping-cart text-2xl mb-2"></i>
                            <p class="font-medium">View Orders</p>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const sidebar = document.querySelector('.fixed.inset-y-0');
    sidebar.classList.toggle('-translate-x-full');
});
</script>
</body>
</html>