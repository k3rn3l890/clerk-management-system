<?php
require_once 'includes/header.php';

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Get all notifications with message_view.php URLs in the message
$db->query("SELECT * FROM notifications WHERE message LIKE '% - message_view.php?id=%'");
$notifications = $db->resultSet();

$fixedCount = 0;

// Process each notification
foreach ($notifications as $notification) {
    // Extract the message and link parts
    $messageParts = explode(' - message_view.php?id=', $notification['message']);
    
    if (count($messageParts) == 2) {
        $cleanMessage = $messageParts[0];
        $messageId = trim($messageParts[1]);
        
        // Update the notification with clean message and proper link
        $db->query("UPDATE notifications SET 
                    message = :message,
                    link = :link
                    WHERE notification_id = :id");
        $db->bind(':message', $cleanMessage);
        $db->bind(':link', 'message_view.php?id=' . $messageId);
        $db->bind(':id', $notification['notification_id']);
        
        if ($db->execute()) {
            $fixedCount++;
        }
    }
}

// Set success message
setFlashMessage("Fixed $fixedCount notification messages by moving URLs to the link field.", 'success');
redirect('notifications.php');
?>
