<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set content type header
header('Content-Type: text/plain; charset=utf-8');

echo "=== Starting Database Migrations ===\n\n";

// Include configuration and database connection
try {
    error_log("Including config files...");
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    error_log("Config files included successfully");
} catch (Exception $e) {
    error_log("Error including config files: " . $e->getMessage());
    die("Error including configuration files. Check error log for details.");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Only administrators can run migrations.');
}

// Initialize database connection
try {
    error_log("Initializing database connection...");
    $database = new Database();
    $db = $database;
    error_log("Database connection initialized");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Error connecting to database. Check error log for details.");
}

// Get all migration files
$migrationsDir = __DIR__ . '/database/migrations';
$migrationFiles = glob($migrationsDir . '/*.php');

// Sort migrations by filename (which should include a timestamp)
sort($migrationFiles);

// Track which migrations have been run
$migrationsTable = "CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    // Create migrations table if it doesn't exist
    try {
        $db->query($migrationsTable);
        $db->execute();
    } catch (Exception $e) {
        // Table might already exist
    }
    
    // Get the current batch number
    $currentBatch = 0;
    try {
        $db->query("SELECT MAX(batch) as max_batch FROM migrations");
        $result = $db->single();
        $currentBatch = $result ? (int)$result['max_batch'] : 0;
    } catch (Exception $e) {
        // Table might be empty
    }
    $nextBatch = $currentBatch + 1;
    
    // Run each migration
    foreach ($migrationFiles as $migrationFile) {
        $migrationName = basename($migrationFile);
        
        // Check if this migration has already been run
        $migrationExists = false;
        try {
            $db->query("SELECT id FROM migrations WHERE migration = :migration");
            $db->bind(':migration', $migrationName);
            $result = $db->single();
            $migrationExists = ($result !== false);
        } catch (Exception $e) {
            // Table might not exist yet or other error
        }
        
        if (!$migrationExists) {
            // Include the migration file
            require_once $migrationFile;
            
            // Special case for our migration class
            if (basename($migrationFile) === '20250706_add_document_access_fields.php') {
                $className = 'AddDocumentAccessFields';
            } else {
                // Extract the class name from the filename (remove .php and any numbers/underscores)
                $className = str_replace('.php', '', basename($migrationFile));
                // Remove date prefix if exists
                $className = preg_replace('/^\d+_/', '', $className);
                // Convert to StudlyCase
                $className = str_replace('_', '', ucwords($className, '_'));
            }
            
            if (class_exists($className)) {
                $migration = new $className();
                
                try {
                    echo "Running migration: $migrationName... ";
                    
                    // Run the migration
                    $migration->up($db);
                    
                    // First, check if the columns already exist
                    $checkSql = "
                        SELECT COUNT(*) as column_exists 
                        FROM information_schema.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'documents' 
                        AND COLUMN_NAME IN ('last_accessed_by', 'last_accessed_at')
                    ";
                    
                    $db->query($checkSql);
                    $result = $db->single();
                    
                    // Record the migration
                    $db->query("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");
                    $db->bind(':migration', $migrationName);
                    $db->bind(':batch', $nextBatch);
                    $db->execute();
                    
                    echo "DONE\n";
                } catch (Exception $e) {
                    echo "ERROR: " . htmlspecialchars($e->getMessage()) . "\n";
                    throw $e; // Re-throw to be caught by the outer try-catch
                }
            } else {
                $output[] = "<span class='text-danger'>Error: Migration class $className not found in $migrationName</span><br>";
            }
        } else {
            $output[] = "Skipping already run migration: $migrationName<br>";
        }
    }
    
    $output[] = "</pre>";
    $output[] = "<div class='mt-3 alert alert-success'>All migrations completed successfully!</div>";
    
} catch (Exception $e) {
    $output[] = "<div class='alert alert-danger mt-3'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Close card divs
$output[] = "</div></div>";

// Output the results
$title = 'Database Migrations';
$content = implode("\n", $output);

// Include the layout
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
        
        <?php echo $content; ?>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
