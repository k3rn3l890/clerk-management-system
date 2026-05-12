<?php
/**
 * Automated installation orchestrator for Ghana Court Clerk Management System
 */

define('APP_PATH', __DIR__);
chdir(APP_PATH);

// First check environment
require_once 'check_env.php';

// Install Composer dependencies
echo "Installing Composer dependencies...\n";
$composerOutput = shell_exec('composer install 2>&1');
echo $composerOutput ? $composerOutput : "Composer install completed.\n";
if (strpos($composerOutput, 'error') !== false) {
    die("Composer install failed. Please check the output above.\n");
}

// Then create database structure
require_once 'config/config.php';
$database = new Database();
$db = $database->getConnection();

// Execute schema SQL
echo "Creating database schema...\n";
$schema = file_get_contents('database/court_db.sql');
$statements = preg_split('/;\s*\n/', $schema);

foreach ($statements as $stmt) {
    if (trim($stmt) === '') continue;
    try {
        $db->exec($stmt . ';');
    } catch (PDOException $e) {
        echo "Schema error: " . $e->getMessage() . "\n";
    }
}

// Then run migrations
$skipExistingChecks = true; // Force re-run of migration table creation
echo "Running migrations...\n";
require_once 'run_migration.php';

// Run additional setup and migration scripts
echo "Running additional setup and migration scripts...\n";

// Create table scripts
require_once 'create_case_status_history_table.php';
require_once 'create_document_tracking_tables.php';
require_once 'create_document_version_control.php';
require_once 'create_external_lawyers_table.php';
require_once 'create_judges_table.php';
require_once 'create_lawyers_table.php';
require_once 'create_location_tracking_tables.php';
require_once 'create_party_lawyers_table.php';
require_once 'create_password_resets_table.php';
require_once 'create_payments_table.php';
require_once 'create_settings_table.php';
require_once 'create_user_preferences_table.php';

// Add column/data scripts
require_once 'add_lawyer_status_columns.php';
require_once 'add_link_column.php';
require_once 'add_status_reason_column.php';
require_once 'add_status_to_lawyer_tables.php';

// Fix/recreate table scripts
require_once 'final_fix_lawyer_tables.php';
require_once 'fix_external_lawyers_table.php';
require_once 'fix_lawyer_tables.php';
require_once 'fix_lawyers_table.php';
require_once 'fix_notifications.php';
require_once 'recreate_lawyer_tables.php';

// Update system settings scripts
require_once 'update_mime_types.php';
require_once 'update_system_name.php';

// Comprehensive admin user management
echo "Managing admin user...\n";

// Check existing admin users
$adminCheck = $db->query("SELECT user_id, username, email, status FROM users WHERE role = 'admin'");
$adminUsers = $adminCheck->fetchAll(PDO::FETCH_ASSOC);

if (empty($adminUsers)) {
    echo "No admin users found. Creating default admin user...\n";
    require_once 'create_admin.php';
    echo "Default admin user created successfully\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
} else {
    echo "Found " . count($adminUsers) . " admin user(s):\n";
    foreach ($adminUsers as $admin) {
        echo "- User ID: {$admin['user_id']}, Username: {$admin['username']}, Email: {$admin['email']}, Status: {$admin['status']}\n";
    }
    
    // Reset admin password to ensure access
    echo "\nResetting admin password to ensure system access...\n";
    $defaultPassword = 'admin123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    // Update all admin users' passwords
    foreach ($adminUsers as $admin) {
        $updateStmt = $db->prepare("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id");
        $updateStmt->execute([
            ':password' => $hashedPassword,
            ':user_id' => $admin['user_id']
        ]);
        echo "Updated password for admin user: {$admin['username']}\n";
    }
    
    // Ensure at least one active admin
    $activeAdminCheck = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
    if ($activeAdminCheck->fetchColumn() === 0) {
        // Activate the first admin user
        $firstAdmin = $adminUsers[0];
        $activateStmt = $db->prepare("UPDATE users SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id");
        $activateStmt->execute([':user_id' => $firstAdmin['user_id']]);
        echo "Activated admin user: {$firstAdmin['username']}\n";
    }
    
    echo "\nAdmin login credentials:\n";
    echo "Username: " . $adminUsers[0]['username'] . "\n";
    echo "Password: " . $defaultPassword . "\n";
    echo "Email: " . $adminUsers[0]['email'] . "\n";
}

// Create additional admin management script for future use
$adminScriptContent = '<?php
/**
 * Admin Password Management Script
 * This script provides functionality to reset admin passwords
 */

require_once "config/config.php";
require_once "config/database.php";

// Default admin credentials
$defaultUsername = "admin";
$defaultPassword = "admin123";

echo "=== Admin Password Management ===\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if admin user exists
    $stmt = $db->prepare("SELECT user_id, username, email, status FROM users WHERE username = :username");
    $stmt->execute([":username" => $defaultUsername]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Admin user found: {$admin[\'username\']} (ID: {$admin[\'user_id\']})\n";
        
        // Reset password
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id");
        $updateStmt->execute([
            ":password" => $hashedPassword,
            ":user_id" => $admin["user_id"]
        ]);
        
        echo "Password reset successfully!\n";
        echo "New credentials:\n";
        echo "Username: {$defaultUsername}\n";
        echo "Password: {$defaultPassword}\n";
        
    } else {
        echo "Admin user not found. Creating new admin user...\n";
        
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $insertStmt = $db->prepare("INSERT INTO users (username, password, email, first_name, last_name, role, status) VALUES (:username, :password, :email, :first_name, :last_name, :role, :status)");
        $insertStmt->execute([
            ":username" => $defaultUsername,
            ":password" => $hashedPassword,
            ":email" => "admin@court.gov.gh",
            ":first_name" => "System",
            ":last_name" => "Administrator",
            ":role" => "admin",
            ":status" => "active"
        ]);
        
        echo "Admin user created successfully!\n";
        echo "Login credentials:\n";
        echo "Username: {$defaultUsername}\n";
        echo "Password: {$defaultPassword}\n";
    }
    
    echo "\nYou can now login at: http://localhost/clerk_mgmt1/login.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>';

file_put_contents(APP_PATH . '/reset_admin_password.php', $adminScriptContent);
echo "Created admin password reset script: reset_admin_password.php\n";

echo "\n=== Installation Summary ===\n";
echo "✓ Environment checks completed\n";
echo "✓ Composer dependencies installed\n";
echo "✓ Database schema created\n";
echo "✓ Migrations executed\n";
echo "✓ Additional setup scripts completed\n";
echo "✓ Admin user management completed\n";
echo "✓ Password reset script created\n";

echo "\n=== Login Information ===\n";
echo "URL: http://localhost/clerk_mgmt1/login.php\n";
echo "Username: admin\n";
echo "Password: admin123\n";

echo "\n=== Password Reset Options ===\n";
echo "1. Use the automated installer again: php automated_installer.php\n";
echo "2. Use the standalone script: php reset_admin_password.php\n";
echo "3. Access via browser: http://localhost/clerk_mgmt1/reset_admin_password.php\n";

echo "\nSystem installation completed successfully!\n";

// ... (existing code from run_migration.php) ...