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

// Check if required parameters are provided
$required_params = ['document_id', 'content'];
foreach ($required_params as $param) {
    if (!isset($_POST[$param]) || empty($_POST[$param])) {
        header('HTTP/1.1 400 Bad Request');
        exit(json_encode(['error' => "Missing required parameter: {$param}"]));
    }
}

// Get parameters
$document_id = $_POST['document_id'];
$version_id = isset($_POST['version_id']) && !empty($_POST['version_id']) ? $_POST['version_id'] : null;
$content = $_POST['content'];
$is_private = isset($_POST['is_private']) ? intval($_POST['is_private']) : 0;
$page_number = isset($_POST['page_number']) ? intval($_POST['page_number']) : null;
$position_x = isset($_POST['position_x']) ? floatval($_POST['position_x']) : null;
$position_y = isset($_POST['position_y']) ? floatval($_POST['position_y']) : null;
$width = isset($_POST['width']) ? floatval($_POST['width']) : null;
$height = isset($_POST['height']) ? floatval($_POST['height']) : null;
$annotation_type = isset($_POST['annotation_type']) ? $_POST['annotation_type'] : 'note';
$annotation_id = isset($_POST['annotation_id']) && !empty($_POST['annotation_id']) ? $_POST['annotation_id'] : null;

// Initialize database connection
$db = new Database();

// Check if annotations are enabled
$db->query("SELECT setting_value FROM settings WHERE setting_name = 'enable_document_annotations'");
$annotationsEnabled = $db->single();

if (!$annotationsEnabled || $annotationsEnabled['setting_value'] != '1') {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Annotations are disabled']));
}

// Check if document exists
$db->query("SELECT d.*, 
            CASE WHEN u.user_role = 'admin' THEN 1
                 WHEN d.is_public = 1 THEN 1
                 WHEN c.assigned_to = :user_id THEN 1
                 WHEN c.litigant_id = :user_id THEN 1
                 ELSE 0
            END as can_annotate
            FROM documents d
            JOIN users u ON u.user_id = :user_id
            LEFT JOIN cases c ON d.case_id = c.case_id
            WHERE d.document_id = :document_id");
$db->bind(':document_id', $document_id);
$db->bind(':user_id', $user_id);
$document = $db->single();

if (!$document) {
    header('HTTP/1.1 404 Not Found');
    exit(json_encode(['error' => 'Document not found']));
}

// Check if user has permission to annotate the document
if ($document['can_annotate'] != 1) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'You do not have permission to annotate this document']));
}

try {
    // Update existing annotation or create new one
    if ($annotation_id) {
        // Check if the annotation exists and belongs to the user
        $db->query("SELECT * FROM document_annotations 
                    WHERE annotation_id = :annotation_id AND user_id = :user_id");
        $db->bind(':annotation_id', $annotation_id);
        $db->bind(':user_id', $user_id);
        $existing_annotation = $db->single();
        
        if (!$existing_annotation) {
            header('HTTP/1.1 403 Forbidden');
            exit(json_encode(['error' => 'You do not have permission to edit this annotation']));
        }
        
        // Update annotation
        $db->query("UPDATE document_annotations SET
                    content = :content,
                    is_private = :is_private,
                    page_number = :page_number,
                    position_x = :position_x,
                    position_y = :position_y,
                    width = :width,
                    height = :height,
                    annotation_type = :annotation_type,
                    updated_at = NOW()
                    WHERE annotation_id = :annotation_id");
        $db->bind(':content', $content);
        $db->bind(':is_private', $is_private);
        $db->bind(':page_number', $page_number);
        $db->bind(':position_x', $position_x);
        $db->bind(':position_y', $position_y);
        $db->bind(':width', $width);
        $db->bind(':height', $height);
        $db->bind(':annotation_type', $annotation_type);
        $db->bind(':annotation_id', $annotation_id);
        $db->execute();
        
        $result = ['success' => true, 'annotation_id' => $annotation_id, 'message' => 'Annotation updated'];
    } else {
        // Create new annotation
        $db->query("INSERT INTO document_annotations
                    (document_id, version_id, user_id, page_number, position_x, position_y, 
                    width, height, content, annotation_type, created_at, is_private)
                    VALUES
                    (:document_id, :version_id, :user_id, :page_number, :position_x, :position_y,
                    :width, :height, :content, :annotation_type, NOW(), :is_private)");
        $db->bind(':document_id', $document_id);
        $db->bind(':version_id', $version_id);
        $db->bind(':user_id', $user_id);
        $db->bind(':page_number', $page_number);
        $db->bind(':position_x', $position_x);
        $db->bind(':position_y', $position_y);
        $db->bind(':width', $width);
        $db->bind(':height', $height);
        $db->bind(':content', $content);
        $db->bind(':annotation_type', $annotation_type);
        $db->bind(':is_private', $is_private);
        $db->execute();
        
        $new_annotation_id = $db->lastInsertId();
        $result = ['success' => true, 'annotation_id' => $new_annotation_id, 'message' => 'Annotation created'];
    }
    
    // Log the annotation activity
    $db->query("INSERT INTO document_access_logs 
                (document_id, user_id, access_time, access_type, ip_address, details) 
                VALUES (:document_id, :user_id, NOW(), :access_type, :ip_address, :details)");
    $db->bind(':document_id', $document_id);
    $db->bind(':user_id', $user_id);
    $db->bind(':access_type', $annotation_id ? 'edit_annotation' : 'add_annotation');
    $db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
    $db->bind(':details', json_encode([
        'annotation_id' => $annotation_id ?? $new_annotation_id,
        'version_id' => $version_id
    ]));
    $db->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode(['error' => 'An error occurred: ' . $e->getMessage()]));
} 