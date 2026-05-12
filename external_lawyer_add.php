<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $lawyerName = trim($_POST['lawyer_name'] ?? '');
    $barNumber = trim($_POST['bar_number'] ?? '');
    $lawFirm = trim($_POST['law_firm'] ?? '');
    $contactInfo = trim($_POST['contact_info'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    
    // Validate required fields
    if (empty($lawyerName)) {
        $errors[] = 'Lawyer name is required';
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Insert external lawyer
            $db->query("INSERT INTO external_lawyers (lawyer_name, bar_number, law_firm, contact_info, notes, status, created_at, updated_at) 
                        VALUES (:lawyer_name, :bar_number, :law_firm, :contact_info, :notes, :status, NOW(), NOW())");
            $db->bind(':lawyer_name', $lawyerName);
            $db->bind(':bar_number', $barNumber);
            $db->bind(':law_firm', $lawFirm);
            $db->bind(':contact_info', $contactInfo);
            $db->bind(':notes', $notes);
            $db->bind(':status', $status);
            $db->execute();
            
            // Get the ID of the inserted lawyer
            $lawyerId = $db->lastInsertId();
            
            // Commit transaction
            $db->endTransaction();
            
            setFlashMessage('External lawyer added successfully', 'success');
            
            // Check if we're coming from party_lawyer_assign page
            if (isset($_GET['return']) && $_GET['return'] === 'party_assign' && isset($_GET['case_id']) && isset($_GET['party_id'])) {
                $caseId = (int)$_GET['case_id'];
                $partyId = (int)$_GET['party_id'];
                redirect("party_lawyer_assign.php?case_id=$caseId&party_id=$partyId");
            } else {
                redirect('external_lawyers.php');
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            setFlashMessage('Error adding external lawyer: ' . $e->getMessage(), 'danger');
        }
    }
}

// Check if we should show a return link
$returnLink = '';
if (isset($_GET['return']) && $_GET['return'] === 'party_assign' && isset($_GET['case_id']) && isset($_GET['party_id'])) {
    $caseId = (int)$_GET['case_id'];
    $partyId = (int)$_GET['party_id'];
    $returnLink = "party_lawyer_assign.php?case_id=$caseId&party_id=$partyId";
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add External Lawyer</h1>
        <?php if (!empty($returnLink)): ?>
            <a href="<?php echo $returnLink; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Lawyer Assignment
            </a>
        <?php else: ?>
            <a href="external_lawyers.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to External Lawyers
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lawyer Information</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="lawyer_name" class="form-label">Lawyer Name *</label>
                    <input type="text" class="form-control" id="lawyer_name" name="lawyer_name" value="<?php echo $lawyerName ?? ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="bar_number" class="form-label">Bar Number</label>
                    <input type="text" class="form-control" id="bar_number" name="bar_number" value="<?php echo $barNumber ?? ''; ?>">
                </div>
                
                <div class="mb-3">
                    <label for="law_firm" class="form-label">Law Firm</label>
                    <input type="text" class="form-control" id="law_firm" name="law_firm" value="<?php echo $lawFirm ?? ''; ?>">
                </div>
                
                <div class="mb-3">
                    <label for="contact_info" class="form-label">Contact Information</label>
                    <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo $contactInfo ?? ''; ?>" placeholder="Phone, Email, etc.">
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $notes ?? ''; ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?php echo (isset($status) && $status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($status) && $status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Add External Lawyer</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 