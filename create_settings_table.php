<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
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
    
    // Add document control settings
    $settingsToAdd = [
        [
            'name' => 'document_watermark_template',
            'value' => 'Confidential - {USERNAME} - {DATETIME} - {DOCUMENT_ID}',
            'description' => 'Template for document watermarks'
        ],
        [
            'name' => 'download_expiry_hours',
            'value' => '24',
            'description' => 'Hours until secure download links expire'
        ],
        [
            'name' => 'default_allow_download',
            'value' => '1',
            'description' => 'Default setting for allowing document downloads'
        ],
        [
            'name' => 'enable_document_versioning',
            'value' => '1',
            'description' => 'Enable document versioning system'
        ],
        [
            'name' => 'enable_document_annotations',
            'value' => '1',
            'description' => 'Enable document annotation features'
        ]
    ];
    
    foreach ($settingsToAdd as $setting) {
        // Check if setting already exists
        $db->query("SELECT COUNT(*) as count FROM settings WHERE setting_name = :name");
        $db->bind(':name', $setting['name']);
        $result = $db->single();
        
        if ($result['count'] == 0) {
            // Add setting
            $db->query("INSERT INTO settings (setting_name, setting_value, description) 
                        VALUES (:name, :value, :description)");
            $db->bind(':name', $setting['name']);
            $db->bind(':value', $setting['value']);
            $db->bind(':description', $setting['description']);
            $db->execute();
            echo "SUCCESS: Added setting: {$setting['name']}.<br>";
        } else {
            echo "INFO: Setting {$setting['name']} already exists.<br>";
        }
    }
    
    echo "<br>Settings table setup complete.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}
?> 