<?php
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to download documents
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer', 'litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid document ID.', 'danger');
    redirect('documents.php');
}

$documentId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get user's role
$userRole = $_SESSION['role'];

// Get document details
$db->query("SELECT d.*, c.case_number, c.case_title 
            FROM documents d 
            JOIN cases c ON d.case_id = c.case_id 
            WHERE d.document_id = :document_id");
$db->bind(':document_id', $documentId);
$document = $db->single();

if (!$document) {
    setFlashMessage('Document not found.', 'danger');
    redirect('documents.php');
}

// Check if the user has access to this document
$hasAccess = false;

if (in_array($userRole, ['admin', 'court_clerk'])) {
    // Admins and court clerks have access to all documents
    $hasAccess = true;
} elseif ($userRole === 'judge') {
    // Judges have access to documents for cases assigned to them
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                WHERE d.document_id = :document_id AND c.assigned_judge = :user_id");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'lawyer') {
    // Lawyers have access to documents for cases they're associated with
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                JOIN case_lawyers cl ON c.case_id = cl.case_id 
                JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                WHERE d.document_id = :document_id AND l.user_id = :user_id");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'litigant') {
    // Litigants have access to documents for cases they're a party to and public documents
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE d.document_id = :document_id 
                AND cp.user_id = :user_id 
                AND (d.is_public = 1 OR d.uploaded_by = :user_id2)");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $db->bind(':user_id2', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
}

if (!$hasAccess) {
    setFlashMessage('You do not have permission to download this document.', 'danger');
    redirect('documents.php');
}

// Get file path
$filePath = APP_ROOT . '/' . $document['file_path'];

// Check if file exists
if (!file_exists($filePath)) {
    setFlashMessage('Document file not found on the server.', 'danger');
    redirect('document_view.php?id=' . $documentId);
}

// Log the action
logAction('Document downloaded', 'documents', $documentId);

// Handle geolocation tracking if parameters are provided
$latitude = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$longitude = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$locationName = isset($_GET['loc']) ? sanitize($_GET['loc']) : null;
$deviceType = isset($_GET['device']) ? sanitize($_GET['device']) : null;

// Track document access if not already tracked by JavaScript
if ($latitude && $longitude) {
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
                'download', 
                :ip_address, 
                :user_agent,
                :latitude, 
                :longitude, 
                :location_name, 
                :device_type
            )");
    
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
    $db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    $db->bind(':latitude', $latitude);
    $db->bind(':longitude', $longitude);
    $db->bind(':location_name', $locationName ?: null);
    $db->bind(':device_type', $deviceType ?: null);
    
    $db->execute();
    
    // Update the document's last accessed info
    $db->query("UPDATE documents SET 
                last_accessed_at = NOW(), 
                last_accessed_by = :user_id 
                WHERE document_id = :document_id");
    $db->bind(':user_id', $_SESSION['user_id']);
    $db->bind(':document_id', $documentId);
    $db->execute();
}

// Get file extension and mime type
$fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain'
];

$mimeType = isset($mimeTypes[strtolower($fileExtension)]) ? $mimeTypes[strtolower($fileExtension)] : 'application/octet-stream';

// Clean the filename for download
$downloadFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $document['document_title']) . '.' . $fileExtension;

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Clear output buffer
ob_clean();
flush();

// Read file and output to browser
readfile($filePath);
exit;
