<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $currentUser['id']]);
        
        if ($stmt->fetch()) {
            $error = 'Email already exists. Please use a different email.';
        } else {
            // Update user
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$first_name, $last_name, $email, $currentUser['id']])) {
                $success = 'Profile updated successfully!';
                
                // Refresh current user data from DB
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$currentUser['id']]);
                $currentUser = $stmt->fetch();
                
                // Update session
                $_SESSION['user'] = $currentUser;
            } else {
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}

// Get user's referral stats
$stmt = $pdo->prepare("
    SELECT u.username, u.created_at 
    FROM users u 
    WHERE u.referred_by = ? 
    ORDER BY u.created_at DESC
");
$stmt->execute([$currentUser['id']]);
$referredUsers = $stmt->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-500 to-blue-500 p-8 text-white">
            <h1 class="text-3xl font-extrabold mb-2">Edit Profile</h1>
            <p class="text-green-100">Update your personal information</p>
            <div class="mt-4 flex justify-center">
                <div class="w-16 h-1 bg-white rounded-full"></div>
            </div>
        </div>

        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Edit Form -->
                <div class="lg:col-span-2">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-base font-medium text-gray-700 mb-2">
                                    First Name *
                                </label>
                                <input type="text" id="first_name" name="first_name" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                       placeholder="Enter your first name"
                                       value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="last_name" class="block text-base font-medium text-gray-700 mb-2">
                                    Last Name *
                                </label>
                                <input type="text" id="last_name" name="last_name" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                       placeholder="Enter your last name"
                                       value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-base font-medium text-gray-700 mb-2">
                                Email Address *
                            </label>
                            <input type="email" id="email" name="email" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition duration-300"
                                   placeholder="your@email.com"
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>">
                        </div>

                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" disabled 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500"
                                   value="<?php echo htmlspecialchars($currentUser['username']); ?>">
                            <p class="text-sm text-gray-500 mt-1">Username cannot be changed for security reasons.</p>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <a href="/online-plaza/profile/index.php" class="text-gray-600 hover:text-gray-800 font-medium flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Profile
                            </a>
                            <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-8 py-3 rounded-lg hover:from-green-600 hover:to-blue-600 transition font-semibold flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Referral Information -->
                <div class="space-y-6">
                    <!-- Referral Code -->
                    <div class="bg-gradient-to-br from-green-50 to-blue-50 border border-green-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Your Referral Code</h3>
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="flex-1 bg-white px-4 py-3 rounded-lg border-2 border-green-300">
                                <code class="text-lg font-mono font-bold text-green-600 break-all">
                                    <?php echo htmlspecialchars($currentUser['referral_code']); ?>
                                </code>
                            </div>
                            <button onclick="copyReferralCode()" 
                                    class="bg-green-500 text-white p-3 rounded-lg hover:bg-green-600 transition duration-300 flex-shrink-0"
                                    title="Copy Referral Code">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <p class="text-sm text-gray-600">
                            Share this code with friends to earn rewards when they sign up!
                        </p>
                    </div>

                    <!-- Referral Stats -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Referral Stats</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total Referrals:</span>
                                <span class="font-semibold text-green-600"><?php echo count($referredUsers); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Rewards Earned:</span>
                                <span class="font-semibold text-blue-600">$0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Referrals -->
                    <?php if ($referredUsers): ?>
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Referrals</h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($referredUsers, 0, 3) as $referral): ?>
                                <div class="flex items-center justify-between py-2">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-bold mr-3">
                                            <?php echo strtoupper(substr($referral['username'], 0, 1)); ?>
                                        </div>
                                        <span class="text-sm font-medium"><?php echo htmlspecialchars($referral['username']); ?></span>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        <?php echo date('M j', strtotime($referral['created_at'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyReferralCode() {
    const referralCode = '<?php echo $currentUser['referral_code']; ?>';
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(referralCode).then(() => {
            showNotification('Referral code copied to clipboard!', 'success');
        }).catch(err => {
            console.error('Failed to copy: ', err);
            showNotification('Failed to copy referral code', 'error');
        });
    } else {
        // Fallback for older browsers
        const tempInput = document.createElement('input');
        tempInput.value = referralCode;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showNotification('Referral code copied to clipboard!', 'success');
    }
}

function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(notification => notification.remove());

    // Create new notification
    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.add('translate-x-0');
    }, 10);

    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Add some CSS for the notification
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        .custom-notification {
            transform: translateX(100%);
        }
        .custom-notification.translate-x-0 {
            transform: translateX(0);
        }
        .custom-notification.translate-x-full {
            transform: translateX(100%);
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once '../includes/footer.php'; ?>
