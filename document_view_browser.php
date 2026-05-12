<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = new Database();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Check if document ID is provided
if (!isset($_GET['id'])) {
    header("Location: documents.php");
    exit();
}

$document_id = $_GET['id'];
$version_id = isset($_GET['version']) ? $_GET['version'] : null;

// Get document details
if ($version_id) {
    // Get specific document version
    $db->query("SELECT dv.*, d.document_title, d.case_id, d.document_type, d.is_public, 
                d.allow_download, c.case_number, u.full_name as modified_by_name
                FROM document_versions dv 
                JOIN documents d ON dv.document_id = d.document_id
                JOIN cases c ON d.case_id = c.case_id
                JOIN users u ON dv.modified_by = u.user_id
                WHERE dv.version_id = :version_id");
    $db->bind(':version_id', $version_id);
    $document = $db->single();
    
    if (!$document) {
        $_SESSION['error'] = "Document version not found.";
        header("Location: documents.php");
        exit();
    }
} else {
    // Get latest document info
    $db->query("SELECT d.*, c.case_number, u.full_name as uploaded_by_name
                FROM documents d
                JOIN cases c ON d.case_id = c.case_id
                JOIN users u ON d.uploaded_by = u.user_id
                WHERE d.document_id = :document_id");
    $db->bind(':document_id', $document_id);
    $document = $db->single();
    
    if (!$document) {
        $_SESSION['error'] = "Document not found.";
        header("Location: documents.php");
        exit();
    }
    
    // Check if there are versions available
    $db->query("SELECT version_id, file_path, file_size, mime_type 
                FROM document_versions 
                WHERE document_id = :document_id
                ORDER BY version_number DESC
                LIMIT 1");
    $db->bind(':document_id', $document_id);
    $latestVersion = $db->single();
    
    if ($latestVersion) {
        $document['file_path'] = $latestVersion['file_path'];
        $document['file_size'] = $latestVersion['file_size'];
        $document['mime_type'] = $latestVersion['mime_type'];
        $version_id = $latestVersion['version_id'];
    }
}

// Check permissions to view document
$canView = false;

// Admins can view all documents
if ($user_role == 'admin') {
    $canView = true;
} else {
    // Check if user is associated with the document's case
    $db->query("SELECT * FROM cases WHERE case_id = :case_id AND (assigned_to = :user_id OR litigant_id = :user_id)");
    $db->bind(':case_id', $document['case_id']);
    $db->bind(':user_id', $user_id);
    $case = $db->single();
    
    if ($case || $document['is_public'] == 1) {
        $canView = true;
    }
}

if (!$canView) {
    $_SESSION['error'] = "You don't have permission to view this document.";
    header("Location: documents.php");
    exit();
}

// Get version history if available
$versions = [];
$db->query("SELECT dv.*, u.full_name as modified_by_name
           FROM document_versions dv
           JOIN users u ON dv.modified_by = u.user_id
           WHERE dv.document_id = :document_id
           ORDER BY dv.version_number DESC");
$db->bind(':document_id', $document_id);
$versions = $db->resultset();

// Get document annotations
$annotations = [];
$db->query("SELECT setting_value FROM settings WHERE setting_name = 'enable_document_annotations'");
$annotationsEnabled = $db->single();
$annotationsEnabled = ($annotationsEnabled && $annotationsEnabled['setting_value'] == '1');

if ($annotationsEnabled) {
    $db->query("SELECT a.*, u.full_name as created_by_name
               FROM document_annotations a
               JOIN users u ON a.user_id = u.user_id
               WHERE a.document_id = :document_id
               AND (a.version_id = :version_id OR a.version_id IS NULL)
               AND (a.is_private = 0 OR a.user_id = :user_id)
               ORDER BY a.page_number, a.created_at");
    $db->bind(':document_id', $document_id);
    $db->bind(':version_id', $version_id);
    $db->bind(':user_id', $user_id);
    $annotations = $db->resultset();
}

// Log document access
$db->query("INSERT INTO document_access_logs 
            (document_id, user_id, access_time, access_type, ip_address, latitude, longitude) 
            VALUES (:document_id, :user_id, NOW(), 'view', :ip_address, :latitude, :longitude)");
$db->bind(':document_id', $document_id);
$db->bind(':user_id', $user_id);
$db->bind(':ip_address', $_SERVER['REMOTE_ADDR']);
$db->bind(':latitude', isset($_SESSION['latitude']) ? $_SESSION['latitude'] : null);
$db->bind(':longitude', isset($_SESSION['longitude']) ? $_SESSION['longitude'] : null);
$db->execute();

// Update document's last accessed information
$db->query("UPDATE documents SET last_accessed_at = NOW(), last_accessed_by = :user_id WHERE document_id = :document_id");
$db->bind(':user_id', $user_id);
$db->bind(':document_id', $document_id);
$db->execute();

// Get file extension
$file_extension = pathinfo($document['file_path'], PATHINFO_EXTENSION);

// Prepare page title
$page_title = $document['document_title'];

// Include header
include('includes/header.php');
?>

<div class="container-fluid mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="documents.php">Documents</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($document['document_title']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo htmlspecialchars($document['document_title']); ?></h5>
                    <div>
                        <?php if ($document['allow_download']): ?>
                        <a href="secure_download.php?id=<?php echo $document_id; ?><?php echo $version_id ? '&version=' . $version_id : ''; ?>" 
                           class="btn btn-sm btn-outline-primary" title="Download Document">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($user_role == 'admin' || $document['uploaded_by'] == $user_id): ?>
                        <a href="document_upload_version.php?id=<?php echo $document_id; ?>" 
                           class="btn btn-sm btn-outline-success" title="Upload New Version">
                            <i class="fas fa-upload"></i> New Version
                        </a>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#documentInfoModal">
                            <i class="fas fa-info-circle"></i> Info
                        </button>
                    </div>
                </div>
                <div class="card-body p-0" style="height: 75vh;">
                    <?php if (strtolower($file_extension) === 'pdf'): ?>
                        <!-- PDF Viewer Options -->
                        <?php
                        // Always use our custom PDF viewer to ensure consistent behavior
                        $usePdfJs = true;
                        
                        // Add document_id to the URL for tracking
                        $pdfViewerUrl = "pdf_viewer.php?file=" . urlencode($document['file_path']) . 
                                        "&document_id=" . $document_id;
                        $directPdfUrl = "serve_pdf.php?file=" . urlencode($document['file_path']) . 
                                       "&document_id=" . $document_id;
                        
                        // Check if we should use PDF.js viewer or direct embedding based on browser capabilities
                        $userAgent = $_SERVER['HTTP_USER_AGENT'];
                        
                        // For modern browsers that support PDF embedding well, we can use direct embedding
                        if ((strpos($userAgent, 'Chrome') !== false || 
                            strpos($userAgent, 'Firefox') !== false || 
                            strpos($userAgent, 'Safari') !== false) &&
                            !isset($_GET['viewer'])) {
                            $usePdfJs = false;
                        }
                        
                        // Override with query parameter if specified
                        if (isset($_GET['viewer']) && $_GET['viewer'] === 'pdfjs') {
                            $usePdfJs = true;
                        } elseif (isset($_GET['viewer']) && $_GET['viewer'] === 'direct') {
                            $usePdfJs = false;
                        }
                        ?>
                        
                        <div class="text-center py-2 bg-light border-bottom">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="?id=<?php echo $document_id; ?>&viewer=pdfjs" class="btn btn-<?php echo $usePdfJs ? 'primary' : 'outline-secondary'; ?>">
                                    <i class="fas fa-file-pdf"></i> PDF.js Viewer
                                </a>
                                <a href="?id=<?php echo $document_id; ?>&viewer=direct" class="btn btn-<?php echo !$usePdfJs ? 'primary' : 'outline-secondary'; ?>">
                                    <i class="fas fa-browser"></i> Browser Viewer
                                </a>
                            </div>
                        </div>
                        
                        <?php if ($usePdfJs): ?>
                        <!-- PDF.js Viewer -->
                        <iframe src="<?php echo $pdfViewerUrl; ?>" 
                                style="width: 100%; height: calc(100% - 38px); border: none;" 
                                allow="fullscreen" allowfullscreen></iframe>
                        <?php else: ?>
                        <!-- Direct PDF Embedding -->
                        <object data="<?php echo $directPdfUrl; ?>" 
                                type="application/pdf" 
                                style="width: 100%; height: calc(100% - 38px); border: none;">
                            <div class="p-5 text-center">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                                <h4>PDF Viewer Not Available</h4>
                                <p>Your browser doesn't support embedded PDFs or has blocked them.</p>
                                <div class="mt-3">
                                    <a href="<?php echo $pdfViewerUrl; ?>" class="btn btn-primary">
                                        <i class="fas fa-external-link-alt"></i> Open in PDF.js Viewer
                                    </a>
                                    <?php if ($document['allow_download']): ?>
                                    <a href="secure_download.php?id=<?php echo $document_id; ?><?php echo $version_id ? '&version=' . $version_id : ''; ?>" 
                                       class="btn btn-secondary ml-2">
                                        <i class="fas fa-download"></i> Download PDF
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </object>
                        <?php endif; ?>
                    <?php elseif (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <!-- Image Viewer -->
                        <div class="text-center p-3" style="height: 100%; overflow: auto;">
                            <img src="<?php echo $document['file_path']; ?>" class="img-fluid" alt="<?php echo htmlspecialchars($document['document_title']); ?>">
                        </div>
                    <?php elseif (in_array(strtolower($file_extension), ['doc', 'docx'])): ?>
                        <!-- Word Document Viewer -->
                        <div class="p-5 text-center">
                            <i class="far fa-file-word fa-5x mb-3"></i>
                            <h4>Microsoft Word Document</h4>
                            <p>This document cannot be viewed directly in the browser.</p>
                            <?php if ($document['allow_download']): ?>
                            <a href="secure_download.php?id=<?php echo $document_id; ?><?php echo $version_id ? '&version=' . $version_id : ''; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download Document
                            </a>
                            <?php else: ?>
                            <p class="text-muted">Downloads are not allowed for this document.</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif (strtolower($file_extension) === 'txt'): ?>
                        <!-- Text Viewer -->
                        <div class="p-4" style="height: 100%; overflow: auto;">
                            <pre style="white-space: pre-wrap;"><?php echo htmlspecialchars(file_get_contents($document['file_path'])); ?></pre>
                        </div>
                    <?php else: ?>
                        <!-- Unsupported Format -->
                        <div class="p-5 text-center">
                            <i class="far fa-file fa-5x mb-3"></i>
                            <h4>Unsupported File Format</h4>
                            <p>This file type cannot be viewed directly in the browser.</p>
                            <?php if ($document['allow_download']): ?>
                            <a href="secure_download.php?id=<?php echo $document_id; ?><?php echo $version_id ? '&version=' . $version_id : ''; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-download"></i> Download Document
                            </a>
                            <?php else: ?>
                            <p class="text-muted">Downloads are not allowed for this document.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <!-- Version History Card -->
            <?php if (!empty($versions)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">Version History</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($versions as $v): ?>
                            <a href="document_view_browser.php?id=<?php echo $document_id; ?>&version=<?php echo $v['version_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo ($version_id == $v['version_id']) ? 'active' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Version <?php echo $v['version_number']; ?></h6>
                                    <small><?php echo date('M j, Y', strtotime($v['modified_at'])); ?></small>
                                </div>
                                <small><?php echo htmlspecialchars($v['modified_by_name']); ?></small>
                                <?php if (!empty($v['change_summary'])): ?>
                                <small class="d-block text-truncate"><?php echo htmlspecialchars($v['change_summary']); ?></small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Document Access History Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">Access History</h6>
                </div>
                <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                    <div id="accessHistory">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>When</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody id="accessHistoryData">
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        Loading history...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer p-2">
                    <small class="text-muted">Showing recent activity only</small>
                </div>
            </div>
            
            <!-- Annotations Card -->
            <?php if ($annotationsEnabled): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Annotations</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addAnnotationBtn">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                    <div id="annotations">
                        <?php if (empty($annotations)): ?>
                            <div class="p-3 text-center text-muted">
                                No annotations yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($annotations as $annotation): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo ($annotation['page_number']) ? 'Page ' . $annotation['page_number'] : 'Document'; ?></h6>
                                            <small><?php echo date('M j, Y', strtotime($annotation['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($annotation['content']); ?></p>
                                        <small class="d-block">
                                            By: <?php echo htmlspecialchars($annotation['created_by_name']); ?>
                                            <?php if ($annotation['is_private']): ?>
                                            <span class="badge badge-secondary">Private</span>
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($annotation['user_id'] == $user_id || $user_role == 'admin'): ?>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-annotation-btn" 
                                                    data-id="<?php echo $annotation['annotation_id']; ?>"
                                                    data-content="<?php echo htmlspecialchars($annotation['content']); ?>"
                                                    data-private="<?php echo $annotation['is_private']; ?>"
                                                    data-page="<?php echo $annotation['page_number']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-annotation-btn"
                                                    data-id="<?php echo $annotation['annotation_id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Document Info Modal -->
<div class="modal fade" id="documentInfoModal" tabindex="-1" role="dialog" aria-labelledby="documentInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentInfoModalLabel">Document Information</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <th>Title:</th>
                            <td><?php echo htmlspecialchars($document['document_title']); ?></td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td><?php echo htmlspecialchars($document['document_type']); ?></td>
                        </tr>
                        <tr>
                            <th>Case:</th>
                            <td><?php echo htmlspecialchars($document['case_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Uploaded By:</th>
                            <td><?php echo isset($document['uploaded_by_name']) ? htmlspecialchars($document['uploaded_by_name']) : htmlspecialchars($document['modified_by_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Uploaded On:</th>
                            <td><?php echo isset($document['created_at']) ? date('M j, Y g:i A', strtotime($document['created_at'])) : date('M j, Y g:i A', strtotime($document['modified_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>File Size:</th>
                            <td><?php echo formatFileSize($document['file_size']); ?></td>
                        </tr>
                        <tr>
                            <th>File Type:</th>
                            <td><?php echo strtoupper($file_extension); ?></td>
                        </tr>
                        <tr>
                            <th>Version:</th>
                            <td><?php echo isset($document['version_number']) ? $document['version_number'] : (isset($versions[0]) ? $versions[0]['version_number'] : '1'); ?></td>
                        </tr>
                        <tr>
                            <th>Visibility:</th>
                            <td><?php echo $document['is_public'] ? 'Public' : 'Private'; ?></td>
                        </tr>
                        <tr>
                            <th>Downloads:</th>
                            <td><?php echo $document['allow_download'] ? 'Allowed' : 'Not Allowed'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Annotation Modal -->
<?php if ($annotationsEnabled): ?>
<div class="modal fade" id="annotationModal" tabindex="-1" role="dialog" aria-labelledby="annotationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="annotationModalLabel">Add Annotation</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="annotationForm">
                    <input type="hidden" id="annotation_id">
                    <div class="form-group">
                        <label for="page_number">Page Number</label>
                        <input type="number" class="form-control" id="page_number" min="1">
                        <small class="form-text text-muted">Leave blank to annotate the entire document.</small>
                    </div>
                    <div class="form-group">
                        <label for="annotation_content">Content</label>
                        <textarea class="form-control" id="annotation_content" rows="3" required></textarea>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="is_private">
                        <label class="form-check-label" for="is_private">Private (only visible to you)</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAnnotationBtn">Save</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Load access history
    loadAccessHistory();
    
    <?php if ($annotationsEnabled): ?>
    // Add annotation button click
    $('#addAnnotationBtn').click(function() {
        $('#annotation_id').val('');
        $('#page_number').val('');
        $('#annotation_content').val('');
        $('#is_private').prop('checked', false);
        $('#annotationModalLabel').text('Add Annotation');
        $('#annotationModal').modal('show');
    });
    
    // Edit annotation button click
    $('.edit-annotation-btn').click(function() {
        const id = $(this).data('id');
        const content = $(this).data('content');
        const isPrivate = $(this).data('private') == 1;
        const page = $(this).data('page');
        
        $('#annotation_id').val(id);
        $('#page_number').val(page);
        $('#annotation_content').val(content);
        $('#is_private').prop('checked', isPrivate);
        $('#annotationModalLabel').text('Edit Annotation');
        $('#annotationModal').modal('show');
    });
    
    // Save annotation
    $('#saveAnnotationBtn').click(function() {
        const id = $('#annotation_id').val();
        const content = $('#annotation_content').val();
        const pageNumber = $('#page_number').val();
        const isPrivate = $('#is_private').is(':checked') ? 1 : 0;
        
        if (!content) {
            alert('Please enter annotation content.');
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('document_id', <?php echo $document_id; ?>);
        formData.append('version_id', <?php echo $version_id ? $version_id : 'null'; ?>);
        formData.append('content', content);
        formData.append('page_number', pageNumber);
        formData.append('is_private', isPrivate);
        
        if (id) {
            formData.append('annotation_id', id);
        }
        
        // Save via AJAX
        $.ajax({
            url: 'save_annotation.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#annotationModal').modal('hide');
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });
    
    // Delete annotation
    $('.delete-annotation-btn').click(function() {
        if (!confirm('Are you sure you want to delete this annotation?')) {
            return;
        }
        
        const id = $(this).data('id');
        
        // Create form data
        const formData = new FormData();
        formData.append('annotation_id', id);
        
        // Delete via AJAX
        $.ajax({
            url: 'delete_annotation.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });
    <?php endif; ?>
    
    // Load document access history
    function loadAccessHistory() {
        $.ajax({
            url: 'get_document_access_history.php',
            type: 'GET',
            data: {
                document_id: <?php echo $document_id; ?>
            },
            success: function(response) {
                $('#accessHistoryData').html(response);
            },
            error: function(xhr, status, error) {
                $('#accessHistoryData').html('<tr><td colspan="4" class="text-center text-danger">Error loading history</td></tr>');
            }
        });
    }
    
    // Listen for PDF viewer events
    window.addEventListener('message', function(event) {
        if (event.data.type === 'pagechange') {
            console.log('Page changed to', event.data.page);
            // You can do something when the page changes
        }
    });
    
    document.addEventListener('pdfLoaded', function(e) {
        console.log('PDF loaded with', e.detail.totalPages, 'pages');
        // You can do something when the PDF is fully loaded
    });
});
</script>

<?php
// Helper function to format file size
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

include('includes/footer.php');
?> 