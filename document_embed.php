<?php
// Start output buffering
ob_start();

try {
    // Include required files
    require_once 'config/database.php';
    require_once 'includes/functions.php';

    // Check if document ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Document ID is required');
    }

    $documentId = (int)$_GET['id'];
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        throw new Exception('Access denied. Please log in.');
    }

    // Initialize database
    $db = new Database();

    // Get document details
    $db->query("SELECT * FROM documents WHERE document_id = :document_id");
    $db->bind(':document_id', $documentId);
    $document = $db->single();

    if (!$document) {
        throw new Exception('Document not found');
    }

    // Check if user has access
    $hasAccess = checkDocumentAccess($db, $documentId, $_SESSION['user_id'], $_SESSION['role']);
    
    if (!$hasAccess) {
        throw new Exception('You do not have permission to view this document');
    }

    // Get file path and validate
    $filePath = APP_ROOT . '/' . ltrim($document['file_path'], '/');
    $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    if ($fileExtension !== 'pdf' || !file_exists($filePath)) {
        throw new Exception('Invalid document type or file not found');
    }

    // Update access log
    logDocumentAccess($db, $documentId, $_SESSION['user_id']);

    // Stream the original PDF inline (no watermarking)
    while (ob_get_level()) { ob_end_clean(); }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    // Do NOT set a CSP for PDFs to avoid breaking built-in viewers
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the error
    error_log("Error in document_embed.php: " . $e->getMessage());
    
    // Output error as JSON if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } else {
        // Otherwise output plain text error
        header('HTTP/1.0 500 Internal Server Error');
        echo 'Error: ' . $e->getMessage();
    }
    exit;
}

/**
 * Check if user has access to the document
 */
function checkDocumentAccess($db, $documentId, $userId, $userRole) {
    if (in_array($userRole, ['admin', 'court_clerk'])) {
        return true;
    }
    
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                LEFT JOIN case_lawyers cl ON c.case_id = cl.case_id 
                LEFT JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                LEFT JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE d.document_id = :document_id 
                AND (
                    (c.assigned_judge = :user_id1) OR 
                    (l.user_id = :user_id2) OR 
                    (cp.user_id = :user_id3) OR 
                    (d.uploaded_by = :user_id4)
                )");
    
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id1', $userId);
    $db->bind(':user_id2', $userId);
    $db->bind(':user_id3', $userId);
    $db->bind(':user_id4', $userId);
    
    $result = $db->single();
    return ($result['count'] > 0);
}

/**
 * Log document access
 */
function logDocumentAccess($db, $documentId, $userId) {
    try {
        $db->query("UPDATE documents 
                    SET last_accessed_by = :user_id, 
                        last_accessed_at = NOW() 
                    WHERE document_id = :document_id");
        $db->bind(':user_id', $userId);
        $db->bind(':document_id', $documentId);
        $db->execute();
    } catch (Exception $e) {
        error_log("Failed to log document access: " . $e->getMessage());
    }
}

/**
 * Get watermark text with user info
 */
// Watermarking removed

// Watermarking removed

// Watermarking logic and any HTML/script output removed
