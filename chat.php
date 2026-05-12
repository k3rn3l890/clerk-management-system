<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];
$currentUserName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get conversation partner ID if provided
$partnerId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$partnerName = '';

// Process new message submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = sanitize($_POST['message']);
    $receiverId = (int)$_POST['receiver_id'];
    
    // Validate inputs
    if (empty($message)) {
        $errors[] = 'Message cannot be empty';
    }
    
    if (empty($receiverId)) {
        $errors[] = 'Recipient is required';
    }
    
    // If no errors, send message
    if (empty($errors)) {
        // Get the existing conversation subject or create a new one
        $db->query("SELECT subject FROM messages 
                    WHERE (sender_id = :user_id1 AND receiver_id = :receiver_id1) 
                    OR (sender_id = :receiver_id2 AND receiver_id = :user_id2) 
                    ORDER BY created_at DESC LIMIT 1");
        $db->bind(':user_id1', $userId);
        $db->bind(':receiver_id1', $receiverId);
        $db->bind(':receiver_id2', $receiverId);
        $db->bind(':user_id2', $userId);
        $existingConversation = $db->single();
        
        $subject = 'Conversation';
        if ($existingConversation && !empty($existingConversation['subject'])) {
            $subject = $existingConversation['subject'];
        }
        
        $db->query("INSERT INTO messages (sender_id, receiver_id, subject, message, is_read, created_at) 
                    VALUES (:sender_id, :receiver_id, :subject, :message, 0, NOW())");
        $db->bind(':sender_id', $userId);
        $db->bind(':receiver_id', $receiverId);
        $db->bind(':subject', $subject);
        $db->bind(':message', $message);
        
        if ($db->execute()) {
            $success = 'Message sent successfully';
            
            // Log the action
            logAction('Message sent', 'messages', $db->lastInsertId());
            
            // Create notification for recipient
            createNotification($receiverId, 'New message received', 'You have received a new message from ' . $currentUserName, 'chat.php?user=' . $userId);
            
            // Set success message and use JavaScript redirect instead of PHP header redirect
            $success = 'Message sent successfully';
            echo "<script>window.location.href = 'chat.php?user=$receiverId';</script>";
            exit;
        } else {
            $errors[] = 'Failed to send message';
        }
    }
}

// Get all users who have conversations with the current user
$db->query("SELECT DISTINCT 
            CASE 
                WHEN m.sender_id = :user_id1 THEN m.receiver_id
                ELSE m.sender_id
            END as contact_id,
            CASE 
                WHEN m.sender_id = :user_id2 THEN CONCAT(r.first_name, ' ', r.last_name)
                ELSE CONCAT(s.first_name, ' ', s.last_name)
            END as contact_name,
            CASE 
                WHEN m.sender_id = :user_id3 THEN r.role
                ELSE s.role
            END as contact_role,
            MAX(m.created_at) as last_message_time,
            COUNT(CASE WHEN m.is_read = 0 AND m.receiver_id = :user_id4 THEN 1 END) as unread_count
            FROM messages m
            JOIN users s ON m.sender_id = s.user_id
            JOIN users r ON m.receiver_id = r.user_id
            WHERE m.sender_id = :user_id5 OR m.receiver_id = :user_id6
            GROUP BY contact_id, contact_name, contact_role
            ORDER BY last_message_time DESC");
$db->bind(':user_id1', $userId);
$db->bind(':user_id2', $userId);
$db->bind(':user_id3', $userId);
$db->bind(':user_id4', $userId);
$db->bind(':user_id5', $userId);
$db->bind(':user_id6', $userId);
$conversations = $db->resultSet();

// If a partner is selected, get conversation messages
$messages = [];
if ($partnerId > 0) {
    // Get partner details
    $db->query("SELECT CONCAT(first_name, ' ', last_name) as full_name, role FROM users WHERE user_id = :partner_id");
    $db->bind(':partner_id', $partnerId);
    $partner = $db->single();
    
    if ($partner) {
        $partnerName = $partner['full_name'];
        
        // Get messages between current user and partner
        $db->query("SELECT m.*, 
                    CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    s.role as sender_role
                    FROM messages m 
                    JOIN users s ON m.sender_id = s.user_id 
                    WHERE (m.sender_id = :user_id1 AND m.receiver_id = :partner_id1)
                    OR (m.sender_id = :partner_id2 AND m.receiver_id = :user_id2)
                    ORDER BY m.created_at ASC");
        $db->bind(':user_id1', $userId);
        $db->bind(':partner_id1', $partnerId);
        $db->bind(':partner_id2', $partnerId);
        $db->bind(':user_id2', $userId);
        $messages = $db->resultSet();
        
        // Mark all messages from partner as read
        $db->query("UPDATE messages SET is_read = 1 
                    WHERE sender_id = :partner_id AND receiver_id = :user_id AND is_read = 0");
        $db->bind(':partner_id', $partnerId);
        $db->bind(':user_id', $userId);
        $db->execute();
    }
}

// Get all users for new conversation
$db->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name, role 
            FROM users 
            WHERE user_id != :user_id 
            ORDER BY role, full_name");
$db->bind(':user_id', $userId);
$allUsers = $db->resultSet();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Court Clerk Management System</title>
    <!-- Custom styles for this page -->
    <style>
        .chat-container {
            height: calc(100vh - 250px);
            display: flex;
        }
        .contacts-list {
            width: 300px;
            border-right: 1px solid #e3e6f0;
            overflow-y: auto;
        }
        .chat-messages {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .message-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        .message-input {
            border-top: 1px solid #e3e6f0;
            padding: 15px;
            background-color: #f8f9fc;
        }
        .contact-item {
            padding: 10px 15px;
            border-bottom: 1px solid #e3e6f0;
            cursor: pointer;
        }
        .contact-item:hover {
            background-color: #f8f9fc;
        }
        .contact-item.active {
            background-color: #4e73df;
            color: white;
        }
        .message-bubble {
            max-width: 75%;
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 10px;
            position: relative;
        }
        .message-sent {
            background-color: #4e73df;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .message-received {
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.7rem;
            color: rgba(0,0,0,0.5);
            margin-top: 5px;
            text-align: right;
        }
        .message-sent .message-time {
            color: rgba(255,255,255,0.7);
        }
        .contact-name {
            font-weight: bold;
        }
        .contact-role {
            font-size: 0.8rem;
            color: #858796;
        }
        .active .contact-role {
            color: rgba(255,255,255,0.7);
        }
        .unread-badge {
            background-color: #e74a3b;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #858796;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dddfeb;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Messages</h1>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newChatModal">
                <i class="fas fa-plus fa-sm text-white-50"></i> New Chat
            </button>
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
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card shadow mb-4">
            <div class="card-body p-0">
                <div class="chat-container">
                    <!-- Contacts List -->
                    <div class="contacts-list">
                        <?php if (empty($conversations)): ?>
                            <div class="p-3 text-center text-muted">
                                <p>No conversations yet</p>
                                <p>Start a new chat by clicking the "New Chat" button</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <div class="contact-item <?php echo ($partnerId == $conversation['contact_id']) ? 'active' : ''; ?>" 
                                     onclick="window.location.href='chat.php?user=<?php echo $conversation['contact_id']; ?>'">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="contact-name"><?php echo $conversation['contact_name']; ?></div>
                                            <div class="contact-role"><?php echo ucwords(str_replace('_', ' ', $conversation['contact_role'])); ?></div>
                                        </div>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="chat-messages">
                        <?php if ($partnerId > 0 && !empty($partnerName)): ?>
                            <!-- Chat Header -->
                            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo $partnerName; ?></h5>
                            </div>
                            
                            <!-- Message List -->
                            <div class="message-list" id="messageList">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center text-muted my-5">
                                        <p>No messages yet</p>
                                        <p>Start the conversation by sending a message</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message-bubble <?php echo ($message['sender_id'] == $userId) ? 'message-sent' : 'message-received'; ?>">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                            <div class="message-time">
                                                <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Message Input -->
                            <div class="message-input">
                                <form method="post" action="chat.php?user=<?php echo $partnerId; ?>">
                                    <div class="input-group">
                                        <input type="hidden" name="receiver_id" value="<?php echo $partnerId; ?>">
                                        <textarea class="form-control" name="message" placeholder="Type your message..." rows="2" required></textarea>
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="submit" name="send_message">
                                                <i class="fas fa-paper-plane"></i> Send
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Empty State -->
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <h4>Select a conversation</h4>
                                <p>Choose an existing conversation or start a new one</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Chat Modal -->
    <div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newChatModalLabel">Start New Conversation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="userSelect">Select User</label>
                        <select class="form-control" id="userSelect">
                            <option value="">-- Select User --</option>
                            <?php foreach ($allUsers as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo $user['full_name']; ?> (<?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="startChatBtn">Start Chat</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to bottom of message list
            const messageList = document.getElementById('messageList');
            if (messageList) {
                messageList.scrollTop = messageList.scrollHeight;
            }
            
            // Handle new chat button
            const startChatBtn = document.getElementById('startChatBtn');
            const userSelect = document.getElementById('userSelect');
            
            if (startChatBtn && userSelect) {
                startChatBtn.addEventListener('click', function() {
                    const selectedUserId = userSelect.value;
                    if (selectedUserId) {
                        window.location.href = 'chat.php?user=' + selectedUserId;
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>
