<?php
/**
 * Run database migrations
 */

// Include configuration
require_once 'config/config.php';
require_once 'includes/Database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

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

$db->exec($migrationsTable);

// Get the current batch number
$result = $db->query("SELECT MAX(batch) as max_batch FROM migrations");
$currentBatch = $result->fetch(PDO::FETCH_ASSOC)['max_batch'] ?? 0;
$nextBatch = $currentBatch + 1;

echo "Starting database migrations...\n";

// Run each migration
foreach ($migrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile);
    
    // Check if this migration has already been run
    $stmt = $db->prepare("SELECT id FROM migrations WHERE migration = ?");
    $stmt->execute([$migrationName]);
    
    if ($stmt->rowCount() === 0) {
        // Include the migration file
        require_once $migrationFile;
        
        // Extract the class name from the filename
        $className = str_replace('.php', '', $migrationName);
        
        if (class_exists($className)) {
            $migration = new $className();
            
            try {
                // Run the migration
                echo "Running migration: $migrationName\n";
                $migration->up($db);
                
                // Record the migration
                $stmt = $db->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$migrationName, $nextBatch]);
                
                echo "Migration completed: $migrationName\n";
            } catch (Exception $e) {
                echo "Error running migration $migrationName: " . $e->getMessage() . "\n";
                exit(1);
            }
        } else {
            echo "Error: Migration class $className not found in $migrationName\n";
        }
    } else {
        echo "Skipping already run migration: $migrationName\n";
    }
}

echo "All migrations completed successfully!\n";
