<?php
require_once 'includes/header.php';

// Redirect if not logged in or not admin
if (!isLoggedIn() || !hasRole(['admin'])) {
    redirect('login.php');
}

$db = new Database();

try {
    // Create case_status_history table
    $db->query("CREATE TABLE IF NOT EXISTS case_status_history (
        history_id INT PRIMARY KEY AUTO_INCREMENT,
        case_id INT NOT NULL,
        old_status VARCHAR(50) NOT NULL,
        new_status VARCHAR(50) NOT NULL,
        reason VARCHAR(50) NOT NULL,
        remarks TEXT,
        changed_by INT NOT NULL,
        changed_at DATETIME NOT NULL,
        FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE RESTRICT
    ) ENGINE=InnoDB");
    
    $db->execute();
    
    // Add indexes for better performance
    $db->query("CREATE INDEX idx_case_status_history_case_id ON case_status_history(case_id)");
    $db->execute();
    
    $db->query("CREATE INDEX idx_case_status_history_changed_by ON case_status_history(changed_by)");
    $db->execute();
    
    setFlashMessage('Case status history table created successfully.', 'success');
} catch (Exception $e) {
    setFlashMessage('Error creating case status history table: ' . $e->getMessage(), 'danger');
}

redirect('dashboard.php');
?> 