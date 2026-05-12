<?php
require_once 'includes/header.php';

// Redirect if not logged in or not a litigant
if (!isLoggedIn() || !hasRole(['litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];

// Get case IDs where user is a party
$db->query("SELECT case_id FROM case_parties WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$caseResults = $db->resultSet();
$caseIds = array_column($caseResults, 'case_id');

// Get all hearings for these cases
if (!empty($caseIds)) {
    $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
    
    $db->query("SELECT h.*, c.case_number, c.case_title
                FROM hearings h
                JOIN cases c ON h.case_id = c.case_id
                WHERE h.case_id IN ($placeholders)
                ORDER BY h.hearing_date DESC");
    
    foreach ($caseIds as $index => $caseId) {
        $db->bind($index + 1, $caseId);
    }
    
    $hearings = $db->resultSet();
} else {
    $hearings = [];
}

// Group hearings by status
$upcomingHearings = array_filter($hearings, function($h) { return $h['status'] === 'scheduled'; });
$completedHearings = array_filter($hearings, function($h) { return $h['status'] === 'completed'; });
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Hearings</h1>
        <div>
            <a href="litigant_cases.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to My Cases
            </a>
        </div>
    </div>

    <!-- Upcoming Hearings Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Hearings</h6>
        </div>
        <div class="card-body">
            <?php if (!empty($upcomingHearings)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="upcomingHearingsTable">
                        <thead>
                            <tr>
                                <th>Case Number</th>
                                <th>Case Title</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingHearings as $hearing): ?>
                            <tr>
                                <td><?php echo $hearing['case_number']; ?></td>
                                <td><?php echo $hearing['case_title']; ?></td>
                                <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                <td><?php echo $hearing['hearing_type']; ?></td>
                                <td><?php echo $hearing['location']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">No upcoming hearings found</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed Hearings Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Completed Hearings</h6>
        </div>
        <div class="card-body">
            <?php if (!empty($completedHearings)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="completedHearingsTable">
                        <thead>
                            <tr>
                                <th>Case Number</th>
                                <th>Case Title</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedHearings as $hearing): ?>
                            <tr>
                                <td><?php echo $hearing['case_number']; ?></td>
                                <td><?php echo $hearing['case_title']; ?></td>
                                <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                <td><?php echo $hearing['hearing_type']; ?></td>
                                <td><?php echo $hearing['location']; ?></td>
                                <td><?php echo $hearing['outcome'] ?: 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">No completed hearings found</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Initialize DataTables
echo "<script>
    $(document).ready(function() {
        $('#upcomingHearingsTable').DataTable({
            order: [[2, 'asc']]
        });
        $('#completedHearingsTable').DataTable({
            order: [[2, 'desc']]
        });
    });
</script>";

require_once 'includes/footer.php';
?>
