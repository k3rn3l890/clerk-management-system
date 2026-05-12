<?php
/**
 * Script to create location tracking tables for the geolocation enforcement feature
 */
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Create location_policy_agreements table if it doesn't exist
    $db->query("CREATE TABLE IF NOT EXISTS location_policy_agreements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        agreement_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        browser_info TEXT,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )");
    $db->execute();
    echo "SUCCESS: Created 'location_policy_agreements' table.<br>";
    
    // Check if enforce_location_tracking setting exists
    $db->query("SELECT COUNT(*) as count FROM settings WHERE setting_name = 'enforce_location_tracking'");
    $result = $db->single();
    
    if ($result['count'] == 0) {
        // Insert default setting
        $db->query("INSERT INTO settings (setting_name, setting_value, description) 
                    VALUES ('enforce_location_tracking', 'yes', 'Require users to enable location tracking to use the system')");
        $db->execute();
        echo "SUCCESS: Added 'enforce_location_tracking' setting with default value 'yes'.<br>";
    } else {
        echo "INFO: 'enforce_location_tracking' setting already exists.<br>";
    }
    
    // Check if location_tracking_message setting exists
    $db->query("SELECT COUNT(*) as count FROM settings WHERE setting_name = 'location_tracking_message'");
    $result = $db->single();
    
    if ($result['count'] == 0) {
        // Insert default message
        $defaultMessage = "The Ghana Court Clerk Management System requires location access for legal and security purposes. Your location will be recorded when accessing court documents to ensure proper document tracking and maintain chain of custody.";
        $db->query("INSERT INTO settings (setting_name, setting_value, description) 
                    VALUES ('location_tracking_message', :message, 'Message displayed to users when requesting location permissions')");
        $db->bind(':message', $defaultMessage);
        $db->execute();
        echo "SUCCESS: Added 'location_tracking_message' setting with default message.<br>";
    } else {
        echo "INFO: 'location_tracking_message' setting already exists.<br>";
    }
    
    // Add columns to track location enforcement for users
    $db->query("SHOW COLUMNS FROM users LIKE 'location_enabled'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        $db->query("ALTER TABLE users ADD COLUMN location_enabled BOOLEAN DEFAULT NULL");
        $db->execute();
        echo "SUCCESS: Added 'location_enabled' column to users table.<br>";
    } else {
        echo "INFO: 'location_enabled' column already exists in users table.<br>";
    }
    
    $db->query("SHOW COLUMNS FROM users LIKE 'location_last_updated'");
    $columnExists = $db->single();
    
    if (!$columnExists) {
        $db->query("ALTER TABLE users ADD COLUMN location_last_updated TIMESTAMP NULL");
        $db->execute();
        echo "SUCCESS: Added 'location_last_updated' column to users table.<br>";
    } else {
        echo "INFO: 'location_last_updated' column already exists in users table.<br>";
    }
    
    echo "<br>Location tracking tables and settings have been created successfully. <a href='settings.php'>Go to Settings</a> to configure location tracking options.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}