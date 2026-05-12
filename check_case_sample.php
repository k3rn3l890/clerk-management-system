<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Get a sample case
$db->query("SELECT * FROM cases LIMIT 1");
$case = $db->single();

echo "<h2>Sample Case Record</h2>";
if ($case) {
    echo "<pre>";
    print_r($case);
    echo "</pre>";
    
    echo "<h3>Columns in this case:</h3>";
    echo "<ul>";
    foreach ($case as $key => $value) {
        echo "<li><strong>" . $key . "</strong>: " . (is_null($value) ? "NULL" : $value) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No cases found in the database.</p>";
}

// Check if there's a case_parties or similar table that might link lawyers to cases
$db->query("SHOW TABLES LIKE '%part%'");
$partyTables = $db->resultSet();

echo "<h2>Tables that might contain party relationships:</h2>";
if (!empty($partyTables)) {
    echo "<ul>";
    foreach ($partyTables as $table) {
        $tableName = array_values($table)[0];
        echo "<li>" . $tableName . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No tables found with 'part' in the name.</p>";
}

// Check for any tables that might contain lawyer relationships
$db->query("SHOW TABLES LIKE '%lawyer%'");
$lawyerTables = $db->resultSet();

echo "<h2>Tables that might contain lawyer relationships:</h2>";
if (!empty($lawyerTables)) {
    echo "<ul>";
    foreach ($lawyerTables as $table) {
        $tableName = array_values($table)[0];
        echo "<li>" . $tableName . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No tables found with 'lawyer' in the name.</p>";
}
?>
