<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$currentUser = getCurrentUser();
if (!isLoggedIn() || !$currentUser || $currentUser['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Get products with pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

try {
    // Build conditions
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($category)) {
        $conditions[] = "p.category = ?";
        $params[] = $category;
    }
    
    if ($status === 'active') {
        $conditions[] = "p.is_active = 1";
    } elseif ($status === 'inactive') {
        $conditions[] = "p.is_active = 0";
    }
    
    $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM products p 
                 LEFT JOIN companies c ON p.company_id = c.id 
                 $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalProducts / $limit);

    // Get products with company info - FIXED: Remove order_items reference
    $sql = "
        SELECT p.*, 
               c.name as company_name,
               c.contact_email as company_email,
               u.first_name as vendor_first_name,
               u.last_name as vendor_last_name
        FROM products p 
        LEFT JOIN companies c ON p.company_id = c.id 
        LEFT JOIN users u ON c.user_id = u.id
        $whereClause
        ORDER BY p.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Error loading products: " . $e->getMessage();
}

$pageTitle = "Manage Products - Martly Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/martly.svg">
</head>
<body class="bg-gray-50">
<div class="min-h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="ml-0 lg:ml-64">
        <?php include 'includes/topbar.php'; ?>
        <main class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Manage Products</h1>
                    <p class="text-gray-600 mt-2">View and manage all products in the marketplace</p>
                </div>
                <!-- <button class="bg-gradient-to-r from-purple-500 to-pink-600 text-white px-6 py-3 rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>
                    Add Product
                </button> -->
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Product name, description..." 
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-300 w-full">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                            <a href="products.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-300 whitespace-nowrap">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Products Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if (empty($products)): ?>
                    <div class="col-span-full bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                        <i class="fas fa-box text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No products found</p>
                        <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                            <p class="text-sm mt-1">Try adjusting your filters</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-300">
                            <!-- Product Image -->
                            <div class="h-48 bg-gradient-to-br from-purple-100 to-pink-100 flex items-center justify-center relative">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="h-full w-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-box text-4xl text-purple-300"></i>
                                <?php endif; ?>
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo (isset($product['is_active']) && $product['is_active']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo (isset($product['is_active']) && $product['is_active']) ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 text-lg mb-1 truncate">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h3>
                                <p class="text-gray-600 text-sm mb-2 line-clamp-2">
                                    <?php echo htmlspecialchars($product['description'] ?? 'No description'); ?>
                                </p>
                                
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-2xl font-bold text-gray-900">
                                        $<?php echo number_format($product['price'], 2); ?>
                                    </span>
                                    <?php if (($product['stock_quantity'] ?? 0) > 0): ?>
                                        <span class="text-sm text-green-600 font-medium">
                                            <?php echo $product['stock_quantity']; ?> in stock
                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm text-red-600 font-medium">Out of stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center justify-between text-sm text-gray-500 mb-3">
                                    <span><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span>
                                    <span><?php echo htmlspecialchars($product['brand'] ?? ''); ?></span>
                                </div>
                                
                                <div class="border-t border-gray-100 pt-3">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">By: <?php echo htmlspecialchars($product['company_name'] ?? 'Unknown'); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex justify-between items-center mt-4 pt-3 border-t border-gray-100">
                                    <button class="text-blue-600 hover:text-blue-800 transition-colors duration-300" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-green-600 hover:text-green-800 transition-colors duration-300" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-purple-600 hover:text-purple-800 transition-colors duration-300" title="Inventory">
                                        <i class="fas fa-warehouse"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 transition-colors duration-300" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white px-6 py-4 border-t border-gray-200 rounded-b-xl mt-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                        <span class="font-medium"><?php echo min($offset + $limit, $totalProducts); ?></span> of
                        <span class="font-medium"><?php echo $totalProducts; ?></span> products
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" 
                               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-300">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" 
                               class="px-4 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors duration-300">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?>" 
                               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-300">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const sidebar = document.querySelector('.fixed.inset-y-0');
    sidebar.classList.toggle('-translate-x-full');
});
</script>
</body>
</html>