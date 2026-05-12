<?php
require_once 'includes/header.php';

// Redirect if not logged in or not a lawyer
if (!isLoggedIn() || !hasRole(['lawyer'])) {
    redirect('login.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];

// Get all cases where the user is a lawyer
$db->query("SELECT c.*, 
            CONCAT(j.first_name, ' ', j.last_name) as judge_name,
            COUNT(DISTINCT h.hearing_id) as hearing_count,
            COUNT(DISTINCT d.document_id) as document_count
            FROM cases c 
            LEFT JOIN users j ON c.assigned_judge = j.user_id
            LEFT JOIN hearings h ON c.case_id = h.case_id
            LEFT JOIN documents d ON c.case_id = d.case_id
            JOIN case_parties cp ON c.case_id = cp.case_id
            JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
            JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
            WHERE l.user_id = :user_id
            GROUP BY c.case_id
            ORDER BY c.created_at DESC");
$db->bind(':user_id', $userId);
$cases = $db->resultSet();

// Get case statuses for filtering
$statuses = ['active', 'pending', 'closed', 'archived'];
$currentStatus = isset($_GET['status']) && in_array($_GET['status'], $statuses) ? $_GET['status'] : '';

// Filter cases by status if requested
if (!empty($currentStatus)) {
    $db->query("SELECT c.*, 
                CONCAT(j.first_name, ' ', j.last_name) as judge_name,
                COUNT(DISTINCT h.hearing_id) as hearing_count,
                COUNT(DISTINCT d.document_id) as document_count
                FROM cases c 
                LEFT JOIN users j ON c.assigned_judge = j.user_id
                LEFT JOIN hearings h ON c.case_id = h.case_id
                LEFT JOIN documents d ON c.case_id = d.case_id
                JOIN case_parties cp ON c.case_id = cp.case_id
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id AND c.status = :status
                GROUP BY c.case_id
                ORDER BY c.created_at DESC");
    $db->bind(':user_id', $userId);
    $db->bind(':status', $currentStatus);
    $cases = $db->resultSet();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Cases</h1>
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
                            <option value="lawyer_cases.php">All Cases</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="lawyer_cases.php?status=<?php echo $status; ?>" <?php echo ($currentStatus == $status) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?> Cases
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cases Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo !empty($currentStatus) ? ucfirst($currentStatus) . ' Cases' : 'All Cases'; ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($cases)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No cases found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered <?php echo (count($cases) > 0) ? 'datatable' : ''; ?>" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Case Number</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Judge</th>
                                <th>Filed Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <td><?php echo $case['case_number']; ?></td>
                                    <td><?php echo $case['title']; ?></td>
                                    <td><?php echo $case['case_type']; ?></td>
                                    <td><?php echo getCaseStatusLabel($case['status']); ?></td>
                                    <td><?php echo $case['judge_name'] ?: 'Not Assigned'; ?></td>
                                    <td><?php echo formatDate($case['filing_date']); ?></td>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $case['case_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="lawyer_hearings.php?case_id=<?php echo $case['case_id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-gavel"></i> Hearings (<?php echo $case['hearing_count']; ?>)
                                        </a>
                                        <a href="lawyer_documents.php?case_id=<?php echo $case['case_id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-file-alt"></i> Documents (<?php echo $case['document_count']; ?>)
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
