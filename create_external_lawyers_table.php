<?php
require_once 'includes/header.php';

// Redirect if not logged in or not an admin
if (!isLoggedIn() || !hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

try {
    // Check if the table already exists
    $db->query("SHOW TABLES LIKE 'external_lawyers'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create the external_lawyers table
        $db->query("CREATE TABLE external_lawyers (
            external_lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            lawyer_name VARCHAR(100) NOT NULL,
            bar_number VARCHAR(50),
            contact_info VARCHAR(255),
            law_firm VARCHAR(255),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE
        )");
        
        setFlashMessage('External lawyers table created successfully.', 'success');
    } else {
        setFlashMessage('External lawyers table already exists.', 'info');
    }
} catch (Exception $e) {
    setFlashMessage('Error creating external lawyers table: ' . $e->getMessage(), 'danger');
}

// Redirect to dashboard
redirect('dashboard.php');
?>
