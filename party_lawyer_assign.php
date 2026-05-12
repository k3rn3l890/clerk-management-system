<?php
require_once 'includes/header.php';

// Function to check if a column exists in a table
function columnExists($db, $tableName, $columnName) {
    try {
        $db->query("SHOW COLUMNS FROM $tableName LIKE :columnName");
        $db->bind(':columnName', $columnName);
        return $db->single() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if case ID and party ID are provided
if (!isset($_GET['case_id']) || empty($_GET['case_id']) || !isset($_GET['party_id']) || empty($_GET['party_id'])) {
    setFlashMessage('Invalid request.', 'danger');
    redirect('cases.php');
}

$caseId = (int)$_GET['case_id'];
$partyId = (int)$_GET['party_id'];

// Initialize database
$db = new Database();

// Get case details
$db->query("SELECT case_number, case_title FROM cases WHERE case_id = :case_id");
$db->bind(':case_id', $caseId);
$case = $db->single();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('cases.php');
}

// Get party details
$db->query("SELECT * FROM case_parties WHERE party_id = :party_id AND case_id = :case_id");
$db->bind(':party_id', $partyId);
$db->bind(':case_id', $caseId);
$party = $db->single();

if (!$party) {
    setFlashMessage('Party not found.', 'danger');
    redirect("case_view.php?id=$caseId");
}

// Get all registered lawyers
try {
    // Check if status column exists in lawyers table
    $lawyerStatusExists = columnExists($db, 'lawyers', 'status');
    
    if ($lawyerStatusExists) {
        $db->query("SELECT l.lawyer_id, l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name 
                    FROM lawyers l 
                    JOIN users u ON l.user_id = u.user_id 
                    WHERE l.status = 'active' AND u.status = 'active'
                    ORDER BY u.first_name, u.last_name");
    } else {
        $db->query("SELECT l.lawyer_id, l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name 
                    FROM lawyers l 
                    JOIN users u ON l.user_id = u.user_id 
                    WHERE u.status = 'active'
                    ORDER BY u.first_name, u.last_name");
    }
    $registeredLawyers = $db->resultSet();
} catch (Exception $e) {
    // Fallback to basic query without status filtering
    $db->query("SELECT l.lawyer_id, l.bar_number, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name 
                FROM lawyers l 
                JOIN users u ON l.user_id = u.user_id 
                ORDER BY u.first_name, u.last_name");
    $registeredLawyers = $db->resultSet();
}

// Get all external lawyers
try {
    // Check if external_lawyers table exists and has status column
    $externalStatusExists = columnExists($db, 'external_lawyers', 'status');
    
    if ($externalStatusExists) {
        $db->query("SELECT external_lawyer_id, lawyer_name, bar_number, law_firm 
                    FROM external_lawyers 
                    WHERE status = 'active'
                    ORDER BY lawyer_name");
    } else {
        $db->query("SELECT external_lawyer_id, lawyer_name, bar_number, law_firm 
                    FROM external_lawyers 
                    ORDER BY lawyer_name");
    }
    $externalLawyers = $db->resultSet();
} catch (Exception $e) {
    // If table doesn't exist or has errors, set empty array
    $externalLawyers = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lawyerType = $_POST['lawyer_type'] ?? '';
    $lawyerId = $_POST['lawyer_id'] ?? '';
    $externalLawyerId = $_POST['external_lawyer_id'] ?? '';
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Remove existing lawyer assignments for this party
        $db->query("DELETE FROM party_lawyers WHERE case_party_id = :party_id");
        $db->bind(':party_id', $partyId);
        $db->execute();
        
        // Add new lawyer assignment if selected
        if ($lawyerType === 'registered' && !empty($lawyerId)) {
            $db->query("INSERT INTO party_lawyers (case_party_id, lawyer_id) VALUES (:party_id, :lawyer_id)");
            $db->bind(':party_id', $partyId);
            $db->bind(':lawyer_id', $lawyerId);
            $db->execute();
        } elseif ($lawyerType === 'external' && !empty($externalLawyerId)) {
            $db->query("INSERT INTO party_lawyers (case_party_id, external_lawyer_id) VALUES (:party_id, :external_lawyer_id)");
            $db->bind(':party_id', $partyId);
            $db->bind(':external_lawyer_id', $externalLawyerId);
            $db->execute();
        }
        
        // Commit transaction
        $db->endTransaction();
        
        setFlashMessage('Lawyer assignment updated successfully.', 'success');
        redirect("case_view.php?id=$caseId");
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->cancelTransaction();
        setFlashMessage('Error updating lawyer assignment: ' . $e->getMessage(), 'danger');
    }
}

// Get current lawyer assignment
try {
    $db->query("SELECT pl.id as assignment_id, pl.lawyer_id, pl.external_lawyer_id, pl.case_party_id, 
                l.lawyer_id, CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
                el.external_lawyer_id, el.lawyer_name as external_lawyer_name
                FROM party_lawyers pl
                LEFT JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                LEFT JOIN users u ON l.user_id = u.user_id
                LEFT JOIN external_lawyers el ON pl.external_lawyer_id = el.external_lawyer_id
                WHERE pl.case_party_id = :party_id");
    $db->bind(':party_id', $partyId);
    $currentAssignment = $db->single();
    
    // Display the current assignment in flash message if it exists
    if ($currentAssignment) {
        if ($currentAssignment['lawyer_id']) {
            setFlashMessage('Current assignment: ' . $currentAssignment['lawyer_name'], 'info');
        } elseif ($currentAssignment['external_lawyer_id']) {
            setFlashMessage('Current assignment: ' . $currentAssignment['external_lawyer_name'], 'info');
        }
    } else {
        setFlashMessage('No lawyer currently assigned to this party.', 'info');
    }
} catch (Exception $e) {
    $currentAssignment = null;
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Assign Lawyer to Party</h1>
        <a href="case_view.php?id=<?php echo $caseId; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Case
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Case: <?php echo $case['case_number']; ?></h6>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <strong>Case Title:</strong> <?php echo $case['case_title']; ?><br>
                <strong>Party Name:</strong> <?php echo $party['party_name']; ?><br>
                <strong>Party Type:</strong> <?php echo ucfirst($party['party_type']); ?>
            </div>

            <form action="" method="POST">
                <div class="mb-3">
                    <label for="lawyer_type" class="form-label">Lawyer Type</label>
                    <select class="form-control" id="lawyer_type" name="lawyer_type" required>
                        <option value="">Select Lawyer Type</option>
                        <option value="registered" <?php echo ($currentAssignment && $currentAssignment['lawyer_id']) ? 'selected' : ''; ?>>Registered Lawyer</option>
                        <option value="external" <?php echo ($currentAssignment && $currentAssignment['external_lawyer_id']) ? 'selected' : ''; ?>>External Lawyer</option>
                        <option value="none">No Lawyer</option>
                    </select>
                </div>

                <div id="registered_lawyer_section" class="mb-3" style="display: none;">
                    <label for="lawyer_id" class="form-label">Select Registered Lawyer</label>
                    <select class="form-control" id="lawyer_id" name="lawyer_id">
                        <option value="">Select Lawyer</option>
                        <?php foreach ($registeredLawyers as $lawyer): ?>
                        <option value="<?php echo $lawyer['lawyer_id']; ?>" 
                                <?php echo ($currentAssignment && $currentAssignment['lawyer_id'] == $lawyer['lawyer_id']) ? 'selected' : ''; ?>>
                            <?php echo $lawyer['lawyer_name']; ?> (Bar #: <?php echo $lawyer['bar_number']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="external_lawyer_section" class="mb-3" style="display: none;">
                    <label for="external_lawyer_id" class="form-label">Select External Lawyer</label>
                    <select class="form-control" id="external_lawyer_id" name="external_lawyer_id">
                        <option value="">Select External Lawyer</option>
                        <?php foreach ($externalLawyers as $lawyer): ?>
                        <option value="<?php echo $lawyer['external_lawyer_id']; ?>"
                                <?php echo ($currentAssignment && $currentAssignment['external_lawyer_id'] == $lawyer['external_lawyer_id']) ? 'selected' : ''; ?>>
                            <?php echo $lawyer['lawyer_name']; ?> 
                            (Bar #: <?php echo $lawyer['bar_number'] ?: 'N/A'; ?>)
                            <?php echo $lawyer['law_firm'] ? ' - ' . $lawyer['law_firm'] : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-2">
                        <a href="external_lawyer_add.php?return=party_assign&case_id=<?php echo $caseId; ?>&party_id=<?php echo $partyId; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> Add New External Lawyer
                        </a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lawyerTypeSelect = document.getElementById('lawyer_type');
    const registeredSection = document.getElementById('registered_lawyer_section');
    const externalSection = document.getElementById('external_lawyer_section');
    const lawyerIdSelect = document.getElementById('lawyer_id');
    const externalLawyerIdSelect = document.getElementById('external_lawyer_id');
    
    function updateSections() {
        const selectedType = lawyerTypeSelect.value;
        
        registeredSection.style.display = 'none';
        externalSection.style.display = 'none';
        lawyerIdSelect.required = false;
        externalLawyerIdSelect.required = false;
        
        if (selectedType === 'registered') {
            registeredSection.style.display = 'block';
            lawyerIdSelect.required = true;
        } else if (selectedType === 'external') {
            externalSection.style.display = 'block';
            externalLawyerIdSelect.required = true;
        }
    }
    
    lawyerTypeSelect.addEventListener('change', updateSections);
    updateSections(); // Initial setup
});
</script>

<?php require_once 'includes/footer.php'; ?> 