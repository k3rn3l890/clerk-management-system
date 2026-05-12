<?php
/**
 * Script to create the payments table if it doesn't exist
 * This is needed for the payment functionality
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if payments table exists
    $db->query("SHOW TABLES LIKE 'payments'");
    $tableExists = $db->single();
    
    if (!$tableExists) {
        // Create payments table
        $db->query("CREATE TABLE payments (
            payment_id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            payment_type VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            status ENUM('pending', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
            due_date DATE,
            paid_date DATETIME NULL,
            receipt_number VARCHAR(50) NULL,
            payment_method VARCHAR(50) NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(user_id)
        )");
        $db->execute();
        echo "SUCCESS: Created 'payments' table.<br>";
        
        // Insert sample payment data
        $db->query("INSERT INTO payments (case_id, payment_type, amount, description, status, due_date, created_by) 
                   SELECT 
                       case_id, 
                       CASE 
                           WHEN RAND() < 0.3 THEN 'filing_fee' 
                           WHEN RAND() < 0.6 THEN 'court_fee' 
                           ELSE 'fine' 
                       END as payment_type,
                       ROUND(RAND() * 5000 + 500, 2) as amount,
                       CASE 
                           WHEN RAND() < 0.3 THEN 'Initial case filing fee' 
                           WHEN RAND() < 0.6 THEN 'Court processing fee' 
                           ELSE 'Fine imposed by court ruling' 
                       END as description,
                       CASE 
                           WHEN RAND() < 0.5 THEN 'pending' 
                           WHEN RAND() < 0.8 THEN 'paid' 
                           ELSE 'overdue' 
                       END as status,
                       DATE_ADD(CURRENT_DATE, INTERVAL FLOOR(RAND() * 30) DAY) as due_date,
                       (SELECT user_id FROM users WHERE role = 'admin' LIMIT 1) as created_by
                   FROM cases
                   LIMIT 15");
        $db->execute();
        echo "SUCCESS: Added sample payment data.<br>";
    } else {
        echo "INFO: 'payments' table already exists.<br>";
    }
    
    echo "<br>You can now <a href='payments.php'>return to the payments page</a>.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "<br>Please contact your system administrator.";
}
?>
