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
    $db->query("SELECT c.case_number FROM cases c 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE c.case_id = :case_id AND l.user_id = :user_id");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $userId);
    $caseResult = $db->single();
    
    if (!$caseResult) {
        setFlashMessage('You do not have permission to view documents for this case.', 'danger');
        redirect('lawyer_cases.php');
    }
    
    $caseTitle = $caseResult['case_number'];
}

// Get documents for this lawyer
if ($caseId > 0) {
    // Get documents for a specific case
    $db->query("SELECT d.*, c.case_number, c.case_title as case_title, 
                d.document_title as title, d.created_at as uploaded_at,
                CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM documents d
                JOIN cases c ON d.case_id = c.case_id
                JOIN users u ON d.uploaded_by = u.user_id
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE d.case_id = :case_id AND l.user_id = :user_id
                ORDER BY d.created_at DESC");
    $db->bind(':case_id', $caseId);
    $db->bind(':user_id', $userId);
} else {
    // Get all documents for this lawyer
    $db->query("SELECT d.*, c.case_number, c.case_title as case_title, 
                d.document_title as title, d.created_at as uploaded_at,
                CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM documents d
                JOIN cases c ON d.case_id = c.case_id
                JOIN users u ON d.uploaded_by = u.user_id
                JOIN case_parties cp ON c.case_id = cp.case_id 
                JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                WHERE l.user_id = :user_id
                ORDER BY d.created_at DESC");
    $db->bind(':user_id', $userId);
}

$documents = $db->resultSet();

// Get document types for filtering
$documentTypes = [];
foreach ($documents as $document) {
    if (!in_array($document['document_type'], $documentTypes)) {
        $documentTypes[] = $document['document_type'];
    }
}

$currentType = isset($_GET['type']) && in_array($_GET['type'], $documentTypes) ? $_GET['type'] : '';

// Filter documents by type if requested
if (!empty($currentType)) {
    if ($caseId > 0) {
        $db->query("SELECT d.*, c.case_number, c.case_title as case_title, 
                    d.document_title as title, d.created_at as uploaded_at,
                    CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                    FROM documents d
                    JOIN cases c ON d.case_id = c.case_id
                    JOIN users u ON d.uploaded_by = u.user_id
                    JOIN case_parties cp ON c.case_id = cp.case_id 
                    JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                    JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                    WHERE d.case_id = :case_id AND l.user_id = :user_id AND d.document_type = :document_type
                    ORDER BY d.created_at DESC");
        $db->bind(':case_id', $caseId);
        $db->bind(':user_id', $userId);
        $db->bind(':document_type', $currentType);
    } else {
        $db->query("SELECT d.*, c.case_number, c.case_title as case_title, 
                    d.document_title as title, d.created_at as uploaded_at,
                    CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                    FROM documents d
                    JOIN cases c ON d.case_id = c.case_id
                    JOIN users u ON d.uploaded_by = u.user_id
                    JOIN case_parties cp ON c.case_id = cp.case_id 
                    JOIN party_lawyers pl ON pl.case_party_id = cp.party_id 
                    JOIN lawyers l ON pl.lawyer_id = l.lawyer_id 
                    WHERE l.user_id = :user_id AND d.document_type = :document_type
                    ORDER BY d.created_at DESC");
        $db->bind(':user_id', $userId);
        $db->bind(':document_type', $currentType);
    }
    $documents = $db->resultSet();
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php echo $caseId > 0 ? 'Documents for Case: ' . $caseTitle : 'My Documents'; ?>
        </h1>
        <div>
            <?php if ($caseId > 0): ?>
            <a href="lawyer_cases.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to My Cases
            </a>
            <?php endif; ?>
            <a href="document_upload.php<?php echo $caseId > 0 ? '?case_id=' . $caseId : ''; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-upload"></i> Upload Document
            </a>
        </div>
    </div>
    
    <!-- Filter Options -->
    <?php if (!empty($documentTypes)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Options</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="typeFilter">Filter by Document Type</label>
                        <select class="form-control" id="typeFilter" onchange="window.location.href = this.value;">
                            <option value="<?php echo $caseId > 0 ? 'lawyer_documents.php?case_id=' . $caseId : 'lawyer_documents.php'; ?>">All Documents</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?php echo $caseId > 0 ? 'lawyer_documents.php?case_id=' . $caseId . '&type=' . urlencode($type) : 'lawyer_documents.php?type=' . urlencode($type); ?>" <?php echo ($currentType == $type) ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Documents Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo !empty($currentType) ? $currentType . ' Documents' : 'All Documents'; ?>
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No documents found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered <?php echo (count($documents) > 0) ? 'datatable' : ''; ?>" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <?php if ($caseId == 0): ?>
                                <th>Case</th>
                                <?php endif; ?>
                                <th>Document Title</th>
                                <th>Type</th>
                                <th>Uploaded By</th>
                                <th>Date Uploaded</th>
                                <th>File Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <?php if ($caseId == 0): ?>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $document['case_id']; ?>">
                                            <?php echo $document['case_number']; ?>
                                        </a>
                                        <div><small><?php echo $document['case_title']; ?></small></div>
                                    </td>
                                    <?php endif; ?>
                                    <td><?php echo $document['title']; ?></td>
                                    <td><?php echo $document['document_type']; ?></td>
                                    <td><?php echo $document['uploaded_by_name']; ?></td>
                                    <td><?php echo formatDateTime($document['uploaded_at']); ?></td>
                                    <td><?php echo formatFileSize($document['file_size']); ?></td>
                                    <td>
                                        <a href="document_view.php?id=<?php echo $document['document_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="<?php echo $document['file_path']; ?>" class="btn btn-success btn-sm" download>
                                            <i class="fas fa-download"></i> Download
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
