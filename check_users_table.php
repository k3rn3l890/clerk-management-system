<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check if profile_picture column exists in users table
$db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
$hasProfilePictureColumn = $db->single();

if (!$hasProfilePictureColumn) {
    // Add profile_picture column to users table
    try {
        $db->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'img/undraw_profile.svg' AFTER email");
        $db->execute();
        echo "SUCCESS: Added profile_picture column to users table.<br>";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "<br>";
    }
} else {
    echo "INFO: profile_picture column already exists in users table.<br>";
}

// Check if uploads directory exists
$uploadsDir = UPLOAD_PATH . '/profile_pictures';
if (!file_exists($uploadsDir)) {
    // Create uploads directory
    if (mkdir($uploadsDir, 0777, true)) {
        echo "SUCCESS: Created profile pictures upload directory.<br>";
    } else {
        echo "ERROR: Failed to create profile pictures upload directory.<br>";
    }
} else {
    echo "INFO: Profile pictures upload directory already exists.<br>";
}

echo "<br>You can now <a href='profile.php'>return to the profile page</a>.";
?>
