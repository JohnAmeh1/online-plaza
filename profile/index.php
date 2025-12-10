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
if ($currentUser['user_type'] === 'admin') {
    header('Location: /online-plaza/admin/index.php');
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

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    <div class="max-w-6xl mx-auto px-3 sm:px-4 py-4 sm:py-8">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
            <!-- Profile Header -->
            <div class="relative bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-6 sm:p-8 text-white overflow-hidden">
                <div class="absolute inset-0 opacity-20">
                    <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl"></div>
                </div>
                
                <div class="relative flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-6 text-center sm:text-left w-full md:w-auto">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 bg-white rounded-2xl flex items-center justify-center text-indigo-600 text-3xl sm:text-4xl font-bold shadow-2xl border-4 border-white/30 backdrop-blur-sm">
                            <?php echo strtoupper(substr($currentUser['first_name'] ?? '', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1)); ?>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-1"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></h1>
                            <p class="text-indigo-100 text-sm sm:text-base font-medium mb-1">@<?php echo htmlspecialchars($currentUser['username']); ?></p>
                            <p class="text-indigo-100 text-xs sm:text-sm"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                        </div>
                    </div>

                    <!-- Wallet Balance & Referral Stats -->
                    <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                        <!-- Wallet Balance -->
                        <div class="bg-white/20 backdrop-blur-md rounded-xl p-4 sm:p-5 min-w-[160px] border border-white/30 hover:bg-white/25 transition-all duration-300">
                            <div class="text-2xl sm:text-3xl font-bold mb-1">₦<?php echo number_format($walletBalance, 2); ?></div>
                            <div class="text-indigo-50 text-sm font-medium mb-3">Wallet Balance</div>
                            <a href="/online-plaza/wallet/wallet.php"
                                class="inline-flex items-center justify-center w-full bg-white text-indigo-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-indigo-50 transition-all duration-300 shadow-lg">
                                <i class="fas fa-wallet mr-2"></i>Manage Wallet
                            </a>
                        </div>

                        <!-- Referral Stats -->
                        <div class="bg-white/20 backdrop-blur-md rounded-xl p-4 sm:p-5 min-w-[160px] border border-white/30 hover:bg-white/25 transition-all duration-300">
                            <div class="text-2xl sm:text-3xl font-bold mb-1"><?php echo $referredUsers; ?></div>
                            <div class="text-indigo-50 text-sm font-medium mb-1">Referred Friends</div>
                            <div class="text-indigo-100 text-xs font-semibold bg-white/10 rounded-full px-3 py-1 inline-block">
                                ₦400 per friend
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="p-4 sm:p-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 mb-8">
                    <!-- User Info -->
                    <div class="lg:col-span-2">
                        <h2 class="text-xl sm:text-2xl font-bold mb-5 flex items-center text-slate-900">
                            <span class="w-1 h-7 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-3"></span>
                            Personal Information
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                            <div class="space-y-4">
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200">
                                    <label class="block text-xs font-bold text-slate-600 mb-2">First Name</label>
                                    <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($currentUser['first_name'] ?? 'Not set'); ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200">
                                    <label class="block text-xs font-bold text-slate-600 mb-2">Last Name</label>
                                    <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($currentUser['last_name'] ?? 'Not set'); ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200">
                                    <label class="block text-xs font-bold text-slate-600 mb-2">Member Since</label>
                                    <p class="text-sm font-semibold text-slate-900"><?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200">
                                    <label class="block text-xs font-bold text-slate-600 mb-2">Username</label>
                                    <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($currentUser['username']); ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200">
                                    <label class="block text-xs font-bold text-slate-600 mb-2">Email</label>
                                    <p class="text-sm font-semibold text-slate-900 truncate"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-4 border border-indigo-200">
                                    <label class="block text-xs font-bold text-slate-600 mb-2">Account Type</label>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow-lg">
                                        <i class="fas fa-user mr-1.5"></i><?php echo ucfirst($currentUser['user_type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold mb-5 flex items-center text-slate-900">
                            <span class="w-1 h-7 bg-gradient-to-b from-emerald-600 to-green-600 rounded-full mr-3"></span>
                            Quick Actions
                        </h2>
                        <div class="space-y-3">
                            <form method="POST" action="">
                                <button type="submit" name="become_vendor" 
                                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-bold shadow-lg group">
                                    <i class="fas fa-store mr-2 group-hover:scale-110 transition-transform"></i>
                                    Start Selling on Martly
                                </button>
                            </form>
                            <button onclick="shareReferral()" 
                                    class="w-full bg-gradient-to-r from-emerald-600 to-green-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-bold shadow-lg group">
                                <i class="fas fa-share-alt mr-2 group-hover:rotate-12 transition-transform"></i>
                                Refer & Earn
                            </button>
                            <a href="/online-plaza/orders/index.php" 
                               class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center justify-center font-bold shadow-lg">
                                <i class="fas fa-shopping-cart mr-2"></i>View Orders
                            </a>
                            <a href="/online-plaza/profile/edit.php" 
                               class="block w-full bg-gradient-to-r from-slate-600 to-gray-700 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 text-center font-bold shadow-lg">
                                <i class="fas fa-edit mr-2"></i>
                                Edit Profile
                            </a>
                            <a href="/online-plaza/products/index.php" 
                               class="block w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 px-4 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 text-center font-bold shadow-lg">
                                <i class="fas fa-shopping-bag mr-2"></i>
                                Browse Products
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Referral Section -->
                <div class="bg-white border-2 border-emerald-200 rounded-2xl p-5 sm:p-6 mb-8 shadow-xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-100 to-green-100 rounded-full blur-3xl opacity-30 -mr-32 -mt-32"></div>
                    
                    <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center mr-4">
                                    <i class="fas fa-gift text-white text-xl"></i>
                                </div>
                                <h3 class="text-xl sm:text-2xl font-bold text-slate-900">Your Referral Program</h3>
                            </div>
                            <p class="text-slate-600 text-sm sm:text-base mb-4">Share your code with friends and earn ₦400 for each friend who joins!</p>
                            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-2 border-amber-300 rounded-xl p-4">
                                <div class="flex items-center text-amber-900 mb-2">
                                    <div class="w-8 h-8 bg-amber-400 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-info-circle text-white"></i>
                                    </div>
                                    <span class="text-sm font-bold">Referral Rate: 1 Friend = ₦400</span>
                                </div>
                                <p class="text-amber-800 text-xs font-medium ml-11">You earn ₦400 immediately when a friend signs up using your referral code.</p>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                            <div class="bg-gradient-to-br from-emerald-50 to-green-50 px-5 py-4 rounded-xl border-2 border-emerald-400 shadow-lg">
                                <code class="text-xl sm:text-2xl font-mono font-bold text-emerald-600"><?php echo htmlspecialchars($currentUser['referral_code']); ?></code>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="copyReferralCode()" 
                                        class="bg-gradient-to-br from-emerald-500 to-green-600 text-white p-3 rounded-xl hover:shadow-lg hover:scale-110 transition-all duration-300" 
                                        title="Copy Code">
                                    <i class="fas fa-copy text-lg"></i>
                                </button>
                                <button onclick="shareReferral()" 
                                        class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-3 rounded-xl hover:shadow-lg hover:scale-110 transition-all duration-300" 
                                        title="Share">
                                    <i class="fas fa-share-alt text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <div class="bg-white border-2 border-slate-200 rounded-2xl p-5 sm:p-6 text-center hover:shadow-xl hover:border-emerald-300 transition-all duration-300 group">
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-users text-white text-2xl"></i>
                        </div>
                        <div class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-emerald-600 to-green-600 bg-clip-text text-transparent mb-2"><?php echo $referredUsers; ?></div>
                        <div class="text-slate-600 text-sm sm:text-base font-semibold mb-2">Friends Referred</div>
                        <div class="text-emerald-600 text-xs font-bold bg-emerald-50 rounded-full px-3 py-1 inline-block">
                            Potential: ₦<?php echo number_format($referralEarnings, 2); ?>
                        </div>
                    </div>
                    
                    <div class="bg-white border-2 border-slate-200 rounded-2xl p-5 sm:p-6 text-center hover:shadow-xl hover:border-indigo-300 transition-all duration-300 group">
                        <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-chart-line text-white text-2xl"></i>
                        </div>
                        <div class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent mb-2"><?php echo count($activities); ?></div>
                        <div class="text-slate-600 text-sm sm:text-base font-semibold">Recent Activities</div>
                    </div>
                    
                    <div class="bg-white border-2 border-slate-200 rounded-2xl p-5 sm:p-6 text-center hover:shadow-xl hover:border-violet-300 transition-all duration-300 group">
                        <div class="w-16 h-16 bg-gradient-to-br from-violet-500 to-purple-600 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-coins text-white text-2xl"></i>
                        </div>
                        <div class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-violet-600 to-purple-600 bg-clip-text text-transparent mb-2">₦<?php echo number_format($totalReferralEarnings, 2); ?></div>
                        <div class="text-slate-600 text-sm sm:text-base font-semibold mb-2">Referral Earnings</div>
                        <div class="text-violet-600 text-xs font-bold bg-violet-50 rounded-full px-3 py-1 inline-block">
                            Total Earned
                        </div>
                    </div>
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

        .p-6, .p-5 {
            padding: 0.75rem !important;
        }

        .mb-8 {
            margin-bottom: 1.5rem !important;
        }

        .gap-8, .gap-6, .gap-4 {
            gap: 1rem !important;
        }

        .w-28, .h-28 {
            width: 5rem !important;
            height: 5rem !important;
        }

        .w-24, .h-24 {
            width: 4.5rem !important;
            height: 4.5rem !important;
        }

        .text-4xl {
            font-size: 2rem !important;
        }

        .text-3xl {
            font-size: 1.5rem !important;
        }

        .text-2xl {
            font-size: 1.25rem !important;
        }

        .border-2 {
            border-width: 1px !important;
        }
    }
</style>

<script>
    function copyReferralCode() {
        const referralCode = '<?php echo $currentUser['referral_code']; ?>';
        navigator.clipboard.writeText(referralCode).then(function() {
            showNotification('Referral code copied to clipboard!', 'success');
        }).catch(function(err) {
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
            navigator.clipboard.writeText(referralMessage + '\n' + window.location.origin + '/online-plaza/auth/register.php?ref=' + referralCode).then(function() {
                showNotification('Referral message copied to clipboard!', 'success');
            });
        }
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-4 z-50 px-6 py-4 rounded-xl shadow-2xl text-white font-bold transform transition-all duration-300 translate-x-full ${
            type === 'success' ? 'bg-gradient-to-r from-emerald-500 to-green-600' : 'bg-gradient-to-r from-blue-500 to-indigo-600'
        }`;
        notification.innerHTML = `
            <div class="flex items-center">
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                </div>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
</script>

<?php require_once '../includes/footer.php'; ?>