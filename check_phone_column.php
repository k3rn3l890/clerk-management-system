<?php
/**
 * Script to check if phone column exists in users table
 * If it doesn't exist, it will be added
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if phone column exists in users table
    $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        // Add phone column to users table
        $db->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email");
        $db->execute();
        echo "SUCCESS: Added 'phone' column to users table.<br>";
    } else {
        echo "INFO: 'phone' column already exists in users table.<br>";
    }
    
    echo "<br>You can now <a href='users.php'>return to the users page</a>.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
