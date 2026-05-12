<?php
/**
 * Document Version Control System Setup Script
 * Creates tables for controlled document viewing, editing, and downloads
 */
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Add mime_type column to documents table
    $db->query("SHOW COLUMNS FROM documents LIKE 'mime_type'");
    $mimeTypeExists = $db->single();
    
    if (!$mimeTypeExists) {
        $db->query("ALTER TABLE documents ADD COLUMN mime_type VARCHAR(100) NULL");
        $db->execute();
        
        // Update mime_type for existing documents based on file extension
        $db->query("UPDATE documents SET mime_type = 
            CASE 
                WHEN file_path LIKE '%.pdf' THEN 'application/pdf'
                WHEN file_path LIKE '%.docx' THEN 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                WHEN file_path LIKE '%.doc' THEN 'application/msword'
                WHEN file_path LIKE '%.jpg' OR file_path LIKE '%.jpeg' THEN 'image/jpeg'
                WHEN file_path LIKE '%.png' THEN 'image/png'
                ELSE 'application/octet-stream'
            END
            WHERE mime_type IS NULL");
        $db->execute();
        echo "SUCCESS: Added and populated 'mime_type' column to documents table.<br>";
    } else {
        echo "INFO: 'mime_type' column already exists in documents table.<br>";
    }
    
    // Create document versions table
    $db->query("CREATE TABLE IF NOT EXISTS document_versions (
        version_id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        version_number INT NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        modified_by INT NOT NULL,
        modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        change_summary TEXT NULL,
        status ENUM('draft', 'published', 'archived') DEFAULT 'published',
        FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
        FOREIGN KEY (modified_by) REFERENCES users(user_id)
    )");
    $db->execute();
    echo "SUCCESS: Created 'document_versions' table.<br>";
    
    // Create document edits table to track specific changes
    $db->query("CREATE TABLE IF NOT EXISTS document_edits (
        edit_id INT AUTO_INCREMENT PRIMARY KEY,
        version_id INT NOT NULL,
        user_id INT NOT NULL,
        edit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        edit_type ENUM('text_change', 'format_change', 'image_change', 'annotation', 'other') NOT NULL,
        edit_location TEXT NULL,
        old_content MEDIUMTEXT NULL,
        new_content MEDIUMTEXT NULL,
        ip_address VARCHAR(45) NULL,
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        FOREIGN KEY (version_id) REFERENCES document_versions(version_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    $db->execute();
    echo "SUCCESS: Created 'document_edits' table.<br>";
    
    // Create secure document downloads table
    $db->query("CREATE TABLE IF NOT EXISTS secure_downloads (
        download_id VARCHAR(64) PRIMARY KEY,
        document_id INT NOT NULL,
        user_id INT NOT NULL,
        version_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        download_limit INT DEFAULT 1,
        download_count INT DEFAULT 0,
        watermark_text VARCHAR(255) NULL,
        encryption_key VARCHAR(255) NULL,
        ip_restriction VARCHAR(45) NULL,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (version_id) REFERENCES document_versions(version_id) ON DELETE SET NULL
    )");
    $db->execute();
    echo "SUCCESS: Created 'secure_downloads' table.<br>";
    
    // Create document annotations table
    $db->query("CREATE TABLE IF NOT EXISTS document_annotations (
        annotation_id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        version_id INT NULL,
        user_id INT NOT NULL,
        page_number INT NULL,
        position_x FLOAT NULL,
        position_y FLOAT NULL,
        width FLOAT NULL,
        height FLOAT NULL,
        content TEXT NOT NULL,
        annotation_type ENUM('note', 'highlight', 'draw', 'text') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_private BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE,
        FOREIGN KEY (version_id) REFERENCES document_versions(version_id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    $db->execute();
    echo "SUCCESS: Created 'document_annotations' table.<br>";
    
    // Add allow_download column to documents table if not exists
    $db->query("SHOW COLUMNS FROM documents LIKE 'allow_download'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        $db->query("ALTER TABLE documents ADD COLUMN allow_download BOOLEAN DEFAULT TRUE");
        $db->execute();
        echo "SUCCESS: Added 'allow_download' column to documents table.<br>";
    } else {
        echo "INFO: 'allow_download' column already exists in documents table.<br>";
    }
    
    // Migrate existing documents to the version control system
    $db->query("SELECT COUNT(*) as count FROM document_versions");
    $result = $db->single();
    
    if ($result['count'] == 0) {
        // No versions exist yet, let's migrate existing documents
        $db->query("INSERT INTO document_versions 
                    (document_id, version_number, file_path, file_size, mime_type, modified_by, modified_at, status)
                    SELECT 
                        document_id, 
                        1, 
                        file_path, 
                        file_size, 
                        mime_type, 
                        uploaded_by, 
                        created_at, 
                        'published'
                    FROM documents");
        $db->execute();
        $migratedCount = $db->rowCount();
        echo "SUCCESS: Migrated $migratedCount existing documents to version control system.<br>";
    } else {
        echo "INFO: Document versions already exist, skipping migration.<br>";
    }
    
    // Check if settings table exists
    $db->query("SHOW TABLES LIKE 'settings'");
    $settingsExists = $db->single();
    
    if (!$settingsExists) {
        // Create settings table if it doesn't exist
        $db->query("CREATE TABLE IF NOT EXISTS settings (
            setting_id INT AUTO_INCREMENT PRIMARY KEY,
            setting_name VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $db->execute();
        echo "SUCCESS: Created 'settings' table.<br>";
    }
    
    // Add settings for document control
    $db->query("SELECT COUNT(*) as count FROM settings WHERE setting_name = 'document_watermark_template'");
    $result = $db->single();
    
    if ($result['count'] == 0) {
        $defaultWatermark = "Confidential - {USERNAME} - {DATETIME} - {DOCUMENT_ID}";
        $db->query("INSERT INTO settings (setting_name, setting_value, description) 
                    VALUES ('document_watermark_template', :watermark, 'Template for document watermarks')");
        $db->bind(':watermark', $defaultWatermark);
        $db->execute();
        echo "SUCCESS: Added document watermark template setting.<br>";
    } else {
        echo "INFO: Document watermark template setting already exists.<br>";
    }
    
    $db->query("SELECT COUNT(*) as count FROM settings WHERE setting_name = 'download_expiry_hours'");
    $result = $db->single();
    
    if ($result['count'] == 0) {
        $db->query("INSERT INTO settings (setting_name, setting_value, description) 
                    VALUES ('download_expiry_hours', '24', 'Hours until secure download links expire')");
        $db->execute();
        echo "SUCCESS: Added download expiry setting.<br>";
    } else {
        echo "INFO: Download expiry setting already exists.<br>";
    }
    
    echo "<br>Document version control system has been set up successfully. <a href='settings.php'>Go to Settings</a> to configure document access options.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
} 