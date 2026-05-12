<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to edit hearings
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if hearing ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid hearing ID.', 'danger');
    redirect('hearings.php');
}

$hearingId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get hearing details
$db->query("SELECT h.*, c.case_number, c.case_title 
            FROM hearings h 
            JOIN cases c ON h.case_id = c.case_id 
            WHERE h.hearing_id = :hearing_id");
$db->bind(':hearing_id', $hearingId);
$hearing = $db->single();

if (!$hearing) {
    setFlashMessage('Hearing not found.', 'danger');
    redirect('hearings.php');
}

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $hearingDate = sanitize($_POST['hearing_date']);
    $hearingTime = sanitize($_POST['hearing_time']);
    $hearingType = sanitize($_POST['hearing_type']);
    $location = sanitize($_POST['location']);
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes']);
    
    // Validate form data
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
    
    if (empty($status)) {
        $errors[] = 'Status is required';
    }
    
    // Combine date and time
    $hearingDateTime = $hearingDate . ' ' . $hearingTime;
    
    // If no errors, proceed with updating the hearing
    if (empty($errors)) {
        try {
            // Get old hearing data for audit log
            $oldHearing = $hearing;
            
            // Update hearing
            $db->query("UPDATE hearings SET 
                        hearing_date = :hearing_date, 
                        hearing_type = :hearing_type, 
                        location = :location, 
                        status = :status, 
                        notes = :notes 
                        WHERE hearing_id = :hearing_id");
            
            $db->bind(':hearing_date', $hearingDateTime);
            $db->bind(':hearing_type', $hearingType);
            $db->bind(':location', $location);
            $db->bind(':status', $status);
            $db->bind(':notes', $notes);
            $db->bind(':hearing_id', $hearingId);
            
            $db->execute();
            
            // Log action
            logAction('Hearing updated', 'hearings', $hearingId, $oldHearing, [
                'hearing_date' => $hearingDateTime,
                'hearing_type' => $hearingType,
                'location' => $location,
                'status' => $status,
                'notes' => $notes
            ]);
            
            // Check if status was changed
            $statusChanged = $oldHearing['status'] !== $status;
            
            // Check if date/time was changed
            $dateChanged = $oldHearing['hearing_date'] !== $hearingDateTime;
            
            // If status or date changed, send notifications
            if ($statusChanged || $dateChanged) {
                // Get case details for notifications
                $db->query("SELECT c.*, j.user_id as judge_id FROM cases c 
                            LEFT JOIN users j ON c.assigned_judge = j.user_id 
                            WHERE c.case_id = :case_id");
                $db->bind(':case_id', $hearing['case_id']);
                $caseDetails = $db->single();
                
                // Prepare notification message
                if ($statusChanged && $dateChanged) {
                    $message = "The hearing for case {$caseDetails['case_number']} has been updated. New status: " . 
                               ucfirst($status) . ". New date/time: " . formatDateTime($hearingDateTime);
                } elseif ($statusChanged) {
                    $message = "The status of the hearing for case {$caseDetails['case_number']} has been updated to " . 
                               ucfirst($status) . ".";
                } elseif ($dateChanged) {
                    $message = "The date/time of the hearing for case {$caseDetails['case_number']} has been changed to " . 
                               formatDateTime($hearingDateTime) . ".";
                }
                
                // Send notification to assigned judge
                if ($caseDetails['judge_id']) {
                    sendNotification(
                        $caseDetails['judge_id'],
                        'Hearing Updated',
                        $message,
                        $caseDetails['case_id'],
                        $hearingId
                    );
                }
                
                // Send notifications to lawyers and parties
                // Get lawyers associated with the case
                $db->query("SELECT DISTINCT l.user_id 
                            FROM case_lawyers cl 
                            JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                            WHERE cl.case_id = :case_id");
                $db->bind(':case_id', $hearing['case_id']);
                $lawyers = $db->resultSet();
                
                foreach ($lawyers as $lawyer) {
                    sendNotification(
                        $lawyer['user_id'],
                        'Hearing Updated',
                        $message,
                        $caseDetails['case_id'],
                        $hearingId
                    );
                }
                
                // Get litigants associated with the case
                $db->query("SELECT user_id FROM case_parties WHERE case_id = :case_id AND user_id IS NOT NULL");
                $db->bind(':case_id', $hearing['case_id']);
                $litigants = $db->resultSet();
                
                foreach ($litigants as $litigant) {
                    sendNotification(
                        $litigant['user_id'],
                        'Hearing Updated',
                        $message,
                        $caseDetails['case_id'],
                        $hearingId
                    );
                }
            }
            
            // Set success message
            setFlashMessage('Hearing updated successfully.', 'success');
            
            // Redirect to hearing view page
            redirect('hearing_view.php?id=' . $hearingId);
        } catch (Exception $e) {
            $errors[] = 'An error occurred while updating the hearing: ' . $e->getMessage();
        }
    }
}

// Extract date and time from hearing date
$hearingDateParts = explode(' ', $hearing['hearing_date']);
$hearingDate = $hearingDateParts[0];
$hearingTime = isset($hearingDateParts[1]) ? $hearingDateParts[1] : '00:00:00';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Edit Hearing</h1>
    
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
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $hearingId); ?>" method="post">
                <div class="mb-3">
                    <label class="form-label">Case</label>
                    <p class="form-control-static">
                        <a href="case_view.php?id=<?php echo $hearing['case_id']; ?>">
                            <?php echo $hearing['case_number'] . ' - ' . $hearing['case_title']; ?>
                        </a>
                    </p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hearingDate" class="form-label required-field">Hearing Date</label>
                            <input type="date" class="form-control" id="hearingDate" name="hearing_date" value="<?php echo $hearingDate; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hearingTime" class="form-label required-field">Hearing Time</label>
                            <input type="time" class="form-control" id="hearingTime" name="hearing_time" value="<?php echo substr($hearingTime, 0, 5); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hearingType" class="form-label required-field">Hearing Type</label>
                            <select class="form-select" id="hearingType" name="hearing_type" required>
                                <option value="">Select Type</option>
                                <option value="Initial Appearance" <?php echo ($hearing['hearing_type'] === 'Initial Appearance') ? 'selected' : ''; ?>>Initial Appearance</option>
                                <option value="Arraignment" <?php echo ($hearing['hearing_type'] === 'Arraignment') ? 'selected' : ''; ?>>Arraignment</option>
                                <option value="Status Conference" <?php echo ($hearing['hearing_type'] === 'Status Conference') ? 'selected' : ''; ?>>Status Conference</option>
                                <option value="Pre-Trial Conference" <?php echo ($hearing['hearing_type'] === 'Pre-Trial Conference') ? 'selected' : ''; ?>>Pre-Trial Conference</option>
                                <option value="Trial" <?php echo ($hearing['hearing_type'] === 'Trial') ? 'selected' : ''; ?>>Trial</option>
                                <option value="Motion Hearing" <?php echo ($hearing['hearing_type'] === 'Motion Hearing') ? 'selected' : ''; ?>>Motion Hearing</option>
                                <option value="Sentencing" <?php echo ($hearing['hearing_type'] === 'Sentencing') ? 'selected' : ''; ?>>Sentencing</option>
                                <option value="Appeal Hearing" <?php echo ($hearing['hearing_type'] === 'Appeal Hearing') ? 'selected' : ''; ?>>Appeal Hearing</option>
                                <option value="Other" <?php echo ($hearing['hearing_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="location" class="form-label required-field">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo $hearing['location']; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label required-field">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="scheduled" <?php echo ($hearing['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="completed" <?php echo ($hearing['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="postponed" <?php echo ($hearing['status'] === 'postponed') ? 'selected' : ''; ?>>Postponed</option>
                        <option value="cancelled" <?php echo ($hearing['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo $hearing['notes']; ?></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="hearing_view.php?id=<?php echo $hearingId; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Hearing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
