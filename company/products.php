<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

if ($currentUser['user_type'] !== 'vendor') {
    header('Location: /online-plaza/profile/index.php');
    exit;
}

// Get company details
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$company = $stmt->fetch();

// Get products
$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count
    FROM products p 
    WHERE p.company_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$company['id']]);
$products = $stmt->fetchAll();

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $productId = (int)$_POST['product_id'];
    
    // Verify product belongs to company
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND company_id = ?");
    $stmt->execute([$productId, $company['id']]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        header("Location: /online-plaza/company/products.php?deleted=1");
        exit;
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Manage Products</h1>
            <p class="text-gray-600">Manage your product listings</p>
        </div>
        <a href="/online-plaza/products/create.php" class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition duration-300">
            <i class="fas fa-plus mr-2"></i>
            Add New Product
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            Product deleted successfully.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($products): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50 transition duration-300">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if ($product['image_url']): ?>
                                            <img class="h-10 w-10 rounded-lg object-cover" src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="h-10 w-10 bg-gray-200 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <?php if ($product['category']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['category']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">$<?php echo number_format($product['price'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $product['stock_quantity']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['avg_rating']): ?>
                                        <div class="flex items-center">
                                            <span class="text-sm text-yellow-400 mr-1"><?php echo number_format($product['avg_rating'], 1); ?></span>
                                            <i class="fas fa-star text-yellow-400 text-xs"></i>
                                            <span class="text-sm text-gray-500 ml-1">(<?php echo $product['review_count']; ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">No reviews</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $product['stock_quantity'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="/online-plaza/products/view.php?id=<?php echo $product['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/online-plaza/products/edit.php?id=<?php echo $product['id']; ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <!-- <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="delete_product" class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form> -->
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-box text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No Products Yet</h3>
                <p class="text-gray-500 mb-6">Start by adding your first product to your store.</p>
                <a href="/online-plaza/products/create.php" class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition duration-300">
                    <i class="fas fa-plus mr-2"></i>
                    Add Your First Product
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>