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

// Get company subscription
$subscriptionStmt = $pdo->prepare("SELECT * FROM vendor_subscriptions WHERE company_id = ? ORDER BY created_at DESC LIMIT 1");
$subscriptionStmt->execute([$company['id']]);
$subscription = $subscriptionStmt->fetch();

// Calculate days until expiry
$daysUntilExpiry = null;
$canRenew = false;
if ($subscription && $subscription['expiry_date']) {
    $expiryDate = new DateTime($subscription['expiry_date']);
    $today = new DateTime();
    $interval = $today->diff($expiryDate);
    $daysUntilExpiry = $interval->days;

    // Check if subscription is active and within 7 days of expiry
    if ($subscription['status'] === 'active' && $daysUntilExpiry <= 7 && $daysUntilExpiry >= 0) {
        $canRenew = true;
    }
}

// Get company stats
$postsCount = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE company_id = ?");
$postsCount->execute([$company['id']]);
$postsCount = $postsCount->fetchColumn();

$productsCount = $pdo->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
$productsCount->execute([$company['id']]);
$productsCount = $productsCount->fetchColumn();

$totalLikes = $pdo->prepare("
    SELECT COUNT(*) FROM post_likes pl 
    JOIN posts p ON pl.post_id = p.id 
    WHERE p.company_id = ?
");
$totalLikes->execute([$company['id']]);
$totalLikes = $totalLikes->fetchColumn();

// Get wallet balance for the vendor
$walletStmt = $pdo->prepare("SELECT COALESCE(balance, 0) as wallet_balance FROM wallet WHERE user_id = ?");
$walletStmt->execute([$currentUser['id']]);
$walletData = $walletStmt->fetch();
$walletBalance = $walletData ? $walletData['wallet_balance'] : 0;

// Recent posts
$recentPosts = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count
    FROM posts p 
    WHERE p.company_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$recentPosts->execute([$company['id']]);
$recentPosts = $recentPosts->fetchAll();

// Get unread notifications count
$notificationStmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$notificationStmt->execute([$currentUser['id']]);
$unreadNotifications = $notificationStmt->fetchColumn();
?>

<?php require_once '../includes/header.php'; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    <div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-8">
        <!-- Dashboard Header -->
        <div class="mb-6 sm:mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-slate-900 mb-2 flex items-center">
                    <span class="w-1.5 h-10 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-4"></span>
                    Company Dashboard
                </h1>
                <p class="text-slate-600 ml-6">Manage your business on Online Plaza</p>
            </div>
            <a href="/online-plaza/company/index.php?id=<?php echo $company['id']; ?>" 
               class="inline-flex items-center justify-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-300">
                <i class="fas fa-store mr-2"></i>View Public Page
            </a>
        </div>

        <!-- Subscription Status Banner -->
        <?php if ($subscription): ?>
            <div class="mb-6 sm:mb-8">
                <?php if ($subscription['status'] === 'active'): ?>
                    <div class="bg-white border-2 border-emerald-200 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-100 to-green-100 rounded-full blur-3xl opacity-30 -mr-32 -mt-32"></div>
                        <div class="relative">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div class="flex items-center">
                                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                                        <i class="fas fa-crown text-white text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-slate-900 mb-1">Active Subscription</h3>
                                        <p class="text-slate-600 text-sm">
                                            Expires in
                                            <span class="font-bold <?php echo $daysUntilExpiry <= 7 ? 'text-rose-600' : 'text-emerald-600'; ?>">
                                                <?php echo $daysUntilExpiry; ?> day<?php echo $daysUntilExpiry !== 1 ? 's' : ''; ?>
                                            </span>
                                            · <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <?php if ($canRenew): ?>
                                        <a href="/online-plaza/company/renew-subscription.php"
                                            class="inline-flex items-center bg-gradient-to-r from-orange-500 to-rose-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300">
                                            <i class="fas fa-sync-alt mr-2"></i>Renew Now
                                        </a>
                                    <?php else: ?>
                                        <button disabled
                                            class="inline-flex items-center bg-slate-200 text-slate-500 px-6 py-2.5 rounded-xl font-semibold cursor-not-allowed">
                                            <i class="fas fa-sync-alt mr-2"></i>Renew
                                        </button>
                                    <?php endif; ?>
                                    <a href="/online-plaza/company/subscription-history.php"
                                        class="inline-flex items-center border-2 border-emerald-500 text-emerald-700 px-6 py-2.5 rounded-xl font-semibold hover:bg-emerald-50 transition-all duration-300">
                                        <i class="fas fa-history mr-2"></i>History
                                    </a>
                                </div>
                            </div>

                            <?php if ($daysUntilExpiry <= 7): ?>
                                <div class="mt-4 bg-gradient-to-r from-amber-50 to-orange-50 border-2 border-amber-300 rounded-xl p-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-amber-400 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-exclamation-triangle text-white"></i>
                                        </div>
                                        <p class="text-amber-900 text-sm font-semibold">
                                            Your subscription expires soon. Renew now to continue enjoying all vendor features.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($subscription['status'] === 'expired'): ?>
                    <div class="bg-white border-2 border-rose-200 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-rose-100 to-red-100 rounded-full blur-3xl opacity-30 -mr-32 -mt-32"></div>
                        <div class="relative">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div class="flex items-center">
                                    <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                                        <i class="fas fa-crown text-white text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold text-slate-900 mb-1">Subscription Expired</h3>
                                        <p class="text-slate-600 text-sm">
                                            Expired on <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="/online-plaza/company/renew-subscription.php"
                                    class="inline-flex items-center bg-gradient-to-r from-rose-500 to-red-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300">
                                    <i class="fas fa-play-circle mr-2"></i>Activate Now
                                </a>
                            </div>
                            <div class="mt-4 bg-gradient-to-r from-rose-50 to-red-50 border-2 border-rose-300 rounded-xl p-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-rose-400 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-exclamation-circle text-white"></i>
                                    </div>
                                    <p class="text-rose-900 text-sm font-semibold">
                                        Your subscription has expired. Renew now to restore vendor features.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- No Subscription Found -->
            <div class="mb-6 sm:mb-8 bg-white border-2 border-indigo-200 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full blur-3xl opacity-30 -mr-32 -mt-32"></div>
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mr-4 shadow-lg">
                            <i class="fas fa-crown text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 mb-1">No Active Subscription</h3>
                            <p class="text-slate-600 text-sm">Get started with a vendor subscription to access all features</p>
                        </div>
                    </div>
                    <a href="/online-plaza/company/subscribe.php"
                        class="inline-flex items-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300">
                        <i class="fas fa-play-circle mr-2"></i>Subscribe Now
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-slate-200 group">
                <div class="flex items-center">
                    <div class="p-3 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-newspaper text-white text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-600 mb-1">Total Posts</p>
                        <p class="text-2xl sm:text-3xl font-bold bg-gradient-to-r from-emerald-600 to-green-600 bg-clip-text text-transparent"><?php echo $postsCount; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-slate-200 group">
                <div class="flex items-center">
                    <div class="p-3 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-box text-white text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-600 mb-1">Total Products</p>
                        <p class="text-2xl sm:text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent"><?php echo $productsCount; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-slate-200 group cursor-pointer" 
                 onclick="window.location.href='/online-plaza/company/orders.php'">
                <div class="flex items-center">
                    <div class="p-3 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-bell text-white text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-600 mb-1">Notifications</p>
                        <p class="text-2xl sm:text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent"><?php echo $unreadNotifications; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-slate-200 group">
                <div class="flex items-center">
                    <div class="p-3 bg-gradient-to-br from-rose-500 to-red-600 rounded-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-heart text-white text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-600 mb-1">Total Likes</p>
                        <p class="text-2xl sm:text-3xl font-bold bg-gradient-to-r from-rose-600 to-red-600 bg-clip-text text-transparent"><?php echo $totalLikes; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-slate-200 group cursor-pointer" 
                 onclick="window.location.href='/online-plaza/wallet/wallet.php'">
                <div class="flex items-center">
                    <div class="p-3 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-wallet text-white text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-600 mb-1">Wallet Balance</p>
                        <p class="text-sm sm:text-sm font-normal text-zinc-600 mb-1">Click to manage wallet</p>
                        <p class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-violet-600 to-purple-600 bg-clip-text text-transparent">₦<?php echo number_format($walletBalance, 2); ?></p>
                                                
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
            <!-- Quick Actions & Company Info (Left) -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Subscription Quick Info -->
                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-6 border border-slate-200">
                    <h2 class="text-xl font-bold mb-5 flex items-center text-slate-900">
                        <span class="w-1 h-6 bg-gradient-to-b from-violet-600 to-purple-600 rounded-full mr-3"></span>
                        Subscription
                    </h2>
                    <div class="space-y-4">
                        <?php if ($subscription): ?>
                            <div class="bg-gradient-to-br from-violet-50 to-purple-50 rounded-xl p-4 border-2 border-violet-200">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-semibold text-slate-700">Status</span>
                                    <span class="px-3 py-1.5 rounded-full text-xs font-bold 
                                        <?php echo $subscription['status'] === 'active' ? 'bg-emerald-100 text-emerald-700 border border-emerald-300' : 'bg-rose-100 text-rose-700 border border-rose-300'; ?>">
                                        <?php echo ucfirst($subscription['status']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-semibold text-slate-700">Amount</span>
                                    <span class="text-sm font-bold text-indigo-600">₦<?php echo number_format($subscription['amount'], 2); ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-slate-700">Expiry Date</span>
                                    <span class="text-sm font-bold <?php echo $daysUntilExpiry <= 7 ? 'text-rose-600' : 'text-slate-700'; ?>">
                                        <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-3">
                            <?php if ($subscription && $canRenew): ?>
                                <a href="/online-plaza/company/renew-subscription.php" 
                                   class="w-full bg-gradient-to-r from-orange-500 to-rose-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold">
                                    <i class="fas fa-sync-alt mr-2"></i>Renew Subscription
                                </a>
                            <?php elseif (!$subscription || $subscription['status'] === 'expired'): ?>
                                <a href="/online-plaza/company/subscribe.php" 
                                   class="w-full bg-gradient-to-r from-violet-600 to-purple-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold">
                                    <i class="fas fa-crown mr-2"></i>Get Subscription
                                </a>
                            <?php endif; ?>
                            <a href="/online-plaza/company/subscription-history.php" 
                               class="w-full border-2 border-violet-500 text-violet-700 py-3 px-4 rounded-xl hover:bg-violet-50 transition-all duration-300 flex items-center justify-center font-semibold">
                                <i class="fas fa-history mr-2"></i>Subscription History
                            </a>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-6 border border-slate-200">
                    <h2 class="text-xl font-bold mb-5 flex items-center text-slate-900">
                        <span class="w-1 h-6 bg-gradient-to-b from-emerald-600 to-green-600 rounded-full mr-3"></span>
                        Quick Actions
                    </h2>
                    <div class="space-y-3">
                        <a href="/online-plaza/posts/create.php" 
                           class="w-full bg-gradient-to-r from-emerald-600 to-green-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold group">
                            <i class="fas fa-plus mr-2 group-hover:rotate-90 transition-transform duration-300"></i>Create New Post
                        </a>
                        <a href="/online-plaza/company/orders.php" 
                           class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold">
                            <i class="fas fa-shopping-bag mr-2"></i>View Orders
                        </a>
                        <a href="/online-plaza/company/products.php" 
                           class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold">
                            <i class="fas fa-box mr-2"></i>Manage Products
                        </a>
                        <a href="/online-plaza/company/posts.php" 
                           class="w-full bg-gradient-to-r from-slate-600 to-gray-700 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold">
                            <i class="fas fa-newspaper mr-2"></i>Manage Posts
                        </a>
                        <a href="/online-plaza/wallet/wallet.php" 
                           class="w-full bg-gradient-to-r from-violet-600 to-purple-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-semibold">
                            <i class="fas fa-wallet mr-2"></i>Manage Wallet
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-6 border border-slate-200">
                    <h2 class="text-xl font-bold mb-5 flex items-center text-slate-900">
                        <span class="w-1 h-6 bg-gradient-to-b from-indigo-600 to-blue-600 rounded-full mr-3"></span>
                        Company Info
                    </h2>
                    <div class="space-y-4">
                        <div class="bg-gradient-to-br from-slate-50 to-blue-50 rounded-xl p-4 border border-slate-200">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Company Name</label>
                            <p class="text-sm text-slate-900 font-semibold"><?php echo htmlspecialchars($company['name']); ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-slate-50 to-blue-50 rounded-xl p-4 border border-slate-200">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Contact Email</label>
                            <p class="text-sm text-slate-900 truncate"><?php echo htmlspecialchars($company['contact_email']); ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-slate-50 to-blue-50 rounded-xl p-4 border border-slate-200">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Phone</label>
                            <p class="text-sm text-slate-900"><?php echo htmlspecialchars($company['phone']); ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-slate-50 to-blue-50 rounded-xl p-4 border border-slate-200">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Description</label>
                            <p class="text-sm text-slate-900 line-clamp-3"><?php echo htmlspecialchars($company['description']); ?></p>
                        </div>
                    </div>
                    <a href="/online-plaza/company/edit.php" 
                       class="mt-5 inline-flex items-center text-indigo-600 hover:text-indigo-800 font-semibold transition-colors duration-300">
                        <i class="fas fa-edit mr-2"></i>Edit Company Info
                    </a>
                </div>
            </div>

            <!-- Recent Posts (Right) -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-6 border border-slate-200">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl sm:text-2xl font-bold flex items-center text-slate-900">
                            <span class="w-1 h-7 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-3"></span>
                            Recent Posts
                        </h2>
                        <a href="/online-plaza/company/posts.php" 
                           class="text-indigo-600 hover:text-indigo-800 font-semibold transition-colors duration-300 flex items-center">
                            View All<i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <?php if ($recentPosts): ?>
                        <div class="space-y-4 overflow-y-auto custom-scrollbar" style="max-height: 650px;">
                            <?php foreach ($recentPosts as $post): ?>
                                <div class="border-2 border-slate-200 rounded-2xl p-4 sm:p-5 hover:border-indigo-300 hover:shadow-lg transition-all duration-300 bg-gradient-to-br from-white to-slate-50">
                                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                                        <div class="flex-1">
                                            <?php if ($post['media_url']): ?>
                                                <div class="w-full h-48 sm:h-56 bg-slate-100 rounded-xl mb-4 overflow-hidden">
                                                    <?php if ($post['media_type'] === 'image'): ?>
                                                        <img src="<?php echo htmlspecialchars($post['media_url']); ?>" 
                                                             alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                                             class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                                                    <?php else: ?>
                                                        <video class="w-full h-full object-cover" controls>
                                                            <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <h3 class="font-bold text-lg sm:text-xl mb-2 text-slate-900"><?php echo htmlspecialchars($post['title']); ?></h3>
                                            <p class="text-slate-600 text-sm mb-3 line-clamp-2"><?php echo substr(htmlspecialchars($post['content']), 0, 120); ?>...</p>
                                            <div class="flex items-center text-sm text-slate-500 space-x-5">
                                                <span class="inline-flex items-center font-medium">
                                                    <i class="fas fa-heart mr-1.5 text-rose-500"></i>
                                                    <?php echo $post['like_count']; ?> likes
                                                </span>
                                                <span class="inline-flex items-center font-medium">
                                                    <i class="fas fa-comment mr-1.5 text-indigo-500"></i>
                                                    <?php echo $post['comment_count']; ?> comments
                                                </span>
                                                <span class="inline-flex items-center text-slate-400 font-medium">
                                                    <i class="far fa-clock mr-1.5"></i>
                                                    <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex md:flex-col space-x-3 md:space-x-0 md:space-y-3">
                                            <a href="/online-plaza/posts/view.php?id=<?php echo $post['id']; ?>" 
                                               class="w-10 h-10 flex items-center justify-center bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-lg hover:shadow-lg hover:scale-110 transition-all duration-300" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/online-plaza/posts/edit.php?id=<?php echo $post['id']; ?>" 
                                               class="w-10 h-10 flex items-center justify-center bg-gradient-to-br from-emerald-500 to-green-600 text-white rounded-lg hover:shadow-lg hover:scale-110 transition-all duration-300" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="deletePost(<?php echo $post['id']; ?>)" 
                                                    class="w-10 h-10 flex items-center justify-center bg-gradient-to-br from-rose-500 to-red-600 text-white rounded-lg hover:shadow-lg hover:scale-110 transition-all duration-300" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-newspaper text-4xl text-indigo-400"></i>
                            </div>
                            <p class="text-slate-500 mb-5 text-sm sm:text-base">You haven't created any posts yet.</p>
                            <a href="/online-plaza/posts/create.php" 
                               class="inline-flex items-center bg-gradient-to-r from-emerald-600 to-green-600 text-white px-6 py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 font-semibold">
                                <i class="fas fa-plus-circle mr-2"></i>Create Your First Post
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom scrollbar for recent posts */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: linear-gradient(to bottom, #6366f1, #a855f7);
        border-radius: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(to bottom, #4f46e5, #9333ea);
    }

    @media (max-width: 700px) {
        .max-w-7xl {
            max-width: 100vw !important;
        }

        .rounded-2xl {
            border-radius: 1rem !important;
        }

        .shadow-lg,
        .shadow-xl {
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.08) !important;
        }

        .p-8 {
            padding: 1rem !important;
        }

        .p-6 {
            padding: 0.75rem !important;
        }

        .p-5 {
            padding: 0.75rem !important;
        }

        .mb-8 {
            margin-bottom: 1.5rem !important;
        }

        .gap-8,
        .gap-6,
        .gap-4 {
            gap: 1rem !important;
        }

        .w-20,
        .h-20 {
            width: 3.5rem !important;
            height: 3.5rem !important;
        }

        .w-16,
        .h-16 {
            width: 2.5rem !important;
            height: 2.5rem !important;
        }

        .w-14,
        .h-14 {
            width: 2.5rem !important;
            height: 2.5rem !important;
        }

        .text-3xl,
        .text-4xl {
            font-size: 1.5rem !important;
        }

        .text-2xl {
            font-size: 1.25rem !important;
        }

        .text-xl {
            font-size: 1.1rem !important;
        }

        .text-lg {
            font-size: 1rem !important;
        }

        .px-6 {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }

        .py-6 {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }

        .py-8 {
            padding-top: 1.25rem !important;
            padding-bottom: 1.25rem !important;
        }

        .flex-row,
        .md\:flex-row,
        .lg\:flex-row {
            flex-direction: column !important;
        }

        .md\:items-center,
        .md\:justify-between,
        .lg\:col-span-2,
        .lg\:col-span-1 {
            align-items: stretch !important;
            justify-content: flex-start !important;
        }

        .grid-cols-1,
        .md\:grid-cols-4,
        .lg\:grid-cols-3,
        .lg\:grid-cols-5 {
            grid-template-columns: 1fr !important;
        }

        .space-y-8,
        .space-y-6,
        .space-y-4,
        .space-y-3 {
            row-gap: 1rem !important;
        }

        .overflow-y-auto {
            max-height: 400px !important;
        }

        .sticky {
            position: static !important;
        }

        .border-2 {
            border-width: 1px !important;
        }
    }
</style>

<script>
    function deletePost(postId) {
        if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
            fetch('/online-plaza/posts/api/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting post: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the post.');
                });
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>