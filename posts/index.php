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
    $userLikes = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-4xl mx-auto bg-black min-h-screen relative overflow-y-auto custom-scrollbar" style="max-height: 950px;">
    <?php if ($posts): ?>
        <div class="pt-20 pb-4 relative">
            <!-- Navigation Arrows -->
            <div class="fixed right-8 top-1/2 transform -translate-y-1/2 z-30 flex flex-col space-y-4">
                <!-- Up Arrow -->
                <button id="prevPost" class="w-12 h-12 bg-black/70 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/30 hover:bg-white/30 transition-all duration-300 opacity-70 hover:opacity-100 arrow-btn">
                    <i class="fas fa-chevron-up text-white text-xl"></i>
                </button>
                
                <!-- Down Arrow -->
                <button id="nextPost" class="w-12 h-12 bg-black/70 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/30 hover:bg-white/30 transition-all duration-300 opacity-70 hover:opacity-100 arrow-btn">
                    <i class="fas fa-chevron-down text-white text-xl"></i>
                </button>
            </div>

            <?php foreach ($posts as $index => $post): ?>
                <?php
                $isLiked = in_array($post['id'], $userLikes);
                ?>
                <div class="post-container relative min-h-[80vh] md:min-h-[90vh] mb-8 bg-black rounded-xl overflow-hidden border border-gray-800 shadow-lg" data-post-index="<?php echo $index; ?>">
                    <!-- Media Container -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <?php if ($post['media_url']): ?>
                            <?php if ($post['media_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>"
                                    class="w-full h-full object-cover">
                            <?php else: ?>
                                <video class="w-full h-full object-cover" controls playsinline autoplay muted loop>
                                    <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-gray-900 to-black flex items-center justify-center">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-video text-4xl mb-4"></i>
                                    <p>No media available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Action Buttons -->
                    <div class="absolute right-4 top-1/2 transform -translate-y-1/2 flex flex-col items-center space-y-6 z-20">
                        <!-- Like Button -->
                        <div class="flex flex-col items-center">
                            <button class="w-12 h-12 bg-black/50 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/20 hover:bg-white/20 transition-all duration-300 like-btn"
                                data-post-id="<?php echo $post['id']; ?>"
                                data-liked="<?php echo $isLiked ? 'true' : 'false'; ?>">
                                <i class="<?php echo $isLiked ? 'fas text-red-500' : 'far'; ?> fa-heart text-xl like-icon"></i>
                            </button>
                            <span class="text-white text-xs font-semibold mt-1 like-count"><?php echo $post['like_count']; ?></span>
                        </div>

                        <!-- Comment Button -->
                        <div class="flex flex-col items-center">
                            <button class="w-12 h-12 bg-black/50 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/20 hover:bg-white/20 transition-all duration-300 comment-btn"
                                data-post-id="<?php echo $post['id']; ?>">
                                <i class="fas fa-comment text-white text-xl"></i>
                            </button>
                            <span class="text-white text-xs font-semibold mt-1 comment-count"><?php echo $post['comment_count']; ?></span>
                        </div>

                        <!-- Share Button -->
                        <div class="flex flex-col items-center">
                            <button class="w-12 h-12 bg-black/50 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/20 hover:bg-white/20 transition-all duration-300 share-btn"
                                data-post-id="<?php echo $post['id']; ?>">
                                <i class="fas fa-share text-white text-xl"></i>
                            </button>
                            <span class="text-white text-xs font-semibold mt-1 share-count">
                                <?php echo $post['share_count']; ?>
                            </span>
                        </div>

                        <!-- More Options -->
                        <button class="w-10 h-10 bg-black/50 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/20 hover:bg-white/20 transition-all duration-300">
                            <i class="fas fa-ellipsis-h text-white text-lg"></i>
                        </button>
                    </div>

                    <!-- Bottom Info Section -->
                    <div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-black/90 to-transparent z-10">
                        <!-- Company Info -->
                        <div class="flex items-center mb-4">
                            <?php if ($post['company_logo']): ?>
                                <img src="<?php echo htmlspecialchars($post['company_logo']); ?>" alt="<?php echo htmlspecialchars($post['company_name']); ?>"
                                    class="w-10 h-10 rounded-full object-cover border-2 border-white mr-3">
                            <?php else: ?>
                                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white text-sm font-bold mr-3">
                                    <?php echo strtoupper(substr($post['company_name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <a href="/online-plaza/company/index.php?id=<?php echo $post['company_id']; ?>" class="text-white font-semibold text-base hover:underline block">
                                    <?php echo htmlspecialchars($post['company_name']); ?>
                                </a>
                                <button class="mt-1 px-4 py-1 bg-white/20 text-white text-sm rounded-full hover:bg-white/30 transition-all duration-300">
                                    Follow
                                </button>
                            </div>
                        </div>

                        <!-- Post Content -->
                        <div class="mb-4">
                            <p class="text-white text-base leading-relaxed line-clamp-3">
                                <?php echo htmlspecialchars($post['content']); ?>
                            </p>
                        </div>

                        <!-- Audio/Music and Timestamp -->
                        <div class="flex items-center justify-between text-sm text-gray-300">
                            <div class="flex items-center">
                                <i class="fas fa-music mr-2"></i>
                                <span>Original Sound - <?php echo htmlspecialchars($post['company_name']); ?></span>
                            </div>
                            <span class="text-gray-400">
                                <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="flex items-center justify-center min-h-screen text-white pt-20">
            <div class="text-center max-w-md mx-auto px-4">
                <i class="fas fa-video text-6xl text-gray-500 mb-6"></i>
                <h3 class="text-2xl font-semibold text-gray-400 mb-4">No Posts Yet</h3>
                <p class="text-gray-500 text-lg mb-6">Check back later for updates from our vendors</p>
                <button class="px-6 py-3 bg-green-500 text-white rounded-full hover:bg-green-600 transition-colors duration-300 font-semibold">
                    Explore Companies
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Comment Modal -->
<div id="commentModal" class="fixed inset-0 bg-black z-50 hidden transform transition-transform duration-300 ease-in-out translate-y-full">
    <div class="flex flex-col h-full bg-white rounded-t-3xl overflow-hidden max-w-2xl mx-auto">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <button class="text-gray-500 text-lg close-comment-modal hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="text-xl font-semibold">Comments</h3>
            <div class="w-6"></div> <!-- Spacer for balance -->
        </div>

        <!-- Comments List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6" id="commentsList">
            <!-- Comments will be loaded here -->
        </div>

        <!-- Comment Input -->
        <div class="p-6 border-t border-gray-200 bg-white">
            <div class="flex space-x-4">
                <input type="text"
                    placeholder="Add a comment..."
                    class="flex-1 px-6 py-4 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:bg-white transition-all duration-300 comment-input text-base"
                    id="commentInput">
                <button class="text-green-500 font-semibold px-6 hover:text-green-600 transition-colors duration-300 post-comment-btn text-base">
                    Post
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Responsive container */
    .max-w-4xl {
        max-width: 896px;
    }

    /* Better laptop layout */
    @media (min-width: 768px) {
        .min-h-\[90vh\] {
            min-height: 90vh;
        }

        .relative.min-h-\[80vh\] {
            margin: 0 auto 2rem;
            max-width: 800px;
        }
    }

    /* Line clamp utility */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .line-clamp-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Smooth transitions */
    * {
        transition: all 0.2s ease-in-out;
    }

    /* Like animation */
    @keyframes likeAnimation {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.3);
        }

        100% {
            transform: scale(1);
        }
    }

    .like-animation {
        animation: likeAnimation 0.4s ease-in-out;
    }

    /* Modal animation */
    .modal-open {
        transform: translateY(0) !important;
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

    /* Custom scrollbar for webkit */
    ::-webkit-scrollbar {
        width: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #1f2937;
    }

    ::-webkit-scrollbar-thumb {
        background: #4b5563;
        border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #6b7280;
    }

    /* Arrow button styles */
    .arrow-btn {
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .arrow-btn:hover {
        transform: scale(1.1);
    }

    /* Hide arrows on mobile */
    @media (max-width: 768px) {
        .fixed.right-8 {
            display: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentPostId = null;
        let currentPostIndex = 0;
        const postContainers = document.querySelectorAll('.post-container');
        const totalPosts = postContainers.length;

        // Navigation arrow functionality
        const prevPostBtn = document.getElementById('prevPost');
        const nextPostBtn = document.getElementById('nextPost');

        // Update arrow states
        function updateArrowStates() {
            if (prevPostBtn) {
                prevPostBtn.disabled = currentPostIndex === 0;
                prevPostBtn.style.opacity = currentPostIndex === 0 ? '0.3' : '0.7';
            }
            
            if (nextPostBtn) {
                nextPostBtn.disabled = currentPostIndex === totalPosts - 1;
                nextPostBtn.style.opacity = currentPostIndex === totalPosts - 1 ? '0.3' : '0.7';
            }
        }

        // Navigate to post
        function navigateToPost(index) {
            if (index < 0 || index >= totalPosts) return;
            
            currentPostIndex = index;
            const targetPost = postContainers[index];
            
            // Smooth scroll to the post
            targetPost.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            updateArrowStates();
        }

        // Previous post
        if (prevPostBtn) {
            prevPostBtn.addEventListener('click', function() {
                if (currentPostIndex > 0) {
                    navigateToPost(currentPostIndex - 1);
                }
            });
        }

        // Next post
        if (nextPostBtn) {
            nextPostBtn.addEventListener('click', function() {
                if (currentPostIndex < totalPosts - 1) {
                    navigateToPost(currentPostIndex + 1);
                }
            });
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                e.preventDefault();
                if (currentPostIndex > 0) {
                    navigateToPost(currentPostIndex - 1);
                }
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                e.preventDefault();
                if (currentPostIndex < totalPosts - 1) {
                    navigateToPost(currentPostIndex + 1);
                }
            }
        });

        // Track current post based on scroll position
        function updateCurrentPostIndex() {
            const scrollPosition = window.scrollY + 100; // Offset for better detection
            
            for (let i = 0; i < postContainers.length; i++) {
                const post = postContainers[i];
                const postTop = post.offsetTop;
                const postBottom = postTop + post.offsetHeight;
                
                if (scrollPosition >= postTop && scrollPosition < postBottom) {
                    currentPostIndex = i;
                    updateArrowStates();
                    break;
                }
            }
        }

        // Initialize arrow states
        if (totalPosts > 0) {
            updateArrowStates();
            
            // Update on scroll
            window.addEventListener('scroll', updateCurrentPostIndex);
            
            // Also update on load
            updateCurrentPostIndex();
        }

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

        // Close modal when clicking outside
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

        // Fixed Like functionality
        const likeBtns = document.querySelectorAll('.like-btn');
        likeBtns.forEach(btn => {
            btn.addEventListener('click', async function() {
                const postId = this.dataset.postId;
                const likeIcon = this.querySelector('.like-icon');
                const likeCount = this.parentElement.querySelector('.like-count');
                const isLiked = this.dataset.liked === 'true';

                console.log('Like button clicked - Post:', postId, 'Current state:', isLiked);

                // Visual feedback
                likeIcon.classList.add('like-animation');
                this.style.pointerEvents = 'none'; // Prevent double clicks

                try {
                    const formData = new URLSearchParams();
                    formData.append('post_id', postId);

                    const response = await fetch('/online-plaza/posts/api/like.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });

                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Non-JSON response from like.php:', text.substring(0, 200));
                        throw new Error('Server returned an invalid response');
                    }

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('Like response:', data);

                    if (data.success) {
                        // Update UI based on server response
                        if (data.liked) {
                            likeIcon.classList.replace('far', 'fas');
                            likeIcon.classList.add('text-red-500');
                            this.dataset.liked = 'true';
                        } else {
                            likeIcon.classList.replace('fas', 'far');
                            likeIcon.classList.remove('text-red-500');
                            this.dataset.liked = 'false';
                        }

                        // Update like count
                        likeCount.textContent = data.like_count;

                        // Show success message
                        showNotification(data.liked ? 'Post liked!' : 'Post unliked!', 'success');
                    } else {
                        throw new Error(data.message || 'Like action failed');
                    }
                } catch (error) {
                    console.error('Like error:', error);
                    showNotification('Error: ' + error.message, 'error');

                    // Revert visual state on error
                    if (isLiked) {
                        likeIcon.classList.replace('far', 'fas');
                        likeIcon.classList.add('text-red-500');
                    } else {
                        likeIcon.classList.replace('fas', 'far');
                        likeIcon.classList.remove('text-red-500');
                    }
                } finally {
                    setTimeout(() => {
                        likeIcon.classList.remove('like-animation');
                        this.style.pointerEvents = 'auto';
                    }, 400);
                }
            });
        });

        // Improved Share functionality
        const shareBtns = document.querySelectorAll('.share-btn');
        shareBtns.forEach(btn => {
            btn.addEventListener('click', async function() {
                const postContainer = this.closest('.relative');
                const likeBtn = postContainer.querySelector('.like-btn');
                const postId = likeBtn ? likeBtn.dataset.postId : this.dataset.postId;
                const shareCountElement = this.parentElement.querySelector('span');

                if (!postId) {
                    showNotification('Error: Cannot identify post to share', 'error');
                    return;
                }

                console.log('Sharing post:', postId);

                // Visual feedback
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin text-white text-xl"></i>';
                this.disabled = true;

                try {
                    const formData = new URLSearchParams();
                    formData.append('post_id', postId);

                    const response = await fetch('/online-plaza/posts/api/share.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });

                    let data;
                    try {
                        data = await response.json();
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        throw new Error('Invalid response from server');
                    }

                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status} ${response.statusText}`);
                    }

                    if (data.success) {
                        // Update share count
                        if (shareCountElement) {
                            shareCountElement.textContent = data.share_count;
                        }

                        showNotification(data.message, 'success');

                        // Try native share API
                        if (navigator.share) {
                            try {
                                await navigator.share({
                                    title: 'Check this out!',
                                    text: 'Found this interesting post',
                                    url: window.location.href
                                });
                            } catch (shareError) {
                                console.log('Native share canceled or failed:', shareError);
                            }
                        }
                    } else {
                        throw new Error(data.message || 'Failed to share post');
                    }

                } catch (error) {
                    console.error('Share error details:', error);
                    showNotification('Share failed: ' + error.message, 'error');
                } finally {
                    // Reset button state
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            });
        });

        function showNotification(message, type = 'success') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());

            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-20 right-4 p-4 rounded-lg text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
            notification.textContent = message;

            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.className = 'ml-4 text-white hover:text-gray-200';
            closeBtn.onclick = () => notification.remove();
            notification.appendChild(closeBtn);

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        function openCommentModal(postId) {
            loadComments(postId);
            commentModal.classList.remove('hidden');
            setTimeout(() => {
                commentModal.classList.add('modal-open');
            }, 50);
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            commentModal.classList.remove('modal-open');
            setTimeout(() => {
                commentModal.classList.add('hidden');
            }, 300);
            document.body.style.overflow = 'auto';
            commentInput.value = '';
        }

        function loadComments(postId) {
            // Show loading state
            commentsList.innerHTML = `
            <div class="flex justify-center items-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500"></div>
            </div>
        `;

            // Fetch actual comments from API
            fetch(`/online-plaza/posts/api/get_comments.php?post_id=${postId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.comments && data.comments.length > 0) {
                        commentsList.innerHTML = data.comments.map(comment => `
                        <div class="flex space-x-3 animate-fade-in">
                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                ${(comment.username || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div class="flex-1">
                                <div class="bg-gray-100 rounded-2xl px-4 py-2">
                                    <p class="font-semibold text-sm">${comment.username || 'User'}</p>
                                    <p class="text-gray-700">${comment.comment}</p>
                                </div>
                                <div class="flex space-x-4 text-xs text-gray-500 mt-1 px-1">
                                    <span>${formatTime(comment.created_at)}</span>
                                    <button class="hover:text-gray-700">Like</button>
                                    <button class="hover:text-gray-700">Reply</button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    } else {
                        commentsList.innerHTML = `
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-comments text-3xl mb-2"></i>
                            <p>No comments yet</p>
                            <p class="text-sm">Be the first to comment!</p>
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsList.innerHTML = `
                    <div class="text-center text-red-500 py-8">
                        <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                        <p>Failed to load comments</p>
                        <p class="text-sm">Please try again later</p>
                        <p class="text-xs mt-2">Error: ${error.message}</p>
                    </div>
                `;
                });
        }

        function postComment() {
            const comment = commentInput.value.trim();
            if (!comment) return;

            // Show loading state
            const originalText = postCommentBtn.innerHTML;
            postCommentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            postCommentBtn.disabled = true;

            // Send comment to API
            fetch('/online-plaza/posts/api/comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${currentPostId}&comment=${encodeURIComponent(comment)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Add new comment to the list
                        const commentHTML = `
                    <div class="flex space-x-3 animate-fade-in">
                        <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                            ${(data.comment.username || 'U').charAt(0).toUpperCase()}
                        </div>
                        <div class="flex-1">
                            <div class="bg-gray-100 rounded-2xl px-4 py-2">
                                <p class="font-semibold text-sm">${data.comment.username || 'You'}</p>
                                <p class="text-gray-700">${comment}</p>
                            </div>
                            <div class="flex space-x-4 text-xs text-gray-500 mt-1 px-1">
                                <span>Just now</span>
                                <button class="hover:text-gray-700">Like</button>
                                <button class="hover:text-gray-700">Reply</button>
                            </div>
                        </div>
                    </div>
                `;

                        if (commentsList.querySelector('.text-center')) {
                            commentsList.innerHTML = commentHTML;
                        } else {
                            commentsList.insertAdjacentHTML('afterbegin', commentHTML);
                        }

                        // Update comment count
                        const commentCount = document.querySelector(`[data-post-id="${currentPostId}"]`).parentElement.querySelector('.comment-count');
                        if (commentCount) {
                            commentCount.textContent = parseInt(commentCount.textContent) + 1;
                        }

                        // Clear input
                        commentInput.value = '';

                        // Show success notification
                        showNotification('Comment posted successfully!', 'success');
                    } else {
                        showNotification('Failed to post comment: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error posting comment:', error);
                    showNotification('An error occurred while posting the comment: ' + error.message, 'error');
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