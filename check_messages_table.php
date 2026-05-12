<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check messages table structure
$db->query("DESCRIBE messages");
$columns = $db->resultSet();

echo "<h2>Messages Table Structure</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . $column['Field'] . "</td>";
    echo "<td>" . $column['Type'] . "</td>";
    echo "<td>" . $column['Null'] . "</td>";
    echo "<td>" . $column['Key'] . "</td>";
    echo "<td>" . ($column['Default'] === NULL ? 'NULL' : $column['Default']) . "</td>";
    echo "<td>" . $column['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check for sample data
$db->query("SELECT * FROM messages LIMIT 5");
$messages = $db->resultSet();

echo "<h2>Sample Messages (Up to 5)</h2>";
if (empty($messages)) {
    echo "<p>No messages found in the database.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Sender ID</th><th>Recipient ID</th><th>Subject</th><th>Message</th><th>Is Read</th><th>Created At</th></tr>";
    foreach ($messages as $message) {
        echo "<tr>";
        echo "<td>" . $message['message_id'] . "</td>";
        echo "<td>" . $message['sender_id'] . "</td>";
        echo "<td>" . $message['recipient_id'] . "</td>";
        echo "<td>" . $message['subject'] . "</td>";
        echo "<td>" . substr($message['message'], 0, 50) . (strlen($message['message']) > 50 ? '...' : '') . "</td>";
        echo "<td>" . $message['is_read'] . "</td>";
        echo "<td>" . $message['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check for any errors in the messages table
echo "<h2>Testing Message Insertion</h2>";
try {
    // Start transaction
    $db->beginTransaction();
    
    // Try to insert a test message
    $db->query("INSERT INTO messages (sender_id, recipient_id, subject, message, is_read, created_at) 
                VALUES (1, 2, 'Test Subject', 'Test Message Content', 0, NOW())");
    $result = $db->execute();
    
    if ($result) {
        echo "<p style='color:green'>Test message insertion successful.</p>";
    } else {
        echo "<p style='color:red'>Test message insertion failed.</p>";
    }
    
    // Rollback the transaction to avoid adding test data
    $db->cancelTransaction();
    echo "<p>Transaction rolled back - no test data was added.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    $db->cancelTransaction();
}

// Check Bootstrap modal functionality
echo "<h2>Bootstrap Modal Functionality Check</h2>";
echo "<p>The messaging system uses Bootstrap modals. Let's check if the necessary JavaScript is included:</p>";

// Check if jQuery is included
$db->query("SELECT * FROM settings WHERE setting_name = 'system_version'");
$version = $db->single();

echo "<p>System version: " . ($version ? $version['setting_value'] : 'Unknown') . "</p>";
echo "<p>Check the following in your browser console:</p>";
echo "<ul>";
echo "<li>Is jQuery loaded? <code>typeof jQuery !== 'undefined'</code> should return true</li>";
echo "<li>Is Bootstrap loaded? <code>typeof bootstrap !== 'undefined'</code> should return true</li>";
echo "<li>Is the modal function available? <code>typeof jQuery.fn.modal !== 'undefined'</code> should return true</li>";
echo "</ul>";

// Check users table for potential recipients
$db->query("SELECT COUNT(*) as count FROM users");
$userCount = $db->single();
echo "<h2>User Count</h2>";
echo "<p>Total users in the system: " . $userCount['count'] . "</p>";

// Check if there are any admin users
$db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$adminCount = $db->single();
echo "<p>Admin users: " . $adminCount['count'] . "</p>";
?>
