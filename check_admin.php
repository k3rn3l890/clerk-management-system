<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check admin users
$db->query("SELECT user_id, username, first_name, last_name, email, role, status FROM users WHERE role = 'admin'");
$admins = $db->resultSet();

echo "<h2>Admin Users</h2>";
if (empty($admins)) {
    echo "<p>No admin users found in the database.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Status</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>" . $admin['user_id'] . "</td>";
        echo "<td>" . $admin['username'] . "</td>";
        echo "<td>" . $admin['first_name'] . " " . $admin['last_name'] . "</td>";
        echo "<td>" . $admin['email'] . "</td>";
        echo "<td>" . $admin['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if users table exists and has data
$db->query("SHOW TABLES LIKE 'users'");
$tableExists = $db->single();

echo "<h2>Database Check</h2>";
if (!$tableExists) {
    echo "<p>The 'users' table does not exist in the database.</p>";
} else {
    $db->query("SELECT COUNT(*) as count FROM users");
    $count = $db->single();
    echo "<p>Total users in database: " . $count['count'] . "</p>";
    
    // Check table structure
    $db->query("DESCRIBE users");
    $columns = $db->resultSet();
    
    echo "<h3>Users Table Structure</h3>";
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
}
?>
