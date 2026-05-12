<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view audit logs
if (!hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit;
}

// Initialize database
$db = new Database();

// Get filter parameters
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$entityType = isset($_GET['entity_type']) ? sanitize($_GET['entity_type']) : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// Build query based on filters
$query = "SELECT al.*, 
          CONCAT(u.first_name, ' ', u.last_name) as user_name 
          FROM audit_logs al 
          LEFT JOIN users u ON al.user_id = u.user_id
          WHERE 1=1";

$params = [];

if (!empty($action)) {
    $query .= " AND al.action LIKE :action";
    $params[':action'] = "%$action%";
}

if (!empty($entityType)) {
    $query .= " AND al.entity_type = :entity_type";
    $params[':entity_type'] = $entityType;
}

if ($userId > 0) {
    $query .= " AND al.user_id = :user_id";
    $params[':user_id'] = $userId;
}

if (!empty($startDate)) {
    $query .= " AND DATE(al.created_at) >= :start_date";
    $params[':start_date'] = $startDate;
}

if (!empty($endDate)) {
    $query .= " AND DATE(al.created_at) <= :end_date";
    $params[':end_date'] = $endDate;
}

// Order by most recent first
$query .= " ORDER BY al.created_at DESC";

// Add limit to prevent performance issues
$query .= " LIMIT 500";

// Execute query
$db->query($query);

// Bind parameters
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

// Get results
$logs = $db->resultSet();

// Get users for filter dropdown
$db->query("SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name");
$users = $db->resultSet();

// Get distinct actions for filter dropdown
$db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$actions = $db->resultSet();

// Get distinct entity types for filter dropdown
$db->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type");
$entityTypes = $db->resultSet();
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Audit Logs</h1>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="action" class="form-label">Action</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $act): ?>
                        <option value="<?php echo $act['action']; ?>" <?php echo ($action === $act['action']) ? 'selected' : ''; ?>>
                            <?php echo $act['action']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="entityType" class="form-label">Entity Type</label>
                    <select class="form-select" id="entityType" name="entity_type">
                        <option value="">All Types</option>
                        <?php foreach ($entityTypes as $type): ?>
                        <option value="<?php echo $type['entity_type']; ?>" <?php echo ($entityType === $type['entity_type']) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type['entity_type']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="userId" class="form-label">User</label>
                    <select class="form-select" id="userId" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo ($userId === (int)$user['user_id']) ? 'selected' : ''; ?>>
                            <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="startDate" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label for="endDate" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="audit_logs.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Audit Logs Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Audit Logs</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered <?php echo (count($logs) > 0) ? 'datatable' : ''; ?>" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity Type</th>
                            <th>Entity ID</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                <td><?php echo $log['user_name'] ?: 'System'; ?></td>
                                <td><?php echo $log['action']; ?></td>
                                <td><?php echo ucfirst($log['entity_type']); ?></td>
                                <td><?php echo $log['entity_id']; ?></td>
                                <td><?php echo $log['ip_address']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm view-details" data-bs-toggle="modal" data-bs-target="#logDetailsModal" 
                                            data-old="<?php echo htmlspecialchars($log['old_value'] ?: ''); ?>" 
                                            data-new="<?php echo htmlspecialchars($log['new_value'] ?: ''); ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No audit logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logDetailsModalLabel">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Old Value</h6>
                        <pre id="oldValueContent" class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>New Value</h6>
                        <pre id="newValueContent" class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view details button click
    document.querySelectorAll('.view-details').forEach(function(button) {
        button.addEventListener('click', function() {
            var oldValue = this.getAttribute('data-old');
            var newValue = this.getAttribute('data-new');
            
            // Try to parse and format JSON values
            try {
                if (oldValue && oldValue.trim().startsWith('{')) {
                    oldValue = JSON.stringify(JSON.parse(oldValue), null, 2);
                }
            } catch (e) {
                // Not valid JSON, leave as is
            }
            
            try {
                if (newValue && newValue.trim().startsWith('{')) {
                    newValue = JSON.stringify(JSON.parse(newValue), null, 2);
                }
            } catch (e) {
                // Not valid JSON, leave as is
            }
            
            document.getElementById('oldValueContent').textContent = oldValue || 'N/A';
            document.getElementById('newValueContent').textContent = newValue || 'N/A';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
