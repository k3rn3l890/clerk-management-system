<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check if admin already exists
$db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$result = $db->single();

if ($result['count'] > 0) {
    echo "<h2>Admin User Already Exists</h2>";
    echo "<p>There is already at least one admin user in the database.</p>";
    
    // Display existing admin users
    $db->query("SELECT user_id, username, first_name, last_name, email, status FROM users WHERE role = 'admin'");
    $admins = $db->resultSet();
    
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
    
    echo "<h3>Reset Admin Password</h3>";
    echo "<p>If you're having trouble logging in, you can reset the admin password:</p>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
        $adminId = (int)$_POST['admin_id'];
        $newPassword = 'admin123'; // Default password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $db->query("UPDATE users SET password = :password, status = 'active' WHERE user_id = :user_id AND role = 'admin'");
        $db->bind(':password', $hashedPassword);
        $db->bind(':user_id', $adminId);
        
        if ($db->execute()) {
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "Password reset successfully for admin user ID: " . $adminId . "<br>";
            echo "New password: <strong>" . $newPassword . "</strong>";
            echo "</div>";
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "Failed to reset password.";
            echo "</div>";
        }
    }
    
    echo "<form method='post' action=''>";
    echo "<select name='admin_id'>";
    foreach ($admins as $admin) {
        echo "<option value='" . $admin['user_id'] . "'>" . $admin['username'] . " (" . $admin['first_name'] . " " . $admin['last_name'] . ")</option>";
    }
    echo "</select>";
    echo "<input type='submit' name='reset_password' value='Reset Password'>";
    echo "</form>";
    
} else {
    // Create admin user if it doesn't exist
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $firstName = $_POST['first_name'];
        $lastName = $_POST['last_name'];
        $email = $_POST['email'];
        
        // Validate input
        $errors = [];
        
        if (empty($username)) {
            $errors[] = "Username is required";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        if (empty($firstName)) {
            $errors[] = "First name is required";
        }
        
        if (empty($lastName)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (empty($errors)) {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert admin user
            $db->query("INSERT INTO users (username, password, first_name, last_name, email, role, status, created_at, updated_at) 
                        VALUES (:username, :password, :first_name, :last_name, :email, 'admin', 'active', NOW(), NOW())");
            
            $db->bind(':username', $username);
            $db->bind(':password', $hashedPassword);
            $db->bind(':first_name', $firstName);
            $db->bind(':last_name', $lastName);
            $db->bind(':email', $email);
            
            if ($db->execute()) {
                echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                echo "Admin user created successfully!<br>";
                echo "Username: <strong>" . $username . "</strong><br>";
                echo "Password: <strong>" . $password . "</strong><br>";
                echo "You can now <a href='login.php'>login</a> with these credentials.";
                echo "</div>";
            } else {
                echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                echo "Failed to create admin user.";
                echo "</div>";
            }
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>" . $error . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
    }
    
    echo "<h2>Create Admin User</h2>";
    echo "<p>No admin users found. Create one below:</p>";
    
    echo "<form method='post' action=''>";
    echo "<table>";
    echo "<tr><td>Username:</td><td><input type='text' name='username' value='admin' required></td></tr>";
    echo "<tr><td>Password:</td><td><input type='text' name='password' value='admin123' required></td></tr>";
    echo "<tr><td>First Name:</td><td><input type='text' name='first_name' value='System' required></td></tr>";
    echo "<tr><td>Last Name:</td><td><input type='text' name='last_name' value='Administrator' required></td></tr>";
    echo "<tr><td>Email:</td><td><input type='email' name='email' value='admin@example.com' required></td></tr>";
    echo "<tr><td colspan='2'><input type='submit' name='create_admin' value='Create Admin User'></td></tr>";
    echo "</table>";
    echo "</form>";
}

// Check database tables
echo "<h2>Database Tables Check</h2>";
$db->query("SHOW TABLES");
$tables = $db->resultSet();

if (empty($tables)) {
    echo "<p>No tables found in the database. The database may not be properly set up.</p>";
} else {
    echo "<p>Tables in database:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "<li>" . $tableName . "</li>";
    }
    echo "</ul>";
}
?>
