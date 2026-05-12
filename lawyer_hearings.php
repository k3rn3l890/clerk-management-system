<?php
require_once 'includes/header.php';

// Redirect if not logged in or not a lawyer
if (!isLoggedIn() || !hasRole(['lawyer'])) {
    redirect('login.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];

// Check if filtering by case
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$caseTitle = '';

if ($caseId > 0) {
    // Verify that the lawyer has access to this case (via party_lawyers)
    $db->query("SELECT c.case_number, c.case_title FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE c.case_id = :case_id AND l.user_id = :user_id");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $userId);
    $caseResult = $db->single();
    
    if (!$caseResult) {
        setFlashMessage('You do not have permission to view hearings for this case.', 'danger');
        redirect('lawyer_cases.php');
    }
    
    $caseTitle = $caseResult['case_title'];
}

// Get hearings for this lawyer
if ($caseId > 0) {
    // Get hearings for a specific case
    $db->query("SELECT h.*, c.case_number, c.case_title as case_title, c.status as case_status,
                CONCAT(j.first_name, ' ', j.last_name) as judge_name
                FROM hearings h
                JOIN cases c ON h.case_id = c.case_id
                LEFT JOIN users j ON c.assigned_judge = j.user_id
                JOIN case_parties cp ON c.case_id = cp.case_id
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE h.case_id = :case_id AND l.user_id = :user_id
                ORDER BY h.hearing_date DESC");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $userId);
} else {
    // Get all hearings for this lawyer
    $db->query("SELECT h.*, c.case_number, c.case_title as case_title, c.status as case_status,
                CONCAT(j.first_name, ' ', j.last_name) as judge_name
                FROM hearings h
                JOIN cases c ON h.case_id = c.case_id
                LEFT JOIN users j ON c.assigned_judge = j.user_id
                JOIN case_parties cp ON c.case_id = cp.case_id
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id
                ORDER BY h.hearing_date DESC");
    $db->bind(':user_id', $userId);
}

$hearings = $db->resultSet();

// Get hearing statuses for filtering
$statuses = ['scheduled', 'completed', 'cancelled', 'postponed'];
$currentStatus = isset($_GET['status']) && in_array($_GET['status'], $statuses) ? $_GET['status'] : '';

// Filter hearings by status if requested
if (!empty($currentStatus)) {
    if ($caseId > 0) {
        $db->query("SELECT h.*, c.case_number, c.case_title as case_title, c.status as case_status,
                    CONCAT(j.first_name, ' ', j.last_name) as judge_name
                    FROM hearings h
                    JOIN cases c ON h.case_id = c.case_id
                    LEFT JOIN users j ON c.assigned_judge = j.user_id
                    JOIN case_parties cp ON c.case_id = cp.case_id
                    JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
                    JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                    WHERE h.case_id = :case_id AND l.user_id = :user_id AND h.status = :status
                    ORDER BY h.hearing_date DESC");
        $db->bind(':case_id', $caseId);
        $db->bind(':user_id', $userId);
        $db->bind(':status', $currentStatus);
    } else {
        $db->query("SELECT h.*, c.case_number, c.case_title as case_title, c.status as case_status,
                    CONCAT(j.first_name, ' ', j.last_name) as judge_name
                    FROM hearings h
                    JOIN cases c ON h.case_id = c.case_id
                    LEFT JOIN users j ON c.assigned_judge = j.user_id
                    JOIN case_parties cp ON c.case_id = cp.case_id
                    JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
                    JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                    WHERE l.user_id = :user_id AND h.status = :status
                    ORDER BY h.hearing_date DESC");
        $db->bind(':user_id', $userId);
        $db->bind(':status', $currentStatus);
    }
    $hearings = $db->resultSet();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo $caseId > 0 ? 'Hearings for Case: ' . $caseTitle : 'My Hearings'; ?>
        </h1>
        <?php if ($caseId > 0): ?>
        <a href="lawyer_cases.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to My Cases
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Filter Options -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Options</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="statusFilter">Filter by Status</label>
                        <select class="form-control" id="statusFilter" onchange="window.location.href = this.value;">
                            <option value="<?php echo $caseId > 0 ? 'lawyer_hearings.php?case_id=' . $caseId : 'lawyer_hearings.php'; ?>">All Hearings</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $caseId > 0 ? 'lawyer_hearings.php?case_id=' . $caseId . '&status=' . $status : 'lawyer_hearings.php?status=' . $status; ?>" <?php echo ($currentStatus == $status) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?> Hearings
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hearings Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo !empty($currentStatus) ? ucfirst($currentStatus) . ' Hearings' : 'All Hearings'; ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($hearings)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No hearings found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered <?php echo (count($hearings) > 0) ? 'datatable' : ''; ?>" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <?php if ($caseId == 0): ?>
                                <th>Case</th>
                                <?php endif; ?>
                                <th>Hearing Type</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>Judge</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hearings as $hearing): ?>
                                <tr>
                                    <?php if ($caseId == 0): ?>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $hearing['case_id']; ?>">
                                            <?php echo $hearing['case_number']; ?>
                                        </a>
                                        <div><small><?php echo $hearing['case_title']; ?></small></div>
                                    </td>
                                    <?php endif; ?>
                                    <td><?php echo $hearing['hearing_type']; ?></td>
                                    <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                    <td><?php echo $hearing['location']; ?></td>
                                    <td><?php echo $hearing['judge_name'] ?: 'Not Assigned'; ?></td>
                                    <td><?php echo getHearingStatusLabel($hearing['status']); ?></td>
                                    <td>
                                        <a href="hearing_view.php?id=<?php echo $hearing['hearing_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
