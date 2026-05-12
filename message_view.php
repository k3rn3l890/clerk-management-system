<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if message ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid message ID.', 'danger');
    redirect('messages.php');
}

$messageId = (int)$_GET['id'];
$isSent = isset($_GET['sent']) && $_GET['sent'] == 1;
$userId = $_SESSION['user_id'];

// Initialize database
$db = new Database();

// Get message details
$db->query("SELECT m.*, 
            CONCAT(s.first_name, ' ', s.last_name) as sender_name,
            s.role as sender_role,
            CONCAT(r.first_name, ' ', r.last_name) as recipient_name,
            r.role as recipient_role
            FROM messages m 
            JOIN users s ON m.sender_id = s.user_id 
            JOIN users r ON m.receiver_id = r.user_id 
            WHERE m.message_id = :message_id");
$db->bind(':message_id', $messageId);
$message = $db->single();

if (!$message) {
    setFlashMessage('Message not found.', 'danger');
    redirect('messages.php');
}

// Check if user has permission to view this message
if ($message['sender_id'] != $userId && $message['receiver_id'] != $userId) {
    setFlashMessage('You do not have permission to view this message.', 'danger');
    redirect('messages.php');
}

// Mark message as read if recipient is viewing
if ($message['receiver_id'] == $userId && !$message['is_read']) {
    $db->query("UPDATE messages SET is_read = 1 WHERE message_id = :message_id");
    $db->bind(':message_id', $messageId);
    $db->execute();
    
    // Refresh message data
    $message['is_read'] = 1;
}

// Process reply form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $replyMessage = sanitize($_POST['reply_message']);
    
    // Validate input
    if (empty($replyMessage)) {
        $errors[] = 'Reply message is required';
    }
    
    // If no errors, send reply
    if (empty($errors)) {
        // Determine recipient (the sender of the original message)
        $receiverId = $message['sender_id'];
        
        // Create subject with Re: prefix if not already present
        $subject = $message['subject'];
        if (strpos(strtolower($subject), 're:') !== 0) {
            $subject = 'Re: ' . $subject;
        }
        
        $db->query("INSERT INTO messages (sender_id, receiver_id, subject, message, is_read, created_at) 
                    VALUES (:sender_id, :receiver_id, :subject, :message, 0, NOW())");
        $db->bind(':sender_id', $userId);
        $db->bind(':receiver_id', $receiverId);
        $db->bind(':subject', $subject);
        $db->bind(':message', $replyMessage);
        
        if ($db->execute()) {
            $success = 'Reply sent successfully';
            
            // Log the action
            logAction('Message reply sent', 'messages', $db->lastInsertId());
            
            // Create notification for recipient
            createNotification($receiverId, 'New message received', 'You have received a reply from ' . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'], 'message_view.php?id=' . $db->lastInsertId());
        } else {
            $errors[] = 'Failed to send reply';
        }
    }
}

// Delete message
if (isset($_GET['delete']) && $_GET['delete'] == 1) {
    // Check if user has permission to delete this message
    if ($message['sender_id'] == $userId || $message['receiver_id'] == $userId) {
        $db->query("DELETE FROM messages WHERE message_id = :message_id");
        $db->bind(':message_id', $messageId);
        
        if ($db->execute()) {
            setFlashMessage('Message deleted successfully.', 'success');
            redirect('messages.php');
        } else {
            setFlashMessage('Failed to delete message.', 'danger');
        }
    } else {
        setFlashMessage('You do not have permission to delete this message.', 'danger');
        redirect('messages.php');
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo $isSent ? 'Sent Message' : 'Message'; ?>
        </h1>
        <div>
            <a href="messages.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Messages
            </a>
            <?php if (!$isSent && $message['receiver_id'] == $userId): ?>
            <a href="#replyForm" class="btn btn-primary btn-sm">
                <i class="fas fa-reply"></i> Reply
            </a>
            <?php endif; ?>
            <a href="message_view.php?id=<?php echo $messageId; ?>&delete=1" class="btn btn-danger btn-sm confirm-delete">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <!-- Message Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo $message['subject']; ?></h6>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1">
                        <strong>From:</strong> <?php echo $message['sender_name']; ?> 
                        <span class="badge badge-secondary"><?php echo ucfirst($message['sender_role']); ?></span>
                    </p>
                    <p class="mb-1">
                        <strong>To:</strong> <?php echo $message['recipient_name']; ?> 
                        <span class="badge badge-secondary"><?php echo ucfirst($message['recipient_role']); ?></span>
                    </p>
                </div>
                <div class="col-md-6 text-md-right">
                    <p class="mb-1">
                        <strong>Date:</strong> <?php echo formatDateTime($message['created_at']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Status:</strong> 
                        <?php if ($message['is_read']): ?>
                            <span class="text-success">Read</span>
                        <?php else: ?>
                            <span class="text-warning">Unread</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <hr>
            
            <div class="message-content p-3">
                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
            </div>
        </div>
    </div>
    
    <?php if (!$isSent && $message['receiver_id'] == $userId): ?>
    <!-- Reply Form -->
    <div class="card shadow mb-4" id="replyForm">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Reply</h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $messageId; ?>">
                <div class="form-group">
                    <textarea class="form-control" name="reply_message" rows="6" required></textarea>
                </div>
                <button type="submit" name="send_reply" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
