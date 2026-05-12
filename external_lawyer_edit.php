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

// Check if lawyer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid lawyer ID.', 'danger');
    redirect('external_lawyers.php');
}

$lawyerId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get lawyer details
try {
    $db->query("SELECT * FROM external_lawyers WHERE external_lawyer_id = :id");
    $db->bind(':id', $lawyerId);
    $lawyer = $db->single();
    
    if (!$lawyer) {
        setFlashMessage('Lawyer not found.', 'danger');
        redirect('external_lawyers.php');
    }
} catch (Exception $e) {
    setFlashMessage('Error retrieving lawyer details: ' . $e->getMessage(), 'danger');
    redirect('external_lawyers.php');
}

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
            
            // Update external lawyer
            $db->query("UPDATE external_lawyers 
                        SET lawyer_name = :lawyer_name, 
                            bar_number = :bar_number, 
                            law_firm = :law_firm, 
                            contact_info = :contact_info, 
                            notes = :notes, 
                            status = :status, 
                            updated_at = NOW() 
                        WHERE external_lawyer_id = :id");
            $db->bind(':lawyer_name', $lawyerName);
            $db->bind(':bar_number', $barNumber);
            $db->bind(':law_firm', $lawFirm);
            $db->bind(':contact_info', $contactInfo);
            $db->bind(':notes', $notes);
            $db->bind(':status', $status);
            $db->bind(':id', $lawyerId);
            $db->execute();
            
            // Commit transaction
            $db->endTransaction();
            
            setFlashMessage('External lawyer updated successfully', 'success');
            redirect('external_lawyers.php');
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            setFlashMessage('Error updating external lawyer: ' . $e->getMessage(), 'danger');
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit External Lawyer</h1>
        <a href="external_lawyers.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to External Lawyers
        </a>
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
                    <input type="text" class="form-control" id="lawyer_name" name="lawyer_name" value="<?php echo htmlspecialchars($lawyer['lawyer_name']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="bar_number" class="form-label">Bar Number</label>
                    <input type="text" class="form-control" id="bar_number" name="bar_number" value="<?php echo htmlspecialchars($lawyer['bar_number'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="law_firm" class="form-label">Law Firm</label>
                    <input type="text" class="form-control" id="law_firm" name="law_firm" value="<?php echo htmlspecialchars($lawyer['law_firm'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="contact_info" class="form-label">Contact Information</label>
                    <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($lawyer['contact_info'] ?? ''); ?>" placeholder="Phone, Email, etc.">
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($lawyer['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?php echo (isset($lawyer['status']) && $lawyer['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($lawyer['status']) && $lawyer['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Lawyer</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 