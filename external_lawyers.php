<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Function to check if a column exists in a table
function columnExists($db, $tableName, $columnName) {
    try {
        $db->query("SHOW COLUMNS FROM $tableName LIKE :columnName");
        $db->bind(':columnName', $columnName);
        return $db->single() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

// Handle status change
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $lawyerId = (int)$_GET['id'];
    $newStatus = $_GET['status'] === 'active' ? 'inactive' : 'active';
    
    try {
        $db->query("UPDATE external_lawyers SET status = :status WHERE external_lawyer_id = :id");
        $db->bind(':status', $newStatus);
        $db->bind(':id', $lawyerId);
        $db->execute();
        
        setFlashMessage('Lawyer status updated successfully', 'success');
    } catch (Exception $e) {
        setFlashMessage('Error updating lawyer status: ' . $e->getMessage(), 'danger');
    }
    
    redirect('external_lawyers.php');
}

// Handle lawyer deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $lawyerId = (int)$_GET['id'];
    
    try {
        // Check if lawyer is assigned to any party
        $db->query("SELECT COUNT(*) as count FROM party_lawyers WHERE external_lawyer_id = :id");
        $db->bind(':id', $lawyerId);
        $assignmentCount = $db->single()['count'];
        
        if ($assignmentCount > 0) {
            setFlashMessage('Cannot delete lawyer: This lawyer is assigned to one or more parties', 'danger');
        } else {
            $db->query("DELETE FROM external_lawyers WHERE external_lawyer_id = :id");
            $db->bind(':id', $lawyerId);
            $db->execute();
            
            setFlashMessage('Lawyer deleted successfully', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Error deleting lawyer: ' . $e->getMessage(), 'danger');
    }
    
    redirect('external_lawyers.php');
}

// Get all external lawyers
try {
    // Check if status column exists
    $statusExists = columnExists($db, 'external_lawyers', 'status');
    
    if ($statusExists) {
        $db->query("SELECT * FROM external_lawyers ORDER BY lawyer_name");
    } else {
        $db->query("SELECT * FROM external_lawyers ORDER BY lawyer_name");
    }
    $lawyers = $db->resultSet();
} catch (Exception $e) {
    $lawyers = [];
    setFlashMessage('Error retrieving lawyers: ' . $e->getMessage(), 'danger');
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">External Lawyers</h1>
        <a href="external_lawyer_add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New Lawyer
        </a>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All External Lawyers</h6>
        </div>
        <div class="card-body">
            <?php if (empty($lawyers)): ?>
                <div class="alert alert-info">
                    No external lawyers found. <a href="external_lawyer_add.php">Add a new external lawyer</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Bar Number</th>
                                <th>Law Firm</th>
                                <th>Contact Info</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lawyers as $lawyer): ?>
                                <tr>
                                    <td><?php echo $lawyer['lawyer_name']; ?></td>
                                    <td><?php echo $lawyer['bar_number'] ?: 'N/A'; ?></td>
                                    <td><?php echo $lawyer['law_firm'] ?: 'N/A'; ?></td>
                                    <td><?php echo $lawyer['contact_info'] ?: 'N/A'; ?></td>
                                    <td>
                                        <?php if (isset($lawyer['status'])): ?>
                                            <span class="badge <?php echo $lawyer['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo ucfirst($lawyer['status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="external_lawyer_edit.php?id=<?php echo $lawyer['external_lawyer_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if (isset($lawyer['status'])): ?>
                                            <a href="external_lawyers.php?action=toggle_status&id=<?php echo $lawyer['external_lawyer_id']; ?>&status=<?php echo $lawyer['status']; ?>" 
                                               class="btn btn-sm <?php echo $lawyer['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                               onclick="return confirm('Are you sure you want to <?php echo $lawyer['status'] === 'active' ? 'deactivate' : 'activate'; ?> this lawyer?')">
                                                <i class="fas <?php echo $lawyer['status'] === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="external_lawyers.php?action=delete&id=<?php echo $lawyer['external_lawyer_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this lawyer?')">
                                            <i class="fas fa-trash"></i>
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