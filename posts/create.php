<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Redirect if user is not a vendor
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $media_type = $_POST['media_type'] ?? 'image';
    
    // Validation
    if (empty($title) || empty($content)) {
        $error = 'Please fill in title and content.';
    } else {
        $media_url = '';
        $media_filename = '';
        
        // Handle file upload
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/posts/';
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
                    $media_url = '/online-plaza/uploads/posts/' . $media_filename;
                } else {
                    $error = 'Failed to upload file. Please try again.';
                }
            }
        } elseif (!empty($_POST['media_url'])) {
            // Fallback to URL if provided
            $media_url = trim($_POST['media_url']);
        }
        
        if (empty($error)) {
            // Insert post
            $stmt = $pdo->prepare("INSERT INTO posts (company_id, title, content, media_url, media_filename, media_type) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$company['id'], $title, $content, $media_url, $media_filename, $media_type])) {
                $success = 'Post created successfully!';
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

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Create New Post</h1>
            <a href="/online-plaza/company/dashboard.php" class="text-gray-600 hover:text-gray-800">
                ← Back to Dashboard
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

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Post Title *</label>
                <input type="text" id="title" name="title" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                       placeholder="Enter a compelling title for your post"
                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                <textarea id="content" name="content" required rows="8"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                          placeholder="Share updates, promotions, or news about your company"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="media_type" class="block text-sm font-medium text-gray-700 mb-2">Media Type</label>
                    <select id="media_type" name="media_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="image" <?php echo (isset($_POST['media_type']) && $_POST['media_type'] === 'image') ? 'selected' : ''; ?>>Image</option>
                        <option value="video" <?php echo (isset($_POST['media_type']) && $_POST['media_type'] === 'video') ? 'selected' : ''; ?>>Video</option>
                    </select>
                </div>

                <div>
                    <label for="media_file" class="block text-sm font-medium text-gray-700 mb-2">Upload Media File</label>
                    <input type="file" id="media_file" name="media_file" accept="image/*,video/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           onchange="previewMedia(this)">
                    <p class="text-xs text-gray-500 mt-1">Max file size: 10MB. For images: JPEG, PNG, GIF, WebP. For videos: MP4, MOV, AVI, WebM.</p>
                </div>
            </div>

            <div id="urlSection" class="hidden">
                <label for="media_url" class="block text-sm font-medium text-gray-700 mb-2">Or provide Media URL</label>
                <input type="url" id="media_url" name="media_url"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                       placeholder="https://example.com/image.jpg"
                       value="<?php echo isset($_POST['media_url']) ? htmlspecialchars($_POST['media_url']) : ''; ?>">
            </div>

            <!-- Preview Section -->
            <div id="previewSection" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                    <h3 id="previewTitle" class="font-semibold text-lg mb-2"></h3>
                    <p id="previewContent" class="text-gray-600 mb-3"></p>
                    <div id="previewMedia" class="mb-3"></div>
                    <p class="text-sm text-gray-500">Posted by: <?php echo htmlspecialchars($company['name']); ?></p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                <button type="button" onclick="previewPost()" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition duration-300">
                    Preview Post
                </button>
                <button type="submit" class="bg-green-500 text-white px-8 py-2 rounded-lg hover:bg-green-600 transition duration-300 font-semibold">
                    Publish Post
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewMedia(input) {
    const urlSection = document.getElementById('urlSection');
    if (input.files && input.files[0]) {
        urlSection.classList.add('hidden');
    } else {
        urlSection.classList.remove('hidden');
    }
}

function previewPost() {
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;
    const mediaFile = document.getElementById('media_file').files[0];
    const mediaUrl = document.getElementById('media_url').value;
    const mediaType = document.getElementById('media_type').value;
    
    if (title || content) {
        document.getElementById('previewTitle').textContent = title || 'No title';
        document.getElementById('previewContent').textContent = content || 'No content';
        
        const previewMedia = document.getElementById('previewMedia');
        previewMedia.innerHTML = '';
        
        if (mediaFile) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (mediaType === 'image') {
                    previewMedia.innerHTML = `<img src="${e.target.result}" alt="Preview" class="max-w-full h-auto rounded-lg max-h-64">`;
                } else {
                    previewMedia.innerHTML = `
                        <video controls class="max-w-full h-auto rounded-lg max-h-64">
                            <source src="${e.target.result}" type="${mediaFile.type}">
                            Your browser does not support the video tag.
                        </video>
                    `;
                }
            };
            reader.readAsDataURL(mediaFile);
        } else if (mediaUrl) {
            if (mediaType === 'image') {
                previewMedia.innerHTML = `<img src="${mediaUrl}" alt="Preview" class="max-w-full h-auto rounded-lg max-h-64">`;
            } else {
                previewMedia.innerHTML = `
                    <video controls class="max-w-full h-auto rounded-lg max-h-64">
                        <source src="${mediaUrl}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                `;
            }
        } else {
            previewMedia.innerHTML = '<p class="text-gray-500 text-sm">No media attached</p>';
        }
        
        document.getElementById('previewSection').classList.remove('hidden');
    }
}

// Show URL section if no file is selected initially
document.addEventListener('DOMContentLoaded', function() {
    const mediaFile = document.getElementById('media_file');
    if (!mediaFile.files.length) {
        document.getElementById('urlSection').classList.remove('hidden');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>