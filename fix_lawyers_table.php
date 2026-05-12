<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    // Check if lawyers table exists
    $db->query("SELECT COUNT(*) as table_exists FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = 'lawyers'");
    $lawyersExists = $db->single()['table_exists'] > 0;
    
    if ($lawyersExists) {
        // Check if status column exists
        $db->query("SHOW COLUMNS FROM lawyers LIKE 'status'");
        $statusExists = $db->single();
        
        if (!$statusExists) {
            echo "Adding status column to lawyers table...\n";
            $db->query("ALTER TABLE lawyers 
                        ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            $db->execute();
            echo "Status column added to lawyers table.\n";
        } else {
            echo "Status column already exists in lawyers table.\n";
        }
    } else {
        echo "Lawyers table doesn't exist. Please run create_lawyers_table.php first.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?> 