<?php
/**
 * Script to fix the external_lawyers table issue
 * This script will create the external_lawyers table if it doesn't exist
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    $db = new Database();
    
    // Check if external_lawyers table exists
    $db->query("SELECT COUNT(*) as table_exists FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = 'external_lawyers'");
    $externalLawyersExists = $db->single()['table_exists'] > 0;
    
    if (!$externalLawyersExists) {
        echo "Creating external_lawyers table...\n";
        $db->query("CREATE TABLE external_lawyers (
            external_lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
            lawyer_name VARCHAR(100) NOT NULL,
            bar_number VARCHAR(50),
            contact_info VARCHAR(255),
            law_firm VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $db->execute();
        echo "external_lawyers table created successfully.\n";
    } else {
        // Check if status column exists
        $db->query("SHOW COLUMNS FROM external_lawyers LIKE 'status'");
        $statusExists = $db->single();
        
        if (!$statusExists) {
            echo "Adding status column to external_lawyers table...\n";
            $db->query("ALTER TABLE external_lawyers 
                        ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            $db->execute();
            echo "Status column added to external_lawyers table.\n";
        } else {
            echo "Status column already exists in external_lawyers table.\n";
        }
    }
    
    echo "Migration completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?>
