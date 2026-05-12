<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "Checking lawyers table structure:\n";
    $db->query("DESCRIBE lawyers");
    $lawyersColumns = $db->resultSet();
    print_r($lawyersColumns);
    
    echo "\nChecking external_lawyers table structure:\n";
    $db->query("DESCRIBE external_lawyers");
    $externalLawyersColumns = $db->resultSet();
    print_r($externalLawyersColumns);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 