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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /online-plaza/company/dashboard.php');
    exit;
}

$postId = (int)$_GET['id'];

// Get company details
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$company = $stmt->fetch();

// Get post details and verify ownership
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND company_id = ?");
$stmt->execute([$postId, $company['id']]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: /online-plaza/company/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $media_type = $_POST['media_type'] ?? 'image';
    $remove_media = isset($_POST['remove_media']);
    
    // Validation
    if (empty($title) || empty($content)) {
        $error = 'Please fill in title and content.';
    } else {
        $media_url = $post['media_url'];
        $media_filename = $post['media_filename'];
        
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
            
            // Validate file type based on media_type selection
            if ($media_type === 'image' && !in_array($fileType, $allowedImageTypes)) {
                $error = 'Please upload a valid image file (JPEG, PNG, GIF, WebP).';
            } elseif ($media_type === 'video' && !in_array($fileType, $allowedVideoTypes)) {
                $error = 'Please upload a valid video file (MP4, MOV, AVI, WebM).';
            } else {
                // Delete old file if exists
                if ($media_filename && file_exists('../uploads/posts/' . $media_filename)) {
                    unlink('../uploads/posts/' . $media_filename);
                }
                
                // Generate unique filename
                $media_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file['name']);
                $uploadPath = $uploadDir . $media_filename;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $media_url = '/online-plaza/uploads/posts/' . $media_filename;
                } else {
                    $error = 'Failed to upload file. Please try again.';
                }
            }
        } elseif ($remove_media) {
            // Remove existing media
            if ($media_filename && file_exists('../uploads/posts/' . $media_filename)) {
                unlink('../uploads/posts/' . $media_filename);
            }
            $media_url = '';
            $media_filename = '';
        } elseif (!empty($_POST['media_url'])) {
            // Use provided URL
            $media_url = trim($_POST['media_url']);
            // If switching from file to URL, delete the file
            if ($media_filename && file_exists('../uploads/posts/' . $media_filename)) {
                unlink('../uploads/posts/' . $media_filename);
            }
            $media_filename = '';
        }
        
        if (empty($error)) {
            // Update post
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, media_url = ?, media_filename = ?, media_type = ? WHERE id = ?");
            
            if ($stmt->execute([$title, $content, $media_url, $media_filename, $media_type, $postId])) {
                $success = 'Post updated successfully!';
                // Refresh post data
                $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch();
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
            <h1 class="text-2xl font-bold text-gray-900">Edit Post</h1>
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
                       value="<?php echo htmlspecialchars($post['title']); ?>">
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                <textarea id="content" name="content" required rows="8"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"><?php echo htmlspecialchars($post['content']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="media_type" class="block text-sm font-medium text-gray-700 mb-2">Media Type</label>
                    <select id="media_type" name="media_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="image" <?php echo $post['media_type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                        <option value="video" <?php echo $post['media_type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                    </select>
                </div>

                <div>
                    <label for="media_file" class="block text-sm font-medium text-gray-700 mb-2">Upload New Media File</label>
                    <input type="file" id="media_file" name="media_file" accept="image/*,video/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Max file size: 10MB</p>
                </div>
            </div>

            <div>
                <label for="media_url" class="block text-sm font-medium text-gray-700 mb-2">Or provide Media URL</label>
                <input type="url" id="media_url" name="media_url"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                       value="<?php echo htmlspecialchars($post['media_url']); ?>">
            </div>

            <!-- Current Media -->
            <?php if ($post['media_url']): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Media</label>
                    <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                        <?php if ($post['media_type'] === 'image'): ?>
                            <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Current media" class="max-w-full h-auto rounded-lg max-h-64">
                        <?php else: ?>
                            <video controls class="max-w-full h-auto rounded-lg max-h-64">
                                <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php endif; ?>
                        <div class="mt-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="remove_media" class="rounded border-gray-300 text-green-500 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-600">Remove current media</span>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                <a href="/online-plaza/posts/view.php?id=<?php echo $postId; ?>" class="text-gray-600 hover:text-gray-800 font-medium">
                    Cancel
                </a>
                <button type="submit" class="bg-green-500 text-white px-8 py-2 rounded-lg hover:bg-green-600 transition duration-300 font-semibold">
                    Update Post
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>