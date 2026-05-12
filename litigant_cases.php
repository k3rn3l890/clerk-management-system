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

// Get cases where user is a party
$db->query("SELECT c.case_id, c.case_number, c.case_title, c.status, c.filing_date,
            CONCAT(j.first_name, ' ', j.last_name) as judge_name
            FROM cases c
            JOIN case_parties cp ON c.case_id = cp.case_id
            LEFT JOIN users j ON c.assigned_judge = j.user_id
            WHERE cp.user_id = :user_id
            ORDER BY c.filing_date DESC");
$db->bind(':user_id', $userId);
$cases = $db->resultSet();

// Get upcoming hearings for these cases
$caseIds = array_column($cases, 'case_id');
$placeholders = implode(',', array_fill(0, count($caseIds), '?'));

if (!empty($caseIds)) {
    $db->query("SELECT h.*, c.case_number
                FROM hearings h
                JOIN cases c ON h.case_id = c.case_id
                WHERE h.case_id IN ($placeholders) AND h.status = 'scheduled'
                ORDER BY h.hearing_date ASC");
    
    foreach ($caseIds as $index => $caseId) {
        $db->bind($index + 1, $caseId);
    }
    
    $upcomingHearings = $db->resultSet();
} else {
    $upcomingHearings = [];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Cases</h1>
    </div>

    <!-- Upcoming Hearings Card -->
    <?php if (!empty($upcomingHearings)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Hearings</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Case Number</th>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingHearings as $hearing): ?>
                        <tr>
                            <td><?php echo $hearing['case_number']; ?></td>
                            <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                            <td><?php echo $hearing['hearing_type']; ?></td>
                            <td><?php echo $hearing['location']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- My Cases Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">My Case List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable">
                    <thead>
                        <tr>
                            <th>Case Number</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Filing Date</th>
                            <th>Assigned Judge</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cases)): ?>
                            <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><?php echo $case['case_number']; ?></td>
                                <td><?php echo $case['case_title']; ?></td>
                                <td><?php echo ucfirst($case['status']); ?></td>
                                <td><?php echo formatDate($case['filing_date']); ?></td>
                                <td><?php echo $case['judge_name'] ?: 'Not Assigned'; ?></td>
                                <td>
                                    <a href="litigant_case_view.php?id=<?php echo $case['case_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No cases found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
