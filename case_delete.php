<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to delete cases
if (!hasRole(['admin'])) {
    setFlashMessage('You do not have permission to delete cases.', 'danger');
    redirect('cases.php');
}

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('cases.php');
}

$caseId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Check if the case exists
$db->query("SELECT case_number, case_title FROM cases WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$case = $db->single();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('cases.php');
}

// Process deletion
try {
    // Start transaction
    $db->beginTransaction();
    
    // Get case details for logging
    $caseNumber = $case['case_number'];
    $caseTitle = $case['case_title'];
    
    // Delete related records first (due to foreign key constraints)
    // This assumes you have ON DELETE CASCADE set up in your database
    // If not, you'll need to delete related records manually
    
    // Delete the case
    $db->query("DELETE FROM cases WHERE case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $db->execute();
    
    // Commit transaction
    $db->endTransaction();
    
    // Log the action
    logAction('Case deleted', 'cases', $caseId, json_encode($case), null);
    
    setFlashMessage("Case #$caseNumber \"$caseTitle\" has been successfully deleted.", 'success');
} catch (Exception $e) {
    // Rollback transaction on error
    $db->cancelTransaction();
    setFlashMessage('An error occurred while deleting the case: ' . $e->getMessage(), 'danger');
}

// Use JavaScript redirect instead of PHP header redirect to avoid 'headers already sent' error
echo "<script>window.location.href = 'cases.php';</script>";
exit;
?>
