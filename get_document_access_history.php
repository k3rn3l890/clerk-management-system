<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

// Get user role
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if document ID is provided
if (!isset($_GET['document_id'])) {
    die("Missing document ID");
}

$document_id = $_GET['document_id'];

// Initialize database connection
$db = new Database();

// Check if document exists and user has permission to view it
$db->query("SELECT d.*, 
            CASE WHEN u.user_role = 'admin' THEN 1
                 WHEN d.is_public = 1 THEN 1
                 WHEN c.assigned_to = :user_id THEN 1
                 WHEN c.litigant_id = :user_id THEN 1
                 ELSE 0
            END as can_view
            FROM documents d
            JOIN users u ON u.user_id = :user_id
            LEFT JOIN cases c ON d.case_id = c.case_id
            WHERE d.document_id = :document_id");
$db->bind(':document_id', $document_id);
$db->bind(':user_id', $user_id);
$document = $db->single();

if (!$document) {
    die("Document not found");
}

// Check if user has permission to view document history
if ($document['can_view'] != 1 && $user_role != 'admin') {
    die("You don't have permission to view this document's access history");
}

// Get access history
$db->query("SELECT l.*, u.full_name, u.user_role
            FROM document_access_logs l
            JOIN users u ON l.user_id = u.user_id
            WHERE l.document_id = :document_id
            ORDER BY l.access_time DESC
            LIMIT 100");
$db->bind(':document_id', $document_id);
$history = $db->resultset();

// Output access history as HTML table rows
if (count($history) > 0) {
    foreach ($history as $entry) {
        // Convert access_type to readable format
        $accessType = ucfirst(str_replace('_', ' ', $entry['access_type']));
        
        // Format user info
        $userName = htmlspecialchars($entry['full_name']);
        $userRole = htmlspecialchars(ucfirst($entry['user_role']));
        
        // Format date
        $accessTime = date('M j, Y g:i:s A', strtotime($entry['access_time']));
        
        // Format location info
        $locationInfo = '';
        if (!empty($entry['latitude']) && !empty($entry['longitude'])) {
            $locationInfo = "<span title='Latitude: {$entry['latitude']}, Longitude: {$entry['longitude']}'>
                               <i class='fas fa-map-marker-alt'></i> Map Location
                             </span>";
        }
        
        // Extra details for specific access types
        $extraDetails = '';
        if (!empty($entry['details'])) {
            $details = json_decode($entry['details'], true);
            
            if ($entry['access_type'] == 'add_annotation' || $entry['access_type'] == 'edit_annotation') {
                $extraDetails = " (Annotation ID: {$details['annotation_id']})";
            } elseif ($entry['access_type'] == 'delete_annotation') {
                $extraDetails = " (Deleted annotation ID: {$details['annotation_id']})";
            } elseif ($entry['access_type'] == 'download') {
                $extraDetails = " (Download ID: " . substr($details['download_id'] ?? 'N/A', 0, 8) . "...)";
            }
        }
        
        echo "<tr>
                <td>{$userName} <small class='text-muted'>({$userRole})</small></td>
                <td>{$accessType}{$extraDetails}</td>
                <td>{$accessTime}</td>
                <td>{$entry['ip_address']} {$locationInfo}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='4' class='text-center'>No access history available.</td></tr>";
} 