<?php
/**
 * Script to create the judges table if it doesn't exist
 * This is needed for storing judge-specific information
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if judges table exists
    $db->query("SHOW TABLES LIKE 'judges'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create judges table
        $db->query("CREATE TABLE judges (
            judge_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            court_division VARCHAR(100),
            appointment_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )");
        $db->execute();
        echo "SUCCESS: Created 'judges' table.<br>";
    } else {
        echo "INFO: 'judges' table already exists.<br>";
    }
    
    echo "<br>You can now <a href='users.php'>return to the users page</a>.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
