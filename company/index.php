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

// Get company stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
$stmt->execute([$companyId]);
$productsCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE company_id = ?");
$stmt->execute([$companyId]);
$postsCount = $stmt->fetchColumn();

// Initialize review variables
$reviews = [];
$avgRating = 0;
$reviewCount = 0;

// Check if company_reviews table exists and get reviews
try {
    $stmt = $pdo->prepare("
        SELECT cr.*, u.username, u.profile_picture 
        FROM company_reviews cr 
        JOIN users u ON cr.user_id = u.id 
        WHERE cr.company_id = ? 
        ORDER BY cr.created_at DESC
    ");
    $stmt->execute([$companyId]);
    $reviews = $stmt->fetchAll();

    // Calculate average rating
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
        FROM company_reviews 
        WHERE company_id = ?
    ");
    $stmt->execute([$companyId]);
    $ratingData = $stmt->fetch();
    $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $reviewCount = $ratingData['review_count'] ?: 0;
} catch (PDOException $e) {
    // Table doesn't exist yet, show message
    $reviews = [];
    $avgRating = 0;
    $reviewCount = 0;
    $tableError = "Review system is not available yet. Please contact administrator.";
}

$pageTitle = $company['name'] . " - OnlinePlaza";
require_once '../includes/header.php';
?>

<!-- Add session message display -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="max-w-7xl mx-auto px-4 mb-4 mt-4">
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo $_SESSION['success']; ?></span>
            </div>
            <button type="button" onclick="this.parentElement.style.display='none'" class="text-green-700 hover:text-green-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="max-w-7xl mx-auto px-4 mb-4 mt-4">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?php echo $_SESSION['error']; ?></span>
            </div>
            <button type="button" onclick="this.parentElement.style.display='none'" class="text-red-700 hover:text-red-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    <div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-8">
        <!-- Company Header -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6 sm:mb-8 border border-slate-200">
            <?php if ($company['banner']): ?>
                <div class="h-32 sm:h-56 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 relative overflow-hidden">
                    <img src="<?php echo htmlspecialchars($company['banner']); ?>"
                        alt="<?php echo htmlspecialchars($company['name']); ?> banner"
                        class="w-full h-full object-cover mix-blend-overlay opacity-80">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                </div>
            <?php else: ?>
                <div class="h-32 sm:h-56 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 relative overflow-hidden">
                    <div class="absolute inset-0 opacity-20">
                        <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl"></div>
                        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl"></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="p-4 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="flex items-start">
                        <?php if ($company['logo']): ?>
                            <img src="<?php echo htmlspecialchars($company['logo']); ?>"
                                alt="<?php echo htmlspecialchars($company['name']); ?> logo"
                                class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl object-cover border-4 border-white shadow-2xl -mt-12 sm:-mt-16 bg-white ring-2 ring-indigo-100">
                        <?php else: ?>
                            <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-white text-2xl sm:text-3xl font-bold border-4 border-white shadow-2xl -mt-12 sm:-mt-16 ring-2 ring-indigo-100">
                                <?php echo strtoupper(substr($company['name'], 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="ml-4 sm:ml-6">
                            <h1 class="text-2xl sm:text-4xl font-bold text-slate-900 mb-1"><?php echo htmlspecialchars($company['name']); ?></h1>
                            <p class="text-slate-600 mt-2 text-sm sm:text-base max-w-2xl leading-relaxed"><?php echo htmlspecialchars($company['description']); ?></p>
                            <div class="flex flex-wrap items-center mt-3 sm:mt-4 gap-3 sm:gap-5">
                                <!-- Rating Display -->
                                <?php if ($avgRating > 0): ?>
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <div class="flex items-center mr-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= floor($avgRating)): ?>
                                                    <i class="fas fa-star text-xs"></i>
                                                <?php elseif ($i == ceil($avgRating) && fmod($avgRating, 1) >= 0.5): ?>
                                                    <i class="fas fa-star-half-alt text-xs"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-xs"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <?php echo $avgRating; ?> (<?php echo $reviewCount; ?> reviews)
                                    </div>
                                <?php endif; ?>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    <i class="fas fa-box mr-2"></i><?php echo $productsCount; ?> Products
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium bg-purple-50 text-purple-700 border border-purple-100">
                                    <i class="fas fa-newspaper mr-2"></i><?php echo $postsCount; ?> Posts
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium bg-slate-50 text-slate-700 border border-slate-200">
                                    <i class="fas fa-calendar mr-2"></i>Joined <?php echo date('F Y', strtotime($company['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php
                    $currentUser = getCurrentUser();
                    if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']):
                    ?>
                        <a href="/online-plaza/company/dashboard.php"
                            class="inline-flex items-center justify-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 sm:px-8 py-2.5 sm:py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 text-sm sm:text-base font-medium w-full sm:w-auto">
                            <i class="fas fa-cog mr-2"></i>Manage Store
                        </a>
                    <?php elseif (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'customer'): ?>
                        <!-- Review Button for Customers -->
                        <button onclick="openReviewModal()"
                            class="inline-flex items-center justify-center bg-gradient-to-r from-amber-500 to-orange-600 text-white px-6 sm:px-8 py-2.5 sm:py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 text-sm sm:text-base font-medium w-full sm:w-auto">
                            <i class="fas fa-star mr-2"></i>Leave Review
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($company['contact_email'] || $company['phone'] || $company['address'] || $company['whatsapp_url'] || $company['instagram_url']): ?>
                    <div class="mt-6 sm:mt-8 pt-6 sm:pt-8 border-t border-slate-200">
                        <h3 class="text-lg sm:text-xl font-bold text-slate-900 mb-4 flex items-center">
                            <span class="w-1 h-6 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-3"></span>
                            Contact Information
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php if ($company['contact_email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($company['contact_email']); ?>"
                                    class="flex items-center p-4 bg-gradient-to-br from-red-50 to-pink-50 rounded-xl hover:shadow-md transition-all duration-300 border border-red-100 group">
                                    <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-pink-500 rounded-lg flex items-center justify-center text-white mr-3 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <span class="text-sm text-slate-700 truncate font-medium"><?php echo htmlspecialchars($company['contact_email']); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($company['phone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($company['phone']); ?>"
                                    class="flex items-center p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl hover:shadow-md transition-all duration-300 border border-blue-100 group">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center text-white mr-3 group-hover:scale-110 transition-transform">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <span class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($company['phone']); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($company['address']): ?>
                                <div class="flex items-center p-4 bg-gradient-to-br from-slate-50 to-gray-50 rounded-xl border border-slate-200 sm:col-span-2 lg:col-span-1">
                                    <div class="w-10 h-10 bg-gradient-to-br from-slate-500 to-gray-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <span class="text-sm text-slate-700 truncate font-medium"><?php echo htmlspecialchars($company['address']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Social Media Links -->
                        <?php if ($company['whatsapp_url'] || $company['instagram_url']): ?>
                            <div class="mt-5">
                                <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center">
                                    <i class="fas fa-share-alt mr-2 text-indigo-600"></i>Connect With Us
                                </h4>
                                <div class="flex flex-wrap gap-3">
                                    <?php if ($company['whatsapp_url']): ?>
                                        <a href="<?php echo htmlspecialchars($company['whatsapp_url']); ?>"
                                            target="_blank"
                                            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:shadow-lg hover:scale-105 transition-all duration-300 text-sm font-medium">
                                            <i class="fab fa-whatsapp text-lg mr-2"></i>WhatsApp
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($company['instagram_url']): ?>
                                        <a href="<?php echo htmlspecialchars($company['instagram_url']); ?>"
                                            target="_blank"
                                            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-pink-500 to-rose-600 text-white rounded-lg hover:shadow-lg hover:scale-105 transition-all duration-300 text-sm font-medium">
                                            <i class="fab fa-instagram text-lg mr-2"></i>Instagram
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
            <!-- Posts Section -->
            <div class="lg:col-span-2">
                <!-- Reviews Section -->
                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-8 border border-slate-200 mb-6 sm:mb-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 flex items-center">
                            <span class="w-1 h-8 bg-gradient-to-b from-amber-500 to-orange-600 rounded-full mr-3"></span>
                            Customer Reviews
                        </h2>
                        <?php if ($reviewCount > 0): ?>
                            <span class="px-4 py-1.5 bg-amber-100 text-amber-700 rounded-full text-sm font-semibold">
                                <?php echo $reviewCount; ?> review<?php echo $reviewCount !== 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($tableError)): ?>
                        <div class="text-center py-8 bg-amber-50 rounded-xl border border-amber-200">
                            <i class="fas fa-exclamation-triangle text-amber-500 text-3xl mb-3"></i>
                            <p class="text-amber-700 font-medium"><?php echo $tableError; ?></p>
                        </div>
                    <?php elseif ($reviews): ?>
                        <!-- Scrollable container -->
                        <div class="max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                            <div class="space-y-6">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="border border-slate-200 rounded-2xl p-5 sm:p-6 hover:shadow-lg hover:border-amber-200 transition-all duration-300 bg-gradient-to-br from-white to-amber-50/30">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center">
                                                <?php if ($review['profile_picture']): ?>
                                                    <img src="<?php echo htmlspecialchars($review['profile_picture']); ?>"
                                                        alt="<?php echo htmlspecialchars($review['username']); ?>"
                                                        class="w-10 h-10 rounded-full object-cover border-2 border-amber-200 mr-3">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                                        <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h4 class="font-bold text-slate-900"><?php echo htmlspecialchars($review['username']); ?></h4>
                                                    <div class="flex items-center mt-1">
                                                        <div class="flex text-amber-400 mr-2">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <?php if ($i <= $review['rating']): ?>
                                                                    <i class="fas fa-star text-sm"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-star text-sm"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <span class="text-xs text-slate-500"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($review['comment']): ?>
                                            <p class="text-slate-700 leading-relaxed text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 sm:py-16">
                            <div class="w-20 h-20 bg-gradient-to-br from-amber-100 to-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-star text-4xl text-amber-400"></i>
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-slate-700 mb-2">No Reviews Yet</h3>
                            <p class="text-slate-500 mb-6 text-sm sm:text-base">Be the first to review this company!</p>
                            <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'customer'): ?>
                                <button onclick="openReviewModal()"
                                    class="inline-flex items-center bg-gradient-to-r from-amber-500 to-orange-600 text-white px-6 py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 font-medium">
                                    <i class="fas fa-star mr-2"></i>Write First Review
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Posts Section -->
                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-8 border border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 flex items-center">
                            <span class="w-1 h-8 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-3"></span>
                            Latest Updates
                        </h2>
                        <?php if ($postsCount > 0): ?>
                            <span class="px-4 py-1.5 bg-indigo-100 text-indigo-700 rounded-full text-sm font-semibold">
                                <?php echo $postsCount; ?> post<?php echo $postsCount !== 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($posts): ?>
                        <div class="space-y-6">
                            <?php foreach ($posts as $post): ?>
                                <div class="border border-slate-200 rounded-2xl p-5 sm:p-6 hover:shadow-xl hover:border-indigo-200 transition-all duration-300 bg-gradient-to-br from-white to-slate-50">
                                    <?php if ($post['media_url']): ?>
                                        <div class="mb-4 rounded-xl overflow-hidden">
                                            <?php if ($post['media_type'] === 'image'): ?>
                                                <img src="<?php echo htmlspecialchars($post['media_url']); ?>"
                                                    alt="<?php echo htmlspecialchars($post['title']); ?>"
                                                    class="w-full h-56 sm:h-72 object-cover hover:scale-105 transition-transform duration-500">
                                            <?php else: ?>
                                                <video class="w-full h-56 sm:h-72 object-cover" controls>
                                                    <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                                    Your browser does not support the video tag.
                                                </video>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <h3 class="text-xl sm:text-2xl font-bold mb-3 text-slate-900"><?php echo htmlspecialchars($post['title']); ?></h3>
                                    <p class="text-slate-600 mb-4 leading-relaxed text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>

                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pt-4 border-t border-slate-200 gap-3">
                                        <div class="flex space-x-6">
                                            <span class="inline-flex items-center text-sm font-medium <?php echo $post['like_count'] > 0 ? 'text-rose-600' : 'text-slate-500'; ?>">
                                                <i class="fas fa-heart mr-2"></i><?php echo $post['like_count']; ?> likes
                                            </span>
                                            <span class="inline-flex items-center text-sm font-medium <?php echo $post['comment_count'] > 0 ? 'text-indigo-600' : 'text-slate-500'; ?>">
                                                <i class="fas fa-comment mr-2"></i><?php echo $post['comment_count']; ?> comments
                                            </span>
                                        </div>
                                        <span class="text-sm text-slate-500 font-medium">
                                            <i class="far fa-clock mr-1"></i><?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 sm:py-16">
                            <div class="w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-newspaper text-4xl text-indigo-400"></i>
                            </div>
                            <h3 class="text-lg sm:text-xl font-bold text-slate-700 mb-2">No Posts Yet</h3>
                            <p class="text-slate-500 mb-6 text-sm sm:text-base">This company hasn't posted any updates.</p>
                            <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): ?>
                                <a href="/online-plaza/posts/create.php"
                                    class="inline-flex items-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 font-medium">
                                    <i class="fas fa-plus-circle mr-2"></i>Create Your First Post
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Products Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-6 sticky top-4 border border-slate-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-5 gap-2">
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 flex items-center">
                            <span class="w-1 h-6 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-2"></span>
                            Featured
                        </h2>
                        <?php if ($productsCount > 0): ?>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">
                                <?php echo $productsCount; ?> items
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($products): ?>
                        <div class="space-y-4">
                            <?php foreach ($products as $product): ?>
                                <div class="border border-slate-200 rounded-xl p-4 hover:shadow-lg hover:border-indigo-200 transition-all duration-300 group bg-gradient-to-br from-white to-slate-50">
                                    <div class="flex items-start space-x-3">
                                        <?php if ($product['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-xl group-hover:scale-105 transition-transform duration-300 border-2 border-slate-100">
                                        <?php else: ?>
                                            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-xl flex items-center justify-center group-hover:scale-105 transition-transform duration-300 border-2 border-indigo-200">
                                                <i class="fas fa-image text-indigo-400 text-xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-bold text-sm mb-1.5 text-slate-900 line-clamp-2" title="<?php echo htmlspecialchars($product['name']); ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </h3>
                                            <p class="text-indigo-600 font-bold text-base mb-2">₦<?php echo number_format($product['price'], 2); ?></p>
                                            <?php if ($product['avg_rating']): ?>
                                                <div class="flex items-center text-xs">
                                                    <div class="flex text-amber-400 mr-1.5">
                                                        <?php
                                                        $rating = round($product['avg_rating']);
                                                        for ($i = 1; $i <= 5; $i++):
                                                            if ($i <= $rating):
                                                        ?>
                                                                <i class="fas fa-star text-xs"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star text-xs"></i>
                                                        <?php endif;
                                                        endfor; ?>
                                                    </div>
                                                    <span class="text-slate-600 font-medium">(<?php echo $product['review_count'] ?: 0; ?>)</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-xs text-slate-400 font-medium">No reviews yet</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a href="/online-plaza/products/view.php?id=<?php echo $product['id']; ?>"
                                        class="block mt-3 text-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-2 rounded-lg text-sm font-medium hover:shadow-md hover:scale-105 transition-all duration-300">
                                        View Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($productsCount > 6): ?>
                            <div class="mt-6 text-center">
                                <a href="/online-plaza/products/index.php?company=<?php echo $companyId; ?>"
                                    class="inline-flex items-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2.5 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 text-sm font-medium">
                                    View All Products
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-box text-3xl text-indigo-400"></i>
                            </div>
                            <p class="text-slate-500 text-sm mb-4">No products available yet.</p>
                            <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): ?>
                                <a href="/online-plaza/products/create.php"
                                    class="inline-flex items-center bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-5 py-2 rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 text-sm font-medium">
                                    <i class="fas fa-plus-circle mr-2"></i>Add Product
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <?php if (isLoggedIn() && $currentUser && $currentUser['user_type'] === 'vendor' && $currentUser['id'] == $company['user_id']): ?>
                    <div class="bg-white rounded-2xl shadow-xl p-5 sm:p-6 mt-6 border border-slate-200">
                        <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                            <span class="w-1 h-5 bg-gradient-to-b from-indigo-600 to-purple-600 rounded-full mr-2"></span>
                            Quick Actions
                        </h3>
                        <div class="space-y-3">
                            <a href="/online-plaza/posts/create.php"
                                class="flex items-center p-3 bg-gradient-to-br from-green-50 to-emerald-50 text-green-700 rounded-xl hover:shadow-md hover:scale-105 transition-all duration-300 border border-green-200 group">
                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center text-white mr-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <span class="font-medium">Create New Post</span>
                            </a>
                            <a href="/online-plaza/products/create.php"
                                class="flex items-center p-3 bg-gradient-to-br from-blue-50 to-indigo-50 text-blue-700 rounded-xl hover:shadow-md hover:scale-105 transition-all duration-300 border border-blue-200 group">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center text-white mr-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <span class="font-medium">Add New Product</span>
                            </a>
                            <a href="/online-plaza/company/dashboard.php"
                                class="flex items-center p-3 bg-gradient-to-br from-purple-50 to-pink-50 text-purple-700 rounded-xl hover:shadow-md hover:scale-105 transition-all duration-300 border border-purple-200 group">
                                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center text-white mr-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <span class="font-medium">View Dashboard</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal - FIXED FORM ACTION -->
<div id="reviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl p-6 sm:p-8 max-w-md w-full mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Leave a Review</h3>
            <button type="button" onclick="closeReviewModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- CHANGED: Using separate submit_review.php file -->
        <form method="POST" action="/online-plaza/company/submit_review.php" id="reviewForm">
            <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">

            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-3">Rating *</label>
                <div class="flex justify-center space-x-2" id="starRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="text-2xl text-amber-400 hover:scale-110 transition-transform"
                            onclick="setRating(<?php echo $i; ?>)" data-rating="<?php echo $i; ?>">
                            <i class="far fa-star"></i>
                        </button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0" required>
                <p id="ratingError" class="text-red-500 text-sm mt-2 hidden">Please select a rating between 1-5 stars</p>
            </div>

            <div class="mb-6">
                <label for="comment" class="block text-sm font-medium text-slate-700 mb-2">Comment *</label>
                <textarea name="comment" id="comment" rows="4" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"
                    placeholder="Share your experience with this company..."></textarea>
                <p id="commentError" class="text-red-500 text-sm mt-2 hidden">Please enter a comment</p>
            </div>

            <div class="flex space-x-3">
                <button type="button" onclick="closeReviewModal()"
                    class="flex-1 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-xl hover:bg-slate-50 transition-colors font-medium">
                    Cancel
                </button>
                <button type="submit" name="submit_review"
                    class="flex-1 px-4 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-300 font-medium">
                    Submit Review
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentRating = 0;

    function openReviewModal() {
        console.log("Opening review modal for company ID: <?php echo $companyId; ?>");
        document.getElementById('reviewModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // Reset form when opening
        currentRating = 0;
        document.getElementById('ratingInput').value = '0';
        const stars = document.querySelectorAll('#starRating button');
        stars.forEach(star => {
            star.innerHTML = '<i class="far fa-star"></i>';
        });
        document.getElementById('comment').value = '';
        document.getElementById('ratingError').classList.add('hidden');
        document.getElementById('commentError').classList.add('hidden');
    }

    function closeReviewModal() {
        console.log("Closing review modal");
        document.getElementById('reviewModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function setRating(rating) {
        console.log("Setting rating to: " + rating);
        currentRating = rating;
        document.getElementById('ratingInput').value = rating;

        const stars = document.querySelectorAll('#starRating button');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.innerHTML = '<i class="fas fa-star"></i>';
            } else {
                star.innerHTML = '<i class="far fa-star"></i>';
            }
        });
        document.getElementById('ratingError').classList.add('hidden');
    }

    // Form validation
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        const rating = document.getElementById('ratingInput').value;
        const comment = document.getElementById('comment').value.trim();
        let valid = true;

        console.log("Form validation - Rating:", rating, "Comment:", comment);

        // Validate rating
        if (rating === '0' || rating === 0) {
            document.getElementById('ratingError').classList.remove('hidden');
            valid = false;
        } else {
            document.getElementById('ratingError').classList.add('hidden');
        }

        // Validate comment
        if (comment === '') {
            document.getElementById('commentError').classList.remove('hidden');
            valid = false;
        } else {
            document.getElementById('commentError').classList.add('hidden');
        }

        if (!valid) {
            e.preventDefault();
            console.log("Form validation failed");
            return false;
        }

        console.log("Form validation passed - submitting");
        return true;
    });

    // Close modal when clicking outside
    document.getElementById('reviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReviewModal();
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>