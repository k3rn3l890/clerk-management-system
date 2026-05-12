<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to add cases
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Initialize variables
$errors = [];
$success = false;

// Get judges for dropdown
$db->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'judge' AND status = 'active'");
$judges = $db->resultSet();

// Get lawyers for dropdown
$db->query("SELECT l.lawyer_id, l.bar_number, u.first_name, u.last_name 
            FROM lawyers l 
            JOIN users u ON l.user_id = u.user_id 
            WHERE u.status = 'active'");
$lawyers = $db->resultSet();

// Get litigants for linking parties to user accounts (optional)
$db->query("SELECT user_id, first_name, last_name, email FROM users WHERE role = 'litigant' AND status = 'active'");
$litigants = $db->resultSet();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $caseTitle = sanitize($_POST['case_title']);
    $caseType = sanitize($_POST['case_type']);
    $filingDate = sanitize($_POST['filing_date']);
    $description = sanitize($_POST['description']);
    $assignedJudge = !empty($_POST['assigned_judge']) ? (int)$_POST['assigned_judge'] : null;
    $caseLawyers = isset($_POST['case_lawyers']) ? $_POST['case_lawyers'] : [];
    
    // Get external lawyer information
    $externalLawyerNames = isset($_POST['external_lawyer_name']) ? $_POST['external_lawyer_name'] : [];
    $externalLawyerBarNumbers = isset($_POST['external_lawyer_bar']) ? $_POST['external_lawyer_bar'] : [];
    $externalLawyerContacts = isset($_POST['external_lawyer_contact']) ? $_POST['external_lawyer_contact'] : [];
    $externalLawyerFirms = isset($_POST['external_lawyer_firm']) ? $_POST['external_lawyer_firm'] : [];
    
    // Get party information
    $partyNames = isset($_POST['party_name']) ? $_POST['party_name'] : [];
    $partyTypes = isset($_POST['party_type']) ? $_POST['party_type'] : [];
    $partyContacts = isset($_POST['party_contact']) ? $_POST['party_contact'] : [];
    // Optional mapping of a party to an existing litigant user account
    $partyUserIds = isset($_POST['party_user_id']) ? $_POST['party_user_id'] : [];
    
    // Get party lawyer assignments
    $partyLawyers = isset($_POST['party_lawyer']) ? $_POST['party_lawyer'] : [];
    $partyExternalLawyers = isset($_POST['party_external_lawyer']) ? $_POST['party_external_lawyer'] : [];
    
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
    
    if (empty($partyNames) || count($partyNames) < 2) {
        $errors[] = 'At least two parties are required for a case';
    }
    
    // If no errors, proceed with saving the case
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Generate case number
            $caseNumber = generateCaseNumber();
            
            // Insert case
            $db->query("INSERT INTO cases (case_number, case_title, case_type, filing_date, status, description, assigned_judge, created_by) 
                        VALUES (:case_number, :case_title, :case_type, :filing_date, :status, :description, :assigned_judge, :created_by)");
            
            $db->bind(':case_number', $caseNumber);
            $db->bind(':case_title', $caseTitle);
            $db->bind(':case_type', $caseType);
            $db->bind(':filing_date', $filingDate);
            $db->bind(':status', 'pending');
            $db->bind(':description', $description);
            $db->bind(':assigned_judge', $assignedJudge);
            $db->bind(':created_by', $_SESSION['user_id']);
            
            $db->execute();
            
            // Get the last inserted case ID
            $caseId = $db->lastInsertId();
            
            // Insert parties and assign lawyers to parties
            for ($i = 0; $i < count($partyNames); $i++) {
                if (!empty($partyNames[$i]) && !empty($partyTypes[$i])) {
                    $db->query("INSERT INTO case_parties (case_id, party_type, party_name, contact_info, user_id) 
                                VALUES (:case_id, :party_type, :party_name, :contact_info, :user_id)");
                    
                    $db->bind(':case_id', $caseId);
                    $db->bind(':party_type', $partyTypes[$i]);
                    $db->bind(':party_name', $partyNames[$i]);
                    $db->bind(':contact_info', $partyContacts[$i] ?? null);
                    // Bind linked litigant user if provided, else NULL
                    $linkedUserId = null;
                    if (isset($partyUserIds[$i]) && $partyUserIds[$i] !== '') {
                        $candidate = (int)$partyUserIds[$i];
                        $linkedUserId = $candidate > 0 ? $candidate : null;
                    }
                    $db->bind(':user_id', $linkedUserId);
                    
                    $db->execute();
                    
                    // Get the last inserted party ID
                    $partyId = $db->lastInsertId();
                    
                    // Assign registered lawyers to this party
                    if (isset($partyLawyers[$i]) && is_array($partyLawyers[$i])) {
                        foreach ($partyLawyers[$i] as $lawyerId) {
                            if (!empty($lawyerId)) {
                                $db->query("INSERT INTO party_lawyers (case_party_id, lawyer_id) 
                                            VALUES (:case_party_id, :lawyer_id)");
                                
                                $db->bind(':case_party_id', $partyId);
                                $db->bind(':lawyer_id', $lawyerId);
                                
                                $db->execute();
                            }
                        }
                    }
                    
                    // Handle external lawyer for this party
                    if (isset($partyExternalLawyers[$i]) && $partyExternalLawyers[$i] === 'new') {
                        // If a new external lawyer is added for this party, create it
                        if (isset($externalLawyerNames[$i]) && !empty($externalLawyerNames[$i])) {
                            $db->query("INSERT INTO external_lawyers (case_id, lawyer_name, bar_number, contact_info, law_firm) 
                                        VALUES (:case_id, :lawyer_name, :bar_number, :contact_info, :law_firm)");
                            
                            $db->bind(':case_id', $caseId);
                            $db->bind(':lawyer_name', $externalLawyerNames[$i]);
                            $db->bind(':bar_number', $externalLawyerBarNumbers[$i] ?? null);
                            $db->bind(':contact_info', $externalLawyerContacts[$i] ?? null);
                            $db->bind(':law_firm', $externalLawyerFirms[$i] ?? null);
                            
                            $db->execute();
                            
                            // Get the last inserted external lawyer ID
                            $externalLawyerId = $db->lastInsertId();
                            
                            // Link the external lawyer to the party
                            $db->query("INSERT INTO party_lawyers (case_party_id, external_lawyer_id) 
                                        VALUES (:case_party_id, :external_lawyer_id)");
                            
                            $db->bind(':case_party_id', $partyId);
                            $db->bind(':external_lawyer_id', $externalLawyerId);
                            
                            $db->execute();
                        }
                    }
                }
            }
            
            // Insert case lawyers
            if (!empty($caseLawyers)) {
                foreach ($caseLawyers as $lawyerId) {
                    $db->query("INSERT INTO case_lawyers (case_id, lawyer_id, assigned_at) 
                                VALUES (:case_id, :lawyer_id, NOW())");
                    
                    $db->bind(':case_id', $caseId);
                    $db->bind(':lawyer_id', $lawyerId);
                    
                    $db->execute();
                    
                    // Get lawyer details for notification
                    $db->query("SELECT u.user_id, u.first_name, u.last_name 
                                FROM lawyers l 
                                JOIN users u ON l.user_id = u.user_id 
                                WHERE l.lawyer_id = :lawyer_id");
                    $db->bind(':lawyer_id', $lawyerId);
                    $lawyer = $db->single();
                    
                    // Notify the lawyer if they have a user account
                    if ($lawyer && isset($lawyer['user_id'])) {
                        sendNotification(
                            $lawyer['user_id'],
                            'New Case Assignment',
                            "You have been assigned to a new case: $caseNumber - $caseTitle",
                            $caseId
                        );
                    }
                }
            }
            
            // Insert external lawyers
            for ($i = 0; $i < count($externalLawyerNames); $i++) {
                if (!empty($externalLawyerNames[$i])) {
                    $db->query("INSERT INTO external_lawyers (case_id, lawyer_name, bar_number, contact_info, law_firm) 
                                VALUES (:case_id, :lawyer_name, :bar_number, :contact_info, :law_firm)");
                    
                    $db->bind(':case_id', $caseId);
                    $db->bind(':lawyer_name', $externalLawyerNames[$i]);
                    $db->bind(':bar_number', $externalLawyerBarNumbers[$i] ?? null);
                    $db->bind(':contact_info', $externalLawyerContacts[$i] ?? null);
                    $db->bind(':law_firm', $externalLawyerFirms[$i] ?? null);
                    
                    $db->execute();
                }
            }
            
            // Commit transaction
            $db->endTransaction();
            
            // Log action
            logAction('Case created', 'cases', $caseId);
            
            // Set success message
            setFlashMessage('Case created successfully.', 'success');
            
            // If assigned to a judge, send notification
            if ($assignedJudge) {
                sendNotification(
                    $assignedJudge,
                    'New Case Assignment',
                    "You have been assigned to a new case: $caseNumber - $caseTitle",
                    $caseId
                );
            }
            
            // Use JavaScript redirect instead of PHP header redirect to avoid 'headers already sent' error
            echo "<script>window.location.href = 'case_view.php?id=$caseId';</script>";
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->cancelTransaction();
            $errors[] = 'An error occurred while creating the case: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Add New Case</h1>
    
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
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="caseForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="caseTitle" class="form-label required-field">Case Title</label>
                            <input type="text" class="form-control" id="caseTitle" name="case_title" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="caseType" class="form-label required-field">Case Type</label>
                            <select class="form-select" id="caseType" name="case_type" required>
                                <option value="">Select Type</option>
                                <option value="civil">Civil</option>
                                <option value="criminal">Criminal</option>
                                <option value="family">Family</option>
                                <option value="commercial">Commercial</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="filingDate" class="form-label required-field">Filing Date</label>
                            <input type="date" class="form-control" id="filingDate" name="filing_date" value="<?php echo date('Y-m-d'); ?>" readonly required>
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
                                <option value="<?php echo $judge['user_id']; ?>">
                                    <?php echo $judge['first_name'] . ' ' . $judge['last_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Case Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mb-3">Lawyers</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="caseLawyers" class="form-label">Registered Lawyers</label>
                            <select class="form-select" id="caseLawyers" name="case_lawyers[]" multiple size="4">
                                <?php foreach ($lawyers as $lawyer): ?>
                                <option value="<?php echo $lawyer['lawyer_id']; ?>">
                                    <?php echo $lawyer['first_name'] . ' ' . $lawyer['last_name']; ?> (Bar #: <?php echo $lawyer['bar_number']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple lawyers</small>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>External Lawyers (not registered in the system)</h6>
                    <div id="externalLawyersContainer">
                        <!-- Initial external lawyer row -->
                        <div class="external-lawyer-row mb-3 border p-3 rounded">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Lawyer Name</label>
                                        <input type="text" class="form-control" name="external_lawyer_name[]" placeholder="Full Name">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">Bar Number</label>
                                        <input type="text" class="form-control" name="external_lawyer_bar[]" placeholder="Bar #">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Contact Info</label>
                                        <input type="text" class="form-control" name="external_lawyer_contact[]" placeholder="Phone/Email">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Law Firm</label>
                                        <input type="text" class="form-control" name="external_lawyer_firm[]" placeholder="Law Firm">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger remove-external-lawyer"><i class="fas fa-times"></i> Remove</button>
                        </div>
                    </div>
                    
                    <button type="button" id="addExternalLawyer" class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Add Another External Lawyer
                    </button>
                </div>
                
                <hr class="my-4">
                
                <h5 class="mb-3">Case Parties</h5>
                <div id="partiesContainer">
                    <!-- Initial party rows will be added dynamically -->
                    <div class="party-row mb-3 border p-3 rounded">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label for="partyName0" class="form-label required-field">Party Name</label>
                                    <input type="text" class="form-control" id="partyName0" name="party_name[]" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="partyType0" class="form-label required-field">Party Type</label>
                                    <select class="form-select" id="partyType0" name="party_type[]" required>
                                        <option value="">Select Type</option>
                                        <option value="plaintiff">Plaintiff</option>
                                        <option value="defendant">Defendant</option>
                                        <option value="appellant">Appellant</option>
                                        <option value="respondent">Respondent</option>
                                        <option value="witness">Witness</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="partyContact0" class="form-label">Contact Info</label>
                                    <input type="text" class="form-control" id="partyContact0" name="party_contact[]">
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
                                        <option value="<?php echo (int)$l['user_id']; ?>"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name'] . ' (' . $l['email'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select the litigant account if this party has a user login.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="party-row mb-3 border p-3 rounded">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label for="partyName1" class="form-label required-field">Party Name</label>
                                    <input type="text" class="form-control" id="partyName1" name="party_name[]" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="partyType1" class="form-label required-field">Party Type</label>
                                    <select class="form-select" id="partyType1" name="party_type[]" required>
                                        <option value="">Select Type</option>
                                        <option value="plaintiff">Plaintiff</option>
                                        <option value="defendant">Defendant</option>
                                        <option value="appellant">Appellant</option>
                                        <option value="respondent">Respondent</option>
                                        <option value="witness">Witness</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="partyContact1" class="form-label">Contact Info</label>
                                    <input type="text" class="form-control" id="partyContact1" name="party_contact[]">
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
                                        <option value="<?php echo (int)$l['user_id']; ?>"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name'] . ' (' . $l['email'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-party"><i class="fas fa-times"></i> Remove</button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <button type="button" id="addParty" class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Add Another Party
                    </button>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-between">
                    <a href="cases.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- Add custom JavaScript for the lawyer selection interface -->
<script>
// Ensure jQuery is loaded
if (typeof jQuery === 'undefined') {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
}

// Use window.onload to ensure DOM is fully loaded
window.onload = function() {
    // Initialize Select2 for better lawyer selection experience
    if ($.fn.select2) {
        $('#caseLawyers').select2({
            placeholder: 'Select lawyers for this case',
            allowClear: true,
            width: '100%'
        });
        
        $('.party-lawyer').select2({
            placeholder: 'Select lawyers for this party',
            allowClear: true,
            width: '100%'
        });
    }
    
        // Handle external lawyer selection for parties
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id && e.target.id.startsWith('partyExternalLawyer')) {
            var index = e.target.id.replace('partyExternalLawyer', '');
            var value = e.target.value;
            
            // If 'Add New External Lawyer' is selected, show a form to add a new external lawyer
            if (value === 'new') {
                // Create a new external lawyer form for this party if it doesn't exist
                var containerId = 'externalLawyerForm' + index;
                var container = document.getElementById(containerId);
                
                if (!container) {
                    container = document.createElement('div');
                    container.id = containerId;
                    container.className = 'mt-2 p-2 border rounded';
                    container.innerHTML = `
                        <h6>New External Lawyer for this Party</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Lawyer Name</label>
                                    <input type="text" class="form-control" name="external_lawyer_name[${index}]" placeholder="Full Name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Bar Number</label>
                                    <input type="text" class="form-control" name="external_lawyer_bar[${index}]" placeholder="Bar #">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Contact Info</label>
                                    <input type="text" class="form-control" name="external_lawyer_contact[${index}]" placeholder="Phone/Email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <label class="form-label">Law Firm</label>
                                    <input type="text" class="form-control" name="external_lawyer_firm[${index}]" placeholder="Law Firm">
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Add the form after the select element
                    e.target.parentNode.appendChild(container);
                }
            } else {
                // If any other option is selected, remove the external lawyer form if it exists
                var containerId = 'externalLawyerForm' + index;
                var container = document.getElementById(containerId);
                if (container) {
                    container.remove();
                }
            }
        }
    });
    
    // Handle adding external lawyer rows - using direct event binding
    document.getElementById('addExternalLawyer').addEventListener('click', function() {
        var externalLawyersContainer = document.getElementById('externalLawyersContainer');
        var newRow = document.createElement('div');
        newRow.className = 'external-lawyer-row mb-3 border p-3 rounded';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Lawyer Name</label>
                        <input type="text" class="form-control" name="external_lawyer_name[]" placeholder="Full Name">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">Bar Number</label>
                        <input type="text" class="form-control" name="external_lawyer_bar[]" placeholder="Bar #">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Contact Info</label>
                        <input type="text" class="form-control" name="external_lawyer_contact[]" placeholder="Phone/Email">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Law Firm</label>
                        <input type="text" class="form-control" name="external_lawyer_firm[]" placeholder="Law Firm">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-danger remove-external-lawyer"><i class="fas fa-times"></i> Remove</button>
        `;
        externalLawyersContainer.appendChild(newRow);
    });
    
    // Handle removing external lawyer rows
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-external-lawyer') || 
            (e.target.parentElement && e.target.parentElement.classList.contains('remove-external-lawyer'))) {
            var button = e.target.classList.contains('remove-external-lawyer') ? e.target : e.target.parentElement;
            var row = button.closest('.external-lawyer-row');
            if (row) {
                row.remove();
            }
        }
    });
    
    // Handle adding party rows
    var partyIndex = 2; // Start from 2 since we already have 0 and 1
    
    document.getElementById('addParty').addEventListener('click', function() {
        var partiesContainer = document.getElementById('partiesContainer');
        var newRow = document.createElement('div');
        newRow.className = 'party-row mb-3 border p-3 rounded';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-5">
                    <div class="mb-3">
                        <label for="partyName${partyIndex}" class="form-label required-field">Party Name</label>
                        <input type="text" class="form-control" id="partyName${partyIndex}" name="party_name[]" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="partyType${partyIndex}" class="form-label required-field">Party Type</label>
                        <select class="form-select" id="partyType${partyIndex}" name="party_type[]" required>
                            <option value="">Select Type</option>
                            <option value="plaintiff">Plaintiff</option>
                            <option value="defendant">Defendant</option>
                            <option value="appellant">Appellant</option>
                            <option value="respondent">Respondent</option>
                            <option value="witness">Witness</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="partyContact${partyIndex}" class="form-label">Contact Info</label>
                        <input type="text" class="form-control" id="partyContact${partyIndex}" name="party_contact[]">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="partyLawyer${partyIndex}" class="form-label">Assigned Lawyer</label>
                        <select class="form-select party-lawyer" id="partyLawyer${partyIndex}" name="party_lawyer[${partyIndex}][]" multiple>
                            <option value="">None</option>
                            <?php foreach ($lawyers as $lawyer): ?>
                            <option value="<?php echo $lawyer['lawyer_id']; ?>">
                                <?php echo $lawyer['first_name'] . ' ' . $lawyer['last_name']; ?> (Bar #: <?php echo $lawyer['bar_number']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple lawyers</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="partyExternalLawyer${partyIndex}" class="form-label">External Lawyer</label>
                        <select class="form-select" id="partyExternalLawyer${partyIndex}" name="party_external_lawyer[${partyIndex}]">
                            <option value="">None</option>
                            <option value="new">Add New External Lawyer</option>
                        </select>
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
                            <option value="<?php echo (int)$l['user_id']; ?>"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name'] . ' (' . $l['email'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-danger remove-party"><i class="fas fa-times"></i> Remove</button>
        `;
        partiesContainer.appendChild(newRow);
        partyIndex++;
    });
    
    // Handle removing party rows
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-party') || 
            (e.target.parentElement && e.target.parentElement.classList.contains('remove-party'))) {
            var button = e.target.classList.contains('remove-party') ? e.target : e.target.parentElement;
            var row = button.closest('.party-row');
            if (row) {
                row.remove();
            }
        }
    });
};
</script>
