<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to add payments
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit;
}

// Initialize database
$db = new Database();

// Get cases for dropdown
$db->query("SELECT case_id, case_number, case_title FROM cases ORDER BY case_number");
$cases = $db->resultSet();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $caseId = sanitize($_POST['case_id']);
    $paymentType = sanitize($_POST['payment_type']);
    $amount = sanitize($_POST['amount']);
    $description = sanitize($_POST['description']);
    $dueDate = sanitize($_POST['due_date']);
    $status = sanitize($_POST['status']);
    
    // Validate form data
    if (empty($caseId)) {
        $errors[] = 'Case is required';
    }
    
    if (empty($paymentType)) {
        $errors[] = 'Payment type is required';
    }
    
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = 'Amount must be a positive number';
    }
    
    if (empty($dueDate)) {
        $errors[] = 'Due date is required';
    }
    
    // If no errors, add payment
    if (empty($errors)) {
        try {
            $db->query("INSERT INTO payments (case_id, payment_type, amount, description, status, due_date, created_by) 
                        VALUES (:case_id, :payment_type, :amount, :description, :status, :due_date, :created_by)");
            $db->bind(':case_id', $caseId);
            $db->bind(':payment_type', $paymentType);
            $db->bind(':amount', $amount);
            $db->bind(':description', $description);
            $db->bind(':status', $status);
            $db->bind(':due_date', $dueDate);
            $db->bind(':created_by', $_SESSION['user_id']);
            $db->execute();
            
            $paymentId = $db->lastInsertId();
            
            // Log the action
            logAction('Payment added', 'payments', $paymentId);
            
            $success = true;
            setFlashMessage('Payment added successfully.', 'success');
            
            // Redirect to payments page
            redirect('payments.php');
        } catch (Exception $e) {
            $errors[] = 'An error occurred while adding the payment: ' . $e->getMessage();
        }
    }
}

// Check if payments table exists
$tableExists = false;
try {
    $db->query("SHOW TABLES LIKE 'payments'");
    $result = $db->single();
    $tableExists = !empty($result);
} catch (Exception $e) {
    // Table doesn't exist
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Record New Payment</h1>
        <a href="payments.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Payments
        </a>
    </div>
    
    <?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> Payments Table Not Found</h5>
        <p>The payments table does not exist in the database. Please run the script to create it.</p>
        <a href="create_payments_table.php" class="btn btn-warning">Create Payments Table</a>
    </div>
    <?php else: ?>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Payment added successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Payment Details</h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="case_id" class="form-label required-field">Case</label>
                        <select class="form-select" id="case_id" name="case_id" required>
                            <option value="">Select Case</option>
                            <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>">
                                <?php echo $case['case_number'] . ' - ' . $case['case_title']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="payment_type" class="form-label required-field">Payment Type</label>
                        <select class="form-select" id="payment_type" name="payment_type" required>
                            <option value="">Select Type</option>
                            <option value="filing_fee">Filing Fee</option>
                            <option value="court_fee">Court Fee</option>
                            <option value="fine">Fine</option>
                            <option value="settlement">Settlement</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="amount" class="form-label required-field">Amount (GHS)</label>
                        <div class="input-group">
                            <span class="input-group-text">GHS</span>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="due_date" class="form-label required-field">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label required-field">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <a href="payments.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
