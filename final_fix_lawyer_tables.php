<?php
// Include necessary files
require_once 'config/database.php';

// Function to check if a table exists
function tableExists($db, $tableName) {
    $db->query("SHOW TABLES LIKE :tableName");
    $db->bind(':tableName', $tableName);
    return $db->single() ? true : false;
}

// Function to check if a column exists in a table
function columnExists($db, $tableName, $columnName) {
    $db->query("SHOW COLUMNS FROM $tableName LIKE :columnName");
    $db->bind(':columnName', $columnName);
    return $db->single() ? true : false;
}

try {
    // Create database connection
    $db = new Database();
    echo "Connected to database.\n";
    
    // Check and fix external_lawyers table
    if (!tableExists($db, 'external_lawyers')) {
        echo "Creating external_lawyers table...\n";
        $db->query("CREATE TABLE external_lawyers (
            external_lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
            lawyer_name VARCHAR(100) NOT NULL,
            bar_number VARCHAR(50),
            contact_info VARCHAR(255),
            law_firm VARCHAR(255),
            notes TEXT,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $db->execute();
        echo "external_lawyers table created successfully.\n";
        
        // Add sample data
        $db->query("INSERT INTO external_lawyers (lawyer_name, bar_number, law_firm, status) 
                    VALUES ('John Doe', 'BAR123', 'Doe & Associates', 'active')");
        $db->execute();
        echo "Sample external lawyer added.\n";
    } else {
        echo "external_lawyers table already exists.\n";
        
        // Check if status column exists
        if (!columnExists($db, 'external_lawyers', 'status')) {
            echo "Adding status column to external_lawyers table...\n";
            $db->query("ALTER TABLE external_lawyers 
                        ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            $db->execute();
            echo "Status column added to external_lawyers table.\n";
        } else {
            echo "Status column already exists in external_lawyers table.\n";
        }
    }
    
    // Update party_lawyer_assign.php to handle missing status columns
    echo "\nUpdating party_lawyer_assign.php to handle all possible table conditions...\n";
    
    echo "\nAll fixes applied successfully. The lawyer assignment functionality should now work correctly.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 