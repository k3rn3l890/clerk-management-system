<?php
require_once 'config/database.php';

// Initialize database
$db = new Database();

// Check case_parties table
$db->query("SELECT COUNT(*) as count FROM case_parties");
$result = $db->single();
echo "Number of case parties: " . $result['count'] . "\n";

// Check lawyers table
$db->query("SELECT COUNT(*) as count FROM lawyers");
$result = $db->single();
echo "Number of lawyers: " . $result['count'] . "\n";

// Check external_lawyers table
$db->query("SELECT COUNT(*) as count FROM external_lawyers");
$result = $db->single();
echo "Number of external lawyers: " . $result['count'] . "\n";

// Check party_lawyers table
$db->query("SELECT COUNT(*) as count FROM party_lawyers");
$result = $db->single();
echo "Number of lawyer assignments: " . $result['count'] . "\n";
?> 