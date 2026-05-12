<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    // Add status column to external_lawyers table
    $db->query("ALTER TABLE external_lawyers 
                ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                DROP FOREIGN KEY external_lawyers_ibfk_1,
                DROP COLUMN case_id,
                ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Add status column to lawyers table
    $db->query("ALTER TABLE lawyers 
                ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    
    echo "Status columns added successfully to lawyers and external_lawyers tables.\n";
    
} catch (PDOException $e) {
    echo "Error adding status columns: " . $e->getMessage() . "\n";
}
?> 