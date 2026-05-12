<?php
require_once 'includes/header.php';

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Check if the link column already exists in the notifications table
$db->query("SHOW COLUMNS FROM notifications LIKE 'link'");
$columnExists = $db->single();

if (!$columnExists) {
    // Add the link column to the notifications table
    $db->query("ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER message");
    
    if ($db->execute()) {
        setFlashMessage('The "link" column has been successfully added to the notifications table.', 'success');
    } else {
        setFlashMessage('Failed to add the "link" column to the notifications table.', 'danger');
    }
} else {
    setFlashMessage('The "link" column already exists in the notifications table.', 'info');
}

redirect('dashboard.php');
?>
