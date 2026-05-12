<?php
require_once 'includes/functions.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Log the logout action
    logAction('User logout', 'users', $_SESSION['user_id']);
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

// Redirect to login page
redirect('login.php');
?>
