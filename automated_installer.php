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

// Check if admin exists and create if not
echo "Validating admin user...\n";
$adminCheck = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
if ($adminCheck->fetchColumn() === 0) {
    require_once 'create_admin.php';
    echo "Admin user created automatically\n";
} else {
    echo "Admin user already exists\n";
}

echo "\nSystem installation completed successfully!\n";

// ... (existing code from run_migration.php) ...