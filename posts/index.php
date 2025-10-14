<?php
require_once '../includes/config.php';

// Build query
$query = "
    SELECT p.*, c.name as company_name, c.id as company_id, c.logo as company_logo,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM post_shares WHERE post_id = p.id) as share_count
    FROM posts p 
    JOIN companies c ON p.company_id = c.id 
    ORDER BY p.created_at DESC
";

// Get posts
$stmt = $pdo->prepare($query);
$stmt->execute();
$posts = $stmt->fetchAll();

// Check if user has liked each post
$userLikes = [];
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];

    $stmt = $pdo->prepare("SELECT post_id FROM post_likes WHERE user_id = ?");
    $stmt->execute([$userId]);
    $likedPosts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $userLikes = array_flip($likedPosts);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-green-50">
    <div class="max-w-3xl mx-auto px-4 py-6">
        <?php if ($posts): ?>
            <?php foreach ($posts as $post): ?>
                <?php $isLiked = isLoggedIn() && isset($userLikes[$post['id']]); ?>

                <!-- Post Card -->
                <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 mb-6 overflow-hidden border border-gray-200" data-post-id="<?php echo $post['id']; ?>">
                    <!-- Post Header -->
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <?php if ($post['company_logo']): ?>
                                <img src="<?php echo htmlspecialchars($post['company_logo']); ?>"
                                    alt="<?php echo htmlspecialchars($post['company_name']); ?>"
                                    class="w-12 h-12 rounded-full object-cover border-2 border-blue-300 shadow-md">
                            <?php else: ?>
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-md">
                                    <?php echo strtoupper(substr($post['company_name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>

                            <div>
                                <a href="/online-plaza/company/index.php?id=<?php echo $post['company_id']; ?>"
                                    class="font-bold text-gray-800 hover:text-blue-600 transition-colors">
                                    <?php echo htmlspecialchars($post['company_name']); ?>
                                </a>
                                <p class="text-xs text-gray-500 flex items-center">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <button class="text-gray-400 hover:text-gray-600 transition-colors p-2 hover:bg-gray-100 rounded-full">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </div>

                    <!-- Post Content -->
                    <div class="px-4 pb-3">
                        <p class="text-gray-700 text-base leading-relaxed whitespace-pre-wrap">
                            <?php echo htmlspecialchars($post['content']); ?>
                        </p>
                    </div>

                    <!-- Post Media -->
                    <?php if ($post['media_url']): ?>
                        <div class="w-full bg-gray-900">
                            <?php if ($post['media_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($post['media_url']); ?>"
                                    alt="<?php echo htmlspecialchars($post['title']); ?>"
                                    class="w-full object-contain max-h-[600px]">
                            <?php else: ?>
                                <video class="w-full object-contain max-h-[600px]" controls playsinline>
                                    <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Engagement Stats -->
                    <div class="px-4 py-3 flex items-center justify-between text-sm text-gray-600 border-t border-gray-100">
                        <div class="flex items-center space-x-2">
                            <div class="flex -space-x-1">
                                <div class="w-5 h-5 bg-gradient-to-br from-green-400 to-emerald-400 rounded-full flex items-center justify-center border-2 border-white">
                                    <i class="fas fa-heart text-white text-xs"></i>
                                </div>
                            </div>
                            <span class="like-count-text hover:text-blue-600 cursor-pointer transition-colors">
                                <span class="like-count" data-post-id="<?php echo $post['id']; ?>"><?php echo $post['like_count']; ?></span>
                                <?php echo $post['like_count'] == 1 ? 'like' : 'likes'; ?>
                            </span>
                        </div>

                        <div class="flex items-center space-x-4">
                            <span class="hover:text-blue-600 cursor-pointer transition-colors">
                                <span class="comment-count" data-post-id="<?php echo $post['id']; ?>"><?php echo $post['comment_count']; ?></span>
                                <?php echo $post['comment_count'] == 1 ? 'comment' : 'comments'; ?>
                            </span>
                            <span class="hover:text-blue-600 cursor-pointer transition-colors">
                                <span class="share-count" data-post-id="<?php echo $post['id']; ?>"><?php echo $post['share_count']; ?></span>
                                <?php echo $post['share_count'] == 1 ? 'share' : 'shares'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="px-4 py-2 border-t border-gray-200 flex items-center justify-around">
                        <button class="like-btn flex-1 flex items-center justify-center space-x-2 py-3 rounded-lg hover:bg-gray-100 transition-all duration-300 group"
                            data-post-id="<?php echo $post['id']; ?>"
                            data-liked="<?php echo $isLiked ? 'true' : 'false'; ?>">
                            <i class="<?php echo $isLiked ? 'fas text-green-500' : 'far text-gray-600'; ?> fa-heart text-xl like-icon transition-all duration-300 group-hover:scale-110"></i>
                            <span class="font-semibold <?php echo $isLiked ? 'text-green-500' : 'text-gray-600'; ?> like-text group-hover:text-green-500 transition-colors">
                                Like
                            </span>
                        </button>

                        <button class="comment-btn flex-1 flex items-center justify-center space-x-2 py-3 rounded-lg hover:bg-gray-100 transition-all duration-300 group"
                            data-post-id="<?php echo $post['id']; ?>">
                            <i class="far fa-comment text-xl text-gray-600 group-hover:text-blue-500 transition-all duration-300 group-hover:scale-110"></i>
                            <span class="font-semibold text-gray-600 group-hover:text-blue-500 transition-colors">
                                Comment
                            </span>
                        </button>

                        <button class="share-btn flex-1 flex items-center justify-center space-x-2 py-3 rounded-lg hover:bg-gray-100 transition-all duration-300 group"
                            data-post-id="<?php echo $post['id']; ?>">
                            <i class="fas fa-share text-xl text-gray-600 group-hover:text-yellow-600 transition-all duration-300 group-hover:scale-110"></i>
                            <span class="font-semibold text-gray-600 group-hover:text-yellow-600 transition-colors">
                                Share
                            </span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="flex items-center justify-center min-h-[60vh]">
                <div class="text-center max-w-md mx-auto px-4">
                    <div class="w-24 h-24 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl">
                        <i class="fas fa-newspaper text-5xl text-white"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-4">No Posts Yet</h3>
                    <p class="text-gray-600 text-lg mb-8 leading-relaxed">Check back later for updates from our vendors</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Comment Modal -->
<div id="commentModal" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-800">Comments</h3>
            <button class="close-comment-modal text-gray-500 hover:text-gray-700 hover:bg-gray-100 w-10 h-10 rounded-full transition-all duration-300 flex items-center justify-center">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Comments List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar" id="commentsList">
            <!-- Comments will be loaded here -->
        </div>

        <!-- Comment Input -->
        <div class="p-6 border-t border-gray-200 bg-gray-50">
            <div class="flex space-x-3">
                <input type="text"
                    placeholder="Write a comment..."
                    class="flex-1 px-4 py-3 bg-white text-gray-800 placeholder-gray-500 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all duration-300 comment-input border border-gray-200"
                    id="commentInput">
                <button class="px-6 py-3 bg-gradient-to-r from-blue-400 to-cyan-400 text-white font-bold rounded-full hover:from-blue-500 hover:to-cyan-500 transition-all duration-300 post-comment-btn shadow-lg hover:shadow-xl hover:scale-105">
                    Post
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(229, 231, 235, 0.5);
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: linear-gradient(to bottom, #60a5fa, #34d399);
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(to bottom, #3b82f6, #10b981);
    }

    /* Like animation */
    @keyframes likeAnimation {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.3);
        }
    }

    .like-animation {
        animation: likeAnimation 0.4s ease-in-out;
    }

    /* Fade in animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fadeIn 0.3s ease-out;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentPostId = null;

        // Comment modal functionality
        const commentModal = document.getElementById('commentModal');
        const commentBtns = document.querySelectorAll('.comment-btn');
        const closeCommentModal = document.querySelector('.close-comment-modal');
        const postCommentBtn = document.querySelector('.post-comment-btn');
        const commentInput = document.getElementById('commentInput');
        const commentsList = document.getElementById('commentsList');

        // Open comment modal
        commentBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                currentPostId = this.dataset.postId;
                openCommentModal(currentPostId);
            });
        });

        // Close comment modal
        closeCommentModal.addEventListener('click', closeModal);
        commentModal.addEventListener('click', function(e) {
            if (e.target === commentModal) {
                closeModal();
            }
        });

        // Post comment
        postCommentBtn.addEventListener('click', postComment);
        commentInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                postComment();
            }
        });

        // // Like functionality - Fixed to work with new structure
        // const likeBtns = document.querySelectorAll('.like-btn');
        // likeBtns.forEach(btn => {
        //     btn.addEventListener('click', async function() {
        //         const postId = this.dataset.postId;
        //         const isLiked = this.dataset.liked === 'true';

        //         // Get elements within the post card
        //         const postCard = this.closest('[data-post-id]');
        //         const icon = this.querySelector('.like-icon');
        //         const likeText = this.querySelector('.like-text');
        //         const likeCountElement = postCard.querySelector(`.like-count[data-post-id="${postId}"]`);

        //         if (!icon || !likeText || !likeCountElement) {
        //             console.error('Like button elements not found');
        //             return;
        //         }

        //         // Add animation
        //         icon.classList.add('like-animation');
        //         setTimeout(() => icon.classList.remove('like-animation'), 400);

        //         try {
        //             const response = await fetch('/online-plaza/posts/api/like.php', {
        //                 method: 'POST',
        //                 headers: {
        //                     'Content-Type': 'application/x-www-form-urlencoded',
        //                 },
        //                 body: `post_id=${postId}`
        //             });

        //             const data = await response.json();

        //             if (data.success) {
        //                 if (data.liked) {
        //                     // User liked the post
        //                     icon.classList.remove('far', 'text-gray-600');
        //                     icon.classList.add('fas', 'text-green-500');
        //                     likeText.classList.remove('text-gray-600');
        //                     likeText.classList.add('text-green-500');
        //                     this.dataset.liked = 'true';
        //                 } else {
        //                     // User unliked the post
        //                     icon.classList.remove('fas', 'text-green-500');
        //                     icon.classList.add('far', 'text-gray-600');
        //                     likeText.classList.remove('text-green-500');
        //                     likeText.classList.add('text-gray-600');
        //                     this.dataset.liked = 'false';
        //                 }

        //                 // Update like count
        //                 likeCountElement.textContent = data.like_count;

        //                 showNotification(data.liked ? 'Post liked!' : 'Post unliked', 'success');
        //             } else {
        //                 showNotification(data.message || 'Failed to like post', 'error');
        //             }
        //         } catch (error) {
        //             console.error('Error liking post:', error);
        //             showNotification('Failed to like post', 'error');
        //         }
        //     });
        // });

        // Share functionality
        const shareBtns = document.querySelectorAll('.share-btn');
        shareBtns.forEach(btn => {
            btn.addEventListener('click', async function() {
                const postId = this.dataset.postId;
                const postCard = this.closest('[data-post-id]');
                const shareCountElement = postCard.querySelector(`.share-count[data-post-id="${postId}"]`);

                try {
                    const response = await fetch('/online-plaza/posts/api/share.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `post_id=${postId}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        if (shareCountElement) {
                            shareCountElement.textContent = data.share_count;
                        }
                        showNotification(data.message, 'success');

                        if (navigator.share) {
                            try {
                                await navigator.share({
                                    title: 'Check this out!',
                                    text: 'Found this interesting post',
                                    url: window.location.href
                                });
                            } catch (shareError) {
                                console.log('Native share canceled');
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error sharing post:', error);
                    showNotification('Failed to share post', 'error');
                }
            });
        });

        function showNotification(message, type = 'success') {
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());

            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-24 right-6 p-4 rounded-2xl text-white z-50 shadow-2xl backdrop-blur-xl border-2 ${
            type === 'success' 
                ? 'bg-gradient-to-r from-green-400 to-emerald-400 border-green-300' 
                : 'bg-gradient-to-r from-red-400 to-rose-400 border-red-300'
        }`;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }

        function openCommentModal(postId) {
            loadComments(postId);
            commentModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            commentModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            commentInput.value = '';
        }

        function loadComments(postId) {
            commentsList.innerHTML = `
            <div class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-10 w-10 border-4 border-blue-400 border-t-transparent"></div>
            </div>
        `;

            fetch(`/online-plaza/posts/api/get_comments.php?post_id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.comments && data.comments.length > 0) {
                        commentsList.innerHTML = data.comments.map(comment => `
                        <div class="flex space-x-3 animate-fade-in">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg flex-shrink-0">
                                ${(comment.username || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1">
                                <div class="bg-gray-100 rounded-2xl px-4 py-3">
                                    <p class="font-bold text-sm text-gray-800 mb-1">${comment.username || 'User'}</p>
                                    <p class="text-gray-700">${comment.comment}</p>
                                </div>
                                <div class="flex space-x-4 text-xs text-gray-500 mt-2 px-1">
                                    <span class="font-medium">${formatTime(comment.created_at)}</span>
                                    <button class="hover:text-blue-500 transition-colors font-semibold">Like</button>
                                    <button class="hover:text-blue-500 transition-colors font-semibold">Reply</button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    } else {
                        commentsList.innerHTML = `
                        <div class="text-center text-gray-500 py-12">
                            <div class="w-20 h-20 bg-gradient-to-br from-blue-100 to-cyan-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-comments text-4xl text-blue-400"></i>
                            </div>
                            <p class="text-lg font-semibold text-gray-700 mb-2">No comments yet</p>
                            <p class="text-sm">Be the first to comment!</p>
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsList.innerHTML = `
                    <div class="text-center text-red-500 py-12">
                        <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
                        <p class="text-lg font-semibold">Failed to load comments</p>
                    </div>
                `;
                });
        }

        function postComment() {
            const comment = commentInput.value.trim();
            if (!comment) return;

            const originalText = postCommentBtn.innerHTML;
            postCommentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            postCommentBtn.disabled = true;

            fetch('/online-plaza/posts/api/comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${currentPostId}&comment=${encodeURIComponent(comment)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const commentHTML = `
                    <div class="flex space-x-3 animate-fade-in">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg flex-shrink-0">
                            ${(data.comment.username || 'U').charAt(0).toUpperCase()}
                        </div>
                        <div class="flex-1">
                            <div class="bg-gray-100 rounded-2xl px-4 py-3">
                                <p class="font-bold text-sm text-gray-800 mb-1">${data.comment.username || 'You'}</p>
                                <p class="text-gray-700">${comment}</p>
                            </div>
                            <div class="flex space-x-4 text-xs text-gray-500 mt-2 px-1">
                                <span class="font-medium">Just now</span>
                                <button class="hover:text-blue-500 transition-colors font-semibold">Like</button>
                                <button class="hover:text-blue-500 transition-colors font-semibold">Reply</button>
                            </div>
                        </div>
                    </div>
                `;

                        if (commentsList.querySelector('.text-center')) {
                            commentsList.innerHTML = commentHTML;
                        } else {
                            commentsList.insertAdjacentHTML('afterbegin', commentHTML);
                        }

                        // Update comment counts
                        const commentCountElement = document.querySelector(`.comment-count[data-post-id="${currentPostId}"]`);
                        if (commentCountElement && data.comment_count !== undefined) {
                            commentCountElement.textContent = data.comment_count;
                        }

                        commentInput.value = '';
                        showNotification('Comment posted successfully!', 'success');
                    } else {
                        showNotification('Failed to post comment', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error posting comment:', error);
                    showNotification('An error occurred', 'error');
                })
                .finally(() => {
                    postCommentBtn.innerHTML = originalText;
                    postCommentBtn.disabled = false;
                });
        }

        function formatTime(timestamp) {
            if (!timestamp) return 'Just now';

            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
            return Math.floor(diff / 86400000) + 'd ago';
        }

        // Keyboard escape to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !commentModal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>