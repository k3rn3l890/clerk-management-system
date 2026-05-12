<?php
/**
 * Script to add status_reason column to users table
 * This script checks if the column exists and adds it if it doesn't
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if status_reason column exists
    $db->query("SHOW COLUMNS FROM users LIKE 'status_reason'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        // Add status_reason column to users table
        $db->query("ALTER TABLE users ADD COLUMN status_reason TEXT AFTER status");
        $db->execute();
        echo "SUCCESS: Added 'status_reason' column to users table.<br>";
    } else {
        echo "INFO: 'status_reason' column already exists in users table.<br>";
    }
    
    echo "<br>You can now <a href='users.php'>return to the users page</a>.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
