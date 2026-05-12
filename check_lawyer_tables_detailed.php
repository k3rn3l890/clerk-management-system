<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    // Check if tables exist
    $db->query("SELECT COUNT(*) as table_exists FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = 'lawyers'");
    $lawyersExists = $db->single()['table_exists'] > 0;
    
    $db->query("SELECT COUNT(*) as table_exists FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = 'external_lawyers'");
    $externalLawyersExists = $db->single()['table_exists'] > 0;
    
    echo "Tables exist check:\n";
    echo "- lawyers table exists: " . ($lawyersExists ? "Yes" : "No") . "\n";
    echo "- external_lawyers table exists: " . ($externalLawyersExists ? "Yes" : "No") . "\n\n";
    
    if ($lawyersExists) {
        echo "LAWYERS TABLE STRUCTURE:\n";
        $db->query("SHOW COLUMNS FROM lawyers");
        $lawyersColumns = $db->resultSet();
        foreach ($lawyersColumns as $column) {
            echo "- " . $column['Field'] . ": " . $column['Type'];
            if ($column['Null'] === 'NO') echo " (NOT NULL)";
            if ($column['Default'] !== null) echo " DEFAULT '" . $column['Default'] . "'";
            echo "\n";
        }
    }
    
    if ($externalLawyersExists) {
        echo "\nEXTERNAL_LAWYERS TABLE STRUCTURE:\n";
        $db->query("SHOW COLUMNS FROM external_lawyers");
        $externalLawyersColumns = $db->resultSet();
        foreach ($externalLawyersColumns as $column) {
            echo "- " . $column['Field'] . ": " . $column['Type'];
            if ($column['Null'] === 'NO') echo " (NOT NULL)";
            if ($column['Default'] !== null) echo " DEFAULT '" . $column['Default'] . "'";
            echo "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 