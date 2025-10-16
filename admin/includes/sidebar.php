<?php
// Check if user is admin
$currentUser = getCurrentUser();
if (!isLoggedIn() || !$currentUser || $currentUser['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="flex items-center justify-center h-16 border-b border-gray-200">
        <a href="index.php" class="flex items-center space-x-2">
            <img src="../assets/martly.svg" alt="Martly" class="w-8 h-8">
            <span class="text-xl font-bold text-gray-900">Martly Admin</span>
        </a>
    </div>
    
    <nav class="mt-6">
        <div class="px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-700 bg-blue-50 border-l-4 border-blue-500 rounded-lg">
                <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            
            <a href="users.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-300">
                <i class="fas fa-users w-5 mr-3"></i>
                <span class="font-medium">Users</span>
            </a>
            
            <a href="vendors.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-300">
                <i class="fas fa-store w-5 mr-3"></i>
                <span class="font-medium">Vendors</span>
            </a>
            
            <a href="products.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-300">
                <i class="fas fa-box w-5 mr-3"></i>
                <span class="font-medium">Products</span>
            </a>
            
            <a href="orders.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-300">
                <i class="fas fa-shopping-cart w-5 mr-3"></i>
                <span class="font-medium">Orders</span>
            </a>
            
            <a href="activities.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-300">
                <i class="fas fa-history w-5 mr-3"></i>
                <span class="font-medium">Activities</span>
            </a>
        </div>
    </nav>
    
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200">
        <a href="../auth/logout.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-300">
            <i class="fas fa-sign-out-alt w-5 mr-3"></i>
            <span class="font-medium">Logout</span>
        </a>
    </div>
</div>