<?php
/**
 * Script to create the password_resets table if it doesn't exist
 * This is needed for the password reset functionality
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if password_resets table exists
    $db->query("SHOW TABLES LIKE 'password_resets'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create password_resets table
        $db->query("CREATE TABLE password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )");
        $db->execute();
        echo "SUCCESS: Created 'password_resets' table.<br>";
    } else {
        echo "INFO: 'password_resets' table already exists.<br>";
    }
    
    echo "<br>You can now <a href='login.php'>return to the login page</a> and use the forgot password feature.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
