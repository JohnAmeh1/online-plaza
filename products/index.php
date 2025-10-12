<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get filters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Build query
$query = "
    SELECT p.*, c.name as company_name, c.id as company_id,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM products p 
    JOIN companies c ON p.company_id = c.id 
    WHERE p.stock_quantity > 0
";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND p.category = ?";
    $params[] = $category;
}

if (!empty($min_price)) {
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}

if (!empty($max_price)) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}

$query .= " ORDER BY p.created_at DESC";

// Get products
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();

$pageTitle = "Products - Martly";
require_once '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Discover Products</h1>
                    <p class="text-gray-600 mt-2">Find amazing products from trusted vendors</p>
                </div>
                <div class="w-full md:w-auto">
                    <form method="GET" class="flex space-x-2">
                        <div class="relative flex-1 md:w-80">
                            <input type="text" name="search" placeholder="Search products..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <button type="submit" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-xl hover:from-green-600 hover:to-blue-600 transition duration-300 font-semibold">
                            Search
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Filters Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sticky top-4">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">Filters</h2>
                        <?php if ($category || $min_price || $max_price): ?>
                            <a href="/online-plaza/products/index.php" class="text-sm text-green-600 hover:text-green-700 font-medium">
                                Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <form method="GET" class="space-y-6">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        
                        <!-- Category Filter -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Category</label>
                            <select name="category" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Price Range -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Price Range</label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <input type="number" name="min_price" placeholder="Min" 
                                           value="<?php echo htmlspecialchars($min_price); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                           min="0">
                                </div>
                                <div>
                                    <input type="number" name="max_price" placeholder="Max" 
                                           value="<?php echo htmlspecialchars($max_price); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                           min="0">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Apply Filters Button -->
                        <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-blue-500 text-white py-3 rounded-xl hover:from-green-600 hover:to-blue-600 transition duration-300 font-semibold">
                            Apply Filters
                        </button>
                    </form>

                    <!-- Quick Stats -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="text-center">
                            <p class="text-sm text-gray-600">Found</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($products); ?></p>
                            <p class="text-sm text-gray-600">products</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="lg:col-span-3">
                <?php if ($products): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($products as $product): ?>
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition duration-300 group">
                                <!-- Product Image -->
                                <div class="relative h-48 bg-gray-100 overflow-hidden">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300">
                                            <i class="fas fa-image text-4xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Badges -->
                                    <div class="absolute top-3 left-3 flex flex-col space-y-1">
                                        <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                            <span class="bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded-full">New</span>
                                        <?php endif; ?>
                                        <?php if ($product['stock_quantity'] < 10 && $product['stock_quantity'] > 0): ?>
                                            <span class="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">Low Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Product Info -->
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-semibold text-gray-900 text-lg line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <span class="text-green-600 font-bold text-xl">₦<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                    
                                    <p class="text-gray-600 text-sm mb-3 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                                    
                                    <!-- Company and Rating -->
                                    <div class="flex items-center justify-between mb-3">
                                        <a href="/online-plaza/company/index.php?id=<?php echo $product['company_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                            <i class="fas fa-store mr-1 text-sm"></i>
                                            <?php echo htmlspecialchars($product['company_name']); ?>
                                        </a>
                                        
                                        <?php if ($product['avg_rating']): ?>
                                            <div class="flex items-center text-sm">
                                                <div class="flex text-yellow-400 mr-1">
                                                    <?php
                                                    $rating = round($product['avg_rating']);
                                                    for ($i = 1; $i <= 5; $i++):
                                                        if ($i <= $rating):
                                                    ?>
                                                        <i class="fas fa-star text-xs"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-xs"></i>
                                                    <?php endif; endfor; ?>
                                                </div>
                                                <span class="text-gray-500 text-xs">(<?php echo $product['review_count']; ?>)</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">No reviews</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Stock and Category -->
                                    <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                                        <span class="flex items-center">
                                            <i class="fas fa-box mr-1"></i>
                                            <?php echo $product['stock_quantity']; ?> in stock
                                        </span>
                                        <?php if ($product['category']): ?>
                                            <span class="bg-gray-100 px-2 py-1 rounded-lg text-xs"><?php echo htmlspecialchars($product['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <a href="/online-plaza/products/view.php?id=<?php echo $product['id']; ?>" 
                                       class="block w-full bg-gradient-to-r from-green-500 to-blue-500 text-white text-center py-3 rounded-xl hover:from-green-600 hover:to-blue-600 transition duration-300 font-semibold">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-200">
                        <div class="max-w-md mx-auto">
                            <div class="w-24 h-24 bg-gradient-to-br from-gray-200 to-gray-300 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-search text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">No products found</h3>
                            <p class="text-gray-600 mb-6">Try adjusting your search criteria or browse different categories.</p>
                            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                                <a href="/online-plaza/products/index.php" 
                                   class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-6 py-3 rounded-xl hover:from-green-600 hover:to-blue-600 transition duration-300 font-semibold">
                                    Clear Filters
                                </a>
                                <a href="/online-plaza/" 
                                   class="border border-gray-300 text-gray-700 px-6 py-3 rounded-xl hover:bg-gray-50 transition duration-300 font-semibold">
                                    Browse Home
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<?php require_once '../includes/footer.php'; ?>