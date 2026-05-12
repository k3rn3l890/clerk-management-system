<?php
/**
 * Script to create the lawyers table if it doesn't exist
 * This is needed for storing lawyer-specific information
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if lawyers table exists
    $db->query("SHOW TABLES LIKE 'lawyers'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create lawyers table
        $db->query("CREATE TABLE lawyers (
            lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bar_number VARCHAR(50),
            specialization VARCHAR(100),
            law_firm VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )");
        $db->execute();
        echo "SUCCESS: Created 'lawyers' table.<br>";
    } else {
        echo "INFO: 'lawyers' table already exists.<br>";
    }
    
    echo "<br>You can now <a href='users.php'>return to the users page</a>.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
