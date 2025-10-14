<!-- Footer -->
<footer class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white pt-16 pb-12 mt-20 border-t border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Main Footer Content -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
            <!-- Brand Section -->
            <div class="lg:col-span-1">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-store text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-bold bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">Martly</span>
                </div>
                <p class="text-gray-300 leading-relaxed mb-6 text-sm lg:text-base">
                    Your premier digital marketplace connecting shoppers with trusted vendors. Discover unique products and build your business community.
                </p>
                <div class="flex space-x-4">
                    <a href="#" class="w-10 h-10 bg-gray-700 hover:bg-blue-600 rounded-xl flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <i class="fab fa-facebook-f text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-700 hover:bg-pink-600 rounded-xl flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <i class="fab fa-instagram text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-700 hover:bg-blue-400 rounded-xl flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <i class="fab fa-twitter text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-700 hover:bg-red-600 rounded-xl flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <i class="fab fa-youtube text-white"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-lg font-bold mb-6 relative inline-block">
                    Quick Links
                    <span class="absolute bottom-0 left-0 w-1/2 h-0.5 bg-gradient-to-r from-blue-400 to-purple-400"></span>
                </h3>
                <ul class="space-y-3">
                    <li>
                        <a href="/online-plaza/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                            <i class="fas fa-home mr-3 text-blue-400 group-hover:scale-110 transition-transform"></i>
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="/online-plaza/posts/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                            <i class="fas fa-newspaper mr-3 text-green-400 group-hover:scale-110 transition-transform"></i>
                            Posts Feed
                        </a>
                    </li>
                    <li>
                        <a href="/online-plaza/products/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                            <i class="fas fa-shopping-cart mr-3 text-yellow-400 group-hover:scale-110 transition-transform"></i>
                            Products
                        </a>
                    </li>
                    <li>
                        <a href="/online-plaza/companies/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                            <i class="fas fa-store mr-3 text-purple-400 group-hover:scale-110 transition-transform"></i>
                            Companies
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Account Section -->
            <div>
                <h3 class="text-lg font-bold mb-6 relative inline-block">
                    Account
                    <span class="absolute bottom-0 left-0 w-1/2 h-0.5 bg-gradient-to-r from-green-400 to-blue-400"></span>
                </h3>
                <ul class="space-y-3">
                    <?php if (isLoggedIn()): ?>
                        <li>
                            <a href="/online-plaza/profile/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                                <i class="fas fa-user-circle mr-3 text-cyan-400 group-hover:scale-110 transition-transform"></i>
                                My Profile
                            </a>
                        </li>
                        <li>
                            <a href="/online-plaza/activities/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                                <i class="fas fa-bell mr-3 text-orange-400 group-hover:scale-110 transition-transform"></i>
                                Activities
                            </a>
                        </li>
                        <li>
                            <a href="/online-plaza/settings/index.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                                <i class="fas fa-cog mr-3 text-gray-400 group-hover:scale-110 transition-transform"></i>
                                Settings
                            </a>
                        </li>
                        <li>
                            <a href="/online-plaza/auth/logout.php" class="text-gray-300 hover:text-red-400 transition-all duration-300 flex items-center group">
                                <i class="fas fa-sign-out-alt mr-3 text-red-400 group-hover:scale-110 transition-transform"></i>
                                Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="/online-plaza/auth/login.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                                <i class="fas fa-sign-in-alt mr-3 text-green-400 group-hover:scale-110 transition-transform"></i>
                                Login
                            </a>
                        </li>
                        <li>
                            <a href="/online-plaza/auth/register.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                                <i class="fas fa-user-plus mr-3 text-blue-400 group-hover:scale-110 transition-transform"></i>
                                Register
                            </a>
                        </li>
                        <li>
                            <a href="/online-plaza/auth/forgot-password.php" class="text-gray-300 hover:text-white transition-all duration-300 flex items-center group">
                                <i class="fas fa-key mr-3 text-yellow-400 group-hover:scale-110 transition-transform"></i>
                                Forgot Password
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Contact & Support -->
            <div>
                <h3 class="text-lg font-bold mb-6 relative inline-block">
                    Support
                    <span class="absolute bottom-0 left-0 w-1/2 h-0.5 bg-gradient-to-r from-purple-400 to-pink-400"></span>
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start space-x-3 group">
                        <div class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-blue-500/30 transition-colors">
                            <i class="fas fa-envelope text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <span class="text-gray-400 text-sm block">Email</span>
                            <a href="mailto:support@onlineplaza.com" class="text-gray-300 hover:text-white transition-colors">support@onlineplaza.com</a>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3 group">
                        <div class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-green-500/30 transition-colors">
                            <i class="fas fa-phone text-green-400 text-sm"></i>
                        </div>
                        <div>
                            <span class="text-gray-400 text-sm block">Phone</span>
                            <a href="tel:+15551234567" class="text-gray-300 hover:text-white transition-colors">+1 (555) 123-4567</a>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3 group">
                        <div class="w-8 h-8 bg-purple-500/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-purple-500/30 transition-colors">
                            <i class="fas fa-map-marker-alt text-purple-400 text-sm"></i>
                        </div>
                        <div>
                            <span class="text-gray-400 text-sm block">Address</span>
                            <span class="text-gray-300">123 Plaza Street, Digital City</span>
                        </div>
                    </li>
                    <li class="flex items-start space-x-3 group">
                        <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-orange-500/30 transition-colors">
                            <i class="fas fa-clock text-orange-400 text-sm"></i>
                        </div>
                        <div>
                            <span class="text-gray-400 text-sm block">Support Hours</span>
                            <span class="text-gray-300">24/7 Available</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="border-t border-gray-700/50 mt-12 pt-8">
            <div class="flex flex-col lg:flex-row justify-between items-center space-y-4 lg:space-y-0">
                <!-- Copyright -->
                <div class="text-gray-400 text-sm text-center lg:text-left">
                    <p>&copy; <?php echo date('Y'); ?> Martly. All rights reserved. Built with <i class="fas fa-heart text-red-400 mx-1"></i> for the digital marketplace community.</p>
                </div>

                <!-- Additional Links -->
                <div class="flex flex-wrap justify-center lg:justify-end space-x-6 text-sm">
                    <a href="/online-plaza/privacy.php" class="text-gray-400 hover:text-white transition-colors duration-300">Privacy Policy</a>
                    <a href="/online-plaza/terms.php" class="text-gray-400 hover:text-white transition-colors duration-300">Terms of Service</a>
                    <a href="/online-plaza/cookies.php" class="text-gray-400 hover:text-white transition-colors duration-300">Cookie Policy</a>
                    <a href="/online-plaza/sitemap.php" class="text-gray-400 hover:text-white transition-colors duration-300">Sitemap</a>
                </div>
            </div>

            <!-- Mobile App Badges -->
            <div class="flex justify-center lg:justify-start space-x-4 mt-6">
                <a href="#" class="inline-flex items-center space-x-2 bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-all duration-300 transform hover:scale-105">
                    <i class="fab fa-apple text-xl"></i>
                    <div class="text-left">
                        <div class="text-xs text-gray-400">Download on the</div>
                        <div class="text-white font-semibold text-sm">App Store</div>
                    </div>
                </a>
                <a href="#" class="inline-flex items-center space-x-2 bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-all duration-300 transform hover:scale-105">
                    <i class="fab fa-google-play text-xl"></i>
                    <div class="text-left">
                        <div class="text-xs text-gray-400">Get it on</div>
                        <div class="text-white font-semibold text-sm">Google Play</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="backToTop" class="fixed bottom-8 right-8 w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 text-white rounded-full shadow-2xl flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-2xl opacity-0 invisible z-50">
    <i class="fas fa-chevron-up text-lg"></i>
</button>

<script>
    // Back to top functionality
    document.addEventListener('DOMContentLoaded', function() {
        const backToTop = document.getElementById('backToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTop.classList.remove('opacity-0', 'invisible');
                backToTop.classList.add('opacity-100', 'visible');
            } else {
                backToTop.classList.remove('opacity-100', 'visible');
                backToTop.classList.add('opacity-0', 'invisible');
            }
        });

        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
</script>

<!-- Scripts -->
<script src="/online-plaza/assets/js/main.js"></script>
<script src="/online-plaza/assets/js/ajax.js"></script>
<script src="/online-plaza/assets/js/refresh-controls.js"></script>
</body>
</html>