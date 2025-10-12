    <!-- Footer -->
    <footer class="bg-gray-800 text-white pt-12 pb-8 mt-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Online Plaza</h3>
                    <p class="text-gray-400">Your digital marketplace for all your shopping needs. Connect with vendors and discover amazing products.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="/online-plaza/index.php" class="text-gray-400 hover:text-white">Home</a></li>
                        <li><a href="/online-plaza/posts/index.php" class="text-gray-400 hover:text-white">Posts</a></li>
                        <li><a href="/online-plaza/products/index.php" class="text-gray-400 hover:text-white">Products</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Account</h3>
                    <ul class="space-y-2">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/online-plaza/profile/index.php" class="text-gray-400 hover:text-white">Profile</a></li>
                            <li><a href="/online-plaza/activities/index.php" class="text-gray-400 hover:text-white">Activities</a></li>
                            <li><a href="/online-plaza/auth/logout.php" class="text-gray-400 hover:text-white">Logout</a></li>
                        <?php else: ?>
                            <li><a href="/online-plaza/auth/login.php" class="text-gray-400 hover:text-white">Login</a></li>
                            <li><a href="/online-plaza/auth/register.php" class="text-gray-400 hover:text-white">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-envelope mr-2"></i> support@onlineplaza.com</li>
                        <li><i class="fas fa-phone mr-2"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i> 123 Plaza Street, Digital City</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Online Plaza. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="/online-plaza/assets/js/main.js"></script>
    <script src="/online-plaza/assets/js/ajax.js"></script>
    <!-- <script src="/online-plaza/assets/js/auto-refresh.js"></script> -->
    <script src="/online-plaza/assets/js/refresh-controls.js"></script>
    </body>

    </html>