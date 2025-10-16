<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$currentUser = getCurrentUser();
if (!isLoggedIn() || !$currentUser || $currentUser['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Get activities with pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

try {
    // Build conditions
    $conditions = [];
    $params = [];
    
    if (!empty($type)) {
        $conditions[] = "a.activity_type = ?";
        $params[] = $type;
    }
    
    if (!empty($user_id)) {
        $conditions[] = "a.user_id = ?";
        $params[] = $user_id;
    }
    
    $whereClause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM activities a 
                 LEFT JOIN users u ON a.user_id = u.id 
                 $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalActivities = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalActivities / $limit);

    // Get activities with user info
    $sql = "
        SELECT a.*, 
               u.first_name, 
               u.last_name, 
               u.username,
               u.user_type
        FROM activities a 
        LEFT JOIN users u ON a.user_id = u.id 
        $whereClause
        ORDER BY a.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get activity types for filter
    $typeStmt = $pdo->query("SELECT DISTINCT activity_type FROM activities WHERE activity_type IS NOT NULL ORDER BY activity_type");
    $activityTypes = $typeStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get users for filter
    $userStmt = $pdo->query("SELECT id, first_name, last_name, username FROM users ORDER BY first_name, last_name");
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error loading activities: " . $e->getMessage();
}

$pageTitle = "Activities Log - Martly Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/martly.svg">
</head>
<body class="bg-gray-50">
<div class="min-h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="ml-0 lg:ml-64">
        <?php include 'includes/topbar.php'; ?>
        <main class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Activities Log</h1>
                    <p class="text-gray-600 mt-2">Monitor all system activities and user actions</p>
                </div>
                <!-- <button class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl hover:from-blue-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105" onclick="exportActivities()">
                    <i class="fas fa-download mr-2"></i>
                    Export Log
                </button> -->
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                        <select name="type" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Types</option>
                            <?php foreach ($activityTypes as $activityType): ?>
                                <option value="<?php echo htmlspecialchars($activityType); ?>" <?php echo $type === $activityType ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $activityType)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                        <select name="user_id" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user_id === $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-300 w-full">
                            Filter
                        </button>
                        <?php if (!empty($type) || !empty($user_id)): ?>
                            <a href="activities.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-300 whitespace-nowrap">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Activities Timeline -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Activities</h3>
                    <p class="text-gray-600 text-sm mt-1">Total <?php echo number_format($totalActivities); ?> activities logged</p>
                </div>

                <div class="divide-y divide-gray-100">
                    <?php if (empty($activities)): ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-history text-4xl text-gray-300 mb-3"></i>
                            <p>No activities found</p>
                            <?php if (!empty($type) || !empty($user_id)): ?>
                                <p class="text-sm mt-1">Try adjusting your filters</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="p-6 hover:bg-gray-50 transition-colors duration-300">
                                <div class="flex items-start space-x-4">
                                    <!-- User Avatar -->
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($activity['first_name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Activity Content -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                </span>
                                                <span class="text-sm text-gray-500">@<?php echo htmlspecialchars($activity['username']); ?></span>
                                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full 
                                                    <?php echo $activity['user_type'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo ucfirst($activity['user_type']); ?>
                                                </span>
                                            </div>
                                            <span class="text-sm text-gray-500">
                                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-800 mb-2">
                                            <?php echo htmlspecialchars($activity['activity_type']); ?>
                                        </p>
                                        
                                        <?php if (!empty($activity['description'])): ?>
                                            <p class="text-gray-600 text-sm bg-gray-50 p-3 rounded-lg">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                                <span>IP: <?php echo htmlspecialchars($activity['ip_address']); ?></span>
                                                <?php if (!empty($activity['user_agent'])): ?>
                                                    <span>•</span>
                                                    <span class="truncate" title="<?php echo htmlspecialchars($activity['user_agent']); ?>">
                                                        Browser: <?php echo htmlspecialchars(substr($activity['user_agent'], 0, 50)); ?>...
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Activity Icon -->
                                    <div class="flex-shrink-0">
                                        <?php
                                        $activityIcons = [
                                            'login' => 'fas fa-sign-in-alt text-green-500',
                                            'logout' => 'fas fa-sign-out-alt text-red-500',
                                            'create' => 'fas fa-plus-circle text-blue-500',
                                            'update' => 'fas fa-edit text-yellow-500',
                                            'delete' => 'fas fa-trash text-red-500',
                                            'register' => 'fas fa-user-plus text-green-500',
                                            'purchase' => 'fas fa-shopping-cart text-purple-500',
                                            'view' => 'fas fa-eye text-gray-500'
                                        ];
                                        $iconClass = $activityIcons[strtolower($activity['activity_type'])] ?? 'fas fa-bell text-blue-500';
                                        ?>
                                        <i class="<?php echo $iconClass; ?> text-lg"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="bg-white px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                            <span class="font-medium"><?php echo min($offset + $limit, $totalActivities); ?></span> of
                            <span class="font-medium"><?php echo $totalActivities; ?></span> activities
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($type) ? '&type=' . urlencode($type) : ''; ?><?php echo !empty($user_id) ? '&user_id=' . $user_id : ''; ?>" 
                                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-300">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($type) ? '&type=' . urlencode($type) : ''; ?><?php echo !empty($user_id) ? '&user_id=' . $user_id : ''; ?>" 
                                   class="px-4 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors duration-300">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($type) ? '&type=' . urlencode($type) : ''; ?><?php echo !empty($user_id) ? '&user_id=' . $user_id : ''; ?>" 
                                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-300">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
// Mobile sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const sidebar = document.querySelector('.fixed.inset-y-0');
    sidebar.classList.toggle('-translate-x-full');
});

// Export activities function
function exportActivities() {
    const params = new URLSearchParams(window.location.search);
    window.open('export_activities.php?' + params.toString(), '_blank');
}
</script>
</body>
</html>