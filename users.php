<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to manage users
if (!hasRole(['admin', 'it_admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    redirect('dashboard.php');
}

// Initialize database
$db = new Database();

// Get filter parameters
$role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query based on filters
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

// Add filters
if (!empty($role)) {
    $query .= " AND role = :role";
    $params[':role'] = $role;
}

if (!empty($status)) {
    $query .= " AND status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $query .= " AND (username LIKE :search OR email LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add order by
$query .= " ORDER BY created_at DESC";

// Prepare and execute query
$db->query($query);

// Bind parameters
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

// Get results
$users = $db->resultSet();

// Get role options for filter
$roleOptions = ['court_clerk', 'judge', 'admin', 'it_admin', 'lawyer', 'litigant'];

// Get status options for filter
$statusOptions = ['active', 'inactive', 'suspended'];
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">User Management</h1>
    
    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <?php foreach ($roleOptions as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo ($role === $option) ? 'selected' : ''; ?>>
                            <?php echo ucwords(str_replace('_', ' ', $option)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusOptions as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo ($status === $option) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($option); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Username, email, or name" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Users List</h6>
            <a href="user_add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New User
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['username']; ?></td>
                                <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php elseif ($user['status'] === 'inactive'): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php elseif ($user['status'] === 'suspended'): ?>
                                    <span class="badge bg-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <a href="user_view.php?id=<?php echo $user['user_id']; ?>" class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View User">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="user_edit.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="user_status.php?id=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-sm" data-bs-toggle="tooltip" title="Change Status">
                                        <i class="fas fa-user-cog"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
