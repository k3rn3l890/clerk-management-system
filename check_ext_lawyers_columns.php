<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    // Check columns in external_lawyers table
    $db->query("SHOW COLUMNS FROM external_lawyers");
    $columns = $db->resultSet();
    
    echo "Columns in external_lawyers table:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check if status column exists
    $hasStatus = false;
    foreach ($columns as $column) {
        if ($column['Field'] == 'status') {
            $hasStatus = true;
            break;
        }
    }
    
    if (!$hasStatus) {
        echo "\nStatus column does not exist. Adding it now...\n";
        $db->query("ALTER TABLE external_lawyers 
                    ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
        $db->execute();
        echo "Status column added successfully.\n";
    } else {
        echo "\nStatus column exists.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 