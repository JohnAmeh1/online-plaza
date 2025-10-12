<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /online-plaza/index.php');
    exit;
}

$companyId = (int)$_GET['id'];

// Get company details
$stmt = $pdo->prepare("
    SELECT c.*, u.username 
    FROM companies c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$companyId]);
$company = $stmt->fetch();

if (!$company) {
    header('Location: /online-plaza/index.php');
    exit;
}

// Get company posts
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count
    FROM posts p 
    WHERE p.company_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$companyId]);
$posts = $stmt->fetchAll();

// Get company products
$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM products p 
    WHERE p.company_id = ? AND p.stock_quantity > 0
    ORDER BY p.created_at DESC 
    LIMIT 6
");
$stmt->execute([$companyId]);
$products = $stmt->fetchAll();

// Get company stats - FIXED: Proper execution of count queries
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
$stmt->execute([$companyId]);
$productsCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE company_id = ?");
$stmt->execute([$companyId]);
$postsCount = $stmt->fetchColumn();

$pageTitle = $company['name'] . " - OnlinePlaza";
require_once '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <!-- Company Header -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <?php if ($company['banner']): ?>
            <div class="h-48 bg-gray-200">
                <img src="<?php echo htmlspecialchars($company['banner']); ?>" alt="<?php echo htmlspecialchars($company['name']); ?> banner" class="w-full h-full object-cover">
            </div>
        <?php else: ?>
            <div class="h-48 bg-gradient-to-r from-green-400 to-blue-500"></div>
        <?php endif; ?>
        
        <div class="p-6">
            <div class="flex items-start justify-between">
                <div class="flex items-center">
                    <?php if ($company['logo']): ?>
                        <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="<?php echo htmlspecialchars($company['name']); ?> logo" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg -mt-12 bg-white">
                    <?php else: ?>
                        <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center text-white text-2xl font-bold border-4 border-white shadow-lg -mt-12">
                            <?php echo strtoupper(substr($company['name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="ml-6">
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($company['name']); ?></h1>
                        <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($company['description']); ?></p>
                        <div class="flex items-center mt-3 space-x-4 text-sm text-gray-500">
                            <span><i class="fas fa-box mr-1"></i> <?php echo $productsCount; ?> Products</span>
                            <span><i class="fas fa-newspaper mr-1"></i> <?php echo $postsCount; ?> Posts</span>
                            <span><i class="fas fa-calendar mr-1"></i> Joined <?php echo date('F Y', strtotime($company['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php 
                $currentUser = getCurrentUser();
                if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): 
                ?>
                    <a href="/online-plaza/company/dashboard.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition duration-300">
                        Manage Store
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($company['contact_email'] || $company['phone'] || $company['address']): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-lg font-semibold mb-3">Contact Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <?php if ($company['contact_email']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-gray-400 mr-2"></i>
                            <span><?php echo htmlspecialchars($company['contact_email']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($company['phone']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-gray-400 mr-2"></i>
                            <span><?php echo htmlspecialchars($company['phone']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($company['address']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>
                            <span><?php echo htmlspecialchars($company['address']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Posts Section -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold">Latest Updates</h2>
                    <?php if ($postsCount > 0): ?>
                        <span class="text-sm text-gray-500"><?php echo $postsCount; ?> post<?php echo $postsCount !== 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($posts): ?>
                    <div class="space-y-6">
                        <?php foreach ($posts as $post): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition duration-300">
                                <?php if ($post['media_url']): ?>
                                    <div class="mb-4">
                                        <?php if ($post['media_type'] === 'image'): ?>
                                            <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-64 object-cover rounded-lg">
                                        <?php else: ?>
                                            <video class="w-full h-64 object-cover rounded-lg" controls>
                                                <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <h3 class="text-xl font-semibold mb-3"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                
                                <div class="flex items-center justify-between text-sm text-gray-500">
                                    <div class="flex space-x-4">
                                        <span class="flex items-center">
                                            <i class="fas fa-heart mr-1 <?php echo $post['like_count'] > 0 ? 'text-red-500' : ''; ?>"></i>
                                            <?php echo $post['like_count']; ?> likes
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-comment mr-1 <?php echo $post['comment_count'] > 0 ? 'text-blue-500' : ''; ?>"></i>
                                            <?php echo $post['comment_count']; ?> comments
                                        </span>
                                    </div>
                                    <span><?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
                                </div>
                                
                                <!-- <div class="mt-4 pt-4 border-t border-gray-200">
                                    <a href="/online-plaza/posts/view.php?id=<?php echo $post['id']; ?>" class="inline-flex items-center text-green-600 hover:text-green-800 font-medium transition duration-300">
                                        View Post 
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div> -->
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-newspaper text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Posts Yet</h3>
                        <p class="text-gray-500 mb-6">This company hasn't posted any updates.</p>
                        <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): ?>
                            <a href="/online-plaza/posts/create.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition duration-300">
                                Create Your First Post
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Products Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Featured Products</h2>
                    <?php if ($productsCount > 0): ?>
                        <span class="text-sm text-gray-500"><?php echo $productsCount; ?> product<?php echo $productsCount !== 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($products): ?>
                    <div class="space-y-4">
                        <?php foreach ($products as $product): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-300 group">
                                <div class="flex items-center space-x-3">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-16 h-16 object-cover rounded group-hover:scale-105 transition duration-300">
                                    <?php else: ?>
                                        <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center group-hover:bg-gray-300 transition duration-300">
                                            <i class="fas fa-image text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-sm mb-1 truncate" title="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </h3>
                                        <p class="text-green-600 font-bold text-sm">₦<?php echo number_format($product['price'], 2); ?></p>
                                        <?php if ($product['avg_rating']): ?>
                                            <div class="flex items-center text-xs mt-1">
                                                <div class="flex text-yellow-400">
                                                    <?php
                                                    $rating = round($product['avg_rating']);
                                                    for ($i = 1; $i <= 5; $i++):
                                                        if ($i <= $rating):
                                                    ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif; endfor; ?>
                                                </div>
                                                <span class="text-gray-500 ml-1">(<?php echo $product['review_count'] ?: 0; ?>)</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-xs text-gray-500 mt-1">No reviews yet</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="/online-plaza/products/view.php?id=<?php echo $product['id']; ?>" class="block mt-3 text-center bg-gray-100 text-gray-700 py-2 rounded text-sm hover:bg-green-500 hover:text-white transition duration-300">
                                    View Details
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($productsCount > 6): ?>
                        <div class="mt-6 text-center">
                            <a href="/online-plaza/products/index.php?company=<?php echo $companyId; ?>" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition duration-300 text-sm inline-flex items-center">
                                View All Products
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-box text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-sm mb-4">No products available yet.</p>
                        <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): ?>
                            <a href="/online-plaza/products/create.php" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition duration-300 text-sm">
                                Add Your First Product
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="/online-plaza/posts/create.php" class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition duration-300">
                            <i class="fas fa-plus-circle mr-3"></i>
                            <span>Create New Post</span>
                        </a>
                        <a href="/online-plaza/products/create.php" class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition duration-300">
                            <i class="fas fa-plus-circle mr-3"></i>
                            <span>Add New Product</span>
                        </a>
                        <a href="/online-plaza/company/dashboard.php" class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition duration-300">
                            <i class="fas fa-chart-line mr-3"></i>
                            <span>View Dashboard</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>