<?php
require_once 'config.php';
require_once 'functions.php';
$currentUser = getCurrentUser();

// Get cart item count
$cartCount = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    $cartCount = $result['total'] ?: 0;
}

// Get unread notifications count for vendors
$unreadNotifications = 0;
if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor') {
    $notificationStmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $notificationStmt->execute([$currentUser['id']]);
    $unreadNotifications = $notificationStmt->fetchColumn();
}

// Get unread activity count
$unreadActivityCount = isLoggedIn() ? getUnreadActivityCount() : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Plaza - Your Digital Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/online-plaza/assets/css/style.css">
    <link rel="icon" href="../assets/martly.svg">
    <script src="/online-plaza/assets/js/auto-refresh.js"></script>

    <style>
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #ef4444, #ec4899);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            z-index: 10;
        }

        .cart-icon {
            position: relative;
            transition: all 0.3s ease;
        }

        .cart-icon:hover {
            transform: scale(1.1);
        }

        .cart-pulse {
            animation: pulse 2s infinite;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #ef4444, #ec4899);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            z-index: 10;
            padding: 0 4px;
        }

        .notification-badge-bottom {
            position: absolute;
            top: -2px;
            right: 8px;
            background: linear-gradient(135deg, #ef4444, #ec4899);
            color: white;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            font-size: 0.65rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .nav-blur {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .logo-gradient {
            background: linear-gradient(135deg, #10b981, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #10b981, #3b82f6);
        }

        .nav-link {
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link.active {
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            padding: 8px 0;
            z-index: 40;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 12px;
            font-size: 0.75rem;
            color: #6b7280;
            transition: all 0.3s ease;
            position: relative;
            flex: 1;
        }

        .bottom-nav-item.active {
            color: #10b981;
        }

        .bottom-nav-icon {
            font-size: 1.25rem;
            margin-bottom: 4px;
        }

        .bottom-nav-item-plus {
            flex: 0 0 auto;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="nav-blur shadow-lg sticky top-0 border-b border-gray-200 z-30">
        <div class="w-full mx-auto">
            <div class="flex justify-between items-center h-16 lg:h-20 px-4 lg:px-8">
                <!-- Logo -->
                <a href="/online-plaza/index.php" class="flex items-center space-x-3 group flex-shrink-0">
                    <div class="w-8 h-8 lg:w-10 lg:h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center shadow-lg transform group-hover:rotate-12 transition-transform duration-300">
                        <i class="fas fa-store text-white text-sm lg:text-lg"></i>
                    </div>
                    <span class="font-bold text-xl lg:text-2xl logo-gradient">Martly</span>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center justify-center flex-1 mx-4 lg:mx-8">
                    <div class="flex items-center space-x-1 lg:space-x-2">
                        <a href="/online-plaza/index.php" class="nav-link px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) === 'index.php' && !isset($_GET['page'])) ? 'active' : ''; ?>">
                            <i class="fas fa-home mr-2"></i>Home
                        </a>
                        <a href="/online-plaza/posts/index.php" class="nav-link px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'posts/') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-newspaper mr-2"></i>Posts
                        </a>
                        <a href="/online-plaza/products/index.php" class="nav-link px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'products/') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-bag mr-2"></i>Products
                        </a>
                        <?php if (isLoggedIn()): ?>
                            <a href="/online-plaza/activities/index.php" class="nav-link relative px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'activities/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-heart mr-2"></i>Activities
                                <?php if ($unreadActivityCount > 0): ?>
                                    <span class="notification-badge">
                                        <?php echo $unreadActivityCount > 9 ? '9+' : $unreadActivityCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <?php if ($currentUser && $currentUser['user_type'] === 'vendor'): ?>
                                <a href="/online-plaza/company/orders.php" class="nav-link relative px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'company/orders') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-bell mr-2"></i>Notifications
                                    <?php if ($unreadNotifications > 0): ?>
                                        <span class="notification-badge">
                                            <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <a href="/online-plaza/company/dashboard.php" class="nav-link px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'company/dashboard') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-building mr-2"></i>Company
                                </a>
                            <?php else: ?>
                                <a href="/online-plaza/profile/index.php" class="nav-link px-4 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'profile/') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-user mr-2"></i>Profile
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Desktop User Actions -->
                <div class="hidden md:flex items-center space-x-3 lg:space-x-4 flex-shrink-0">
                    <?php if (isLoggedIn()): ?>

                        <div class="flex items-center space-x-2 lg:space-x-3 px-3 lg:px-4 py-1.5 lg:py-2 bg-gradient-to-r from-green-50 to-blue-50 rounded-full border border-green-200">
                            <div class="w-7 h-7 lg:w-8 lg:h-8 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-xs lg:text-sm">
                                <?php echo strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1)); ?>
                            </div>
                            <span class="text-gray-700 font-semibold text-sm lg:text-base">Hello, <?php echo htmlspecialchars($currentUser['first_name'] ?? $currentUser['username']); ?></span>
                        </div>
                        <a href="/online-plaza/auth/logout.php" class="px-4 lg:px-6 py-1.5 lg:py-2.5 font-semibold text-gray-700 hover:text-red-600 border-2 border-gray-300 hover:border-red-400 rounded-xl transition-all duration-300 text-sm lg:text-base">
                            <i class="fas fa-sign-out-alt mr-1 lg:mr-2"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="/online-plaza/auth/login.php" class="px-4 lg:px-6 py-1.5 lg:py-2.5 font-semibold text-gray-700 hover:text-green-600 border-2 border-gray-300 hover:border-green-400 rounded-xl transition-all duration-300 text-sm lg:text-base">
                            Login
                        </a>
                        <a href="/online-plaza/auth/register.php" class="btn-gradient px-4 lg:px-6 py-1.5 lg:py-2.5 font-semibold text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 text-sm lg:text-base">
                            <span>Sign Up</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center space-x-3">
                    <?php if (isLoggedIn()): ?>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userDropdownBtn" type="button"
                                class="w-8 h-8 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                aria-haspopup="true" aria-expanded="false">
                                <?php echo strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1)); ?>
                            </button>
                            <div id="userDropdownMenu"
                                class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 z-50 py-2">
                                
                                <a href="/online-plaza/activities/index.php" class="block px-4 py-2 text-gray-700 hover:bg-green-50">
                                    <i class="fas fa-heart mr-3 text-green-600"></i>Activities
                                    <?php if ($unreadActivityCount > 0): ?>
                                        <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">
                                            <?php echo $unreadActivityCount > 9 ? '9+' : $unreadActivityCount; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>

                                <?php if ($currentUser && $currentUser['user_type'] === 'vendor'): ?>
                                    <a href="/online-plaza/company/orders.php" class="block px-4 py-2 text-gray-700 hover:bg-green-50">
                                        <i class="fas fa-bell mr-3 text-green-600"></i>Notifications
                                        <?php if ($unreadNotifications > 0): ?>
                                            <span class="ml-2 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">
                                                <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="/online-plaza/company/dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-green-50">
                                        <i class="fas fa-building mr-3 text-green-600"></i>Company
                                    </a>
                                
                                <?php endif; ?>
                                
                                <div class="border-t border-gray-100 my-2"></div>
                                <a href="/online-plaza/auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/online-plaza/auth/login.php" class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-600">
                            <i class="fas fa-user text-sm"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- YouTube-style Bottom Navigation (Mobile only) -->
    <nav class="bottom-nav md:hidden">
        <div class="flex justify-around items-center">
            <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor'): ?>
                <!-- Posts -->
                <a href="/online-plaza/posts/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'posts/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper bottom-nav-icon"></i>
                    <span>Posts</span>
                </a>
                <!-- Products -->
                <a href="/online-plaza/products/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'products/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag bottom-nav-icon"></i>
                    <span>Products</span>
                </a>
                <!-- Plus Button (center, styled like YouTube) -->
                <a href="/online-plaza/posts/create.php" class="bottom-nav-item-plus flex items-center justify-center" style="margin-top:-1.5rem;">
                    <span class="w-14 h-14 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg border-4 border-white text-white text-2xl font-bold transition-transform duration-200 hover:scale-110">
                        <i class="fas fa-plus"></i>
                    </span>
                </a>
                <!-- Notifications -->
                <a href="/online-plaza/company/orders.php" class="bottom-nav-item relative <?php echo strpos($_SERVER['REQUEST_URI'], 'company/orders') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-bell bottom-nav-icon"></i>
                    <span>Notifications</span>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge-bottom">
                            <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <!-- Company -->
                <a href="/online-plaza/company/dashboard.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'company/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-building bottom-nav-icon"></i>
                    <span>Company</span>
                </a>
            <?php else: ?>
                <!-- Home -->
                <a href="/online-plaza/index.php" class="bottom-nav-item <?php echo (basename($_SERVER['PHP_SELF']) === 'index.php' && !isset($_GET['page'])) ? 'active' : ''; ?>">
                    <i class="fas fa-home bottom-nav-icon"></i>
                    <span>Home</span>
                </a>
                <!-- Posts -->
                <a href="/online-plaza/posts/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'posts/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper bottom-nav-icon"></i>
                    <span>Posts</span>
                </a>
                <!-- Products -->
                <a href="/online-plaza/products/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'products/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag bottom-nav-icon"></i>
                    <span>Products</span>
                </a>
                <!-- Activities -->
                <a href="/online-plaza/activities/index.php" class="bottom-nav-item relative <?php echo strpos($_SERVER['REQUEST_URI'], 'activities/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-heart bottom-nav-icon"></i>
                    <span>Activities</span>
                    <?php if ($unreadActivityCount > 0): ?>
                        <span class="notification-badge-bottom">
                            <?php echo $unreadActivityCount > 9 ? '9+' : $unreadActivityCount; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <!-- Profile -->
                <a href="/online-plaza/profile/index.php" class="bottom-nav-item <?php echo strpos($_SERVER['REQUEST_URI'], 'profile/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-user bottom-nav-icon"></i>
                    <span>Profile</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('userDropdownBtn');
            const menu = document.getElementById('userDropdownMenu');
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', function(e) {
                    if (!btn.contains(e.target)) {
                        menu.classList.add('hidden');
                    }
                });
            }
        });
    </script>