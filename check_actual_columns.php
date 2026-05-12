<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check if messages table exists
$db->query("SHOW TABLES LIKE 'messages'");
$tableExists = $db->single();

echo "<h2>Messages Table Check</h2>";
if (!$tableExists) {
    echo "<p>The 'messages' table does not exist in the database.</p>";
} else {
    // Get column names
    $db->query("SHOW COLUMNS FROM messages");
    $columns = $db->resultSet();
    
    echo "<h3>Columns in messages table:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
    // Try to get a sample message
    $db->query("SELECT * FROM messages LIMIT 1");
    $sample = $db->single();
    
    if ($sample) {
        echo "<h3>Sample message record:</h3>";
        echo "<pre>";
        print_r($sample);
        echo "</pre>";
    } else {
        echo "<p>No messages found in the table.</p>";
    }
}

// Check if there's a different messages table
$db->query("SHOW TABLES");
$tables = $db->resultSet();

echo "<h3>All tables in database:</h3>";
echo "<ul>";
foreach ($tables as $table) {
    $tableName = array_values($table)[0];
    echo "<li>" . $tableName . "</li>";
}
echo "</ul>";

// Look for tables that might contain messages
foreach ($tables as $table) {
    $tableName = array_values($table)[0];
    if (strpos($tableName, 'message') !== false || strpos($tableName, 'msg') !== false || strpos($tableName, 'mail') !== false) {
        echo "<h3>Found potential message table: " . $tableName . "</h3>";
        
        $db->query("SHOW COLUMNS FROM " . $tableName);
        $columns = $db->resultSet();
        
        echo "<h4>Columns:</h4>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
        }
        echo "</ul>";
    }
}
?>
