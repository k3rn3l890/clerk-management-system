<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if document ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Missing document ID.";
    header("Location: documents.php");
    exit();
}

$db = new Database();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$document_id = $_GET['id'];
$version_id = isset($_GET['version']) ? $_GET['version'] : null;

// Get document details
if ($version_id) {
    // Get specific document version
    $db->query("SELECT dv.*, d.document_title, d.case_id, d.document_type, d.is_public, d.allow_download
                FROM document_versions dv 
                JOIN documents d ON dv.document_id = d.document_id
                WHERE dv.version_id = :version_id");
    $db->bind(':version_id', $version_id);
    $document = $db->single();
} else {
    // Get latest document info
    $db->query("SELECT d.* 
                FROM documents d 
                WHERE d.document_id = :document_id");
    $db->bind(':document_id', $document_id);
    $document = $db->single();
    
    // Check if there are versions available
    $db->query("SELECT version_id, file_path, file_size, mime_type 
                FROM document_versions 
                WHERE document_id = :document_id
                ORDER BY version_number DESC
                LIMIT 1");
    $db->bind(':document_id', $document_id);
    $latestVersion = $db->single();
    
    if ($latestVersion) {
        $document['file_path'] = $latestVersion['file_path'];
        $document['file_size'] = $latestVersion['file_size'];
        $document['mime_type'] = $latestVersion['mime_type'];
        $version_id = $latestVersion['version_id'];
    }
}

// Check if document exists
if (!$document) {
    $_SESSION['error'] = "Document not found.";
    header("Location: documents.php");
    exit();
}

// Check if downloads are allowed
if (!$document['allow_download']) {
    $_SESSION['error'] = "Downloads are not allowed for this document.";
    header("Location: document_view_browser.php?id={$document_id}" . ($version_id ? "&version={$version_id}" : ""));
    exit();
}

// Check user permissions
$canDownload = false;

// Admin can download all documents
if ($user_role == 'admin') {
    $canDownload = true;
} else {
    // Check if user is associated with the document's case
    $db->query("SELECT * FROM cases WHERE case_id = :case_id AND (assigned_to = :user_id OR litigant_id = :user_id)");
    $db->bind(':case_id', $document['case_id']);
    $db->bind(':user_id', $user_id);
    $case = $db->single();
    
    if ($case || $document['is_public'] == 1) {
        $canDownload = true;
    }
}

if (!$canDownload) {
    $_SESSION['error'] = "You don't have permission to download this document.";
    header("Location: documents.php");
    exit();
}

// Generate a unique download ID
$download_id = bin2hex(random_bytes(32));

// Get settings
$db->query("SELECT setting_value FROM settings WHERE setting_name = 'download_expiry_hours'");
$expiryHours = $db->single();
$expiry_hours = ($expiryHours && $expiryHours['setting_value']) ? intval($expiryHours['setting_value']) : 24;

$db->query("SELECT setting_value FROM settings WHERE setting_name = 'document_watermark_template'");
$watermarkTemplate = $db->single();
$watermark_template = ($watermarkTemplate && $watermarkTemplate['setting_value']) ? $watermarkTemplate['setting_value'] : "Confidential - {USERNAME} - {DATETIME} - {DOCUMENT_ID}";

// Replace watermark placeholders
$watermark_text = str_replace(
    ['{USERNAME}', '{DATETIME}', '{DOCUMENT_ID}', '{IP_ADDRESS}'],
    [
        $_SESSION['username'] ?? $_SESSION['user_id'],
        date('Y-m-d H:i:s'),
        $document_id,
        $_SERVER['REMOTE_ADDR']
    ],
    $watermark_template
);

// Calculate expiry time
$expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));

// Get file extension
$file_extension = pathinfo($document['file_path'], PATHINFO_EXTENSION);

// Generate a random encryption key for PDF encryption
$encryption_key = bin2hex(random_bytes(16));

// Insert secure download record
$db->query("INSERT INTO secure_downloads 
            (download_id, document_id, user_id, version_id, created_at, expires_at, 
            watermark_text, encryption_key, ip_restriction, is_active)
            VALUES 
            (:download_id, :document_id, :user_id, :version_id, NOW(), :expires_at,
            :watermark_text, :encryption_key, :ip_restriction, 1)");
$db->bind(':download_id', $download_id);
$db->bind(':document_id', $document_id);
$db->bind(':user_id', $user_id);
$db->bind(':version_id', $version_id);
$db->bind(':expires_at', $expires_at);
$db->bind(':watermark_text', $watermark_text);
$db->bind(':encryption_key', $encryption_key);
$db->bind(':ip_restriction', $_SERVER['REMOTE_ADDR']);
$db->execute();

// Log document access
$db->query("INSERT INTO document_access_logs 
            (document_id, user_id, access_time, access_type, ip_address, latitude, longitude) 
            VALUES (:document_id, :user_id, NOW(), 'download', :ip_address, :latitude, :longitude)");
$db->bind(':document_id', $document_id);
$db->bind(':user_id', $user_id);
$db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
$db->bind(':latitude', isset($_SESSION['latitude']) ? $_SESSION['latitude'] : null);
$db->bind(':longitude', isset($_SESSION['longitude']) ? $_SESSION['longitude'] : null);
$db->execute();

// Update document's last accessed information
$db->query("UPDATE documents SET last_accessed_at = NOW(), last_accessed_by = :user_id WHERE document_id = :document_id");
$db->bind(':user_id', $user_id);
$db->bind(':document_id', $document_id);
$db->execute();

// Increment download count
$db->query("UPDATE secure_downloads SET download_count = download_count + 1 WHERE download_id = :download_id");
$db->bind(':download_id', $download_id);
$db->execute();

// Get the file path
$file_path = $document['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    $_SESSION['error'] = "File not found on server.";
    header("Location: document_view_browser.php?id={$document_id}" . ($version_id ? "&version={$version_id}" : ""));
    exit();
}

// PDF watermarking for PDF files
if (strtolower($file_extension) == 'pdf') {
    // For production use, implement PDF watermarking with a library like FPDF or TCPDF
    // This would require server-side processing of the PDF
    
    // For now, we'll just deliver the PDF with a browser watermark warning
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
} 
// For other document types, just download directly
else {
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
} 