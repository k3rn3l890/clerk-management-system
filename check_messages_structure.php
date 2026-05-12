<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check messages table structure
$db->query("SHOW TABLES LIKE 'messages'");
$tableExists = $db->single();

if (!$tableExists) {
    echo "<p>The 'messages' table does not exist in the database.</p>";
    exit;
}

$db->query("DESCRIBE messages");
$columns = $db->resultSet();

echo "<h2>Messages Table Structure</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . $column['Field'] . "</td>";
    echo "<td>" . $column['Type'] . "</td>";
    echo "<td>" . $column['Null'] . "</td>";
    echo "<td>" . $column['Key'] . "</td>";
    echo "<td>" . ($column['Default'] === NULL ? 'NULL' : $column['Default']) . "</td>";
    echo "<td>" . $column['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
