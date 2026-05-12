<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to upload documents
if (!hasRole(['court_clerk', 'admin', 'lawyer'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Initialize variables
$errors = [];
$success = false;
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;

// If case ID is provided, check if it exists and if user has access
if ($caseId) {
    // Check if case exists
    $db->query("SELECT * FROM cases WHERE case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $case = $db->single();
    
    if (!$case) {
        setFlashMessage('Case not found.', 'danger');
        redirect('cases.php');
    }
    
    // Check if user has access to this case
    $hasAccess = false;
    
    if (hasRole('admin')) {
        // Admins have access to all cases
        $hasAccess = true;
    } elseif (hasRole('court_clerk')) {
        // Court clerks only have access to cases they created
        $db->query("SELECT COUNT(*) as count FROM cases WHERE case_id = :case_id AND created_by = :user_id");
        $db->bind(':case_id', $caseId);
        $db->bind(':user_id', $_SESSION['user_id']);
        $result = $db->single();
        $hasAccess = ($result['count'] > 0);
    } elseif (hasRole(['lawyer'])) {
        // Lawyers have access to cases they're associated with
        $db->query("SELECT COUNT(*) as count 
                    FROM case_lawyers cl 
                    JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                    WHERE cl.case_id = :case_id AND l.user_id = :user_id");
        $db->bind(':case_id', $caseId);
        $db->bind(':user_id', $_SESSION['user_id']);
        $result = $db->single();
        $hasAccess = ($result['count'] > 0);
    }
    
    if (!$hasAccess) {
        setFlashMessage('You do not have permission to upload documents to this case.', 'danger');
        redirect('cases.php');
    }
}

// Get cases for dropdown (if case ID is not provided)
if (!$caseId) {
    $query = "SELECT c.* FROM cases c";
    $params = [];
    
    if (hasRole('lawyer')) {
        $query .= " JOIN case_lawyers cl ON c.case_id = cl.case_id 
                    JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                    WHERE l.user_id = :user_id";
        $params[':user_id'] = $_SESSION['user_id'];
    } elseif (hasRole('court_clerk')) {
        $query .= " WHERE c.created_by = :user_id";
        $params[':user_id'] = $_SESSION['user_id'];
    }
    
    $query .= " ORDER BY c.case_number";
    $db->query($query);
    
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    
    $cases = $db->resultSet();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $documentTitle = sanitize($_POST['document_title']);
    $documentType = sanitize($_POST['document_type']);
    $caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : $caseId;
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    
    // Validate form data
    if (empty($documentTitle)) {
        $errors[] = 'Document title is required';
    }
    
    if (empty($documentType)) {
        $errors[] = 'Document type is required';
    }
    
    if (empty($caseId)) {
        $errors[] = 'Case is required';
    }
    
    // Check if file is uploaded
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Document file is required';
    } else {
        $file = $_FILES['document_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        
        // Validate file
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'There was an error uploading the file';
        } elseif (!in_array($fileExt, $allowedExtensions)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions);
        } elseif ($fileSize > 10000000) { // 10MB max
            $errors[] = 'File size too large. Maximum size: 10MB';
        }
    }
    
    // If no errors, proceed with uploading the document
    if (empty($errors)) {
        try {
            // Create upload directory if it doesn't exist
            $uploadDir = UPLOAD_PATH . '/documents/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $newFileName = uniqid('doc_') . '.' . $fileExt;
            $filePath = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpName, $filePath)) {
                // Insert document record
                $db->query("INSERT INTO documents (case_id, document_title, document_type, file_path, file_size, uploaded_by, is_public) 
                            VALUES (:case_id, :document_title, :document_type, :file_path, :file_size, :uploaded_by, :is_public)");
                
                $db->bind(':case_id', $caseId);
                $db->bind(':document_title', $documentTitle);
                $db->bind(':document_type', $documentType);
                $db->bind(':file_path', 'uploads/documents/' . $newFileName);
                $db->bind(':file_size', $fileSize);
                $db->bind(':uploaded_by', $_SESSION['user_id']);
                $db->bind(':is_public', $isPublic);
                
                $db->execute();
                
                // Get the last inserted document ID
                $documentId = $db->lastInsertId();
                
                // Log action
                logAction('Document uploaded', 'documents', $documentId);
                
                // Set success message
                setFlashMessage('Document uploaded successfully.', 'success');
                
                // Redirect to case view page
                redirect('case_view.php?id=' . $caseId);
            } else {
                $errors[] = 'Failed to upload document';
            }
        } catch (Exception $e) {
            $errors[] = 'An error occurred while uploading the document: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Upload Document</h1>
    
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
            <h6 class="m-0 font-weight-bold text-primary">Document Information</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($caseId ? '?case_id=' . $caseId : '')); ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="documentTitle" class="form-label required-field">Document Title</label>
                            <input type="text" class="form-control" id="documentTitle" name="document_title" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="documentType" class="form-label required-field">Document Type</label>
                            <select class="form-select" id="documentType" name="document_type" required>
                                <option value="">Select Type</option>
                                <option value="Pleading">Pleading</option>
                                <option value="Motion">Motion</option>
                                <option value="Order">Order</option>
                                <option value="Judgment">Judgment</option>
                                <option value="Exhibit">Exhibit</option>
                                <option value="Affidavit">Affidavit</option>
                                <option value="Transcript">Transcript</option>
                                <option value="Evidence">Evidence</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <?php if (!$caseId): ?>
                <div class="mb-3">
                    <label for="caseId" class="form-label required-field">Case</label>
                    <select class="form-select" id="caseId" name="case_id" required>
                        <option value="">Select Case</option>
                        <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>">
                            <?php echo $case['case_number'] . ' - ' . $case['case_title']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="documentFile" class="form-label required-field">Document File</label>
                    <input type="file" class="form-control" id="documentFile" name="document_file" required>
                    <div class="form-text">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT. Maximum size: 10MB.</div>
                </div>
                
                <?php if (hasRole(['court_clerk', 'admin'])): ?>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="isPublic" name="is_public">
                    <label class="form-check-label" for="isPublic">Make document publicly accessible to all case parties</label>
                </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between">
                    <a href="<?php echo $caseId ? 'case_view.php?id=' . $caseId : 'cases.php'; ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
