<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view hearings
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer', 'litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Get user's role
$userRole = $_SESSION['role'];

// Initialize database
$db = new Database();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Build query based on user role and filters
$query = "SELECT DISTINCT h.*, c.case_number, c.case_title, c.case_type, 
          CONCAT(j.first_name, ' ', j.last_name) as judge_name 
          FROM hearings h 
          JOIN cases c ON h.case_id = c.case_id 
          LEFT JOIN users j ON c.assigned_judge = j.user_id";

$params = [];

// Add role-specific conditions
if ($userRole === 'lawyer') {
    $query .= " JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
} elseif ($userRole === 'litigant') {
    $query .= " JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
} elseif ($userRole === 'judge') {
    $query .= " WHERE c.assigned_judge = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
} else {
    $query .= " WHERE 1=1"; // For admin and court clerk
}

// Add filters
if (!empty($status)) {
    if (count($params) > 0) {
        $query .= " AND h.status = :status";
    } else {
        $query .= " AND h.status = :status";
    }
    $params[':status'] = $status;
}

if (!empty($caseId)) {
    $query .= " AND h.case_id = :case_id";
    $params[':case_id'] = $caseId;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(h.hearing_date) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(h.hearing_date) <= :date_to";
    $params[':date_to'] = $dateTo;
}

// Add order by
$query .= " ORDER BY h.hearing_date DESC";

// Prepare and execute query
$db->query($query);

// Bind parameters
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

// Get results
$hearings = $db->resultSet();

// Get hearing statuses for filter
$statusOptions = ['scheduled', 'completed', 'postponed', 'cancelled'];

// Get cases for filter (if admin or court clerk)
$cases = [];
if (hasRole(['admin', 'court_clerk'])) {
    $db->query("SELECT case_id, case_number, case_title FROM cases ORDER BY case_number");
    $cases = $db->resultSet();
} elseif ($userRole === 'judge') {
    $db->query("SELECT case_id, case_number, case_title FROM cases WHERE assigned_judge = :user_id ORDER BY case_number");
    $db->bind(':user_id', $_SESSION['user_id']);
    $cases = $db->resultSet();
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT DISTINCT c.case_id, c.case_number, c.case_title 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id 
                ORDER BY c.case_number");
    $db->bind(':user_id', $_SESSION['user_id']);
    $cases = $db->resultSet();
} elseif ($userRole === 'litigant') {
    $db->query("SELECT DISTINCT c.case_id, c.case_number, c.case_title 
                FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE cp.user_id = :user_id 
                ORDER BY c.case_number");
    $db->bind(':user_id', $_SESSION['user_id']);
    $cases = $db->resultSet();
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Hearings Management</h1>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusOptions as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo ($status === $option) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($option); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="case_id" class="form-label">Case</label>
                    <select class="form-select" id="case_id" name="case_id">
                        <option value="">All Cases</option>
                        <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>" <?php echo ($caseId == $case['case_id']) ? 'selected' : ''; ?>>
                            <?php echo $case['case_number']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Hearings Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Hearings List</h6>
            <?php if (hasRole(['court_clerk', 'admin'])): ?>
            <a href="hearing_add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Schedule New Hearing
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered <?php echo (count($hearings) > 0) ? 'datatable' : ''; ?>" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Judge</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($hearings) > 0): ?>
                            <?php foreach ($hearings as $hearing): ?>
                            <tr>
                                <td>
                                    <a href="case_view.php?id=<?php echo $hearing['case_id']; ?>">
                                        <?php echo $hearing['case_number']; ?>
                                    </a>
                                    <div class="small text-muted"><?php echo $hearing['case_title']; ?></div>
                                </td>
                                <td><?php echo formatDateTime($hearing['hearing_date']); ?></td>
                                <td><?php echo $hearing['hearing_type']; ?></td>
                                <td><?php echo $hearing['location']; ?></td>
                                <td><?php echo $hearing['judge_name'] ?: 'Not Assigned'; ?></td>
                                <td><?php echo getHearingStatusLabel($hearing['status']); ?></td>
                                <td>
                                    <a href="hearing_view.php?id=<?php echo $hearing['hearing_id']; ?>" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Hearing">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasRole(['court_clerk', 'admin'])): ?>
                                    <a href="hearing_edit.php?id=<?php echo $hearing['hearing_id']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Edit Hearing">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="hearing_delete.php?id=<?php echo $hearing['hearing_id']; ?>" class="btn btn-danger btn-sm confirm-delete" data-bs-toggle="tooltip" title="Delete Hearing">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No hearings found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
