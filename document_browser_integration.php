<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if administrator
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header('Location: dashboard.php');
    exit();
}

$db = new Database();

// Process the form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Update document links in all relevant files
        
        // 1. Update documents.php view links
        $documentsFile = file_get_contents('documents.php');
        $updatedDocumentsFile = str_replace(
            'document_view.php?id=',
            'document_view_browser.php?id=',
            $documentsFile
        );
        file_put_contents('documents.php', $updatedDocumentsFile);
        
        // 2. Update lawyer_documents.php view links
        if (file_exists('lawyer_documents.php')) {
            $lawyerDocumentsFile = file_get_contents('lawyer_documents.php');
            $updatedLawyerDocumentsFile = str_replace(
                'document_view.php?id=',
                'document_view_browser.php?id=',
                $lawyerDocumentsFile
            );
            file_put_contents('lawyer_documents.php', $updatedLawyerDocumentsFile);
        }
        
        // 3. Update litigant views if they exist
        if (file_exists('litigant_cases.php')) {
            $litigantCasesFile = file_get_contents('litigant_cases.php');
            $updatedLitigantCasesFile = str_replace(
                'document_view.php?id=',
                'document_view_browser.php?id=',
                $litigantCasesFile
            );
            file_put_contents('litigant_cases.php', $updatedLitigantCasesFile);
        }
        
        // 4. Update cases.php view links
        if (file_exists('cases.php')) {
            $casesFile = file_get_contents('cases.php');
            $updatedCasesFile = str_replace(
                'document_view.php?id=',
                'document_view_browser.php?id=',
                $casesFile
            );
            file_put_contents('cases.php', $updatedCasesFile);
        }
        
        // 5. Update case_view.php document links
        if (file_exists('case_view.php')) {
            $caseViewFile = file_get_contents('case_view.php');
            $updatedCaseViewFile = str_replace(
                'document_view.php?id=',
                'document_view_browser.php?id=',
                $caseViewFile
            );
            file_put_contents('case_view.php', $updatedCaseViewFile);
        }
        
        // 6. Update any direct PDF links to use serve_pdf.php
        $filesToCheck = ['documents.php', 'lawyer_documents.php', 'case_view.php', 'litigant_cases.php'];
        foreach ($filesToCheck as $file) {
            if (file_exists($file)) {
                $fileContent = file_get_contents($file);
                
                // Replace direct links to PDF files with serve_pdf.php links
                $updatedContent = preg_replace(
                    '/<a\s+href="(uploads\/documents\/[^"]+\.pdf)"([^>]*)>/i',
                    '<a href="serve_pdf.php?file=\\1"\\2>',
                    $fileContent
                );
                
                file_put_contents($file, $updatedContent);
            }
        }
        
        // Create a backup of the original document_view.php file
        if (file_exists('document_view.php')) {
            copy('document_view.php', 'document_view_original.php');
        }
        
        // Create a redirection from document_view.php to document_view_browser.php
        $redirectCode = '<?php
// Redirect from old document view to new browser-based viewer
require_once "config/config.php";
session_start();

if(!isset($_GET["id"])) {
    header("Location: documents.php");
    exit();
}

$document_id = $_GET["id"];
$version = isset($_GET["version"]) ? "&version=" . $_GET["version"] : "";

// Set a flash message
$_SESSION["info"] = "You have been redirected to the new browser-based document viewer.";

// Redirect to new viewer
header("Location: document_view_browser.php?id=" . $document_id . $version);
exit();';
        
        file_put_contents('document_view.php', $redirectCode);
        
        // Add document version control settings to settings table
        $settings = [
            'enable_document_versioning' => '1',
            'enable_document_annotations' => '1',
            'default_allow_download' => '1',
            'download_expiry_hours' => '24',
            'document_watermark_template' => 'Confidential - {USERNAME} - {DATETIME} - {DOCUMENT_ID}',
            'pdf_viewer_default' => 'auto' // Auto-detect best viewer
        ];
        
        foreach ($settings as $name => $value) {
            // Check if setting exists
            $db->query("SELECT COUNT(*) AS count FROM settings WHERE setting_name = :name");
            $db->bind(':name', $name);
            $result = $db->single();
            
            if ($result && $result['count'] > 0) {
                // Update existing setting
                $db->query("UPDATE settings SET setting_value = :value WHERE setting_name = :name");
                $db->bind(':name', $name);
                $db->bind(':value', $value);
                $db->execute();
            } else {
                // Insert new setting
                $db->query("INSERT INTO settings (setting_name, setting_value, description) 
                            VALUES (:name, :value, :description)");
                $db->bind(':name', $name);
                $db->bind(':value', $value);
                
                // Set description based on setting name
                switch ($name) {
                    case 'enable_document_versioning':
                        $description = 'Enable document version control system';
                        break;
                    case 'enable_document_annotations':
                        $description = 'Enable document annotation features';
                        break;
                    case 'default_allow_download':
                        $description = 'Default setting for allowing document downloads';
                        break;
                    case 'download_expiry_hours':
                        $description = 'Hours until secure download links expire';
                        break;
                    case 'document_watermark_template':
                        $description = 'Template for document watermarks';
                        break;
                    case 'pdf_viewer_default':
                        $description = 'Default PDF viewer (auto, pdfjs, or direct)';
                        break;
                    default:
                        $description = '';
                }
                
                $db->bind(':description', $description);
                $db->execute();
            }
        }
        
        // Update settings menu to include document control settings
        $settingsFile = file_get_contents('settings.php');
        if (strpos($settingsFile, 'Document Control Settings') === false) {
            $settingsMenuPattern = '<div class="list-group mb-4">';
            $documentControlLink = '<div class="list-group mb-4">
                <a href="document_version_integration.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-file-alt mr-2"></i> Document Control Settings
                </a>';
            
            $updatedSettingsFile = str_replace(
                $settingsMenuPattern,
                $documentControlLink,
                $settingsFile
            );
            
            file_put_contents('settings.php', $updatedSettingsFile);
        }
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = "Document browser viewer integration completed successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Set page title
$page_title = "Document Browser Integration";

// Include header
include('includes/header.php');
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="settings.php">Settings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Document Browser Integration</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Document Browser Integration</h5>
                </div>
                <div class="card-body">
                    <?php displayMessage(); ?>
                    
                    <div class="alert alert-info">
                        <p><strong>This tool will:</strong></p>
                        <ul>
                            <li>Update document links to use the new browser-based viewer</li>
                            <li>Create necessary database tables for document version control</li>
                            <li>Enable document annotations and secure downloads</li>
                            <li>Add document control settings to the settings menu</li>
                            <li>Create a backup of the original document view page</li>
                        </ul>
                    </div>
                    
                    <p>Before proceeding, make sure you have created the necessary database tables by running:</p>
                    <ul>
                        <li><code>create_document_version_control.php</code></li>
                    </ul>
                    
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="confirm" name="confirm" required>
                                <label class="custom-control-label" for="confirm">
                                    I confirm that I have backed up my database and files before proceeding.
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Integrate Document Browser</button>
                            <a href="settings.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Manual Setup Instructions</h5>
                </div>
                <div class="card-body">
                    <p>If you prefer to integrate the document browser manually, follow these steps:</p>
                    
                    <ol>
                        <li>Run the <code>create_document_version_control.php</code> script to create necessary database tables.</li>
                        <li>Update document links in all relevant files to point to <code>document_view_browser.php</code> instead of <code>document_view.php</code>.</li>
                        <li>Configure document control settings in the settings table.</li>
                        <li>Add a link to document control settings in the settings menu.</li>
                    </ol>
                    
                    <div class="mt-3">
                        <a href="create_document_version_control.php" class="btn btn-info" target="_blank">Run Table Creation Script</a>
                        <a href="document_version_integration.php" class="btn btn-secondary">Check Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?> 