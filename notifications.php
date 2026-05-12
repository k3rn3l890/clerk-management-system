<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];

// Mark a specific notification as read if ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $notificationId = (int)$_GET['id'];
    
    // Get the notification details
    $db->query("SELECT * FROM notifications WHERE notification_id = :notification_id AND user_id = :user_id");
    $db->bind(':notification_id', $notificationId);
    $db->bind(':user_id', $userId);
    $notification = $db->single();
    
    // Mark as read if found
    if ($notification) {
        $db->query("UPDATE notifications SET is_read = 1 WHERE notification_id = :notification_id");
        $db->bind(':notification_id', $notificationId);
        $db->execute();
    }
}

// Mark all notifications as read if requested
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
    $db->bind(':user_id', $userId);
    
    if ($db->execute()) {
        setFlashMessage('All notifications marked as read.', 'success');
    }
    
    redirect('notifications.php');
}

// Delete notification if requested
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $notificationId = (int)$_GET['delete'];
    
    $db->query("DELETE FROM notifications WHERE notification_id = :notification_id AND user_id = :user_id");
    $db->bind(':notification_id', $notificationId);
    $db->bind(':user_id', $userId);
    
    if ($db->execute()) {
        setFlashMessage('Notification deleted successfully.', 'success');
    }
    
    redirect('notifications.php');
}

// Delete all notifications if requested
if (isset($_GET['delete_all']) && $_GET['delete_all'] == 1) {
    $db->query("DELETE FROM notifications WHERE user_id = :user_id");
    $db->bind(':user_id', $userId);
    
    if ($db->execute()) {
        setFlashMessage('All notifications deleted successfully.', 'success');
    }
    
    redirect('notifications.php');
}

// Get notifications
$db->query("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
$db->bind(':user_id', $userId);
$notifications = $db->resultSet();

// Get unread notification count
$db->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
$db->bind(':user_id', $userId);
$unreadCount = $db->single()['count'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Notifications</h1>
        <div>
            <?php if (!empty($notifications)): ?>
                <?php if ($unreadCount > 0): ?>
                <a href="notifications.php?mark_all_read=1" class="btn btn-primary btn-sm">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </a>
                <?php endif; ?>
                <a href="notifications.php?delete_all=1" class="btn btn-danger btn-sm confirm-delete">
                    <i class="fas fa-trash"></i> Delete All
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Notifications List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Your Notifications
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['id']) && !empty($_GET['id']) && isset($notification)): ?>
                <!-- Single Notification View -->
                <div class="mb-3">
                    <a href="notifications.php" class="btn btn-secondary btn-sm mb-3">
                        <i class="fas fa-arrow-left"></i> Back to All Notifications
                    </a>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo $notification['title']; ?></h5>
                        <small class="text-muted"><?php echo formatDateTime($notification['created_at']); ?></small>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                    </div>
                    <div class="card-footer">
                        <a href="notifications.php?delete=<?php echo $notification['notification_id']; ?>" class="btn btn-danger btn-sm confirm-delete">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            <?php else: ?>
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-4x mb-3 text-gray-300"></i>
                    <p class="text-gray-500">You don't have any notifications</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        // Determine notification icon based on link
                        $icon = 'fas fa-bell';
                        // Check if link key exists before using it
                        if (isset($notification['link']) && !empty($notification['link'])) {
                            if (strpos($notification['link'], 'case_') !== false) {
                                $icon = 'fas fa-gavel';
                            } elseif (strpos($notification['link'], 'hearing_') !== false) {
                                $icon = 'fas fa-calendar-alt';
                            } elseif (strpos($notification['link'], 'document_') !== false) {
                                $icon = 'fas fa-file-alt';
                            } elseif (strpos($notification['link'], 'message_') !== false) {
                                $icon = 'fas fa-envelope';
                            } elseif (strpos($notification['link'], 'payment_') !== false) {
                                $icon = 'fas fa-money-bill-wave';
                            }
                        }
                        ?>
                        
                        <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="notification-icon mr-3">
                                        <i class="<?php echo $icon; ?> fa-lg <?php echo $notification['is_read'] ? 'text-gray-500' : 'text-primary'; ?>"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 <?php echo $notification['is_read'] ? '' : 'font-weight-bold'; ?>">
                                            <?php echo $notification['title']; ?>
                                        </h6>
                                        <p class="mb-1"><?php echo $notification['message']; ?></p>
                                        <small class="text-muted">
                                            <?php echo timeAgo($notification['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <?php if (!empty($notification['link'])): ?>
                                    <a href="<?php echo $notification['link']; ?><?php echo $notification['is_read'] ? '' : '&mark_read=' . $notification['notification_id']; ?>" class="btn btn-sm btn-outline-primary mr-2">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="notifications.php?delete=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-danger confirm-delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
