<?php
// Turn on error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection parameters
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'clerk_mgmt';

echo "Attempting to connect to MySQL directly...\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected successfully.\n\n";
    
    // Check database name
    echo "Current database: " . $database . "\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database:\n";
    if (count($tables) > 0) {
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    } else {
        echo "No tables found.\n";
    }
    
    // Check for lawyers and external_lawyers tables
    $hasLawyers = in_array('lawyers', $tables);
    $hasExternalLawyers = in_array('external_lawyers', $tables);
    
    echo "\nChecking for specific tables:\n";
    echo "- lawyers table exists: " . ($hasLawyers ? "Yes" : "No") . "\n";
    echo "- external_lawyers table exists: " . ($hasExternalLawyers ? "Yes" : "No") . "\n";
    
    // Create tables if they don't exist
    if (!$hasLawyers) {
        echo "\nCreating lawyers table...\n";
        $pdo->exec("CREATE TABLE lawyers (
            lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            bar_number VARCHAR(50),
            specialization VARCHAR(100),
            law_firm VARCHAR(100),
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )");
        echo "Lawyers table created.\n";
    }
    
    if (!$hasExternalLawyers) {
        echo "\nCreating external_lawyers table...\n";
        $pdo->exec("CREATE TABLE external_lawyers (
            external_lawyer_id INT AUTO_INCREMENT PRIMARY KEY,
            lawyer_name VARCHAR(100) NOT NULL,
            bar_number VARCHAR(50),
            contact_info VARCHAR(255),
            law_firm VARCHAR(255),
            notes TEXT,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "External lawyers table created.\n";
    }
    
    // Check columns in tables
    if ($hasLawyers || !$hasLawyers) {
        $stmt = $pdo->query("SHOW COLUMNS FROM lawyers");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nColumns in lawyers table:\n";
        $hasStatusColumn = false;
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
            if ($column['Field'] == 'status') {
                $hasStatusColumn = true;
            }
        }
        
        if (!$hasStatusColumn) {
            echo "\nAdding status column to lawyers table...\n";
            $pdo->exec("ALTER TABLE lawyers 
                        ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            echo "Status column added to lawyers table.\n";
        }
    }
    
    if ($hasExternalLawyers || !$hasExternalLawyers) {
        $stmt = $pdo->query("SHOW COLUMNS FROM external_lawyers");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nColumns in external_lawyers table:\n";
        $hasStatusColumn = false;
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
            if ($column['Field'] == 'status') {
                $hasStatusColumn = true;
            }
        }
        
        if (!$hasStatusColumn) {
            echo "\nAdding status column to external_lawyers table...\n";
            $pdo->exec("ALTER TABLE external_lawyers 
                        ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
            echo "Status column added to external_lawyers table.\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?> 