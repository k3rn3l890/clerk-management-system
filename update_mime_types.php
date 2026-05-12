<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Update mime_type for ALL documents based on file extension
    $db->query("UPDATE documents SET mime_type = 
        CASE 
            WHEN file_path LIKE '%.pdf' THEN 'application/pdf'
            WHEN file_path LIKE '%.docx' THEN 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            WHEN file_path LIKE '%.doc' THEN 'application/msword'
            WHEN file_path LIKE '%.jpg' OR file_path LIKE '%.jpeg' THEN 'image/jpeg'
            WHEN file_path LIKE '%.png' THEN 'image/png'
            ELSE 'application/octet-stream'
        END");
    $db->execute();
    $updatedCount = $db->rowCount();
    
    echo "SUCCESS: Updated mime_type for $updatedCount documents.<br>";
    
    // Verify the update by checking a sample document
    $db->query("SELECT document_id, file_path, mime_type FROM documents LIMIT 3");
    $samples = $db->resultset();
    
    if (count($samples) > 0) {
        echo "<h2>Sample Documents Data After Update</h2>";
        echo "<pre>";
        print_r($samples);
        echo "</pre>";
    } else {
        echo "No documents found in the database.<br>";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?> 