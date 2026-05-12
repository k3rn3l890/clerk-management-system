<?php
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to delete documents
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer'])) {
    setFlashMessage('You do not have permission to delete documents.', 'danger');
    redirect('documents.php');
}

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid document ID.', 'danger');
    redirect('documents.php');
}

$documentId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Initialize database
$db = new Database();

// Get document details
$db->query("SELECT d.*, c.case_id, c.case_number, c.case_title 
            FROM documents d 
            JOIN cases c ON d.case_id = c.case_id 
            WHERE d.document_id = :document_id");
$db->bind(':document_id', $documentId);
$document = $db->single();

if (!$document) {
    setFlashMessage('Document not found.', 'danger');
    redirect('documents.php');
}

// Check if user has permission to delete this document
$hasPermission = false;

if (in_array($userRole, ['admin', 'court_clerk'])) {
    // Admins and court clerks can delete any document
    $hasPermission = true;
} elseif ($userRole === 'judge') {
    // Judges can delete documents for cases assigned to them
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                WHERE d.document_id = :document_id AND c.assigned_judge = :user_id");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $userId);
    $result = $db->single();
    $hasPermission = ($result['count'] > 0);
} elseif ($userRole === 'lawyer') {
    // Lawyers can only delete documents they uploaded
    $hasPermission = ($document['uploaded_by'] == $userId);
}

if (!$hasPermission) {
    setFlashMessage('You do not have permission to delete this document.', 'danger');
    redirect('documents.php');
}

// Get file path
$filePath = APP_ROOT . '/' . $document['file_path'];

// Store document info for logging
$documentInfo = [
    'document_id' => $document['document_id'],
    'document_title' => $document['document_title'],
    'document_type' => $document['document_type'],
    'case_number' => $document['case_number'],
    'file_path' => $document['file_path']
];

// Delete document record from database
$db->query("DELETE FROM documents WHERE document_id = :document_id");
$db->bind(':document_id', $documentId);

if ($db->execute()) {
    // Delete physical file if it exists
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Log the action
    logAction('Document deleted', 'documents', $documentId, json_encode($documentInfo), null);
    
    // Create notification for case participants
    if (in_array($userRole, ['admin', 'court_clerk', 'judge'])) {
        // Get all parties and lawyers associated with the case
        $db->query("SELECT user_id FROM case_parties WHERE case_id = :case_id");
        $db->bind(':case_id', $document['case_id']);
        $parties = $db->resultSet();
        
        $db->query("SELECT l.user_id 
                    FROM case_lawyers cl 
                    JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                    WHERE cl.case_id = :case_id");
        $db->bind(':case_id', $document['case_id']);
        $lawyers = $db->resultSet();
        
        $notificationTitle = 'Document Deleted';
        $notificationMessage = 'A document "' . $document['document_title'] . '" has been deleted from case ' . $document['case_number'];
        
        // Notify parties
        foreach ($parties as $party) {
            if ($party['user_id'] != $userId) {
                createNotification($party['user_id'], $notificationTitle, $notificationMessage, 'case_view.php?id=' . $document['case_id']);
            }
        }
        
        // Notify lawyers
        foreach ($lawyers as $lawyer) {
            if ($lawyer['user_id'] != $userId) {
                createNotification($lawyer['user_id'], $notificationTitle, $notificationMessage, 'case_view.php?id=' . $document['case_id']);
            }
        }
        
        // Notify assigned judge if not the one deleting
        if ($userRole !== 'judge') {
            $db->query("SELECT assigned_judge FROM cases WHERE case_id = :case_id");
            $db->bind(':case_id', $document['case_id']);
            $judge = $db->single();
            
            if ($judge && $judge['assigned_judge'] != $userId) {
                createNotification($judge['assigned_judge'], $notificationTitle, $notificationMessage, 'case_view.php?id=' . $document['case_id']);
            }
        }
    }
    
    setFlashMessage('Document deleted successfully.', 'success');
} else {
    setFlashMessage('Failed to delete document.', 'danger');
}

// Redirect back to documents page or case view
$redirectUrl = isset($_GET['return_to']) && $_GET['return_to'] === 'case' 
    ? 'case_view.php?id=' . $document['case_id'] 
    : 'documents.php';

redirect($redirectUrl);
?>
