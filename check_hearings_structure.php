<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check hearings table structure
$db->query("DESCRIBE hearings");
$columns = $db->resultSet();

echo "<h2>Hearings Table Structure</h2>";
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

// Get a sample hearing
$db->query("SELECT * FROM hearings LIMIT 1");
$hearing = $db->single();

echo "<h2>Sample Hearing Record</h2>";
if ($hearing) {
    echo "<pre>";
    print_r($hearing);
    echo "</pre>";
}
?>
