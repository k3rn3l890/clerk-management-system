<?php
require_once 'config/config.php';
require_once 'config/database.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit(json_encode(['error' => 'Method not allowed']));
}

// Get user ID
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if annotation ID is provided
if (!isset($_POST['annotation_id']) || empty($_POST['annotation_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'Missing annotation ID']));
}

$annotation_id = $_POST['annotation_id'];

// Initialize database connection
$db = new Database();

// Check if annotations are enabled
$db->query("SELECT setting_value FROM settings WHERE setting_name = 'enable_document_annotations'");
$annotationsEnabled = $db->single();

if (!$annotationsEnabled || $annotationsEnabled['setting_value'] != '1') {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Annotations are disabled']));
}

// Get annotation details including document details
$db->query("SELECT a.*, d.document_id, d.case_id, d.is_public
            FROM document_annotations a
            JOIN documents d ON a.document_id = d.document_id
            WHERE a.annotation_id = :annotation_id");
$db->bind(':annotation_id', $annotation_id);
$annotation = $db->single();

if (!$annotation) {
    header('HTTP/1.1 404 Not Found');
    exit(json_encode(['error' => 'Annotation not found']));
}

// Check if user has permission to delete the annotation
$canDelete = false;

// User can delete their own annotations
if ($annotation['user_id'] == $user_id) {
    $canDelete = true;
}
// Admin can delete any annotation
else if ($user_role == 'admin') {
    $canDelete = true;
}

if (!$canDelete) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'You do not have permission to delete this annotation']));
}

try {
    // Delete the annotation
    $db->query("DELETE FROM document_annotations WHERE annotation_id = :annotation_id");
    $db->bind(':annotation_id', $annotation_id);
    $db->execute();
    
    // Log the deletion
    $db->query("INSERT INTO document_access_logs 
                (document_id, user_id, access_time, access_type, ip_address, details) 
                VALUES (:document_id, :user_id, NOW(), 'delete_annotation', :ip_address, :details)");
    $db->bind(':document_id', $annotation['document_id']);
    $db->bind(':user_id', $user_id);
    $db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
    $db->bind(':details', json_encode([
        'annotation_id' => $annotation_id,
        'owner_id' => $annotation['user_id']
    ]));
    $db->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Annotation deleted']);
    exit;
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode(['error' => 'An error occurred: ' . $e->getMessage()]));
} 