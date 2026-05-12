<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

try {
    // Create party_lawyers table
    $db->query("CREATE TABLE IF NOT EXISTS party_lawyers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_party_id INT NOT NULL,
        lawyer_id INT NULL,
        external_lawyer_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (case_party_id),
        INDEX (lawyer_id),
        INDEX (external_lawyer_id),
        FOREIGN KEY (case_party_id) REFERENCES case_parties(party_id) ON DELETE CASCADE,
        FOREIGN KEY (lawyer_id) REFERENCES lawyers(lawyer_id) ON DELETE SET NULL,
        FOREIGN KEY (external_lawyer_id) REFERENCES external_lawyers(external_lawyer_id) ON DELETE SET NULL
    )");
    
    $db->execute();
    
    echo "party_lawyers table created successfully!";
} catch (Exception $e) {
    echo "Error creating party_lawyers table: " . $e->getMessage();
    
    // If error is due to foreign key constraint, try without foreign keys
    if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
        strpos($e->getMessage(), '1005') !== false ||
        strpos($e->getMessage(), '1215') !== false) {
        try {
            echo "\n\nTrying to create table without foreign key constraints...";
            
            $db->query("CREATE TABLE IF NOT EXISTS party_lawyers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                case_party_id INT NOT NULL,
                lawyer_id INT NULL,
                external_lawyer_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (case_party_id),
                INDEX (lawyer_id),
                INDEX (external_lawyer_id)
            )");
            
            $db->execute();
            
            echo "\nparty_lawyers table created without foreign key constraints.";
            echo "\nNote: You should manually ensure referential integrity.";
        } catch (Exception $e2) {
            echo "\nError in fallback creation: " . $e2->getMessage();
        }
    }
}
?>
