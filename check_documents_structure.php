<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();

try {
    // Check if documents table exists
    $db->query("SHOW TABLES LIKE 'documents'");
    $tableExists = $db->resultset();
    
    if (count($tableExists) > 0) {
        // Get table structure
        $db->query("DESCRIBE documents");
        $columns = $db->resultset();
        
        echo "<h2>Documents Table Structure</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Get sample data
        $db->query("SELECT * FROM documents LIMIT 1");
        $sample = $db->single();
        
        if ($sample) {
            echo "<h2>Sample Document Data</h2>";
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        } else {
            echo "<p>No documents found in the table.</p>";
        }
    } else {
        echo "<p>Documents table does not exist.</p>";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?> 