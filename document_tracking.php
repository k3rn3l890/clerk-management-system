<?php
require_once 'includes/header.php';

/**
 * Helper function to get the CSS class for different action types
 * @param string $actionType
 * @return string
 */
function getActionClass($actionType) {
    switch ($actionType) {
        case 'view':
            return 'info';
        case 'download':
            return 'success';
        case 'edit':
            return 'warning';
        case 'delete':
            return 'danger';
        default:
            return 'secondary';
    }
}

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view document tracking
if (!hasRole(['court_clerk', 'admin', 'judge'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Get document ID if provided
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;

// Get filters
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';
$actionType = isset($_GET['action_type']) ? sanitize($_GET['action_type']) : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';

// Validate date inputs and build datetime bounds for index-friendly filtering
$dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
if (!empty($startDate) && !preg_match($dateRegex, $startDate)) { $startDate = ''; }
if (!empty($endDate) && !preg_match($dateRegex, $endDate)) { $endDate = ''; }
$startDateTime = !empty($startDate) ? $startDate . ' 00:00:00' : '';
$endDateTime   = !empty($endDate)   ? $endDate   . ' 23:59:59' : '';

// Build query based on filters
$query = "SELECT dal.*, 
          d.document_title, d.document_type, d.file_path,
          c.case_id, c.case_number, c.case_title,
          CONCAT(u.first_name, ' ', u.last_name) as user_name,
          u.role as user_role
          FROM document_access_logs dal
          JOIN documents d ON dal.document_id = d.document_id
          JOIN cases c ON d.case_id = c.case_id
          JOIN users u ON dal.user_id = u.user_id
          WHERE 1=1 ";

$params = [];

if ($documentId) {
    $query .= "AND dal.document_id = :document_id ";
    $params[':document_id'] = $documentId;
    
    // Get document details
    $db->query("SELECT d.*, c.case_number, c.case_title 
                FROM documents d 
                JOIN cases c ON d.case_id = c.case_id 
                WHERE d.document_id = :document_id");
    $db->bind(':document_id', $documentId);
    $document = $db->single();
}

if ($caseId) {
    $query .= "AND c.case_id = :case_id ";
    $params[':case_id'] = $caseId;
    
    // Get case details
    $db->query("SELECT * FROM cases WHERE case_id = :case_id");
    $db->bind(':case_id', $caseId);
    $case = $db->single();
}

if (!empty($startDateTime)) {
    $query .= "AND dal.access_time >= :start_dt ";
    $params[':start_dt'] = $startDateTime;
}

if (!empty($endDateTime)) {
    $query .= "AND dal.access_time <= :end_dt ";
    $params[':end_dt'] = $endDateTime;
}

if (!empty($actionType)) {
    $query .= "AND dal.action_type = :action_type ";
    $params[':action_type'] = $actionType;
}

if (!empty($userId)) {
    $query .= "AND dal.user_id = :user_id ";
    $params[':user_id'] = $userId;
}

// Add order by
$query .= "ORDER BY dal.access_time DESC";

// Execute query
$db->query($query);

// Bind parameters
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

// Get access logs
$accessLogs = $db->resultSet();

// Get users for filter
$db->query("SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name, role 
            FROM users 
            ORDER BY first_name, last_name");
$users = $db->resultSet();

// Check if document_access_logs table exists
$tableExists = false;
try {
    $db->query("SHOW TABLES LIKE 'document_access_logs'");
    $result = $db->single();
    $tableExists = !empty($result);
} catch (Exception $e) {
    // Table doesn't exist
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?php if ($documentId && isset($document)): ?>
                Document Tracking: <?php echo $document['document_title']; ?>
            <?php elseif ($caseId && isset($case)): ?>
                Document Tracking for Case: <?php echo $case['case_number']; ?>
            <?php else: ?>
                Document Tracking
            <?php endif; ?>
        </h1>
        <div>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($documentId): ?>
                <a href="document_view.php?id=<?php echo $documentId; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-file"></i> View Document
                </a>
            <?php elseif ($caseId): ?>
                <a href="case_view.php?id=<?php echo $caseId; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-folder"></i> View Case
                </a>
            <?php endif; ?>
            <a href="documents.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Documents
            </a>
        </div>
    </div>
    
    <?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> Document Tracking Not Available</h5>
        <p>The document tracking feature is not fully set up. Please run the setup script to create the necessary database tables.</p>
        <a href="create_document_tracking_tables.php" class="btn btn-warning">Set Up Document Tracking</a>
    </div>
    <?php else: ?>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filter Document Access Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="document_tracking.php" method="get">
                        <?php if ($documentId): ?>
                            <input type="hidden" name="id" value="<?php echo $documentId; ?>">
                        <?php elseif ($caseId): ?>
                            <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="action_type" class="form-label">Action Type</label>
                                <select class="form-select" id="action_type" name="action_type">
                                    <option value="">All Actions</option>
                                    <option value="view" <?php echo ($actionType === 'view') ? 'selected' : ''; ?>>View</option>
                                    <option value="download" <?php echo ($actionType === 'download') ? 'selected' : ''; ?>>Download</option>
                                    <option value="edit" <?php echo ($actionType === 'edit') ? 'selected' : ''; ?>>Edit</option>
                                    <option value="delete" <?php echo ($actionType === 'delete') ? 'selected' : ''; ?>>Delete</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="user_id" class="form-label">User</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo ($userId == $user['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo $user['full_name'] . ' (' . ucfirst($user['role']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo 'document_tracking.php' . ($documentId ? '?id=' . $documentId : '') . ($caseId ? '?case_id=' . $caseId : ''); ?>" class="btn btn-secondary">Clear Filters</a>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Location Status Alert Section -->
    <div class="row mb-3">
        <div class="col-md-12">
            <?php if (empty($accessLogs) || !array_filter($accessLogs, function($log) { return !empty($log['latitude']) && !empty($log['longitude']); })) : ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-exclamation-triangle"></i> No Geolocation Data Available</h5>
                <p>No real location data is available for tracking. To collect accurate location data:</p>
                <ol>
                    <li>Ensure users are accessing documents with geolocation permissions enabled in their browsers</li>
                    <li>Add the necessary JavaScript code to all document view and download pages</li>
                    <li>Consider adding a privacy policy to inform users about location tracking</li>
                </ol>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php else: ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-info-circle"></i> Local Testing Environment Detected</h5>
                <p><strong>Local Testing:</strong> When testing on localhost, you may see approximate locations based on your IP address or network configuration. For accurate tracking, deploy to a public server.</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <!-- Map View -->
    <?php /* Map is always visible; markers load via AJAX */ ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Geographic Access Map</h6>
            <div>
                <div class="input-group input-group-sm">
                    <span id="selected-coordinates" class="badge bg-secondary me-2 d-flex align-items-center">No location selected</span>
                    <button id="toggle-coordinates" class="btn btn-sm btn-outline-primary">Show All Coordinates</button>
                    <button id="toggle-live-tracking" class="btn btn-sm btn-outline-success ms-2">Enable Live Tracking</button>
                    <button id="center-ghana" class="btn btn-sm btn-outline-info ms-2" title="Center map on Ghana"><i class="fas fa-map-marker-alt"></i> Ghana</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="accessMap" style="height: 500px;"></div>
            
            <!-- Coordinates Table (initially hidden) -->
            <div id="coordinates-table-container" class="mt-3" style="display: none;">
                <h6 class="font-weight-bold">Access Coordinates</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="coordinates-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Time</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessLogs as $log): ?>
                                <?php if ($log['latitude'] && $log['longitude']): ?>
                                <tr>
                                    <td><?php echo $log['user_name']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getActionClass($log['action_type']); ?>">
                                            <?php echo ucfirst($log['action_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $log['latitude']; ?></td>
                                    <td><?php echo $log['longitude']; ?></td>
                                    <td><?php echo formatDateTime($log['access_time']); ?></td>
                                    <td><?php echo $log['location_name'] ? substr($log['location_name'], 0, 30) . (strlen($log['location_name']) > 30 ? '...' : '') : 'Unknown'; ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php /* end map card */ ?>
    
    <!-- Access Logs Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Document Access Logs</h6>
        </div>
        <div class="card-body">
            <?php if (empty($accessLogs)): ?>
                <div class="alert alert-info">
                    No access logs found. <?php echo (!empty($startDate) || !empty($endDate) || !empty($actionType) || !empty($userId)) ? 'Try clearing some filters.' : ''; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <?php if (!$documentId): ?>
                                <th>Document</th>
                                <?php endif; ?>
                                <?php if (!$caseId): ?>
                                <th>Case</th>
                                <?php endif; ?>
                                <th>User</th>
                                <th>Action</th>
                                <th>Location</th>
                                <th>Device</th>
                                <th>IP Address</th>
                                <?php if (hasRole(['admin'])): ?>
                                <th>Track Location</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessLogs as $log): ?>
                                <tr>
                                    <td><?php echo formatDateTime($log['access_time']); ?></td>
                                    
                                    <?php if (!$documentId): ?>
                                    <td>
                                        <a href="document_view.php?id=<?php echo $log['document_id']; ?>">
                                            <?php echo $log['document_title']; ?>
                                        </a>
                                        <small class="d-block text-muted"><?php echo ucfirst($log['document_type']); ?></small>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <?php if (!$caseId): ?>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $log['case_id']; ?>">
                                            <?php echo $log['case_number']; ?>
                                        </a>
                                        <small class="d-block text-muted"><?php echo $log['case_title']; ?></small>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <?php echo $log['user_name']; ?>
                                        <small class="d-block text-muted"><?php echo ucfirst($log['user_role']); ?></small>
                                    </td>
                                    
                                    <td>
                                        <?php
                                        $actionClass = '';
                                        switch ($log['action_type']) {
                                            case 'view':
                                                $actionClass = 'info';
                                                $actionIcon = 'eye';
                                                break;
                                            case 'download':
                                                $actionClass = 'success';
                                                $actionIcon = 'download';
                                                break;
                                            case 'edit':
                                                $actionClass = 'warning';
                                                $actionIcon = 'edit';
                                                break;
                                            case 'delete':
                                                $actionClass = 'danger';
                                                $actionIcon = 'trash';
                                                break;
                                            default:
                                                $actionClass = 'secondary';
                                                $actionIcon = 'question';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $actionClass; ?>">
                                            <i class="fas fa-<?php echo $actionIcon; ?>"></i>
                                            <?php echo ucfirst($log['action_type']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php if ($log['latitude'] && $log['longitude']): ?>
                                            <a href="#" class="location-link" data-lat="<?php echo $log['latitude']; ?>" data-lng="<?php echo $log['longitude']; ?>" data-bs-toggle="tooltip" title="Click to view on map">
                                                <i class="fas fa-map-marker-alt text-danger"></i>
                                                <?php echo $log['location_name'] ? substr($log['location_name'], 0, 30) . (strlen($log['location_name']) > 30 ? '...' : '') : 'Map Location'; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Location not available</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php
                                        $deviceIcon = 'desktop';
                                        if ($log['device_type'] === 'mobile') {
                                            $deviceIcon = 'mobile-alt';
                                        } elseif ($log['device_type'] === 'tablet') {
                                            $deviceIcon = 'tablet-alt';
                                        }
                                        ?>
                                        <i class="fas fa-<?php echo $deviceIcon; ?>"></i>
                                        <?php echo $log['device_type'] ? ucfirst($log['device_type']) : 'Unknown'; ?>
                                    </td>
                                    
                                    <td><?php echo $log['ip_address']; ?></td>
                                    <td>
                                        <?php if (hasRole(['admin'])): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="#" class="btn btn-primary track-document" 
                                                   data-document-id="<?php echo $log['document_id']; ?>" 
                                                   data-document-title="<?php echo htmlspecialchars($log['document_title']); ?>"
                                                   data-bs-toggle="tooltip" 
                                                   title="Track document access locations">
                                                    <i class="fas fa-file-alt"></i> Track Document
                                                </a>
                                                <a href="#" class="btn btn-info track-user" 
                                                   data-user-id="<?php echo $log['user_id']; ?>" 
                                                   data-user-name="<?php echo htmlspecialchars($log['user_name']); ?>"
                                                   data-bs-toggle="tooltip" 
                                                   title="Track user access locations">
                                                    <i class="fas fa-user-clock"></i> Track User
                                                </a>
                                            </div>
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
    <?php endif; ?>

    <?php if (hasRole(['admin'])): ?>
    <!-- Location Policy Status -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Location Tracking Status</h6>
        </div>
        <div class="card-body">
            <?php 
            // Get users who agreed to location tracking
            $db->query("SELECT lpa.*, 
                      CONCAT(u.first_name, ' ', u.last_name) as user_name,
                      u.role as user_role, 
                      u.username,
                      (SELECT COUNT(*) FROM document_access_logs dal WHERE dal.user_id = lpa.user_id AND dal.latitude IS NOT NULL) as location_count
                      FROM location_policy_agreements lpa
                      JOIN users u ON lpa.user_id = u.user_id
                      GROUP BY lpa.user_id
                      ORDER BY lpa.agreement_time DESC
                      LIMIT 10");
            $locationAgreements = $db->resultSet();
            ?>
            
            <?php if (empty($locationAgreements)): ?>
                <div class="alert alert-info">
                    No users have agreed to location tracking yet. Users will be prompted to allow location tracking when they access the system.
                </div>
            <?php else: ?>
                <p class="mb-3">The following users have agreed to location tracking:</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Agreement Time</th>
                                <th>Location Data Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locationAgreements as $agreement): ?>
                                <tr>
                                    <td><?php echo $agreement['user_name']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo ucfirst($agreement['user_role']); ?></span></td>
                                    <td><?php echo formatDateTime($agreement['agreement_time']); ?></td>
                                    <td>
                                        <?php if ($agreement['location_count'] > 0): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle"></i> Location data available</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> No location data yet</span>
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
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- Load Leaflet.js and additional plugins for enhanced maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.79.0/dist/L.Control.Locate.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.79.0/dist/L.Control.Locate.min.css" />
<script src="https://unpkg.com/leaflet-measure@3.1.0/dist/leaflet-measure.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet-measure@3.1.0/dist/leaflet-measure.css" />

<script>
    // Set flag to prevent footer.php from initializing DataTables
    window.customDataTablesInit = true;
    
    // Function to show notifications
    function showNotification(message, type = 'info', duration = 5000) {
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
        alertDiv.innerHTML = message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        
        // Insert at the top of the card body
        var cardBody = document.querySelector('.card-body');
        cardBody.insertBefore(alertDiv, cardBody.firstChild);
        
        // Auto-dismiss after specified duration
        setTimeout(function() {
            alertDiv.classList.remove('show');
            setTimeout(function() {
                alertDiv.remove();
            }, 150);
        }, duration);
    }
    
    // Function to get action class
    function getActionClass(action) {
        switch(action) {
            case 'view': return 'info';
            case 'download': return 'success';
            case 'edit': return 'warning';
            case 'delete': return 'danger';
            default: return 'secondary';
        }
    }
    
    // Initialize DataTable with custom settings
    document.addEventListener('DOMContentLoaded', function() {
        if (document.querySelector('.datatable')) {
            $('.datatable').DataTable({
                order: [[0, 'desc']],
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
        }
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
    
    // Initialize map (always)
    var mapPoints = [];
    var hasLocationData = true;
    // No server-side injection; map will show real data via AJAX only
    
    if (true) {
        // Initialize map centered on Ghana
        var map = L.map('accessMap').setView([5.6037, -0.1870], 7); // Default center on Ghana with wider view
        
        // Add multiple base layers for better map options
        var baseLayers = {
            'OpenStreetMap': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }),
            'Satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
            }),
            'Terrain': L.tileLayer('https://stamen-tiles-{s}.a.ssl.fastly.net/terrain/{z}/{x}/{y}{r}.png', {
                attribution: 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            })
        };
        
        // Add the default base layer
        baseLayers['OpenStreetMap'].addTo(map);
        
        // Add layer control
        L.control.layers(baseLayers, null, {position: 'topright'}).addTo(map);
        
        // Add geocoder for searching locations
        L.Control.geocoder().addTo(map);
        
        // Add locate control for finding user's location
        L.control.locate({
            position: 'topright',
            strings: {
                title: "Show my location"
            },
            locateOptions: {
                enableHighAccuracy: true
            }
        }).addTo(map);
        
        // Add measurement tool
        var measureControl = new L.Control.Measure({
            position: 'topright',
            primaryLengthUnit: 'kilometers',
            secondaryLengthUnit: 'miles',
            primaryAreaUnit: 'sqkilometers',
            secondaryAreaUnit: 'sqmiles'
        });
        measureControl.addTo(map);
        
        // Add scale
        L.control.scale().addTo(map);
        
        var bounds = L.latLngBounds();
        
        // Create custom markers with different colors based on action type
        function getMarkerIcon(action) {
            var iconColor = '#3388ff'; // Default blue
            
            switch(action.toLowerCase()) {
                case 'view':
                    iconColor = '#17a2b8'; // Info blue
                    break;
                case 'download':
                    iconColor = '#28a745'; // Success green
                    break;
                case 'edit':
                    iconColor = '#ffc107'; // Warning yellow
                    break;
                case 'delete':
                    iconColor = '#dc3545'; // Danger red
                    break;
            }
            
            return L.divIcon({
                html: '<div style="background-color: ' + iconColor + '; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
                className: 'custom-div-icon',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
        }
        
        // Create layer group for markers
        var markers = L.layerGroup().addTo(map);

        // ---- Dynamic data fetching & live updates ----
        var seenLogIds = new Set();
        var lastFetchedTime = '';
        var hasFitToBounds = false;

        function addMarker(point) {
            var marker = L.marker([point.lat, point.lng], {
                icon: getMarkerIcon(point.action),
                title: point.title + ' - ' + point.action
            }).addTo(markers);
            var popupContent =
                '<div class="map-popup">'+
                '<h6 class="mb-2">'+point.title+'</h6>'+
                '<div class="mb-1"><span class="badge bg-'+getActionClass(point.action.toLowerCase())+'">'+point.action+'</span></div>'+
                '<div class="mb-1"><strong>Document:</strong> '+point.document+'</div>'+
                '<div class="mb-1"><strong>Time:</strong> '+point.time+'</div>'+
                '<div class="mb-1">'+
                '<strong>Coordinates:</strong><br>'+
                '<code>'+Number(point.lat).toFixed(6)+', '+Number(point.lng).toFixed(6)+'</code>'+
                '</div>'+
                '<button class="btn btn-sm btn-outline-primary mt-2 copy-coords" '+
                    'data-lat="'+Number(point.lat).toFixed(6)+'" '+
                    'data-lng="'+Number(point.lng).toFixed(6)+'">'+
                    '<i class="fas fa-copy"></i> Copy Coordinates'+
                '</button>'+
                '</div>';
            marker.bindPopup(popupContent);
            // Update selected coordinates and wire copy button after popup opens
            marker.on('click', function() {
                var sel = document.getElementById('selected-coordinates');
                if (sel) {
                    sel.innerHTML = '<i class="fas fa-map-marker-alt text-danger me-1"></i> ' + Number(point.lat).toFixed(6) + ', ' + Number(point.lng).toFixed(6);
                    sel.className = 'badge bg-primary me-2 d-flex align-items-center';
                }
                setTimeout(function(){
                    var copyBtn = document.querySelector('.copy-coords');
                    if (copyBtn) {
                        copyBtn.addEventListener('click', function(e){
                            e.preventDefault();
                            var lat = this.getAttribute('data-lat');
                            var lng = this.getAttribute('data-lng');
                            navigator.clipboard.writeText(lat + ', ' + lng).then(function(){
                                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                                setTimeout(function(){
                                    copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Coordinates';
                                }, 2000);
                            });
                        });
                    }
                }, 100);
            });
            bounds.extend([point.lat, point.lng]);
        }

        function buildApiUrl() {
            var params = new URLSearchParams(window.location.search);
            if (lastFetchedTime) params.set('since_time', lastFetchedTime);
            return 'get_document_access_map_data.php?' + params.toString();
        }

        function fetchMapData() {
            fetch(buildApiUrl(), { credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(json){
                    if (!json || !json.success) return;
                    var newMax = lastFetchedTime;
                    (json.points || []).forEach(function(p){
                        if (p.log_id && seenLogIds.has(p.log_id)) return;
                        if (p.log_id) seenLogIds.add(p.log_id);
                        mapPoints.push(p);
                        addMarker(p);
                        if (!newMax || p.time > newMax) newMax = p.time;
                    });
                    lastFetchedTime = newMax || lastFetchedTime;
                    if (!hasFitToBounds && mapPoints.length > 0) {
                        map.fitBounds(bounds, { padding: [50, 50] });
                        hasFitToBounds = true;
                    }
                })
                .catch(function(err){
                    console.error('Map data fetch error:', err);
                });
        }

        // Initial load and polling
        fetchMapData();
        setInterval(fetchMapData, 15000);

        // Add a legend for action types
        var legend = L.control({ position: 'bottomright' });

        legend.onAdd = function (map) {
            var div = L.DomUtil.create('div', 'info legend');
            var actions = ['View', 'Download', 'Edit', 'Delete'];
            var colors = ['#17a2b8', '#28a745', '#ffc107', '#dc3545'];

            div.innerHTML += '<strong>Action Types</strong><br>';

            for (var i = 0; i < actions.length; i++) {
                div.innerHTML +=
                    '<i style="background:' + colors[i] + '; width: 12px; height: 12px; display: inline-block; margin-right: 5px; border-radius: 50%; border: 1px solid #ccc;"></i> ' +
                    actions[i] + '<br>';
            }
            return div;
        };

        legend.addTo(map);

        // User filter functionality
        $('#userFilter').on('change', function() {
            var userId = $(this).val();
            var userName = $(this).find('option:selected').text();

            if (userId === '') {
                // Show all markers
                markers.clearLayers();
                mapPoints.forEach(function(point) {
                    var marker = L.marker([point.lat, point.lng], {
                        icon: getMarkerIcon(point.action),
                        title: point.title + ' - ' + point.action
                    }).addTo(markers);
                    var popupContent =
                        '<div class="map-popup">' +
                        '<h6 class="mb-2">' + point.title + '</h6>' +
                        '<div class="mb-1"><span class="badge bg-' + getActionClass(point.action.toLowerCase()) + '">' + point.action + '</span></div>' +
                        '<div class="mb-1"><strong>Document:</strong> ' + point.document + '</div>' +
                        '<div class="mb-1"><strong>Time:</strong> ' + point.time + '</div>' +
                        '<div class="mb-1">' +
                        '<strong>Coordinates:</strong><br>' +
                        '<code>' + point.lat.toFixed(6) + ', ' + point.lng.toFixed(6) + '</code>' +
                        '</div>' +
                        '</div>';
                    marker.bindPopup(popupContent);
                });
                showNotification('Showing all access locations.', 'info');
                if (mapPoints.length > 0) {
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            } else {
                // Clear existing markers
                if (map.hasLayer(markers)) {
                    map.removeLayer(markers);
                }

                // Create new marker group
                markers = L.layerGroup().addTo(map);

                // Filter points for this user
                var filteredPoints = mapPoints.filter(function(point) {
                    return point.userId == userId;
                });

                // Add filtered markers
                if (filteredPoints.length > 0) {
                    var bounds = L.latLngBounds();

                    filteredPoints.forEach(function(point) {
                        var marker = L.marker([point.lat, point.lng], {
                            icon: getMarkerIcon(point.action),
                            title: point.title + ' - ' + point.action
                        }).addTo(markers);

                        // Create popup content
                        var popupContent =
                            '<div class="map-popup">'+
                                '<h6 class="mb-2">'+point.title+'</h6>'+
                                '<div class="mb-1"><span class="badge bg-'+getActionClass(point.action.toLowerCase())+'">'+point.action+'</span></div>'+
                                '<div class="mb-1"><strong>Document:</strong> '+point.document+'</div>'+
                                '<div class="mb-1"><strong>Time:</strong> '+point.time+'</div>'+
                                '<div class="mb-1">'+
                                    '<strong>Coordinates:</strong><br>'+
                                    '<code>'+point.lat.toFixed(6)+', '+point.lng.toFixed(6)+'</code>'+
                                '</div>'+
                            '</div>';

                        marker.bindPopup(popupContent);
                        bounds.extend([point.lat, point.lng]);
                    });

                    // Fit map to bounds
                    map.fitBounds(bounds, { padding: [50, 50] });

                    // Show notification
                    showNotification('Showing access locations for user: <strong>' + userName + '</strong>. Found ' + filteredPoints.length + ' access points.', 'info');
                } else {
                    showNotification('No access locations found for user: <strong>' + userName + '</strong>.', 'warning');
                }
            }
        });
        
        // Handle location link clicks
        document.querySelectorAll('.location-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var lat = parseFloat(this.getAttribute('data-lat'));
                var lng = parseFloat(this.getAttribute('data-lng'));
                map.setView([lat, lng], 15);
                
                // Find the marker at this location and open its popup
                for (var i = 0; i < mapPoints.length; i++) {
                    if (mapPoints[i].lat === lat && mapPoints[i].lng === lng) {
                        L.marker([lat, lng]).addTo(map)
                            .bindPopup(
                                "<strong>" + mapPoints[i].title + "</strong><br>" +
                                "<strong>Action:</strong> " + mapPoints[i].action + "<br>" +
                                "<strong>Document:</strong> " + mapPoints[i].document + "<br>" +
                                "<strong>Time:</strong> " + mapPoints[i].time
                            )
                            .openPopup();
                        break;
                    }
                }
            });
        });
        
        // Handle toggle coordinates button
        document.getElementById('toggle-coordinates').addEventListener('click', function() {
            var container = document.getElementById('coordinates-table-container');
            var button = document.getElementById('toggle-coordinates');
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                button.textContent = 'Hide Coordinates';
                button.className = 'btn btn-sm btn-outline-secondary';
            } else {
                container.style.display = 'none';
                button.textContent = 'Show All Coordinates';
                button.className = 'btn btn-sm btn-outline-primary';
            }
        });
        
        // Handle live tracking button
        document.getElementById('toggle-live-tracking').addEventListener('click', function() {
            var button = document.getElementById('toggle-live-tracking');
            
            if (button.textContent === 'Enable Live Tracking') {
                // Start live tracking
                startLiveTracking();
                button.textContent = 'Disable Live Tracking';
                button.className = 'btn btn-sm btn-outline-danger ms-2';
            } else {
                // Stop live tracking
                stopLiveTracking();
                button.textContent = 'Enable Live Tracking';
                button.className = 'btn btn-sm btn-outline-success ms-2';
            }
        });
        
        // Center on Ghana button
        document.getElementById('center-ghana').addEventListener('click', function() {
            map.setView([5.6037, -0.1870], 7);
        });
        
        // Live tracking variables
        var liveTrackingEnabled = false;
        var liveTrackingInterval;
        var liveMarker;
        var liveCircle;
        
        // Function to start live tracking
        function startLiveTracking() {
            liveTrackingEnabled = true;
            
            // Create a marker for live location if it doesn't exist
            if (!liveMarker) {
                liveMarker = L.marker([0, 0], {
                    icon: L.divIcon({
                        html: '<div style="background-color: #007bff; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>',
                        className: 'live-location-icon',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(map);
                
                liveMarker.bindPopup('<strong>Your Current Location</strong><br><span id="live-coordinates"></span>');
            }
            
            // Update location every 10 seconds
            updateLiveLocation();
            liveTrackingInterval = setInterval(updateLiveLocation, 10000);
            
            // Show notification
            showNotification('Live tracking enabled. Your location will be updated every 10 seconds.');
        }
        
        // Function to stop live tracking
        function stopLiveTracking() {
            liveTrackingEnabled = false;
            clearInterval(liveTrackingInterval);
            
            // Remove live marker and circle
            if (liveMarker) {
                map.removeLayer(liveMarker);
                liveMarker = null;
            }
            
            if (liveCircle) {
                map.removeLayer(liveCircle);
                liveCircle = null;
            }
            
            // Show notification
            showNotification('Live tracking disabled.');
        }
        
        // Function to update live location
        function updateLiveLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    var accuracy = position.coords.accuracy;
                    
                    // Update marker position
                    if (liveMarker) {
                        liveMarker.setLatLng([lat, lng]);
                        document.getElementById('live-coordinates').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                        liveMarker.openPopup();
                    }
                    
                    // Update or create accuracy circle
                    if (liveCircle) {
                        map.removeLayer(liveCircle);
                    }
                    
                    liveCircle = L.circle([lat, lng], {
                        radius: accuracy,
                        color: '#007bff',
                        fillColor: '#007bff',
                        fillOpacity: 0.1,
                        weight: 1
                    }).addTo(map);
                    
                    // Center map on current location
                    map.setView([lat, lng], 15);
                    
                    // Log to console
                    console.log('Live location updated:', lat, lng, 'accuracy:', accuracy);
                    
                }, function(error) {
                    console.error('Error getting location:', error);
                    showNotification('Error getting your location: ' + error.message, 'danger');
                }, {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                });
            } else {
                showNotification('Geolocation is not supported by this browser.', 'danger');
                stopLiveTracking();
            }
        }
        

        
        // Track document and user location functionality
        $(document).ready(function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Track document button click handler
            $('.track-document').on('click', function(e) {
                e.preventDefault();
                var documentId = $(this).data('document-id');
                var documentTitle = $(this).data('document-title');
                
                // Filter the map to show only access points for this document
                filterMapByDocument(documentId, documentTitle);
                
                // Scroll to map
                $('html, body').animate({
                    scrollTop: $('#accessMap').offset().top - 100
                }, 500);
            });
            
            // Track user button click handler
            $('.track-user').on('click', function(e) {
                e.preventDefault();
                var userId = $(this).data('user-id');
                var userName = $(this).data('user-name');
                
                // Filter the map to show only access points for this user
                filterMapByUser(userId, userName);
                
                // Scroll to map
                $('html, body').animate({
                    scrollTop: $('#accessMap').offset().top - 100
                }, 500);
            });
            
            // Function to filter map by document
            function filterMapByDocument(documentId, documentTitle) {
                // Clear existing markers
                if (markers) {
                    map.removeLayer(markers);
                }
                
                // Create new marker group
                markers = L.layerGroup().addTo(map);
                
                // Filter points for this document
                var filteredPoints = mapPoints.filter(function(point) {
                    return point.documentId == documentId;
                });
                
                // Add filtered markers
                if (filteredPoints.length > 0) {
                    var bounds = L.latLngBounds();
                    
                    filteredPoints.forEach(function(point) {
                        var marker = L.marker([point.lat, point.lng], {
                            icon: getMarkerIcon(point.action),
                            title: point.title + ' - ' + point.action
                        }).addTo(markers);
                        
                        // Create popup content
                        var popupContent = 
                            '<div class="map-popup">'+
                                '<h6 class="mb-2">'+point.title+'</h6>'+
                                '<div class="mb-1"><span class="badge bg-'+getActionClass(point.action.toLowerCase())+'">'+point.action+'</span></div>'+
                                '<div class="mb-1"><strong>Document:</strong> '+point.document+'</div>'+
                                '<div class="mb-1"><strong>Time:</strong> '+point.time+'</div>'+
                                '<div class="mb-1">'+
                                    '<strong>Coordinates:</strong><br>'+
                                    '<code>'+point.lat.toFixed(6)+', '+point.lng.toFixed(6)+'</code>'+
                                '</div>'+
                            '</div>';
                        
                        marker.bindPopup(popupContent);
                        bounds.extend([point.lat, point.lng]);
                    });
                    
                    // Fit map to bounds
                    map.fitBounds(bounds, { padding: [50, 50] });
                    
                    // Show notification
                    showNotification('Showing access locations for document: <strong>' + documentTitle + '</strong>. Found ' + filteredPoints.length + ' access points.', 'info');
                } else {
                    showNotification('No access locations found for document: <strong>' + documentTitle + '</strong>.', 'warning');
                }
            }
            
            // Function to filter map by user
            function filterMapByUser(userId, userName) {
                // Clear existing markers
                if (markers) {
                    map.removeLayer(markers);
                }
                
                // Create new marker group
                markers = L.layerGroup().addTo(map);
                
                // Filter points for this user
                var filteredPoints = mapPoints.filter(function(point) {
                    return point.userId == userId;
                });
                
                // Add filtered markers
                if (filteredPoints.length > 0) {
                    var bounds = L.latLngBounds();
                    
                    filteredPoints.forEach(function(point) {
                        var marker = L.marker([point.lat, point.lng], {
                            icon: getMarkerIcon(point.action),
                            title: point.title + ' - ' + point.action
                        }).addTo(markers);
                        
                        // Create popup content
                        var popupContent = 
                            '<div class="map-popup">'+
                                '<h6 class="mb-2">'+point.title+'</h6>'+
                                '<div class="mb-1"><span class="badge bg-'+getActionClass(point.action.toLowerCase())+'">'+point.action+'</span></div>'+
                                '<div class="mb-1"><strong>Document:</strong> '+point.document+'</div>'+
                                '<div class="mb-1"><strong>Time:</strong> '+point.time+'</div>'+
                                '<div class="mb-1">'+
                                    '<strong>Coordinates:</strong><br>'+
                                    '<code>'+point.lat.toFixed(6)+', '+point.lng.toFixed(6)+'</code>'+
                                '</div>'+
                            '</div>';
                        
                        marker.bindPopup(popupContent);
                        bounds.extend([point.lat, point.lng]);
                    });
                    
                    // Fit map to bounds
                    map.fitBounds(bounds, { padding: [50, 50] });
                    
                    // Show notification
                    showNotification('Showing access locations for user: <strong>' + userName + '</strong>. Found ' + filteredPoints.length + ' access points.', 'info');
                } else {
                    showNotification('No access locations found for user: <strong>' + userName + '</strong>.', 'warning');
                }
            }
        });
    }
</script>
