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

// Get posts
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_shares WHERE post_id = p.id) as share_count
    FROM posts p 
    WHERE p.company_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$company['id']]);
$posts = $stmt->fetchAll();

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $postId = (int)$_POST['post_id'];
    
    // Verify post belongs to company
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND company_id = ?");
    $stmt->execute([$postId, $company['id']]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        header("Location: /online-plaza/company/posts.php?deleted=1");
        exit;
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Manage Posts</h1>
            <p class="text-gray-600">Manage your company posts and updates</p>
        </div>
        <a href="/online-plaza/posts/create.php" class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition duration-300">
            <i class="fas fa-plus mr-2"></i>
            Create New Post
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            Post deleted successfully.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($posts): ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($posts as $post): ?>
                    <div class="p-6 hover:bg-gray-50 transition duration-300">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3"><?php echo substr(htmlspecialchars($post['content']), 0, 200); ?>...</p>
                                
                                <div class="flex items-center text-sm text-gray-500 space-x-4 mb-3">
                                    <span class="flex items-center">
                                        <i class="fas fa-heart mr-1"></i>
                                        <?php echo $post['like_count']; ?> likes
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-comment mr-1"></i>
                                        <?php echo $post['comment_count']; ?> comments
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-share mr-1"></i>
                                        <?php echo $post['share_count']; ?> shares
                                    </span>
                                    <span>
                                        <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($post['media_url']): ?>
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-paperclip mr-1"></i>
                                        <?php echo ucfirst($post['media_type']); ?> attached
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex space-x-2 ml-4">
                                <a href="/online-plaza/posts/view.php?id=<?php echo $post['id']; ?>" class="text-blue-600 hover:text-blue-800 p-2" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="/online-plaza/posts/edit.php?id=<?php echo $post['id']; ?>" class="text-green-600 hover:text-green-800 p-2" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="delete_post" class="text-red-600 hover:text-red-800 p-2" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-newspaper text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No Posts Yet</h3>
                <p class="text-gray-500 mb-6">Start by creating your first post to engage with customers.</p>
                <a href="/online-plaza/posts/create.php" class="bg-green-500 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition duration-300">
                    <i class="fas fa-plus mr-2"></i>
                    Create Your First Post
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>