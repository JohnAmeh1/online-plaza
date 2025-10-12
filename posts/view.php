<?php
require_once '../includes/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /online-plaza/posts/index.php');
    exit;
}

$postId = (int)$_GET['id'];

// Handle like action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_post'])) {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to like posts';
    } else {
        require_once 'api/like.php'; // This will handle the like logic
        // The like.php will redirect back here after processing
    }
}

// Get post details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as company_name, c.id as company_id, u.username,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_shares WHERE post_id = p.id) as share_count
    FROM posts p 
    JOIN companies c ON p.company_id = c.id 
    JOIN users u ON c.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: /online-plaza/posts/index.php');
    exit;
}

// Check if current user liked this post
$userLiked = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $_SESSION['user_id']]);
    $userLiked = (bool)$stmt->fetch();
}

// Get comments
$stmt = $pdo->prepare("
    SELECT pc.*, u.username 
    FROM post_comments pc 
    JOIN users u ON pc.user_id = u.id 
    WHERE pc.post_id = ? 
    ORDER BY pc.created_at DESC
");
$stmt->execute([$postId]);
$comments = $stmt->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Post -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <?php if ($post['media_url']): ?>
            <div class="w-full">
                <?php if ($post['media_type'] === 'image'): ?>
                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-64 md:h-96 object-cover">
                <?php else: ?>
                    <video class="w-full h-64 md:h-96 object-cover" controls>
                        <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <a href="/online-plaza/company/index.php?id=<?php echo $post['company_id']; ?>" class="text-green-600 hover:text-green-800 font-semibold">
                    <?php echo htmlspecialchars($post['company_name']); ?>
                </a>
                <span class="text-gray-500 text-sm"><?php echo date('F j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="prose max-w-none mb-6">
                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>

            <!-- Interaction Buttons -->
            <div class="flex items-center justify-between border-t border-b border-gray-200 py-3">
                <!-- In the posts loop, replace the like section with: -->
                <div class="flex items-center space-x-6 text-sm text-gray-500">
                    <form method="POST" action="/online-plaza/posts/api/like.php" class="inline">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <input type="hidden" name="like_post" value="1">
                        <button type="submit" class="flex items-center hover:text-red-600 transition duration-200">
                            <i class="fas fa-heart mr-1"></i>
                            <?php echo $post['like_count']; ?>
                        </button>
                    </form>

                    <span class="flex items-center">
                        <i class="fas fa-comment mr-1"></i>
                        <?php echo $post['comment_count']; ?>
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-share mr-1"></i>
                        <?php echo $post['share_count']; ?>
                    </span>
                </div>

                <?php if (isLoggedIn() && getCurrentUser()['user_type'] === 'vendor'): ?>
                    <div class="flex space-x-2">
                        <a href="/online-plaza/posts/edit.php?id=<?php echo $postId; ?>" class="text-green-600 hover:text-green-800 text-sm">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </a>
                        <button onclick="deletePost(<?php echo $postId; ?>)" class="text-red-600 hover:text-red-800 text-sm">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    v<!-- Loading Popup -->
    <div id="loadingPopup" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 max-w-sm mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Posting</h3>
                <button onclick="dismissPopup()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex items-center space-x-3">
                <i class="fas fa-spinner fa-spin text-green-500"></i>
                <p class="text-gray-700">Processing your action...</p>
            </div>
        </div>
    </div>
    <!-- Comments Section -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Comments</h2>

        <?php if (isLoggedIn()): ?>
            <form class="comment-form mb-6" data-post-id="<?php echo $postId; ?>">
                <div class="flex space-x-4">
                    <div class="flex-1">
                        <textarea class="comment-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Write a comment..."
                            rows="3"
                            data-post-id="<?php echo $postId; ?>"></textarea>
                    </div>
                    <div>
                        <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-md hover:bg-green-600 transition duration-300 h-full">
                            Post
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-gray-50 p-4 rounded-lg mb-6 text-center">
                <p class="text-gray-600 mb-2">Please <a href="/online-plaza/auth/login.php" class="text-green-600 hover:text-green-800">login</a> to leave a comment.</p>
            </div>
        <?php endif; ?>

        <div class="comments-list space-y-4" data-post-id="<?php echo $postId; ?>">
            <?php if ($comments): ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold mr-2">
                                    <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                                </div>
                                <span class="font-semibold"><?php echo htmlspecialchars($comment['username']); ?></span>
                            </div>
                            <span class="text-gray-500 text-sm"><?php echo date('M j, g:i A', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <p class="text-gray-700"><?php echo htmlspecialchars($comment['comment']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No comments yet. Be the first to comment!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function deletePost(postId) {
        if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
            fetch('/online-plaza/posts/api/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '/online-plaza/company/dashboard.php';
                    } else {
                        alert('Error deleting post: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the post.');
                });
        }
    }

    // Show loading popup
    function showLoadingPopup() {
        const popup = document.getElementById('loadingPopup');
        if (popup) {
            popup.classList.remove('hidden');
        }
    }

    // Dismiss popup
    function dismissPopup() {
        const popup = document.getElementById('loadingPopup');
        if (popup) {
            popup.classList.add('hidden');
        }
    }

    // Auto-dismiss popup after 5 seconds (safety measure)
    function autoDismissPopup() {
        setTimeout(() => {
            dismissPopup();
        }, 5000);
    }

    // Add smooth transition for like button and show popup
    document.addEventListener('DOMContentLoaded', function() {
        const likeForms = document.querySelectorAll('form[method="POST"]');
        likeForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    // Show loading popup
                    showLoadingPopup();
                    autoDismissPopup();

                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>';

                    // Add a small delay to show the loading state
                    setTimeout(() => {
                        this.submit();
                    }, 100);
                }
            });
        });

        // Add event listener for comment form
        const commentForm = document.querySelector('.comment-form');
        if (commentForm) {
            commentForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const button = this.querySelector('button[type="submit"]');
                const textarea = this.querySelector('.comment-input');
                const postId = this.dataset.postId;
                const comment = textarea.value.trim();

                if (!comment) {
                    alert('Please enter a comment');
                    return;
                }

                if (button) {
                    // Show loading popup
                    showLoadingPopup();

                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

                    // Submit comment via AJAX
                    const formData = new FormData();
                    formData.append('post_id', postId);
                    formData.append('comment', comment);

                    fetch('/online-plaza/posts/api/comment.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            dismissPopup();
                            button.disabled = false;
                            button.innerHTML = 'Post';

                            if (data.success) {
                                // Add new comment to the list
                                const commentsList = document.querySelector('.comments-list');
                                const newComment = document.createElement('div');
                                newComment.className = 'comment bg-gray-50 p-4 rounded-lg';
                                newComment.innerHTML = `
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold mr-2">
                                        ${data.comment.username.charAt(0).toUpperCase()}
                                    </div>
                                    <span class="font-semibold">${data.comment.username}</span>
                                </div>
                                <span class="text-gray-500 text-sm">Just now</span>
                            </div>
                            <p class="text-gray-700">${data.comment.comment}</p>
                        `;

                                // If there's a "no comments" message, remove it
                                const noCommentsMsg = commentsList.querySelector('p.text-gray-500');
                                if (noCommentsMsg) {
                                    noCommentsMsg.remove();
                                }

                                commentsList.insertBefore(newComment, commentsList.firstChild);

                                // Update comment count
                                const commentCount = document.querySelector('.comment-count');
                                if (commentCount) {
                                    commentCount.textContent = parseInt(commentCount.textContent) + 1;
                                }

                                // Clear textarea
                                textarea.value = '';
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            dismissPopup();
                            button.disabled = false;
                            button.innerHTML = 'Post';
                            console.error('Error:', error);
                            alert('An error occurred while posting the comment.');
                        });
                }
            });
        }

        // Close popup when clicking outside
        const popup = document.getElementById('loadingPopup');
        if (popup) {
            popup.addEventListener('click', function(e) {
                if (e.target === this) {
                    dismissPopup();
                }
            });
        }

        // Close popup with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dismissPopup();
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>