<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to view user details
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

// Get additional details based on role
$roleSpecificDetails = [];

// Check if the tables exist before querying
try {
    if ($user['role'] === 'lawyer') {
        // Check if lawyers table exists
        $db->query("SHOW TABLES LIKE 'lawyers'");
        $lawyersTableExists = $db->single();
        
        if ($lawyersTableExists) {
            $db->query("SELECT * FROM lawyers WHERE user_id = :user_id");
            $db->bind(':user_id', $userId);
            $lawyerDetails = $db->single();
            
            if ($lawyerDetails) {
                $roleSpecificDetails = [
                    'Bar Number' => $lawyerDetails['bar_number'] ?? 'Not provided',
                    'Specialization' => $lawyerDetails['specialization'] ?? 'Not provided',
                    'Law Firm' => $lawyerDetails['law_firm'] ?? 'Not provided'
                ];
            }
        } else {
            // If table doesn't exist, provide basic role info
            $roleSpecificDetails = [
                'Role Type' => 'Lawyer',
                'Description' => 'Legal representative in court cases'
            ];
        }
    } elseif ($user['role'] === 'judge') {
        // Check if judges table exists
        $db->query("SHOW TABLES LIKE 'judges'");
        $judgesTableExists = $db->single();
        
        if ($judgesTableExists) {
            $db->query("SELECT * FROM judges WHERE user_id = :user_id");
            $db->bind(':user_id', $userId);
            $judgeDetails = $db->single();
            
            if ($judgeDetails) {
                $roleSpecificDetails = [
                    'Court Division' => $judgeDetails['court_division'] ?? 'Not provided',
                    'Appointment Date' => isset($judgeDetails['appointment_date']) ? formatDate($judgeDetails['appointment_date']) : 'Not provided'
                ];
            }
        } else {
            // If table doesn't exist, provide basic role info
            $roleSpecificDetails = [
                'Role Type' => 'Judge',
                'Description' => 'Judicial officer presiding over court cases'
            ];
        }
    } elseif ($user['role'] === 'court_clerk') {
        $roleSpecificDetails = [
            'Role Type' => 'Court Clerk',
            'Description' => 'Administrative staff managing court records and proceedings'
        ];
    } elseif ($user['role'] === 'litigant') {
        $roleSpecificDetails = [
            'Role Type' => 'Litigant',
            'Description' => 'Party involved in a legal case'
        ];
    } elseif ($user['role'] === 'admin' || $user['role'] === 'it_admin') {
        $roleSpecificDetails = [
            'Role Type' => ucfirst(str_replace('_', ' ', $user['role'])),
            'Description' => 'System administrator with full access privileges'
        ];
    }
} catch (Exception $e) {
    // If any error occurs, provide basic info based on role
    $roleSpecificDetails = [
        'Role Type' => ucfirst(str_replace('_', ' ', $user['role'])),
        'Description' => 'User of the Court Clerk Management System'
    ];
}

// Get recent activity - check if audit_logs table exists first
try {
    $db->query("SHOW TABLES LIKE 'audit_logs'");
    $auditLogsTableExists = $db->single();
    
    if ($auditLogsTableExists) {
        $db->query("SELECT * FROM audit_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
        $db->bind(':user_id', $userId);
        $recentActivity = $db->resultSet();
    } else {
        $recentActivity = [];
    }
} catch (Exception $e) {
    // If any error occurs, set empty array
    $recentActivity = [];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Details</h1>
        <div>
            <a href="users.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <a href="user_edit.php?id=<?php echo $userId; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit User
            </a>
            <?php if ($userId != $_SESSION['user_id']): ?>
            <a href="user_status.php?id=<?php echo $userId; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-user-cog"></i> Change Status
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- User Profile Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($user['profile_picture'])): ?>
                        <img class="img-profile rounded-circle mb-3" src="<?php echo $user['profile_picture']; ?>" width="150" height="150">
                        <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 150px; height: 150px; font-size: 48px;">
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
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Phone:</strong>
                            <span><?php echo isset($user['phone']) && !empty($user['phone']) ? $user['phone'] : 'Not provided'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Created:</strong>
                            <span><?php echo formatDateTime($user['created_at']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>Last Login:</strong>
                            <span><?php echo isset($user['last_login']) && !empty($user['last_login']) ? formatDateTime($user['last_login']) : 'Never'; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <?php if (!empty($roleSpecificDetails)): ?>
            <!-- Role-specific Details -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?> Details</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($roleSpecificDetails as $label => $value): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong><?php echo $label; ?>:</strong>
                            <span><?php echo $value ?: 'Not provided'; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-8">
            <!-- Recent Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php if (count($recentActivity) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Action</th>
                                    <th>Entity Type</th>
                                    <th>Entity ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td><?php echo formatDateTime($activity['created_at']); ?></td>
                                    <td><?php echo $activity['action']; ?></td>
                                    <td><?php echo ucfirst($activity['entity_type']); ?></td>
                                    <td><?php echo $activity['entity_id']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center">No recent activity found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
