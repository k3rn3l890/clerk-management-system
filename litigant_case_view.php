<?php
require_once 'includes/header.php';

// Redirect if not logged in or not a litigant
if (!isLoggedIn() || !hasRole(['litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('litigant_cases.php');
}

$caseId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

// Initialize database
$db = new Database();

// Verify litigant has access to this case
$db->query("SELECT COUNT(*) as count FROM case_parties WHERE case_id = :case_id AND user_id = :user_id");
$db->bind(':case_id', $caseId);
$db->bind(':user_id', $userId);
$result = $db->single();

if ($result['count'] == 0) {
    setFlashMessage('You do not have access to view this case.', 'danger');
    redirect('litigant_cases.php');
}

// Get case details
$db->query("SELECT c.*, CONCAT(j.first_name, ' ', j.last_name) as judge_name FROM cases c LEFT JOIN users j ON c.assigned_judge = j.user_id WHERE c.case_id = :case_id");
$db->bind(':case_id', $caseId);
$case = $db->single();

// Get case parties
$db->query("SELECT * FROM case_parties WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$parties = $db->resultSet();

// Get case lawyers
$db->query("SELECT cl.*, l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name, el.lawyer_name as external_lawyer_name, el.bar_number as external_lawyer_bar, el.law_firm, cp.party_name, cp.party_type FROM case_lawyers cl LEFT JOIN lawyers l ON cl.lawyer_id = l.lawyer_id LEFT JOIN users u ON l.user_id = u.user_id LEFT JOIN external_lawyers el ON cl.external_lawyer_id = el.external_lawyer_id JOIN case_parties cp ON cl.party_id = cp.party_id WHERE cl.case_id = :case_id");
$db->bind(':case_id', $caseId);
$lawyers = $db->resultSet();

// Get case hearings
$db->query("SELECT * FROM hearings WHERE case_id = :case_id ORDER BY hearing_date DESC");
$db->bind(':case_id', $caseId);
$hearings = $db->resultSet();

// Get case documents visible to litigants
$db->query("SELECT d.* FROM documents d WHERE d.case_id = :case_id AND d.visible_to_litigant = 1 ORDER BY d.created_at DESC");
$db->bind(':case_id', $caseId);
$documents = $db->resultSet();

require_once 'includes/footer.php';
?>
