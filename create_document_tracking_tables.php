<?php
/**
 * Script to create document tracking tables for geospatial features
 */
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if document_access_logs table exists
    $db->query("SHOW TABLES LIKE 'document_access_logs'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create document_access_logs table
        $db->query("CREATE TABLE document_access_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            user_id INT NOT NULL,
            action_type ENUM('view', 'download', 'edit', 'delete') NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            location_accuracy DECIMAL(10, 2) NULL,
            location_name VARCHAR(255) NULL,
            device_type VARCHAR(50) NULL,
            access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )");
        $db->execute();
        echo "SUCCESS: Created 'document_access_logs' table.<br>";
    } else {
        echo "INFO: 'document_access_logs' table already exists.<br>";
    }
    
    // Check if case_activity_logs table exists
    $db->query("SHOW TABLES LIKE 'case_activity_logs'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create case_activity_logs table
        $db->query("CREATE TABLE case_activity_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            user_id INT NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            location_name VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )");
        $db->execute();
        echo "SUCCESS: Created 'case_activity_logs' table.<br>";
    } else {
        echo "INFO: 'case_activity_logs' table already exists.<br>";
    }
    
    // Check if document_versions table exists
    $db->query("SHOW TABLES LIKE 'document_versions'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create document_versions table to track document changes
        $db->query("CREATE TABLE document_versions (
            version_id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            version_number INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size INT NOT NULL,
            modified_by INT NOT NULL,
            modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            change_notes TEXT NULL,
            FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
            FOREIGN KEY (modified_by) REFERENCES users(user_id)
        )");
        $db->execute();
        echo "SUCCESS: Created 'document_versions' table.<br>";
    } else {
        echo "INFO: 'document_versions' table already exists.<br>";
    }
    
    // Add last_accessed_at and last_accessed_by columns to documents table if they don't exist
    $db->query("SHOW COLUMNS FROM documents LIKE 'last_accessed_at'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        $db->query("ALTER TABLE documents ADD COLUMN last_accessed_at TIMESTAMP NULL");
        $db->execute();
        echo "SUCCESS: Added 'last_accessed_at' column to documents table.<br>";
    } else {
        echo "INFO: 'last_accessed_at' column already exists in documents table.<br>";
    }
    
    $db->query("SHOW COLUMNS FROM documents LIKE 'last_accessed_by'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        $db->query("ALTER TABLE documents ADD COLUMN last_accessed_by INT NULL");
        $db->execute();
        echo "SUCCESS: Added 'last_accessed_by' column to documents table.<br>";
    } else {
        echo "INFO: 'last_accessed_by' column already exists in documents table.<br>";
    }
    
    echo "<br>Document tracking tables have been created successfully. <a href='documents.php'>Return to Documents</a>";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
