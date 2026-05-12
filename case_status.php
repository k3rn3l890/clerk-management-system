<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to change case status
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid case ID.', 'danger');
    redirect('cases.php');
}

$caseId = (int)$_GET['id'];
$db = new Database();

// Get case details
$db->query("SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
            FROM cases c 
            LEFT JOIN users u ON c.created_by = u.user_id 
            WHERE c.case_id = :case_id");
$db->bind(':case_id', $caseId);
$case = $db->single();

if (!$case) {
    setFlashMessage('Case not found.', 'danger');
    redirect('cases.php');
}

// Define available status options
$statusOptions = [
    'pending' => 'Pending',
    'active' => 'Active',
    'on_hold' => 'On Hold',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    'dismissed' => 'Dismissed',
    'archived' => 'Archived'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if (empty($newStatus) || !array_key_exists($newStatus, $statusOptions)) {
        setFlashMessage('Please select a valid status.', 'danger');
    } else {
        try {
            // Update case status
            $db->query("UPDATE cases SET 
                        status = :status,
                        updated_at = NOW()
                        WHERE case_id = :case_id");
            $db->bind(':status', $newStatus);
            $db->bind(':case_id', $caseId);
            $success = $db->execute();

            if ($success) {
                // Check if case_status_history table exists
                $db->query("SHOW TABLES LIKE 'case_status_history'");
                $tableExists = (bool)$db->single();
                
                if ($tableExists) {
                    // Insert into status history
                    $db->query("INSERT INTO case_status_history 
                                (case_id, old_status, new_status, reason, remarks, changed_by, changed_at) 
                                VALUES 
                                (:case_id, :old_status, :new_status, :reason, :remarks, :changed_by, NOW())");
                    $db->bind(':case_id', $caseId);
                    $db->bind(':old_status', $case['status']);
                    $db->bind(':new_status', $newStatus);
                    $db->bind(':reason', $reason);
                    $db->bind(':remarks', $remarks);
                    $db->bind(':changed_by', $_SESSION['user_id']);
                    $db->execute();
                }
                
                // Get all relevant users to notify
                $usersToNotify = [];
                
                // Get case parties
                $db->query("SELECT DISTINCT user_id FROM case_parties WHERE case_id = :case_id AND user_id IS NOT NULL");
                $db->bind(':case_id', $caseId);
                $parties = $db->resultSet();
                foreach ($parties as $party) {
                    $usersToNotify[] = $party['user_id'];
                }
                
                // Get case lawyers
                $db->query("SELECT DISTINCT l.user_id 
                           FROM case_lawyers cl 
                           JOIN lawyers l ON cl.lawyer_id = l.lawyer_id 
                           WHERE cl.case_id = :case_id AND l.user_id IS NOT NULL");
                $db->bind(':case_id', $caseId);
                $lawyers = $db->resultSet();
                foreach ($lawyers as $lawyer) {
                    $usersToNotify[] = $lawyer['user_id'];
                }
                
                // Get assigned judge if exists
                if (!empty($case['assigned_judge'])) {
                    $usersToNotify[] = $case['assigned_judge'];
                }
                
                // Add admin and court clerk users
                $db->query("SELECT user_id FROM users WHERE role IN ('admin', 'court_clerk') AND user_id IS NOT NULL");
                $staff = $db->resultSet();
                foreach ($staff as $member) {
                    $usersToNotify[] = $member['user_id'];
                }
                
                // Remove duplicates and null values
                $usersToNotify = array_filter(array_unique($usersToNotify));
                
                // Create notifications
                $notificationTitle = 'Case Status Updated';
                $notificationMessage = "The status of case {$case['case_number']} has been updated from {$case['status']} to {$newStatus}.";
                
                // Only notify existing users
                if (!empty($usersToNotify)) {
                    foreach ($usersToNotify as $userId) {
                        try {
                            createNotification($userId, $notificationTitle, $notificationMessage);
                        } catch (Exception $e) {
                            // Log notification error but continue with the process
                            error_log("Failed to create notification for user {$userId}: " . $e->getMessage());
                        }
                    }
                }
                
                setFlashMessage('Case status updated successfully.', 'success');
                redirect("case_view.php?id={$caseId}");
            } else {
                setFlashMessage('Error updating case status. Please try again.', 'danger');
            }
        } catch (Exception $e) {
            setFlashMessage('Error updating case status: ' . $e->getMessage(), 'danger');
        }
    }
}

// Get status history
try {
    $db->query("SELECT h.*, CONCAT(u.first_name, ' ', u.last_name) as changed_by_name 
                FROM case_status_history h 
                JOIN users u ON h.changed_by = u.user_id 
                WHERE h.case_id = :case_id 
                ORDER BY h.changed_at DESC");
    $db->bind(':case_id', $caseId);
    $statusHistory = $db->resultSet();
} catch (Exception $e) {
    $statusHistory = [];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Change Case Status: <?php echo $case['case_number']; ?>
        </h1>
        <a href="case_view.php?id=<?php echo $caseId; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Case
        </a>
    </div>

    <div class="row">
        <!-- Status Change Form -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Update Case Status</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="currentStatus" class="form-label">Current Status</label>
                            <input type="text" class="form-control bg-light" id="currentStatus" 
                                   value="<?php echo ucfirst($case['status']); ?>" readonly disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <?php if ($value !== $case['status']): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Change</label>
                            <select class="form-control" id="reason" name="reason" required>
                                <option value="">Select Reason</option>
                                <option value="court_order">Court Order</option>
                                <option value="settlement">Settlement</option>
                                <option value="procedural">Procedural Requirement</option>
                                <option value="party_request">Party Request</option>
                                <option value="administrative">Administrative</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Additional Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                      placeholder="Enter any additional details about the status change"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Update Status
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Status History -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status History</h6>
                </div>
                <div class="card-body">
                    <?php if (count($statusHistory) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Changed From</th>
                                    <th>Changed To</th>
                                    <th>Reason</th>
                                    <th>Changed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statusHistory as $history): ?>
                                <tr>
                                    <td><?php echo formatDateTime($history['changed_at']); ?></td>
                                    <td><?php echo ucfirst($history['old_status']); ?></td>
                                    <td><?php echo ucfirst($history['new_status']); ?></td>
                                    <td>
                                        <?php echo ucfirst(str_replace('_', ' ', $history['reason'])); ?>
                                        <?php if ($history['remarks']): ?>
                                        <i class="fas fa-info-circle" data-toggle="tooltip" 
                                           title="<?php echo htmlspecialchars($history['remarks']); ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $history['changed_by_name']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No status changes recorded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<?php require_once 'includes/footer.php'; ?> 