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

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Dashboard Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-1">Company Dashboard</h1>
            <p class="text-gray-600">Manage your business on Online Plaza</p>
        </div>
        <a href="/online-plaza/company/index.php?id=<?php echo $company['id']; ?>" class="mt-4 md:mt-0 inline-flex items-center bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-2 rounded-lg font-semibold shadow hover:from-green-600 hover:to-blue-600 transition">
            <i class="fas fa-store mr-2"></i> View Public Page
        </a>
    </div>

    <!-- Subscription Status Banner -->
    <?php if ($subscription): ?>
        <div class="mb-8">
            <?php if ($subscription['status'] === 'active'): ?>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center mb-4 md:mb-0">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-crown text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-green-800">Active Subscription</h3>
                                <p class="text-green-600 text-sm">
                                    Expires in
                                    <span class="font-bold <?php echo $daysUntilExpiry <= 7 ? 'text-red-600' : 'text-green-700'; ?>">
                                        <?php echo $daysUntilExpiry; ?> day<?php echo $daysUntilExpiry !== 1 ? 's' : ''; ?>
                                    </span>
                                    - <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <?php if ($canRenew): ?>
                                <a href="/online-plaza/company/renew-subscription.php"
                                    class="bg-gradient-to-r from-orange-500 to-red-500 text-white px-6 py-2 rounded-lg font-semibold hover:from-orange-600 hover:to-red-600 transition shadow">
                                    <i class="fas fa-sync-alt mr-2"></i> Renew Now
                                </a>
                            <?php else: ?>
                                <button disabled
                                    class="bg-gray-300 text-gray-500 px-6 py-2 rounded-lg font-semibold cursor-not-allowed">
                                    <i class="fas fa-sync-alt mr-2"></i> Renew
                                </button>
                            <?php endif; ?>
                            <a href="/online-plaza/company/subscription-history.php"
                                class="border border-green-500 text-green-600 px-6 py-2 rounded-lg font-semibold hover:bg-green-50 transition">
                                <i class="fas fa-history mr-2"></i> History
                            </a>
                        </div>
                    </div>

                    <?php if ($daysUntilExpiry <= 7): ?>
                        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                <p class="text-yellow-800 text-sm font-medium">
                                    Your subscription expires soon. Renew now to continue enjoying all vendor features.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($subscription['status'] === 'expired'): ?>
                <div class="bg-gradient-to-r from-red-50 to-orange-50 border border-red-200 rounded-xl p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center mb-4 md:mb-0">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                                <i class="fas fa-crown text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-red-800">Subscription Expired</h3>
                                <p class="text-red-600 text-sm">
                                    Expired on <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <a href="/online-plaza/company/renew-subscription.php"
                            class="bg-gradient-to-r from-red-500 to-orange-500 text-white px-6 py-2 rounded-lg font-semibold hover:from-red-600 hover:to-orange-600 transition shadow">
                            <i class="fas fa-play-circle mr-2"></i> Activate Now
                        </a>
                    </div>
                    <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                            <p class="text-red-800 text-sm font-medium">
                                Your subscription has expired. Renew now to restore vendor features.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- No Subscription Found -->
        <div class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-crown text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-800">No Active Subscription</h3>
                        <p class="text-blue-600 text-sm">Get started with a vendor subscription to access all features</p>
                    </div>
                </div>
                <a href="/online-plaza/company/subscribe.php"
                    class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white px-6 py-2 rounded-lg font-semibold hover:from-blue-600 hover:to-indigo-600 transition shadow">
                    <i class="fas fa-play-circle mr-2"></i> Subscribe Now
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <i class="fas fa-newspaper text-green-600 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Posts</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $postsCount; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <i class="fas fa-box text-blue-600 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Products</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $productsCount; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center cursor-pointer" onclick="window.location.href='/online-plaza/company/orders.php'">
            <div class="p-3 bg-purple-100 rounded-lg">
                <i class="fas fa-bell text-purple-600 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Unread Notifications</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $unreadNotifications; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center">
            <div class="p-3 bg-red-100 rounded-lg">
                <i class="fas fa-heart text-red-600 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Likes</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $totalLikes; ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-lg flex items-center cursor-pointer hover:shadow-xl transition duration-300" onclick="window.location.href='/online-plaza/wallet/wallet.php'">
            <div class="p-3 bg-purple-100 rounded-lg">
                <i class="fas fa-wallet text-purple-600 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Wallet Balance</p>
                <p class="text-2xl font-bold text-purple-600">₦<?php echo number_format($walletBalance, 2); ?></p>
                <p class="text-xs text-gray-500 mt-1 hover:text-purple-600 transition duration-300">Click to manage wallet</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Quick Actions & Company Info (Left) -->
        <div class="lg:col-span-1 space-y-8">
            <!-- Subscription Quick Info -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4 text-purple-700">Subscription</h2>
                <div class="space-y-4">
                    <?php if ($subscription): ?>
                        <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg p-4 border border-purple-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-purple-700">Status</span>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                    <?php echo $subscription['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($subscription['status']); ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-purple-700">Amount</span>
                                <span class="text-sm font-semibold">₦<?php echo number_format($subscription['amount'], 2); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-purple-700">Expiry Date</span>
                                <span class="text-sm font-semibold <?php echo $daysUntilExpiry <= 7 ? 'text-red-600' : 'text-gray-600'; ?>">
                                    <?php echo date('M j, Y', strtotime($subscription['expiry_date'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-3">
                        <?php if ($subscription && $canRenew): ?>
                            <a href="/online-plaza/company/renew-subscription.php" class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white py-3 px-4 rounded-lg hover:from-orange-600 hover:to-red-600 transition flex items-center justify-center font-semibold shadow">
                                <i class="fas fa-sync-alt mr-2"></i> Renew Subscription
                            </a>
                        <?php elseif (!$subscription || $subscription['status'] === 'expired'): ?>
                            <a href="/online-plaza/company/subscribe.php" class="w-full bg-gradient-to-r from-purple-500 to-blue-500 text-white py-3 px-4 rounded-lg hover:from-purple-600 hover:to-blue-600 transition flex items-center justify-center font-semibold shadow">
                                <i class="fas fa-crown mr-2"></i> Get Subscription
                            </a>
                        <?php endif; ?>
                        <a href="/online-plaza/company/subscription-history.php" class="w-full border border-purple-500 text-purple-600 py-3 px-4 rounded-lg hover:bg-purple-50 transition flex items-center justify-center font-semibold">
                            <i class="fas fa-history mr-2"></i> Subscription History
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4 text-green-700">Quick Actions</h2>
                <div class="space-y-3">
                    <a href="/online-plaza/posts/create.php" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 px-4 rounded-lg hover:from-green-600 hover:to-blue-600 transition flex items-center justify-center font-semibold shadow">
                        <i class="fas fa-plus mr-2"></i> Create New Post
                    </a>
                    <a href="/online-plaza/company/orders.php" class="w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-3 px-4 rounded-lg hover:from-purple-600 hover:to-pink-600 transition flex items-center justify-center font-semibold shadow">
                        <i class="fas fa-shopping-bag mr-2"></i> View Orders
                    </a>
                    <a href="/online-plaza/company/products.php" class="w-full bg-gradient-to-r from-blue-500 to-green-500 text-white py-3 px-4 rounded-lg hover:from-blue-600 hover:to-green-600 transition flex items-center justify-center font-semibold shadow">
                        <i class="fas fa-box mr-2"></i> Manage Products
                    </a>
                    <a href="/online-plaza/company/posts.php" class="w-full bg-gradient-to-r from-gray-500 to-gray-700 text-white py-3 px-4 rounded-lg hover:from-gray-600 hover:to-gray-800 transition flex items-center justify-center font-semibold shadow">
                        <i class="fas fa-newspaper mr-2"></i> Manage Posts
                    </a>
                    <a href="/online-plaza/wallet/wallet.php" class="w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-3 px-4 rounded-lg hover:from-purple-600 hover:to-pink-600 transition flex items-center justify-center font-semibold shadow">
                        <i class="fas fa-wallet mr-2"></i> Manage Wallet
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4 text-blue-700">Company Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Company Name</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold"><?php echo htmlspecialchars($company['name']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Email</label>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($company['contact_email']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($company['phone']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($company['description']); ?></p>
                    </div>
                </div>
                <a href="/online-plaza/company/edit.php" class="mt-4 inline-flex items-center text-green-600 hover:text-green-800 font-medium">
                    <i class="fas fa-edit mr-1"></i> Edit Company Info
                </a>
            </div>
        </div>

        <!-- Recent Posts (Right) -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Posts</h2>
                    <a href="/online-plaza/company/posts.php" class="text-green-600 hover:text-green-800 font-medium">View All</a>
                </div>
                <?php if ($recentPosts): ?>
                    <div class="space-y-4 overflow-y-auto custom-scrollbar" style="max-height: 550px;">
                        <?php foreach ($recentPosts as $post): ?>
                            <div class="border border-gray-200 rounded-xl p-4 hover:bg-gradient-to-r hover:from-green-50 hover:to-blue-50 transition duration-300">
                                <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                                    <div class="flex-1 md:mr-4">
                                        <?php if ($post['media_url']): ?>
                                            <div class="w-full h-48 bg-gray-100 rounded-lg mb-3 overflow-hidden">
                                                <?php if ($post['media_type'] === 'image'): ?>
                                                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <video class="w-full h-full object-cover" controls>
                                                        <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <h3 class="font-semibold text-lg mb-1 text-gray-900"><?php echo htmlspecialchars($post['title']); ?></h3>
                                        <p class="text-gray-600 text-sm mb-2"><?php echo substr(htmlspecialchars($post['content']), 0, 120); ?>...</p>
                                        <div class="flex items-center text-sm text-gray-500 space-x-4">
                                            <span class="flex items-center">
                                                <i class="fas fa-heart mr-1"></i>
                                                <?php echo $post['like_count']; ?> likes
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-comment mr-1"></i>
                                                <?php echo $post['comment_count']; ?> comments
                                            </span>
                                            <span class="text-gray-400">
                                                <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-row md:flex-col space-x-2 md:space-x-0 md:space-y-2 mt-4 md:mt-0 md:ml-4">
                                        <a href="/online-plaza/posts/view.php?id=<?php echo $post['id']; ?>" class="text-blue-600 hover:text-blue-800" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/online-plaza/posts/edit.php?id=<?php echo $post['id']; ?>" class="text-green-600 hover:text-green-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="deletePost(<?php echo $post['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-newspaper text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 mb-4">You haven't created any posts yet.</p>
                        <a href="/online-plaza/posts/create.php" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-2 rounded-lg hover:from-green-600 hover:to-blue-600 transition font-semibold">
                            Create Your First Post
                        </a>
                    </div>
                <?php endif; ?>
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
        background: linear-gradient(to bottom, #22c55e, #3b82f6);
        border-radius: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 6px;
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
        .lg\:grid-cols-3 {
            grid-template-columns: 1fr !important;
        }

        .space-y-8,
        .space-y-6,
        .space-y-4,
        .space-y-3 {
            row-gap: 1rem !important;
        }

        .overflow-y-auto {
            max-height: 350px !important;
        }

        .sticky {
            position: static !important;
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