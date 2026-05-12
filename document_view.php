<?php
// Start output buffering to prevent header errors
ob_start();

require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    ob_end_clean();
    redirect('login.php');
    exit;
}

// Check if user has permission to view documents
if (!hasRole(['court_clerk', 'admin', 'judge', 'lawyer', 'litigant'])) {
    ob_end_clean();
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
    exit;
}

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    ob_end_clean();
    setFlashMessage('Invalid document ID.', 'danger');
    redirect('documents.php');
    exit;
}

$documentId = (int)$_GET['id'];

// Initialize database
$db = new Database();

// Get user's role
$userRole = $_SESSION['role'];

// Get document details
$db->query("SELECT d.*, c.case_number, c.case_title, c.case_type, c.status as case_status, 
            CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name 
            FROM documents d 
            JOIN cases c ON d.case_id = c.case_id 
            JOIN users u ON d.uploaded_by = u.user_id 
            WHERE d.document_id = :document_id");
$db->bind(':document_id', $documentId);
$document = $db->single();

if (!$document) {
    ob_end_clean();
    setFlashMessage('Document not found.', 'danger');
    redirect('documents.php');
    exit;
}

// Check if the user has access to this document
$hasAccess = false;

if (in_array($userRole, ['admin', 'court_clerk'])) {
    $hasAccess = true;
} elseif ($userRole === 'judge') {
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                WHERE d.document_id = :document_id AND c.assigned_judge = :user_id");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'lawyer') {
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                JOIN case_lawyers cl ON c.case_id = cl.case_id 
                JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                WHERE d.document_id = :document_id AND l.user_id = :user_id");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
} elseif ($userRole === 'litigant') {
    $db->query("SELECT COUNT(*) as count 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                JOIN case_parties cp ON c.case_id = cp.case_id 
                WHERE d.document_id = :document_id 
                AND cp.user_id = :user_id 
                AND (d.is_public = 1 OR d.uploaded_by = :user_id2)");
    $db->bind(':document_id', $documentId);
    $db->bind(':user_id', $_SESSION['user_id']);
    $db->bind(':user_id2', $_SESSION['user_id']);
    $result = $db->single();
    $hasAccess = ($result['count'] > 0);
}

if (!$hasAccess) {
    ob_end_clean();
    setFlashMessage('You do not have permission to view this document.', 'danger');
    redirect('documents.php');
    exit;
}

// Get file extension and path
$fileExtension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
$filePath = APP_ROOT . '/' . $document['file_path'];
$canPreview = in_array($fileExtension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt']);

// Continue with normal page rendering (PDFs are embedded via document_embed.php)
ob_end_flush();

// Check if file exists
$fileExists = file_exists($filePath);

// Get the relative path for the document
$documentPath = $document['file_path'];
// Ensure the path doesn't start with a slash to prevent issues with URL joining
$documentPath = ltrim($documentPath, '/');
?>

<div class="document-view-page" data-document-id="<?php echo $documentId; ?>" style="display:none"></div>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Document Details
        </h1>
        <div>
            <a href="case_view.php?id=<?php echo $document['case_id']; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Case
            </a>
            <?php if (hasRole(['admin'])): ?>
            <a href="document_tracking.php?id=<?php echo $documentId; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-map-marker-alt"></i> Track Document Access
            </a>
            <?php endif; ?>
            <?php if (hasRole(['court_clerk', 'admin']) || $document['uploaded_by'] == $_SESSION['user_id']): ?>
            <a href="document_delete.php?id=<?php echo $documentId; ?>" class="btn btn-danger btn-sm confirm-delete">
                <i class="fas fa-trash"></i> Delete
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Information Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Document Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">Document Title:</th>
                            <td><?php echo $document['document_title']; ?></td>
                        </tr>
                        <tr>
                            <th>Document Type:</th>
                            <td><?php echo $document['document_type']; ?></td>
                        </tr>
                        <tr>
                            <th>Case:</th>
                            <td>
                                <a href="case_view.php?id=<?php echo $document['case_id']; ?>">
                                    <?php echo $document['case_number']; ?> - <?php echo $document['case_title']; ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>File Type:</th>
                            <td><?php echo strtoupper($fileExtension); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">File Size:</th>
                            <td><?php echo formatFileSize($document['file_size']); ?></td>
                        </tr>
                        <tr>
                            <th>Uploaded By:</th>
                            <td><?php echo $document['uploaded_by_name']; ?></td>
                        </tr>
                        <tr>
                            <th>Uploaded On:</th>
                            <td><?php echo formatDateTime($document['created_at']); ?></td>
                        </tr>
                        <tr>
                            <th>Visibility:</th>
                            <td>
                                <?php if ($document['is_public']): ?>
                                <span class="badge bg-success">Public</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Restricted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Preview Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Document Preview</h6>
        </div>
        <div class="card-body">
            <?php if ($fileExists): ?>
                <?php if ($canPreview && file_exists($filePath)): ?>
                    <?php if (strtolower($fileExtension) === 'pdf'): ?>
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Document Viewer</h5>
                                <div>
                                    <a href="document_embed.php?id=<?php echo $documentId; ?>&v=<?php echo time(); ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-2">
                                        <i class="fas fa-external-link-alt me-1"></i> Open in New Tab
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-0" style="min-height: 70vh;">
                                <!-- PDF.js Viewer Container -->
                                <div id="pdf-viewer-container" data-pdf-url="document_embed.php?id=<?php echo $documentId; ?>&v=<?php echo time(); ?>" style="height: 100%; overflow: auto; background-color: #525659;">
                                    <div class="d-flex justify-content-center align-items-center" style="height: 100%; min-height: 300px;">
                                        <div class="text-center text-white p-4">
                                            <div class="spinner-border text-light mb-3" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p>Loading document viewer...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i> 
                                    This document is for viewing only. Downloading and printing have been disabled.
                                </small>
                            </div>
                        </div>
                        
                        <!-- PDF.js Library -->
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
                        <script>
                        // Initialize PDF.js and guard against load failures
                        (function() {
                            const container = document.getElementById('pdf-viewer-container');
                            const pdfjsLib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
                            if (!pdfjsLib || !pdfjsLib.GlobalWorkerOptions) {
                                console.error('PDF.js failed to load or is undefined');
                                if (container) {
                                    container.innerHTML = `
                                        <div class="alert alert-danger m-3">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            The PDF viewer could not be initialized. Please try refreshing the page or <a href="document_embed.php?id=<?php echo $documentId; ?>" target="_blank" class="alert-link">open in a new tab</a>.
                                        </div>
                                    `;
                                }
                                return;
                            }

                            // Set worker path
                            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
                        })();
                        
                        // Initialize PDF viewer
                        function initPdfViewer() {
                            const container = document.getElementById('pdf-viewer-container');
                            if (!container) return;
                            
                            const pdfUrl = container.getAttribute('data-pdf-url');
                            if (!pdfUrl) return;
                            
                            // Initialize PDF.js viewer
                            const loadingTask = (window.pdfjsLib || window['pdfjs-dist/build/pdf']).getDocument({
                                url: pdfUrl,
                                disableAutoFetch: true,
                                disableStream: true,
                                disableRange: true
                            });
                            
                            let pdfDoc = null;
                            let pageNum = 1;
                            let pageRendering = false;
                            let pageNumPending = null;
                            let scale = 1.5;
                            
                            // Get canvas and context
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            container.innerHTML = '';
                            container.appendChild(canvas);
                            
                            // Render the page
                            function renderPage(num) {
                                pageRendering = true;
                                
                                loadingTask.promise.then(function(pdf) {
                                    pdfDoc = pdf;
                                    
                                    return pdf.getPage(num);
                                }).then(function(page) {
                                    const viewport = page.getViewport({ scale: scale });
                                    canvas.height = viewport.height;
                                    canvas.width = viewport.width;
                                    
                                    const renderContext = { canvasContext: ctx, viewport: viewport };
                                    const renderTask = page.render(renderContext);
                                    
                                    renderTask.promise.then(function() {
                                        pageRendering = false;
                                        if (pageNumPending !== null) {
                                            renderPage(pageNumPending);
                                            pageNumPending = null;
                                        }
                                    });
                                }).catch(function(error) {
                                    console.error('Error rendering PDF:', error);
                                    container.innerHTML = `
                                        <div class="alert alert-danger m-3">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Error loading PDF document. Please try again or open in a new tab.
                                        </div>
                                    `;
                                });
                                
                                var pageNumEl = document.getElementById('page-num');
                                if (pageNumEl) { pageNumEl.textContent = num; }
                            }
                            
                            // Queue rendering of the page
                            function queueRenderPage(num) {
                                if (pageRendering) {
                                    pageNumPending = num;
                                } else {
                                    renderPage(num);
                                }
                            }
                            
                            // Navigation functions
                            function onPrevPage() {
                                if (pageNum <= 1) return;
                                pageNum--;
                                queueRenderPage(pageNum);
                            }
                            
                            function onNextPage() {
                                if (!pdfDoc) return;
                                if (pageNum >= pdfDoc.numPages) return;
                                pageNum++;
                                queueRenderPage(pageNum);
                            }
                        
                        // Initial render
                        renderPage(pageNum);
                        
                        // Add navigation controls
                        const nav = document.createElement('div');
                        nav.className = 'pdf-controls d-flex justify-content-center align-items-center p-2 bg-dark';
                        nav.innerHTML = `
                            <button id="pdf-prev" class="btn btn-sm btn-outline-light me-2">
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <span class="text-white mx-3">
                                Page <span id="page-num">1</span> of <span id="page-count">?</span>
                            </span>
                            <button id="pdf-next" class="btn btn-sm btn-outline-light ms-2">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                            <div class="ms-3">
                                <button id="pdf-zoom-in" class="btn btn-sm btn-outline-light me-1">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                <button id="pdf-zoom-out" class="btn btn-sm btn-outline-light">
                                    <i class="fas fa-search-minus"></i>
                                </button>
                            </div>
                        `;
                        container.parentNode.insertBefore(nav, container.nextSibling);
                        
                        // Update page count when PDF is loaded
                        loadingTask.promise.then(function(pdf) {
                            document.getElementById('page-count').textContent = pdf.numPages;
                        });
                        
                        // Make functions globally available for buttons
                        window.onPrevPage = onPrevPage;
                        window.onNextPage = onNextPage;

                        // Wire up button events using closures
                        nav.querySelector('#pdf-prev').addEventListener('click', onPrevPage);
                        nav.querySelector('#pdf-next').addEventListener('click', onNextPage);
                        nav.querySelector('#pdf-zoom-in').addEventListener('click', function() {
                            scale *= 1.2; renderPage(pageNum);
                        });
                        nav.querySelector('#pdf-zoom-out').addEventListener('click', function() {
                            if (scale > 0.5) { scale *= 0.8; renderPage(pageNum); }
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initPdfViewer);
                    } else {
                        initPdfViewer();
                    }
                    </script>
                <?php elseif (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <div class="text-center">
                        <img src="<?php echo $document['file_path']; ?>" alt="<?php echo $document['document_title']; ?>" class="img-fluid" style="max-height: 800px;">
                    </div>
                    <?php elseif (strtolower($fileExtension) === 'txt'): ?>
                        <?php
                        $content = file_get_contents($filePath);
                        $content = htmlspecialchars($content);
                        ?>
                        <pre class="p-3 bg-light" style="max-height: 800px; overflow-y: auto;"><?php echo $content; ?></pre>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This file type cannot be previewed in the browser. Please download the file to view its contents.
                    </div>
                    <div class="text-center py-5">
                        <i class="fas fa-file fa-5x mb-3 text-primary"></i>
                        <h4><?php echo $document['document_title']; ?></h4>
                        <p class="text-muted"><?php echo strtoupper($fileExtension); ?> file - In-browser viewing only</p>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> This document is set to view-only mode. Downloading has been disabled.
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> The document file could not be found on the server.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- Custom scripts for this page -->
<script src="assets/js/document_geolocation.js"></script>
<script src="assets/js/document_protection.js"></script>
<script>
    // Log document view when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a moment for geolocation to initialize
        setTimeout(function() {
            trackDocumentAccess(<?php echo $documentId; ?>, 'view');
        }, 1000);
    });
    
    // Log document download when download button is clicked
    document.querySelectorAll('.download-document-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            trackDocumentAccess(<?php echo $documentId; ?>, 'download');
        });
    });
</script>
