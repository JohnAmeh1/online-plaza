<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// If user is a vendor, redirect to company page
if ($currentUser['user_type'] === 'vendor') {
    header('Location: /online-plaza/company/dashboard.php');
    exit;
}

// Handle become vendor request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: /online-plaza/profile/become_vendor.php');
    exit;
}

// Get user's activities
$stmt = $pdo->prepare("
    SELECT a.*, 
           u.username as actor_username,
           CASE 
               WHEN a.reference_type = 'post' THEN (SELECT title FROM posts WHERE id = a.reference_id)
               WHEN a.reference_type = 'product' THEN (SELECT name FROM products WHERE id = a.reference_id)
               ELSE NULL
           END as reference_title
    FROM activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE a.user_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
");

$stmt->execute([$currentUser['id']]);
$activities = $stmt->fetchAll();

// Get user stats including wallet balance
$stmt = $pdo->prepare("
    SELECT u.*, 
           COALESCE(w.balance, 0) as wallet_balance,
           (SELECT COUNT(*) FROM users WHERE referred_by = u.id) as referred_count
    FROM users u 
    LEFT JOIN wallet w ON u.id = w.user_id 
    WHERE u.id = ?
");
$stmt->execute([$currentUser['id']]);
$currentUser = $stmt->fetch();

$referredUsers = $currentUser['referred_count'];
$walletBalance = $currentUser['wallet_balance'];

// Calculate referral earnings (400 naira per friend)
$referralEarnings = $referredUsers * 400;

// Get total referral earnings from transactions
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_referral_earnings 
    FROM transactions 
    WHERE user_id = ? AND description LIKE '%referral%' AND type = 'credit'
");
$stmt->execute([$currentUser['id']]);
$totalReferralEarnings = $stmt->fetchColumn();
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-2 sm:px-4 py-6 sm:py-8">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-green-500 to-blue-500 p-6 sm:p-8 text-white">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="w-20 h-20 sm:w-24 sm:h-24 bg-white rounded-full flex items-center justify-center text-green-500 text-2xl sm:text-3xl font-bold mr-4 sm:mr-6 shadow-lg border-4 border-green-100">
                        <?php echo strtoupper(substr($currentUser['first_name'] ?? '', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1)); ?>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></h1>
                        <p class="text-green-100 text-sm sm:text-base">@<?php echo htmlspecialchars($currentUser['username']); ?></p>
                        <p class="text-green-100 text-xs sm:text-base"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                    </div>
                </div>

                <!-- Wallet Balance & Referral Stats -->
                <div class="flex flex-col sm:flex-row gap-4 text-center">
                    <!-- Wallet Balance -->
                    <div class="bg-white bg-opacity-20 rounded-lg p-4 min-w-[140px]">
                        <div class="text-xl sm:text-2xl font-bold">₦<?php echo number_format($walletBalance, 2); ?></div>
                        <div class="text-green-100 text-sm">Wallet Balance</div>
                        <a href="/online-plaza/wallet/wallet.php"
                            class="inline-block mt-2 bg-white text-green-600 px-3 py-1 rounded text-xs font-semibold hover:bg-green-50 transition duration-300">
                            Manage Wallet
                        </a>
                    </div>

                    <!-- Referral Stats -->
                    <div class="bg-white bg-opacity-20 rounded-lg p-4 min-w-[140px]">
                        <div class="text-xl sm:text-2xl font-bold"><?php echo $referredUsers; ?></div>
                        <div class="text-green-100 text-sm">Referred Friends</div>
                        <div class="text-green-100 text-xs mt-1">₦400 per friend</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="p-4 sm:p-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 mb-8">
                <!-- User Info -->
                <div class="lg:col-span-2">
                    <h2 class="text-lg sm:text-xl font-semibold mb-4 text-green-700">Personal Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700">First Name</label>
                                <p class="mt-1 text-xs sm:text-sm text-gray-900"><?php echo htmlspecialchars($currentUser['first_name'] ?? 'Not set'); ?></p>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700">Last Name</label>
                                <p class="mt-1 text-xs sm:text-sm text-gray-900"><?php echo htmlspecialchars($currentUser['last_name'] ?? 'Not set'); ?></p>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700">Member Since</label>
                                <p class="mt-1 text-xs sm:text-sm text-gray-900"><?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700">Username</label>
                                <p class="mt-1 text-xs sm:text-sm text-gray-900"><?php echo htmlspecialchars($currentUser['username']); ?></p>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700">Email</label>
                                <p class="mt-1 text-xs sm:text-sm text-gray-900"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-gray-700">Account Type</label>
                                <p class="mt-1 text-xs sm:text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo ucfirst($currentUser['user_type']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold mb-4 text-blue-700">Quick Actions</h2>
                    <div class="space-y-3">
                        <form method="POST" action="">
                            <button type="submit" name="become_vendor" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-2 sm:py-3 px-3 sm:px-4 rounded-lg hover:from-green-600 hover:to-blue-600 transition duration-300 flex items-center justify-center font-semibold shadow text-sm sm:text-base">
                                <i class="fas fa-store mr-2"></i>
                                Start Selling on Martly
                            </button>
                        </form>
                        <button onclick="shareReferral()" class="w-full bg-gradient-to-r from-blue-500 to-green-500 text-white py-2 sm:py-3 px-3 sm:px-4 rounded-lg hover:from-blue-600 hover:to-green-600 transition duration-300 flex items-center justify-center font-semibold shadow text-sm sm:text-base">
                            <i class="fas fa-share-alt mr-2"></i>
                            Refer & Earn
                        </button>
                        <a href="/online-plaza/orders/index.php" class="w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-3 px-4 rounded-lg hover:from-purple-600 hover:to-pink-600 transition flex items-center justify-center font-semibold shadow text-sm sm:text-base">
                            <i class="fas fa-shopping-bag mr-2"></i> View Orders
                        </a>
                        <a href="/online-plaza/profile/edit.php" class="block w-full bg-gradient-to-r from-gray-500 to-gray-700 text-white py-2 sm:py-3 px-3 sm:px-4 rounded-lg hover:from-gray-600 hover:to-gray-800 transition duration-300 text-center font-semibold shadow text-sm sm:text-base">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Profile
                        </a>
                        <a href="/online-plaza/products/index.php" class="block w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-2 sm:py-3 px-3 sm:px-4 rounded-lg hover:from-purple-600 hover:to-pink-600 transition duration-300 text-center font-semibold shadow text-sm sm:text-base">
                            <i class="fas fa-shopping-bag mr-2"></i>
                            Browse Products
                        </a>
                    </div>
                </div>
            </div>

            <!-- Referral Section -->
            <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-lg p-4 sm:p-6 mb-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">Your Referral Program</h3>
                        <p class="text-gray-600 text-xs sm:text-sm mb-2">Share your code with friends and earn ₦400 for each friend who joins!</p>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-center text-yellow-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span class="text-sm font-semibold">Referral Rate: 1 Friend = ₦400</span>
                            </div>
                            <p class="text-yellow-700 text-xs mt-1">You earn ₦400 immediately when a friend signs up using your referral code.</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 sm:space-x-4">
                        <div class="bg-white px-3 sm:px-4 py-2 sm:py-3 rounded-lg border-2 border-green-300">
                            <code class="text-base sm:text-lg font-mono font-bold text-green-600"><?php echo htmlspecialchars($currentUser['referral_code']); ?></code>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="copyReferralCode()" class="bg-green-500 text-white p-2 sm:p-3 rounded-lg hover:bg-green-600 transition duration-300" title="Copy Code">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button onclick="shareReferral()" class="bg-blue-500 text-white p-2 sm:p-3 rounded-lg hover:bg-blue-600 transition duration-300" title="Share">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
                <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 text-center">
                    <div class="text-xl sm:text-2xl font-bold text-green-600 mb-1 sm:mb-2"><?php echo $referredUsers; ?></div>
                    <div class="text-gray-600 text-sm sm:text-base">Friends Referred</div>
                    <div class="text-green-500 text-xs font-semibold mt-1">Potential: ₦<?php echo number_format($referralEarnings, 2); ?></div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 text-center">
                    <div class="text-xl sm:text-2xl font-bold text-blue-600 mb-1 sm:mb-2"><?php echo count($activities); ?></div>
                    <div class="text-gray-600 text-sm sm:text-base">Recent Activities</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4 sm:p-6 text-center">
                    <div class="text-xl sm:text-2xl font-bold text-purple-600 mb-1 sm:mb-2">₦<?php echo number_format($totalReferralEarnings, 2); ?></div>
                    <div class="text-gray-600 text-sm sm:text-base">Referral Earnings</div>
                    <div class="text-purple-500 text-xs font-semibold mt-1">Total Earned</div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    @media (max-width: 700px) {
        .max-w-6xl {
            max-width: 100vw !important;
        }

        .rounded-2xl {
            border-radius: 1rem !important;
        }

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

        .w-24,
        .h-24 {
            width: 4rem !important;
            height: 4rem !important;
        }

        .w-20,
        .h-20 {
            width: 3.5rem !important;
            height: 3.5rem !important;
        }
    }
</style>

<script>
    function copyReferralCode() {
        const referralCode = '<?php echo $currentUser['referral_code']; ?>';
        navigator.clipboard.writeText(referralCode).then(function() {
            // Show success message
            showNotification('Referral code copied to clipboard!', 'success');
        }).catch(function(err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = referralCode;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotification('Referral code copied to clipboard!', 'success');
        });
    }

    function shareReferral() {
        const referralCode = '<?php echo $currentUser['referral_code']; ?>';
        const referralMessage = `Join me on Online Plaza! Use my referral code: ${referralCode} to sign up and get started. I'll earn ₦400 when you join!`;

        if (navigator.share) {
            navigator.share({
                title: 'Join Online Plaza',
                text: referralMessage,
                url: window.location.origin + '/online-plaza/auth/register.php?ref=' + referralCode
            }).then(() => {
                showNotification('Referral shared successfully!', 'success');
            }).catch(err => {
                console.log('Error sharing:', err);
            });
        } else {
            // Fallback - copy to clipboard with message
            navigator.clipboard.writeText(referralMessage + '\n' + window.location.origin + '/online-plaza/auth/register.php?ref=' + referralCode).then(function() {
                showNotification('Referral message copied to clipboard!', 'success');
            });
        }
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-semibold transform transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500' : 'bg-blue-500'
    }`;
        notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} mr-2"></i>
            ${message}
        </div>
    `;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Initialize notification positioning
    document.addEventListener('DOMContentLoaded', function() {
        const style = document.createElement('style');
        style.textContent = `
        .fixed.top-20.right-4 {
            transform: translateX(100%);
        }
    `;
        document.head.appendChild(style);
    });
</script>

<?php require_once '../includes/footer.php'; ?>
