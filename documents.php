<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view documents
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer', 'litigant'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Set up filtering
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;
$documentType = isset($_GET['document_type']) ? sanitize($_GET['document_type']) : null;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : null;

// Build query based on user role and filters
$query = "SELECT d.*, c.case_number, c.case_title, c.case_type, 
          CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name 
          FROM documents d 
          JOIN cases c ON d.case_id = c.case_id 
          JOIN users u ON d.uploaded_by = u.user_id 
          WHERE 1=1 ";

$params = [];

// Apply role-based restrictions
if ($userRole === 'judge') {
    // Judges can see documents for cases assigned to them
    $query .= "AND c.assigned_judge = :user_id ";
    $params[':user_id'] = $userId;
} elseif ($userRole === 'court_clerk') {
    // Court clerks can only see documents for cases they created
    $query .= "AND c.created_by = :user_id ";
    $params[':user_id'] = $userId;
} elseif ($userRole === 'lawyer') {
    // Lawyers can see documents for cases they're associated with (via party_lawyers)
    $query .= "AND EXISTS (
                SELECT 1 FROM case_parties cp
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id
                WHERE cp.case_id = c.case_id AND l.user_id = :user_id
              ) ";
    $params[':user_id'] = $userId;
} elseif ($userRole === 'litigant') {
    // Litigants can see documents for cases they are a party to (parity with lawyers/judges)
    $query .= "AND EXISTS (
                    SELECT 1 FROM case_parties cp 
                    WHERE cp.case_id = c.case_id AND cp.user_id = :user_id
                ) ";
    $params[':user_id'] = $userId;
}

// Apply filters
if ($caseId) {
    $query .= "AND d.case_id = :case_id ";
    $params[':case_id'] = $caseId;
}

if ($documentType) {
    $query .= "AND d.document_type = :document_type ";
    $params[':document_type'] = $documentType;
}

if ($search) {
    $query .= "AND (d.document_title LIKE :search OR c.case_number LIKE :search OR c.case_title LIKE :search) ";
    $params[':search'] = '%' . $search . '%';
}

// Add order by
$query .= "ORDER BY d.created_at DESC";

// Execute query
$db->query($query);

// Bind parameters
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

// Get documents
$documents = $db->resultSet();

// Get document types for filter
$db->query("SELECT DISTINCT document_type FROM documents ORDER BY document_type");
$documentTypes = $db->resultSet();

// Get cases for filter (based on user role)
$caseQuery = "SELECT c.case_id, c.case_number, c.case_title FROM cases c WHERE 1=1 ";

if ($userRole === 'judge') {
    $caseQuery .= "AND c.assigned_judge = :user_id ";
} elseif ($userRole === 'lawyer') {
    $caseQuery .= "AND EXISTS (
                    SELECT 1 FROM case_parties cp 
                    JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                    JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                    WHERE cp.case_id = c.case_id AND l.user_id = :user_id
                  ) ";
} elseif ($userRole === 'litigant') {
    $caseQuery .= "AND EXISTS (
                    SELECT 1 FROM case_parties cp 
                    WHERE cp.case_id = c.case_id AND cp.user_id = :user_id
                  ) ";
}

$caseQuery .= "ORDER BY c.filing_date DESC";

$db->query($caseQuery);
if (in_array($userRole, ['judge', 'lawyer', 'litigant'])) {
    $db->bind(':user_id', $userId);
}
$cases = $db->resultSet();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Documents</h1>
        <div>
            <?php if (hasRole(['admin'])): ?>
            <a href="document_tracking.php" class="d-none d-sm-inline-block btn btn-sm btn-info shadow-sm me-2">
                <i class="fas fa-map-marker-alt fa-sm text-white-50"></i> Document Tracking
            </a>
            <?php endif; ?>
            <?php if (hasRole(['court_clerk', 'admin', 'judge', 'lawyer'])): ?>
            <a href="document_upload.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-upload fa-sm text-white-50"></i> Upload New Document
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Documents</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="caseId" class="form-label">Case</label>
                    <select class="form-select" id="caseId" name="case_id">
                        <option value="">All Cases</option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>" <?php echo ($caseId == $case['case_id']) ? 'selected' : ''; ?>>
                                <?php echo $case['case_number']; ?> - <?php echo $case['case_title']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="documentType" class="form-label">Document Type</label>
                    <select class="form-select" id="documentType" name="document_type">
                        <option value="">All Types</option>
                        <?php foreach ($documentTypes as $type): ?>
                            <option value="<?php echo $type['document_type']; ?>" <?php echo ($documentType == $type['document_type']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type['document_type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by title or case number" value="<?php echo $search; ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Documents Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                Document List
                <?php if (!empty($documents)): ?>
                <span class="badge bg-primary"><?php echo count($documents); ?> document<?php echo count($documents) !== 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-4x mb-3 text-gray-300"></i>
                    <p class="text-gray-500">No documents found</p>
                    <?php if (!empty($search) || !empty($caseId) || !empty($documentType)): ?>
                        <p class="text-muted">Try adjusting your filters</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="documentsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Case</th>
                                <th>File Type</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <?php
                                $fileExtension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                ?>
                                <tr>
                                    <td><?php echo $document['document_title']; ?></td>
                                    <td><?php echo ucfirst($document['document_type']); ?></td>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $document['case_id']; ?>">
                                            <?php echo $document['case_number']; ?>
                                        </a>
                                        <small class="d-block text-muted"><?php echo $document['case_title']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo strtoupper($fileExtension); ?></span>
                                    </td>
                                    <td><?php echo $document['uploaded_by_name']; ?></td>
                                    <td><?php echo formatDateTime($document['created_at']); ?></td>
                                    <td>
                                        <a href="document_embed.php?id=<?php echo $document['document_id']; ?>&v=<?php echo time(); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View Document">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (hasRole(['court_clerk', 'admin']) || $document['uploaded_by'] == $userId): ?>
                                        <a href="document_delete.php?id=<?php echo $document['document_id']; ?>" class="btn btn-sm btn-danger confirm-delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#documentsTable').DataTable({
            order: [[5, 'desc']], // Sort by date column descending
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Confirm delete
        $('.confirm-delete').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
