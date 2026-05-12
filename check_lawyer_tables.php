<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check lawyers table structure
echo "===== LAWYERS TABLE STRUCTURE =====\n";
try {
    $db->query("DESCRIBE lawyers");
    $columns = $db->resultSet();
    
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - Null: {$column['Null']} - Key: {$column['Key']} - Default: " . 
             ($column['Default'] ?? 'NULL') . " - Extra: {$column['Extra']}\n";
    }
    
    // Get sample data
    $db->query("SELECT * FROM lawyers LIMIT 3");
    $data = $db->resultSet();
    echo "\nSample Data (up to 3 records):\n";
    print_r($data);
} catch (Exception $e) {
    echo "Error checking lawyers table: " . $e->getMessage() . "\n";
}

// Check external_lawyers table structure
echo "\n\n===== EXTERNAL LAWYERS TABLE STRUCTURE =====\n";
try {
    $db->query("DESCRIBE external_lawyers");
    $columns = $db->resultSet();
    
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - Null: {$column['Null']} - Key: {$column['Key']} - Default: " . 
             ($column['Default'] ?? 'NULL') . " - Extra: {$column['Extra']}\n";
    }
    
    // Get sample data
    $db->query("SELECT * FROM external_lawyers LIMIT 3");
    $data = $db->resultSet();
    echo "\nSample Data (up to 3 records):\n";
    print_r($data);
} catch (Exception $e) {
    echo "Error checking external_lawyers table: " . $e->getMessage() . "\n";
}

// Check party_lawyers table structure
echo "\n\n===== PARTY LAWYERS TABLE STRUCTURE =====\n";
try {
    $db->query("DESCRIBE party_lawyers");
    $columns = $db->resultSet();
    
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - Null: {$column['Null']} - Key: {$column['Key']} - Default: " . 
             ($column['Default'] ?? 'NULL') . " - Extra: {$column['Extra']}\n";
    }
    
    // Get sample data
    $db->query("SELECT * FROM party_lawyers LIMIT 3");
    $data = $db->resultSet();
    echo "\nSample Data (up to 3 records):\n";
    print_r($data);
    
    // Check case_parties table
    echo "\n\n===== CASE PARTIES TABLE STRUCTURE =====\n";
    $db->query("DESCRIBE case_parties");
    $columns = $db->resultSet();
    
    foreach ($columns as $column) {
        echo "{$column['Field']} - {$column['Type']} - Null: {$column['Null']} - Key: {$column['Key']} - Default: " . 
             ($column['Default'] ?? 'NULL') . " - Extra: {$column['Extra']}\n";
    }
    
    // Get sample data
    $db->query("SELECT * FROM case_parties LIMIT 3");
    $data = $db->resultSet();
    echo "\nSample Data (up to 3 records):\n";
    print_r($data);
    
    // Check assignments and relationships
    echo "\n\n===== PARTY LAWYER ASSIGNMENTS =====\n";
    $db->query("SELECT pl.*, 
                l.bar_number, 
                CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
                el.lawyer_name as external_lawyer_name,
                el.bar_number as external_lawyer_bar,
                el.law_firm,
                cp.party_name, 
                cp.party_type,
                cp.case_id
                FROM party_lawyers pl
                LEFT JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                LEFT JOIN users u ON l.user_id = u.user_id
                LEFT JOIN external_lawyers el ON pl.external_lawyer_id = el.external_lawyer_id
                JOIN case_parties cp ON pl.case_party_id = cp.party_id 
                LIMIT 10");
    $assignments = $db->resultSet();
    print_r($assignments);
} catch (Exception $e) {
    echo "Error checking party_lawyers table: " . $e->getMessage() . "\n";
}
?> 