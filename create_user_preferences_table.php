<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check if user_preferences table already exists
$db->query("SHOW TABLES LIKE 'user_preferences'");
$tableExists = $db->single();

if (!$tableExists) {
    // Create user_preferences table
    $db->query("CREATE TABLE user_preferences (
        preference_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        notification_email TINYINT(1) NOT NULL DEFAULT 1,
        notification_sms TINYINT(1) NOT NULL DEFAULT 0,
        theme VARCHAR(50) NOT NULL DEFAULT 'light',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (preference_id),
        KEY user_id (user_id),
        CONSTRAINT user_preferences_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    if ($db->execute()) {
        echo "<p>User preferences table created successfully.</p>";
    } else {
        echo "<p>Error creating user preferences table.</p>";
    }
} else {
    echo "<p>User preferences table already exists.</p>";
}

// Redirect to profile page after 3 seconds
echo "<p>Redirecting to profile page in 3 seconds...</p>";
echo "<script>setTimeout(function() { window.location.href = 'profile.php'; }, 3000);</script>";
?>
