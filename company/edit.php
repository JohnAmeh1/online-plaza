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

if (!$company) {
    header('Location: /online-plaza/company/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $contact_email = trim($_POST['contact_email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $whatsapp_url = trim($_POST['whatsapp_url']);
    $instagram_url = trim($_POST['instagram_url']);

    // Validation
    if (empty($name) || empty($description) || empty($contact_email)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Handle logo upload
            $logoPath = $company['logo'];

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logoData = handleImageUpload($_FILES['logo'], 'logos', $company['logo']);
                $logoPath = $logoData['path'];
            }

            // Handle banner upload
            $bannerPath = $company['banner'];

            if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                $bannerData = handleImageUpload($_FILES['banner'], 'banners', $company['banner']);
                $bannerPath = $bannerData['path'];
            }

            // Update company with image paths and social links
            $stmt = $pdo->prepare("UPDATE companies SET 
                name = ?, description = ?, contact_email = ?, phone = ?, address = ?, 
                whatsapp_url = ?, instagram_url = ?,
                logo = ?, banner = ?, updated_at = NOW() 
                WHERE id = ?");

            if ($stmt->execute([
                $name,
                $description,
                $contact_email,
                $phone,
                $address,
                $whatsapp_url,
                $instagram_url,
                $logoPath,
                $bannerPath,
                $company['id']
            ])) {
                $success = 'Company information updated successfully!';
                // Refresh company data
                $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
                $stmt->execute([$currentUser['id']]);
                $company = $stmt->fetch();
            } else {
                $error = 'An error occurred. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Error uploading files: ' . $e->getMessage();
        }
    }
}

/**
 * Handle image upload
 */
function handleImageUpload($file, $type, $currentPath = '')
{
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.');
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Validate image dimensions
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new Exception('Invalid image file.');
    }

    // Specific dimension checks
    if ($type === 'logos' && ($imageInfo[0] > 2000 || $imageInfo[1] > 2000)) {
        throw new Exception('Logo dimensions should not exceed 2000x2000 pixels.');
    }

    if ($type === 'banners' && ($imageInfo[0] > 2000 || $imageInfo[1] > 800)) {
        throw new Exception('Banner dimensions should not exceed 2000x800 pixels.');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/online-plaza/uploads/' . $type . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename while preserving extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file.');
    }

    // Delete old file if it exists and is in our uploads directory
    if (!empty($currentPath) && strpos($currentPath, '/online-plaza/uploads/') !== false) {
        $oldFilePath = $_SERVER['DOCUMENT_ROOT'] . $currentPath;
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    return [
        'path' => '/online-plaza/uploads/' . $type . '/' . $fileName,
        'size' => $file['size'],
        'original_name' => $file['name'],
        'width' => $imageInfo[0],
        'height' => $imageInfo[1],
        'mime_type' => $mimeType
    ];
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
            <h1 class="text-3xl font-extrabold text-gray-800">Edit Company Information</h1>
            <a href="/online-plaza/company/dashboard.php" class="text-green-600 hover:text-green-800 font-semibold flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
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

        <!-- Current Images Preview -->
        <?php if ($company['logo'] || $company['banner']): ?>
            <div class="mb-8 p-6 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Images</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if ($company['logo']): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Current Logo</p>
                            <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Current Logo" class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                        </div>
                    <?php endif; ?>
                    <?php if ($company['banner']): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Current Banner</p>
                            <img src="<?php echo htmlspecialchars($company['banner']); ?>" alt="Current Banner" class="w-full h-32 object-cover rounded-lg border border-gray-300">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-8">
            <div>
                <label for="name" class="block text-lg font-medium text-gray-700 mb-2">Company Name *</label>
                <input type="text" id="name" name="name" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                    value="<?php echo htmlspecialchars($company['name']); ?>">
            </div>

            <div>
                <label for="description" class="block text-lg font-medium text-gray-700 mb-2">Description *</label>
                <textarea id="description" name="description" required rows="4"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"><?php echo htmlspecialchars($company['description']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label for="contact_email" class="block text-lg font-medium text-gray-700 mb-2">Contact Email *</label>
                    <input type="email" id="contact_email" name="contact_email" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                        value="<?php echo htmlspecialchars($company['contact_email']); ?>">
                </div>

                <div>
                    <label for="phone" class="block text-lg font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" id="phone" name="phone"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                        value="<?php echo htmlspecialchars($company['phone']); ?>">
                </div>
            </div>

            <div>
                <label for="address" class="block text-lg font-medium text-gray-700 mb-2">Business Address</label>
                <textarea id="address" name="address" rows="3"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"><?php echo htmlspecialchars($company['address']); ?></textarea>
            </div>

            <!-- Social Media Links -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label for="whatsapp_url" class="block text-lg font-medium text-gray-700 mb-2">WhatsApp URL</label>
                    <input type="url" id="whatsapp_url" name="whatsapp_url"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                        placeholder="https://wa.me/1234567890"
                        value="<?php echo htmlspecialchars($company['whatsapp_url'] ?? ''); ?>">
                    <p class="text-sm text-gray-500 mt-1">Full WhatsApp URL (e.g., https://wa.me/1234567890)</p>
                </div>

                <div>
                    <label for="instagram_url" class="block text-lg font-medium text-gray-700 mb-2">Instagram URL</label>
                    <input type="url" id="instagram_url" name="instagram_url"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-lg"
                        placeholder="https://instagram.com/username"
                        value="<?php echo htmlspecialchars($company['instagram_url'] ?? ''); ?>">
                    <p class="text-sm text-gray-500 mt-1">Full Instagram profile URL</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label for="logo" class="block text-lg font-medium text-gray-700 mb-2">Company Logo</label>
                    <input type="file" id="logo" name="logo" accept="image/*"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                        onchange="previewImage(this, 'logoPreview')">
                    <p class="text-sm text-gray-500 mt-1">Upload company logo (JPEG, PNG, GIF, WebP, max 5MB)</p>
                    <div id="logoPreview" class="mt-2 hidden">
                        <p class="text-sm font-medium text-gray-700 mb-2">Logo Preview:</p>
                        <img class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                    </div>
                </div>

                <div>
                    <label for="banner" class="block text-lg font-medium text-gray-700 mb-2">Company Banner</label>
                    <input type="file" id="banner" name="banner" accept="image/*"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-transparent text-base"
                        onchange="previewImage(this, 'bannerPreview')">
                    <p class="text-sm text-gray-500 mt-1">Upload company banner (JPEG, PNG, GIF, WebP, max 5MB)</p>
                    <div id="bannerPreview" class="mt-2 hidden">
                        <p class="text-sm font-medium text-gray-700 mb-2">Banner Preview:</p>
                        <img class="w-full h-32 object-cover rounded-lg border border-gray-300">
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row items-center justify-between pt-6 border-t border-gray-200 gap-4">
                <button type="submit" class="bg-green-500 text-white px-8 py-3 rounded-lg hover:bg-green-600 transition font-semibold w-full md:w-auto">
                    Update Company Information
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const previewImg = preview.querySelector('img');

        if (input.files && input.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.classList.remove('hidden');
            }

            reader.readAsDataURL(input.files[0]);
        } else {
            preview.classList.add('hidden');
        }
    }

    // Live preview for text fields
    document.addEventListener('DOMContentLoaded', function() {
        const nameInput = document.getElementById('name');
        const descriptionInput = document.getElementById('description');

        function updateLivePreview() {
            const previewSection = document.getElementById('previewSection');
            const previewName = document.getElementById('previewName');
            const previewDescription = document.getElementById('previewDescription');

            if (nameInput.value || descriptionInput.value) {
                previewName.textContent = nameInput.value || 'Company Name';
                previewDescription.textContent = descriptionInput.value || 'Company description';
                previewSection.classList.remove('hidden');
            } else {
                previewSection.classList.add('hidden');
            }
        }

        nameInput.addEventListener('input', updateLivePreview);
        descriptionInput.addEventListener('input', updateLivePreview);
    });
</script>

<?php require_once '../includes/footer.php'; ?>