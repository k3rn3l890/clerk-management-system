<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to edit cases
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('cases.php');
}

$caseId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get case details
$db->query("SELECT * FROM cases WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$case = $db->single();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('cases.php');
}

// Get case parties
$db->query("SELECT * FROM case_parties WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$parties = $db->resultSet();

// Get judges for dropdown
$db->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'judge' AND status = 'active'");
$judges = $db->resultSet();

// Get litigants for linking parties to user accounts (optional)
$db->query("SELECT user_id, first_name, last_name, email FROM users WHERE role = 'litigant' AND status = 'active'");
$litigants = $db->resultSet();

// Initialize variables
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $caseTitle = sanitize($_POST['case_title']);
    $caseType = sanitize($_POST['case_type']);
    $filingDate = sanitize($_POST['filing_date']);
    $status = sanitize($_POST['status']);
    $description = sanitize($_POST['description']);
    $assignedJudge = !empty($_POST['assigned_judge']) ? (int)$_POST['assigned_judge'] : null;
    
    // Get party information
    $partyIds = isset($_POST['party_id']) ? $_POST['party_id'] : [];
    $partyNames = isset($_POST['party_name']) ? $_POST['party_name'] : [];
    $partyTypes = isset($_POST['party_type']) ? $_POST['party_type'] : [];
    $partyContacts = isset($_POST['party_contact']) ? $_POST['party_contact'] : [];
    $partyUserIds = isset($_POST['party_user_id']) ? $_POST['party_user_id'] : [];
    
    // Validate form data
    if (empty($caseTitle)) {
        $errors[] = 'Case title is required';
    }
    
    if (empty($caseType)) {
        $errors[] = 'Case type is required';
    }
    
    if (empty($filingDate)) {
        $errors[] = 'Filing date is required';
    } elseif (strtotime($filingDate) > time()) {
        $errors[] = 'Filing date cannot be in the future';
    }
    
    if (empty($status)) {
        $errors[] = 'Case status is required';
    }
    
    if (empty($partyNames) || count($partyNames) < 2) {
        $errors[] = 'At least two parties are required for a case';
    }
    
    // If no errors, proceed with updating the case
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Get old case data for audit log
            $oldCase = $case;
            
            // Update case
            $db->query("UPDATE cases SET 
                        case_title = :case_title, 
                        case_type = :case_type, 
                        filing_date = :filing_date, 
                        status = :status, 
                        description = :description, 
                        assigned_judge = :assigned_judge 
                        WHERE case_id = :case_id");
            
            $db->bind(':case_title', $caseTitle);
            $db->bind(':case_type', $caseType);
            $db->bind(':filing_date', $filingDate);
            $db->bind(':status', $status);
            $db->bind(':description', $description);
            $db->bind(':assigned_judge', $assignedJudge);
            $db->bind(':case_id', $caseId);
            
            $db->execute();
            
            // Update existing parties and add new ones
            $existingPartyIds = [];
            
            for ($i = 0; $i < count($partyNames); $i++) {
                if (!empty($partyNames[$i]) && !empty($partyTypes[$i])) {
                    if (!empty($partyIds[$i])) {
                        // Update existing party
                        $db->query("UPDATE case_parties SET 
                                    party_type = :party_type, 
                                    party_name = :party_name, 
                                    contact_info = :contact_info,
                                    user_id = :user_id
                                    WHERE party_id = :party_id AND case_id = :case_id");
                        
                        $db->bind(':party_type', $partyTypes[$i]);
                        $db->bind(':party_name', $partyNames[$i]);
                        $db->bind(':contact_info', $partyContacts[$i] ?? null);
                        $linkedUserId = null;
                        if (isset($partyUserIds[$i]) && $partyUserIds[$i] !== '') {
                            $candidate = (int)$partyUserIds[$i];
                            $linkedUserId = $candidate > 0 ? $candidate : null;
                        }
                        $db->bind(':user_id', $linkedUserId);
                        $db->bind(':party_id', $partyIds[$i]);
                        $db->bind(':case_id', $caseId);
                        
                        $db->execute();
                        
                        $existingPartyIds[] = $partyIds[$i];
                    } else {
                        // Add new party
                        $db->query("INSERT INTO case_parties (case_id, party_type, party_name, contact_info, user_id) 
                                    VALUES (:case_id, :party_type, :party_name, :contact_info, :user_id)");
                        
                        $db->bind(':case_id', $caseId);
                        $db->bind(':party_type', $partyTypes[$i]);
                        $db->bind(':party_name', $partyNames[$i]);
                        $db->bind(':contact_info', $partyContacts[$i] ?? null);
                        $linkedUserId = null;
                        if (isset($partyUserIds[$i]) && $partyUserIds[$i] !== '') {
                            $candidate = (int)$partyUserIds[$i];
                            $linkedUserId = $candidate > 0 ? $candidate : null;
                        }
                        $db->bind(':user_id', $linkedUserId);
                        
                        $db->execute();
                    }
                }
            }
            
            // Delete parties that were removed
            if (!empty($existingPartyIds)) {
                $placeholders = implode(',', array_fill(0, count($existingPartyIds), '?'));
                $db->query("DELETE FROM case_parties WHERE case_id = ? AND party_id NOT IN ($placeholders)");
                
                $params = array_merge([$caseId], $existingPartyIds);
                $db->execute($params);
            } else {
                $db->query("DELETE FROM case_parties WHERE case_id = ?");
                $db->execute([$caseId]);
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Get updated case data for audit log
            $db->query("SELECT * FROM cases WHERE case_id = :case_id");
            $db->bind(':case_id', $caseId);
            $newCase = $db->single();
            
            // Log action
            logAction('Case updated', 'cases', $caseId, $oldCase, $newCase);
            
            // Set success message
            setFlashMessage('Case updated successfully.', 'success');
            
            // If judge was changed, send notification to the new judge
            if ($assignedJudge && $assignedJudge != $oldCase['assigned_judge']) {
                sendNotification(
                    $assignedJudge,
                    'Case Assignment',
                    "You have been assigned to case: {$case['case_number']} - $caseTitle",
                    $caseId
                );
            }
            
            // Redirect to case view page
            redirect('case_view.php?id=' . $caseId);
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            $errors[] = 'An error occurred while updating the case: ' . $e->getMessage();
        }
    }
}

// Get updated case parties (in case of validation error)
if (!empty($errors)) {
    $db->query("SELECT * FROM case_parties WHERE case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $parties = $db->resultSet();
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Edit Case</h1>
    
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
            <h6 class="m-0 font-weight-bold text-primary">Case Information</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $caseId); ?>" method="post" id="caseForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="caseNumber" class="form-label">Case Number</label>
                            <input type="text" class="form-control" id="caseNumber" value="<?php echo $case['case_number']; ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="caseTitle" class="form-label required-field">Case Title</label>
                            <input type="text" class="form-control" id="caseTitle" name="case_title" value="<?php echo $case['case_title']; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="caseType" class="form-label required-field">Case Type</label>
                            <select class="form-select" id="caseType" name="case_type" required>
                                <option value="">Select Type</option>
                                <option value="civil" <?php echo ($case['case_type'] === 'civil') ? 'selected' : ''; ?>>Civil</option>
                                <option value="criminal" <?php echo ($case['case_type'] === 'criminal') ? 'selected' : ''; ?>>Criminal</option>
                                <option value="family" <?php echo ($case['case_type'] === 'family') ? 'selected' : ''; ?>>Family</option>
                                <option value="commercial" <?php echo ($case['case_type'] === 'commercial') ? 'selected' : ''; ?>>Commercial</option>
                                <option value="other" <?php echo ($case['case_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="filingDate" class="form-label required-field">Filing Date</label>
                            <input type="date" class="form-control" id="filingDate" name="filing_date" value="<?php echo $case['filing_date']; ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="assignedJudge" class="form-label">Assigned Judge</label>
                            <select class="form-select" id="assignedJudge" name="assigned_judge">
                                <option value="">Select Judge</option>
                                <?php foreach ($judges as $judge): ?>
                                <option value="<?php echo $judge['user_id']; ?>" <?php echo ($case['assigned_judge'] == $judge['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo $judge['first_name'] . ' ' . $judge['last_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label required-field">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo ($case['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo ($case['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo ($case['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                <option value="appealed" <?php echo ($case['status'] === 'appealed') ? 'selected' : ''; ?>>Appealed</option>
                                <option value="archived" <?php echo ($case['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Case Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo $case['description']; ?></textarea>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mb-3">Case Parties</h5>
                <div id="partiesContainer">
                    <?php foreach ($parties as $index => $party): ?>
                    <div class="party-row mb-3 border p-3 rounded">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label for="partyName<?php echo $index; ?>" class="form-label required-field">Party Name</label>
                                    <input type="hidden" name="party_id[]" value="<?php echo $party['party_id']; ?>">
                                    <input type="text" class="form-control" id="partyName<?php echo $index; ?>" name="party_name[]" value="<?php echo $party['party_name']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="partyType<?php echo $index; ?>" class="form-label required-field">Party Type</label>
                                    <select class="form-select" id="partyType<?php echo $index; ?>" name="party_type[]" required>
                                        <option value="">Select Type</option>
                                        <option value="plaintiff" <?php echo ($party['party_type'] === 'plaintiff') ? 'selected' : ''; ?>>Plaintiff</option>
                                        <option value="defendant" <?php echo ($party['party_type'] === 'defendant') ? 'selected' : ''; ?>>Defendant</option>
                                        <option value="appellant" <?php echo ($party['party_type'] === 'appellant') ? 'selected' : ''; ?>>Appellant</option>
                                        <option value="respondent" <?php echo ($party['party_type'] === 'respondent') ? 'selected' : ''; ?>>Respondent</option>
                                        <option value="witness" <?php echo ($party['party_type'] === 'witness') ? 'selected' : ''; ?>>Witness</option>
                                        <option value="other" <?php echo ($party['party_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="partyContact<?php echo $index; ?>" class="form-label">Contact Info</label>
                                    <input type="text" class="form-control" id="partyContact<?php echo $index; ?>" name="party_contact[]" value="<?php echo $party['contact_info']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Link to Litigant User (optional)</label>
                                    <select class="form-select" name="party_user_id[]">
                                        <option value="">-- None --</option>
                                        <?php foreach ($litigants as $l): ?>
                                        <option value="<?php echo (int)$l['user_id']; ?>" <?php echo (!empty($party['user_id']) && (int)$party['user_id'] === (int)$l['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name'] . ' (' . $l['email'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select the litigant account if this party has a user login.</small>
                                </div>
                            </div>
                        </div>
                        <?php if (count($parties) > 2): ?>
                        <button type="button" class="btn btn-sm btn-danger remove-party"><i class="fas fa-times"></i> Remove</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mb-3">
                    <button type="button" id="addParty" class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Add Another Party
                    </button>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-between">
                    <a href="case_view.php?id=<?php echo $caseId; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
