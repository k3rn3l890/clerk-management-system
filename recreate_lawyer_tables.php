<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "Starting table recreation process...\n";
    
    // Drop existing tables if they exist
    echo "Dropping party_lawyers table if exists...\n";
    $db->query("DROP TABLE IF EXISTS party_lawyers");
    $db->execute();
    
    echo "Dropping external_lawyers table if exists...\n";
    $db->query("DROP TABLE IF EXISTS external_lawyers");
    $db->execute();
    
    echo "Dropping lawyers table if exists...\n";
    $db->query("DROP TABLE IF EXISTS lawyers");
    $db->execute();
    
    // Create lawyers table
    echo "Creating lawyers table...\n";
    $db->query("CREATE TABLE lawyers (
        lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bar_number VARCHAR(50),
        specialization VARCHAR(100),
        law_firm VARCHAR(100),
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");
    $db->execute();
    
    // Create external_lawyers table
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
    
    // Create party_lawyers table
    echo "Creating party_lawyers table...\n";
    $db->query("CREATE TABLE party_lawyers (
        party_lawyer_id INT PRIMARY KEY AUTO_INCREMENT,
        case_party_id INT NOT NULL,
        lawyer_id INT NULL,
        external_lawyer_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (case_party_id) REFERENCES case_parties(party_id) ON DELETE CASCADE,
        FOREIGN KEY (lawyer_id) REFERENCES lawyers(lawyer_id) ON DELETE SET NULL,
        FOREIGN KEY (external_lawyer_id) REFERENCES external_lawyers(external_lawyer_id) ON DELETE SET NULL,
        CONSTRAINT chk_lawyer_type CHECK (
            (lawyer_id IS NOT NULL AND external_lawyer_id IS NULL) OR
            (lawyer_id IS NULL AND external_lawyer_id IS NOT NULL) OR
            (lawyer_id IS NULL AND external_lawyer_id IS NULL)
        )
    )");
    $db->execute();
    
    echo "Tables recreated successfully.\n";
    
} catch (PDOException $e) {
    echo "Error recreating tables: " . $e->getMessage() . "\n";
}
?> 