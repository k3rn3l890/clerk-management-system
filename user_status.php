<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to manage user status
if (!hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Invalid user ID.', 'danger');
    echo "<script>window.location.href = 'users.php';</script>";
    exit;
}

$userId = (int)$_GET['id'];

// Prevent changing own status
if ($userId === (int)$_SESSION['user_id']) {
    setFlashMessage('You cannot change your own status.', 'danger');
    echo "<script>window.location.href = 'users.php';</script>";
    exit;
}

// Initialize database
$db = new Database();

// Get user details
$db->query("SELECT * FROM users WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$user = $db->single();

if (!$user) {
    setFlashMessage('User not found.', 'danger');
    echo "<script>window.location.href = 'users.php';</script>";
    exit;
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $status = sanitize($_POST['status']);
    $reason = sanitize($_POST['reason']);
    
    // Validate form data
    if (empty($status)) {
        $errors[] = 'Status is required';
    }
    
    if ($status === 'suspended' && empty($reason)) {
        $errors[] = 'Reason is required when suspending a user';
    }
    
    // If no errors, update user status
    if (empty($errors)) {
        try {
            // Get current user data for logging
            $oldUserData = $user;
            
            // Check if status_reason column exists
            $db->query("SHOW COLUMNS FROM users LIKE 'status_reason'");
            $columnExists = $db->single();
            
            if ($columnExists) {
                // If column exists, update with status reason
                $db->query("UPDATE users SET status = :status, status_reason = :reason, updated_at = NOW() WHERE user_id = :user_id");
                $db->bind(':status', $status);
                $db->bind(':reason', $reason);
                $db->bind(':user_id', $userId);
            } else {
                // If column doesn't exist, update without status reason
                $db->query("UPDATE users SET status = :status, updated_at = NOW() WHERE user_id = :user_id");
                $db->bind(':status', $status);
                $db->bind(':user_id', $userId);
            }
            
            $db->execute();
            
            // Log the action
            logAction('User status changed', 'users', $userId, json_encode(['status' => $oldUserData['status']]), json_encode(['status' => $status, 'reason' => $reason]));
            
            // Update user variable to reflect changes
            $user['status'] = $status;
            $user['status_reason'] = $reason;
            
            $success = true;
            setFlashMessage('User status updated successfully.', 'success');
        } catch (Exception $e) {
            $errors[] = 'An error occurred while updating the user status: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Change User Status</h1>
        <div>
            <a href="users.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <a href="user_view.php?id=<?php echo $userId; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> View User
            </a>
            <a href="user_edit.php?id=<?php echo $userId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit User
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        User status updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- User Info Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($user['profile_picture'])): ?>
                        <img class="img-profile rounded-circle mb-3" src="<?php echo $user['profile_picture']; ?>" width="100" height="100">
                        <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 36px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                        <h5 class="font-weight-bold"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h5>
                        <p class="mb-0">
                            <span class="badge <?php echo ($user['status'] === 'active') ? 'bg-success' : (($user['status'] === 'inactive') ? 'bg-secondary' : 'bg-danger'); ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Username:</strong>
                            <span><?php echo $user['username']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Email:</strong>
                            <span><?php echo $user['email']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Role:</strong>
                            <span><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></span>
                        </li>
                        <?php if (!empty($user['status_reason'])): ?>
                        <li class="list-group-item">
                            <strong>Status Reason:</strong>
                            <p class="mt-1 mb-0"><?php echo $user['status_reason']; ?></p>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Change Status Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Change Status</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $userId); ?>">
                        <div class="mb-3">
                            <label for="status" class="form-label required-field">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo ($user['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo ($user['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                            <small class="form-text text-muted">
                                <strong>Active:</strong> User can log in and use the system normally.<br>
                                <strong>Inactive:</strong> User account is temporarily disabled but can be reactivated.<br>
                                <strong>Suspended:</strong> User account is suspended due to policy violations or other issues.
                            </small>
                        </div>
                        
                        <div class="mb-3" id="reasonContainer">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3"><?php echo htmlspecialchars($user['status_reason'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Required when suspending a user. Optional for other status changes.</small>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="users.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle status change to show/hide reason field
    const statusSelect = document.getElementById('status');
    const reasonContainer = document.getElementById('reasonContainer');
    const reasonTextarea = document.getElementById('reason');
    
    function updateReasonField() {
        if (statusSelect.value === 'suspended') {
            reasonContainer.classList.remove('d-none');
            reasonTextarea.setAttribute('required', 'required');
        } else {
            reasonContainer.classList.remove('d-none'); // Always show, but not required
            reasonTextarea.removeAttribute('required');
        }
    }
    
    // Initial check
    updateReasonField();
    
    // Add event listener for changes
    statusSelect.addEventListener('change', updateReasonField);
});
</script>

<?php require_once 'includes/footer.php'; ?>
