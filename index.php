<?php
require_once 'includes/config.php';


// Fetch recent posts (show 3 random from the latest 6)
$stmt = $pdo->query("
    SELECT p.*, c.name as company_name, c.logo as company_logo, u.username 
    FROM posts p 
    JOIN companies c ON p.company_id = c.id 
    JOIN users u ON c.user_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 6
");
$recentPostsAll = $stmt->fetchAll();
shuffle($recentPostsAll);
$recentPosts = array_slice($recentPostsAll, 0, 3);

// Fetch featured products (show 3 random from the latest 8)
$stmt = $pdo->query("
    SELECT pr.*, c.name as company_name 
    FROM products pr 
    JOIN companies c ON pr.company_id = c.id 
    WHERE pr.stock_quantity > 0 
    ORDER BY pr.created_at DESC 
    LIMIT 8
");
$featuredProductsAll = $stmt->fetchAll();
shuffle($featuredProductsAll);
$featuredProducts = array_slice($featuredProductsAll, 0, 3);
?>

<link rel="icon" href="./assets/martly.svg">

<?php require_once 'includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-green-400 to-blue-500 rounded-3xl p-10 text-white mb-14 shadow-xl flex flex-col md:flex-row items-center justify-between">
        <div class="max-w-2xl">
            <h1 class="text-5xl font-extrabold mb-4 drop-shadow-lg">Welcome to <span class="text-yellow-200">Martly Plaza</span></h1>
            <p class="text-2xl mb-8 font-light">Your digital marketplace connecting buyers and sellers in one convenient platform.</p>
            <div class="flex flex-wrap gap-4">
                <a href="/online-plaza/products/index.php" class="bg-white text-green-600 px-8 py-3 rounded-xl font-bold shadow hover:bg-gray-100 transition duration-300">Shop Now</a>
                <?php if (!isLoggedIn()): ?>
                    <a href="/online-plaza/auth/register.php" class="bg-transparent border-2 border-white text-white px-8 py-3 rounded-xl font-bold hover:bg-white hover:text-green-600 transition duration-300">Join Now</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hidden md:block flex items-center justify-center w-80 h-80">
            <i class="fas fa-store text-[8rem] text-white/80 drop-shadow-lg bg-gradient-to-br from-green-400 to-blue-500 rounded-3xl p-8"></i>
        </div>

    </section>

    <!-- Recent Posts Section -->
    <section class="mb-14">
        <h2 class="text-3xl font-extrabold mb-8 text-gray-800 text-center">Recent Updates from Vendors</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($recentPosts as $post): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition duration-300 flex flex-col">
                    <?php if (!empty($post['media_url'])): ?>
                        <div class="h-56 bg-gray-200 flex items-center justify-center">
                            <?php if ($post['media_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="h-full w-full object-cover">
                            <?php elseif ($post['media_type'] === 'video'): ?>
                                <video class="h-full w-full object-cover" controls>
                                    <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="h-56 bg-gradient-to-br from-green-100 to-blue-100 flex items-center justify-center">
                            <i class="fas fa-image text-5xl text-green-300"></i>
                        </div>
                    <?php endif; ?>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="font-bold text-xl mb-2 text-gray-800"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="text-gray-600 text-base mb-4 flex-1"><?php echo substr(htmlspecialchars($post['content']), 0, 100); ?>...</p>
                        <div class="flex items-center justify-between mt-2">
                            <a href="/online-plaza/company/index.php?id=<?php echo $post['company_id']; ?>" class="flex items-center space-x-2 text-green-600 hover:text-green-800 text-sm font-medium">
                                <?php if (!empty($post['company_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['company_logo']); ?>" alt="Logo" class="w-7 h-7 rounded-full border border-green-200">
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($post['company_name']); ?></span>
                            </a>
                            <!-- <a href="/online-plaza/posts/view.php?id=<?php echo $post['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">Read More</a> -->
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-8">
            <a href="/online-plaza/posts/index.php" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-8 py-3 rounded-xl font-bold shadow hover:from-green-600 hover:to-blue-600 transition duration-300">View All Posts</a>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="mb-14">
        <h2 class="text-3xl font-extrabold mb-8 text-gray-800 text-center">Featured Products</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach ($featuredProducts as $product): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition duration-300 flex flex-col">
                    <div class="h-56 bg-gray-200 flex items-center justify-center">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="h-full w-full object-cover">
                        <?php else: ?>
                            <div class="text-gray-400">
                                <i class="fas fa-box-open text-5xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6 flex-1 flex flex-col">
                        <h3 class="font-bold text-xl mb-2 text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="text-gray-600 text-base mb-3 flex-1"><?php echo substr(htmlspecialchars($product['description']), 0, 80); ?>...</p>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-green-600 font-bold text-lg">₦<?php echo number_format($product['price'], 2); ?></span>
                            <span class="text-gray-500 text-sm"><?php echo htmlspecialchars($product['company_name']); ?></span>
                        </div>
                        <a href="/online-plaza/products/view.php?id=<?php echo $product['id']; ?>" class="block mt-4 bg-gradient-to-r from-green-500 to-blue-500 text-white text-center py-2 rounded-xl font-semibold hover:from-green-600 hover:to-blue-600 transition duration-300">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-8">
            <a href="/online-plaza/products/index.php" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-8 py-3 rounded-xl font-bold shadow hover:from-green-600 hover:to-blue-600 transition duration-300">View All Products</a>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="bg-gradient-to-r from-green-50 to-blue-50 rounded-3xl p-10 mb-14 shadow">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 text-center">
            <div>
                <h3 class="text-4xl font-extrabold text-green-600 mb-2">50+</h3>
                <p class="text-gray-700 text-lg font-medium">Vendors</p>
            </div>
            <div>
                <h3 class="text-4xl font-extrabold text-green-600 mb-2">500+</h3>
                <p class="text-gray-700 text-lg font-medium">Products</p>
            </div>
            <div>
                <h3 class="text-4xl font-extrabold text-green-600 mb-2">10,000+</h3>
                <p class="text-gray-700 text-lg font-medium">Happy Customers</p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <?php if (!isLoggedIn()): ?>
        <section class="bg-gradient-to-r from-blue-400 to-purple-500 rounded-3xl p-10 text-white text-center shadow-xl">
            <h2 class="text-4xl font-extrabold mb-4">Ready to Join Our Plaza?</h2>
            <p class="text-2xl mb-8 max-w-2xl mx-auto font-light">Sign up today to start shopping or become a vendor and open your own online store!</p>
            <a href="/online-plaza/auth/register.php" class="bg-white text-blue-600 px-10 py-4 rounded-xl font-bold text-xl hover:bg-gray-100 transition duration-300 shadow">Get Started Now</a>
        </section>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
<!-- Add this script in your header or before closing body tag -->

<script>
    // Test if buttons are clickable
    document.addEventListener('DOMContentLoaded', function() {
        const testBtn = document.getElementById('install-martly-btn');
        if (testBtn) {
            console.log('✅ Install button found and should be clickable');
            testBtn.addEventListener('click', function() {
                console.log('✅ Install button clicked successfully!');
                alert('Install button is working! The PWA prompt should appear if requirements are met.');
            });
        }
    });

    // Register Service Worker with correct path
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/online-plaza/sw.js', {
                    scope: '/online-plaza/'
                })
                .then(function(registration) {
                    console.log('Service Worker registered with scope:', registration.scope);
                })
                .catch(function(error) {
                    console.log('Service Worker registration failed:', error);
                });
        });
    }
</script>