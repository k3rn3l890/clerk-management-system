<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view cases
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
$caseType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query based on user role and filters
$query = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name, 
          CONCAT(j.first_name, ' ', j.last_name) as judge_name 
          FROM cases c 
          LEFT JOIN users u ON c.created_by = u.user_id 
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
} elseif ($userRole === 'court_clerk') {
    $query .= " WHERE c.created_by = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
} else {
    $query .= " WHERE 1=1"; // For admin only
}

// Add filters
if (!empty($status)) {
    $query .= " AND c.status = :status";
    $params[':status'] = $status;
}

if (!empty($caseType)) {
    $query .= " AND c.case_type = :case_type";
    $params[':case_type'] = $caseType;
}

if (!empty($search)) {
    $query .= " AND (c.case_number LIKE :search OR c.case_title LIKE :search)";
    $params[':search'] = "%$search%";
}

// Avoid duplicates if a lawyer is assigned to multiple parties in the same case
$query .= " GROUP BY c.case_id ORDER BY c.created_at DESC";

// Prepare and execute query
$db->query($query);

// Bind parameters
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

// Get results
$cases = $db->resultSet();

// Get case statuses for filter
$statusOptions = ['pending', 'active', 'closed', 'appealed', 'archived'];

// Get case types for filter
$typeOptions = ['civil', 'criminal', 'family', 'commercial', 'other'];
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Cases Management</h1>
    
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
                    <label for="type" class="form-label">Case Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <?php foreach ($typeOptions as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo ($caseType === $option) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($option); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Case number or title" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cases Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Cases List</h6>
            <?php if (hasRole(['court_clerk', 'admin'])): ?>
            <a href="case_add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New Case
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered <?php echo (count($cases) > 0) ? 'datatable' : ''; ?>" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Case Number</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Filing Date</th>
                            <th>Status</th>
                            <th>Judge</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cases) > 0): ?>
                            <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><?php echo $case['case_number']; ?></td>
                                <td><?php echo $case['case_title']; ?></td>
                                <td><?php echo ucfirst($case['case_type']); ?></td>
                                <td><?php echo formatDate($case['filing_date']); ?></td>
                                <td><?php echo getCaseStatusLabel($case['status']); ?></td>
                                <td><?php echo $case['judge_name'] ?: 'Not Assigned'; ?></td>
                                <td>
                                    <a href="case_view.php?id=<?php echo $case['case_id']; ?>" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Case">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasRole(['court_clerk', 'admin'])): ?>
                                    <a href="case_edit.php?id=<?php echo $case['case_id']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Edit Case">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="case_delete.php?id=<?php echo $case['case_id']; ?>" class="btn btn-danger btn-sm confirm-delete" data-bs-toggle="tooltip" title="Delete Case">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No cases found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
