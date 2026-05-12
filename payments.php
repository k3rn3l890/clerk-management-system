<?php
require_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if user has permission to access payments
if (!hasRole(['court_clerk', 'admin'])) {
    setFlashMessage('You do not have permission to access this page.', 'danger');
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit;
}

// Initialize database
$db = new Database();

// Handle payment actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $paymentId = (int)$_GET['id'];
    
    if ($action === 'mark_paid' && hasRole(['court_clerk', 'admin'])) {
        try {
            // Get current payment data for logging
            $db->query("SELECT * FROM payments WHERE payment_id = :payment_id");
            $db->bind(':payment_id', $paymentId);
            $oldPayment = $db->single();
            
            if ($oldPayment) {
                // Update payment status
                $db->query("UPDATE payments SET 
                            status = 'paid', 
                            paid_date = NOW(),
                            updated_at = NOW() 
                            WHERE payment_id = :payment_id");
                $db->bind(':payment_id', $paymentId);
                $db->execute();
                
                // Log the action
                logAction('Payment marked as paid', 'payments', $paymentId, json_encode(['status' => $oldPayment['status']]), json_encode(['status' => 'paid']));
                
                setFlashMessage('Payment marked as paid successfully.', 'success');
            } else {
                setFlashMessage('Payment not found.', 'danger');
            }
        } catch (Exception $e) {
            setFlashMessage('An error occurred: ' . $e->getMessage(), 'danger');
        }
    } elseif ($action === 'delete' && hasRole(['admin'])) {
        try {
            // Get current payment data for logging
            $db->query("SELECT * FROM payments WHERE payment_id = :payment_id");
            $db->bind(':payment_id', $paymentId);
            $oldPayment = $db->single();
            
            if ($oldPayment) {
                // Delete payment
                $db->query("DELETE FROM payments WHERE payment_id = :payment_id");
                $db->bind(':payment_id', $paymentId);
                $db->execute();
                
                // Log the action
                logAction('Payment deleted', 'payments', $paymentId, json_encode($oldPayment), null);
                
                setFlashMessage('Payment deleted successfully.', 'success');
            } else {
                setFlashMessage('Payment not found.', 'danger');
            }
        } catch (Exception $e) {
            setFlashMessage('An error occurred: ' . $e->getMessage(), 'danger');
        }
    }
    
    // Redirect to prevent resubmission
    redirect('payments.php');
}

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$paymentType = isset($_GET['payment_type']) ? sanitize($_GET['payment_type']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';

// Build query based on filters
$query = "SELECT p.*, c.case_number, c.case_title 
          FROM payments p 
          LEFT JOIN cases c ON p.case_id = c.case_id 
          WHERE 1=1";
$params = [];

if (!empty($status)) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status;
}

if ($caseId > 0) {
    $query .= " AND p.case_id = :case_id";
    $params[':case_id'] = $caseId;
}

if (!empty($paymentType)) {
    $query .= " AND p.payment_type = :payment_type";
    $params[':payment_type'] = $paymentType;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(p.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(p.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

$query .= " ORDER BY p.created_at DESC";

// Execute query
$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}
$payments = $db->resultSet();

// Get payment types for filter
$db->query("SELECT DISTINCT payment_type FROM payments ORDER BY payment_type");
$paymentTypes = $db->resultSet();

// Get cases for filter
$db->query("SELECT case_id, case_number, case_title FROM cases ORDER BY case_number");
$cases = $db->resultSet();

// Check if payments table exists
$tableExists = false;
try {
    $db->query("SHOW TABLES LIKE 'payments'");
    $result = $db->single();
    $tableExists = !empty($result);
} catch (Exception $e) {
    // Table doesn't exist
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Payments</h1>
        <div>
            <?php if (hasRole(['court_clerk', 'admin'])): ?>
            <a href="payment_add.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Record New Payment
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="payment_report.php" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel"></i> Export Report
            </a>
        </div>
    </div>
    
    <?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> Payments Table Not Found</h5>
        <p>The payments table does not exist in the database. Please run the script to create it.</p>
        <a href="create_payments_table.php" class="btn btn-warning">Create Payments Table</a>
    </div>
    <?php else: ?>
    
    <!-- Flash Messages -->
    <?php displayFlashMessage(); ?>
    
    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filter Payments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="payments.php" method="get">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo ($status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    <option value="overdue" <?php echo ($status === 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="payment_type" class="form-label">Payment Type</label>
                                <select class="form-select" id="payment_type" name="payment_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($paymentTypes as $type): ?>
                                    <option value="<?php echo $type['payment_type']; ?>" <?php echo ($paymentType === $type['payment_type']) ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $type['payment_type'])); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="case_id" class="form-label">Case</label>
                                <select class="form-select" id="case_id" name="case_id">
                                    <option value="">All Cases</option>
                                    <?php foreach ($cases as $case): ?>
                                    <option value="<?php echo $case['case_id']; ?>" <?php echo ($caseId === (int)$case['case_id']) ? 'selected' : ''; ?>>
                                        <?php echo $case['case_number'] . ' - ' . $case['case_title']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="payments.php" class="btn btn-secondary">Clear Filters</a>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Payment Records</h6>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
            <div class="alert alert-info">
                No payments found. <?php echo (!empty($status) || $caseId > 0 || !empty($paymentType) || !empty($dateFrom) || !empty($dateTo)) ? 'Try clearing some filters.' : ''; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Case</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Paid Date</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['payment_id']; ?></td>
                            <td>
                                <a href="case_view.php?id=<?php echo $payment['case_id']; ?>">
                                    <?php echo $payment['case_number']; ?>
                                </a>
                                <small class="d-block text-muted"><?php echo $payment['case_title']; ?></small>
                            </td>
                            <td><?php echo ucwords(str_replace('_', ' ', $payment['payment_type'])); ?></td>
                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                            <td>
                                <?php
                                $statusClass = '';
                                switch ($payment['status']) {
                                    case 'paid':
                                        $statusClass = 'success';
                                        break;
                                    case 'pending':
                                        $statusClass = 'warning';
                                        break;
                                    case 'overdue':
                                        $statusClass = 'danger';
                                        break;
                                    default:
                                        $statusClass = 'secondary';
                                }
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($payment['due_date']); ?></td>
                            <td><?php echo $payment['paid_date'] ? formatDate($payment['paid_date']) : '-'; ?></td>
                            <td><?php echo formatDateTime($payment['created_at']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="payment_view.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($payment['status'] !== 'paid' && hasRole(['court_clerk', 'admin'])): ?>
                                    <a href="payments.php?action=mark_paid&id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to mark this payment as paid?')">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (hasRole(['admin'])): ?>
                                    <a href="payments.php?action=delete&id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this payment? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
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
