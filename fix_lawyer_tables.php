<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    // First, drop the foreign key from external_lawyers table
    $db->query("ALTER TABLE external_lawyers DROP FOREIGN KEY external_lawyers_ibfk_1");
    
    // Then drop the case_id column
    $db->query("ALTER TABLE external_lawyers DROP COLUMN case_id");
    
    // Add status and updated_at columns to external_lawyers
    $db->query("ALTER TABLE external_lawyers 
                ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Add status column to lawyers table
    $db->query("ALTER TABLE lawyers 
                ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    
    echo "Tables updated successfully.\n";
    
} catch (PDOException $e) {
    echo "Error updating tables: " . $e->getMessage() . "\n";
    
    // If the error is about the foreign key not existing, continue with other changes
    if (strpos($e->getMessage(), "Can't DROP") !== false) {
        try {
            // Try to add the columns anyway
            $db->query("ALTER TABLE external_lawyers 
                       DROP COLUMN case_id,
                       ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                       ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            
            $db->query("ALTER TABLE lawyers 
                       ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            
            echo "Tables updated successfully after error recovery.\n";
        } catch (PDOException $e2) {
            echo "Error in recovery attempt: " . $e2->getMessage() . "\n";
        }
    }
}
?> 