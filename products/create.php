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

$error = '';
$success = '';

// Platform fee percentage (7%)
$platformFeePercentage = 7.00;

// Standard ecommerce categories
$categories = [
    'electronics' => 'Electronics',
    'fashion' => 'Fashion & Clothing',
    'home_garden' => 'Home & Garden',
    'beauty' => 'Beauty & Personal Care',
    'sports' => 'Sports & Outdoors',
    'toys' => 'Toys & Games',
    'books' => 'Books & Media',
    'food' => 'Food & Beverages',
    'health' => 'Health & Wellness',
    'automotive' => 'Automotive',
    'jewelry' => 'Jewelry & Accessories',
    'art_crafts' => 'Arts & Crafts',
    'pet_supplies' => 'Pet Supplies',
    'baby_kids' => 'Baby & Kids',
    'office' => 'Office Supplies'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock_quantity = (int)$_POST['stock_quantity'];
    $category = trim($_POST['category']);
    $brand = trim($_POST['brand']);
    $weight = $_POST['weight'] ? (float)$_POST['weight'] : null;
    $dimensions = trim($_POST['dimensions']);
    $color = trim($_POST['color']);
    $size = trim($_POST['size']);
    $material = trim($_POST['material']);
    $warranty = trim($_POST['warranty']);
    $media_type = $_POST['media_type'] ?? 'image';
    
    // Calculate platform fee and net amount
    $platform_fee = ($price * $platformFeePercentage) / 100;
    $net_amount = $price - $platform_fee;
    
    // Validation
    if (empty($name) || empty($description) || $price <= 0 || empty($category)) {
        $error = 'Please fill in all required fields with valid values.';
    } else {
        $image_url = '';
        $media_filename = '';
        
        // Handle file upload
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowedVideoTypes = ['video/mp4', 'video/mov', 'video/avi', 'video/webm'];
            
            $file = $_FILES['media_file'];
            $fileType = mime_content_type($file['tmp_name']);
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            
            // Validate file type based on media_type selection
            if ($media_type === 'image' && !in_array($fileType, $allowedImageTypes)) {
                $error = 'Please upload a valid image file (JPEG, PNG, GIF, WebP).';
            } elseif ($media_type === 'video' && !in_array($fileType, $allowedVideoTypes)) {
                $error = 'Please upload a valid video file (MP4, MOV, AVI, WebM).';
            } else {
                // Generate unique filename
                $media_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file['name']);
                $uploadPath = $uploadDir . $media_filename;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $image_url = '/online-plaza/uploads/products/' . $media_filename;
                } else {
                    $error = 'Failed to upload file. Please try again.';
                }
            }
        } elseif (!empty($_POST['image_url'])) {
            // Fallback to URL if provided
            $image_url = trim($_POST['image_url']);
        }
        
        if (empty($error)) {
            // Insert product with platform fee and net amount
            $stmt = $pdo->prepare("INSERT INTO products (company_id, name, description, price, stock_quantity, category, brand, weight, dimensions, color, size, material, warranty, image_url, media_filename, media_type, platform_fee, net_amount, fee_percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([
                $company['id'], 
                $name, 
                $description, 
                $price, 
                $stock_quantity, 
                $category, 
                $brand, 
                $weight, 
                $dimensions, 
                $color, 
                $size, 
                $material, 
                $warranty, 
                $image_url, 
                $media_filename, 
                $media_type,
                $platform_fee,
                $net_amount,
                $platformFeePercentage
            ])) {
                $success = 'Product created successfully! Platform fee (7%) will be deducted from each sale.';
                // Clear form
                $_POST = array();
                $_FILES = array();
            } else {
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
            <h1 class="text-3xl font-extrabold text-gray-800">Add New Product</h1>
            <a href="/online-plaza/company/products.php" class="text-green-600 hover:text-green-800 font-semibold flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Products
            </a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-8">
            <!-- Basic Information -->
            <div class="bg-gray-50 p-6 rounded-xl">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Basic Information</h2>
                
                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-lg font-medium text-gray-700 mb-2">Product Name *</label>
                        <input type="text" id="name" name="name" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                               placeholder="Enter product name"
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>

                    <div>
                        <label for="description" class="block text-lg font-medium text-gray-700 mb-2">Description *</label>
                        <textarea id="description" name="description" required rows="6"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                                  placeholder="Describe your product in detail"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div>
                        <label class="block text-lg font-medium text-gray-700 mb-4">Category *</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                            <?php foreach ($categories as $key => $value): ?>
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="category" value="<?php echo $key; ?>" 
                                           class="mr-3 text-green-500 focus:ring-green-400" 
                                           <?php echo (isset($_POST['category']) && $_POST['category'] === $key) ? 'checked' : ''; ?>>
                                    <span class="text-sm font-medium"><?php echo $value; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing & Inventory -->
            <div class="bg-gray-50 p-6 rounded-xl">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Pricing & Inventory</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="price" class="block text-lg font-medium text-gray-700 mb-2">Price ($) *</label>
                        <input type="number" id="price" name="price" required step="0.01" min="0.01"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                               placeholder="0.00"
                               value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>"
                               onchange="calculateFees()" onkeyup="calculateFees()">
                    </div>

                    <div>
                        <label for="stock_quantity" class="block text-lg font-medium text-gray-700 mb-2">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" required min="0"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                               placeholder="0"
                               value="<?php echo isset($_POST['stock_quantity']) ? htmlspecialchars($_POST['stock_quantity']) : '0'; ?>">
                    </div>

                    <div>
                        <label for="brand" class="block text-lg font-medium text-gray-700 mb-2">Brand</label>
                        <input type="text" id="brand" name="brand"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                               placeholder="Brand name"
                               value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>">
                    </div>
                </div>

                <!-- Platform Fee Calculation Section -->
                <div class="mt-6 p-4 bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Platform Fee Breakdown</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Platform Fee (<?php echo $platformFeePercentage; ?>%)</label>
                            <input type="number" id="platform_fee" name="platform_fee" step="0.01" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white text-red-600 font-semibold">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Your Net Amount</label>
                            <input type="number" id="net_amount" name="net_amount" step="0.01" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white text-green-600 font-semibold">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Price</label>
                            <input type="number" id="total_price" step="0.01" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mt-3 flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        Platform fee of <?php echo $platformFeePercentage; ?>% is automatically deducted from each sale
                    </p>
                </div>
            </div>

            <!-- Product Specifications -->
            <div class="bg-gray-50 p-6 rounded-xl">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Product Specifications</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="weight" class="block text-lg font-medium text-gray-700 mb-2">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" step="0.01" min="0"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               placeholder="0.00"
                               value="<?php echo isset($_POST['weight']) ? htmlspecialchars($_POST['weight']) : ''; ?>">
                    </div>

                    <div>
                        <label for="dimensions" class="block text-lg font-medium text-gray-700 mb-2">Dimensions</label>
                        <input type="text" id="dimensions" name="dimensions"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               placeholder="L x W x H"
                               value="<?php echo isset($_POST['dimensions']) ? htmlspecialchars($_POST['dimensions']) : ''; ?>">
                    </div>

                    <div>
                        <label for="color" class="block text-lg font-medium text-gray-700 mb-2">Color</label>
                        <input type="text" id="color" name="color"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               placeholder="Product color"
                               value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>">
                    </div>

                    <div>
                        <label for="size" class="block text-lg font-medium text-gray-700 mb-2">Size</label>
                        <input type="text" id="size" name="size"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               placeholder="S, M, L, XL or dimensions"
                               value="<?php echo isset($_POST['size']) ? htmlspecialchars($_POST['size']) : ''; ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label for="material" class="block text-lg font-medium text-gray-700 mb-2">Material</label>
                        <input type="text" id="material" name="material"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               placeholder="Product material"
                               value="<?php echo isset($_POST['material']) ? htmlspecialchars($_POST['material']) : ''; ?>">
                    </div>

                    <div>
                        <label for="warranty" class="block text-lg font-medium text-gray-700 mb-2">Warranty</label>
                        <input type="text" id="warranty" name="warranty"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               placeholder="1 year warranty"
                               value="<?php echo isset($_POST['warranty']) ? htmlspecialchars($_POST['warranty']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Media Upload -->
            <div class="bg-gray-50 p-6 rounded-xl">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Product Media</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label for="media_type" class="block text-lg font-medium text-gray-700 mb-2">Media Type</label>
                        <select id="media_type" name="media_type" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base">
                            <option value="image" <?php echo (isset($_POST['media_type']) && $_POST['media_type'] === 'image') ? 'selected' : ''; ?>>Image</option>
                            <!-- <option value="video" <?php echo (isset($_POST['media_type']) && $_POST['media_type'] === 'video') ? 'selected' : ''; ?>>Video</option> -->
                        </select>
                    </div>

                    <div>
                        <label for="media_file" class="block text-lg font-medium text-gray-700 mb-2">Upload Product Media</label>
                        <input type="file" id="media_file" name="media_file" accept="image/*,video/*"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                               onchange="previewMedia(this)">
                        <p class="text-xs text-gray-500 mt-1">Max file size: 10MB. For images: JPEG, PNG, GIF, WebP. For videos: MP4, MOV, AVI, WebM.</p>
                    </div>
                </div>

                <div id="urlSection" class="mt-6 hidden">
                    <label for="image_url" class="block text-lg font-medium text-gray-700 mb-2">Or provide Media URL</label>
                    <input type="url" id="image_url" name="image_url"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                           placeholder="https://example.com/product-image.jpg"
                           value="<?php echo isset($_POST['image_url']) ? htmlspecialchars($_POST['image_url']) : ''; ?>">
                </div>
            </div>

            <!-- Preview Section -->
            <div id="previewSection" class="bg-gray-50 p-6 rounded-xl hidden">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Product Preview</h2>
                <div class="border border-gray-300 rounded-lg p-6 bg-white">
                    <div class="flex flex-col md:flex-row items-start space-y-4 md:space-y-0 md:space-x-6">
                        <div id="previewMedia" class="w-32 h-32 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 id="previewName" class="font-bold text-xl text-gray-800"></h3>
                                    <p id="previewBrand" class="text-gray-600 text-sm mt-1"></p>
                                    <p id="previewCategory" class="text-green-600 font-semibold text-sm mt-1"></p>
                                </div>
                                <div class="text-right">
                                    <p id="previewPrice" class="text-2xl font-bold text-green-600"></p>
                                    <p id="previewNetAmount" class="text-sm text-gray-600 mt-1"></p>
                                </div>
                            </div>
                            <p id="previewDescription" class="mt-3 text-gray-600"></p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                                <div>
                                    <span class="font-semibold">Stock:</span>
                                    <span id="previewStock" class="ml-1"></span>
                                </div>
                                <div>
                                    <span class="font-semibold">Color:</span>
                                    <span id="previewColor" class="ml-1"></span>
                                </div>
                                <div>
                                    <span class="font-semibold">Size:</span>
                                    <span id="previewSize" class="ml-1"></span>
                                </div>
                                <div>
                                    <span class="font-semibold">Material:</span>
                                    <span id="previewMaterial" class="ml-1"></span>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <div>
                                        <span class="font-semibold">Weight:</span>
                                        <span id="previewWeight" class="ml-1"></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Dimensions:</span>
                                        <span id="previewDimensions" class="ml-1"></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Warranty:</span>
                                        <span id="previewWarranty" class="ml-1"></span>
                                    </div>
                                    <div>
                                        <span class="font-semibold">Platform Fee:</span>
                                        <span id="previewPlatformFee" class="ml-1 text-red-600 font-semibold"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col md:flex-row items-center justify-between pt-6 border-t border-gray-200 gap-4">
                <button type="button" onclick="previewProduct()" class="bg-gradient-to-r from-green-500 to-blue-500 text-white px-8 py-3 rounded-lg font-semibold shadow hover:from-green-600 hover:to-blue-600 transition flex items-center">
                    <i class="fas fa-eye mr-2"></i> Preview Product
                </button>
                <button type="submit" class="bg-green-500 text-white px-8 py-3 rounded-lg hover:bg-green-600 transition font-semibold flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function calculateFees() {
    const price = parseFloat(document.getElementById('price').value) || 0;
    const platformFeePercentage = <?php echo $platformFeePercentage; ?>;
    const platformFee = (price * platformFeePercentage) / 100;
    const netAmount = price - platformFee;
    
    document.getElementById('platform_fee').value = platformFee.toFixed(2);
    document.getElementById('net_amount').value = netAmount.toFixed(2);
    document.getElementById('total_price').value = price.toFixed(2);
}

function previewMedia(input) {
    const urlSection = document.getElementById('urlSection');
    if (input.files && input.files[0]) {
        urlSection.classList.add('hidden');
    } else {
        urlSection.classList.remove('hidden');
    }
}

function previewProduct() {
    const name = document.getElementById('name').value;
    const description = document.getElementById('description').value;
    const price = document.getElementById('price').value;
    const stock = document.getElementById('stock_quantity').value;
    const brand = document.getElementById('brand').value;
    const weight = document.getElementById('weight').value;
    const dimensions = document.getElementById('dimensions').value;
    const color = document.getElementById('color').value;
    const size = document.getElementById('size').value;
    const material = document.getElementById('material').value;
    const warranty = document.getElementById('warranty').value;
    const mediaFile = document.getElementById('media_file').files[0];
    const mediaUrl = document.getElementById('image_url').value;
    const mediaType = document.getElementById('media_type').value;
    
    // Calculate fees for preview
    const platformFeePercentage = <?php echo $platformFeePercentage; ?>;
    const platformFee = (price * platformFeePercentage) / 100;
    const netAmount = price - platformFee;
    
    // Get selected category
    const categoryInput = document.querySelector('input[name="category"]:checked');
    const category = categoryInput ? categoryInput.nextElementSibling.textContent : 'No category selected';
    
    if (name || description || price) {
        document.getElementById('previewName').textContent = name || 'No name';
        document.getElementById('previewBrand').textContent = brand || 'No brand';
        document.getElementById('previewCategory').textContent = category;
        document.getElementById('previewPrice').textContent = price ? `$${parseFloat(price).toFixed(2)}` : '$0.00';
        document.getElementById('previewNetAmount').textContent = `You earn: $${netAmount.toFixed(2)}`;
        document.getElementById('previewStock').textContent = stock ? `${stock} in stock` : 'Out of stock';
        document.getElementById('previewColor').textContent = color || 'Not specified';
        document.getElementById('previewSize').textContent = size || 'Not specified';
        document.getElementById('previewMaterial').textContent = material || 'Not specified';
        document.getElementById('previewWeight').textContent = weight ? `${weight} kg` : 'Not specified';
        document.getElementById('previewDimensions').textContent = dimensions || 'Not specified';
        document.getElementById('previewWarranty').textContent = warranty || 'No warranty';
        document.getElementById('previewPlatformFee').textContent = `$${platformFee.toFixed(2)} (${platformFeePercentage}%)`;
        document.getElementById('previewDescription').textContent = description || 'No description';
        
        const previewMedia = document.getElementById('previewMedia');
        previewMedia.innerHTML = '';
        
        if (mediaFile) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (mediaType === 'image') {
                    previewMedia.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-32 h-32 object-cover rounded">`;
                } else {
                    previewMedia.innerHTML = `
                        <video class="w-32 h-32 object-cover rounded" controls>
                            <source src="${e.target.result}" type="${mediaFile.type}">
                        </video>
                    `;
                }
            };
            reader.readAsDataURL(mediaFile);
        } else if (mediaUrl) {
            if (mediaType === 'image') {
                previewMedia.innerHTML = `<img src="${mediaUrl}" alt="Preview" class="w-32 h-32 object-cover rounded">`;
            } else {
                previewMedia.innerHTML = `
                    <video class="w-32 h-32 object-cover rounded" controls>
                        <source src="${mediaUrl}" type="video/mp4">
                    </video>
                `;
            }
        } else {
            previewMedia.innerHTML = '<i class="fas fa-image text-gray-400 text-2xl"></i>';
        }
        
        document.getElementById('previewSection').classList.remove('hidden');
        
        // Scroll to preview section
        document.getElementById('previewSection').scrollIntoView({ behavior: 'smooth' });
    }
}

// Calculate fees on page load if price is already set
document.addEventListener('DOMContentLoaded', function() {
    calculateFees();
    
    // Show URL section if no file is selected initially
    const mediaFile = document.getElementById('media_file');
    if (!mediaFile.files.length) {
        document.getElementById('urlSection').classList.remove('hidden');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>