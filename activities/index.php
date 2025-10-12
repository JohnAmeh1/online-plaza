<?php
require_once '../includes/config.php';

if (!isLoggedIn()) {
    header('Location: /online-plaza/auth/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Get activities
$stmt = $pdo->prepare("
    SELECT a.*, 
           u.username as actor_username,
           CASE 
               WHEN a.reference_type = 'post' THEN (SELECT title FROM posts WHERE id = a.reference_id)
               WHEN a.reference_type = 'product' THEN (SELECT name FROM products WHERE id = a.reference_id)
               ELSE NULL
           END as reference_title
    FROM activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE a.user_id = ? 
    ORDER BY a.created_at DESC
");
$stmt->execute([$currentUser['id']]);
$activities = $stmt->fetchAll();

// Mark all as read
$pdo->prepare("UPDATE activities SET is_read = TRUE WHERE user_id = ?")->execute([$currentUser['id']]);
?>

<?php require_once '../includes/header.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Activities & Notifications</h1>
            <span class="text-gray-500"><?php echo count($activities); ?> activities</span>
        </div>

        <?php if ($activities): ?>
            <div class="space-y-4">
                <?php foreach ($activities as $activity): ?>
                    <div class="flex items-start p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-300 <?php echo !$activity['is_read'] ? 'bg-blue-50 border-blue-200' : ''; ?>">
                        <div class="flex-shrink-0 mr-4">
                            <?php
                            $icon = '';
                            $color = '';
                            switch ($activity['activity_type']) {
                                case 'like':
                                    $icon = 'fas fa-heart';
                                    $color = 'text-red-500';
                                    break;
                                case 'comment':
                                    $icon = 'fas fa-comment';
                                    $color = 'text-blue-500';
                                    break;
                                case 'share':
                                    $icon = 'fas fa-share';
                                    $color = 'text-green-500';
                                    break;
                                case 'review':
                                    $icon = 'fas fa-star';
                                    $color = 'text-yellow-500';
                                    break;
                                default:
                                    $icon = 'fas fa-bell';
                                    $color = 'text-gray-500';
                            }
                            ?>
                            <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center border <?php echo $color; ?>">
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                        </div>
                        
                        <div class="flex-1">
                            <p class="text-gray-800">
                                <?php
                                $message = '';
                                switch ($activity['activity_type']) {
                                    case 'like':
                                        $message = "<strong>{$activity['actor_username']}</strong> liked your post";
                                        break;
                                    case 'comment':
                                        $message = "<strong>{$activity['actor_username']}</strong> commented on your post";
                                        break;
                                    case 'share':
                                        $message = "<strong>{$activity['actor_username']}</strong> shared your post";
                                        break;
                                    case 'review':
                                        $message = "<strong>{$activity['actor_username']}</strong> reviewed your product";
                                        break;
                                    default:
                                        $message = "New activity from <strong>{$activity['actor_username']}</strong>";
                                }
                                
                                if ($activity['reference_title']) {
                                    $message .= ": \"{$activity['reference_title']}\"";
                                }
                                
                                echo $message;
                                ?>
                            </p>
                            <p class="text-gray-500 text-sm mt-1">
                                <?php echo date('F j, Y \a\t g:i A', strtotime($activity['created_at'])); ?>
                            </p>
                        </div>
                        
                        <?php if (!$activity['is_read']): ?>
                            <div class="flex-shrink-0">
                                <span class="inline-block w-3 h-3 bg-blue-500 rounded-full"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-bell text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No activities yet</h3>
                <p class="text-gray-500">Your activities and notifications will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>