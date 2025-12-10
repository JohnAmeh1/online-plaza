<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /online-plaza/products/index.php');
    exit;
}

$productId = (int)$_GET['id'];

// Get product details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as company_name, c.id as company_id, c.description as company_description,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM products p 
    JOIN companies c ON p.company_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: /online-plaza/products/index.php');
    exit;
}

// Get reviews
$stmt = $pdo->prepare("
    SELECT pr.*, u.username, u.first_name, u.last_name
    FROM product_reviews pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.product_id = ? 
    ORDER BY pr.created_at DESC
");
$stmt->execute([$productId]);
$reviews = $stmt->fetchAll();

// Check if current user has already reviewed this product
$userHasReviewed = false;
$currentUser = getCurrentUser();
if ($currentUser) {
    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$productId, $currentUser['id']]);
    $userHasReviewed = (bool)$stmt->fetch();
}

// Related products
$relatedStmt = $pdo->prepare("
    SELECT id, name, image_url, price 
    FROM products 
    WHERE category = ? AND id != ? AND stock_quantity > 0 
    ORDER BY RAND() LIMIT 3
");
$relatedStmt->execute([$product['category'], $productId]);
$related = $relatedStmt->fetchAll();

$pageTitle = $product['name'] . " - Martly";
require_once '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="mb-8" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center text-sm text-gray-600 space-x-2">
                <li><a href="/online-plaza/" class="hover:text-green-600 transition duration-300">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li><a href="/online-plaza/products/index.php" class="hover:text-green-600 transition duration-300">Products</a></li>
                <?php if ($product['category']): ?>
                    <li><i class="fas fa-chevron-right text-xs"></i></li>
                    <li>
                        <a href="/online-plaza/products/index.php?category=<?php echo urlencode($product['category']); ?>"
                            class="hover:text-green-600 transition duration-300">
                            <?php echo htmlspecialchars($product['category']); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li class="text-green-600 font-semibold"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <!-- Product Details -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-8">
                <!-- Product Images -->
                <div class="space-y-4">
                    <!-- Main Image -->
                    <div class="bg-gray-100 rounded-2xl overflow-hidden h-96 flex items-center justify-center">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                class="w-full h-full object-cover rounded-2xl">
                        <?php else: ?>
                            <div class="text-center text-gray-400">
                                <i class="fas fa-image text-6xl mb-4"></i>
                                <p class="text-lg font-semibold">No Image Available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="flex flex-col justify-between">
                    <div class="space-y-6">
                        <!-- Header -->
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <?php if ($product['stock_quantity'] == 0): ?>
                                    <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">Out of Stock</span>
                                <?php elseif (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                    <span class="bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full">New Arrival</span>
                                <?php endif; ?>
                                <?php if ($product['stock_quantity'] < 10 && $product['stock_quantity'] > 0): ?>
                                    <span class="bg-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full">Low Stock</span>
                                <?php endif; ?>
                            </div>

                            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>

                            <!-- Rating -->
                            <div class="flex items-center mb-4">
                                <?php if ($product['avg_rating']): ?>
                                    <div class="flex items-center text-yellow-400 mr-4">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= round($product['avg_rating']) ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ml-2 text-gray-600 font-semibold"><?php echo number_format($product['avg_rating'], 1); ?></span>
                                        <span class="ml-1 text-gray-500">(<?php echo $product['review_count']; ?> reviews)</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-500">No reviews yet</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="flex items-center">
                            <span class="text-4xl font-bold text-green-600">₦<?php echo number_format($product['price'], 2); ?></span>
                        </div>

                        <!-- Description -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
                            <p class="text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>

                        <!-- Key Specifications -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Stock:</span>
                                    <span class="font-semibold <?php echo $product['stock_quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $product['stock_quantity'] > 0 ? $product['stock_quantity'] . ' avl.' : 'Out of Stock'; ?>
                                    </span>
                                </div>
                                <?php if ($product['category']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Category:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['category']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['brand']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Brand:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['brand']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-2">
                                <?php if ($product['weight']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Weight:</span>
                                        <span class="font-semibold"><?php echo number_format($product['weight'], 2); ?> kg</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['color']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Color:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['color']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 mt-8">
                        <a href="/online-plaza/company/index.php?id=<?php echo $product['company_id']; ?>"
                            class="flex-1 bg-gradient-to-r from-blue-500 to-green-500 text-white py-4 px-6 rounded-xl hover:from-blue-600 hover:to-green-600 transition duration-300 font-semibold text-center flex items-center justify-center">
                            <i class="fas fa-store mr-2"></i>
                            Visit Store
                        </a>
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <button onclick="openDeliveryModal(<?php echo $product['id']; ?>, <?php echo $product['price']; ?>)"
                                class="flex-1 bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 px-6 rounded-xl hover:from-green-600 hover:to-blue-600 transition duration-300 font-semibold flex items-center justify-center pay-now-btn">
                                <i class="fas fa-credit-card mr-2"></i>
                                Buy Now - ₦<?php echo number_format($product['price'], 2); ?>
                            </button>
                        <?php else: ?>
                            <button class="flex-1 bg-gray-400 text-white py-4 px-6 rounded-xl cursor-not-allowed font-semibold" disabled>
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                    <!-- Action Buttons
                    <div class="flex flex-col sm:flex-row gap-4 mt-8">
                        <a href="/online-plaza/company/index.php?id=<?php echo $product['company_id']; ?>"
                            class="flex-1 bg-gradient-to-r from-blue-500 to-green-500 text-white py-4 px-6 rounded-xl hover:from-blue-600 hover:to-green-600 transition duration-300 font-semibold text-center flex items-center justify-center">
                            <i class="fas fa-store mr-2"></i>
                            Visit Store
                        </a>
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <button onclick="payNow(<?php echo $product['id']; ?>, <?php echo $product['price']; ?>)"
                                class="flex-1 bg-gradient-to-r from-green-500 to-blue-500 text-white py-4 px-6 rounded-xl hover:from-green-600 hover:to-blue-600 transition duration-300 font-semibold flex items-center justify-center pay-now-btn">
                                <i class="fas fa-credit-card mr-2"></i>
                                Buy Now - ₦<?php echo number_format($product['price'], 2); ?>
                            </button>
                        <?php else: ?>
                            <button class="flex-1 bg-gray-400 text-white py-4 px-6 rounded-xl cursor-not-allowed font-semibold" disabled>
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </div> -->
                </div>
            </div>
        </div>

        <!-- Additional Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Product Specifications -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Product Specifications</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Info -->
                        <div class="space-y-4">
                            <h3 class="font-semibold text-gray-700 border-b pb-2">Basic Information</h3>
                            <div class="space-y-3">
                                <?php if ($product['brand']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Brand:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['brand']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['location']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Location:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['location']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['material']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Material:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['material']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['warranty']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Warranty:</span>
                                        <span class="font-semibold text-green-600"><?php echo htmlspecialchars($product['warranty']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- product Specs -->
                        <div class="space-y-4">
                            <h3 class="font-semibold text-gray-700 border-b pb-2">Additional Information</h3>
                            <div class="space-y-3">
                                <?php if ($product['dimensions']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Dimensions:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['dimensions']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['size']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Size:</span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($product['size']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($product['shipping_time']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Shipping:</span>
                                        <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($product['shipping_time']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Info -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">About the Seller</h2>
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($product['company_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($product['company_name']); ?></h3>
                                <p class="text-gray-600 text-sm">Trusted Vendor</p>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($product['company_description']); ?></p>
                        <a href="/online-plaza/company/index.php?id=<?php echo $product['company_id']; ?>"
                            class="block w-full bg-gray-100 text-gray-700 text-center py-3 rounded-xl hover:bg-gray-200 transition duration-300 font-semibold">
                            Visit Store
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Customer Reviews</h2>

            <!-- Review Stats -->
            <?php if ($product['review_count'] > 0): ?>
                <div class="flex items-center mb-6 p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center text-yellow-400 mr-4">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= round($product['avg_rating']) ? 'text-yellow-400' : 'text-gray-300'; ?> text-2xl"></i>
                        <?php endfor; ?>
                    </div>
                    <div>
                        <span class="text-2xl font-bold text-gray-900 mr-2"><?php echo number_format($product['avg_rating'], 1); ?></span>
                        <span class="text-gray-500">out of 5</span>
                        <p class="text-gray-600 text-sm">Based on <?php echo $product['review_count']; ?> reviews</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Review Form -->
            <!-- Review Form -->
            <?php if ($currentUser && !$userHasReviewed): ?>
                <form method="POST" class="review-form mb-8 p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-xl" data-product-id="<?php echo $productId; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Write a Review</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Your Rating</label>
                        <div class="flex space-x-1">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" class="hidden" required>
                                    <i class="fas fa-star text-2xl text-gray-300 hover:text-yellow-400 rating-star" data-rating="<?php echo $i; ?>"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="review_text" class="block text-sm font-medium text-gray-700 mb-2">Your Review</label>
                        <textarea name="review_text" id="review_text" rows="4" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Share your experience with this product..."></textarea>
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-xl hover:from-green-600 hover:to-blue-600 transition font-semibold">
                        Submit Review
                    </button>
                </form>
            <?php elseif ($currentUser && $userHasReviewed): ?>
                <div class="bg-blue-50 p-6 rounded-xl mb-8 text-center">
                    <i class="fas fa-check-circle text-blue-500 text-2xl mb-2"></i>
                    <p class="text-blue-700 font-semibold">You have already reviewed this product.</p>
                </div>
            <?php else: ?>
                <div class="bg-gray-50 p-6 rounded-xl mb-8 text-center">
                    <p class="text-gray-600 mb-2">Please <a href="/online-plaza/auth/login.php" class="text-green-600 hover:text-green-800 font-semibold">login</a> to write a review.</p>
                </div>
            <?php endif; ?>

            <!-- Reviews List -->
            <div class="reviews-list">
                <?php if ($reviews): ?>
                    <div class="space-y-6">
                        <?php foreach ($reviews as $review): ?>
                            <div class="border border-gray-200 rounded-xl p-6 hover:shadow-sm transition duration-300">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h4>
                                            <p class="text-gray-500 text-sm">@<?php echo htmlspecialchars($review['username']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-yellow-400">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="text-gray-700 mb-3 leading-relaxed"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                <p class="text-gray-500 text-sm"><?php echo date('F j, Y \a\t g:i A', strtotime($review['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-comments text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Reviews Yet</h3>
                        <p class="text-gray-500">Be the first to review this product!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Products -->
        <?php if ($related): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Related Products</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($related as $item): ?>
                        <a href="/online-plaza/products/view.php?id=<?php echo $item['id']; ?>"
                            class="block bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition duration-300 group">
                            <div class="h-40 bg-gray-100 overflow-hidden">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                        alt="<?php echo htmlspecialchars($item['name']); ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300">
                                        <i class="fas fa-image text-2xl text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="text-green-600 font-bold text-lg">₦<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delivery Address Modal -->
<div id="deliveryModal" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200 sticky top-0 bg-white">
            <h3 class="text-2xl font-bold text-gray-800">Delivery Address</h3>
            <button onclick="closeDeliveryModal()" class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 w-10 h-10 rounded-full transition-all duration-300 flex items-center justify-center">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-6">
            <form id="deliveryForm" class="space-y-5">
                <input type="hidden" id="productId" value="">
                <input type="hidden" id="productPrice" value="">

                <!-- Full Name -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="fullName" placeholder="Your full name" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" id="phoneNumber" placeholder="e.g., +234 8012345678" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                </div>

                <!-- Email Address -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="emailAddress" placeholder="your@email.com" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                </div>

                <!-- Street Address -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Street Address</label>
                    <input type="text" id="streetAddress" placeholder="House number, street name" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                </div>

                <!-- City / Town -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">City / Town</label>
                        <input type="text" id="city" placeholder="City" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">State / Province</label>
                        <input type="text" id="state" placeholder="State" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                    </div>
                </div>

                <!-- Postal Code -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Postal Code (Optional)</label>
                    <input type="text" id="postalCode" placeholder="e.g., 101001"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300">
                </div>

                <!-- Delivery Instructions -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Delivery Instructions (Optional)</label>
                    <textarea id="deliveryInstructions" rows="3" placeholder="Any special instructions for delivery..."
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-300 resize-none"></textarea>
                </div>

                <!-- Address Summary -->
                <div class="bg-gradient-to-r from-green-50 to-blue-50 p-4 rounded-xl border border-green-200">
                    <p class="text-sm text-gray-600 mb-2"><span class="font-semibold">Product Price:</span></p>
                    <p class="text-2xl font-bold text-green-600" id="priceDisplay">₦0.00</p>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="flex gap-3 p-6 border-t border-gray-200 bg-gray-50 sticky bottom-0">
            <button onclick="closeDeliveryModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-3 px-6 rounded-xl transition duration-300 font-semibold">
                Cancel
            </button>
            <button onclick="submitDeliveryAndPay()" class="flex-1 bg-gradient-to-r from-green-500 to-blue-500 hover:from-green-600 hover:to-blue-600 text-white py-3 px-6 rounded-xl transition duration-300 font-semibold flex items-center justify-center delivery-buy-btn">
                <i class="fas fa-credit-card mr-2"></i>
                Confirm & Buy Now
            </button>
        </div>
    </div>
</div>

<script>
    // Rating stars interaction
    document.addEventListener('DOMContentLoaded', function() {
        const ratingStars = document.querySelectorAll('.rating-star');
        let selectedRating = 0;

        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                selectedRating = rating;

                ratingStars.forEach(s => {
                    const starRating = parseInt(s.getAttribute('data-rating'));
                    if (starRating <= rating) {
                        s.classList.add('text-yellow-400');
                        s.classList.remove('text-gray-300');
                    } else {
                        s.classList.remove('text-yellow-400');
                        s.classList.add('text-gray-300');
                    }
                });

                const radioInput = document.querySelector(`input[name="rating"][value="${rating}"]`);
                if (radioInput) {
                    radioInput.checked = true;
                }
            });

            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                if (!selectedRating) {
                    ratingStars.forEach(s => {
                        const starRating = parseInt(s.getAttribute('data-rating'));
                        if (starRating <= rating) {
                            s.classList.add('text-yellow-300');
                            s.classList.remove('text-gray-300');
                        }
                    });
                }
            });

            star.addEventListener('mouseleave', function() {
                if (!selectedRating) {
                    ratingStars.forEach(s => {
                        s.classList.remove('text-yellow-300');
                        s.classList.add('text-gray-300');
                    });
                }
            });
        });
    });

    // Open delivery modal
    function openDeliveryModal(productId, price) {
        const modal = document.getElementById('deliveryModal');
        const productIdInput = document.getElementById('productId');
        const productPriceInput = document.getElementById('productPrice');
        const priceDisplay = document.getElementById('priceDisplay');

        productIdInput.value = productId;
        productPriceInput.value = price;
        priceDisplay.textContent = '₦' + parseFloat(price).toLocaleString('en-NG', { minimumFractionDigits: 2 });

        // Pre-fill user info if logged in
        const currentUser = <?php echo json_encode($currentUser); ?>;
        if (currentUser) {
            document.getElementById('fullName').value = (currentUser.first_name || '') + ' ' + (currentUser.last_name || '');
            document.getElementById('emailAddress').value = currentUser.email || '';
            document.getElementById('phoneNumber').value = currentUser.phone || '';
        }

        modal.classList.remove('hidden');
    }

    // Close delivery modal
    function closeDeliveryModal() {
        const modal = document.getElementById('deliveryModal');
        modal.classList.add('hidden');
    }

    // Submit delivery address and process payment
    async function submitDeliveryAndPay() {
        const form = document.getElementById('deliveryForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const productId = document.getElementById('productId').value;
        const price = document.getElementById('productPrice').value;
        const btn = document.querySelector('.delivery-buy-btn');
        const originalText = btn.innerHTML;

        const deliveryData = {
            product_id: productId,
            price: price,
            full_name: document.getElementById('fullName').value,
            phone_number: document.getElementById('phoneNumber').value,
            email_address: document.getElementById('emailAddress').value,
            street_address: document.getElementById('streetAddress').value,
            city: document.getElementById('city').value,
            state: document.getElementById('state').value,
            postal_code: document.getElementById('postalCode').value,
            delivery_instructions: document.getElementById('deliveryInstructions').value
        };

        try {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            btn.disabled = true;

            const formData = new FormData();
            Object.keys(deliveryData).forEach(key => {
                formData.append(key, deliveryData[key]);
            });

            const response = await fetch('/online-plaza/products/process_payment.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                btn.innerHTML = '<i class="fas fa-check mr-2"></i>Payment Successful!';
                btn.classList.remove('from-green-500', 'to-blue-500', 'hover:from-green-600', 'hover:to-blue-600');
                btn.classList.add('bg-green-500', 'hover:bg-green-600');

                showNotification(result.message || 'Order placed successfully!', 'success');

                setTimeout(() => {
                    closeDeliveryModal();
                    window.location.href = '/online-plaza/orders/index.php';
                }, 2000);
            } else {
                throw new Error(result.message || 'Payment failed');
            }

        } catch (error) {
            console.error('Error:', error);
            btn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error';
            btn.classList.remove('from-green-500', 'to-blue-500', 'hover:from-green-600', 'hover:to-blue-600');
            btn.classList.add('bg-red-500', 'hover:bg-red-600');

            showNotification(error.message || 'An error occurred. Please try again.', 'error');

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.className = 'flex-1 bg-gradient-to-r from-green-500 to-blue-500 hover:from-green-600 hover:to-blue-600 text-white py-3 px-6 rounded-xl transition duration-300 font-semibold flex items-center justify-center delivery-buy-btn';
                btn.disabled = false;
            }, 3000);
        }
    }

    // Close modal when clicking outside
    document.getElementById('deliveryModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeliveryModal();
        }
    });

    function payNow(productId, price) {
        openDeliveryModal(productId, price);
    }
</script>

<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<?php require_once '../includes/footer.php'; ?>