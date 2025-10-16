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
    <title>Martly - Your Digital Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" href="../assets/martly.svg">
    <script src="/online-plaza/assets/js/auto-refresh.js"></script>

    <!-- Fix PWA manifest and icons -->
    <link rel="manifest" href="/online-plaza/pwa/manifest.json">
    <link rel="icon" type="image/png" sizes="32x32" href="/online-plaza/pwa/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/online-plaza/pwa/icons/icon-16x16.png">
    <link rel="apple-touch-icon" href="/online-plaza/pwa/icons/icon-192x192.png">

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/online-plaza/pwa/manifest.json">
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Martly">
    <link rel="apple-touch-icon" href="/online-plaza/pwa/icons/icon-192x192.png">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        /* PWA Loading Animation */
        .pwa-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.3s;
        }

        .pwa-loading.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
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
            z-index: 10;
            padding: 0 3px;
        }

        .notification-badge-bottom {
            position: absolute;
            top: -2px;
            right: 6px;
            background: linear-gradient(135deg, #ef4444, #ec4899);
            color: white;
            border-radius: 50%;
            min-width: 16px;
            height: 16px;
            font-size: 0.6rem;
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
            font-size: 0.875rem;
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
            padding: 6px 0;
            z-index: 40;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 6px 8px;
            font-size: 0.7rem;
            color: #6b7280;
            transition: all 0.3s ease;
            position: relative;
            flex: 1;
        }

        .bottom-nav-item.active {
            color: #10b981;
        }

        .bottom-nav-icon {
            font-size: 1.1rem;
            margin-bottom: 2px;
        }

        .bottom-nav-item-plus {
            flex: 0 0 auto;
        }

        /* Extra small devices */
        @media (max-width: 360px) {
            .logo-text {
                font-size: 1.1rem;
            }

            .nav-link {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }

            .bottom-nav-item {
                padding: 4px 6px;
                font-size: 0.65rem;
            }

            .bottom-nav-icon {
                font-size: 1rem;
            }
        }

        /* Small devices */
        @media (min-width: 361px) and (max-width: 480px) {
            .nav-link {
                font-size: 0.85rem;
            }
        }

        /* Critical range: 768px to 845px */
        @media (min-width: 768px) and (max-width: 845px) {
            .nav-container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .nav-link {
                font-size: 0.8rem;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .nav-link i {
                margin-right: 0.25rem;
            }

            .user-greeting {
                max-width: 60px;
                font-size: 0.8rem;
            }

            .user-actions {
                gap: 0.5rem;
            }

            .user-avatar-container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .user-avatar {
                width: 1.5rem;
                height: 1.5rem;
                font-size: 0.7rem;
            }

            .logout-btn {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
                font-size: 0.8rem;
            }

            .logout-btn i {
                margin-right: 0.25rem;
            }

            /* Hide text in nav links, show only icons */
            .nav-link-text {
                display: none;
            }

            .nav-link i {
                margin-right: 0;
            }
        }

        /* Medium devices adjustment */
        @media (min-width: 846px) and (max-width: 1023px) {
            .nav-link {
                font-size: 0.85rem;
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .user-greeting {
                max-width: 80px;
            }
        }

        /* Prevent text overflow in user greeting */
        .user-greeting {
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 640px) {
            .user-greeting {
                max-width: 80px;
            }
        }

        /* Compact menu for medium screens */
        .compact-menu {
            gap: 0.25rem;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Top Navigation -->
    <nav class="nav-blur shadow-lg sticky top-0 border-b border-gray-200 z-30">
        <div class="w-full mx-auto nav-container">
            <div class="flex justify-between items-center h-14 md:h-16 lg:h-20 px-3 sm:px-4 lg:px-8">
                <!-- Logo -->
                <a href="/online-plaza/index.php" class="flex items-center space-x-2 sm:space-x-3 group flex-shrink-0">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center shadow-lg transform group-hover:rotate-12 transition-transform duration-300">
                        <i class="fas fa-store text-white text-xs sm:text-sm lg:text-lg"></i>
                    </div>
                    <span class="font-bold text-lg sm:text-xl lg:text-2xl logo-gradient logo-text">Martly</span>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center justify-center flex-1 mx-2 lg:mx-8 compact-menu">
                    <div class="flex items-center space-x-1 lg:space-x-2">
                        <a href="/online-plaza/index.php" class="nav-link px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) === 'index.php' && !isset($_GET['page'])) ? 'active' : ''; ?>">
                            <i class="fas fa-home mr-2"></i>
                            <span class="nav-link-text">Home</span>
                        </a>
                        <a href="/online-plaza/posts/index.php" class="nav-link px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'posts/') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-newspaper mr-2"></i>
                            <span class="nav-link-text">Posts</span>
                        </a>
                        <a href="/online-plaza/products/index.php" class="nav-link px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'products/') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-bag mr-2"></i>
                            <span class="nav-link-text">Products</span>
                        </a>
                        <?php if (isLoggedIn()): ?>
                            <a href="/online-plaza/activities/index.php" class="nav-link relative px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'activities/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-heart mr-2"></i>
                                <span class="nav-link-text">Activities</span>
                                <?php if ($unreadActivityCount > 0): ?>
                                    <span class="notification-badge">
                                        <?php echo $unreadActivityCount > 9 ? '9+' : $unreadActivityCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <?php if ($currentUser && $currentUser['user_type'] === 'vendor'): ?>
                                <a href="/online-plaza/company/orders.php" class="nav-link relative px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'company/orders') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-bell mr-2"></i>
                                    <span class="nav-link-text">Notifications</span>
                                    <?php if ($unreadNotifications > 0): ?>
                                        <span class="notification-badge">
                                            <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <a href="/online-plaza/company/dashboard.php" class="nav-link px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'company/dashboard') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-building mr-2"></i>
                                    <span class="nav-link-text">Company</span>
                                </a>
                            <?php else: ?>
                                <a href="/online-plaza/profile/index.php" class="nav-link px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium text-gray-700 hover:text-green-600 hover:bg-green-50/50 transition-all duration-300 <?php echo strpos($_SERVER['REQUEST_URI'], 'profile/') !== false ? 'active' : ''; ?>">
                                    <i class="fas fa-user mr-2"></i>
                                    <span class="nav-link-text">Profile</span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Desktop User Actions -->
                <div class="hidden md:flex items-center space-x-2 lg:space-x-4 flex-shrink-0 user-actions">
                    <?php if (isLoggedIn()): ?>
                        <div class="flex items-center space-x-2 lg:space-x-3 px-2 lg:px-4 py-1 lg:py-2 bg-gradient-to-r from-green-50 to-blue-50 rounded-full border border-green-200 user-avatar-container">
                            <div class="w-6 h-6 lg:w-8 lg:h-8 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-xs lg:text-sm user-avatar">
                                <?php echo strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1)); ?>
                            </div>
                            <span class="text-gray-700 font-semibold text-sm lg:text-base user-greeting">Hello, <?php echo htmlspecialchars($currentUser['first_name'] ?? $currentUser['username']); ?></span>
                        </div>
                        <a href="/online-plaza/auth/logout.php" class="px-3 lg:px-6 py-1 lg:py-2.5 font-semibold text-gray-700 hover:text-red-600 border-2 border-gray-300 hover:border-red-400 rounded-xl transition-all duration-300 text-sm lg:text-base logout-btn">
                            <i class="fas fa-sign-out-alt mr-1 lg:mr-2"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="/online-plaza/auth/login.php" class="px-3 lg:px-6 py-1 lg:py-2.5 font-semibold text-gray-700 hover:text-green-600 border-2 border-gray-300 hover:border-green-400 rounded-xl transition-all duration-300 text-sm lg:text-base">
                            Login
                        </a>
                        <a href="/online-plaza/auth/register.php" class="btn-gradient px-3 lg:px-6 py-1 lg:py-2.5 font-semibold text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 text-sm lg:text-base">
                            <span>Sign Up</span>
                        </a>
                    <?php endif; ?>
                    <!-- Install Button - Always visible for testing -->
                    <div class="hidden md:flex items-center" id="install-button-container">
                        <button id="install-martly-btn" class="ml-4 px-4 py-2 bg-gradient-to-r from-green-500 to-blue-500 text-white rounded-lg text-sm font-semibold hover:shadow-lg transition-all duration-300 flex items-center space-x-2">
                            <i class="fas fa-download"></i>
                            <span>Install App</span>
                        </button>
                    </div>

                    <!-- Mobile install badge -->
                    <div class="md:hidden flex items-center" id="mobile-install-container">
                        <button id="pwa-install-badge" class="w-8 h-8 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center text-white text-sm">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>

                    <script>
                        // Force show buttons for testing
                        document.addEventListener('DOMContentLoaded', function() {
                            console.log('Install buttons should be visible now');
                        });
                    </script>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center space-x-2">
                    <?php if (isLoggedIn()): ?>
                        <!-- User Dropdown -->
                        <div class="relative">
                            <button id="userDropdownBtn" type="button"
                                class="w-7 h-7 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-xs focus:outline-none focus:ring-2 focus:ring-green-400"
                                aria-haspopup="true" aria-expanded="false">
                                <?php echo strtoupper(substr($currentUser['first_name'] ?? $currentUser['username'], 0, 1)); ?>
                            </button>
                            <div id="userDropdownMenu"
                                class="hidden absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-lg border border-gray-100 z-50 py-2">
                                <a href="/online-plaza/index.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-green-50 text-sm">
                                    <i class="fas fa-home mr-3 text-green-600 w-4"></i>
                                    <span class="flex-1">Home</span>
                                </a>
                                <?php if ($currentUser && $currentUser['user_type'] === 'vendor'): ?>
                                    <a href="/online-plaza/activities/index.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-green-50 text-sm">
                                        <i class="fas fa-heart mr-3 text-green-600 w-4"></i>
                                        <span class="flex-1">Activities</span>
                                        <?php if ($unreadActivityCount > 0): ?>
                                            <span class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 min-w-[20px] text-center">
                                                <?php echo $unreadActivityCount > 9 ? '9+' : $unreadActivityCount; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>

                                <?php endif; ?>

                                <div class="border-t border-gray-100 my-2"></div>
                                <a href="/online-plaza/auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 text-sm">
                                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/online-plaza/auth/login.php" class="w-7 h-7 bg-gray-200 rounded-full flex items-center justify-center text-gray-600">
                            <i class="fas fa-user text-xs"></i>
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
                    <span class="w-12 h-12 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg border-4 border-white text-white text-xl font-bold transition-transform duration-200 hover:scale-110">
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

            // Auto-hide text in nav links for medium screens
            function checkScreenSize() {
                const screenWidth = window.innerWidth;
                const navLinks = document.querySelectorAll('.nav-link-text');
                const navIcons = document.querySelectorAll('.nav-link i');

                if (screenWidth >= 768 && screenWidth <= 845) {
                    // Hide text, adjust icon margins
                    navLinks.forEach(link => link.style.display = 'none');
                    navIcons.forEach(icon => icon.style.marginRight = '0');
                } else {
                    // Show text, restore icon margins
                    navLinks.forEach(link => link.style.display = 'inline');
                    navIcons.forEach(icon => icon.style.marginRight = '0.5rem');
                }
            }

            // Check on load and resize
            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
        });
    </script>