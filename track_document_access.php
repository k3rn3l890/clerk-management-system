<?php
/**
 * Document Access Tracking API
 * This script handles AJAX requests to track document access with geolocation data
 */

require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if document ID is provided
if (!isset($_POST['document_id']) || empty($_POST['document_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Document ID is required']);
    exit;
}

$documentId = (int)$_POST['document_id'];
$actionType = isset($_POST['action_type']) ? sanitize($_POST['action_type']) : 'view';
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$locationName = isset($_POST['location_name']) ? sanitize($_POST['location_name']) : null;
$deviceType = isset($_POST['device_type']) ? sanitize($_POST['device_type']) : null;

// Initialize database
$db = new Database();

// Check if document exists
$db->query("SELECT document_id FROM documents WHERE document_id = :document_id");
$db->bind(':document_id', $documentId);
$document = $db->single();

if (!$document) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Document not found']);
    exit;
}

// Insert into document_access_logs
$db->query("INSERT INTO document_access_logs (
            document_id, 
            user_id, 
            action_type, 
            ip_address, 
            user_agent,
            latitude, 
            longitude, 
            location_name, 
            device_type
        ) VALUES (
            :document_id, 
            :user_id, 
            :action_type, 
            :ip_address, 
            :user_agent,
            :latitude, 
            :longitude, 
            :location_name, 
            :device_type
        )");

$db->bind(':document_id', $documentId);
$db->bind(':user_id', $_SESSION['user_id']);
$db->bind(':action_type', $actionType);
$db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
$db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT']);
$db->bind(':latitude', $latitude);
$db->bind(':longitude', $longitude);
$db->bind(':location_name', $locationName);
$db->bind(':device_type', $deviceType);

$success = $db->execute();

// Return response
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Document access tracked successfully',
        'data' => [
            'document_id' => $documentId,
            'action_type' => $actionType,
            'has_location' => ($latitude && $longitude) ? true : false
        ]
    ]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to track document access']);
}
