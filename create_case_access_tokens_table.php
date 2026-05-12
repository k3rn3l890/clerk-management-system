<?php
require_once 'includes/functions.php';

// Only allow admins or court clerks to run this
if (!isLoggedIn() || !hasRole(['admin', 'court_clerk', 'it_admin'])) {
    echo "Access denied.";
    exit;
}

try {
    $db = new Database();

    $sql = "CREATE TABLE IF NOT EXISTS case_access_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                case_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_case_id (case_id),
                CONSTRAINT fk_case_access_tokens_case
                    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
                CONSTRAINT fk_case_access_tokens_user
                    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->query($sql);
    $db->execute();

    echo "Table 'case_access_tokens' is ready.";
} catch (Exception $e) {
    error_log('Migration error: ' . $e->getMessage());
    echo "Error creating table: " . htmlspecialchars($e->getMessage());
}
