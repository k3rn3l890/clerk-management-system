<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to schedule hearings
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Initialize variables
$errors = [];
$success = false;
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;

// If case ID is provided, check if it exists
if ($caseId) {
    // Check if case exists and user has access
    $query = "SELECT c.* FROM cases c WHERE c.case_id = :case_id";
    
    if (hasRole('court_clerk')) {
        $query .= " AND c.created_by = :user_id";
    }
    
    $db->query($query);
    $db->bind(':case_id', $caseId);
    
    if (hasRole('court_clerk')) {
        $db->bind(':user_id', $_SESSION['user_id']);
    }
    
    $case = $db->single();
    
    if (!$case) {
        setFlashMessage('Case not found or you do not have permission to access it.', 'danger');
        redirect('cases.php');
    }
}

// Get all cases for dropdown (if case ID is not provided)
if (!$caseId) {
    if (hasRole('court_clerk')) {
        $db->query("SELECT * FROM cases WHERE created_by = :user_id ORDER BY case_number");
        $db->bind(':user_id', $_SESSION['user_id']);
    } else {
        $db->query("SELECT * FROM cases ORDER BY case_number");
    }
    $cases = $db->resultSet();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : $caseId;
    $hearingDate = sanitize($_POST['hearing_date']);
    $hearingTime = sanitize($_POST['hearing_time']);
    $hearingType = sanitize($_POST['hearing_type']);
    $location = sanitize($_POST['location']);
    $notes = sanitize($_POST['notes']);
    
    // Validate form data
    if (empty($caseId)) {
        $errors[] = 'Case is required';
    }
    
    if (empty($hearingDate)) {
        $errors[] = 'Hearing date is required';
    }
    
    if (empty($hearingTime)) {
        $errors[] = 'Hearing time is required';
    }
    
    if (empty($hearingType)) {
        $errors[] = 'Hearing type is required';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required';
    }
    
    // Combine date and time
    $hearingDateTime = $hearingDate . ' ' . $hearingTime;
    
    // If no errors, proceed with saving the hearing
    if (empty($errors)) {
        try {
            // Insert hearing
            $db->query("INSERT INTO hearings (case_id, hearing_date, hearing_type, location, status, notes, created_by) 
                        VALUES (:case_id, :hearing_date, :hearing_type, :location, :status, :notes, :created_by)");
            
            $db->bind(':case_id', $caseId);
            $db->bind(':hearing_date', $hearingDateTime);
            $db->bind(':hearing_type', $hearingType);
            $db->bind(':location', $location);
            $db->bind(':status', 'scheduled');
            $db->bind(':notes', $notes);
            $db->bind(':created_by', $_SESSION['user_id']);
            
            $db->execute();
            
            // Get the last inserted hearing ID
            $hearingId = $db->lastInsertId();
            
            // Log action
            logAction('Hearing scheduled', 'hearings', $hearingId);
            
            // Get case details for notifications
            $db->query("SELECT c.*, j.user_id as judge_id FROM cases c 
                        LEFT JOIN users j ON c.assigned_judge = j.user_id 
                        WHERE c.case_id = :case_id");
            $db->bind(':case_id', $caseId);
            $caseDetails = $db->single();
            
            // Send notification to assigned judge
            if ($caseDetails['judge_id']) {
                sendNotification(
                    $caseDetails['judge_id'],
                    'New Hearing Scheduled',
                    "A new hearing has been scheduled for case {$caseDetails['case_number']} on " . formatDateTime($hearingDateTime),
                    $caseId,
                    $hearingId
                );
            }
            
            // Send notifications to lawyers and parties
            // Get lawyers associated with the case
            $db->query("SELECT DISTINCT l.user_id 
                        FROM case_lawyers cl 
                        JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                        WHERE cl.case_id = :case_id");
            $db->bind(':case_id', $caseId);
            $lawyers = $db->resultSet();
            
            foreach ($lawyers as $lawyer) {
                sendNotification(
                    $lawyer['user_id'],
                    'New Hearing Scheduled',
                    "A new hearing has been scheduled for case {$caseDetails['case_number']} on " . formatDateTime($hearingDateTime),
                    $caseId,
                    $hearingId
                );
            }
            
            // Get litigants associated with the case
            $db->query("SELECT user_id FROM case_parties WHERE case_id = :case_id AND user_id IS NOT NULL");
            $db->bind(':case_id', $caseId);
            $litigants = $db->resultSet();
            
            foreach ($litigants as $litigant) {
                sendNotification(
                    $litigant['user_id'],
                    'New Hearing Scheduled',
                    "A new hearing has been scheduled for case {$caseDetails['case_number']} on " . formatDateTime($hearingDateTime),
                    $caseId,
                    $hearingId
                );
            }
            
            // Set success message
            setFlashMessage('Hearing scheduled successfully.', 'success');
            
            // Redirect to case view page
            redirect('case_view.php?id=' . $caseId);
        } catch (Exception $e) {
            $errors[] = 'An error occurred while scheduling the hearing: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Schedule Hearing</h1>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error!</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Hearing Information</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($caseId ? '?case_id=' . $caseId : '')); ?>" method="post">
                <?php if (!$caseId): ?>
                <div class="mb-3">
                    <label for="caseId" class="form-label required-field">Case</label>
                    <select class="form-select" id="caseId" name="case_id" required>
                        <option value="">Select Case</option>
                        <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>">
                            <?php echo $case['case_number'] . ' - ' . $case['case_title']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                <div class="mb-3">
                    <label class="form-label">Case</label>
                    <p class="form-control-static">
                        <?php echo $case['case_number'] . ' - ' . $case['case_title']; ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hearingDate" class="form-label required-field">Hearing Date</label>
                            <input type="date" class="form-control" id="hearingDate" name="hearing_date" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hearingTime" class="form-label required-field">Hearing Time</label>
                            <input type="time" class="form-control" id="hearingTime" name="hearing_time" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hearingType" class="form-label required-field">Hearing Type</label>
                            <select class="form-select" id="hearingType" name="hearing_type" required>
                                <option value="">Select Type</option>
                                <option value="Initial Appearance">Initial Appearance</option>
                                <option value="Arraignment">Arraignment</option>
                                <option value="Status Conference">Status Conference</option>
                                <option value="Pre-Trial Conference">Pre-Trial Conference</option>
                                <option value="Trial">Trial</option>
                                <option value="Motion Hearing">Motion Hearing</option>
                                <option value="Sentencing">Sentencing</option>
                                <option value="Appeal Hearing">Appeal Hearing</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="location" class="form-label required-field">Location</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="<?php echo $caseId ? 'case_view.php?id=' . $caseId : 'hearings.php'; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Schedule Hearing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
