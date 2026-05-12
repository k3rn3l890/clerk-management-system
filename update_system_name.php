<?php
require_once 'includes/header.php';

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Update system name in settings table
$newSystemName = 'Court Clerk Management System';

// Check if system_name setting exists
$db->query("SELECT * FROM settings WHERE setting_name = 'system_name'");
$existingSetting = $db->single();

if ($existingSetting) {
    // Update existing setting
    $db->query("UPDATE settings SET setting_value = :value WHERE setting_name = 'system_name'");
    $db->bind(':value', $newSystemName);
    
    if ($db->execute()) {
        setFlashMessage('System name has been updated to "' . $newSystemName . '" successfully.', 'success');
    } else {
        setFlashMessage('Failed to update system name.', 'danger');
    }
} else {
    // Insert new setting
    $db->query("INSERT INTO settings (setting_name, setting_value, created_at) VALUES ('system_name', :value, NOW())");
    $db->bind(':value', $newSystemName);
    
    if ($db->execute()) {
        setFlashMessage('System name has been set to "' . $newSystemName . '" successfully.', 'success');
    } else {
        setFlashMessage('Failed to set system name.', 'danger');
    }
}

// Redirect to dashboard
redirect('dashboard.php');
?>
