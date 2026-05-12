<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];

// Get inbox messages (received)
$db->query("SELECT m.*, 
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.role as sender_role
            FROM messages m 
            JOIN users u ON m.sender_id = u.user_id 
            WHERE m.receiver_id = :user_id 
            ORDER BY m.created_at DESC");
$db->bind(':user_id', $userId);
$inboxMessages = $db->resultSet();

// Get sent messages
$db->query("SELECT m.*, 
            CONCAT(u.first_name, ' ', u.last_name) as recipient_name,
            u.role as recipient_role
            FROM messages m 
            JOIN users u ON m.receiver_id = u.user_id 
            WHERE m.sender_id = :user_id 
            ORDER BY m.created_at DESC");
$db->bind(':user_id', $userId);
$sentMessages = $db->resultSet();

// Get unread message count
$db->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = :user_id AND (is_read = 0 OR is_read IS NULL)");
$db->bind(':user_id', $userId);
$unreadCount = $db->single()['count'];

// Get all users for composing new messages
$db->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name, role 
            FROM users 
            WHERE user_id != :user_id 
            ORDER BY role, full_name");
$db->bind(':user_id', $userId);
$users = $db->resultSet();

// Process form submission for new message
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipientId = (int)$_POST['recipient_id'];
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    // Validate inputs
    if (empty($recipientId)) {
        $errors[] = 'Recipient is required';
    }
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If no errors, send message
    if (empty($errors)) {
        $db->query("INSERT INTO messages (sender_id, receiver_id, subject, message, is_read, created_at) 
                    VALUES (:sender_id, :receiver_id, :subject, :message, 0, NOW())");
        $db->bind(':sender_id', $userId);
        $db->bind(':receiver_id', $recipientId);
        $db->bind(':subject', $subject);
        $db->bind(':message', $message);
        
        if ($db->execute()) {
            $success = 'Message sent successfully';
            
            // Log the action
            logAction('Message sent', 'messages', $db->lastInsertId());
            
            // Create notification for recipient
            createNotification($recipientId, 'New message received', 'You have received a new message from ' . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'], 'message_view.php?id=' . $db->lastInsertId());
        } else {
            $errors[] = 'Failed to send message';
        }
    }
}

// Mark message as read if viewing from notification
if (isset($_GET['mark_read']) && !empty($_GET['mark_read'])) {
    $messageId = (int)$_GET['mark_read'];
    
    $db->query("UPDATE messages SET is_read = 1 WHERE message_id = :message_id AND receiver_id = :user_id");
    $db->bind(':message_id', $messageId);
    $db->bind(':user_id', $userId);
    $db->execute();
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Messages</h1>
    
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
    
    <div class="row">
        <div class="col-lg-3">
            <!-- Message Navigation -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Message Center</h6>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary w-100 mb-3" data-bs-toggle="modal" data-bs-target="#composeModal">
                        <i class="fas fa-pen me-2"></i> Compose New Message
                    </button>
                    
                    <div class="list-group" id="messageTab" role="tablist">
                        <a href="#inbox" class="list-group-item list-group-item-action active" data-bs-toggle="tab" data-bs-target="#inbox" role="tab" aria-controls="inbox" aria-selected="true">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-inbox me-2"></i> Inbox
                                </div>
                                <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <a href="#sent" class="list-group-item list-group-item-action" data-bs-toggle="tab" data-bs-target="#sent" role="tab" aria-controls="sent" aria-selected="false">
                            <i class="fas fa-paper-plane me-2"></i> Sent Messages
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <!-- Message Content -->
            <div class="tab-content">
                <!-- Inbox Tab -->
                <div class="tab-pane fade show active" id="inbox">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Inbox</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($inboxMessages)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x mb-3 text-gray-300"></i>
                                    <p class="text-gray-500">Your inbox is empty</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="5%"></th>
                                                <th width="20%">Sender</th>
                                                <th width="50%">Subject</th>
                                                <th width="25%">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inboxMessages as $message): ?>
                                                <tr class="<?php echo $message['is_read'] ? '' : 'font-weight-bold bg-light'; ?>" 
                                                    onclick="window.location='message_view.php?id=<?php echo $message['message_id']; ?>';" 
                                                    style="cursor: pointer;">
                                                    <td>
                                                        <?php if (!$message['is_read']): ?>
                                                            <span class="text-primary"><i class="fas fa-circle fa-sm"></i></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $message['sender_name']; ?>
                                                        <small class="text-muted d-block"><?php echo ucfirst($message['sender_role']); ?></small>
                                                    </td>
                                                    <td><?php echo $message['subject']; ?></td>
                                                    <td><?php echo formatDateTime($message['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sent Tab -->
                <div class="tab-pane fade" id="sent">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Sent Messages</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sentMessages)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-paper-plane fa-4x mb-3 text-gray-300"></i>
                                    <p class="text-gray-500">You haven't sent any messages yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="5%"></th>
                                                <th width="20%">Recipient</th>
                                                <th width="50%">Subject</th>
                                                <th width="25%">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sentMessages as $message): ?>
                                                <tr onclick="window.location='message_view.php?id=<?php echo $message['message_id']; ?>&sent=1';" 
                                                    style="cursor: pointer;">
                                                    <td>
                                                        <?php if ($message['is_read']): ?>
                                                            <span class="text-success"><i class="fas fa-check fa-sm"></i></span>
                                                        <?php else: ?>
                                                            <span class="text-muted"><i class="fas fa-check fa-sm"></i></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $message['recipient_name']; ?>
                                                        <small class="text-muted d-block"><?php echo ucfirst($message['recipient_role']); ?></small>
                                                    </td>
                                                    <td><?php echo $message['subject']; ?></td>
                                                    <td><?php echo formatDateTime($message['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeModal" tabindex="-1" aria-labelledby="composeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="composeModalLabel">Compose New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="recipient">To:</label>
                        <select class="form-control" id="recipient" name="recipient_id" required>
                            <option value="">Select Recipient</option>
                            
                            <?php if (!empty($users)): ?>
                                <?php
                                $currentRole = '';
                                foreach ($users as $user):
                                    if ($currentRole !== $user['role']):
                                        if ($currentRole !== ''):
                                            echo '</optgroup>';
                                        endif;
                                        $currentRole = $user['role'];
                                        echo '<optgroup label="' . ucfirst($currentRole) . 's">';
                                    endif;
                                ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo $user['full_name']; ?></option>
                                <?php
                                    if (end($users) === $user):
                                        echo '</optgroup>';
                                    endif;
                                endforeach;
                                ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
