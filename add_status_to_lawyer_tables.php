<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "Starting safe migration...\n";
    
    // Check if status column exists in lawyers table
    $db->query("SHOW COLUMNS FROM lawyers LIKE 'status'");
    $statusExists = $db->single();
    
    if (!$statusExists) {
        echo "Adding status column to lawyers table...\n";
        $db->query("ALTER TABLE lawyers ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
        $db->execute();
        echo "Status column added to lawyers table.\n";
    } else {
        echo "Status column already exists in lawyers table.\n";
    }
    
    // Check if status column exists in external_lawyers table
    $db->query("SHOW COLUMNS FROM external_lawyers LIKE 'status'");
    $statusExists = $db->single();
    
    if (!$statusExists) {
        echo "Adding status column to external_lawyers table...\n";
        $db->query("ALTER TABLE external_lawyers ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
        $db->execute();
        echo "Status column added to external_lawyers table.\n";
    } else {
        echo "Status column already exists in external_lawyers table.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?> 